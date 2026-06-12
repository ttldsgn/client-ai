<?php
defined( 'ABSPATH' ) || exit;

/**
 * Plugin activation: Create custom database tables and register cron.
 */
function aicb_activate() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $log_table = $wpdb->prefix . AICB_LOG_TABLE;
    $qa_table  = $wpdb->prefix . AICB_QA_TABLE;

    $logs = "CREATE TABLE IF NOT EXISTS {$log_table} (
        id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        session_id  VARCHAR(64)     NOT NULL DEFAULT '',
        question    TEXT            NOT NULL,
        answer      TEXT            NOT NULL,
        page_id     BIGINT UNSIGNED NOT NULL DEFAULT 0,
        ip_hash     VARCHAR(64)     NOT NULL DEFAULT '',
        provider    VARCHAR(32)     NOT NULL DEFAULT '',
        model       VARCHAR(128)    NOT NULL DEFAULT '',
        feedback    TINYINT(1)      DEFAULT NULL,
        created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY created_at (created_at)
    ) $charset;";

    $qa = "CREATE TABLE IF NOT EXISTS {$qa_table} (
        id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        question    VARCHAR(500)    NOT NULL,
        answer      TEXT            NOT NULL,
        active      TINYINT(1)      NOT NULL DEFAULT 1,
        created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $logs );
    dbDelta( $qa );

    foreach ( aicb_default_options() as $key => $val ) {
        if ( false === get_option( 'aicb_' . $key ) ) {
            update_option( 'aicb_' . $key, $val );
        }
    }

    if ( ! wp_next_scheduled( 'aicb_log_cleanup_cron' ) ) {
        wp_schedule_event( time(), 'daily', 'aicb_log_cleanup_cron' );
    }
}

/**
 * Plugin deactivation: Clear scheduled cron tasks cleanly.
 */
function aicb_deactivate() {
    $timestamp = wp_next_scheduled( 'aicb_log_cleanup_cron' );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, 'aicb_log_cleanup_cron' );
    }
}

/**
 * One-time database migration: add feedback column if missing.
 * Runs on admin_init and activation for existing installations.
 */
function aicb_maybe_add_feedback_column() {
    global $wpdb;
    $table = $wpdb->prefix . AICB_LOG_TABLE;
    $row = $wpdb->get_results( "SHOW COLUMNS FROM {$table} LIKE 'feedback'" );
    if ( empty( $row ) ) {
        $wpdb->query( "ALTER TABLE {$table} ADD COLUMN feedback TINYINT(1) DEFAULT NULL AFTER model" );
    }
}
add_action( 'admin_init', 'aicb_maybe_add_feedback_column' );

/**
 * Create or append logs to custom database tables.
 */
function aicb_log( $session_id, $question, $answer, $page_id, $ip_hash, $provider = '', $model = '' ) {
    global $wpdb;
    $table = $wpdb->prefix . AICB_LOG_TABLE;
    $wpdb->insert(
        $table,
        [
            'session_id' => sanitize_text_field( $session_id ),
            'question'   => $question,
            'answer'     => $answer,
            'page_id'    => (int) $page_id,
            'ip_hash'    => $ip_hash,
            'provider'   => sanitize_key( $provider ),
            'model'      => sanitize_text_field( $model ),
            'created_at' => current_time( 'mysql' ),
        ],
        [ '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' ]
    );
    return $wpdb->insert_id;
}

/**
 * Cron validation and data retention cleaner.
 */
add_action( 'aicb_log_cleanup_cron', 'aicb_perform_log_cleanup' );
function aicb_perform_log_cleanup() {
    global $wpdb;
    $days = (int) aicb_opt( 'log_retention_days' );
    if ( $days <= 0 ) return; 
    $table = $wpdb->prefix . AICB_LOG_TABLE;
    $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)", $days ) );
}

/**
 * Validate page configurations.
 */
function aicb_is_page_allowed_for_chatbot( $page_id ) {
    if ( ! $page_id ) return false;
    $post_type = get_post_type( $page_id );
    if ( ! $post_type ) return false;
    $allowed_types = (array) aicb_opt( 'indexed_post_types' );
    if ( ! in_array( $post_type, $allowed_types, true ) ) return false;

    $meta_val = get_post_meta( $page_id, '_aicb_include_kb', true );
    $mode     = aicb_opt( 'indexing_mode' );
    return ( $mode === 'opt-in' ) ? ( '1' === $meta_val ) : ( '0' !== $meta_val );
}

/**
 * Lazy summaries caching generator.
 */
function aicb_get_page_context( $page_id ) {
    if ( ! aicb_is_page_allowed_for_chatbot( $page_id ) ) return '';
    if ( aicb_opt( 'enable_cache' ) ) {
        $cache_version = (int) get_option( 'aicb_cache_version', 0 );
        $cached_digest = get_post_meta( $page_id, '_aicb_page_digest', true );
        $cached_time   = (int) get_post_meta( $page_id, '_aicb_digest_timestamp', true );
        $duration_hours = (int) aicb_opt( 'cache_duration' );
        $is_expired     = false;
        if ( $duration_hours > 0 && $cached_time > 0 ) {
            $is_expired = ( ( time() - $cached_time ) > ( $duration_hours * HOUR_IN_SECONDS ) );
        }
        if ( ! empty( $cached_digest ) && $cached_time >= $cache_version && ! $is_expired ) {
            return $cached_digest;
        }
    }

    $raw_content = aicb_get_raw_page_content( $page_id );
    if ( empty( $raw_content ) || preg_match( '/^\[[^\]]+\]$/', $raw_content ) ) {
        return $raw_content;
    }

    $provider = aicb_opt( 'provider' );
    $model    = aicb_opt( 'model' );
    $system   = "You are a factual summarization agent. Extract a dense, objective, structured digest of the provided text. Focus only on facts, customer service details, pricing, policies, and schedules. Avoid conversational filler or metadata. Return only the plain-text factual details.";
    $prompt   = "Title: " . get_the_title( $page_id ) . "\n\nContent:\n" . $raw_content;
    $result   = aicb_call_ai( $provider, $model, $system, $prompt, 300 );

    if ( is_wp_error( $result ) ) {
        error_log( 'AICB Cache Generation Failure: ' . $result->get_error_message() );
        update_post_meta( $page_id, '_aicb_page_digest', $raw_content );
        update_post_meta( $page_id, '_aicb_digest_timestamp', time() );
        return $raw_content; 
    }

    $new_digest = sanitize_textarea_field( $result['answer'] );
    update_post_meta( $page_id, '_aicb_page_digest', $new_digest );
    update_post_meta( $page_id, '_aicb_digest_timestamp', time() );
    return $new_digest;
}

/**
 * Retrieve clean page text contents.
 */
function aicb_get_raw_page_content( $page_id ) {
    $post = get_post( $page_id );
    if ( ! $post ) return '';
    $content = $post->post_content;
    $raw_content = wp_strip_all_tags( apply_filters( 'the_content', $content ) );
    if ( empty( trim( $raw_content ) ) ) {
        $raw_content = wp_strip_all_tags( $content );
    }
    if ( empty( trim( $raw_content ) ) ) {
        $response = wp_remote_get( get_permalink( $page_id ), [ 'timeout' => 10, 'sslverify' => false ] );
        if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
            $html = wp_remote_retrieve_body( $response );
            if ( preg_match( '/<body[^>]*>(.*?)<\/body>/is', $html, $matches ) ) $html = $matches[1];
            $html = preg_replace( '/<script[^>]*>(.*?)<\/script>/is', '', $html );
            $html = preg_replace( '/<style[^>]*>(.*?)<\/style>/is', '', $html );
            $raw_content = wp_strip_all_tags( $html );
        }
    }
    return substr( trim( $raw_content ), 0, 4000 );
}

/**
 * Strip search triggers for permissive SQL queries.
 */
function aicb_clean_question_for_search( $question ) {
    $question = strtolower( $question );
    $question = preg_replace( '/[^\w\s]/u', '', $question );
    $stop_words = [ 'what', 'is', 'are', 'your', 'about', 'how', 'do', 'you', 'can', 'please', 'tell', 'me', 'the', 'a', 'an', 'and', 'or', 'but' ];
    $words = explode( ' ', $question );
    $keywords = [];
    foreach ( $words as $word ) {
        $word = trim( $word );
        if ( strlen( $word ) > 2 && ! in_array( $word, $stop_words, true ) ) $keywords[] = $word;
    }
    return empty( $keywords ) ? $question : implode( ' ', $keywords );
}

/**
 * Modify search constraints dynamically to widen hit rates.
 */
function aicb_permissive_search_filter( $search, $wp_query ) {
    if ( ! empty( $search ) ) {
        $search = str_replace( ' AND ', ' OR ', $search );
    }
    return $search;
}

/**
 * Primary multi-page contextual fetch loop (Strategy A).
 */
function aicb_retrieve_relevant_contexts( $question, $current_page_id = 0 ) {
    $allowed_types = (array) aicb_opt( 'indexed_post_types' );
    if ( empty( $allowed_types ) ) return '';
    $contexts   = [];
    $pulled_ids = [];

    if ( $current_page_id > 0 && aicb_is_page_allowed_for_chatbot( $current_page_id ) ) {
        $current_context = aicb_get_raw_page_content( $current_page_id );
        if ( ! empty( $current_context ) ) {
            $contexts[]   = "--- ACTIVE CURRENT PAGE REFERENCE (Title: " . get_the_title( $current_page_id ) . " | Link: " . get_permalink( $current_page_id ) . ") ---\n" . $current_context;
            $pulled_ids[] = $current_page_id;
        }
    }

    $args = [ 'post_type' => $allowed_types, 'posts_per_page' => 100, 'post_status' => 'publish', 'fields' => 'ids', 'post__not_in' => $pulled_ids ];
    $query = new WP_Query( $args );
    if ( $query->have_posts() ) {
        foreach ( $query->posts as $post_id ) {
            if ( ! aicb_is_page_allowed_for_chatbot( $post_id ) ) continue;
            $context = aicb_get_page_context( $post_id );
            if ( ! empty( $context ) ) {
                $contexts[] = "--- ASSOCIATED KNOWLEDGE REFERENCE (Title: " . get_the_title( $post_id ) . " | Link: " . get_permalink( $post_id ) . ") ---\n" . $context;
            }
        }
    }
    return empty( $contexts ) ? '' : implode( "\n\n", $contexts );
}

/**
 * Calculate US federal holidays for a given year using PHP date rules (fallback).
 */
function aicb_calculate_us_federal_holidays( $year ) {
    $holidays = [
        [ 'label' => "New Year's Day",            'rule' => 'January 1' ],
        [ 'label' => 'Martin Luther King Jr. Day', 'rule' => 'third monday of january' ],
        [ 'label' => "Presidents' Day",            'rule' => 'third monday of february' ],
        [ 'label' => 'Memorial Day',               'rule' => 'last monday of may' ],
        [ 'label' => 'Juneteenth',                 'rule' => 'June 19' ],
        [ 'label' => 'Independence Day',           'rule' => 'July 4' ],
        [ 'label' => 'Labor Day',                  'rule' => 'first monday of september' ],
        [ 'label' => 'Columbus Day',               'rule' => 'second monday of october' ],
        [ 'label' => 'Veterans Day',               'rule' => 'November 11' ],
        [ 'label' => 'Thanksgiving Day',           'rule' => 'fourth thursday of november' ],
        [ 'label' => 'Christmas Day',              'rule' => 'December 25' ],
    ];

    $entries = [];
    foreach ( $holidays as $h ) {
        $dt = new DateTime( $h['rule'] . ' ' . $year );
        $entries[] = [
            'date'       => $dt->format( 'Y-m-d' ),
            'label'      => $h['label'],
            'status'     => 'closed',
            'hours_open' => '',
            'hours_close'=> '',
        ];
    }
    return $entries;
}

/**
 * Retrieve the list of available countries from Nager.Date API, with a fallback list.
 */
function aicb_get_available_countries() {
    $cache_key = 'aicb_available_countries';
    $cached    = get_transient( $cache_key );
    if ( false !== $cached && is_array( $cached ) ) {
        return $cached;
    }

    $url      = 'https://date.nager.at/api/v3/AvailableCountries';
    $response = wp_remote_get( $url, [ 'timeout' => 10 ] );

    if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        if ( is_array( $data ) && ! empty( $data ) ) {
            usort( $data, function( $a, $b ) {
                return strcmp( $a['name'] ?? '', $b['name'] ?? '' );
            } );
            set_transient( $cache_key, $data, MONTH_IN_SECONDS );
            return $data;
        }
    }

    $fallback = [
        [ 'countryCode' => 'US', 'name' => 'United States' ],
        [ 'countryCode' => 'CA', 'name' => 'Canada' ],
        [ 'countryCode' => 'GB', 'name' => 'United Kingdom' ],
        [ 'countryCode' => 'AU', 'name' => 'Australia' ],
        [ 'countryCode' => 'NZ', 'name' => 'New Zealand' ],
        [ 'countryCode' => 'IE', 'name' => 'Ireland' ],
        [ 'countryCode' => 'NL', 'name' => 'Netherlands' ],
        [ 'countryCode' => 'DE', 'name' => 'Germany' ],
        [ 'countryCode' => 'FR', 'name' => 'France' ],
        [ 'countryCode' => 'ES', 'name' => 'Spain' ],
        [ 'countryCode' => 'IT', 'name' => 'Italy' ],
        [ 'countryCode' => 'ZA', 'name' => 'South Africa' ],
        [ 'countryCode' => 'BR', 'name' => 'Brazil' ],
        [ 'countryCode' => 'IN', 'name' => 'India' ],
        [ 'countryCode' => 'SG', 'name' => 'Singapore' ],
    ];
    return $fallback;
}

/**
 * Fetch public holidays for a given country and year, with fallback calculation for US.
 */
function aicb_fetch_country_holidays( $year, $country_code = 'US' ) {
    $country_code = strtoupper( sanitize_key( $country_code ) ) ?: 'US';
    $cache_key    = 'aicb_holidays_' . $country_code . '_' . $year;
    $cached       = get_transient( $cache_key );
    if ( false !== $cached ) {
        return $cached;
    }

    $url      = 'https://date.nager.at/api/v3/PublicHolidays/' . urlencode( $year ) . '/' . urlencode( $country_code );
    $response = wp_remote_get( $url, [ 'timeout' => 10 ] );

    if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
        if ( 'US' === $country_code ) {
            $entries = aicb_calculate_us_federal_holidays( $year );
        } else {
            $entries = [];
        }
    } else {
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        if ( ! is_array( $data ) || empty( $data ) ) {
            $entries = ( 'US' === $country_code ) ? aicb_calculate_us_federal_holidays( $year ) : [];
        } else {
            $entries = [];
            foreach ( $data as $h ) {
                $entries[] = [
                    'date'       => $h['date'] ?? '',
                    'label'      => $h['localName'] ?? $h['name'] ?? '',
                    'status'     => 'closed',
                    'hours_open' => '',
                    'hours_close'=> '',
                ];
            }
        }
    }

    set_transient( $cache_key, $entries, DAY_IN_SECONDS );
    return $entries;
}

/**
 * Helper: Read calendar_data with forced repair to clean structure and automatic chronological sorting.
 */
function aicb_get_clean_calendar() {
    $raw   = get_option( 'aicb_calendar_data' );
    $fresh = aicb_default_options()['calendar_data'];

    if ( false === $raw || ! is_array( $raw ) ) {
        return $fresh;
    }

    $entries = isset( $raw['entries'] ) && is_array( $raw['entries'] ) ? $raw['entries'] : [];

    usort( $entries, function( $a, $b ) {
        $da = $a['date'] ?? '';
        $db = $b['date'] ?? '';
        $current_year = date( 'Y' );
        $norm_a = ( 0 === strpos( $da, '--' ) ) ? $current_year . '-' . ltrim( $da, '-' ) : $da;
        $norm_b = ( 0 === strpos( $db, '--' ) ) ? $current_year . '-' . ltrim( $db, '-' ) : $db;
        return strcmp( $norm_a, $norm_b );
    } );

    $fresh['entries'] = $entries;
    $fresh['default_weekday_hours']  = $raw['default_weekday_hours'] ?? $fresh['default_weekday_hours'];
    $fresh['default_weekend_hours']  = $raw['default_weekend_hours'] ?? $fresh['default_weekend_hours'];
    $fresh['default_weekend_status'] = $raw['default_weekend_status'] ?? $fresh['default_weekend_status'];

    return $fresh;
}
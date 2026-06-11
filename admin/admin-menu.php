<?php
defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', 'aicb_admin_menu' );
function aicb_admin_menu() {
    add_menu_page( 'AI Chatbot', 'AI Chatbot', 'manage_options', 'ai-chatbot', 'aicb_page_dashboard', 'dashicons-format-chat', 80 );
    add_submenu_page( 'ai-chatbot', 'Dashboard',  'Dashboard',  'manage_options', 'ai-chatbot',             'aicb_page_dashboard' );
    add_submenu_page( 'ai-chatbot', 'Settings',   'Settings',   'manage_options', 'ai-chatbot-settings',    'aicb_page_settings'  );
    add_submenu_page( 'ai-chatbot', 'Calendar',   'Calendar',   'manage_options', 'ai-chatbot-calendar',    'aicb_page_calendar'  );
    add_submenu_page( 'ai-chatbot', 'Custom Q&A', 'Custom Q&A', 'manage_options', 'ai-chatbot-qa',          'aicb_page_qa'        );
    add_submenu_page( 'ai-chatbot', 'Chat Logs',  'Chat Logs',  'manage_options', 'ai-chatbot-logs',        'aicb_page_logs'      );
}

add_action( 'admin_head', 'aicb_admin_styles' );
function aicb_admin_styles() {
    $screen = get_current_screen();
    if ( ! $screen || strpos( $screen->id, 'ai-chatbot' ) === false ) return;
    ?>
    <style>
        .aicb-wrap{max-width:980px}
        .aicb-cards{display:flex;gap:16px;flex-wrap:wrap;margin:20px 0}
        .aicb-card{background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px 24px;flex:1;min-width:150px}
        .aicb-card h3{margin-top:0; margin-bottom:12px; font-size:14px; font-weight:600; color:#334155;}
        .aicb-card .num{font-size:2rem;font-weight:700;color:#2563eb}
        .aicb-card .lbl{color:#555;font-size:13px;margin-top:4px}
        .aicb-section{background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px 24px;margin-bottom:20px}
        .aicb-section h2{margin-top:0}
        table.aicb-logs{width:100%;border-collapse:collapse;font-size:13px}
        table.aicb-logs th{background:#f5f5f5;padding:8px 12px;text-align:left}
        table.aicb-logs td{padding:8px 12px;border-top:1px solid #eee;vertical-align:top}
        table.aicb-logs tr:hover td{background:#fafafa}
        .aicb-notice{padding:10px 16px;border-left:4px solid #2563eb;background:#eff6ff;border-radius:4px;margin-bottom:16px}

        .aicb-provider-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px;margin:8px 0 16px}
        .aicb-provider-card{border:2px solid #e2e8f0;border-radius:10px;padding:14px 12px;cursor:pointer;transition:all .15s;text-align:center;background:#fff;position:relative}
        .aicb-provider-card input[type="radio"]{display:none} 
        .aicb-provider-card.selected{border-color:#2563eb;background:#eff6ff}
        .aicb-provider-logo{font-size:28px;display:block;margin-bottom:6px}
        .aicb-provider-name{font-size:12px;font-weight:600;color:#334155;line-height:1.3}
        .aicb-provider-card .aicb-rec{display:inline-block;font-size:10px;background:#dcfce7;color:#166534;border-radius:99px;padding:1px 7px;margin-top:4px}

        .aicb-key-row{display:none}
        .aicb-key-row.active{display:table-row}

        #aicb-model-wrap select{min-width:280px}
        .aicb-model-desc{color:#666;font-size:12px;margin-top:4px}

        #aicb-custom-fields{display:none}
        #aicb-custom-fields.active{display:contents}

        .aicb-tag{display:inline-block;padding:2px 8px;border-radius:99px;font-size:11px;font-weight:600;vertical-align:middle}
        .aicb-tag-provider{background:#e0f2fe;color:#0369a1}
        
        .ui-autocomplete {
            max-height: 250px;
            overflow-y: auto;
            overflow-x: hidden;
            z-index: 999999 !important;
        }

        /* Styles for expandable advanced prompt engineer section */
        .aicb-advanced-toggle-btn {
            display: inline-block;
            background: #f1f5f9;
            color: #334155;
            padding: 10px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            border: 1px solid #cbd5e1;
            transition: all 0.15s ease;
            cursor: pointer;
        }
        .aicb-advanced-toggle-btn:hover {
            background: #e2e8f0;
            color: #0f172a;
        }
    </style>
    <?php
}

add_action( 'admin_enqueue_scripts', 'aicb_admin_enqueue_scripts' );
function aicb_admin_enqueue_scripts( $hook_suffix ) {
    if ( strpos( $hook_suffix, 'ai-chatbot-calendar' ) !== false ) {
        wp_enqueue_script( 'jquery-ui-datepicker' );
        wp_enqueue_script( 'jquery-ui-autocomplete' ); 
        wp_enqueue_style( 'jquery-ui-style', 'https://code.jquery.com/ui/1.12.1/themes/smoothness/jquery-ui.css', [], '1.12.1' );
    }
}

add_action( 'admin_footer', 'aicb_admin_calendar_js' );
function aicb_admin_calendar_js() {
    $screen = get_current_screen();
    if ( $screen && strpos( $screen->id, 'ai-chatbot-calendar' ) !== false ) {
        $countries = aicb_get_available_countries();
        $autocomplete_data = [];
        foreach ( $countries as $c ) {
            $autocomplete_data[] = [
                'label' => $c['name'],
                'value' => $c['name'],
                'code'  => $c['countryCode']
            ];
        }
        ?>
        <script>
        jQuery(document).ready(function($) {
            $('.aicb-datepicker').datepicker({ 
                dateFormat: 'mm/dd/yy', 
                changeMonth: true, 
                changeYear: true 
            });
            
            var countries = <?php echo wp_json_encode( $autocomplete_data ); ?>;

            $('#seed_country_search').autocomplete({
                source: countries,
                minLength: 0,
                select: function(event, ui) {
                    $('#seed_country_code').val(ui.item.code);
                }
            }).focus(function() {
                $(this).autocomplete('search', $(this).val());
            });
        });
        </script>
        <?php
    }
}

add_action( 'admin_init', 'aicb_register_settings' );
function aicb_register_settings() {
    foreach ( array_keys( aicb_default_options() ) as $f ) {
        register_setting( 'aicb_options', 'aicb_' . $f, [
            'sanitize_callback' => function( $val ) use ( $f ) { return aicb_sanitize_specific_option( $val, $f ); }
        ] );
    }
    foreach ( array_keys( aicb_get_providers() ) as $pid ) {
        register_setting( 'aicb_options', 'aicb_key_' . $pid, [
            'sanitize_callback' => function( $val ) use ( $pid ) { return aicb_sanitize_key_field( $val, $pid ); }
        ] );
    }
}

function aicb_sanitize_specific_option( $val, $field ) {
    // Guard: preserve existing data when the option wasn't submitted in a form
    if ( $val === null || $val === '' ) {
        return get_option( 'aicb_' . $field, aicb_default_options()[ $field ] ?? '' );
    }

    if ( $field === 'calendar_data' ) {
        if ( ! is_array( $val ) ) {
            return aicb_default_options()['calendar_data'];
        }
        $sanitized = [];
        $wd = isset( $val['default_weekday_hours'] ) && is_array( $val['default_weekday_hours'] ) ? $val['default_weekday_hours'] : [];
        $sanitized['default_weekday_hours'] = [
            'open'  => sanitize_text_field( $wd['open'] ?? '09:00' ),
            'close' => sanitize_text_field( $wd['close'] ?? '17:00' ),
        ];
        $we = isset( $val['default_weekend_hours'] ) && is_array( $val['default_weekend_hours'] ) ? $val['default_weekend_hours'] : [];
        $sanitized['default_weekend_hours'] = [
            'open'  => sanitize_text_field( $we['open'] ?? '10:00' ),
            'close' => sanitize_text_field( $we['close'] ?? '15:00' ),
        ];
        $sanitized['default_weekend_status'] = sanitize_text_field( $val['default_weekend_status'] ?? 'closed' );
        $entries = isset( $val['entries'] ) && is_array( $val['entries'] ) ? $val['entries'] : [];
        $sanitized_entries = [];
        foreach ( $entries as $entry ) {
            if ( ! is_array( $entry ) ) continue;
            $sanitized_entries[] = [
                'date'        => sanitize_text_field( $entry['date'] ?? '' ),
                'label'       => sanitize_text_field( $entry['label'] ?? '' ),
                'status'      => sanitize_text_field( $entry['status'] ?? 'open' ),
                'hours_open'  => sanitize_text_field( $entry['hours_open'] ?? '' ),
                'hours_close' => sanitize_text_field( $entry['hours_close'] ?? '' ),
            ];
        }
        $sanitized['entries'] = $sanitized_entries;
        return $sanitized;
    }

    if ( $field === 'custom_endpoint' ) {
        $val = esc_url_raw( trim( $val ) );
        if ( ! empty( $val ) && ! aicb_is_valid_endpoint( $val ) ) {
            add_settings_error( 'aicb_options', 'unsafe_endpoint', 'Warning: The Custom Endpoint URL failed safety checks.', 'error' );
        }
        return $val;
    }

    if ( in_array( $field, [ 'handover_apology', 'handover_prompt', 'handover_btn_text', 'contact_btn_text', 'handover_primary_text', 'handover_secondary_bg', 'handover_secondary_text' ], true ) ) {
        return sanitize_text_field( $val );
    }
    if ( in_array( $field, [ 'contact_btn_url', 'handover_target', 'handover_btn_radius' ], true ) ) {
        return sanitize_text_field( $val ); 
    }
    if ( is_array( $val ) ) {
        return array_map( 'sanitize_text_field', $val );
    }
    return sanitize_textarea_field( $val );
}

function aicb_sanitize_key_field( $val, $pid ) {
    $val = sanitize_text_field( trim( $val ) );
    if ( $val === 'XXXXXXXXXXXXXXXX' ) return get_option( 'aicb_key_' . $pid, '' );
    if ( empty( $val ) ) return '';
    if ( ! aicb_has_secure_salts() ) {
        add_settings_error( 'aicb_options', 'weak_salts_save_error', 'Error: Insecure security salts detected.', 'error' );
        return get_option( 'aicb_key_' . $pid, '' );
    }
    return aicb_encrypt( $val );
}

add_action( 'admin_init', 'aicb_handle_manual_cache_flush' );
function aicb_handle_manual_cache_flush() {
    if ( isset( $_POST['aicb_flush_cache_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aicb_flush_cache_nonce'] ) ), 'aicb_flush_cache' ) ) {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Access denied.' );
        update_option( 'aicb_cache_version', time() );
        add_settings_error( 'aicb_options', 'cache_flushed', 'All cached page summaries have been successfully invalidated.', 'updated' );
    }
}

/**
 * Handle native prompt resets back to standard original values.
 */
add_action( 'admin_init', 'aicb_handle_manual_prompt_reset' );
function aicb_handle_manual_prompt_reset() {
    if ( isset( $_POST['aicb_reset_prompts_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aicb_reset_prompts_nonce'] ) ), 'aicb_reset_prompts' ) ) {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Access denied.' );
        
        $defaults = aicb_default_options();
        $prompt_keys = [ 'prompt_temporal_pivot', 'prompt_tool_instruction', 'prompt_negative_constraints', 'system_prompt' ];
        foreach ( $prompt_keys as $key ) {
            update_option( 'aicb_' . $key, $defaults[ $key ] );
        }
        add_settings_error( 'aicb_options', 'prompts_reset', 'All AI prompt engineering templates have been successfully reset to default schemas.', 'updated' );
    }
}

add_action( 'add_meta_boxes', 'aicb_add_meta_box' );
function aicb_add_meta_box() {
    $allowed_types = (array) aicb_opt( 'indexed_post_types' );
    foreach ( $allowed_types as $type ) {
        add_meta_box( 'aicb_page_settings', 'AI Chatbot — Page Scope', 'aicb_meta_box_callback', $type, 'side', 'default' );
    }
}

function aicb_meta_box_callback( $post ) {
    wp_nonce_field( 'aicb_meta_box_save', 'aicb_meta_box_nonce' );
    $val  = get_post_meta( $post->ID, '_aicb_include_kb', true );
    $mode = aicb_opt( 'indexing_mode' );
    $checked = ( $mode === 'opt-in' ) ? ( '1' === $val ) : ( '0' !== $val );
    ?>
    <p><label><input type="checkbox" name="aicb_include_kb" value="1" <?php checked( $checked ); ?> /> <strong>Include in Knowledge Base</strong></label></p>
    <?php
}

add_action( 'save_post', 'aicb_save_meta_box' );
function aicb_save_meta_box( $post_id ) {
    if ( ! isset( $_POST['aicb_meta_box_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aicb_meta_box_nonce'] ) ), 'aicb_meta_box_save' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;
    delete_post_meta( $post_id, '_aicb_page_digest' );
    delete_post_meta( $post_id, '_aicb_digest_timestamp' );
    update_post_meta( $post_id, '_aicb_include_kb', isset( $_POST['aicb_include_kb'] ) ? '1' : '0' );
}

/* =========================================================
   CONTROLLERS — PAGE ROUTING
   ========================================================= */

function aicb_page_dashboard() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Access denied.' );
    global $wpdb;
    $lt = $wpdb->prefix . AICB_LOG_TABLE;
    $total  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $lt" );
    $today  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $lt WHERE DATE(created_at)=%s", current_time('Y-m-d') ) );
    $week   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$lt} WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)", 7 ) );
    $qa_cnt = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}" . AICB_QA_TABLE . " WHERE active=1" );
    $recent = $wpdb->get_results( "SELECT question, answer, provider, model, created_at FROM $lt ORDER BY id DESC LIMIT 5" );
    $cur_provider = aicb_opt('provider');
    $providers    = aicb_get_providers();
    $pname        = $providers[ $cur_provider ]['name'] ?? $cur_provider;

    // Advanced Stats Queries
    $provider_counts = $wpdb->get_results( "SELECT provider, COUNT(*) as count FROM $lt GROUP BY provider" );
    $top_pages = $wpdb->get_results( "SELECT page_id, COUNT(*) as count FROM $lt WHERE page_id > 0 GROUP BY page_id ORDER BY count DESC LIMIT 5" );
    $handover_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $lt WHERE model LIKE '%handover%'" );
    $cached_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->postmeta WHERE meta_key = '_aicb_page_digest'" );

    include AICB_DIR . 'admin/views/dashboard.php';
}

function aicb_page_settings() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Access denied.' );
    $providers    = aicb_get_providers();
    $cur_provider = aicb_opt( 'provider' );
    $cur_model    = aicb_opt( 'model' );

    include AICB_DIR . 'admin/views/settings.php';
}

function aicb_page_calendar() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Access denied.' );

    if ( isset( $_POST['aicb_cal_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aicb_cal_nonce'] ) ), 'aicb_cal_action' ) ) {
        $action   = sanitize_text_field( $_POST['aicb_action'] ?? '' );
        $calendar = aicb_get_clean_calendar();
        $entries  = $calendar['entries'];

        if ( $action === 'save_tool_status' ) {
            update_option( 'aicb_enable_calendar_tools', isset( $_POST['enable_calendar_tools'] ) ? 1 : 0 );
            echo '<div class="notice notice-success"><p>Tool-calling status updated.</p></div>';
        } elseif ( $action === 'save_defaults' ) {
            $calendar['default_weekday_hours'] = [
                'open'  => sanitize_text_field( $_POST['wd_open'] ?? '09:00' ),
                'close' => sanitize_text_field( $_POST['wd_close'] ?? '17:00' ),
            ];
            $calendar['default_weekend_hours'] = [
                'open'  => sanitize_text_field( $_POST['we_open'] ?? '10:00' ),
                'close' => sanitize_text_field( $_POST['we_close'] ?? '15:00' ),
            ];
            $calendar['default_weekend_status'] = isset( $_POST['we_status'] ) ? sanitize_text_field( $_POST['we_status'] ) : 'closed';
            update_option( 'aicb_calendar_data', $calendar );
            echo '<div class="notice notice-success"><p>Default hours updated.</p></div>';
        } elseif ( $action === 'add_entry' || $action === 'update_entry' ) {
            $is_recurring = isset( $_POST['entry_is_recurring'] ) && $_POST['entry_is_recurring'] === '1';
            $raw_date     = sanitize_text_field( $_POST['entry_date'] ?? '' );

            if ( $is_recurring ) {
                if ( preg_match( '/^(\d{1,2})[\-\/](\d{1,2})[\-\/]\d{4}$/', $raw_date, $m ) ) {
                    $date = '--' . sprintf( '%02d-%02d', $m[1], $m[2] );
                } else {
                    $date = aicb_convert_date_to_iso( $raw_date );
                }
            } else {
                $date = aicb_convert_date_to_iso( $raw_date );
            }

            $entry = [
                'date'       => $date, 
                'label'      => sanitize_text_field( $_POST['entry_label'] ?? '' ),
                'status'     => sanitize_text_field( $_POST['entry_status'] ?? 'open' ),
                'hours_open' => sanitize_text_field( $_POST['entry_hours_open'] ?? '' ),
                'hours_close'=> sanitize_text_field( $_POST['entry_hours_close'] ?? '' ),
            ];
            if ( $action === 'add_entry' ) {
                $entries[] = $entry;
            } elseif ( $action === 'update_entry' && isset( $_POST['entry_index'] ) ) {
                $idx = (int) $_POST['entry_index'];
                if ( isset( $entries[ $idx ] ) ) $entries[ $idx ] = $entry;
            }
            $calendar['entries'] = $entries;
            update_option( 'aicb_calendar_data', $calendar );
            echo '<div class="notice notice-success"><p>Calendar entry saved.</p></div>';
        } elseif ( $action === 'delete_entry' && isset( $_POST['entry_index'] ) ) {
            $idx = (int) $_POST['entry_index'];
            if ( isset( $entries[ $idx ] ) ) {
                array_splice( $entries, $idx, 1 );
                $calendar['entries'] = $entries;
                update_option( 'aicb_calendar_data', $calendar );
                echo '<div class="notice notice-success"><p>Entry deleted.</p></div>';
            }
        } elseif ( $action === 'delete_entries' && isset( $_POST['entry_indices'] ) && is_array( $_POST['entry_indices'] ) ) {
            $indices = array_map( 'intval', $_POST['entry_indices'] );
            rsort( $indices );
            foreach ( $indices as $i ) {
                if ( isset( $entries[ $i ] ) ) array_splice( $entries, $i, 1 );
            }
            $calendar['entries'] = $entries;
            update_option( 'aicb_calendar_data', $calendar );
            echo '<div class="notice notice-success"><p>Selected entries deleted.</p></div>';
        } elseif ( $action === 'clear_all_entries' ) {
            $calendar['entries'] = [];
            update_option( 'aicb_calendar_data', $calendar );
            echo '<div class="notice notice-success"><p>All entries cleared.</p></div>';
        } elseif ( $action === 'repair_calendar' ) {
            $fresh = aicb_default_options()['calendar_data'];
            update_option( 'aicb_calendar_data', $fresh );
            $calendar = $fresh;
            $entries  = [];
            echo '<div class="notice notice-success"><p>Calendar data reset.</p></div>';
        } elseif ( $action === 'seed_holidays' ) {
            $from_year    = isset( $_POST['seed_from_year'] ) ? (int) $_POST['seed_from_year'] : (int) current_time( 'Y' );
            $to_year      = isset( $_POST['seed_to_year'] ) ? (int) $_POST['seed_to_year'] : (int) current_time( 'Y' ) + 2;
            $to_year      = max( $from_year, $to_year );
            $country_code = isset( $_POST['seed_country_code'] ) ? sanitize_key( $_POST['seed_country_code'] ) : 'US';

            $existing_dates = [];
            foreach ( $entries as $e ) {
                if ( isset( $e['date'] ) ) $existing_dates[] = $e['date'];
            }
            $added = 0;
            for ( $y = $from_year; $y <= $to_year; $y++ ) {
                $fed = aicb_fetch_country_holidays( $y, $country_code );
                foreach ( $fed as $h ) {
                    if ( ! in_array( $h['date'], $existing_dates, true ) ) {
                        $entries[] = $h;
                        $existing_dates[] = $h['date'];
                        $added++;
                    }
                }
            }
            $calendar['entries'] = $entries;
            update_option( 'aicb_calendar_data', $calendar );
            
            $countries = aicb_get_available_countries();
            $country_name = $country_code;
            foreach ( $countries as $c ) {
                if ( strtoupper($c['countryCode']) === strtoupper($country_code) ) {
                    $country_name = $c['name'];
                    break;
                }
            }
            echo "<div class='notice notice-success'><p>" . sprintf( esc_html__( '%1$s holidays seeded. Added: %2$d', 'ai-chatbot' ), esc_html( $country_name ), $added ) . "</p></div>";
        }
    }

    $calendar = aicb_get_clean_calendar();
    $entries  = $calendar['entries'];
    $enable_tools = aicb_opt( 'enable_calendar_tools' );
    $edit_idx = isset( $_GET['edit_entry'] ) ? (int) $_GET['edit_entry'] : -1;
    $edit_entry = ( $edit_idx >= 0 && isset( $entries[ $edit_idx ] ) ) ? $entries[ $edit_idx ] : null;

    $per_page     = 50;
    $total_entries = count( $entries );
    $paged_ek     = isset( $_GET['ek_page'] ) ? max( 1, (int) $_GET['ek_page'] ) : 1;
    $total_pages  = max( 1, (int) ceil( $total_entries / $per_page ) );
    if ( $paged_ek > $total_pages ) $paged_ek = $total_pages;
    $offset       = ( $paged_ek - 1 ) * $per_page;
    $page_entries = array_slice( $entries, $offset, $per_page, true );

    $filter_year = isset( $_GET['ek_year'] ) ? sanitize_text_field( $_GET['ek_year'] ) : '';
    $years       = [];
    foreach ( $entries as $e ) {
        $d = $e['date'] ?? '';
        if ( preg_match( '/^(\d{4})/', $d, $m ) ) $years[ $m[1] ] = true;
    }
    ksort( $years );

    include AICB_DIR . 'admin/views/calendar.php';
}

function aicb_page_qa() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Access denied.' );
    global $wpdb;
    $table = $wpdb->prefix . AICB_QA_TABLE;

    if ( isset( $_POST['aicb_qa_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aicb_qa_nonce'] ) ), 'aicb_qa_action' ) ) {
        $action = sanitize_text_field( $_POST['aicb_action'] ?? '' );
        if ( $action === 'add' ) {
            $wpdb->insert( $table, [
                'question' => sanitize_textarea_field( wp_unslash( $_POST['question'] ?? '' ) ),
                'active'   => 1,
            ], [ '%s', '%s', '%d' ] );
        } elseif ( $action === 'update' && isset( $_POST['qa_id'] ) ) {
            $wpdb->update( $table, [
                'question' => sanitize_textarea_field( wp_unslash( $_POST['question'] ?? '' ) ),
                'answer'   => sanitize_textarea_field( wp_unslash( $_POST['answer']   ?? '' ) ),
            ], [ 'id' => (int) $_POST['qa_id'] ], ['%s', '%s'], ['%d'] );
        } elseif ( $action === 'delete' && isset( $_POST['qa_id'] ) ) {
            $wpdb->delete( $table, [ 'id' => (int) $_POST['qa_id'] ], [ '%d' ] );
        } elseif ( $action === 'toggle' && isset( $_POST['qa_id'] ) ) {
            $row = $wpdb->get_row( $wpdb->prepare( "SELECT active FROM $table WHERE id=%d", (int)$_POST['qa_id'] ) );
            if ( $row ) $wpdb->update( $table, [ 'active' => $row->active ? 0 : 1 ], [ 'id' => (int)$_POST['qa_id'] ], ['%d'], ['%d'] );
        }
    }

    $edit_row = isset( $_GET['edit_id'] ) ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id=%d", (int)$_GET['edit_id'] ) ) : null;
    $rows = $wpdb->get_results( "SELECT * FROM $table ORDER BY id DESC" );

    include AICB_DIR . 'admin/views/qa.php';
}

function aicb_page_logs() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Access denied.' );
    global $wpdb;
    if ( isset( $_POST['aicb_clear_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aicb_clear_nonce'] ) ), 'aicb_clear_logs' ) ) {
        $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}" . AICB_LOG_TABLE );
    }
    $per  = 25;
    $page = max( 1, (int)($_GET['paged'] ?? 1) );
    $off  = ($page-1)*$per;
    $total= (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}" . AICB_LOG_TABLE );
    $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}" . AICB_LOG_TABLE . " ORDER BY id DESC LIMIT %d OFFSET %d", $per, $off ) );
    $pages= ceil($total/$per);

    include AICB_DIR . 'admin/views/logs.php';
}
<?php
defined( 'ABSPATH' ) || exit;

/* =========================================================
   1. FRONTEND ENQUEUES & ASSETS
   ========================================================= */

add_action( 'wp_enqueue_scripts', 'aicb_enqueue_frontend' );
function aicb_enqueue_frontend() {
    if ( ! aicb_opt( 'enabled' ) ) return;

    $css_ver = file_exists( AICB_DIR . 'assets/chatbot.css' ) ? filemtime( AICB_DIR . 'assets/chatbot.css' ) : AICB_VERSION;
    $js_ver  = file_exists( AICB_DIR . 'assets/chatbot.js' ) ? filemtime( AICB_DIR . 'assets/chatbot.js' ) : AICB_VERSION;

    wp_enqueue_style( 'aicb-style', AICB_URL . 'assets/chatbot.css', [], $css_ver );

    $primary_color = aicb_opt( 'primary_color' );
    $btn_radius    = (int) aicb_opt( 'handover_btn_radius' );
    $p_text        = aicb_opt( 'handover_primary_text' );
    $s_bg          = aicb_opt( 'handover_secondary_bg' );
    $s_text        = aicb_opt( 'handover_secondary_text' );

    $custom_css = "
        :root {
            --aicb-p-btn-text: " . esc_attr( $p_text ) . ";
            --aicb-s-btn-bg: " . esc_attr( $s_bg ) . ";
            --aicb-s-btn-text: " . esc_attr( $s_text ) . ";
            --aicb-btn-radius: " . $btn_radius . "px;
            --aicb-primary-color: " . esc_attr( $primary_color ) . ";
        }
    ";
    wp_add_inline_style( 'aicb-style', $custom_css );

    wp_enqueue_script( 'aicb-script', AICB_URL . 'assets/chatbot.js', [], $js_ver, true );

    $chat_title = aicb_opt( 'chat_title' );
    if ( aicb_opt( 'pronoun_perspective' ) === 'first-singular' ) {
        $chat_title = str_replace( 'Chat with us', 'Chat with me', $chat_title );
    }

    $chatbot_language = '';
    $language_mode = aicb_opt( 'chatbot_language_mode' );
    if ( $language_mode === 'fixed' ) {
        $chatbot_language = aicb_opt( 'chatbot_language' );
    }

    $enable_feedback = aicb_opt( 'enable_feedback' );

    $enable_lead_capture = aicb_opt( 'enable_lead_capture' );
    $enable_transcript   = aicb_opt( 'enable_transcript_export' );

    wp_localize_script( 'aicb-script', 'aicbData', [
        'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
        'nonce'             => wp_create_nonce( 'aicb_chat' ),
        'position'          => aicb_opt( 'position' ),
        'color'             => $primary_color,
        'icon'              => aicb_opt( 'icon' ),
        'title'             => $chat_title,
        'welcome'           => aicb_opt( 'welcome_msg' ),
        'placeholder'       => aicb_opt( 'placeholder' ),
        'footerText'        => aicb_opt( 'footer_text' ),
        'pageId'            => get_queried_object_id() ?: 0,
        'language'          => $chatbot_language,
        'enableFeedback'    => $enable_feedback ? true : false,
        'feedbackNonce'     => $enable_feedback ? wp_create_nonce( 'aicb_feedback' ) : '',
        'enableHandover'    => aicb_opt( 'enable_handover' ) ? true : false,
        'alwaysShowButtons' => aicb_opt( 'always_show_handover_buttons' ) ? true : false,
        'primaryBtnText'    => esc_html( aicb_opt( 'handover_btn_text' ) ),
        'primaryBtnUrl'     => aicb_clean_url( aicb_get_handover_url() ),
        'secondaryBtnText'  => esc_html( aicb_opt( 'contact_btn_text' ) ),
        'secondaryBtnUrl'   => aicb_clean_url( aicb_opt( 'contact_btn_url' ) ),
        'enableLeadCapture' => $enable_lead_capture ? true : false,
        'leadNonce'         => $enable_lead_capture ? wp_create_nonce( 'aicb_lead' ) : '',
        'enableTranscript'  => $enable_transcript ? true : false,
        'transcriptNonce'   => $enable_transcript ? wp_create_nonce( 'aicb_export_transcript' ) : '',
    ] );
}

/* =========================================================
   2. DOM HTML INJECTION & SHORTCODE
   ========================================================= */

add_action( 'wp_footer', 'aicb_maybe_inject' );
function aicb_maybe_inject() {
    if ( ! aicb_opt( 'enabled' ) || ! aicb_opt( 'show_on_all' ) ) return;
    if ( did_action( 'aicb_shortcode_rendered' ) ) return;
    echo '<div id="aicb-root" class="' . esc_attr( aicb_opt( 'position' ) ) . '" aria-live="polite"></div>';
}

add_shortcode( 'ai_chatbot', 'aicb_shortcode' );
function aicb_shortcode() {
    if ( ! aicb_opt( 'enabled' ) ) return '';
    do_action( 'aicb_shortcode_rendered' );
    return '<div id="aicb-root" class="' . esc_attr( aicb_opt( 'position' ) ) . '" aria-live="polite"></div>';
}

/* =========================================================
   3. AJAX INTERACTION PROCESSING
   ========================================================= */

add_action( 'wp_ajax_aicb_chat',        'aicb_ajax_chat' );
add_action( 'wp_ajax_nopriv_aicb_chat', 'aicb_ajax_chat' );
function aicb_ajax_chat() {
    aicb_set_security_headers();
    do_action( 'aicb_before_ajax_chat', $_POST );
    check_ajax_referer( 'aicb_chat', 'nonce' );

    $ip_hash  = hash( 'sha256', aicb_get_user_ip() );
    $rate_key = 'aicb_rate_' . $ip_hash;
    $hits     = (int) get_transient( $rate_key );
    if ( $hits >= (int) aicb_opt( 'rate_limit' ) ) {
        wp_send_json_error( [ 'message' => 'Rate limit reached.' ], 429 );
    }
    set_transient( $rate_key, $hits + 1, HOUR_IN_SECONDS );

    $question   = sanitize_textarea_field( wp_unslash( $_POST['question'] ?? '' ) );
    $page_id    = (int) ( $_POST['page_id'] ?? 0 );

    // Server-side session management: use per-session transients keyed by session_id.
    // Each session stores the creator's ip_hash and auto-expires after 24 hours.
    $session_id = sanitize_text_field( wp_unslash( $_POST['session_id'] ?? '' ) );
    $session_key = 'aicb_session_' . $session_id;
    $session_data = get_transient( $session_key );

    if ( empty( $session_id ) || false === $session_data ) {
        // New session: generate server-side identifier (unforgeable random bytes)
        $session_id = 'sess_' . bin2hex( random_bytes( 16 ) );
        $session_data = [ 'ip_hash' => $ip_hash ];
        set_transient( 'aicb_session_' . $session_id, $session_data, DAY_IN_SECONDS );
    } elseif ( ! hash_equals( $session_data['ip_hash'], $ip_hash ) ) {
        // Session exists but IP mismatch — reject to prevent session hijacking
        wp_send_json_error( [ 'message' => 'Session expired or invalid.' ], 403 );
    }

    $confirm = isset( $_POST['confirm_handover'] ) && $_POST['confirm_handover'] === 'true';

    if ( strlen( $question ) < 2 || strlen( $question ) > 1000 ) {
        wp_send_json_error( [ 'message' => 'Invalid question length.' ], 400 );
    }

    if ( aicb_opt( 'enable_handover' ) && $confirm ) {
        if ( aicb_is_positive_confirmation( $question ) ) {
            $perspective = aicb_opt( 'pronoun_perspective' );
            $target = 'us';
            if ( $perspective === 'first-singular' ) {
                $target = 'me';
            } elseif ( $perspective === 'neutral' ) {
                $target = 'the team';
            }
            $answer = sprintf( "Great! Please use the options below to connect with %s:", $target );
            aicb_log( $session_id, $question, $answer, $page_id, $ip_hash, 'custom', 'handover-confirmed' );
            wp_send_json_success( [
                'answer'           => $answer,
                'source'           => 'custom-handover',
                'provider'         => 'custom',
                'handover'         => true,
                'session_id'       => $session_id,
                'primaryBtnText'   => esc_html( aicb_opt( 'handover_btn_text' ) ),
                'primaryBtnUrl'    => aicb_clean_url( aicb_get_handover_url() ),
                'secondaryBtnText' => esc_html( aicb_opt( 'contact_btn_text' ) ),
                'secondaryBtnUrl'  => esc_url( aicb_clean_url( aicb_opt( 'contact_btn_url' ) ) )
            ] );
        }
    }

    global $wpdb;
    $qa_table = $wpdb->prefix . AICB_QA_TABLE;
    $custom = $wpdb->get_row( $wpdb->prepare(
        "SELECT answer FROM {$qa_table} WHERE active = 1 AND (LOWER(%s) LIKE CONCAT('%%', LOWER(question), '%%') OR LOWER(question) LIKE CONCAT('%%', LOWER(%s), '%%')) LIMIT 1", $question, $question
    ) );
        if ( $custom ) {
            $answer = sanitize_textarea_field( $custom->answer );
            aicb_log( $session_id, $question, $answer, $page_id, $ip_hash, 'custom', 'custom-match' );
            wp_send_json_success( [ 'answer' => $answer, 'source' => 'custom', 'session_id' => $session_id ] );
    }

    if ( aicb_is_handover_requested( $question ) ) {
        $answer = aicb_opt( 'handover_prompt' );
        aicb_log( $session_id, $question, $answer, $page_id, $ip_hash, 'custom', 'handover-prompt' );
        wp_send_json_success( [ 'answer' => $answer, 'source' => 'custom-handover', 'provider' => 'custom', 'awaiting_confirmation' => true, 'session_id' => $session_id ] );
    }

    $page_context = aicb_retrieve_relevant_contexts( $question, $page_id );
    if ( ! empty( $page_context ) ) {
        $page_context = "\n\n--- KNOWLEDGE BASE DIRECTORY ---\n" . $page_context . "\n--- END DIRECTORY ---";
    }

    // Pre-filter Q&A by keyword matching to avoid KB bloat in context window
    $custom_kb = "";
    $keywords = aicb_extract_keywords( $question );
    if ( ! empty( $keywords ) ) {
        $like_clauses = [];
        $params = [];
        foreach ( $keywords as $kw ) {
            $like_clauses[] = "LOWER(question) LIKE LOWER(%s)";
            $params[] = '%' . $wpdb->esc_like( $kw ) . '%';
        }
        $like_sql = implode( ' OR ', $like_clauses );
        $custom_qas = $wpdb->get_results( $wpdb->prepare(
            "SELECT question, answer FROM {$qa_table} WHERE active = 1 AND ({$like_sql}) LIMIT 5",
            $params
        ) );
        if ( ! empty( $custom_qas ) ) {
            $custom_kb = "\n\n--- CORE BUSINESS RULES & FAQS (PRIORITY) ---\nUse these exact rules and answers to reason and cross-reference. Always prioritize these facts over general knowledge. After cross-referencing, output ONLY your final answer — never include the reasoning steps or alternative scenarios in your response:\n";
            foreach ( $custom_qas as $q ) {
                $custom_kb .= "Q: {$q->question}\nA: {$q->answer}\n\n";
            }
        }
    }

    // Load and interpolate the dynamic, editable temporal pivot prompt
    $temporal_pivot_raw = aicb_opt( 'prompt_temporal_pivot' );
    $temporal_pivot_raw = str_replace( '{current_date}', wp_date( 'l, F j, Y' ), $temporal_pivot_raw );
    $temporal_pivot_raw = str_replace( '{current_time}', wp_date( 'g:i A' ), $temporal_pivot_raw );
    $temporal_pivot     = "\n\n--- TEMPORAL CONTEXT ---\n" . $temporal_pivot_raw;

    // Load editable tool instruction sub-prompt
    $tool_instruction = "";
    if ( aicb_opt( 'enable_calendar_tools' ) ) {
        $tool_instruction = "\n\n" . aicb_opt( 'prompt_tool_instruction' );
    }

    $business_name = aicb_opt( 'business_name' );
    $perspective   = aicb_opt( 'pronoun_perspective' );
    $tone          = aicb_opt( 'chatbot_tone' );

    $identity_prompt = ! empty( $business_name ) ? "You are the official AI representative for the entity '" . esc_attr( $business_name ) . "' directly. Never use any other brand name, phrase, or tagline as your company name." : "You are the official AI representative representing this website.";

    $perspective_prompt = "";
    if ( $perspective === 'first-singular' ) {
        $perspective_prompt = "\n- PERSPECTIVE: Speak strictly in the first-person singular ('I', 'my', 'me', 'myself'). Never refer to yourself as 'we', 'our', or 'us'. You are a solo practitioner. Use only the exact terminology from the CORE BUSINESS RULES — do not invent business-type nouns like 'store', 'shop', 'company', or 'business' unless they explicitly appear in the rules.";
    } elseif ( $perspective === 'neutral' ) {
        $perspective_prompt = "\n- PERSPECTIVE: Speak strictly in a neutral, professional third-person perspective ('the company', 'the service', 'the team'). Do not use 'I' or 'we'. Use only the exact terminology from the CORE BUSINESS RULES — do not invent business-type nouns like 'store', 'shop', 'company', or 'business' unless they explicitly appear in the rules.";
    } else {
        $perspective_prompt = "\n- PERSPECTIVE: Speak strictly in the first-person plural ('we', 'our', 'us', 'ourselves'). You represent an agency or group. Use only the exact terminology from the CORE BUSINESS RULES — do not invent business-type nouns like 'store', 'shop', 'company', or 'business' unless they explicitly appear in the rules.";
    }

    $tone_prompt = "";
    if ( $tone === 'casual' ) {
        $tone_prompt = "\n- TONE: Casual, warm, approachable, and welcoming. Avoid formal business jargon.";
    } elseif ( $tone === 'minimalist' ) {
        $tone_prompt = "\n- TONE: Minimalist, direct, and highly factual. Answer in 1 short sentence if possible. Never write a second sentence unless absolutely required to convey critical data. Cut all conversational fluff.";
    } else {
        $tone_prompt = "\n- TONE: Professional, polite, authoritative, and helpful.";
    }

    // Load dynamic negative constraints prompt
    $negative_constraints = "\n" . aicb_opt( 'prompt_negative_constraints' );

    // Language instruction
    $language_instruction = '';
    $language_mode = aicb_opt( 'chatbot_language_mode' );
    if ( $language_mode === 'auto' && ! empty( $_POST['language'] ) ) {
        $language = sanitize_text_field( wp_unslash( $_POST['language'] ) );
        if ( ! empty( $language ) ) {
            $language_instruction = "\n\n- LANGUAGE: You must respond in {$language}. All responses must be in the user's language. Never switch to another language.";
        }
    } elseif ( $language_mode === 'fixed' ) {
        $language = aicb_opt( 'chatbot_language' );
        if ( ! empty( $language ) ) {
            $language_instruction = "\n\n- LANGUAGE: You must respond in {$language}. All responses must be in this language. Never switch to another language.";
        }
    }

    $system_content = aicb_opt( 'system_prompt' ) . "\n\n" . $identity_prompt . $perspective_prompt . $tone_prompt . $temporal_pivot . $tool_instruction . $negative_constraints . $language_instruction . $custom_kb . $page_context;
    $provider = aicb_opt( 'provider' );
    $model    = aicb_opt( 'model' );

    // Build messages array (no session history to keep token usage low on free-tier models)
    $messages = [
        [ 'role' => 'system', 'content' => $system_content ],
        [ 'role' => 'user',   'content' => $question ],
    ];

    $result = aicb_call_ai( $provider, $model, $messages, aicb_opt( 'max_tokens' ) );

    if ( is_wp_error( $result ) ) {
        $err_code = $result->get_error_code();
        $err_msg  = $result->get_error_message();
        if ( in_array( $err_code, [ 'no_key', 'no_endpoint', 'unsafe_endpoint', 'unknown_provider' ], true ) ) {
            wp_send_json_error( [ 'message' => $err_msg ], 500 );
        } else {
            error_log( 'AICB AI Provider Error: ' . $err_msg );
            wp_send_json_error( [ 'message' => 'The AI assistant could not respond at this time. Please try again later.' ], 502 );
        }
    }

    $answer = sanitize_textarea_field( $result['answer'] );
    $handover_triggered = false;
    if ( aicb_opt( 'enable_handover' ) ) {
        if ( strpos( $answer, '[TRIGGER_HANDOVER]' ) !== false ) {
            $handover_triggered = true;
            $answer = trim( str_replace( '[TRIGGER_HANDOVER]', '', $answer ) );
        }
        if ( $handover_triggered ) {
            $answer = aicb_opt( 'handover_apology' );
            aicb_log( $session_id, $question, $answer, $page_id, $ip_hash, $provider, $model );
            wp_send_json_success( [ 'answer' => $answer, 'source' => 'ai-reasoning', 'provider' => $provider, 'awaiting_confirmation' => true, 'session_id' => $session_id ] );
        }
    }

    $log_id = aicb_log( $session_id, $question, $answer, $page_id, $ip_hash, $provider, $model );
    wp_send_json_success( [
        'log_id'      => $log_id,
        'answer'      => $answer,
        'source'      => 'ai-reasoning',
        'provider'    => $provider,
        'session_id'  => $session_id
    ] );
}

function aicb_set_security_headers() {
    if ( ! headers_sent() ) {
        header( 'X-Content-Type-Options: nosniff' );
        header( 'X-Frame-Options: DENY' );
        header( "Content-Security-Policy: frame-ancestors 'none';" );
    }
}

/* =========================================================
   4. CORE UTILITY HELPERS
   ========================================================= */

/**
 * Generate a server-side session identifier (not used for signing anymore;
 * kept as a no-op for backward compat with any third-party code that may
 * still call it).
 * @deprecated Session auth is now IP-bound via aicb_session_store.
 */
function aicb_generate_session_token( $session_id ) {
    return $session_id;
}

function aicb_get_user_ip() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if ( defined( 'AICB_TRUST_PROXY' ) && AICB_TRUST_PROXY ) {
        if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
            $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
        } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ips = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );
            $ip = trim( $ips[0] );
        }
    }
    return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : 'unknown';
}

function aicb_is_handover_requested( $question ) {
    if ( ! aicb_opt( 'enable_handover' ) ) return false;
    $question = strtolower( trim( $question ) );
    $question = preg_replace( '/[^\w\s]/u', '', $question );
    $phrases = [ 'live person', 'real person', 'human', 'representative', 'operator', 'talk to someone', 'contact me', 'contact us' ];
    foreach ( $phrases as $phrase ) {
        if ( strpos( $question, $phrase ) !== false ) return true;
    }
    return false;
}

function aicb_is_positive_confirmation( $text ) {
    $text = strtolower( trim( $text ) );
    $text = preg_replace( '/[^\w\s]/u', '', $text );
    $confirmations = [ 'yes', 'yeah', 'yep', 'sure', 'please', 'ok', 'okay', 'yes please', 'absolutely', 'do it' ];
    return in_array( $text, $confirmations, true );
}

function aicb_detect_handover( $text ) {
    $text = strtolower( $text );
    $phrases = [ 'live person', 'real person', 'human', 'representative', 'contact us', 'live agent', 'customer service' ];
    foreach ( $phrases as $phrase ) {
        if ( strpos( $text, $phrase ) !== false ) return true;
    }
    return false;
}

/**
 * Extract meaningful keywords from a question for Q&A pre-filtering.
 * Removes common stop words and short words, returns unique lowercase tokens.
 */
function aicb_extract_keywords( $question ) {
    $stop_words = [ 'a', 'an', 'the', 'is', 'it', 'am', 'are', 'was', 'were', 'be', 'been', 'being',
        'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might',
        'can', 'shall', 'to', 'of', 'in', 'for', 'on', 'with', 'at', 'by', 'from', 'as', 'into',
        'through', 'during', 'before', 'after', 'above', 'below', 'between', 'out', 'off', 'over',
        'under', 'again', 'further', 'then', 'once', 'here', 'there', 'when', 'where', 'why', 'how',
        'all', 'each', 'every', 'both', 'few', 'more', 'most', 'other', 'some', 'such', 'no', 'nor',
        'not', 'only', 'own', 'same', 'so', 'than', 'too', 'very', 'just', 'about', 'up', 'what',
        'which', 'who', 'whom', 'this', 'that', 'these', 'those', 'i', 'me', 'my', 'myself', 'you',
        'your', 'yours', 'yourself', 'he', 'him', 'his', 'himself', 'she', 'her', 'hers', 'herself',
        'we', 'our', 'ours', 'ourselves', 'they', 'them', 'their', 'theirs', 'themselves', 'please',
        'help', 'tell', 'know', 'want', 'need', 'like', 'get', 'thanks', 'thank', 'hi', 'hello',
        'hey' ];

    $words = preg_split( '/[^a-zA-Z0-9]+/', strtolower( $question ) );
    $keywords = [];
    foreach ( $words as $w ) {
        $w = trim( $w );
        if ( strlen( $w ) < 3 ) continue;
        if ( in_array( $w, $stop_words, true ) ) continue;
        $keywords[ $w ] = true;
    }
    return array_keys( $keywords );
}

function aicb_clean_url( $url ) {
    return esc_url( $url, [ 'http', 'https', 'tel', 'sms', 'mailto' ] );
}

function aicb_get_handover_url() {
    $type   = aicb_opt( 'handover_type' );
    $target = aicb_opt( 'handover_target' );
    switch ( $type ) {
        case 'whatsapp' : return 'https://wa.me/' . preg_replace( '/[^0-9]/', '', $target );
        case 'tel'      : return 'tel:' . preg_replace( '/[^0-9+]/', '', $target );
        case 'sms'      : return 'sms:' . preg_replace( '/[^0-9+]/', '', $target );
        default         : return esc_url_raw( $target );
    }
}

/* =========================================================
   5. FEEDBACK AJAX ENDPOINT
   ========================================================= */

add_action( 'wp_ajax_aicb_feedback',        'aicb_ajax_feedback' );
add_action( 'wp_ajax_nopriv_aicb_feedback', 'aicb_ajax_feedback' );
function aicb_ajax_feedback() {
    aicb_set_security_headers();
    check_ajax_referer( 'aicb_feedback', 'nonce' );

    if ( ! aicb_opt( 'enable_feedback' ) ) {
        wp_send_json_error( [ 'message' => 'Feedback is disabled.' ], 403 );
    }

    $log_id = isset( $_POST['log_id'] ) ? (int) $_POST['log_id'] : 0;
    $rating = isset( $_POST['rating'] ) ? (int) $_POST['rating'] : -1;

    if ( ! $log_id || ! in_array( $rating, [ 0, 1 ], true ) ) {
        wp_send_json_error( [ 'message' => 'Invalid parameters.' ], 400 );
    }

    global $wpdb;
    $table = $wpdb->prefix . AICB_LOG_TABLE;

    $updated = $wpdb->update(
        $table,
        [ 'feedback' => $rating ],
        [ 'id' => $log_id ],
        [ '%d' ],
        [ '%d' ]
    );

    if ( false === $updated ) {
        wp_send_json_error( [ 'message' => 'Database error.' ], 500 );
    }

    wp_send_json_success( [ 'message' => 'Feedback recorded.' ] );
}

/* =========================================================
   6. LEAD CAPTURE AJAX ENDPOINT
   ========================================================= */

add_action( 'wp_ajax_aicb_lead_submit',        'aicb_ajax_lead_submit' );
add_action( 'wp_ajax_nopriv_aicb_lead_submit', 'aicb_ajax_lead_submit' );
function aicb_ajax_lead_submit() {
    aicb_set_security_headers();
    check_ajax_referer( 'aicb_lead', 'nonce' );

    if ( ! aicb_opt( 'enable_lead_capture' ) ) {
        wp_send_json_error( [ 'message' => 'Lead capture is disabled.' ], 403 );
    }

    $name       = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
    $email      = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
    $message    = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );
    $session_id = sanitize_text_field( wp_unslash( $_POST['session_id'] ?? '' ) );
    $page_id    = (int) ( $_POST['page_id'] ?? 0 );

    // Honeypot: if 'website' field is filled, silently reject (bot)
    if ( ! empty( $_POST['website'] ) ) {
        wp_send_json_success( [ 'message' => 'Thank you!' ] );
    }

    // Rate limiting per IP
    $ip_hash = hash( 'sha256', aicb_get_user_ip() );
    $rate_key = 'aicb_lead_rate_' . $ip_hash;
    $hits     = (int) get_transient( $rate_key );
    if ( $hits >= 3 ) {
        wp_send_json_error( [ 'message' => 'Too many submissions. Please try again later.' ], 429 );
    }
    set_transient( $rate_key, $hits + 1, HOUR_IN_SECONDS );

    if ( empty( $name ) || empty( $email ) || ! is_email( $email ) ) {
        wp_send_json_error( [ 'message' => 'Please provide a valid name and email address.' ], 400 );
    }

    if ( strlen( $name ) > 255 || strlen( $email ) > 255 ) {
        wp_send_json_error( [ 'message' => 'Invalid input length.' ], 400 );
    }

    if ( strlen( $message ) > 2000 ) {
        $message = substr( $message, 0, 2000 );
    }

    global $wpdb;
    $table = $wpdb->prefix . AICB_LEADS_TABLE;

    $inserted = $wpdb->insert(
        $table,
        [
            'name'       => $name,
            'email'      => $email,
            'message'    => $message,
            'session_id' => $session_id,
            'page_id'    => $page_id,
            'created_at' => current_time( 'mysql' ),
        ],
        [ '%s', '%s', '%s', '%s', '%d', '%s' ]
    );

    if ( false === $inserted ) {
        wp_send_json_error( [ 'message' => 'Database error.' ], 500 );
    }

    // Optional email notification
    $notification_email = aicb_opt( 'lead_notification_email' );
    if ( ! empty( $notification_email ) && is_email( $notification_email ) ) {
        $page_title = $page_id ? get_the_title( $page_id ) : 'Unknown';
        $subject = sprintf( 'New lead from %s', get_bloginfo( 'name' ) );
        $body    = "Name: {$name}\nEmail: {$email}\nMessage: {$message}\nPage: {$page_title}\nSession: {$session_id}\n";
        wp_mail( $notification_email, $subject, $body );
    }

    wp_send_json_success( [ 'message' => 'Thank you! We will get back to you soon.' ] );
}

/* =========================================================
   7. TRANSCRIPT EXPORT AJAX ENDPOINT
   ========================================================= */

add_action( 'wp_ajax_aicb_export_transcript',        'aicb_ajax_export_transcript' );
add_action( 'wp_ajax_nopriv_aicb_export_transcript', 'aicb_ajax_export_transcript' );
function aicb_ajax_export_transcript() {
    aicb_set_security_headers();
    check_ajax_referer( 'aicb_export_transcript', 'nonce' );

    if ( ! aicb_opt( 'enable_transcript_export' ) ) {
        wp_send_json_error( [ 'message' => 'Transcript export is disabled.' ], 403 );
    }

    $email      = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
    $session_id = sanitize_text_field( wp_unslash( $_POST['session_id'] ?? '' ) );

    // Honeypot: if 'website' field is filled, silently reject (bot)
    if ( ! empty( $_POST['website'] ) ) {
        wp_send_json_success( [ 'message' => 'Transcript sent!' ] );
    }

    // Rate limiting per IP
    $ip_hash = hash( 'sha256', aicb_get_user_ip() );
    $rate_key = 'aicb_transcript_rate_' . $ip_hash;
    $hits     = (int) get_transient( $rate_key );
    if ( $hits >= 2 ) {
        wp_send_json_error( [ 'message' => 'Too many requests. Please try again later.' ], 429 );
    }
    set_transient( $rate_key, $hits + 1, HOUR_IN_SECONDS );

    if ( empty( $email ) || ! is_email( $email ) ) {
        wp_send_json_error( [ 'message' => 'Please provide a valid email address.' ], 400 );
    }

    if ( empty( $session_id ) ) {
        wp_send_json_error( [ 'message' => 'No conversation session found.' ], 400 );
    }

    // Verify session ownership: session must exist as a transient
    // and the requester's IP must match the IP that created the session
    $session_data = get_transient( 'aicb_session_' . $session_id );
    if ( false === $session_data ) {
        wp_send_json_error( [ 'message' => 'Session not found or has expired.' ], 403 );
    }
    if ( ! hash_equals( $session_data['ip_hash'], $ip_hash ) ) {
        wp_send_json_error( [ 'message' => 'Unauthorized: session IP mismatch.' ], 403 );
    }

    // Build transcript from logs
    global $wpdb;
    $lt = $wpdb->prefix . AICB_LOG_TABLE;
    $messages = $wpdb->get_results( $wpdb->prepare(
        "SELECT question, answer, created_at FROM {$lt} WHERE session_id = %s ORDER BY id ASC",
        $session_id
    ) );

    if ( empty( $messages ) ) {
        wp_send_json_error( [ 'message' => 'No conversation history found for this session.' ], 404 );
    }

    $site_name = get_bloginfo( 'name' );
    $transcript = "Conversation with {$site_name}\n";
    $transcript .= str_repeat( '=', 40 ) . "\n\n";

    foreach ( $messages as $msg ) {
        $time = $msg->created_at ? date( 'M j, Y g:i A', strtotime( $msg->created_at ) ) : '';
        $transcript .= "[{$time}]\n";
        $transcript .= "You: {$msg->question}\n";
        $transcript .= "Bot: {$msg->answer}\n\n";
    }

    $transcript .= str_repeat( '=', 40 ) . "\n";
    $transcript .= "Exported from {$site_name}\n";

    $subject = sprintf( 'Your conversation with %s', $site_name );
    $sent = wp_mail( $email, $subject, $transcript );

    if ( ! $sent ) {
        wp_send_json_error( [ 'message' => 'Failed to send email. Please try again.' ], 500 );
    }

    wp_send_json_success( [ 'message' => 'Transcript sent! Please check your email.' ] );
}

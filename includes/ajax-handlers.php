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

    wp_localize_script( 'aicb-script', 'aicbData', [
        'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
        'nonce'       => wp_create_nonce( 'aicb_chat' ),
        'position'    => aicb_opt( 'position' ),
        'color'       => $primary_color,
        'icon'        => aicb_opt( 'icon' ),
        'title'       => $chat_title,
        'welcome'     => aicb_opt( 'welcome_msg' ),
        'placeholder' => aicb_opt( 'placeholder' ),
        'footerText'  => aicb_opt( 'footer_text' ),
        'pageId'      => get_queried_object_id() ?: 0,
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
    $session_id = sanitize_text_field( wp_unslash( $_POST['session_id'] ?? '' ) );
    $confirm    = isset( $_POST['confirm_handover'] ) && $_POST['confirm_handover'] === 'true';

    if ( strlen( $question ) < 2 || strlen( $question ) > 1000 ) {
        wp_send_json_error( [ 'message' => 'Invalid question length.' ], 400 );
    }

    if ( aicb_opt( 'enable_handover' ) && $confirm ) {
        if ( aicb_is_positive_confirmation( $question ) ) {
            $answer = "Great! Please use the options below to connect with us:";
            aicb_log( $session_id, $question, $answer, $page_id, $ip_hash, 'custom', 'handover-confirmed' );
            wp_send_json_success( [
                'answer'           => $answer,
                'source'           => 'custom-handover',
                'provider'         => 'custom',
                'handover'         => true,
                'primaryBtnText'   => esc_html( aicb_opt( 'handover_btn_text' ) ),
                'primaryBtnUrl'    => aicb_clean_url( aicb_get_handover_url() ),
                'secondaryBtnText' => esc_html( aicb_opt( 'contact_btn_text' ) ),
        'secondaryBtnUrl'  => aicb_clean_url( aicb_opt( 'contact_btn_url' ) )
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
        wp_send_json_success( [ 'answer' => $answer, 'source' => 'custom' ] );
    }

    if ( aicb_is_handover_requested( $question ) ) {
        $answer = aicb_opt( 'handover_prompt' );
        aicb_log( $session_id, $question, $answer, $page_id, $ip_hash, 'custom', 'handover-prompt' );
        wp_send_json_success( [ 'answer' => $answer, 'source' => 'custom-handover', 'provider' => 'custom', 'awaiting_confirmation' => true ] );
    }

    $page_context = aicb_retrieve_relevant_contexts( $question, $page_id );
    if ( ! empty( $page_context ) ) {
        $page_context = "\n\n--- KNOWLEDGE BASE DIRECTORY ---\n" . $page_context . "\n--- END DIRECTORY ---";
    }

    $custom_qas = $wpdb->get_results( "SELECT question, answer FROM {$qa_table} WHERE active = 1" );
    $custom_kb  = "";
    if ( ! empty( $custom_qas ) ) {
        $custom_kb = "\n\n--- CORE BUSINESS RULES & FAQS (PRIORITY) ---\nUse these exact rules and answers to reason and cross-reference. Always prioritize these facts over general knowledge. After cross-referencing, output ONLY your final answer — never include the reasoning steps or alternative scenarios in your response:\n";
        foreach ( $custom_qas as $q ) {
            $custom_kb .= "Q: {$q->question}\nA: {$q->answer}\n\n";
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

    $system_prompt = aicb_opt( 'system_prompt' ) . "\n\n" . $identity_prompt . $perspective_prompt . $tone_prompt . $temporal_pivot . $tool_instruction . $negative_constraints . $custom_kb . $page_context;
    $provider = aicb_opt( 'provider' );
    $model    = aicb_opt( 'model' );

    $result = aicb_call_ai( $provider, $model, $system_prompt, $question, aicb_opt( 'max_tokens' ) );

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
            wp_send_json_success( [ 'answer' => $answer, 'source' => 'ai-reasoning', 'provider' => $provider, 'awaiting_confirmation' => true ] );
        }
    }

    aicb_log( $session_id, $question, $answer, $page_id, $ip_hash, $provider, $model );
    wp_send_json_success( [ 'answer' => $answer, 'source' => 'ai-reasoning', 'provider' => $provider ] );
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

function aicb_get_user_ip() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
    } elseif ( defined( 'AICB_TRUST_PROXY' ) && AICB_TRUST_PROXY && ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
        $ips = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );
        $ip  = trim( $ips[0] );
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
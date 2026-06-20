<?php
defined( 'ABSPATH' ) || exit;

/**
 * Define the default configuration schema (Version 2.2.8).
 */
function aicb_default_options() {
    return [
        // Provider & model
        'provider'              => 'anthropic',
        'model'                 => 'claude-sonnet-4-20250514',
        // Display
        'position'              => 'right',
        'primary_color'         => '#2563eb',
        'icon'                  => 'chat',
        'chat_title'            => 'Chat with us',
        'welcome_msg'           => 'Hi! Ask me anything about this page.',
        'placeholder'           => 'Type your question…',
        'footer_text'           => 'Powered by AI',
        // Behaviour
        'max_tokens'            => 400,
        'rate_limit'            => 20,
        'system_prompt'         => "You are the official AI representative for this website. Answer questions using the provided context. Speak directly in the first-person ('we', 'our', 'us', 'I') as a member of our team. Never use third-person references ('they', 'them', 'their') when speaking about our services. Keep answers extremely concise, friendly, and under two sentences maximum. Avoid robotic intro phrases like 'Based on the context' or 'Available information suggests'.",
        'enabled'               => 1,
        'show_on_all'           => 1,
        'log_retention_days'    => 90, 
        // Cache Configuration
        'enable_cache'          => 1,
        'cache_duration'        => 0, 
        'indexing_mode'         => 'opt-out', 
        'indexed_post_types'    => [ 'page', 'post' ],
        // Handover / Escalation Configuration
        'enable_handover'       => 0,
        'handover_apology'      => "I apologize, I couldn't find that specific information in our database. Would you like to connect with our team?",
        'handover_prompt'       => "Would you like to connect with our team?",
        'handover_type'         => 'whatsapp', 
        'handover_target'       => '15551234567',
        'handover_btn_text'     => 'Connect with a live person',
        'contact_btn_text'      => 'Visit Contact Page',
        'contact_btn_url'       => home_url( '/contact/' ),
        'handover_trigger_phrases' => '',
        'show_footer_help_button'  => 1,
        // Handover Button Stylings
        'handover_primary_text'   => '#ffffff',
        'handover_secondary_bg'   => '#f1f5f9',
        'handover_secondary_text' => '#334155',
        'handover_btn_radius'     => 4,
        'always_show_handover_buttons' => 0,
        // AI Identity and Presets
        'business_name'           => '',
        'pronoun_perspective'     => 'first-plural', 
        'chatbot_tone'            => 'professional', 
        // Calendar / Hours (Tool-calling powered)
        'enable_calendar_tools'   => 1,
        'calendar_data'           => [
            'default_weekday_hours' => [ 'open' => '09:00', 'close' => '17:00' ],
            'default_weekend_hours' => [ 'open' => '10:00', 'close' => '15:00' ],
            'default_weekend_status' => 'closed',
            'entries'               => [],
        ],
        // Advanced Prompt Engineering Templates
        'prompt_temporal_pivot'   => "Today's Date: {current_date}\nCurrent Time: {current_time}\nUse this to compute relative dates when calling available tools.",
        'prompt_tool_instruction' => "- TOOL USE: You have access to a `check_calendar` tool. Whenever the user asks about business hours, holiday closures, or whether you are open on a specific day, call this tool.\n- TOOL VS FAQ: After receiving the tool result, check the 'source' field. If 'source' is 'default' (meaning no specific entry exists in the calendar for that date), check the CORE BUSINESS RULES & FAQS for any rule about the specific holiday or date. If the FAQ provides a more specific rule (e.g., 'Closed on Holidays', 'Open Christmas 10-1'), honor the FAQ as an override. If the FAQ is silent on the specific date, use the tool result. If 'source' is 'entry' (a specific calendar entry was found), use the tool result directly.\n- DATE ACCURACY: Use Today's Date (from TEMPORAL CONTEXT) to resolve relative day references (e.g., 'tomorrow', 'next Monday', 'this weekend') into a YYYY-MM-DD string for the tool.\n- NO RAW DATE REASONING: Never output reasoning about weekday vs weekend or holiday logic in your response. Let the tool determine the status and hours. After receiving the tool result, answer the user naturally and concisely.",
        'prompt_negative_constraints' => "- CONSISTENCY: Your answer must be internally consistent. Never state two facts that contradict each other. Never start a sentence with 'but', 'however', or 'although' — your answer must be a single, decisive statement with no hedging.\n- CONTEXT LEAK SAFEGUARD: Never mention the words 'context', 'reference block', 'database', 'page title', 'home page', or 'provided context' in your response. Answer as if you naturally and confidently know the information.\n- LENGTH RESTRICTION: Keep your answers concise. For simple factual questions, use 1-2 sentences. For questions involving relative dates (today/tomorrow/this week) or logical reasoning, up to 4 sentences is acceptable — but never write more than needed. Do not list other irrelevant page sections or links unless explicitly requested by the user.\n- DIRECTNESS: When the answer is clearly found in the CORE BUSINESS RULES & FAQS, answer directly from them. Do not second-guess or cross-reference page content for potential conflicts when the rules already provide a clear answer.\n- NO FEDERAL REFERENCE: Never use the terms 'federal', 'federal holiday', or 'federal holidays' when describing holiday closures or schedules. Always refer to them simply as 'holidays' or by their specific local name (e.g., 'Closed for Canada Day', 'Closed because of the holiday').\n- INTEGRITY: Use the CORE BUSINESS RULES and ACTIVE KNOWLEDGE REFERENCE together to reason accurately about the question. After determining the answer, output ONLY your final clean conclusion — never include the reasoning steps, alternative scenarios, or conditional statements in your response.",
        // Language & Localization
        'chatbot_language_mode'   => 'auto',
        'chatbot_language'        => '',
        // Feedback
        'enable_feedback'         => 0,
        // Lead Capture
        'enable_lead_capture'     => 0,
        'lead_notification_email' => '',
        // Transcript Export
        'enable_transcript_export' => 0,
    ];
}

/**
 * Safe settings accessor with option fallback.
 */
function aicb_opt( $key ) {
    $defaults = aicb_default_options();
    return get_option( 'aicb_' . $key, $defaults[ $key ] ?? '' );
}

/**
 * Verify WordPress security salts are safely configured.
 */
function aicb_has_secure_salts() {
    $salts = [ 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'AUTH_KEY', 'SECURE_AUTH_SALT' ];
    foreach ( $salts as $salt ) {
        if ( defined( $salt ) ) {
            $val = constant( $salt );
            if ( ! empty( $val ) && $val !== 'put your unique phrase here' ) {
                return true;
            }
        }
    }
    return false;
}

/**
 * Derive encryption key from security salts.
 */
function aicb_get_encryption_key() {
    if ( ! aicb_has_secure_salts() ) {
        return false;
    }
    $salt = defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : ( defined( 'LOGGED_IN_KEY' ) ? LOGGED_IN_KEY : AUTH_KEY );
    return hash( 'sha256', $salt, true );
}

/**
 * Secure AES-256-GCM encryption.
 */
function aicb_encrypt( $value ) {
    if ( empty( $value ) ) return '';
    $key = aicb_get_encryption_key();
    if ( ! $key ) return '';
    $iv_length = openssl_cipher_iv_length( 'aes-256-gcm' );
    $iv        = random_bytes( $iv_length );
    $tag       = '';
    $encrypted = openssl_encrypt( $value, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
    if ( false === $encrypted ) return '';
    return base64_encode( $iv . $tag . $encrypted );
}

/**
 * Secure AES-256-GCM decryption.
 */
function aicb_decrypt( $value ) {
    if ( empty( $value ) ) return '';
    $key = aicb_get_encryption_key();
    if ( ! $key ) return '';
    $data = base64_decode( $value, true );
    if ( ! $data ) return '';
    $iv_length  = openssl_cipher_iv_length( 'aes-256-gcm' );
    $tag_length = 16;
    if ( strlen( $data ) < ( $iv_length + $tag_length ) ) return '';
    $iv        = substr( $data, 0, $iv_length );
    $tag       = substr( $data, $iv_length, $tag_length );
    $encrypted = substr( $data, $iv_length + $tag_length );
    $decrypted = openssl_decrypt( $encrypted, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
    return false !== $decrypted ? $decrypted : '';
}

/**
 * Fetch and safely decrypt API keys.
 */
function aicb_get_key( $provider ) {
    $const_name = 'AICB_KEY_' . strtoupper( sanitize_key( $provider ) );
    if ( defined( $const_name ) ) {
        return constant( $const_name );
    }
    $val = get_option( 'aicb_key_' . sanitize_key( $provider ), '' );
    if ( empty( $val ) ) return '';
    $decrypted = aicb_decrypt( $val );
    if ( '' === $decrypted && '' !== $val ) {
        if ( aicb_has_secure_salts() ) {
            $encrypted = aicb_encrypt( $val );
            if ( ! empty( $encrypted ) ) {
                update_option( 'aicb_key_' . $provider, $encrypted );
                return $val;
            }
        }
        return '';
    }
    return $decrypted;
}

/**
 * Server Side Request Forgery (SSRF) validation.
 */
function aicb_is_valid_endpoint( $url ) {
    if ( empty( $url ) ) return false;
    $parsed = wp_parse_url( $url );
    if ( ! isset( $parsed['scheme'] ) || ! in_array( strtolower( $parsed['scheme'] ), [ 'http', 'https' ], true ) ) {
        return false;
    }
    $env = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
    $is_local_dev = in_array( $env, [ 'local', 'development', 'staging' ], true );
    if ( $is_local_dev ) {
        if ( defined( 'AICB_ALLOW_LOCAL_ENDPOINTS' ) && AICB_ALLOW_LOCAL_ENDPOINTS ) return true;
        if ( apply_filters( 'aicb_allow_local_endpoints', false ) ) return true;
    }
    return (bool) wp_http_validate_url( $url );
}

/**
 * Helper to display standardized US dates in the admin views.
 */
function aicb_format_date_us( $date_str ) {
    if ( empty( $date_str ) ) return '';
    if ( 0 === strpos( $date_str, '--' ) ) {
        $clean = ltrim( $date_str, '-' );
        $clean = str_replace( '-', '/', $clean );
        return $clean . ' (Every Year)';
    }
    $ts = strtotime( $date_str );
    return $ts ? date( 'm/d/Y', $ts ) : $date_str;
}

/**
 * Helper to convert US dates back to SQL/ISO standard for options storage.
 */
function aicb_convert_date_to_iso( $date_str ) {
    $date_str = trim( $date_str );
    if ( empty( $date_str ) ) return '';
    $clean_recur = ltrim( $date_str, '-' );
    if ( preg_match( '/^(\d{1,2})[\-\/](\d{1,2})$/', $clean_recur, $matches ) ) {
        return '--' . sprintf( '%02d-%02d', $matches[1], $matches[2] );
    }
    $ts = strtotime( $date_str );
    if ( $ts ) return date( 'Y-m-d', $ts );
    return $date_str;
}
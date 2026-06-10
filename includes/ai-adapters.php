<?php
defined( 'ABSPATH' ) || exit;

/**
 * Retrieve the static models and API provider specifications catalog.
 */
function aicb_get_catalog() {
    static $catalog = null;
    if ( $catalog !== null ) return $catalog;
    $file = AICB_DIR . 'assets/models.json';
    if ( ! file_exists( $file ) ) return [ 'providers' => [] ];
    $json = file_get_contents( $file );
    $catalog = json_decode( $json, true ) ?: [ 'providers' => [] ];
    return $catalog;
}

function aicb_get_providers() {
    $catalog = aicb_get_catalog();
    $out = [];
    foreach ( $catalog['providers'] as $p ) {
        $out[ $p['id'] ] = $p;
    }
    return $out;
}

function aicb_get_models( $provider_id ) {
    $providers = aicb_get_providers();
    return $providers[ $provider_id ]['models'] ?? [];
}

/**
 * AI Tool Call: Fetch schedule details on a specific date.
 */
function aicb_tool_check_calendar( $date_str ) {
    $calendar = (array) aicb_opt( 'calendar_data' );
    $entries  = $calendar['entries'] ?? [];
    $ts = strtotime( $date_str );
    if ( ! $ts ) {
        return [ 'is_holiday' => false, 'name' => '', 'status' => 'unknown', 'hours' => null ];
    }
    $ymd   = date( 'Y-m-d', $ts );
    $mmdd  = date( 'm-d',  $ts ); 
    $day_n = (int) date( 'N', $ts ); 
    $is_weekend = ( $day_n >= 6 );

    foreach ( $entries as $e ) {
        if ( ( $e['date'] ?? '' ) === $ymd ) {
            return [
                'is_holiday' => ( $e['status'] ?? 'open' ) !== 'open',
                'name'       => $e['label'] ?? '',
                'status'     => $e['status'] ?? 'open',
                'hours'      => ( ! empty( $e['hours_open'] ) && ! empty( $e['hours_close'] ) ) ? $e['hours_open'] . '-' . $e['hours_close'] : null,
                'source'     => 'entry',
            ];
        }
    }

    foreach ( $entries as $e ) {
        $e_date = $e['date'] ?? '';
        $e_mmdd = ltrim( $e_date, '-' );
        if ( $e_mmdd === $mmdd ) {
            return [
                'is_holiday' => ( $e['status'] ?? 'open' ) !== 'open',
                'name'       => $e['label'] ?? '',
                'status'     => $e['status'] ?? 'open',
                'hours'      => ( ! empty( $e['hours_open'] ) && ! empty( $e['hours_close'] ) ) ? $e['hours_open'] . '-' . $e['hours_close'] : null,
                'source'     => 'entry',
            ];
        }
    }

    $default_open   = $is_weekend ? ( $calendar['default_weekend_hours']['open'] ?? '10:00' ) : ( $calendar['default_weekday_hours']['open'] ?? '09:00' );
    $default_close  = $is_weekend ? ( $calendar['default_weekend_hours']['close'] ?? '15:00' ) : ( $calendar['default_weekday_hours']['close'] ?? '17:00' );
    $default_status = $is_weekend ? ( $calendar['default_weekend_status'] ?? 'closed' ) : 'open';

    return [
        'is_holiday' => ( $default_status !== 'open' ),
        'name'       => $is_weekend ? 'Weekend' : '',
        'status'     => $default_status,
        'hours'      => ( $default_status === 'open' ) ? $default_open . '-' . $default_close : null,
        'source'     => 'default',
    ];
}

function aicb_get_calendar_tool_definition_openai() {
    return [
        'type'     => 'function',
        'function' => [
            'name'        => 'check_calendar',
            'description' => 'Call this tool whenever the user asks if the business is open or closed on a specific day, holiday, or date.',
            'parameters'  => [
                'type'       => 'object',
                'properties' => [
                    'date' => [
                        'type'        => 'string',
                        'description' => 'The date to check in YYYY-MM-DD format. Calculate this relative to the current system date provided.',
                    ],
                ],
                'required'   => [ 'date' ],
            ],
        ],
    ];
}

function aicb_get_calendar_tool_definition_anthropic() {
    return [
        'name'         => 'check_calendar',
        'description'  => 'Call this tool whenever the user asks if the business is open or closed on a specific day, holiday, or date.',
        'input_schema' => [
            'type'       => 'object',
            'properties' => [
                'date' => [
                    'type'        => 'string',
                    'description' => 'The date to check in YYYY-MM-DD format. Calculate this relative to the current system date provided.',
                ],
            ],
            'required'   => [ 'date' ],
        ],
    ];
}

/**
 * Route incoming queries to their configured AI adapters.
 */
function aicb_call_ai( $provider, $model, $system, $question, $max_tokens ) {
    $key = aicb_get_key( $provider );
    if ( $provider === 'custom' ) {
        return aicb_adapter_openai_compat( aicb_opt( 'custom_endpoint' ), $key, aicb_opt( 'custom_model_id' ) ?: $model, $system, $question, $max_tokens );
    }
    if ( empty( $key ) ) {
        return new WP_Error( 'no_key', 'No API key configured for this provider.' );
    }
    switch ( $provider ) {
        case 'anthropic':
            return aicb_adapter_anthropic( $key, $model, $system, $question, $max_tokens );
        case 'groq':
            return aicb_adapter_openai_compat( 'https://api.groq.com/openai/v1/chat/completions', $key, $model, $system, $question, $max_tokens );
        case 'google':
            return aicb_adapter_google( $key, $model, $system, $question, $max_tokens );
        case 'cerebras':
            return aicb_adapter_openai_compat( 'https://api.cerebras.ai/v1/chat/completions', $key, $model, $system, $question, $max_tokens );
        case 'mistral':
            return aicb_adapter_openai_compat( 'https://api.mistral.ai/v1/chat/completions', $key, $model, $system, $question, $max_tokens );
        default:
            return new WP_Error( 'unknown_provider', 'Unknown provider: ' . $provider );
    }
}

/**
 * AI Adapter: Anthropic Claude Messages API
 */
function aicb_adapter_anthropic( $key, $model, $system, $question, $max_tokens ) {
    $response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
        'timeout' => 30,
        'headers' => [
            'Content-Type'      => 'application/json',
            'x-api-key'         => $key,
            'anthropic-version' => '2023-06-01',
        ],
        'body' => wp_json_encode( [
            'model'      => $model,
            'max_tokens' => (int) $max_tokens,
            'system'     => $system,
            'messages'   => [ [ 'role' => 'user', 'content' => $question ] ],
        ] ),
    ] );
    if ( is_wp_error( $response ) ) return $response;
    $code = wp_remote_retrieve_response_code( $response );
    $data = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( $code !== 200 || empty( $data['content'][0]['text'] ) ) {
        $msg = $data['error']['message'] ?? 'Anthropic API connection error.';
        return new WP_Error( 'api_error', $msg );
    }
    return [ 'answer' => $data['content'][0]['text'] ];
}

/**
 * AI Adapter: OpenAI-Compatible / Custom Endpoint API
 */
function aicb_adapter_openai_compat( $endpoint, $key, $model, $system, $question, $max_tokens ) {
    if ( empty( $endpoint ) ) return new WP_Error( 'no_endpoint', 'No endpoint configured.' );
    if ( ! aicb_is_valid_endpoint( $endpoint ) ) return new WP_Error( 'unsafe_endpoint', 'The target endpoint is restricted or invalid.' );

    $headers = [ 'Content-Type' => 'application/json' ];
    if ( ! empty( $key ) ) {
        $headers['Authorization'] = 'Bearer ' . $key;
    }

    $messages = [
        [ 'role' => 'system', 'content' => $system ],
        [ 'role' => 'user',   'content' => $question ],
    ];

    $body = [
        'model'      => $model,
        'max_tokens' => (int) $max_tokens,
        'messages'   => $messages,
    ];

    $has_tool_support = false;
    $catalog  = aicb_get_catalog();
    foreach ( $catalog['providers'] as $p ) {
        if ( $p['id'] === 'custom' || strpos( $endpoint, $p['id'] ) !== false || strpos( $endpoint, $p['website'] ?? '' ) !== false ) {
            foreach ( $p['models'] ?? [] as $m ) {
                if ( $m['id'] === $model && ! empty( $m['supports_tools'] ) ) {
                    $has_tool_support = true;
                    break 2;
                }
            }
        }
    }
    if ( ! $has_tool_support && aicb_opt( 'enable_calendar_tools' ) ) {
        $has_tool_support = true; 
    }

    if ( aicb_opt( 'enable_calendar_tools' ) && $has_tool_support ) {
        $body['tools'] = [ aicb_get_calendar_tool_definition_openai() ];
        $body['tool_choice'] = 'auto';
    }

    $response = wp_remote_post( $endpoint, [
        'timeout' => 30,
        'headers' => $headers,
        'body'    => wp_json_encode( $body ),
    ] );
    if ( is_wp_error( $response ) ) return $response;

    $code = wp_remote_retrieve_response_code( $response );
    $data = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( $code !== 200 ) {
        $msg = $data['error']['message'] ?? 'Provider connection error.';
        return new WP_Error( 'api_error', $msg );
    }

    $message = $data['choices'][0]['message'] ?? [];
    if ( ! empty( $message['tool_calls'] ) && aicb_opt( 'enable_calendar_tools' ) ) {
        $messages[] = $message; 
        foreach ( $message['tool_calls'] as $tool_call ) {
            if ( $tool_call['function']['name'] === 'check_calendar' ) {
                $args = json_decode( $tool_call['function']['arguments'], true );
                $date_str = $args['date'] ?? '';
                $result   = aicb_tool_check_calendar( $date_str );
                $messages[] = [
                    'role'       => 'tool',
                    'tool_call_id' => $tool_call['id'],
                    'content'    => wp_json_encode( $result ),
                ];
            }
        }
        $body['messages']   = $messages;
        $body['tool_choice'] = 'none'; 

        $response2 = wp_remote_post( $endpoint, [
            'timeout' => 30,
            'headers' => $headers,
            'body'    => wp_json_encode( $body ),
        ] );
        if ( is_wp_error( $response2 ) ) return $response2;
        $code2 = wp_remote_retrieve_response_code( $response2 );
        $data2 = json_decode( wp_remote_retrieve_body( $response2 ), true );
        if ( $code2 !== 200 || empty( $data2['choices'][0]['message']['content'] ) ) {
            $msg = $data2['error']['message'] ?? 'Provider connection error.';
            return new WP_Error( 'api_error', $msg );
        }
        return [ 'answer' => $data2['choices'][0]['message']['content'] ];
    }

    if ( empty( $message['content'] ) ) {
        $msg = $data['error']['message'] ?? 'Provider connection error.';
        return new WP_Error( 'api_error', $msg );
    }
    return [ 'answer' => $message['content'] ];
}

/**
 * AI Adapter: Google Gemini API (generateContent)
 */
function aicb_adapter_google( $key, $model, $system, $question, $max_tokens ) {
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . urlencode( $model ) . ':generateContent';
    $response = wp_remote_post( $url, [
        'timeout' => 30,
        'headers' => [ 'Content-Type' => 'application/json', 'x-goog-api-key' => $key ],
        'body'    => wp_json_encode( [
            'system_instruction' => [ 'parts' => [ [ 'text' => $system ] ] ],
            'contents'           => [ [ 'role' => 'user', 'parts' => [ [ 'text' => $question ] ] ] ],
            'generationConfig'   => [ 'maxOutputTokens' => (int) $max_tokens ],
        ] ),
    ] );
    if ( is_wp_error( $response ) ) return $response;
    $code = wp_remote_retrieve_response_code( $response );
    $data = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( $code !== 200 || empty( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
        $msg = $data['error']['message'] ?? 'Google API connection error.';
        return new WP_Error( 'api_error', $msg );
    }
    return [ 'answer' => $data['candidates'][0]['content']['parts'][0]['text'] ];
}
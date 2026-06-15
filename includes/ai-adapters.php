<?php
defined( 'ABSPATH' ) || exit;

/**
 * Retrieve the models and API provider specifications catalog from the database,
 * falling back to the JSON seed file only if the DB table is empty.
 */
function aicb_get_catalog() {
    static $catalog = null;
    if ( $catalog !== null ) return $catalog;

    global $wpdb;
    $table = $wpdb->prefix . AICB_MODEL_TABLE;

    // Check if table exists and has rows
    $table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );
    if ( ! $table_exists ) {
        // Fallback to JSON if DB table doesn't exist yet
        $file = AICB_DIR . 'assets/models.json';
        if ( ! file_exists( $file ) ) return [ 'providers' => [] ];
        $json = file_get_contents( $file );
        $catalog = json_decode( $json, true ) ?: [ 'providers' => [] ];
        return $catalog;
    }

    $rows = $wpdb->get_results(
        "SELECT provider_id, provider_name, model_id, name, description, context_k, recommended, supports_tools
         FROM {$table}
         WHERE active = 1
         ORDER BY sort_order ASC, id ASC"
    );

    if ( empty( $rows ) ) {
        // Fallback to JSON if DB is empty (first run, table just created but seeding may not have happened)
        $file = AICB_DIR . 'assets/models.json';
        if ( ! file_exists( $file ) ) return [ 'providers' => [] ];
        $json = file_get_contents( $file );
        $catalog = json_decode( $json, true ) ?: [ 'providers' => [] ];
        return $catalog;
    }

    // Build the catalog structure grouped by provider
    $provider_map = [];
    $provider_order = [];

    foreach ( $rows as $row ) {
        $pid = $row->provider_id;
        if ( ! isset( $provider_map[ $pid ] ) ) {
            $provider_map[ $pid ] = [
                'id'       => $pid,
                'name'     => $row->provider_name ?: $pid,
                'website'  => '',
                'key_label' => '',
                'key_help'  => '',
                'docs_url'  => '',
                'models'   => [],
            ];
            $provider_order[] = $pid;
        }

        $provider_map[ $pid ]['models'][] = [
            'id'             => $row->model_id,
            'name'           => $row->name,
            'description'    => $row->description,
            'context_k'      => (int) $row->context_k,
            'recommended'    => ! empty( $row->recommended ),
            'supports_tools' => ! empty( $row->supports_tools ),
        ];
    }

    $catalog = [ 'providers' => [] ];
    foreach ( $provider_order as $pid ) {
        $catalog['providers'][] = $provider_map[ $pid ];
    }

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
 * Temporary diagnostic logger. Writes to ai-chatbot-debug.log in the plugin directory.
 */
function aicb_debug_log( $message ) {
    $file = AICB_DIR . 'ai-chatbot-debug.log';
    $line = '[' . wp_date( 'Y-m-d H:i:s' ) . '] ' . $message . "\n";
    file_put_contents( $file, $line, FILE_APPEND | LOCK_EX );
}

/**
 * Helper: Extract system prompt string from a messages array.
 * Assumes the first message with role 'system' contains the system prompt content.
 */
function aicb_extract_system_prompt( $messages ) {
    foreach ( $messages as $msg ) {
        if ( isset( $msg['role'] ) && $msg['role'] === 'system' ) {
            return $msg['content'] ?? '';
        }
    }
    return '';
}

/**
 * Helper: Filter out system messages from a messages array, returning only conversation messages.
 */
function aicb_filter_conversation_messages( $messages ) {
    return array_values( array_filter( $messages, function( $msg ) {
        return isset( $msg['role'] ) && $msg['role'] !== 'system';
    } ) );
}

/**
 * Helper: Execute wp_remote_post with a single retry on transient 502/503 errors.
 * Writes diagnostic info to ai-chatbot-debug.log in the plugin directory.
 */
function aicb_remote_post_retry( $url, $args, $max_retries = 1 ) {
    $attempt = 0;
    do {
        $response = wp_remote_post( $url, $args );
        if ( is_wp_error( $response ) ) {
            aicb_debug_log( 'AICB HTTP Error: ' . $response->get_error_message() );
            return $response;
        }
        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 502 && $code !== 503 ) {
            return $response;
        }
        $body = wp_remote_retrieve_body( $response );
        $msg_count = 0;
        if ( isset( $args['body'] ) ) {
            $decoded = json_decode( $args['body'], true );
            $msg_count = count( $decoded['messages'] ?? [] );
        }
        aicb_debug_log( 'AICB API 5xx attempt=' . ( $attempt + 1 ) . ' code=' . $code . ' messages=' . $msg_count . ' url=' . $url . ' body=' . substr( $body, 0, 300 ) );
        $attempt++;
        if ( $attempt <= $max_retries ) {
            usleep( 500000 );
        }
    } while ( $attempt <= $max_retries );
    return $response;
}

/**
 * Route incoming queries to their configured AI adapters.
 *
 * @param string $provider   Provider ID.
 * @param string $model      Model name/ID.
 * @param array  $messages   Full messages array (system + user/assistant history).
 * @param int    $max_tokens Max tokens for response.
 * @return array|WP_Error
 */
function aicb_call_ai( $provider, $model, $messages, $max_tokens ) {
    $key = aicb_get_key( $provider );
    if ( $provider === 'custom' ) {
        return aicb_adapter_openai_compat( aicb_opt( 'custom_endpoint' ), $key, aicb_opt( 'custom_model_id' ) ?: $model, $messages, $max_tokens );
    }
    if ( empty( $key ) ) {
        return new WP_Error( 'no_key', 'No API key configured for this provider.' );
    }
    switch ( $provider ) {
        case 'anthropic':
            return aicb_adapter_anthropic( $key, $model, $messages, $max_tokens );
        case 'groq':
            return aicb_adapter_openai_compat( 'https://api.groq.com/openai/v1/chat/completions', $key, $model, $messages, $max_tokens );
        case 'google':
            return aicb_adapter_google( $key, $model, $messages, $max_tokens );
        case 'cerebras':
            return aicb_adapter_openai_compat( 'https://api.cerebras.ai/v1/chat/completions', $key, $model, $messages, $max_tokens );
        case 'mistral':
            return aicb_adapter_openai_compat( 'https://api.mistral.ai/v1/chat/completions', $key, $model, $messages, $max_tokens );
        default:
            return new WP_Error( 'unknown_provider', 'Unknown provider: ' . $provider );
    }
}

/**
 * AI Adapter: Anthropic Claude Messages API
 *
 * @param string $key        API key.
 * @param string $model      Model name.
 * @param array  $messages   Full messages array (system prompt extracted automatically).
 * @param int    $max_tokens Max tokens.
 * @return array|WP_Error
 */
function aicb_adapter_anthropic( $key, $model, $messages, $max_tokens ) {
    $system    = aicb_extract_system_prompt( $messages );
    $conv_msgs = aicb_filter_conversation_messages( $messages );

    $body = [
        'model'      => $model,
        'max_tokens' => (int) $max_tokens,
        'messages'   => ! empty( $conv_msgs ) ? $conv_msgs : [ [ 'role' => 'user', 'content' => '' ] ],
    ];
    if ( ! empty( $system ) ) {
        $body['system'] = $system;
    }

    $response = aicb_remote_post_retry( 'https://api.anthropic.com/v1/messages', [
        'timeout' => 30,
        'headers' => [
            'Content-Type'      => 'application/json',
            'x-api-key'         => $key,
            'anthropic-version' => '2023-06-01',
        ],
        'body' => wp_json_encode( $body ),
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
 *
 * @param string $endpoint   API endpoint URL.
 * @param string $key        API key.
 * @param string $model      Model name.
 * @param array  $messages   Full messages array (including system).
 * @param int    $max_tokens Max tokens.
 * @return array|WP_Error
 */
function aicb_adapter_openai_compat( $endpoint, $key, $model, $messages, $max_tokens ) {
    if ( empty( $endpoint ) ) return new WP_Error( 'no_endpoint', 'No endpoint configured.' );
    if ( ! aicb_is_valid_endpoint( $endpoint ) ) return new WP_Error( 'unsafe_endpoint', 'The target endpoint is restricted or invalid.' );

    $headers = [ 'Content-Type' => 'application/json' ];
    if ( ! empty( $key ) ) {
        $headers['Authorization'] = 'Bearer ' . $key;
    }

    $body = [
        'model'      => $model,
        'max_tokens' => (int) $max_tokens,
        'messages'   => $messages,
    ];

    // Check tool support: query the DB directly instead of scanning the JSON catalog
    $has_tool_support = false;
    global $wpdb;
    $mtable = $wpdb->prefix . AICB_MODEL_TABLE;
    $mtable_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $mtable ) );
    if ( $mtable_exists ) {
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT supports_tools FROM {$mtable} WHERE model_id = %s AND active = 1 LIMIT 1",
            $model
        ) );
        if ( $row && ! empty( $row->supports_tools ) ) {
            $has_tool_support = true;
        }
    } else {
        // Fallback to old catalog scan if DB table doesn't exist yet
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
    }
    if ( aicb_opt( 'enable_calendar_tools' ) && $has_tool_support ) {
        $body['tools'] = [ aicb_get_calendar_tool_definition_openai() ];
        $body['tool_choice'] = 'auto';
    }

    $response = aicb_remote_post_retry( $endpoint, [
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

        $response2 = aicb_remote_post_retry( $endpoint, [
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
 *
 * @param string $key        API key.
 * @param string $model      Model name.
 * @param array  $messages   Full messages array (system extracted for system_instruction).
 * @param int    $max_tokens Max tokens.
 * @return array|WP_Error
 */
function aicb_adapter_google( $key, $model, $messages, $max_tokens ) {
    $system    = aicb_extract_system_prompt( $messages );
    $conv_msgs = aicb_filter_conversation_messages( $messages );

    $contents = [];
    foreach ( $conv_msgs as $msg ) {
        $role = ( $msg['role'] === 'assistant' ) ? 'model' : 'user';
        $contents[] = [
            'role'  => $role,
            'parts' => [ [ 'text' => $msg['content'] ?? '' ] ],
        ];
    }
    if ( empty( $contents ) ) {
        $contents[] = [ 'role' => 'user', 'parts' => [ [ 'text' => '' ] ] ];
    }

    $body = [
        'contents'         => $contents,
        'generationConfig' => [ 'maxOutputTokens' => (int) $max_tokens ],
    ];
    if ( ! empty( $system ) ) {
        $body['system_instruction'] = [ 'parts' => [ [ 'text' => $system ] ] ];
    }

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . urlencode( $model ) . ':generateContent';
    $response = aicb_remote_post_retry( $url, [
        'timeout' => 30,
        'headers' => [ 'Content-Type' => 'application/json', 'x-goog-api-key' => $key ],
        'body'    => wp_json_encode( $body ),
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
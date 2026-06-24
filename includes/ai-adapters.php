<?php
defined( 'ABSPATH' ) || exit;

/**
 * Retrieve the models and API provider specifications catalog from the database,
 * falling back to the JSON seed file only if the DB table is empty.
 */
function aicb_get_catalog() {
	static $catalog = null;
	if ( $catalog !== null ) {
		return $catalog;
	}

	global $wpdb;
	$table = $wpdb->prefix . AICB_MODEL_TABLE;

	// Check if table exists and has rows
	$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	if ( ! $table_exists ) {
		// Fallback to JSON if DB table doesn't exist yet
		$file = AICB_DIR . 'assets/models.json';
		if ( ! file_exists( $file ) ) {
			return array( 'providers' => array() );
		}
		$json    = file_get_contents( $file );
		$catalog = json_decode( $json, true ) ?: array( 'providers' => array() );
		return $catalog;
	}

	$rows = $wpdb->get_results(
		"SELECT provider_id, provider_name, model_id, name, description, context_k, recommended, supports_tools, api_key, api_endpoint
         FROM {$table}
         WHERE active = 1
         ORDER BY sort_order ASC, id ASC"
	);

	if ( empty( $rows ) ) {
		// Fallback to JSON if DB is empty (first run, table just created but seeding may not have happened)
		$file = AICB_DIR . 'assets/models.json';
		if ( ! file_exists( $file ) ) {
			return array( 'providers' => array() );
		}
		$json    = file_get_contents( $file );
		$catalog = json_decode( $json, true ) ?: array( 'providers' => array() );
		return $catalog;
	}

	// Build the catalog structure grouped by provider
	$provider_map   = array();
	$provider_order = array();

	foreach ( $rows as $row ) {
		$pid = $row->provider_id;
		if ( ! isset( $provider_map[ $pid ] ) ) {
			$provider_map[ $pid ] = array(
				'id'        => $pid,
				'name'      => $row->provider_name ?: $pid,
				'website'   => '',
				'key_label' => '',
				'key_help'  => '',
				'docs_url'  => '',
				'models'    => array(),
			);
			$provider_order[]     = $pid;
		}

		$provider_map[ $pid ]['models'][] = array(
			'id'             => $row->model_id,
			'name'           => $row->name,
			'description'    => $row->description,
			'context_k'      => (int) $row->context_k,
			'recommended'    => ! empty( $row->recommended ),
			'supports_tools' => ! empty( $row->supports_tools ),
			'api_endpoint'   => $row->api_endpoint,
			'api_key'        => $row->api_key,
		);
	}

	$catalog = array( 'providers' => array() );
	foreach ( $provider_order as $pid ) {
		$catalog['providers'][] = $provider_map[ $pid ];
	}

	return $catalog;
}

function aicb_get_providers() {
	$catalog = aicb_get_catalog();
	$out     = array();
	foreach ( $catalog['providers'] as $p ) {
		$out[ $p['id'] ] = $p;
	}
	return $out;
}

function aicb_get_models( $provider_id ) {
	$providers = aicb_get_providers();
	return $providers[ $provider_id ]['models'] ?? array();
}

/**
 * Format a time string (H:i) according to the site's WordPress time format setting.
 * Respects the admin's choice of 12-hour or 24-hour format in Settings > General.
 *
 * @param string $time Time string in H:i format (e.g. "09:00" or "17:00").
 * @return string Formatted time (e.g. "9:00 AM" or "17:00").
 */
function aicb_format_time( $time ) {
	if ( empty( $time ) ) {
		return '';
	}
	$format = get_option( 'time_format', 'g:i A' );
	$dt     = DateTime::createFromFormat( 'H:i', $time );
	return $dt ? $dt->format( $format ) : $time;
}

/**
 * Helper: Search calendar entries by label.
 * Extracted to reduce cyclomatic complexity of check_calendar tool (PHP-R1006).
 */
function aicb_search_calendar_by_label( $entries, $label ) {
	$label_lower = strtolower( trim( $label ) );
	$matches     = array();
	foreach ( $entries as $e ) {
		$entry_label = strtolower( trim( $e['label'] ?? '' ) );
		if ( ! empty( $entry_label ) && ( strpos( $entry_label, $label_lower ) !== false || strpos( $label_lower, $entry_label ) !== false ) ) {
			$matches[] = array(
				'date'   => $e['date'] ?? '',
				'name'   => $e['label'] ?? '',
				'status' => $e['status'] ?? 'open',
				'hours'  => ( ! empty( $e['hours_open'] ) && ! empty( $e['hours_close'] ) ) ? aicb_format_time( $e['hours_open'] ) . ' - ' . aicb_format_time( $e['hours_close'] ) : null,
			);
		}
	}
	return array(
		'matches' => $matches,
		'count'   => count( $matches ),
		'source'  => 'label_search',
	);
}

/**
 * Helper: Retrieve default operating hours parameters.
 * Extracted to reduce cyclomatic complexity of check_calendar tool (PHP-R1006).
 */
function aicb_get_calendar_default_hours( $calendar, $is_weekend ) {
	$default_open   = $is_weekend ? ( $calendar['default_weekend_hours']['open'] ?? '10:00' ) : ( $calendar['default_weekday_hours']['open'] ?? '09:00' );
	$default_close  = $is_weekend ? ( $calendar['default_weekend_hours']['close'] ?? '15:00' ) : ( $calendar['default_weekday_hours']['close'] ?? '17:00' );
	$default_status = $is_weekend ? ( $calendar['default_weekend_status'] ?? 'closed' ) : 'open';

	return array(
		'is_holiday' => ( $default_status !== 'open' ),
		'name'       => $is_weekend ? 'Weekend' : '',
		'status'     => $default_status,
		'hours'      => ( $default_status === 'open' ) ? aicb_format_time( $default_open ) . ' - ' . aicb_format_time( $default_close ) : null,
		'source'     => 'default',
	);
}

/**
 * AI Tool Call: Fetch schedule details on a specific date or search by event label.
 * Refactored to reduce cyclomatic complexity from 23 to 7 (resolves PHP-R1006).
 *
 * @param string $date_str Date in YYYY-MM-DD format, or empty if searching by label.
 * @param string $label    Optional event name/label to search for (case-insensitive fuzzy match).
 * @return array
 */
function aicb_tool_check_calendar( $date_str, $label = '' ) {
	$calendar = (array) aicb_opt( 'calendar_data' );
	$entries  = $calendar['entries'] ?? array();

	// Search by label: return all matching entries
	if ( empty( $date_str ) && ! empty( $label ) ) {
		return aicb_search_calendar_by_label( $entries, $label );
	}

	$ts = strtotime( $date_str );
	if ( ! $ts ) {
		return array(
			'is_holiday' => false,
			'name'       => '',
			'status'     => 'unknown',
			'hours'      => null,
		);
	}
	$ymd        = date( 'Y-m-d', $ts );
	$mmdd       = date( 'm-d', $ts );
	$day_n      = (int) date( 'N', $ts );
	$is_weekend = ( $day_n >= 6 );

	// 1. Look for specific date matches (YYYY-MM-DD)
	foreach ( $entries as $e ) {
		if ( ( $e['date'] ?? '' ) === $ymd ) {
			return array(
				'is_holiday' => ( $e['status'] ?? 'open' ) !== 'open',
				'name'       => $e['label'] ?? '',
				'status'     => $e['status'] ?? 'open',
				'hours'      => ( ! empty( $e['hours_open'] ) && ! empty( $e['hours_close'] ) ) ? aicb_format_time( $e['hours_open'] ) . ' - ' . aicb_format_time( $e['hours_close'] ) : null,
				'source'     => 'entry',
			);
		}
	}

	// 2. Look for recurring holiday matches (MM-DD)
	foreach ( $entries as $e ) {
		$e_date = $e['date'] ?? '';
		$e_mmdd = ltrim( $e_date, '-' );
		if ( $e_mmdd === $mmdd ) {
			return array(
				'is_holiday' => ( $e['status'] ?? 'open' ) !== 'open',
				'name'       => $e['label'] ?? '',
				'status'     => $e['status'] ?? 'open',
				'hours'      => ( ! empty( $e['hours_open'] ) && ! empty( $e['hours_close'] ) ) ? aicb_format_time( $e['hours_open'] ) . ' - ' . aicb_format_time( $e['hours_close'] ) : null,
				'source'     => 'entry',
			);
		}
	}

	// 3. Fallback to standard calendar default configurations
	return aicb_get_calendar_default_hours( $calendar, $is_weekend );
}

function aicb_get_calendar_tool_definition_openai() {
	return array(
		'type'     => 'function',
		'function' => array(
			'name'        => 'check_calendar',
			'description' => 'Look up business hours, holidays, or special events by date or event name. Use this whenever the user asks about schedule, hours, events, or specific named occasions.',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'date'  => array(
						'type'        => 'string',
						'description' => 'The date to check in YYYY-MM-DD format. Calculate this relative to the current system date provided. Omit if searching by event name instead.',
					),
					'label' => array(
						'type'        => 'string',
						'description' => 'An event name or label to search for (e.g. "special event", "holiday", "Christmas"). Use this when the user asks about a specific named event rather than a date. Omit if searching by date instead.',
					),
				),
				'required'   => array(),
			),
		),
	);
}

function aicb_get_calendar_tool_definition_anthropic() {
	return array(
		'name'         => 'check_calendar',
		'description'  => 'Look up business hours, holidays, or special events by date or event name. Use this whenever the user asks about schedule, hours, events, or specific named occasions.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'date'  => array(
					'type'        => 'string',
					'description' => 'The date to check in YYYY-MM-DD format. Calculate this relative to the current system date provided. Omit if searching by event name instead.',
				),
				'label' => array(
					'type'        => 'string',
					'description' => 'An event name or label to search for (e.g. "special event", "holiday", "Christmas"). Use this when the user asks about a specific named event rather than a date. Omit if searching by date instead.',
				),
			),
			'required'   => array(),
		),
	);
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
	return array_values(
		array_filter(
			$messages,
			function ( $msg ) {
				return isset( $msg['role'] ) && $msg['role'] !== 'system';
			}
		)
	);
}

/**
 * Helper: Execute wp_remote_post with a single retry on transient 502/503 errors.
 */
function aicb_remote_post_retry( $url, $args, $max_retries = 1 ) {
	$attempt = 0;
	do {
		$response = wp_remote_post( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = wp_remote_retrieve_response_code( $response );
		if ( $code !== 502 && $code !== 503 ) {
			return $response;
		}
		++$attempt;
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
	$key      = aicb_get_key( $provider );
	$endpoint = '';

	// Route Custom Provider Overrides directly
	if ( $provider === 'custom' ) {
		global $wpdb;
		$table        = $wpdb->prefix . AICB_MODEL_TABLE;
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $table_exists ) {
			$custom_row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT api_key, api_endpoint FROM {$table} WHERE provider_id = 'custom' AND model_id = %s AND active = 1 LIMIT 1",
					$model
				)
			);
			if ( $custom_row ) {
				if ( ! empty( $custom_row->api_endpoint ) ) {
					$endpoint = esc_url_raw( $custom_row->api_endpoint );
				}
				if ( ! empty( $custom_row->api_key ) ) {
					$key = aicb_decrypt( $custom_row->api_key );
				}
			}
		}

		// Custom models require a valid endpoint configured on the AI Models page
		if ( empty( $endpoint ) ) {
			return new WP_Error( 'no_endpoint', 'No Base URL Endpoint configured for this custom model. Please configure it in AI Chatbot > Models.' );
		}

		return aicb_adapter_openai_compat( $endpoint, $key, $model, $messages, $max_tokens );
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

	$body = array(
		'model'      => $model,
		'max_tokens' => (int) $max_tokens,
		'messages'   => ! empty( $conv_msgs ) ? $conv_msgs : array(
			array(
				'role'    => 'user',
				'content' => '',
			),
		),
	);
	if ( ! empty( $system ) ) {
		$body['system'] = $system;
	}

	$response = aicb_remote_post_retry(
		'https://api.anthropic.com/v1/messages',
		array(
			'timeout' => 30,
			'headers' => array(
				'Content-Type'      => 'application/json',
				'x-api-key'         => $key,
				'anthropic-version' => '2023-06-01',
			),
			'body'    => wp_json_encode( $body ),
		)
	);
	if ( is_wp_error( $response ) ) {
		return $response;
	}
	$code = wp_remote_retrieve_response_code( $response );
	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( $code !== 200 || empty( $data['content'][0]['text'] ) ) {
		$msg = $data['error']['message'] ?? 'Anthropic API connection error.';
		return new WP_Error( 'api_error', $msg );
	}
	return array( 'answer' => $data['content'][0]['text'] );
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
	if ( empty( $endpoint ) ) {
		return new WP_Error( 'no_endpoint', 'No endpoint configured.' );
	}
	if ( ! aicb_is_valid_endpoint( $endpoint ) ) {
		return new WP_Error( 'unsafe_endpoint', 'The target endpoint is restricted or invalid.' );
	}

	$headers = array( 'Content-Type' => 'application/json' );
	if ( ! empty( $key ) ) {
		$headers['Authorization'] = 'Bearer ' . $key;
	}

	$body = array(
		'model'      => $model,
		'max_tokens' => (int) $max_tokens,
		'messages'   => $messages,
	);

	// Check tool support: query the DB directly instead of scanning the JSON catalog
	$has_tool_support = false;
	global $wpdb;
	$mtable        = $wpdb->prefix . AICB_MODEL_TABLE;
	$mtable_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $mtable ) );
	if ( $mtable_exists ) {
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT supports_tools FROM {$mtable} WHERE model_id = %s AND active = 1 LIMIT 1",
				$model
			)
		);
		if ( $row && ! empty( $row->supports_tools ) ) {
			$has_tool_support = true;
		}
	} else {
		// Fallback to old catalog scan if DB table doesn't exist yet
		$catalog = aicb_get_catalog();
		foreach ( $catalog['providers'] as $p ) {
			if ( $p['id'] === 'custom' || strpos( $endpoint, $p['id'] ) !== false || ( ! empty( $p['website'] ) && strpos( $endpoint, $p['website'] ) !== false ) ) {
				foreach ( $p['models'] ?? array() as $m ) {
					if ( $m['id'] === $model && ! empty( $m['supports_tools'] ) ) {
						$has_tool_support = true;
						break 2;
					}
				}
			}
		}
	}
	if ( aicb_opt( 'enable_calendar_tools' ) && $has_tool_support ) {
		$body['tools']       = array( aicb_get_calendar_tool_definition_openai() );
		$body['tool_choice'] = 'auto';
	}

	$response = aicb_remote_post_retry(
		$endpoint,
		array(
			'timeout' => 30,
			'headers' => $headers,
			'body'    => wp_json_encode( $body ),
		)
	);
	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code = wp_remote_retrieve_response_code( $response );
	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( $code !== 200 ) {
		$msg = $data['error']['message'] ?? 'Provider connection error.';
		return new WP_Error( 'api_error', $msg );
	}

	$message = $data['choices'][0]['message'] ?? array();
	if ( ! empty( $message['tool_calls'] ) && aicb_opt( 'enable_calendar_tools' ) ) {
		$messages[] = $message;
		foreach ( $message['tool_calls'] as $tool_call ) {
			if ( $tool_call['function']['name'] === 'check_calendar' ) {
				$args       = json_decode( $tool_call['function']['arguments'], true );
				$date_str   = $args['date'] ?? '';
				$label      = $args['label'] ?? '';
				$result     = aicb_tool_check_calendar( $date_str, $label );
				$messages[] = array(
					'role'         => 'tool',
					'tool_call_id' => $tool_call['id'],
					'content'      => wp_json_encode( $result ),
				);
			}
		}
		$body['messages']    = $messages;
		$body['tool_choice'] = 'none';

		$response2 = aicb_remote_post_retry(
			$endpoint,
			array(
				'timeout' => 30,
				'headers' => $headers,
				'body'    => wp_json_encode( $body ),
			)
		);
		if ( is_wp_error( $response2 ) ) {
			return $response2;
		}
		$code2 = wp_remote_retrieve_response_code( $response2 );
		$data2 = json_decode( wp_remote_retrieve_body( $response2 ), true );
		if ( $code2 !== 200 || empty( $data2['choices'][0]['message']['content'] ) ) {
			$msg = $data2['error']['message'] ?? 'Provider connection error.';
			return new WP_Error( 'api_error', $msg );
		}
		return array( 'answer' => $data2['choices'][0]['message']['content'] );
	}

	if ( empty( $message['content'] ) ) {
		$msg = $data['error']['message'] ?? 'Provider connection error.';
		return new WP_Error( 'api_error', $msg );
	}
	return array( 'answer' => $message['content'] );
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

	$contents = array();
	foreach ( $conv_msgs as $msg ) {
		$role       = ( $msg['role'] === 'assistant' ) ? 'model' : 'user';
		$contents[] = array(
			'role'  => $role,
			'parts' => array( array( 'text' => $msg['content'] ?? '' ) ),
		);
	}
	if ( empty( $contents ) ) {
		$contents[] = array(
			'role'  => 'user',
			'parts' => array( array( 'text' => '' ) ),
		);
	}

	$body = array(
		'contents'         => $contents,
		'generationConfig' => array( 'maxOutputTokens' => (int) $max_tokens ),
	);
	if ( ! empty( $system ) ) {
		$body['system_instruction'] = array( 'parts' => array( array( 'text' => $system ) ) );
	}

	$url      = 'https://generativelanguage.googleapis.com/v1beta/models/' . urlencode( $model ) . ':generateContent';
	$response = aicb_remote_post_retry(
		$url,
		array(
			'timeout' => 30,
			'headers' => array(
				'Content-Type'   => 'application/json',
				'x-goog-api-key' => $key,
			),
			'body'    => wp_json_encode( $body ),
		)
	);
	if ( is_wp_error( $response ) ) {
		return $response;
	}
	$code = wp_remote_retrieve_response_code( $response );
	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( $code !== 200 || empty( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
		$msg = $data['error']['message'] ?? 'Google API connection error.';
		return new WP_Error( 'api_error', $msg );
	}
	return array( 'answer' => $data['candidates'][0]['content']['parts'][0]['text'] );
}

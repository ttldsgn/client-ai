<?php
/**
 * Plugin Name:  ClientAI
 * Plugin URI:   https://totaldsgn.com
 * Description:  Floating AI chatbot supporting Anthropic, Groq, Google AI Studio, Cerebras, Mistral, and custom endpoints.
 * Version:      2.7.0
 * Author:       ttldsgn
 * License:      GPL-2.0+
 * Text Domain:  ai-chatbot
 */

defined( 'ABSPATH' ) || exit;

define( 'AICB_VERSION',   '2.7.0' );
define( 'AICB_FILE',      __FILE__ );
define( 'AICB_DIR',       plugin_dir_path( __FILE__ ) );
define( 'AICB_URL',       plugin_dir_url( __FILE__ ) );
define( 'AICB_LOG_TABLE', 'aicb_logs' );
define( 'AICB_QA_TABLE',  'aicb_custom_qa' );
define( 'AICB_MODEL_TABLE', 'aicb_models' );
define( 'AICB_LEADS_TABLE', 'aicb_leads' );

// 1. Activation & Deactivation Hooks
register_activation_hook( AICB_FILE, 'aicb_activate' );
register_deactivation_hook( AICB_FILE, 'aicb_deactivate' );

// 2. Load Core Components
require_once AICB_DIR . 'includes/cryptography.php';
require_once AICB_DIR . 'includes/database.php';
require_once AICB_DIR . 'includes/ai-adapters.php';
require_once AICB_DIR . 'includes/ajax-handlers.php';

// 3. Load Admin Components
if ( is_admin() ) {
	require_once AICB_DIR . 'admin/admin-menu.php';
}

// 4. Uninstall Hook
register_uninstall_hook( AICB_FILE, 'aicb_uninstall' );
<?php
/**
 * Plugin Name:  AI Chatbot (Modular)
 * Plugin URI:   https://example.com/ai-chatbot
 * Description:  Floating AI chatbot supporting Anthropic, Groq, Google AI Studio, Cerebras, Mistral, and custom endpoints (Modular Version).
 * Version:      2.2.8
 * Author:       Your Name
 * License:      GPL-2.0+
 * Text Domain:  ai-chatbot
 */

defined( 'ABSPATH' ) || exit;

define( 'AICB_VERSION',   '2.2.8' );
define( 'AICB_FILE',      __FILE__ );
define( 'AICB_DIR',       plugin_dir_path( __FILE__ ) );
define( 'AICB_URL',       plugin_dir_url( __FILE__ ) );
define( 'AICB_LOG_TABLE', 'aicb_logs' );
define( 'AICB_QA_TABLE',  'aicb_custom_qa' );

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
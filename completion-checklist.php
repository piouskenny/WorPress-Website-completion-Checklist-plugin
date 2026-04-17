<?php
/**
 * Plugin Name: Website QA Checklist System
 * Description: Internal tool for WordPress website quality assurance workflow.
 * Version: 1.0.0
 * Author: Adekunle Kehinde
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define constants
define( 'QA_CHECKLIST_PATH', plugin_dir_path( __FILE__ ) );
define( 'QA_CHECKLIST_URL', plugin_dir_url( __FILE__ ) );

// Load dependencies
require_once QA_CHECKLIST_PATH . 'includes/class-database.php';
require_once QA_CHECKLIST_PATH . 'includes/checklist-items.php';
require_once QA_CHECKLIST_PATH . 'includes/class-api.php';
require_once QA_CHECKLIST_PATH . 'includes/class-automation.php';
require_once QA_CHECKLIST_PATH . 'includes/class-admin.php';

/**
 * Activation Hook
 */
register_activation_hook( __FILE__, array( 'QA_Checklist_Database', 'create_tables' ) );

/**
 * Initialize Plugin
 */
function qa_checklist_init() {
	new QA_Checklist_API();
	if ( is_admin() ) {
		new QA_Checklist_Admin();
	}
}
add_action( 'plugins_loaded', 'qa_checklist_init' );

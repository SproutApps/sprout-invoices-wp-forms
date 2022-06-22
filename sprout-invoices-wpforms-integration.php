<?php if ( ! defined( 'ABSPATH' ) ) { exit; }

/*
 * Plugin Name: Sprout Invoices + WPForms
 * Plugin URI: https://sproutapps.co/sprout-invoices/integrations/
 * Description: Allows for a form submitted by WP Forms to create all necessary records to send your client an invoice or estimate.
 * Author: Sprout Apps
 * Version: 2.0
 * Author URI: https://sproutapps.co
 * Text Domain: sprout-invoices
 * Domain Path: languages
 */

/**
 * Plugin Info for updates
 */
if ( ! defined('SA_ADDON_INVOICE_SUBMISSIONS_FILE') ) {
	define( 'SA_ADDON_INVOICE_SUBMISSIONS_FILE', __FILE__ );
}
if ( ! defined('SA_ADDON_INVOICE_SUBMISSIONS_URL') ) {
	define( 'SA_ADDON_INVOICE_SUBMISSIONS_URL', plugins_url( '', __FILE__ ) );
}

// Plugin File
define( 'SI_WP_FORMS_PLUGIN_FILE', __FILE__ );

if ( ! function_exists( 'sa_load_wpforms_integration_addon' ) ) {

	// Load up after SI is loaded.
	add_action( 'sprout_invoices_loaded', 'sa_load_wpforms_integration_addon' );
	function sa_load_wpforms_integration_addon() {
		require_once( 'inc/WPForms_Controller.php' );
		require_once( 'inc/SI_WPForms.php' );
	}
}

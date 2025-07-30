<?php
/**
 * Plugin Name:     Invoice Management System
 * Plugin URI:      https://autocomputation.com/
 * Description:     Create and manage Invoices as a custom post type.
 * Version:         1.0.0
 * Author:          Sayan Paul
 * Text Domain:     invoice-management-system
 * Domain Path:     /languages
 */

if ( ! defined( 'WPINC' ) ) {
  die;
}

// Define plugin constants
define( 'IMS_PATH', plugin_dir_path( __FILE__ ) );
define( 'IMS_URL',  plugin_dir_url(  __FILE__ ) );
define( 'IMS_VERSION', '1.0.0' );

// Autoload or require your classes
require_once IMS_PATH . 'includes/class-ims-cpt.php';
require_once IMS_PATH . 'includes/class-ims-taxonomies.php';
require_once IMS_PATH . 'includes/class-ims-metaboxes.php';
require_once IMS_PATH . 'includes/class-ims-n8n.php';
require_once IMS_PATH . 'includes/class-ims-helpers.php';
require_once IMS_PATH . 'admin/class-ims-admin.php';
require_once IMS_PATH . 'public/class-ims-public.php';

/**
 * Kick things off
 */
function ims_run() {
  // CPT
  \IMS\CPT::instance()->register();
  // Taxonomies
  \IMS\Taxonomies::instance()->register();
  // Metaboxes
  \IMS\Metaboxes::instance()->init();
  // n8n integration
  \IMS\N8n::instance()->init();
  
  if ( is_admin() ) {
    // \IMS\Admin::instance()->init();
  } else {
    // \IMS\Public_Display::instance()->init();
  }
  
  // Load textdomain
  load_plugin_textdomain( 'invoice-management-system', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'ims_run' );

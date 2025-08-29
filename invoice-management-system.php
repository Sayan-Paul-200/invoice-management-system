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

// --- safe includes with output capture (prevents unexpected activation output) ---
$includes = [
    'includes/class-ims-cpt.php',
    // 'includes/class-ims-taxonomies.php',
    'includes/class-ims-metaboxes.php',
    'includes/class-ims-n8n.php',
    'includes/class-ims-shortcodes.php',
    // 'includes/class-ims-elementor-handler.php',
    // 'includes/class-ims-helpers.php',
];

// Start buffering so that any unexpected echo/notice doesn't reach browser
ob_start();

foreach ( $includes as $rel ) {
    $path = IMS_PATH . $rel;
    if ( file_exists( $path ) ) {
        include_once $path;
    } else {
        error_log( "IMS: missing include {$path}" );
    }
}

// Include admin/public classes separately (optional)
$admin_files = [
    'admin/class-ims-admin.php',
    // 'admin/class-ims-dashboard.php',
    'public/class-ims-public.php',
];

foreach ( $admin_files as $rel ) {
    $path = IMS_PATH . $rel;
    if ( file_exists( $path ) ) {
        include_once $path;
    } else {
        // not fatal; log missing pieces
        error_log( "IMS: optional include missing: {$path}" );
    }
}

$captured = ob_get_clean();
if ( $captured !== '' ) {
    // Log up to first 2000 chars so your debug.log contains the problem
    error_log( 'IMS: unexpected output during include stage (truncated): ' . substr( $captured, 0, 2000 ) );
    // OPTIONAL: you can store full buffer in file for deeper inspection (careful with huge output)
    // file_put_contents( IMS_PATH . 'unexpected_output.log', $captured );
}


/**
 * Runs on plugin activation: register CPT & taxonomies, then flush rewrite rules.
 */
function ims_activate() {
    ob_start();
    // register CPT & taxonomies
    if ( class_exists( '\IMS\CPT' ) ) {
        \IMS\CPT::instance()->register();
    }
    if ( class_exists( '\IMS\Taxonomies' ) ) {
        \IMS\Taxonomies::instance()->register();
    }

    do_action( 'init' );
    flush_rewrite_rules();
    $buf = ob_get_clean();
    if ( $buf !== '' ) {
        error_log( 'IMS activation unexpected output (truncated): ' . substr( $buf, 0, 2000 ) );
    }
}
register_activation_hook( __FILE__, 'ims_activate' );



/**
 * Bootstrap the plugin.
 */
function ims_run() {
    if ( class_exists( '\IMS\CPT' ) ) {
        \IMS\CPT::instance()->register();
    }
    if ( class_exists( '\IMS\Taxonomies' ) ) {
        \IMS\Taxonomies::instance()->register();
    }
    if ( class_exists( '\IMS\Metaboxes' ) ) {
        \IMS\Metaboxes::instance()->init();
    }
    if ( class_exists( '\IMS\N8n' ) ) {
        \IMS\N8n::instance()->init();
    }
    if ( class_exists( '\IMS\Helpers' ) ) {
        \IMS\Helpers::instance()->init();
    }

    if ( is_admin() ) {
        if ( class_exists( '\IMS\Admin' ) ) {
            \IMS\Admin::instance()->init();
        }
        if ( class_exists( '\IMS\AdminDashboard' ) ) {
            \IMS\AdminDashboard::instance()->init();
        }
    } else {
        if ( class_exists( '\IMS\Shortcodes' ) ) {
            \IMS\Shortcodes::instance()->init();
        }
        if ( class_exists( '\IMS\PublicDisplay' ) ) {
            \IMS\PublicDisplay::instance()->init();
        }
    }

    load_plugin_textdomain( 'invoice-management-system', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'ims_run' );

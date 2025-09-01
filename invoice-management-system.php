<?php
/**
 * Plugin Name:     Invoice Management System
 * Plugin URI:      https://autocomputation.com/
 * Description:     Create and manage Invoices as a custom post type.
 * Version:         2.0.0
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
    'includes/class-ims-taxonomies.php',
    'includes/class-ims-metaboxes.php',
    'includes/class-ims-n8n.php',
    'includes/class-ims-shortcodes.php',
    'includes/class-ims-elementor-handler.php',
    'includes/class-ims-helpers.php',
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
    'admin/class-ims-dashboard.php',
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
 * Runs on plugin activation:
 *
 * IMPORTANT: Do NOT call do_action('init') here. Manually firing 'init' can cause other plugins (Elementor, etc.)
 * to run their init code in an activation context — which may lead to classes being included/declared twice.
 *
 * We'll register our CPTs directly and ask WP to flush rewrite rules on the next normal init.
 */
function ims_activate() {
    // Register CPT & taxonomies directly (these methods should only register the post type/taxonomies)
    if ( class_exists( '\IMS\CPT' ) ) {
        \IMS\CPT::instance()->register();
    }
    if ( class_exists( '\IMS\Taxonomies' ) ) {
        \IMS\Taxonomies::instance()->register();
    }

    // Set a transient/option so we can flush rewrite rules safely on the next normal init.
    // (Avoids firing core hooks during activation.)
    update_option( 'ims_flush_rewrite_rules', 1 );
}
register_activation_hook( __FILE__, 'ims_activate' );

/**
 * On normal init, flush rewrite rules if activation asked us to.
 */
function ims_maybe_flush_rewrite_rules() {
    if ( get_option( 'ims_flush_rewrite_rules' ) ) {
        // Flush rewrite rules once
        flush_rewrite_rules();
        delete_option( 'ims_flush_rewrite_rules' );
    }
}
add_action( 'init', 'ims_maybe_flush_rewrite_rules', 20 );


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

error_log( 'IMS: plugin bootstrap loaded' );

add_action( 'admin_post_ims_submit_invoice', function() {
    error_log( 'IMS: admin_post_ims_submit_invoice fired (logged from test hook)' );
}, 1 );
add_action( 'admin_post_nopriv_ims_submit_invoice', function() {
    error_log( 'IMS: admin_post_nopriv_ims_submit_invoice fired (logged from test hook)' );
}, 1 );

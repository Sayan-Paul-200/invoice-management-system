<?php
namespace IMS;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class CPT {
    /**
     * Singleton instance
     *
     * @var CPT|null
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return CPT
     */
    public static function instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Hook into WordPress
     */
    public function register() {
        add_action( 'init', [ $this, 'register_invoice_cpt' ], 0 );
    }

    /**
     * Register the "Invoice" custom post type
     */
    public function register_invoice_cpt() {
        $labels = [
            'name'                  => _x( 'Invoices', 'Post Type General Name', 'invoice-management-system' ),
            'singular_name'         => _x( 'Invoice ID', 'Post Type Singular Name', 'invoice-management-system' ),
            'menu_name'             => __( 'Invoices', 'invoice-management-system' ),
            'name_admin_bar'        => __( 'Invoice ID', 'invoice-management-system' ),
            'archives'              => __( 'Invoice Archives', 'invoice-management-system' ),
            'attributes'            => __( 'Invoice Attributes', 'invoice-management-system' ),
            'parent_item_colon'     => __( 'Parent Invoice:', 'invoice-management-system' ),
            'all_items'             => __( 'All Invoices', 'invoice-management-system' ),
            'add_new_item'          => __( 'Add New Invoice', 'invoice-management-system' ),
            'add_new'               => __( 'Add New', 'invoice-management-system' ),
            'new_item'              => __( 'New Invoice', 'invoice-management-system' ),
            'edit_item'             => __( 'Edit Invoice', 'invoice-management-system' ),
            'update_item'           => __( 'Update Invoice', 'invoice-management-system' ),
            'view_item'             => __( 'View Invoice', 'invoice-management-system' ),
            'view_items'            => __( 'View Invoices', 'invoice-management-system' ),
            'search_items'          => __( 'Search Invoice', 'invoice-management-system' ),
            'not_found'             => __( 'Not found', 'invoice-management-system' ),
            'not_found_in_trash'    => __( 'Not found in Trash', 'invoice-management-system' ),
            'featured_image'        => __( 'Featured Image', 'invoice-management-system' ),
            'set_featured_image'    => __( 'Set featured image', 'invoice-management-system' ),
            'remove_featured_image' => __( 'Remove featured image', 'invoice-management-system' ),
            'use_featured_image'    => __( 'Use as featured image', 'invoice-management-system' ),
            'insert_into_item'      => __( 'Insert into invoice', 'invoice-management-system' ),
            'uploaded_to_this_item' => __( 'Uploaded to this invoice', 'invoice-management-system' ),
            'items_list'            => __( 'Invoices list', 'invoice-management-system' ),
            'items_list_navigation' => __( 'Invoices list navigation', 'invoice-management-system' ),
            'filter_items_list'     => __( 'Filter invoices list', 'invoice-management-system' ),
        ];

        $args = [
            'label'                 => __( 'Invoice', 'invoice-management-system' ),
            'description'           => __( 'Manage your invoices', 'invoice-management-system' ),
            'labels'                => $labels,
            'supports'              => [ 'title', 'editor' ],
            'taxonomies'            => [ /* e.g. 'invoice-status', 'invoice-client' */ ],
            'rewrite'               => array( 'slug' => 'ac-invoices'),
            'hierarchical'          => false,
            'public'                => true,
            'show_ui'               => true,
            'show_in_menu'          => true,
            'menu_position'         => 5,
            'menu_icon'             => 'dashicons-media-spreadsheet',
            'show_in_admin_bar'     => true,
            'show_in_nav_menus'     => true,
            'can_export'            => true,
            'has_archive'           => true,
            'exclude_from_search'   => false,
            'publicly_queryable'    => true,
            'capability_type'       => 'post',
        ];

        register_post_type( 'ac_invoice', $args );
    }
}

// Initialize registration
CPT::instance()->register();

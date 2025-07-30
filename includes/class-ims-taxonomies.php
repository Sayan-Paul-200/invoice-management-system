<?php
namespace IMS;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class Taxonomies {
    /**
     * Singleton instance
     *
     * @var Taxonomies|null
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return Taxonomies
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
        add_action( 'init', [ $this, 'register_taxonomies' ], 0 );
    }

    /**
     * Register custom taxonomies for Invoices
     */
    public function register_taxonomies() {
        // Project (hierarchical, category-like)
        $labels_project = [
            'name'              => _x( 'Projects', 'taxonomy general name', 'invoice-management-system' ),
            'singular_name'     => _x( 'Project', 'taxonomy singular name', 'invoice-management-system' ),
            'search_items'      => __( 'Search Projects', 'invoice-management-system' ),
            'all_items'         => __( 'All Projects', 'invoice-management-system' ),
            'parent_item'       => __( 'Parent Project', 'invoice-management-system' ),
            'parent_item_colon' => __( 'Parent Project:', 'invoice-management-system' ),
            'edit_item'         => __( 'Edit Project', 'invoice-management-system' ),
            'update_item'       => __( 'Update Project', 'invoice-management-system' ),
            'add_new_item'      => __( 'Add New Project', 'invoice-management-system' ),
            'new_item_name'     => __( 'New Project Name', 'invoice-management-system' ),
            'menu_name'         => __( 'Projects', 'invoice-management-system' ),
        ];
        $args_project = [
            'labels'            => $labels_project,
            'hierarchical'      => true,
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => true,
            'rewrite'           => [ 'slug' => 'ac_projects' ],
        ];
        register_taxonomy( 'project', [ 'invoice' ], $args_project );

        // Location (hierarchical, category-like)
        $labels_location = [
            'name'              => _x( 'Locations', 'taxonomy general name', 'invoice-management-system' ),
            'singular_name'     => _x( 'Location', 'taxonomy singular name', 'invoice-management-system' ),
            'search_items'      => __( 'Search Locations', 'invoice-management-system' ),
            'all_items'         => __( 'All Locations', 'invoice-management-system' ),
            'parent_item'       => __( 'Parent Location', 'invoice-management-system' ),
            'parent_item_colon' => __( 'Parent Location:', 'invoice-management-system' ),
            'edit_item'         => __( 'Edit Location', 'invoice-management-system' ),
            'update_item'       => __( 'Update Location', 'invoice-management-system' ),
            'add_new_item'      => __( 'Add New Location', 'invoice-management-system' ),
            'new_item_name'     => __( 'New Location Name', 'invoice-management-system' ),
            'menu_name'         => __( 'Locations', 'invoice-management-system' ),
        ];
        $args_location = [
            'labels'            => $labels_location,
            'hierarchical'      => true,
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => true,
            'rewrite'           => [ 'slug' => 'ac_location' ],
        ];
        register_taxonomy( 'location', [ 'invoice' ], $args_location );
    }
}

// Initialize registration
Taxonomies::instance()->register();

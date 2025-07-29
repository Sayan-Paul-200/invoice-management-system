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
        // Project (non-hierarchical)
        $labels_project = [
            'name'                       => _x( 'Projects', 'Taxonomy General Name', 'invoice-management-system' ),
            'singular_name'              => _x( 'Project', 'Taxonomy Singular Name', 'invoice-management-system' ),
            'menu_name'                  => __( 'Projects', 'invoice-management-system' ),
            'all_items'                  => __( 'All Projects', 'invoice-management-system' ),
            'edit_item'                  => __( 'Edit Project', 'invoice-management-system' ),
            'update_item'                => __( 'Update Project', 'invoice-management-system' ),
            'add_new_item'               => __( 'Add New Project', 'invoice-management-system' ),
            'new_item_name'              => __( 'New Project Name', 'invoice-management-system' ),
            'search_items'               => __( 'Search Projects', 'invoice-management-system' ),
            'popular_items'              => __( 'Popular Projects', 'invoice-management-system' ),
            'separate_items_with_commas' => __( 'Separate projects with commas', 'invoice-management-system' ),
            'add_or_remove_items'        => __( 'Add or remove projects', 'invoice-management-system' ),
            'choose_from_most_used'      => __( 'Choose from the most used', 'invoice-management-system' ),
            'not_found'                  => __( 'No projects found', 'invoice-management-system' ),
        ];
        $args_project = [
            'labels'            => $labels_project,
            'hierarchical'      => false,
            'public'            => true,
            'show_ui'           => true,
            'show_in_nav_menus' => true,
            'show_admin_column' => true,
            'update_count_callback' => '_update_post_term_count',
            'rewrite'           => [ 'slug' => 'project' ],
        ];
        register_taxonomy( 'project', [ 'invoice' ], $args_project );

        // Location (non-hierarchical)
        $labels_location = [
            'name'                       => _x( 'Locations', 'Taxonomy General Name', 'invoice-management-system' ),
            'singular_name'              => _x( 'Location', 'Taxonomy Singular Name', 'invoice-management-system' ),
            'menu_name'                  => __( 'Locations', 'invoice-management-system' ),
            'all_items'                  => __( 'All Locations', 'invoice-management-system' ),
            'edit_item'                  => __( 'Edit Location', 'invoice-management-system' ),
            'update_item'                => __( 'Update Location', 'invoice-management-system' ),
            'add_new_item'               => __( 'Add New Location', 'invoice-management-system' ),
            'new_item_name'              => __( 'New Location Name', 'invoice-management-system' ),
            'search_items'               => __( 'Search Locations', 'invoice-management-system' ),
            'popular_items'              => __( 'Popular Locations', 'invoice-management-system' ),
            'separate_items_with_commas' => __( 'Separate locations with commas', 'invoice-management-system' ),
            'add_or_remove_items'        => __( 'Add or remove locations', 'invoice-management-system' ),
            'choose_from_most_used'      => __( 'Choose from the most used', 'invoice-management-system' ),
            'not_found'                  => __( 'No locations found', 'invoice-management-system' ),
        ];
        $args_location = [
            'labels'            => $labels_location,
            'hierarchical'      => false,
            'public'            => true,
            'show_ui'           => true,
            'show_in_nav_menus' => true,
            'show_admin_column' => true,
            'update_count_callback' => '_update_post_term_count',
            'rewrite'           => [ 'slug' => 'location' ],
        ];
        register_taxonomy( 'location', [ 'invoice' ], $args_location );
    }
}

// Initialize registration
Taxonomies::instance()->register();

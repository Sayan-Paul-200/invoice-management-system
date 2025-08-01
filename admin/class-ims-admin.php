<?php
namespace IMS;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Admin-specific functionality for Invoice Management System plugin.
 */
class Admin {
    /** Singleton instance */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return Admin
     */
    public static function instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Hook into WordPress admin.
     */
    public function init() {
        // Enqueue admin assets
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

        // Customize Invoices list table
        add_filter( 'manage_invoice_posts_columns', [ $this, 'add_status_column' ] );
        add_action( 'manage_invoice_posts_custom_column', [ $this, 'render_status_column' ], 10, 2 );

        // Add status filters below the page title
        add_filter( 'views_edit-invoice', [ $this, 'add_status_views' ] );
        // Apply status filter to query
        add_action( 'pre_get_posts', [ $this, 'filter_by_status' ] );
    }

    /**
     * Enqueue CSS/JS for invoice admin screens.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_admin_assets( $hook ) {
        global $post_type;

        // Only load on Invoice edit or list screens
        if ( 
            $post_type === 'invoice' && in_array( $hook, [ 'edit.php', 'post.php', 'post-new.php' ], true )
        ) {
            // Styles
            wp_enqueue_style(
                'ims-admin-css',
                IMS_URL . 'admin/css/ims-admin.css',
                [],
                IMS_VERSION
            );
            // Scripts
            wp_enqueue_script(
                'ims-admin-js',
                IMS_URL . 'admin/js/ims-admin.js',
                [ 'jquery' ],
                IMS_VERSION,
                true
            );
            wp_localize_script( 'ims-admin-js', 'imsAjax', [ 'ajax_url' => admin_url( 'admin-ajax.php' ) ] );
        }
    }

    /**
     * Add custom columns: Invoice Status and Author
     *
     * @param array $columns Existing columns
     * @return array Modified columns
     */
    public function add_status_column( $columns ) {
        $new_columns = [];
        foreach ( $columns as $key => $label ) {
            $new_columns[ $key ] = $label;
            if ( 'title' === $key ) {
                // After title, add status
                $new_columns['invoice_status'] = __( 'Invoice Status', 'invoice-management-system' );
                // Then add author column
                $new_columns['invoice_author'] = __( 'Author', 'invoice-management-system' );
            }
        }
        return $new_columns;
    }

    /**
     * Render content for our custom column.
     *
     * @param string $column  Column key
     * @param int    $post_id Post ID
     */
    public function render_status_column( $column, $post_id ) {
        switch ( $column ) {
            case 'invoice_status':
                $status = get_post_meta( $post_id, '_invoice_status', true );
                echo $status ? esc_html( ucfirst( sanitize_text_field( $status ) ) ) : '&mdash;';
                break;

            case 'invoice_author':
                $author_id = get_post_field( 'post_author', $post_id );
                $author_name = $author_id ? get_the_author_meta( 'display_name', $author_id ) : '';
                echo $author_name ? esc_html( $author_name ) : '&mdash;';
                break;
        }
    }

    /**
     * Add links to filter by invoice status in the subsubsub section.
     *
     * @param array $views Existing view links
     * @return array Modified view links
     */
    public function add_status_views( $views ) {
        $base_url = admin_url( 'edit.php?post_type=invoice' );
        $statuses = [
            'all'     => __( 'All', 'invoice-management-system' ),
            'pending' => __( 'Pending', 'invoice-management-system' ),
            'paid'    => __( 'Paid', 'invoice-management-system' ),
            'cancel'  => __( 'Cancel', 'invoice-management-system' ),
        ];

        // Count items per status
        $counts = [];
        foreach ( $statuses as $key => $label ) {
            if ( $key === 'all' ) {
                $counts[$key] = wp_count_posts( 'invoice' )->publish;
            } else {
                $q = new \WP_Query([
                    'post_type'      => 'invoice',
                    'post_status'    => 'publish',
                    'meta_key'       => '_invoice_status',
                    'meta_value'     => $key,
                    'fields'         => 'ids',
                    'posts_per_page' => 1,
                ]);
                $counts[$key] = $q->found_posts;
            }
        }

        $new_views = [];
        foreach ( $statuses as $key => $label ) {
            $count = isset( $counts[$key] ) ? $counts[$key] : 0;
            $url = $base_url;
            if ( $key !== 'all' ) {
                $url = add_query_arg( ['meta_key' => '_invoice_status', 'meta_value' => $key], $url );
            }
            $class = ( isset( $_GET['meta_value'] ) && $_GET['meta_value'] === $key ) || ( $key === 'all' && ! isset( $_GET['meta_value'] ) )
                ? 'current' : '';
            $new_views[ $key ] = sprintf(
                '<a href="%1$s" class="%2$s">%3$s <span class="count">(%4$d)</span></a>',
                esc_url( $url ),
                $class,
                esc_html( $label ),
                intval( $count )
            );
        }

        return $new_views;
    }

    /**
     * Apply the invoice status filter to the main admin query.
     *
     * @param \WP_Query $query
     */
    public function filter_by_status( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() ) {
            return;
        }
        if ( isset( $_GET['post_type'], $_GET['meta_key'], $_GET['meta_value'] )
            && $_GET['post_type'] === 'invoice'
            && $_GET['meta_key'] === '_invoice_status'
        ) {
            $query->set( 'meta_key',   sanitize_text_field( wp_unslash( $_GET['meta_key'] ) ) );
            $query->set( 'meta_value', sanitize_text_field( wp_unslash( $_GET['meta_value'] ) ) );
        }
    }
}

// Initialize admin hooks
Admin::instance()->init();

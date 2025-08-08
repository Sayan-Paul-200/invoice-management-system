<?php
namespace IMS;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Helper functions and hooks for the Invoice Management System plugin.
 */
class Helpers {
    /** Singleton instance */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return Helpers
     */
    public static function instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize hooks
     */
    public function init() {
        // 1) Early validation to block the publish flow
        // add_filter( 'wp_insert_post_data', [ $this, 'validate_invoice_before_save' ], 1, 2 );

        // 2) Post‑save cleanup: delete any invalid invoices before they stick
        // add_action( 'save_post_ac_invoice', [ $this, 'cleanup_invalid_invoice' ], 1, 2 );
    }

    

    /**
     * After WP saves the post record, double‑check validity.
     * If invalid, delete it outright (so it never appears even as a draft).
     *
     * @param int     $post_id
     * @param WP_Post $post
     */
    public function cleanup_invalid_invoice( $post_id, $post ) {
        // Only on actual publish attempts
        if ( $post->post_status !== 'publish' ) {
            return;
        }

        // Re‑validate dates
        $inv = isset( $_POST['_invoice_date'] ) ? sanitize_text_field( $_POST['_invoice_date'] ) : '';
        $sub = isset( $_POST['_submission_date'] ) ? sanitize_text_field( $_POST['_submission_date'] ) : '';
        if ( empty( $inv ) || empty( $sub ) || strtotime( $inv ) >= strtotime( $sub ) ) {
            wp_delete_post( $post_id, true );
            wp_die( esc_html__( 'Invoice Date must be before Submission Date.', 'invoice-management-system' ), '', [ 'back_link' => true ] );
        }

        // Re‑validate file upload
        $existing = get_post_meta( $post_id, '_invoice_file_id', true );
        $new_file = isset( $_FILES['_invoice_file'] ) && ! empty( $_FILES['_invoice_file']['name'] );
        if ( ! $existing && ! $new_file ) {
            wp_delete_post( $post_id, true );
            wp_die( esc_html__( 'Invoice file upload is required.', 'invoice-management-system' ), '', [ 'back_link' => true ] );
        }

        // Re‑validate MIME type of any new file
        if ( $new_file ) {
            $allowed = [ 'application/pdf', 'image/jpeg' ];
            $filetype = wp_check_filetype_and_ext(
                $_FILES['_invoice_file']['tmp_name'],
                $_FILES['_invoice_file']['name']
            );
            if ( empty( $filetype['type'] ) || ! in_array( $filetype['type'], $allowed, true ) ) {
                wp_delete_post( $post_id, true );
                wp_die( esc_html__( 'Only PDF or JPEG files are allowed.', 'invoice-management-system' ), '', [ 'back_link' => true ] );
            }
        }

    }
}

// Initialize Helpers
Helpers::instance()->init();

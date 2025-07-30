<?php
namespace IMS;

if ( ! defined( 'ABSPATH' ) ) exit;

class N8n {
    private static $instance = null;

    public static function instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init() {
        // Run *after* metaboxes save (priority 20 > 10)
        add_action( 'save_post_invoice', [ $this, 'trigger_webhook' ], 20, 2 );
    }

    public function trigger_webhook( $post_ID, $post ) {
        // Only on publish
        if ( $post->post_status !== 'publish' ) {
            return;
        }
        
        // Gather meta
        $meta = [
            'invoice_date'    => get_post_meta( $post_ID, '_invoice_date', true ),
            'submission_date' => get_post_meta( $post_ID, '_submission_date', true ),
            'amount'          => get_post_meta( $post_ID, '_invoice_amount', true ),
            'file_id'         => get_post_meta( $post_ID, '_invoice_file_id', true ),
            'status'          => get_post_meta( $post_ID, '_invoice_status', true ) ?: 'pending',
            'payment_date'    => get_post_meta( $post_ID, '_payment_date', true ),
            'to'              => get_post_meta( $post_ID, '_to_email', true ),
            'cc'              => get_post_meta( $post_ID, '_cc_emails', true ) ?: [],
        ];

        // Taxonomies
        $projects  = wp_get_post_terms( $post_ID, 'project',  [ 'fields' => 'names' ] );
        $locations = wp_get_post_terms( $post_ID, 'location', [ 'fields' => 'names' ] );

        // Build query args (serialize arrays to CSV)
        $args = [
            'title'           => $post->post_title,
            'projects'        => implode( ',', $projects ),
            'locations'       => implode( ',', $locations ),
            'invoice_date'    => $meta['invoice_date'],
            'submission_date' => $meta['submission_date'],
            'amount'          => $meta['amount'],
            'file_id'         => $meta['file_id'],
            'status'          => $meta['status'],
            'payment_date'    => $meta['payment_date'],
            'to'              => $meta['to'],
            'cc'              => implode( ', ', (array) $meta['cc'] ),
        ];

        // Fire the GET request
        $url = add_query_arg( $args,
            'https://n8n.srv917960.hstgr.cloud/webhook-test/2acc3471-0fdd-45d6-b5ba-68971f99fe99'
        );

        wp_remote_get( esc_url_raw( $url ), [ 'timeout' => 2 ] );
    }
}

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
        add_action( 'publish_invoice', [ $this, 'trigger_webhook' ], 10, 2 );
    }

    public function trigger_webhook( $post_ID, $post ) {
        // gather meta
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

        // taxonomies
        $projects  = wp_get_post_terms( $post_ID, 'project',  [ 'fields' => 'names' ] );
        $locations = wp_get_post_terms( $post_ID, 'location', [ 'fields' => 'names' ] );

        $args = [
            'title'     => $post->post_title,
            'projects'  => implode( ',', $projects ),
            'locations' => implode( ',', $locations ),
        ] + $meta;

        $url = add_query_arg( $args,
            'https://n8n.srv917960.hstgr.cloud/webhook-test/2acc3471-0fdd-45d6-b5ba-68971f99fe99'
        );

        // fire & forget
        wp_remote_get( esc_url_raw( $url ), [ 'timeout' => 2 ] );
    }
}

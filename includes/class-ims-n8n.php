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
        // After meta save (priority 20 > metabox priority 10)
        add_action( 'save_post_invoice', [ $this, 'maybe_trigger_webhook' ], 20, 2 );
    }

    /**
     * On every invoice save, decide whether it's the first publish or an update.
     */
    public function maybe_trigger_webhook( $post_ID, $post ) {
        // Only for published invoices
        if ( $post->post_status !== 'publish' ) {
            return;
        }

        // Check our custom flag
        $has_been_published = get_post_meta( $post_ID, '_ims_has_been_published', true );

        if ( ! $has_been_published ) {
            // First-time publish
            $this->send_webhook(
                $post_ID,
                $post,
                'https://n8n.srv917960.hstgr.cloud/webhook/2acc3471-0fdd-45d6-b5ba-68971f99fe99'
            );
            // Mark it so we don’t treat subsequent saves as first-publish
            update_post_meta( $post_ID, '_ims_has_been_published', '1' );
        } else {
            // Subsequent updates
            $this->send_webhook(
                $post_ID,
                $post,
                'https://n8n.srv917960.hstgr.cloud/webhook/f40d816c-014f-4b8c-9fbb-3543a436bebb'
            );
        }
    }

    /**
     * Common routine to gather all invoice data and fire the webhook.
     */
    private function send_webhook( $post_ID, $post, $url_base ) {
        // Gather meta
        $meta = [
            'invoice_date'    => get_post_meta( $post_ID, '_invoice_date', true ),
            'submission_date' => get_post_meta( $post_ID, '_submission_date', true ),
            'amount'          => get_post_meta( $post_ID, '_invoice_amount', true ),
            'file_url'        => get_post_meta( $post_ID, '_invoice_file_url', true ),
            'status'          => get_post_meta( $post_ID, '_invoice_status', true ) ?: 'pending',
            'payment_date'    => get_post_meta( $post_ID, '_payment_date', true ),
            'to'              => get_post_meta( $post_ID, '_to_email', true ),
            'cc'              => get_post_meta( $post_ID, '_cc_emails', true ) ?: [],
        ];

        // Taxonomies
        $projects  = wp_get_post_terms( $post_ID, 'project',  [ 'fields' => 'names' ] );
        $locations = wp_get_post_terms( $post_ID, 'location', [ 'fields' => 'names' ] );

        // Build args, flatten arrays into CSV
        $args = [
            'title'           => $post->post_title,
            'projects'        => implode( ',', $projects ),
            'locations'       => implode( ',', $locations ),
            'invoice_date'    => $meta['invoice_date'],
            'submission_date' => $meta['submission_date'],
            'amount'          => $meta['amount'],
            'file_url'        => $meta['file_url'],
            'status'          => $meta['status'],
            'payment_date'    => $meta['payment_date'],
            'to'              => $meta['to'],
            'cc'              => implode( ', ', (array) $meta['cc'] ),
        ];

        $final_url = add_query_arg( $args, $url_base );
        wp_remote_get( esc_url_raw( $final_url ), [ 'timeout' => 2 ] );
    }
}

N8n::instance()->init();

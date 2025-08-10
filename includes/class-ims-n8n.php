<?php
namespace IMS;

if ( ! defined( 'ABSPATH' ) ) exit;

class N8n {
    private static $instance = null;

    /** queue of pending webhooks to run on shutdown */
    private $queue = [];

    public static function instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init() {
        // run after metabox save (metabox priority = 10). We still accept $update arg.
        add_action( 'save_post_ac_invoice', [ $this, 'maybe_trigger_webhook' ], 20, 3 );

        // Process queued webhooks at the very end of the request.
        add_action( 'shutdown', [ $this, 'process_shutdown_webhooks' ], 0 );
    }

    /**
     * Called during save_post_ac_invoice (after metabox saving).
     *
     * We queue the webhook and let process_shutdown_webhooks() perform the send.
     *
     * @param int     $post_ID
     * @param WP_Post $post
     * @param bool    $update
     */
    public function maybe_trigger_webhook( $post_ID, $post, $update ) {
        // Only for published invoices
        $status = get_post_status( $post_ID );
        if ( $status !== 'publish' ) {
            return;
        }

        $has_been_published = get_post_meta( $post_ID, '_ims_has_been_published', true );

        if ( ! $has_been_published ) {
            // First-time publish -> publish webhook
            $url = 'https://n8n.srv917960.hstgr.cloud/webhook/2acc3471-0fdd-45d6-b5ba-68971f99fe99';

            // Mark it so subsequent saves are updates (do this early)
            update_post_meta( $post_ID, '_ims_has_been_published', '1' );
        } else {
            // Update webhook
            $url = 'https://n8n.srv917960.hstgr.cloud/webhook-test/f40d816c-014f-4b8c-9fbb-3543a436bebb';
        }

        // Add to queue; actual sending will happen in shutdown handler
        $this->queue[] = [
            'post_id'  => (int) $post_ID,
            'url_base' => $url,
        ];
    }

    /**
     * Process queued webhooks on shutdown.
     * This runs after all save_post callbacks have finished for the request,
     * so postmeta should be available.
     */
    public function process_shutdown_webhooks() {
        if ( empty( $this->queue ) ) {
            return;
        }

        foreach ( $this->queue as $item ) {
            $post_ID  = isset( $item['post_id'] ) ? (int) $item['post_id'] : 0;
            $url_base = isset( $item['url_base'] ) ? $item['url_base'] : '';

            if ( ! $post_ID || ! $url_base ) {
                continue;
            }

            // send (non-blocking)
            $this->send_webhook( $post_ID, $url_base );
        }

        // clear queue
        $this->queue = [];
    }

    /**
     * Build args from post meta and fire the final GET.
     *
     * @param int    $post_ID
     * @param string $url_base
     */
    private function send_webhook( $post_ID, $url_base ) {
        $post = get_post( $post_ID );
        if ( ! $post ) {
            return;
        }

        // get fresh meta — should be present now
        $meta = [
            'invoice_date'    => get_post_meta( $post_ID, '_invoice_date', true ),
            'submission_date' => get_post_meta( $post_ID, '_submission_date', true ),
            'amount'          => get_post_meta( $post_ID, '_invoice_amount', true ),
            'file_url'        => get_post_meta( $post_ID, '_invoice_file_url', true ),
            'status'          => get_post_meta( $post_ID, '_invoice_status', true ) ?: 'pending',
            'payment_date'    => get_post_meta( $post_ID, '_payment_date', true ),
            'to'              => get_post_meta( $post_ID, '_to_email', true ),
            'cc'              => get_post_meta( $post_ID, '_cc_emails', true ) ?: [],
            'project'         => get_post_meta( $post_ID, '_ims_project', true ),
            'location'        => get_post_meta( $post_ID, '_ims_location', true ),
        ];

        // DEBUG: uncomment temporarily if you want to inspect meta in debug.log
        error_log( 'N8N webhook meta for post ' . $post_ID . ': ' . wp_json_encode( $meta ) );

        // Build args (let add_query_arg handle encoding)
        $args = [
            'title'           => $post->post_title,
            'project'         => (string) $meta['project'],
            'location'        => (string) $meta['location'],
            'invoice_date'    => (string) $meta['invoice_date'],
            'submission_date' => (string) $meta['submission_date'],
            'amount'          => (string) $meta['amount'],
            'file_url'        => (string) $meta['file_url'],
            'status'          => (string) $meta['status'],
            'payment_date'    => (string) $meta['payment_date'],
            'to'              => (string) $meta['to'],
            'cc'              => implode( ', ', (array) $meta['cc'] ),
        ];

        $final_url = add_query_arg( $args, $url_base );

        // Non-blocking remote call
        wp_remote_get( esc_url_raw( $final_url ), [
            'timeout'  => 2,
            'blocking' => false,
        ] );
    }
}

N8n::instance()->init();

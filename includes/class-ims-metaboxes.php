<?php
namespace IMS;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Metaboxes {
    /** @var Metaboxes|null */
    private static $instance = null;

    public static function instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init() {
        add_action( 'add_meta_boxes', [ $this, 'register_metaboxes' ] );
        add_action( 'save_post_invoice',   [ $this, 'save_metaboxes' ], 10, 2 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_toggle_payment_date', [ $this, 'toggle_payment_date_callback' ] );
        add_action( 'post_edit_form_tag', [ $this, 'add_form_enctype' ] );
    }

    public function add_form_enctype() {
        // This will modify the <form> tag for the edit screen to allow file uploads
        echo ' enctype="multipart/form-data"';
    }

    public function register_metaboxes() {
        add_meta_box(
            'ims_invoice_details',
            __( 'Invoice Details', 'invoice-management-system' ),
            [ $this, 'render_invoice_details' ],
            'invoice',
            'normal',
            'high'
        );

        add_meta_box(
            'ims_invoice_status',
            __( 'Invoice Status', 'invoice-management-system' ),
            [ $this, 'render_invoice_status' ],
            'invoice',
            'normal',
            'high'
        );

        add_meta_box(
            'ims_email_receivers',
            __( 'Email Receivers', 'invoice-management-system' ),
            [ $this, 'render_email_receivers' ],
            'invoice',
            'normal',
            'high'
        );
    }

    public function enqueue_assets( $hook ) {
        global $post;
        if ( $hook === 'post-new.php' || $hook === 'post.php' ) {
            if ( isset( $post->post_type ) && $post->post_type === 'invoice' ) {
                // jQuery UI datepicker
                // wp_enqueue_script( 'jquery-ui-datepicker' );
                // wp_enqueue_style( 'jquery-ui-css', '//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css' );
                // Custom admin script
                wp_enqueue_script( 'ims-admin-js', IMS_URL . 'admin/js/ims-admin.js', [ 'jquery' ], IMS_VERSION, true );
                wp_localize_script( 'ims-admin-js', 'imsAjax', [ 'ajax_url' => admin_url( 'admin-ajax.php' ) ] );
            }
        }
    }

    public function render_invoice_details( $post ) {
        wp_nonce_field( 'ims_invoice_details_nonce', 'ims_invoice_details_nonce' );
        $invoice_date    = get_post_meta( $post->ID, '_invoice_date', true );
        $submission_date = get_post_meta( $post->ID, '_submission_date', true );
        $amount          = get_post_meta( $post->ID, '_invoice_amount', true );
        $file_id         = get_post_meta( $post->ID, '_invoice_file_id', true );
        ?>
        <p>
            <label><?php _e( 'Invoice Date:', 'invoice-management-system' ); ?></label><br>
            <input type="date" name="_invoice_date" value="<?php echo esc_attr( $invoice_date ); ?>" />
        </p>
        <p>
            <label><?php _e( 'Submission Date:', 'invoice-management-system' ); ?></label><br>
            <input type="date" name="_submission_date" value="<?php echo esc_attr( $submission_date ); ?>" />
        </p>
        <p>
            <label><?php _e( 'Invoice Amount (₹):', 'invoice-management-system' ); ?></label><br>
            <input type="number" step="0.01" name="_invoice_amount" value="<?php echo esc_attr( $amount ); ?>" />
        </p>
        <p>
            <input type="file" name="_invoice_file" accept="application/pdf,image/jpeg" />
            <?php if ( $file_id ) : ?>
                <p><em><?php _e( 'A file has already been uploaded. Uploading a new one will replace it.', 'invoice-management-system' ); ?></em></p>
                <p><a href="<?php echo esc_url( wp_get_attachment_url( $file_id ) ); ?>" target="_blank"><?php _e( 'View Current File', 'invoice-management-system' ); ?></a></p>
            <?php endif; ?>
        </p>
        <?php
    }

    public function render_invoice_status( $post ) {
        wp_nonce_field( 'ims_invoice_status_nonce', 'ims_invoice_status_nonce' );
        $status       = get_post_meta( $post->ID, '_invoice_status', true ) ?: 'pending';
        $payment_date = get_post_meta( $post->ID, '_payment_date', true );
        ?>
        <p>
            <label><?php _e( 'Status:', 'invoice-management-system' ); ?></label><br>
            <select name="_invoice_status" id="ims-status-select">
                <option value="pending" <?php selected( $status, 'pending' ); ?>><?php _e( 'Pending', 'invoice-management-system' ); ?></option>
                <option value="paid" <?php selected( $status, 'paid' ); ?>><?php _e( 'Paid', 'invoice-management-system' ); ?></option>
                <option value="cancel" <?php selected( $status, 'cancel' ); ?>><?php _e( 'Cancel', 'invoice-management-system' ); ?></option>
            </select>
        </p>
        <div id="ims-payment-date-wrapper" style="<?php echo ( $status === 'paid' ) ? '' : 'display:none;'; ?>">
            <label><?php _e( 'Payment Date:', 'invoice-management-system' ); ?></label><br>
            <input type="date" name="_payment_date" value="<?php echo esc_attr( $payment_date ); ?>" />
        </div>
        <?php
    }

    public function render_email_receivers( $post ) {
        wp_nonce_field( 'ims_email_receivers_nonce', 'ims_email_receivers_nonce' );
        $to   = get_post_meta( $post->ID, '_to_email', true );
        $cc   = get_post_meta( $post->ID, '_cc_emails', true ) ?: [];
        ?>
        <p>
            <label><?php _e( 'To Email:', 'invoice-management-system' ); ?></label><br>
            <input type="email" name="_to_email" value="<?php echo esc_attr( $to ); ?>" style="width:100%;" />
        </p>
        <div id="ims-cc-wrapper">
            <label><?php _e( 'CC Emails:', 'invoice-management-system' ); ?></label>
            <?php foreach ( $cc as $idx => $email ) : ?>
                <p>
                    <input type="email" name="_cc_emails[]" value="<?php echo esc_attr( $email ); ?>" style="width:90%;" />
                    <button class="button ims-remove-cc" type="button">&times;</button>
                </p>
            <?php endforeach; ?>
            <button class="button ims-add-cc" type="button"><?php _e( 'Add CC', 'invoice-management-system' ); ?></button>
        </div>
        <?php
    }

    public function save_metaboxes( $post_id, $post ) {
        // Skip autosave, revisions, AJAX, etc.
        if (
            defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ||
            defined( 'DOING_AJAX' ) && DOING_AJAX ||
            wp_is_post_revision( $post_id ) ||
            wp_is_post_autosave( $post_id )
        ) {
            return;
        }

        // Only run on 'publish' or 'draft' actions from UI
        if ( $post->post_type !== 'invoice' || ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
        
        // Verify nonces
        if ( ! isset( $_POST['ims_invoice_details_nonce'] ) || ! wp_verify_nonce( $_POST['ims_invoice_details_nonce'], 'ims_invoice_details_nonce' ) ) {
            return;
        }

        if ( ! isset( $_POST['ims_invoice_status_nonce'] ) || ! wp_verify_nonce( $_POST['ims_invoice_status_nonce'], 'ims_invoice_status_nonce' ) ) {
            return;
        }

        if ( ! isset( $_POST['ims_email_receivers_nonce'] ) || ! wp_verify_nonce( $_POST['ims_email_receivers_nonce'], 'ims_email_receivers_nonce' ) ) {
            return;
        }

        // Sanitize and validate
        $invoice_date    = sanitize_text_field( $_POST['_invoice_date'] );
        $submission_date = sanitize_text_field( $_POST['_submission_date'] );

        if ( strtotime( $invoice_date ) >= strtotime( $submission_date ) ) {
            wp_die( __( 'Invoice Date must be before Submission Date.', 'invoice-management-system' ) );
        }

        // Ensure file exists or is uploaded
        $existing_file = get_post_meta( $post_id, '_invoice_file_id', true );
        $new_file      = ! empty( $_FILES['_invoice_file']['name'] );
        
        if ( ! $existing_file && ! $new_file ) {
            wp_die( __( 'Invoice file upload is required.', 'invoice-management-system' ) );
        }

        // Update meta values
        update_post_meta( $post_id, '_invoice_date', $invoice_date );
        update_post_meta( $post_id, '_submission_date', $submission_date );
        update_post_meta( $post_id, '_invoice_amount', floatval( $_POST['_invoice_amount'] ) );

        // Handle file upload
        if ( $new_file ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';

            $file    = $_FILES['_invoice_file'];
            $allowed = [ 'application/pdf', 'image/jpeg' ];

            if ( in_array( $file['type'], $allowed, true ) ) {
                $attach_id = media_handle_upload( '_invoice_file', $post_id );
                if ( is_wp_error( $attach_id ) ) {
                    wp_die( $attach_id->get_error_message() );
                }
                update_post_meta( $post_id, '_invoice_file_id', $attach_id );
            } else {
                wp_die( __( 'Only PDF or JPEG files are allowed.', 'invoice-management-system' ) );
            }
        }

        // Invoice Status
        $status = sanitize_text_field( $_POST['_invoice_status'] );
        update_post_meta( $post_id, '_invoice_status', $status );

        if ( $status === 'paid' && ! empty( $_POST['_payment_date'] ) ) {
            update_post_meta( $post_id, '_payment_date', sanitize_text_field( $_POST['_payment_date'] ) );
        } else {
            delete_post_meta( $post_id, '_payment_date' );
        }

        // Email Receivers
        update_post_meta( $post_id, '_to_email', sanitize_email( $_POST['_to_email'] ) );

        $cc_emails = array_filter(
            array_map( 'sanitize_email', (array) $_POST['_cc_emails'] )
        );
        update_post_meta( $post_id, '_cc_emails', $cc_emails );
    }

    public function toggle_payment_date_callback() {
        // This can be used if you want: right now JS toggles the field.
        wp_send_json_success();
    }
}

// Initialize
Metaboxes::instance()->init();

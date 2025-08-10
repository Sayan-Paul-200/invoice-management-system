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
        add_action( 'save_post_ac_invoice',   [ $this, 'save_metaboxes' ], 10, 2 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_toggle_payment_date', [ $this, 'toggle_payment_date_callback' ] );
        add_action( 'post_edit_form_tag', [ $this, 'add_form_enctype' ] );
    }

    public function add_form_enctype() {
        $screen = get_current_screen();
        if ( $screen && isset( $screen->post_type ) && $screen->post_type === 'ac_invoice' ) {
            echo ' enctype="multipart/form-data"';
        }
    }


    public function register_metaboxes() {
        add_meta_box(
            'ims_invoice_details',
            __( 'Invoice Details', 'invoice-management-system' ),
            [ $this, 'render_invoice_details' ],
            'ac_invoice',
            'normal',
            'high'
        );

        add_meta_box(
            'ims_invoice_status',
            __( 'Invoice Status', 'invoice-management-system' ),
            [ $this, 'render_invoice_status' ],
            'ac_invoice',
            'normal',
            'high'
        );

        add_meta_box(
            'ims_email_receivers',
            __( 'Email Receivers', 'invoice-management-system' ),
            [ $this, 'render_email_receivers' ],
            'ac_invoice',
            'normal',
            'high'
        );

        add_meta_box(
            'ims_proj_loc',
            __( 'Projects & Locations', 'invoice-management-system' ),
            [ $this, 'render_proj_loc_metabox' ],
            'ac_invoice',
            'normal',      // sidebar or normal
            'default'      // high, default, low
        );
    }

    public function enqueue_assets( $hook ) {
        global $post;
        if ( $hook === 'post-new.php' || $hook === 'post.php' ) {
            if ( isset( $post->post_type ) && $post->post_type === 'ac_invoice' ) {
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

    public function render_proj_loc_metabox( $post ) {
        wp_nonce_field( 'ims_proj_loc_nonce', 'ims_proj_loc_nonce' );

        // Get saved values (or empty)
        $project  = get_post_meta( $post->ID, '_ims_project', true );
        $location = get_post_meta( $post->ID, '_ims_location', true );

        // Define your options
        $projects = [ 'NFS','GAIL','BGCL','STP','Bharat Net','NFS AMC' ];
        $locations = [ 'Bihar','Delhi','Goa','Gujarat','Haryana','Jharkhand','Madhya Pradesh','Odisha','Port Blair','Rajasthan','Sikkim','Uttar Pradesh','West Bengal' ];

        // Project select
        echo '<p><label>' . esc_html__( 'Project:', 'invoice-management-system' ) . '</label><br>';
        echo '<select name="_ims_project" style="width:100%;">';
        echo '<option value="">' . esc_html__( '-- Select Project --', 'invoice-management-system' ) . '</option>';
        foreach ( $projects as $opt ) {
            printf(
                '<option value="%1$s"%2$s>%1$s</option>',
                esc_attr( $opt ),
                selected( $project, $opt, false )
            );
        }
        echo '</select></p>';

        // Location select
        echo '<p><label>' . esc_html__( 'Location:', 'invoice-management-system' ) . '</label><br>';
        echo '<select name="_ims_location" style="width:100%;">';
        echo '<option value="">' . esc_html__( '-- Select Location --', 'invoice-management-system' ) . '</option>';
        foreach ( $locations as $opt ) {
            printf(
                '<option value="%1$s"%2$s>%1$s</option>',
                esc_attr( $opt ),
                selected( $location, $opt, false )
            );
        }
        echo '</select></p>';
    }


    public function save_metaboxes( $post_id, $post ) {
        // Skip autosave, revisions, AJAX, etc.
        if (
            ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ||
            ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ||
            wp_is_post_revision( $post_id ) ||
            wp_is_post_autosave( $post_id )
        ) {
            return;
        }

        // Only run for our CPT and if user can edit
        if ( $post->post_type !== 'ac_invoice' || ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Verify required nonces (these are output in render_* metaboxes)
        if ( ! isset( $_POST['ims_invoice_details_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['ims_invoice_details_nonce'] ), 'ims_invoice_details_nonce' ) ) {
            return;
        }
        if ( ! isset( $_POST['ims_invoice_status_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['ims_invoice_status_nonce'] ), 'ims_invoice_status_nonce' ) ) {
            return;
        }
        if ( ! isset( $_POST['ims_email_receivers_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['ims_email_receivers_nonce'] ), 'ims_email_receivers_nonce' ) ) {
            return;
        }

        // Safely pull POST values (unslash then sanitize)
        $invoice_date    = isset( $_POST['_invoice_date'] ) ? sanitize_text_field( wp_unslash( $_POST['_invoice_date'] ) ) : '';
        $submission_date = isset( $_POST['_submission_date'] ) ? sanitize_text_field( wp_unslash( $_POST['_submission_date'] ) ) : '';
        $amount_raw      = isset( $_POST['_invoice_amount'] ) ? wp_unslash( $_POST['_invoice_amount'] ) : '';
        $amount          = $amount_raw === '' ? 0.0 : floatval( $amount_raw );

        // Validate dates (if provided)
        if ( $invoice_date && $submission_date ) {
            $inv_ts = strtotime( $invoice_date );
            $sub_ts = strtotime( $submission_date );
            if ( $inv_ts === false || $sub_ts === false || $inv_ts >= $sub_ts ) {
                wp_die( esc_html__( 'Invoice Date must be before Submission Date.', 'invoice-management-system' ) );
            }
        }

        // File upload logic
        $new_file = isset( $_FILES['_invoice_file'] ) && ! empty( $_FILES['_invoice_file']['name'] );

        if ( $new_file ) {
            // allowed mimes
            $allowed = [ 'application/pdf', 'image/jpeg' ];

            // Basic filetype check
            $file_type = wp_check_filetype_and_ext( $_FILES['_invoice_file']['tmp_name'], $_FILES['_invoice_file']['name'] );
            if ( empty( $file_type['type'] ) || ! in_array( $file_type['type'], $allowed, true ) ) {
                wp_die( esc_html__( 'Only PDF or JPEG files are allowed.', 'invoice-management-system' ) );
            }

            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';

            $attach_id = media_handle_upload( '_invoice_file', $post_id );
            if ( is_wp_error( $attach_id ) ) {
                wp_die( esc_html( $attach_id->get_error_message() ) );
            }
            update_post_meta( $post_id, '_invoice_file_id', $attach_id );

            // store URL too
            $file_url = wp_get_attachment_url( $attach_id );
            if ( $file_url ) {
                update_post_meta( $post_id, '_invoice_file_url', esc_url_raw( $file_url ) );
            }
        } else {
            // If no new upload but there is an existing attachment ID, ensure URL meta exists
            $existing_file_id = get_post_meta( $post_id, '_invoice_file_id', true );
            if ( $existing_file_id && ! get_post_meta( $post_id, '_invoice_file_url', true ) ) {
                $existing_url = wp_get_attachment_url( $existing_file_id );
                if ( $existing_url ) {
                    update_post_meta( $post_id, '_invoice_file_url', esc_url_raw( $existing_url ) );
                }
            }
        }

        // Save dates and amount (only when present)
        if ( $invoice_date !== '' ) {
            update_post_meta( $post_id, '_invoice_date', $invoice_date );
        }
        if ( $submission_date !== '' ) {
            update_post_meta( $post_id, '_submission_date', $submission_date );
        }
        update_post_meta( $post_id, '_invoice_amount', $amount );

        // Validate & save invoice status (allow only known values)
        $allowed_status = [ 'pending', 'paid', 'cancel' ];
        $status_raw = isset( $_POST['_invoice_status'] ) ? sanitize_text_field( wp_unslash( $_POST['_invoice_status'] ) ) : 'pending';
        $status = in_array( $status_raw, $allowed_status, true ) ? $status_raw : 'pending';
        update_post_meta( $post_id, '_invoice_status', $status );

        // Payment date
        if ( $status === 'paid' && ! empty( $_POST['_payment_date'] ) ) {
            $payment_date = sanitize_text_field( wp_unslash( $_POST['_payment_date'] ) );
            update_post_meta( $post_id, '_payment_date', $payment_date );
        } else {
            delete_post_meta( $post_id, '_payment_date' );
        }

        // Email Receivers
        $to_email = isset( $_POST['_to_email'] ) ? sanitize_email( wp_unslash( $_POST['_to_email'] ) ) : '';
        update_post_meta( $post_id, '_to_email', $to_email );

        $raw_cc = isset( $_POST['_cc_emails'] ) ? (array) wp_unslash( $_POST['_cc_emails'] ) : [];
        $cc_emails = array_filter( array_map( 'sanitize_email', $raw_cc ) );
        update_post_meta( $post_id, '_cc_emails', $cc_emails );

        // Projects & Locations — validate against allowed lists
        if ( isset( $_POST['ims_proj_loc_nonce'] ) && wp_verify_nonce( wp_unslash( $_POST['ims_proj_loc_nonce'] ), 'ims_proj_loc_nonce' ) ) {
            $allowed_projects = [ 'NFS','GAIL','BGCL','STP','Bharat Net','NFS AMC' ];
            $allowed_locations = [ 'Bihar','Delhi','Goa','Gujarat','Haryana','Jharkhand','Madhya Pradesh','Odisha','Port Blair','Rajasthan','Sikkim','Uttar Pradesh','West Bengal' ];

            $proj_raw = isset( $_POST['_ims_project'] ) ? sanitize_text_field( wp_unslash( $_POST['_ims_project'] ) ) : '';
            $loc_raw  = isset( $_POST['_ims_location'] ) ? sanitize_text_field( wp_unslash( $_POST['_ims_location'] ) ) : '';

            $proj = in_array( $proj_raw, $allowed_projects, true ) ? $proj_raw : '';
            $loc  = in_array( $loc_raw,  $allowed_locations, true ) ? $loc_raw : '';

            update_post_meta( $post_id, '_ims_project',  $proj );
            update_post_meta( $post_id, '_ims_location', $loc );
        }
    }


    public function toggle_payment_date_callback() {
        // This can be used if you want: right now JS toggles the field.
        wp_send_json_success();
    }
}

// Initialize
Metaboxes::instance()->init();

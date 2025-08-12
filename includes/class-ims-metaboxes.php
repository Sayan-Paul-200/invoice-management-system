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
            'ims_invoice_amounts',
            __( 'Invoice Amounts', 'invoice-management-system' ),
            [ $this, 'render_invoice_amounts' ],
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
            'normal',
            'default'
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
        // $amount          = get_post_meta( $post->ID, '_invoice_amount', true );
        $file_id         = get_post_meta( $post->ID, '_invoice_file_id', true );
        $bill_category = get_post_meta( $post->ID, '_invoice_bill_category', true );
        $bill_options = [
            'service'               => __( 'Service', 'invoice-management-system' ),
            'supply'                => __( 'Supply', 'invoice-management-system' ),
            'row'                   => __( 'ROW', 'invoice-management-system' ),
            'amc'                   => __( 'AMC', 'invoice-management-system' ),
            'restoration_service'   => __( 'Restoration Service', 'invoice-management-system' ),
            'restoration_supply'    => __( 'Restoration Supply', 'invoice-management-system' ),
            'restoration_row'       => __( 'Restoration Row', 'invoice-management-system' ),
            'spares'                => __( 'Spares', 'invoice-management-system' ),
            'training'              => __( 'Training', 'invoice-management-system' ),
        ];
        $milestone = get_post_meta( $post->ID, '_invoice_milestone', true );
        $milestone = $milestone !== '' ? intval( $milestone ) : 0;
        $range_id = 'ims_milestone_range_' . $post->ID;
        $val_id   = 'ims_milestone_val_' . $post->ID;
        echo '<p><label>' . esc_html__( 'Bill Category:', 'invoice-management-system' ) . '</label><br>';
        echo '<select name="_invoice_bill_category" style="width:100%;">';
            echo '<option value="">' . esc_html__( '-- Select Category --', 'invoice-management-system' ) . '</option>';
            foreach ( $bill_options as $val => $label ) {
                printf(
                    '<option value="%1$s"%2$s>%3$s</option>',
                    esc_attr( $val ),
                    selected( $bill_category, $val, false ),
                    esc_html( $label )
                    );
                }
                echo '</select></p>';
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
            <input type="file" name="_invoice_file" accept="application/pdf,image/jpeg" />
            <?php if ( $file_id ) : ?>
                <p><em><?php _e( 'A file has already been uploaded. Uploading a new one will replace it.', 'invoice-management-system' ); ?></em></p>
                <p><a href="<?php echo esc_url( wp_get_attachment_url( $file_id ) ); ?>" target="_blank"><?php _e( 'View Current File', 'invoice-management-system' ); ?></a></p>
            <?php endif; ?>
        </p>
        <?php
            echo '<p><label>' . esc_html__( 'Milestone (%):', 'invoice-management-system' ) . '</label><br>';
            printf(
                '<input type="range" id="%1$s" name="_invoice_milestone" min="0" max="100" value="%2$d" oninput="document.getElementById(\'%3$s\').textContent = this.value + \'%%\'" />',
                esc_attr( $range_id ),
                intval( $milestone ),
                esc_attr( $val_id )
            );
            printf( ' <span id="%1$s">%2$d%%</span>', esc_attr( $val_id ), intval( $milestone ) );
            echo '</p>';
    }

    /**
     * Render the Invoice Amounts metabox.
     */
    public function render_invoice_amounts( $post ) {
        // nonce to validate when saving
        wp_nonce_field( 'ims_invoice_amounts_nonce', 'ims_invoice_amounts_nonce' );

        // Prefer the new meta; fall back to old meta for display (migration-friendly)
        $basic_amount = get_post_meta( $post->ID, '_invoice_basic_amount', true );
        if ( $basic_amount === '' ) {
            $basic_amount = get_post_meta( $post->ID, '_invoice_amount', true );
        }

        // Ensure numeric formatting
        $basic_amount = $basic_amount === '' ? '' : esc_attr( floatval( $basic_amount ) );

        // GST percent (0..30)
        $gst = get_post_meta( $post->ID, '_invoice_gst_percent', true );
        if ( $gst === '' ) {
            $gst = 0;
        }
        $gst_val = intval( $gst ); // clamp/display as integer

        // Existing GST amount and total (for display)
        $gst_amount = get_post_meta( $post->ID, '_invoice_gst_amount', true );
        $gst_amount = $gst_amount === '' ? '' : esc_attr( floatval( $gst_amount ) );

        // Total amount (calculated)
        $total_amount = get_post_meta( $post->ID, '_invoice_total_amount', true );
        $total_amount = $total_amount === '' ? '' : esc_attr( floatval( $total_amount ) );

        // Client amount (what client passed)
        $client_amount = get_post_meta( $post->ID, '_invoice_client_amount', true );
        $client_amount = $client_amount === '' ? '' : esc_attr( floatval( $client_amount ) );

        // Retention amount
        $retention = get_post_meta( $post->ID, '_invoice_retention_amount', true );
        $retention = $retention === '' ? '' : esc_attr( floatval( $retention ) );

        // GST Withheld amount (manual input)
        $gst_withheld = get_post_meta( $post->ID, '_invoice_gst_withheld', true );
        $gst_withheld = $gst_withheld === '' ? '' : esc_attr( floatval( $gst_withheld ) );

        // TDS amount (manual input)
        $tds_amount = get_post_meta( $post->ID, '_invoice_tds_amount', true );
        $tds_amount = $tds_amount === '' ? '' : esc_attr( floatval( $tds_amount ) );

        // GST TDS amount (manual input)
        $gst_tds_amount = get_post_meta( $post->ID, '_invoice_gst_tds_amount', true );
        $gst_tds_amount = $gst_tds_amount === '' ? '' : esc_attr( floatval( $gst_tds_amount ) );

        // BOCW amount (manual input)
        $bocw_amount = get_post_meta( $post->ID, '_invoice_bocw_amount', true );
        $bocw_amount = $bocw_amount === '' ? '' : esc_attr( floatval( $bocw_amount ) );

        // Low Depth Deduction amount (manual input)
        $low_depth_deduction = get_post_meta( $post->ID, '_invoice_low_depth_deduction_amount', true );
        $low_depth_deduction = $low_depth_deduction === '' ? '' : esc_attr( floatval( $low_depth_deduction ) );

        // Liquidated Damages amount (manual input)
        $liquidated_damages = get_post_meta( $post->ID, '_invoice_liquidated_damages_amount', true );
        $liquidated_damages = $liquidated_damages === '' ? '' : esc_attr( floatval( $liquidated_damages ) );

        // SLA Penalty amount (manual input)
        $sla_penalty = get_post_meta( $post->ID, '_invoice_sla_penalty_amount', true );
        $sla_penalty = $sla_penalty === '' ? '' : esc_attr( floatval( $sla_penalty ) );

        // Penalty amount (manual input)
        $penalty_amount = get_post_meta( $post->ID, '_invoice_penalty_amount', true );
        $penalty_amount = $penalty_amount === '' ? '' : esc_attr( floatval( $penalty_amount ) );

        // Other Deduction amount (manual input)
        $other_deduction = get_post_meta( $post->ID, '_invoice_other_deduction_amount', true );
        $other_deduction = $other_deduction === '' ? '' : esc_attr( floatval( $other_deduction ) );

        // Total Deductions (calculated)
        $total_deduction = get_post_meta( $post->ID, '_invoice_total_deduction_amount', true );
        $total_deduction = $total_deduction === '' ? '' : esc_attr( floatval( $total_deduction ) );

        // Net Payable (calculated)
        $net_payable = get_post_meta( $post->ID, '_invoice_net_payable_amount', true );
        $net_payable = $net_payable === '' ? '' : esc_attr( floatval( $net_payable ) );

        // Amount paid by client (manual input)
        $amount_paid = get_post_meta( $post->ID, '_invoice_amount_paid', true );
        $amount_paid = $amount_paid === '' ? '' : esc_attr( floatval( $amount_paid ) );

        // Balance (calculated)
        $balance = get_post_meta( $post->ID, '_invoice_balance_amount', true );
        $balance = $balance === '' ? '' : esc_attr( floatval( $balance ) );

        ?>
        <p>
            <label><?php esc_html_e( 'Basic Invoice Amount (₹):', 'invoice-management-system' ); ?></label><br />
            <input type="number" name="_invoice_basic_amount" step="0.01" value="<?php echo $basic_amount; ?>" style="width:100%;" />
        </p>

        <p>
            <label><?php esc_html_e( 'GST (%):', 'invoice-management-system' ); ?></label><br />
            <?php
            $range_id = 'ims_gst_range_' . $post->ID;
            $display_id = 'ims_gst_val_' . $post->ID;
            printf(
                '<input type="range" id="%1$s" name="_invoice_gst_percent" min="0" max="30" value="%2$d" oninput="document.getElementById(\'%3$s\').textContent = this.value + \'%%\'" />',
                esc_attr( $range_id ),
                intval( $gst_val ),
                esc_attr( $display_id )
            );
            printf( ' <span id="%1$s">%2$d%%</span>', esc_attr( $display_id ), intval( $gst_val ) );
            ?>
        </p>

        <p>
            <label><?php esc_html_e( 'GST Amount (₹):', 'invoice-management-system' ); ?></label><br />
            <!-- Visible readonly field for admin -->
            <input type="number" id="ims_gst_amount" step="0.01" value="<?php echo $gst_amount; ?>" readonly style="width:100%;" />
            <!-- Hidden/submit field (readonly inputs submit too; keep name so it's saved) -->
            <input type="hidden" name="_invoice_gst_amount" id="ims_gst_amount_hidden" value="<?php echo $gst_amount; ?>" />
        </p>

        <p>
            <label><?php esc_html_e( 'Total Amount (₹):', 'invoice-management-system' ); ?></label><br />
            <input type="number" id="ims_total_amount" step="0.01" value="<?php echo $total_amount; ?>" readonly style="width:100%;" />
            <input type="hidden" name="_invoice_total_amount" id="ims_total_amount_hidden" value="<?php echo $total_amount; ?>" />
        </p>

        <p>
            <label><?php esc_html_e( 'Client Amount (₹):', 'invoice-management-system' ); ?></label><br />
            <input type="number" name="_invoice_client_amount" step="0.01" value="<?php echo $client_amount; ?>" style="width:100%;" />
            <em class="description"><?php esc_html_e( 'Amount passed by the client.', 'invoice-management-system' ); ?></em>
        </p>

        <p>
            <label><?php esc_html_e( 'Retention Amount (₹):', 'invoice-management-system' ); ?></label><br />
            <input type="number" name="_invoice_retention_amount" step="0.01" value="<?php echo $retention; ?>" style="width:100%;" />
            <em class="description"><?php esc_html_e( 'Retention withheld.', 'invoice-management-system' ); ?></em>
        </p>

        <p>
            <label><?php esc_html_e( 'GST Withheld (₹):', 'invoice-management-system' ); ?></label><br />
            <input type="number" name="_invoice_gst_withheld" step="0.01" value="<?php echo $gst_withheld; ?>" style="width:100%;" />
            <em class="description"><?php esc_html_e( 'Manual GST withheld (if any).', 'invoice-management-system' ); ?></em>
        </p>

        <p>
            <label><?php esc_html_e( 'TDS Amount (₹):', 'invoice-management-system' ); ?></label><br />
            <input type="number" name="_invoice_tds_amount" step="0.01" value="<?php echo $tds_amount; ?>" style="width:100%;" />
            <em class="description"><?php esc_html_e( 'Tax deducted at source (if any).', 'invoice-management-system' ); ?></em>
        </p>

        <p>
            <label><?php esc_html_e( 'GST TDS Amount (₹):', 'invoice-management-system' ); ?></label><br />
            <input type="number" name="_invoice_gst_tds_amount" step="0.01" value="<?php echo $gst_tds_amount; ?>" style="width:100%;" />
            <em class="description"><?php esc_html_e( 'GST TDS (if any).', 'invoice-management-system' ); ?></em>
        </p>

        <p>
            <label><?php esc_html_e( 'BOCW Amount (₹):', 'invoice-management-system' ); ?></label><br />
            <input type="number" name="_invoice_bocw_amount" step="0.01" value="<?php echo $bocw_amount; ?>" style="width:100%;" />
            <em class="description"><?php esc_html_e( 'BOCW amount (if any).', 'invoice-management-system' ); ?></em>
        </p>

        <p>
            <label><?php esc_html_e( 'Low Depth Deduction (₹):', 'invoice-management-system' ); ?></label><br />
            <input type="number" name="_invoice_low_depth_deduction_amount" step="0.01" value="<?php echo $low_depth_deduction; ?>" style="width:100%;" />
            <em class="description"><?php esc_html_e( 'Low depth deduction amount (if any).', 'invoice-management-system' ); ?></em>
        </p>

        <p>
            <label><?php esc_html_e( 'Liquidated Damages (₹):', 'invoice-management-system' ); ?></label><br />
            <input type="number" name="_invoice_liquidated_damages_amount" step="0.01" value="<?php echo $liquidated_damages; ?>" style="width:100%;" />
            <em class="description"><?php esc_html_e( 'Liquidated damages amount (if any).', 'invoice-management-system' ); ?></em>
        </p>

        <p>
            <label><?php esc_html_e( 'SLA Penalty (₹):', 'invoice-management-system' ); ?></label><br />
            <input type="number" name="_invoice_sla_penalty_amount" step="0.01" value="<?php echo $sla_penalty; ?>" style="width:100%;" />
            <em class="description"><?php esc_html_e( 'Service Level Agreement penalty amount (if any).', 'invoice-management-system' ); ?></em>
        </p>

        <p>
            <label><?php esc_html_e( 'Penalty (₹):', 'invoice-management-system' ); ?></label><br />
            <input type="number" name="_invoice_penalty_amount" step="0.01" value="<?php echo $penalty_amount; ?>" style="width:100%;" />
            <em class="description"><?php esc_html_e( 'Penalty amount (if any).', 'invoice-management-system' ); ?></em>
        </p>

        <p>
            <label><?php esc_html_e( 'Other Deduction (₹):', 'invoice-management-system' ); ?></label><br />
            <input type="number" name="_invoice_other_deduction_amount" step="0.01" value="<?php echo $other_deduction; ?>" style="width:100%;" />
            <em class="description"><?php esc_html_e( 'Other deduction amount (if any).', 'invoice-management-system' ); ?></em>
        </p>

        <p>
            <label><?php esc_html_e( 'Total Deductions (₹):', 'invoice-management-system' ); ?></label><br />
            <!-- visible readonly input for admin -->
            <input type="number" id="ims_total_deduction" step="0.01" value="<?php echo $total_deduction; ?>" readonly style="width:100%;" />
            <!-- hidden input that actually gets submitted/saved -->
            <input type="hidden" name="_invoice_total_deduction_amount" id="ims_total_deduction_hidden" value="<?php echo $total_deduction; ?>" />
            <em class="description"><?php esc_html_e( 'Sum of all deductions (auto-calculated).', 'invoice-management-system' ); ?></em>
        </p>

        <p>
            <label><?php esc_html_e( 'Net Payable (₹):', 'invoice-management-system' ); ?></label><br />
            <!-- visible readonly input for admin -->
            <input type="number" id="ims_net_payable" step="0.01" value="<?php echo $net_payable; ?>" readonly style="width:100%;" />
            <!-- hidden input that actually gets submitted/saved -->
            <input type="hidden" name="_invoice_net_payable_amount" id="ims_net_payable_hidden" value="<?php echo $net_payable; ?>" />
            <em class="description"><?php esc_html_e( 'Total amount payable (Total Amount minus Total Deductions).', 'invoice-management-system' ); ?></em>
        </p>

        <p>
            <label><?php esc_html_e( 'Amount Paid by Client (₹):', 'invoice-management-system' ); ?></label><br />
            <input type="number" name="_invoice_amount_paid" step="0.01" value="<?php echo $amount_paid; ?>" style="width:100%;" />
            <em class="description"><?php esc_html_e( 'Amount actually paid by the client.', 'invoice-management-system' ); ?></em>
        </p>

        <p>
            <label><?php esc_html_e( 'Balance (₹):', 'invoice-management-system' ); ?></label><br />
            <!-- visible readonly input -->
            <input type="number" id="ims_balance" step="0.01" value="<?php echo $balance; ?>" readonly style="width:100%;" />
            <!-- hidden input that actually gets submitted/saved -->
            <input type="hidden" name="_invoice_balance_amount" id="ims_balance_hidden" value="<?php echo $balance; ?>" />
            <em class="description"><?php esc_html_e( 'Balance = Net Payable − Amount Paid.', 'invoice-management-system' ); ?></em>
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
                <option value="credit_note_issued" <?php selected( $status, 'credit_note_issued' ); ?>><?php _e( 'Credit Note Issued', 'invoice-management-system' ); ?></option>
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
        $mode = get_post_meta( $post->ID, '_ims_project_mode', true );

        // Define your options
        $projects = [ 'NFS','GAIL','BGCL','STP','Bharat Net','NFS AMC' ];
        $locations = [ 'Bihar','Delhi','Goa','Gujarat','Haryana','Jharkhand','Madhya Pradesh','Odisha','Port Blair','Rajasthan','Sikkim','Uttar Pradesh','West Bengal' ];
        $mode_options = [
            'back_to_back' => __( 'Back to Back', 'invoice-management-system' ),
            'direct'       => __( 'Direct', 'invoice-management-system' ),
        ];

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

        // Mode of Project select
        echo '<p><label>' . esc_html__( 'Project Mode:', 'invoice-management-system' ) . '</label><br>';
        echo '<select name="_ims_project_mode" style="width:100%;">';
        echo '<option value="">' . esc_html__( '-- Select Mode --', 'invoice-management-system' ) . '</option>';
        foreach ( $mode_options as $val => $label ) {
            printf(
                '<option value="%1$s"%2$s>%3$s</option>',
                esc_attr( $val ),
                selected( $mode, $val, false ),
                esc_html( $label )
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
        if ( ! isset( $_POST['ims_invoice_amounts_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['ims_invoice_amounts_nonce'] ), 'ims_invoice_amounts_nonce' ) ) {
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
        // $amount_raw      = isset( $_POST['_invoice_amount'] ) ? wp_unslash( $_POST['_invoice_amount'] ) : '';
        // $amount          = $amount_raw === '' ? 0.0 : floatval( $amount_raw );

        // ====== Save invoice bill category ======
        $allowed_bill = [
            'service',
            'supply',
            'row',
            'amc',
            'restoration_service',
            'restoration_supply',
            'restoration_row',
            'spares',
            'training',
        ];

        // read safely
        $bill_raw = isset( $_POST['_invoice_bill_category'] ) ? sanitize_text_field( wp_unslash( $_POST['_invoice_bill_category'] ) ) : '';

        // if valid, save; otherwise remove meta (or leave as-is)
        if ( $bill_raw === '' ) {
            delete_post_meta( $post_id, '_invoice_bill_category' );
        } elseif ( in_array( $bill_raw, $allowed_bill, true ) ) {
            update_post_meta( $post_id, '_invoice_bill_category', $bill_raw );
        } else {
            // optional: ignore invalid value or sanitize to empty
            delete_post_meta( $post_id, '_invoice_bill_category' );
        }


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
        update_post_meta( $post_id, '_invoice_basic_amount', $basic_amount );
        update_post_meta( $post_id, '_invoice_amount', $basic_amount ); // optional legacy sync

        // ====== Save milestone (%) ======
        if ( isset( $_POST['_invoice_milestone'] ) ) {
            $ms_raw = wp_unslash( $_POST['_invoice_milestone'] );
            // integer value, clamp 0..100
            $ms = intval( $ms_raw );
            $ms = max( 0, min( 100, $ms ) );
            update_post_meta( $post_id, '_invoice_milestone', $ms );
        } else {
            // optionally remove if no value submitted
            delete_post_meta( $post_id, '_invoice_milestone' );
        }

        // Read posted basic amount if present
        $basic_amount_raw = isset( $_POST['_invoice_basic_amount'] )
            ? wp_unslash( $_POST['_invoice_basic_amount'] )
            : null;

        if ( $basic_amount_raw !== null && $basic_amount_raw !== '' ) {
            $basic_amount = floatval( $basic_amount_raw );
            update_post_meta( $post_id, '_invoice_basic_amount', $basic_amount );
        } else {
            // If no new posted value, but old meta exists and new meta missing, migrate it
            $existing_new = get_post_meta( $post_id, '_invoice_basic_amount', true );
            if ( $existing_new === '' ) {
                $old_amount = get_post_meta( $post_id, '_invoice_amount', true );
                if ( $old_amount !== '' ) {
                    // migrate the old meta into new key
                    update_post_meta( $post_id, '_invoice_basic_amount', floatval( $old_amount ) );
                    // optional: delete_post_meta( $post_id, '_invoice_amount' );
                } else {
                    // set to zero if you prefer explicit meta
                    update_post_meta( $post_id, '_invoice_basic_amount', 0.0 );
                }
            }
        }

        // ====== Save GST percent (0..30) ======
        if ( isset( $_POST['_invoice_gst_percent'] ) ) {
            $gst_raw = wp_unslash( $_POST['_invoice_gst_percent'] );
            $gst_int = intval( $gst_raw );
            // Clamp to 0..30
            $gst_int = max( 0, min( 30, $gst_int ) );
            update_post_meta( $post_id, '_invoice_gst_percent', $gst_int );
        } else {
            // If not present in POST, you can leave existing meta as-is or delete:
            // delete_post_meta( $post_id, '_invoice_gst_percent' );
        }

        // ====== Compute & save GST amount (basic * gst_percent / 100) ======
        // Read up-to-date values (prefer posted values; fall back to existing meta)
        $basic_amount_for_gst = null;
        if ( isset( $_POST['_invoice_basic_amount'] ) ) {
            $basic_amount_for_gst = floatval( wp_unslash( $_POST['_invoice_basic_amount'] ) );
        } else {
            $basic_amount_for_gst = floatval( get_post_meta( $post_id, '_invoice_basic_amount', true ) ?: 0 );
        }

        $gst_percent_for_calc = null;
        if ( isset( $_POST['_invoice_gst_percent'] ) ) {
            $gst_percent_for_calc = intval( wp_unslash( $_POST['_invoice_gst_percent'] ) );
        } else {
            $gst_percent_for_calc = intval( get_post_meta( $post_id, '_invoice_gst_percent', true ) ?: 0 );
        }

        // Clamp GST percent defensively
        $gst_percent_for_calc = max( 0, min( 30, $gst_percent_for_calc ) );

        // Calculate
        $gst_amount_calc = round( ( $basic_amount_for_gst * $gst_percent_for_calc ) / 100, 2 );

        // Calculate Total = basic + GST
        $total_amount_calc = round( floatval( $basic_amount_for_gst ) + floatval( $gst_amount_calc ), 2 );

        // Save (float)
        update_post_meta( $post_id, '_invoice_gst_amount', $gst_amount_calc );

        // Save total
        update_post_meta( $post_id, '_invoice_total_amount', $total_amount_calc );

        // Also ensure the visible basic meta exists (you already do this earlier)
        update_post_meta( $post_id, '_invoice_basic_amount', floatval( $basic_amount_for_gst ) );

        // Optional: keep legacy key synced if you still use it
        update_post_meta( $post_id, '_invoice_amount', floatval( $basic_amount_for_gst ) );

        // Read posted client amount if present
        $client_amount_raw = isset( $_POST['_invoice_client_amount'] ) ? wp_unslash( $_POST['_invoice_client_amount'] ) : null;
        if ( $client_amount_raw !== null && $client_amount_raw !== '' ) {
            // cast to float, you can clamp >= 0 if you'd like
            $client_amount = floatval( $client_amount_raw );
            update_post_meta( $post_id, '_invoice_client_amount', $client_amount );
        } else {
            // optional: delete_post_meta( $post_id, '_invoice_client_amount' );
            // or leave existing meta as-is. We'll remove if explicitly empty:
            delete_post_meta( $post_id, '_invoice_client_amount' );
        }

        // Read posted retention amount if present
        $retention_raw = isset( $_POST['_invoice_retention_amount'] ) ? wp_unslash( $_POST['_invoice_retention_amount'] ) : null;

        if ( $retention_raw !== null && $retention_raw !== '' ) {
            $retention = floatval( $retention_raw );
            // optional: enforce non-negative
            if ( $retention < 0 ) {
                $retention = 0.0;
            }
            update_post_meta( $post_id, '_invoice_retention_amount', $retention );
        } else {
            // remove meta if explicitly empty (optional)
            delete_post_meta( $post_id, '_invoice_retention_amount' );
        }

        // Read posted GST withheld if present
        $gst_withheld_raw = isset( $_POST['_invoice_gst_withheld'] ) ? wp_unslash( $_POST['_invoice_gst_withheld'] ) : null;

        if ( $gst_withheld_raw !== null && $gst_withheld_raw !== '' ) {
            $gst_withheld = floatval( $gst_withheld_raw );
            // optional: enforce non-negative
            if ( $gst_withheld < 0 ) {
                $gst_withheld = 0.0;
            }
            update_post_meta( $post_id, '_invoice_gst_withheld', $gst_withheld );
        } else {
            // remove meta if explicitly empty (optional)
            delete_post_meta( $post_id, '_invoice_gst_withheld' );
        }

        // Read posted TDS amount if present
        $tds_raw = isset( $_POST['_invoice_tds_amount'] ) ? wp_unslash( $_POST['_invoice_tds_amount'] ) : null;

        if ( $tds_raw !== null && $tds_raw !== '' ) {
            $tds = floatval( $tds_raw );
            // optional: enforce non-negative
            if ( $tds < 0 ) {
                $tds = 0.0;
            }
            update_post_meta( $post_id, '_invoice_tds_amount', $tds );
        } else {
            // remove meta if explicitly empty (optional)
            delete_post_meta( $post_id, '_invoice_tds_amount' );
        }

        // Read posted GST TDS amount if present
        $gst_tds_raw = isset( $_POST['_invoice_gst_tds_amount'] ) ? wp_unslash( $_POST['_invoice_gst_tds_amount'] ) : null;

        if ( $gst_tds_raw !== null && $gst_tds_raw !== '' ) {
            $gst_tds = floatval( $gst_tds_raw );
            // optional: enforce non-negative
            if ( $gst_tds < 0 ) {
                $gst_tds = 0.0;
            }
            update_post_meta( $post_id, '_invoice_gst_tds_amount', $gst_tds );
        } else {
            // remove meta if explicitly empty (optional)
            delete_post_meta( $post_id, '_invoice_gst_tds_amount' );
        }

        // Read posted BOCW amount if present
        $bocw_raw = isset( $_POST['_invoice_bocw_amount'] ) ? wp_unslash( $_POST['_invoice_bocw_amount'] ) : null;

        if ( $bocw_raw !== null && $bocw_raw !== '' ) {
            $bocw = floatval( $bocw_raw );
            // optional: enforce non-negative
            if ( $bocw < 0 ) {
                $bocw = 0.0;
            }
            update_post_meta( $post_id, '_invoice_bocw_amount', $bocw );
        } else {
            // remove meta if explicitly empty (optional)
            delete_post_meta( $post_id, '_invoice_bocw_amount' );
        }

        // Read posted Low Depth Deduction amount if present
        $low_depth_raw = isset( $_POST['_invoice_low_depth_deduction_amount'] ) ? wp_unslash( $_POST['_invoice_low_depth_deduction_amount'] ) : null;

        if ( $low_depth_raw !== null && $low_depth_raw !== '' ) {
            $low_depth = floatval( $low_depth_raw );
            // optional: enforce non-negative
            if ( $low_depth < 0 ) {
                $low_depth = 0.0;
            }
            update_post_meta( $post_id, '_invoice_low_depth_deduction_amount', $low_depth );
        } else {
            // remove meta if explicitly empty (optional)
            delete_post_meta( $post_id, '_invoice_low_depth_deduction_amount' );
        }

        // Read posted Liquidated Damages amount if present
        $liquidated_raw = isset( $_POST['_invoice_liquidated_damages_amount'] ) ? wp_unslash( $_POST['_invoice_liquidated_damages_amount'] ) : null;

        if ( $liquidated_raw !== null && $liquidated_raw !== '' ) {
            $liquidated = floatval( $liquidated_raw );
            // optional: enforce non-negative
            if ( $liquidated < 0 ) {
                $liquidated = 0.0;
            }
            update_post_meta( $post_id, '_invoice_liquidated_damages_amount', $liquidated );
        } else {
            // remove meta if explicitly empty (optional)
            delete_post_meta( $post_id, '_invoice_liquidated_damages_amount' );
        }

        // Read posted SLA Penalty amount if present
        $sla_penalty_raw = isset( $_POST['_invoice_sla_penalty_amount'] ) ? wp_unslash( $_POST['_invoice_sla_penalty_amount'] ) : null;

        if ( $sla_penalty_raw !== null && $sla_penalty_raw !== '' ) {
            $sla_penalty = floatval( $sla_penalty_raw );
            // optional: enforce non-negative
            if ( $sla_penalty < 0 ) {
                $sla_penalty = 0.0;
            }
            update_post_meta( $post_id, '_invoice_sla_penalty_amount', $sla_penalty );
        } else {
            // remove meta if explicitly empty (optional)
            delete_post_meta( $post_id, '_invoice_sla_penalty_amount' );
        }

        // Read posted Penalty amount if present
        $penalty_raw = isset( $_POST['_invoice_penalty_amount'] ) ? wp_unslash( $_POST['_invoice_penalty_amount'] ) : null;

        if ( $penalty_raw !== null && $penalty_raw !== '' ) {
            $penalty = floatval( $penalty_raw );
            // optional: enforce non-negative
            if ( $penalty < 0 ) {
                $penalty = 0.0;
            }
            update_post_meta( $post_id, '_invoice_penalty_amount', $penalty );
        } else {
            // remove meta if explicitly empty (optional)
            delete_post_meta( $post_id, '_invoice_penalty_amount' );
        }

        // Read posted Other Deduction amount if present
        $other_raw = isset( $_POST['_invoice_other_deduction_amount'] ) ? wp_unslash( $_POST['_invoice_other_deduction_amount'] ) : null;

        if ( $other_raw !== null && $other_raw !== '' ) {
            $other_deduction_val = floatval( $other_raw );
            // optional: enforce non-negative
            if ( $other_deduction_val < 0 ) {
                $other_deduction_val = 0.0;
            }
            update_post_meta( $post_id, '_invoice_other_deduction_amount', $other_deduction_val );
        } else {
            // remove meta if explicitly empty (optional)
            delete_post_meta( $post_id, '_invoice_other_deduction_amount' );
        }

        // Compute & save total deductions (server-side authoritative)
        $deduction_keys = [
            '_invoice_retention_amount',
            '_invoice_gst_withheld',
            '_invoice_tds_amount',
            '_invoice_gst_tds_amount',
            '_invoice_bocw_amount',
            '_invoice_low_depth_deduction_amount',
            '_invoice_liquidated_damages_amount',
            '_invoice_sla_penalty_amount',
            '_invoice_penalty_amount',
            '_invoice_other_deduction_amount',
        ];

        $total_deduction_calc = 0.0;
        foreach ( $deduction_keys as $k ) {
            $val = floatval( get_post_meta( $post_id, $k, true ) ?: 0 );
            $total_deduction_calc += $val;
        }
        // round to 2 decimals
        $total_deduction_calc = round( $total_deduction_calc, 2 );

        update_post_meta( $post_id, '_invoice_total_deduction_amount', $total_deduction_calc );

        // Compute & save net payable (server-side authoritative)
        $stored_total_amount = floatval( get_post_meta( $post_id, '_invoice_total_amount', true ) ?: 0 );
        $stored_total_deduction = floatval( get_post_meta( $post_id, '_invoice_total_deduction_amount', true ) ?: 0 );

        $net_payable_calc = round( $stored_total_amount - $stored_total_deduction, 2 );

        // If you want to avoid negative net values, clamp to 0:
        // $net_payable_calc = max(0, $net_payable_calc);

        update_post_meta( $post_id, '_invoice_net_payable_amount', $net_payable_calc );

        // Read posted "Amount Paid by Client" if present
        $paid_raw = isset( $_POST['_invoice_amount_paid'] ) ? wp_unslash( $_POST['_invoice_amount_paid'] ) : null;

        if ( $paid_raw !== null && $paid_raw !== '' ) {
            $paid = floatval( $paid_raw );
            // optional: enforce non-negative
            if ( $paid < 0 ) {
                $paid = 0.0;
            }
            update_post_meta( $post_id, '_invoice_amount_paid', $paid );
        } else {
            // remove meta if explicitly empty (optional)
            delete_post_meta( $post_id, '_invoice_amount_paid' );
        }

        // Compute & save balance (server-side authoritative)
        // Prefer freshly stored meta (fallbacks to 0)
        $stored_net      = floatval( get_post_meta( $post_id, '_invoice_net_payable_amount', true ) ?: 0 );
        $stored_paid     = floatval( get_post_meta( $post_id, '_invoice_amount_paid', true ) ?: 0 );

        $balance_calc = round( $stored_net - $stored_paid, 2 );
        // optional: clamp to zero if you don't want negative balances:
        // $balance_calc = max( 0, $balance_calc );

        update_post_meta( $post_id, '_invoice_balance_amount', $balance_calc );

        // Validate & save invoice status (allow only known values)
        $allowed_status = [ 'pending', 'paid', 'cancel', 'credit_note_issued' ];
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

        // ---- Mode of Project (Back to Back or Direct) ----
        $allowed_modes = [ 'back_to_back', 'direct' ];
        $mode_raw = isset( $_POST['_ims_project_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['_ims_project_mode'] ) ) : '';
        if ( in_array( $mode_raw, $allowed_modes, true ) ) {
            update_post_meta( $post_id, '_ims_project_mode', $mode_raw );
        } else {
            // remove if invalid/empty
            delete_post_meta( $post_id, '_ims_project_mode' );
        }
    }


    public function toggle_payment_date_callback() {
        // This can be used if you want: right now JS toggles the field.
        wp_send_json_success();
    }
}

// Initialize
Metaboxes::instance()->init();

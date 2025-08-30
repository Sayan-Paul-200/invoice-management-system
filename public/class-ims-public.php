<?php
namespace IMS;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * PublicDisplay
 *
 * Renders the front-end invoice form and handles submission.
 */
class PublicDisplay {
    /** @var PublicDisplay|null */
    private static $instance = null;

    public static function instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init() {
        // Shortcode to render form
        add_shortcode( 'ims_invoice_form', [ $this, 'shortcode_invoice_form' ] );

        // Enqueue public assets
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );

        // Handle form submissions via admin-post.php
        add_action( 'admin_post_ims_submit_invoice', [ $this, 'handle_submission' ] );
        add_action( 'admin_post_nopriv_ims_submit_invoice', [ $this, 'handle_submission' ] );
    }

    /**
     * Enqueue public JS/CSS (CSS/JS are expected to be in public/css/ and public/js/)
     */
    public function enqueue_assets() {
        if ( ! defined( 'IMS_URL' ) || ! defined( 'IMS_VERSION' ) ) {
            return;
        }

        // Flatpickr (CDN)
        wp_register_style( 'flatpickr-css', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', [], '4.6.13' );
        wp_enqueue_style( 'flatpickr-css' );
        wp_register_script( 'flatpickr-js', 'https://cdn.jsdelivr.net/npm/flatpickr', [], '4.6.13', true );
        wp_enqueue_script( 'flatpickr-js' );

        // CSS
        $css_rel  = 'public/css/ims-public.css';
        $css_path = IMS_PATH . $css_rel;
        $css_url  = IMS_URL  . $css_rel;
        if ( file_exists( $css_path ) ) {
            wp_register_style( 'ims-public-css', $css_url, [], IMS_VERSION );
            wp_enqueue_style( 'ims-public-css' );
        } else {
            // fallback: register an empty handle so inline styles can be attached if needed
            wp_register_style( 'ims-public-css', false, [], IMS_VERSION );
            wp_enqueue_style( 'ims-public-css' );
            error_log( 'IMS: public CSS missing at ' . $css_path );
        }

        // JS (depends on flatpickr)
        $js_rel  = 'public/js/ims-public.js';
        $js_path = IMS_PATH . $js_rel;
        $js_url  = IMS_URL  . $js_rel;
        if ( file_exists( $js_path ) ) {
            wp_register_script( 'ims-public-js', $js_url, [ 'jquery', 'flatpickr-js' ], IMS_VERSION, true );
            wp_enqueue_script( 'ims-public-js' );
        } else {
            wp_register_script( 'ims-public-js', false, [ 'jquery', 'flatpickr-js' ], IMS_VERSION, true );
            wp_enqueue_script( 'ims-public-js' );
            error_log( 'IMS: public JS missing at ' . $js_path );
        }

        // Localize runtime data for JS
        $local = [
            'ajax_url'      => admin_url( 'admin-ajax.php' ),
            'max_file_size' => 5 * 1024 * 1024, // 5MB
            'i18n' => [
                'file_too_large' => __( 'File is too large. Maximum 5MB allowed.', 'invoice-management-system' ),
                'invalid_email'  => __( 'Please provide a valid email address.', 'invoice-management-system' ),
            ],
        ];
        wp_localize_script( 'ims-public-js', 'imsPublic', $local );
    }

    /**
     * Shortcode handler [ims_invoice_form]
     */
    public function shortcode_invoice_form( $atts = [] ) {
        $atts = shortcode_atts( [
            'invoice_id' => isset( $_GET['invoice_id'] ) ? absint( $_GET['invoice_id'] ) : 0,
        ], $atts, 'ims_invoice_form' );

        $msg_html = '';
        if ( isset( $_GET['ims_msg'] ) ) {
            $m = wp_unslash( $_GET['ims_msg'] );
            $m = urldecode( $m );
            if ( strpos( $m, 'success:' ) === 0 ) {
                $text = substr( $m, strlen( 'success:' ) );
                $msg_html = '<div class="ims-success">' . esc_html( $text ) . '</div>';
            } else {
                $msg_html = '<div class="ims-error">' . esc_html( $m ) . '</div>';
            }
        }

        $invoice_id = intval( $atts['invoice_id'] );
        $data = $this->get_prefill_data( $invoice_id );

        if ( ! is_user_logged_in() ) {
            $login_url = wp_login_url( get_permalink() );
            return '<div class="ims-form"><p>' . esc_html__( 'You must be logged in to submit or edit invoices.', 'invoice-management-system' ) . ' <a href="' . esc_url( $login_url ) . '">' . esc_html__( 'Log in', 'invoice-management-system' ) . '</a></p></div>';
        }

        ob_start();
        ?>
        <div class="ims-form">
            <?php echo $msg_html; ?>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" id="ims-invoice-form" novalidate>
                <?php wp_nonce_field( 'ims_public_invoice_nonce', 'ims_public_invoice_nonce' ); ?>
                <input type="hidden" name="action" value="ims_submit_invoice" />
                <input type="hidden" name="post_id" value="<?php echo esc_attr( $invoice_id ); ?>" />

                <!-- Row 1 -->
                <div class="ims-row">
                    <div class="ims-col">
                        <label for="_ims_project"><?php esc_html_e( 'Project', 'invoice-management-system' ); ?></label>
                        <select id="_ims_project" name="_ims_project" required>
                            <option value=""><?php esc_html_e( '-- Select Project --', 'invoice-management-system' ); ?></option>
                            <?php foreach ( $this->get_projects() as $proj ) : ?>
                                <option value="<?php echo esc_attr( $proj ); ?>" <?php selected( $data['_ims_project'], $proj ); ?>><?php echo esc_html( $proj ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="ims-col">
                        <label for="_ims_project_mode"><?php esc_html_e( 'Mode of Project', 'invoice-management-system' ); ?></label>
                        <select id="_ims_project_mode" name="_ims_project_mode" required>
                            <option value=""><?php esc_html_e( '-- Select Mode --', 'invoice-management-system' ); ?></option>
                            <?php foreach ( $this->get_modes() as $val => $label ) : ?>
                                <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $data['_ims_project_mode'], $val ); ?>><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Row 2 -->
                <div class="ims-row">
                    <div class="ims-col">
                        <label for="_ims_location"><?php esc_html_e( 'State', 'invoice-management-system' ); ?></label>
                        <select id="_ims_location" name="_ims_location" required>
                            <option value=""><?php esc_html_e( '-- Select State --', 'invoice-management-system' ); ?></option>
                            <?php foreach ( $this->get_locations() as $loc ) : ?>
                                <option value="<?php echo esc_attr( $loc ); ?>" <?php selected( $data['_ims_location'], $loc ); ?>><?php echo esc_html( $loc ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="ims-col">
                        <label for="_invoice_bill_category"><?php esc_html_e( 'Bill Category', 'invoice-management-system' ); ?></label>
                        <select id="_invoice_bill_category" name="_invoice_bill_category" required>
                            <option value=""><?php esc_html_e( '-- Select Category --', 'invoice-management-system' ); ?></option>
                            <?php foreach ( $this->get_bill_categories() as $val => $label ) : ?>
                                <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $data['_invoice_bill_category'], $val ); ?>><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Row 3 -->
                <div class="ims-row">
                    <div class="ims-col">
                        <label for="_invoice_milestone"><?php esc_html_e( 'Milestone', 'invoice-management-system' ); ?></label>
                        <select id="_invoice_milestone" name="_invoice_milestone">
                            <?php foreach ( $this->get_milestone_options() as $val => $label ) : ?>
                                <option value="<?php echo esc_attr( $val ); ?>" <?php selected( (string) $data['_invoice_milestone'], (string) $val ); ?>><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="ims-col">
                        <label for="post_title"><?php esc_html_e( 'Invoice Number', 'invoice-management-system' ); ?></label>
                        <input type="text" id="post_title" name="post_title" value="<?php echo esc_attr( $data['post_title'] ); ?>" required />
                    </div>
                </div>

                <!-- Row 4: dates -->
                <div class="ims-row">
                    <div class="ims-col">
                        <label for="_invoice_date"><?php esc_html_e( 'Invoice Date', 'invoice-management-system' ); ?></label>
                        <input type="text" id="_invoice_date" name="_invoice_date" value="<?php echo esc_attr( $data['_invoice_date'] ); ?>" required autocomplete="off" />
                    </div>

                    <div class="ims-col">
                        <label for="_submission_date"><?php esc_html_e( 'Submission Date', 'invoice-management-system' ); ?></label>
                        <input type="text" id="_submission_date" name="_submission_date" value="<?php echo esc_attr( $data['_submission_date'] ); ?>" required autocomplete="off" />
                    </div>
                </div>

                <!-- Row 5: basic / gst percent -->
                <div class="ims-row">
                    <div class="ims-col">
                        <label for="_invoice_basic_amount"><?php esc_html_e( 'Invoice Basic Amount', 'invoice-management-system' ); ?></label>
                        <div class="ims-suffix">
                            <input type="number" step="0.01" id="_invoice_basic_amount" name="_invoice_basic_amount" value="<?php echo esc_attr( $data['_invoice_basic_amount'] ); ?>" />
                            <span class="ims-sfx">₹</span>
                        </div>
                    </div>

                    <div class="ims-col">
                        <label for="_invoice_gst_percent"><?php esc_html_e( 'GST Percentage Applicable', 'invoice-management-system' ); ?></label>
                        <select id="_invoice_gst_percent" name="_invoice_gst_percent">
                            <?php foreach ( $this->get_gst_percent_options() as $val => $label ) : ?>
                                <option value="<?php echo esc_attr( $val ); ?>" <?php selected( (string) $data['_invoice_gst_percent'], (string) $val ); ?>><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Row 6: gst amount / total -->
                <div class="ims-row">
                    <div class="ims-col">
                        <label for="_invoice_gst_amount"><?php esc_html_e( 'Invoice GST Amount', 'invoice-management-system' ); ?></label>
                        <div class="ims-suffix">
                            <input type="text" readonly id="_invoice_gst_amount" name="_invoice_gst_amount" value="<?php echo esc_attr( $data['_invoice_gst_amount'] ); ?>" />
                            <span class="ims-sfx">₹</span>
                        </div>
                    </div>

                    <div class="ims-col">
                        <label for="_invoice_total_amount"><?php esc_html_e( 'Total Amount', 'invoice-management-system' ); ?></label>
                        <div class="ims-suffix">
                            <input type="text" readonly id="_invoice_total_amount" name="_invoice_total_amount" value="<?php echo esc_attr( $data['_invoice_total_amount'] ); ?>" />
                            <span class="ims-sfx">₹</span>
                        </div>
                    </div>
                </div>

                <!-- Row 7 -->
                <div class="ims-row">
                    <div class="ims-col">
                        <label for="_invoice_client_amount"><?php esc_html_e( 'Passed Amount by Client', 'invoice-management-system' ); ?></label>
                        <div class="ims-suffix">
                            <input type="number" step="0.01" id="_invoice_client_amount" name="_invoice_client_amount" value="<?php echo esc_attr( $data['_invoice_client_amount'] ); ?>" />
                            <span class="ims-sfx">₹</span>
                        </div>
                    </div>

                    <div class="ims-col">
                        <label for="_invoice_retention_amount"><?php esc_html_e( 'Retention', 'invoice-management-system' ); ?></label>
                        <div class="ims-suffix">
                            <input type="number" step="0.01" id="_invoice_retention_amount" name="_invoice_retention_amount" value="<?php echo esc_attr( $data['_invoice_retention_amount'] ); ?>" />
                            <span class="ims-sfx">₹</span>
                        </div>
                    </div>
                </div>

                <!-- Row 8 -->
                <div class="ims-row">
                    <div class="ims-col">
                        <label for="_invoice_gst_withheld"><?php esc_html_e( 'GST Withheld', 'invoice-management-system' ); ?></label>
                        <div class="ims-suffix">
                            <input type="number" step="0.01" id="_invoice_gst_withheld" name="_invoice_gst_withheld" value="<?php echo esc_attr( $data['_invoice_gst_withheld'] ); ?>" />
                            <span class="ims-sfx">₹</span>
                        </div>
                    </div>

                    <div class="ims-col">
                        <label for="_invoice_tds_amount"><?php esc_html_e( 'TDS', 'invoice-management-system' ); ?></label>
                        <div class="ims-suffix">
                            <input type="number" step="0.01" id="_invoice_tds_amount" name="_invoice_tds_amount" value="<?php echo esc_attr( $data['_invoice_tds_amount'] ); ?>" />
                            <span class="ims-sfx">₹</span>
                        </div>
                    </div>
                </div>

                <!-- Row 9 -->
                <div class="ims-row">
                    <div class="ims-col">
                        <label for="_invoice_gst_tds_amount"><?php esc_html_e( 'GST TDS', 'invoice-management-system' ); ?></label>
                        <div class="ims-suffix">
                            <input type="number" step="0.01" id="_invoice_gst_tds_amount" name="_invoice_gst_tds_amount" value="<?php echo esc_attr( $data['_invoice_gst_tds_amount'] ); ?>" />
                            <span class="ims-sfx">₹</span>
                        </div>
                    </div>

                    <div class="ims-col">
                        <label for="_invoice_bocw_amount"><?php esc_html_e( 'BOCW', 'invoice-management-system' ); ?></label>
                        <div class="ims-suffix">
                            <input type="number" step="0.01" id="_invoice_bocw_amount" name="_invoice_bocw_amount" value="<?php echo esc_attr( $data['_invoice_bocw_amount'] ); ?>" />
                            <span class="ims-sfx">₹</span>
                        </div>
                    </div>
                </div>

                <!-- Row 10 -->
                <div class="ims-row">
                    <div class="ims-col">
                        <label for="_invoice_low_depth_deduction_amount"><?php esc_html_e( 'Low Depth Deduction', 'invoice-management-system' ); ?></label>
                        <div class="ims-suffix">
                            <input type="number" step="0.01" id="_invoice_low_depth_deduction_amount" name="_invoice_low_depth_deduction_amount" value="<?php echo esc_attr( $data['_invoice_low_depth_deduction_amount'] ); ?>" />
                            <span class="ims-sfx">₹</span>
                        </div>
                    </div>

                    <div class="ims-col">
                        <label for="_invoice_liquidated_damages_amount"><?php esc_html_e( 'LD', 'invoice-management-system' ); ?></label>
                        <div class="ims-suffix">
                            <input type="number" step="0.01" id="_invoice_liquidated_damages_amount" name="_invoice_liquidated_damages_amount" value="<?php echo esc_attr( $data['_invoice_liquidated_damages_amount'] ); ?>" />
                            <span class="ims-sfx">₹</span>
                        </div>
                    </div>
                </div>

                <!-- Row 11 -->
                <div class="ims-row">
                    <div class="ims-col">
                        <label for="_invoice_sla_penalty_amount"><?php esc_html_e( 'SLA Penalty', 'invoice-management-system' ); ?></label>
                        <div class="ims-suffix">
                            <input type="number" step="0.01" id="_invoice_sla_penalty_amount" name="_invoice_sla_penalty_amount" value="<?php echo esc_attr( $data['_invoice_sla_penalty_amount'] ); ?>" />
                            <span class="ims-sfx">₹</span>
                        </div>
                    </div>

                    <div class="ims-col">
                        <label for="_invoice_penalty_amount"><?php esc_html_e( 'Penalty', 'invoice-management-system' ); ?></label>
                        <div class="ims-suffix">
                            <input type="number" step="0.01" id="_invoice_penalty_amount" name="_invoice_penalty_amount" value="<?php echo esc_attr( $data['_invoice_penalty_amount'] ); ?>" />
                            <span class="ims-sfx">₹</span>
                        </div>
                    </div>
                </div>

                <!-- Row 12 -->
                <div class="ims-row">
                    <div class="ims-col">
                        <label for="_invoice_other_deduction_amount"><?php esc_html_e( 'Other Deduction', 'invoice-management-system' ); ?></label>
                        <div class="ims-suffix">
                            <input type="number" step="0.01" id="_invoice_other_deduction_amount" name="_invoice_other_deduction_amount" value="<?php echo esc_attr( $data['_invoice_other_deduction_amount'] ); ?>" />
                            <span class="ims-sfx">₹</span>
                        </div>
                    </div>

                    <div class="ims-col">
                        <label for="_invoice_total_deduction_amount"><?php esc_html_e( 'Total Deduction', 'invoice-management-system' ); ?></label>
                        <div class="ims-suffix">
                            <input type="text" readonly id="_invoice_total_deduction_amount" name="_invoice_total_deduction_amount" value="<?php echo esc_attr( $data['_invoice_total_deduction_amount'] ); ?>" />
                            <span class="ims-sfx">₹</span>
                        </div>
                    </div>
                </div>

                <!-- Row 13 -->
                <div class="ims-row">
                    <div class="ims-col">
                        <label for="_invoice_net_payable_amount"><?php esc_html_e( 'Net Payable', 'invoice-management-system' ); ?></label>
                        <div class="ims-suffix">
                            <input type="text" readonly id="_invoice_net_payable_amount" name="_invoice_net_payable_amount" value="<?php echo esc_attr( $data['_invoice_net_payable_amount'] ); ?>" />
                            <span class="ims-sfx">₹</span>
                        </div>
                    </div>

                    <div class="ims-col">
                        <label for="_invoice_status"><?php esc_html_e( 'Status', 'invoice-management-system' ); ?></label>
                        <select id="_invoice_status" name="_invoice_status">
                            <?php foreach ( $this->get_statuses() as $val => $label ) : ?>
                                <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $data['_invoice_status'], $val ); ?>><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Row 14 -->
                <div class="ims-row">
                    <div class="ims-col">
                        <label for="_invoice_amount_paid"><?php esc_html_e( 'Amount Paid By Client', 'invoice-management-system' ); ?></label>
                        <div class="ims-suffix">
                            <input type="number" step="0.01" id="_invoice_amount_paid" name="_invoice_amount_paid" value="<?php echo esc_attr( $data['_invoice_amount_paid'] ); ?>" />
                            <span class="ims-sfx">₹</span>
                        </div>
                    </div>

                    <div class="ims-col">
                        <label for="_payment_date"><?php esc_html_e( 'Payment Date', 'invoice-management-system' ); ?></label>
                        <!-- wrapper kept in layout; hidden by default -->
                        <div class="ims-suffix ims-hideable" id="payment-wrapper" style="visibility:hidden;">
                            <input type="text" id="_payment_date" name="_payment_date" value="<?php echo esc_attr( $data['_payment_date'] ); ?>" autocomplete="off" />
                        </div>
                    </div>
                </div>

                <!-- Row 15 -->
                <div class="ims-row">
                    <div class="ims-col">
                        <label for="_invoice_balance_amount"><?php esc_html_e( 'Balance', 'invoice-management-system' ); ?></label>
                        <div class="ims-suffix">
                            <input type="text" readonly step="0.01" id="_invoice_balance_amount" name="_invoice_balance_amount" value="<?php echo esc_attr( $data['_invoice_balance_amount'] ); ?>" />
                            <span class="ims-sfx">₹</span>
                        </div>
                    </div>

                    <div class="ims-col">
                        <label for="post_content"><?php esc_html_e( 'Remarks', 'invoice-management-system' ); ?></label>
                        <textarea id="post_content" name="post_content" rows="2"><?php echo esc_textarea( $data['post_content'] ); ?></textarea>
                    </div>
                </div>

                <!-- Row 16 -->
                <div class="ims-row">
                    <div class="ims-col">
                        <label for="_invoice_file_id"><?php esc_html_e( 'Invoice File', 'invoice-management-system' ); ?></label>
                        <input type="file" id="_invoice_file_id" name="_invoice_file" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.png,.jpg,.jpeg" />
                        <?php if ( ! empty( $data['_invoice_file_url'] ) ) : ?>
                            <div class="ims-note"><a href="<?php echo esc_url( $data['_invoice_file_url'] ); ?>" target="_blank"><?php esc_html_e( 'View existing file', 'invoice-management-system' ); ?></a></div>
                        <?php endif; ?>
                        <div class="ims-note"><?php esc_html_e( 'Max file size: 5MB. Allowed types: pdf, doc, docx, xls, xlsx, csv, png, jpg, jpeg', 'invoice-management-system' ); ?></div>
                    </div>

                    <div class="ims-col">
                        <label for="_to_email"><?php esc_html_e( 'To Email', 'invoice-management-system' ); ?></label>
                        <input type="email" id="_to_email" name="_to_email" value="<?php echo esc_attr( $data['_to_email'] ); ?>" />
                    </div>
                </div>

                <!-- Row 17 (CC repeater full-width pair with empty column kept) -->
                <div class="ims-row">
                    <div class="ims-col">
                        <label><?php esc_html_e( 'CC Emails', 'invoice-management-system' ); ?></label>
                        <div class="ims-cc-list" id="ims-cc-list">
                            <?php
                            $ccs = is_array( $data['_cc_emails'] ) ? $data['_cc_emails'] : ( $data['_cc_emails'] ? explode( ',', $data['_cc_emails'] ) : [] );
                            if ( empty( $ccs ) ) {
                                $ccs = [ '' ];
                            }
                            foreach ( $ccs as $c ) :
                                ?>
                                <p>
                                    <input type="email" name="_cc_emails[]" value="<?php echo esc_attr( trim( $c ) ); ?>" />
                                    <button class="ims-btn secondary ims-remove-cc" type="button" title="<?php esc_attr_e( 'Remove', 'invoice-management-system' ); ?>">&times;</button>
                                </p>
                            <?php endforeach; ?>
                        </div>
                        <button class="ims-btn" id="ims-add-cc" type="button"><?php esc_html_e( 'Add CC', 'invoice-management-system' ); ?></button>
                    </div>

                    <div class="ims-col">
                        <!-- empty intentionally to keep two-column layout clean; could contain helper or preview -->
                    </div>
                </div>

                <!-- Submit -->
                <div style="margin-top:14px;">
                    <button class="ims-btn" type="submit"><?php esc_html_e( 'Submit Invoice', 'invoice-management-system' ); ?></button>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /* -------------------------
     * Prefill / Save / Compute
     * ------------------------- */

    protected function get_prefill_data( $post_id = 0 ) {
        $defaults = [
            '_ims_project' => '',
            '_ims_project_mode' => '',
            '_ims_location' => '',
            '_invoice_bill_category' => '',
            '_invoice_milestone' => '0',
            'post_title' => '',
            '_invoice_date' => '',
            '_submission_date' => '',
            '_invoice_basic_amount' => '',
            '_invoice_gst_percent' => '0',
            '_invoice_gst_amount' => '',
            '_invoice_total_amount' => '',
            '_invoice_client_amount' => '',
            '_invoice_retention_amount' => '',
            '_invoice_gst_withheld' => '',
            '_invoice_tds_amount' => '',
            '_invoice_gst_tds_amount' => '',
            '_invoice_bocw_amount' => '',
            '_invoice_low_depth_deduction_amount' => '',
            '_invoice_liquidated_damages_amount' => '',
            '_invoice_sla_penalty_amount' => '',
            '_invoice_penalty_amount' => '',
            '_invoice_other_deduction_amount' => '',
            '_invoice_total_deduction_amount' => '',
            '_invoice_net_payable_amount' => '',
            '_invoice_status' => 'pending',
            '_invoice_amount_paid' => '',
            '_payment_date' => '',
            '_invoice_balance_amount' => '',
            'post_content' => '',
            '_invoice_file_id' => '',
            '_invoice_file_url' => '',
            '_to_email' => '',
            '_cc_emails' => [],
        ];

        if ( $post_id > 0 ) {
            $post = get_post( $post_id );
            if ( $post && $post->post_type === 'ac_invoice' ) {
                $defaults['post_title'] = $post->post_title;
                $defaults['post_content'] = $post->post_content;
                $meta_keys = array_keys( $defaults );
                foreach ( $meta_keys as $k ) {
                    if ( strpos( $k, '_' ) === 0 ) { // meta-like keys
                        $val = get_post_meta( $post_id, $k, true );
                        if ( $val !== '' && $val !== null ) {
                            $defaults[ $k ] = $val;
                        }
                    }
                }
                // specifically handle cc emails as array
                $defaults['_cc_emails'] = get_post_meta( $post_id, '_cc_emails', true ) ?: ( is_string( $defaults['_cc_emails'] ) ? explode( ',', $defaults['_cc_emails'] ) : [] );
            }
        }

        return $defaults;
    }

    public function handle_submission() {
        // Only accept POST
        if ( ! isset( $_POST ) || strtolower( $_SERVER['REQUEST_METHOD'] ) !== 'post' ) {
            $this->redirect_with_message( '', __( 'Invalid request method', 'invoice-management-system' ) );
        }

        // Nonce
        if ( ! isset( $_POST['ims_public_invoice_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['ims_public_invoice_nonce'] ), 'ims_public_invoice_nonce' ) ) {
            $this->redirect_with_message( '', __( 'Nonce verification failed', 'invoice-management-system' ) );
        }

        // Require logged in to submit. (Change if you want public submissions)
        if ( ! is_user_logged_in() ) {
            $this->redirect_with_message( '', __( 'You must be logged in to submit invoices.', 'invoice-management-system' ) );
        }

        $current_user = wp_get_current_user();

        // Gather inputs safely (unslash + sanitize)
        $post_id = isset( $_POST['post_id'] ) ? intval( wp_unslash( $_POST['post_id'] ) ) : 0;
        $title   = isset( $_POST['post_title'] ) ? sanitize_text_field( wp_unslash( $_POST['post_title'] ) ) : '';
        $content = isset( $_POST['post_content'] ) ? wp_kses_post( wp_unslash( $_POST['post_content'] ) ) : '';

        if ( empty( $title ) ) {
            $this->redirect_with_message( $post_id, __( 'Invoice Number (title) cannot be empty.', 'invoice-management-system' ) );
        }

        // Date validation
        $invoice_date    = isset( $_POST['_invoice_date'] ) ? sanitize_text_field( wp_unslash( $_POST['_invoice_date'] ) ) : '';
        $submission_date = isset( $_POST['_submission_date'] ) ? sanitize_text_field( wp_unslash( $_POST['_submission_date'] ) ) : '';
        if ( $invoice_date && $submission_date ) {
            $inv_ts = strtotime( $invoice_date );
            $sub_ts = strtotime( $submission_date );
            if ( $inv_ts === false || $sub_ts === false || $inv_ts > $sub_ts ) {
                $this->redirect_with_message( $post_id, __( 'Submission Date must be the same or later than Invoice Date.', 'invoice-management-system' ) );
            }
        }

        // Payment date rule
        $payment_date = isset( $_POST['_payment_date'] ) ? sanitize_text_field( wp_unslash( $_POST['_payment_date'] ) ) : '';
        if ( $payment_date && $submission_date ) {
            $pd_ts  = strtotime( $payment_date );
            $sub_ts = strtotime( $submission_date );
            if ( $pd_ts === false || $pd_ts < $sub_ts ) {
                $this->redirect_with_message( $post_id, __( 'Payment Date must be the same or later than Submission Date.', 'invoice-management-system' ) );
            }
        }

        // Create / update post (same as before)
        $is_update = false;
        if ( $post_id > 0 ) {
            $existing = get_post( $post_id );
            if ( $existing && $existing->post_type === 'ac_invoice' ) {
                if ( ! current_user_can( 'edit_post', $post_id ) ) {
                    $this->redirect_with_message( $post_id, __( 'You do not have permission to edit this invoice.', 'invoice-management-system' ) );
                }
                $is_update = true;
                $postarr = [
                    'ID'           => $post_id,
                    'post_title'   => $title,
                    'post_content' => $content,
                    'post_status'  => 'publish',
                ];
                $result = wp_update_post( $postarr, true );
                if ( is_wp_error( $result ) ) {
                    $this->redirect_with_message( $post_id, $result->get_error_message() );
                }
            } else {
                $post_id = 0;
            }
        }

        if ( $post_id === 0 ) {
            $postarr = [
                'post_title'   => $title,
                'post_content' => $content,
                'post_status'  => 'publish',
                'post_type'    => 'ac_invoice',
                'post_author'  => $current_user->ID,
            ];
            $new_id = wp_insert_post( $postarr, true );
            if ( is_wp_error( $new_id ) ) {
                $this->redirect_with_message( 0, $new_id->get_error_message() );
            }
            $post_id = intval( $new_id );
        }

        // Save all metas (same logic)
        $this->save_meta_from_post( $post_id, $_POST );

        // Handle file upload if present
        if ( isset( $_FILES['_invoice_file'] ) && ! empty( $_FILES['_invoice_file']['name'] ) ) {
            $file = $_FILES['_invoice_file'];
            if ( isset( $file['error'] ) && intval( $file['error'] ) !== UPLOAD_ERR_OK ) {
                $this->redirect_with_message( $post_id, sprintf( __( 'File upload error (code %d)', 'invoice-management-system' ), intval( $file['error'] ) ) );
            }
            if ( isset( $file['size'] ) && intval( $file['size'] ) > 5 * 1024 * 1024 ) {
                $this->redirect_with_message( $post_id, __( 'Uploaded file exceeds 5MB limit.', 'invoice-management-system' ) );
            }

            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';

            $attach_id = media_handle_upload( '_invoice_file', $post_id );
            if ( is_wp_error( $attach_id ) ) {
                $this->redirect_with_message( $post_id, $attach_id->get_error_message() );
            } else {
                update_post_meta( $post_id, '_invoice_file_id', intval( $attach_id ) );
                $attach_url = wp_get_attachment_url( $attach_id );
                if ( $attach_url ) {
                    update_post_meta( $post_id, '_invoice_file_url', esc_url_raw( $attach_url ) );
                }
            }
        } else {
            // Ensure file url exists if id exists
            $existing_file_id = get_post_meta( $post_id, '_invoice_file_id', true );
            if ( $existing_file_id && ! get_post_meta( $post_id, '_invoice_file_url', true ) ) {
                $existing_url = wp_get_attachment_url( $existing_file_id );
                if ( $existing_url ) {
                    update_post_meta( $post_id, '_invoice_file_url', esc_url_raw( $existing_url ) );
                }
            }
        }

        // compute final server-side derived fields and save
        $this->compute_and_save_derived_amounts( $post_id );

        $this->redirect_with_message( $post_id, 'success:' . rawurlencode( __( 'Invoice saved successfully', 'invoice-management-system' ) ) );
    }

    protected function save_meta_from_post( $post_id, $raw_post ) {
        $get = function( $key ) use ( $raw_post ) {
            if ( ! isset( $raw_post[ $key ] ) ) return null;
            return wp_unslash( $raw_post[ $key ] );
        };

        $simple_texts = [
            '_ims_project', '_ims_project_mode', '_ims_location', '_invoice_bill_category',
            '_invoice_milestone', '_invoice_date', '_submission_date', '_invoice_status', '_payment_date'
        ];
        foreach ( $simple_texts as $k ) {
            $val = $get( $k );
            if ( $val === null || $val === '' ) {
                delete_post_meta( $post_id, $k );
            } else {
                update_post_meta( $post_id, $k, sanitize_text_field( $val ) );
            }
        }

        $numeric_fields = [
            '_invoice_basic_amount', '_invoice_gst_percent', '_invoice_gst_amount', '_invoice_total_amount',
            '_invoice_client_amount', '_invoice_retention_amount', '_invoice_gst_withheld', '_invoice_tds_amount',
            '_invoice_gst_tds_amount', '_invoice_bocw_amount', '_invoice_low_depth_deduction_amount',
            '_invoice_liquidated_damages_amount', '_invoice_sla_penalty_amount', '_invoice_penalty_amount',
            '_invoice_other_deduction_amount', '_invoice_total_deduction_amount', '_invoice_net_payable_amount',
            '_invoice_amount_paid', '_invoice_balance_amount'
        ];

        foreach ( $numeric_fields as $k ) {
            $val = $get( $k );
            if ( $val === null || $val === '' ) {
                delete_post_meta( $post_id, $k );
                continue;
            }
            $norm = str_replace( [ ',', ' ' ], '', (string) $val );
            $norm = preg_replace( '/[^\d\.\-]/', '', $norm );
            if ( $norm === '' || ! preg_match( '/^-?\d+(\.\d+)?$/', $norm ) ) {
                delete_post_meta( $post_id, $k );
                continue;
            }
            update_post_meta( $post_id, $k, floatval( $norm ) );
        }

        // Emails
        $to = $get( '_to_email' );
        if ( $to !== null ) {
            update_post_meta( $post_id, '_to_email', sanitize_email( $to ) );
        }

        $raw_cc = isset( $raw_post['_cc_emails'] ) ? (array) wp_unslash( $raw_post['_cc_emails'] ) : [];
        $cc_emails = array_filter( array_map( 'sanitize_email', array_map( 'trim', (array) $raw_cc ) ) );
        update_post_meta( $post_id, '_cc_emails', $cc_emails );
    }

    protected function compute_and_save_derived_amounts( $post_id ) {
        $basic   = floatval( get_post_meta( $post_id, '_invoice_basic_amount', true ) ?: 0 );
        $gst_pct = intval( get_post_meta( $post_id, '_invoice_gst_percent', true ) ?: 0 );
        $gst_pct = max( 0, min( 30, $gst_pct ) );
        $gst_amt = round( ( $basic * $gst_pct ) / 100, 2 );
        $total   = round( $basic + $gst_amt, 2 );

        update_post_meta( $post_id, '_invoice_gst_amount', $gst_amt );
        update_post_meta( $post_id, '_invoice_total_amount', $total );

        $ded_keys = [
            '_invoice_retention_amount', '_invoice_gst_withheld', '_invoice_tds_amount', '_invoice_gst_tds_amount',
            '_invoice_bocw_amount', '_invoice_low_depth_deduction_amount', '_invoice_liquidated_damages_amount',
            '_invoice_sla_penalty_amount', '_invoice_penalty_amount', '_invoice_other_deduction_amount'
        ];
        $total_ded = 0.0;
        foreach ( $ded_keys as $k ) {
            $total_ded += floatval( get_post_meta( $post_id, $k, true ) ?: 0 );
        }
        $total_ded = round( $total_ded, 2 );
        update_post_meta( $post_id, '_invoice_total_deduction_amount', $total_ded );

        $net = round( $total - $total_ded, 2 );
        update_post_meta( $post_id, '_invoice_net_payable_amount', $net );

        $paid = floatval( get_post_meta( $post_id, '_invoice_amount_paid', true ) ?: 0 );
        $balance = round( $net - $paid, 2 );
        update_post_meta( $post_id, '_invoice_balance_amount', $balance );
    }

    protected function redirect_with_message( $post_id = 0, $message = '' ) {
        $ref = wp_get_referer();
        if ( ! $ref ) {
            $ref = home_url();
        }
        $sep = ( strpos( $ref, '?' ) === false ) ? '?' : '&';
        $q = '?ims_msg=' . rawurlencode( $message );
        if ( $post_id ) {
            $q .= '&invoice_id=' . intval( $post_id );
        }
        wp_safe_redirect( $ref . $sep . ltrim( $q, '?' ) );
        exit;
    }

    /* --- Lists --- */
    protected function get_projects() {
        return [ 'NFS','GAIL','BGCL','STP','Bharat Net','NFS AMC' ];
    }
    protected function get_modes() {
        return [ 'back_to_back' => __( 'Back to Back', 'invoice-management-system' ), 'direct' => __( 'Direct', 'invoice-management-system' ) ];
    }
    protected function get_locations() {
        return [ 'Bihar','Delhi','Goa','Gujarat','Haryana','Jharkhand','Madhya Pradesh','Odisha','Port Blair','Rajasthan','Sikkim','Uttar Pradesh','West Bengal' ];
    }
    protected function get_bill_categories() {
        return [
            'service' => __( 'Service', 'invoice-management-system' ),
            'supply' => __( 'Supply', 'invoice-management-system' ),
            'row' => __( 'ROW', 'invoice-management-system' ),
            'amc' => __( 'AMC', 'invoice-management-system' ),
            'restoration_service' => __( 'Restoration Service', 'invoice-management-system' ),
            'restoration_supply' => __( 'Restoration Supply', 'invoice-management-system' ),
            'restoration_row' => __( 'Restoration Row', 'invoice-management-system' ),
            'spares' => __( 'Spares', 'invoice-management-system' ),
            'training' => __( 'Training', 'invoice-management-system' ),
        ];
    }
    protected function get_milestone_options() {
        return [ '0' => '0%', '20' => '20%', '40' => '40%', '60' => '60%', '80' => '80%', '100' => '100%' ];
    }
    protected function get_gst_percent_options() {
        return [ '0' => '0%', '5' => '5%', '12' => '12%', '18' => '18%', '28' => '28%' ];
    }
    protected function get_statuses() {
        return [ 'pending' => __( 'Pending', 'invoice-management-system' ), 'paid' => __( 'Paid', 'invoice-management-system' ), 'cancel' => __( 'Cancel', 'invoice-management-system' ), 'credit_note_issued' => __( 'Credit Note Issued', 'invoice-management-system' ) ];
    }
}

// Do not auto-init here; bootstrap should call PublicDisplay::instance()->init();
if ( class_exists( '\IMS\PublicDisplay' ) ) {
    // available for bootstrap
}

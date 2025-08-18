<?php
namespace IMS;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Shortcodes class (all shortcode registrations live here)
 */
class Shortcodes {
    /** @var Shortcodes|null */
    private static $instance = null;

    public static function instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register hooks
     */
    public function init() {
        add_shortcode( 'ims_invoices_table', [ $this, 'shortcode_invoices_table' ] );

        // Print inline JS on front-end edit page (populates Elementor form when invoice_id present)
        add_action( 'wp_footer', [ $this, 'maybe_print_invoice_population_script' ] );
    }

    /**
     * Shortcode: [ims_invoices_table]
     * Attributes:
     *   posts_per_page (int) default -1 (all)
     *
     * Shows a simple table of invoices ordered by post_modified (descending).
     */
    public function shortcode_invoices_table( $atts = [] ) {
        $atts = shortcode_atts( [
            'posts_per_page' => -1,
        ], $atts, 'ims_invoices_table' );

        // Make sure public CSS is available (only when shortcode present on page)
        if ( defined( 'IMS_URL' ) && defined( 'IMS_VERSION' ) ) {
            wp_enqueue_style( 'ims-public-css', IMS_URL . 'public/css/ims-public.css', [], IMS_VERSION );
        }

        $query_args = [
            'post_type'      => 'ac_invoice',
            'post_status'    => 'publish',
            'orderby'        => 'modified',
            'order'          => 'DESC',
            'posts_per_page' => intval( $atts['posts_per_page'] ),
        ];

        $q = new \WP_Query( $query_args );

        if ( ! $q->have_posts() ) {
            return '<p>' . esc_html__( 'No invoices found.', 'invoice-management-system' ) . '</p>';
        }

        $output  = '<div class="ims-invoices-shortcode-wrap">';
        $output .= '<table class="ims-invoices-table">';
        $output .= '<thead><tr>';
        $output .= '<th>' . esc_html__( 'Invoice', 'invoice-management-system' ) . '</th>';
        $output .= '<th>' . esc_html__( 'Invoice Date', 'invoice-management-system' ) . '</th>';
        $output .= '<th>' . esc_html__( 'Total Amount (₹)', 'invoice-management-system' ) . '</th>';
        $output .= '<th>' . esc_html__( 'Total Deductions (₹)', 'invoice-management-system' ) . '</th>';
        $output .= '<th>' . esc_html__( 'Net Payable (₹)', 'invoice-management-system' ) . '</th>';
        $output .= '<th>' . esc_html__( 'Amount Paid (₹)', 'invoice-management-system' ) . '</th>';
        $output .= '<th>' . esc_html__( 'Balance (₹)', 'invoice-management-system' ) . '</th>';
        // Action column
        $output .= '<th>' . esc_html__( 'Action', 'invoice-management-system' ) . '</th>';
        $output .= '<th>' . esc_html__( 'Updated', 'invoice-management-system' ) . '</th>';
        $output .= '</tr></thead><tbody>';

        while ( $q->have_posts() ) {
            $q->the_post();
            $post_id = get_the_ID();

            $title = get_the_title();

            // Build front-end edit page URL and add invoice_id param
            $frontend_edit_url = add_query_arg( 'invoice_id', $post_id, site_url( '/edit-invoice' ) );

            // fetch relevant meta (fall back to 0)
            $total_amount    = floatval( get_post_meta( $post_id, '_invoice_total_amount', true ) ?: 0 );
            $total_deduction = floatval( get_post_meta( $post_id, '_invoice_total_deduction_amount', true ) ?: 0 );
            $net_payable     = floatval( get_post_meta( $post_id, '_invoice_net_payable_amount', true ) ?: 0 );
            $amount_paid     = floatval( get_post_meta( $post_id, '_invoice_amount_paid', true ) ?: 0 );

            // compute balance on-the-fly: net_payable - amount_paid
            $balance = round( $net_payable - $amount_paid, 2 );

            // invoice date
            $inv_date_raw = get_post_meta( $post_id, '_invoice_date', true );
            $inv_date = $inv_date_raw ? esc_html( date_i18n( get_option( 'date_format' ), strtotime( $inv_date_raw ) ) ) : '&mdash;';

            $output .= '<tr>';

            // Title + front-end edit link (replaces internal edit link)
            $output .= '<td>';
            $output .= '<a href="' . esc_url( $frontend_edit_url ) . '">' . esc_html( $title ) . '</a>';
            $output .= '</td>';

            $output .= '<td>' . $inv_date . '</td>';

            // Number formatting using number_format_i18n for localization
            $output .= '<td class="ims-col-right">' . number_format_i18n( $total_amount, 2 ) . '</td>';
            $output .= '<td class="ims-col-right">' . number_format_i18n( $total_deduction, 2 ) . '</td>';
            $output .= '<td class="ims-col-right">' . number_format_i18n( $net_payable, 2 ) . '</td>';
            $output .= '<td class="ims-col-right">' . number_format_i18n( $amount_paid, 2 ) . '</td>';
            $output .= '<td class="ims-col-right">' . number_format_i18n( $balance, 2 ) . '</td>';

            // Action column (show edit button only for users who can edit this post) - link points to front-end page
            if ( current_user_can( 'edit_post', $post_id ) ) {
                $action_html = '<a class="ims-edit-button" href="' . esc_url( $frontend_edit_url ) . '">' . esc_html__( 'Edit', 'invoice-management-system' ) . '</a>';
            } else {
                $action_html = '&mdash;';
            }
            $output .= '<td class="ims-col-action">' . $action_html . '</td>';

            $output .= '<td>' . esc_html( get_the_modified_date() ) . '</td>';

            $output .= '</tr>';
        }

        wp_reset_postdata();

        $output .= '</tbody></table></div>';

        return $output;
    }

    /**
     * Print inline JS to populate Elementor form on front-end edit page.
     * This runs in wp_footer and only outputs when invoice_id query var is present.
     */
    public function maybe_print_invoice_population_script() {
        // Only on frontend (not admin)
        if ( is_admin() ) {
            return;
        }

        // invoice_id comes from query var ?invoice_id=123
        $invoice_id = isset( $_GET['invoice_id'] ) ? absint( $_GET['invoice_id'] ) : 0;
        if ( $invoice_id <= 0 ) {
            return;
        }

        // Ensure post exists and is invoice CPT
        $post = get_post( $invoice_id );
        if ( ! $post || $post->post_type !== 'ac_invoice' ) {
            return;
        }

        // Optional capability check: only show populate script if current user can edit the post.
        if ( ! current_user_can( 'edit_post', $invoice_id ) ) {
            return;
        }

        // Collect meta values (sanitize for output)
        $data = [
            // text fields
            'invoice_id'            => get_the_title( $invoice_id ),
            'bill_category'         => get_post_meta( $invoice_id, '_invoice_bill_category', true ),
            'invoice_date'          => get_post_meta( $invoice_id, '_invoice_date', true ),
            'submission_date'       => get_post_meta( $invoice_id, '_submission_date', true ),
            'milestone'             => get_post_meta( $invoice_id, '_invoice_milestone', true ),
            'basic_amount'          => get_post_meta( $invoice_id, '_invoice_basic_amount', true ),
            'gst_pct'               => get_post_meta( $invoice_id, '_invoice_gst_percent', true ),
            'passed_client'         => get_post_meta( $invoice_id, '_invoice_client_amount', true ),
            'retention_amount'      => get_post_meta( $invoice_id, '_invoice_retention_amount', true ),
            'gst_withheld'          => get_post_meta( $invoice_id, '_invoice_gst_withheld', true ),
            'tds_amount'            => get_post_meta( $invoice_id, '_invoice_tds_amount', true ),
            'gst_tds_amount'        => get_post_meta( $invoice_id, '_invoice_gst_tds_amount', true ),
            'bocw_amount'           => get_post_meta( $invoice_id, '_invoice_bocw_amount', true ),
            'low_depth_deduction'   => get_post_meta( $invoice_id, '_invoice_low_depth_deduction_amount', true ),
            'liquidated_damages'    => get_post_meta( $invoice_id, '_invoice_liquidated_damages_amount', true ),
            'sla_penalty'           => get_post_meta( $invoice_id, '_invoice_sla_penalty_amount', true ),
            'penalty_amount'        => get_post_meta( $invoice_id, '_invoice_penalty_amount', true ),
            'other_deduction'       => get_post_meta( $invoice_id, '_invoice_other_deduction_amount', true ),
            'amount_paid_by_client' => get_post_meta( $invoice_id, '_invoice_amount_paid', true ),
            'invoice_status'        => get_post_meta( $invoice_id, '_invoice_status', true ),
            'payment_date'          => get_post_meta( $invoice_id, '_payment_date', true ),
            'to_email'              => get_post_meta( $invoice_id, '_to_email', true ),
            'cc_emails'             => get_post_meta( $invoice_id, '_cc_emails', true ),
            'invoice_location'      => get_post_meta( $invoice_id, '_ims_location', true ),
            'invoice_project'       => get_post_meta( $invoice_id, '_ims_project', true ),
            'project_mode'          => get_post_meta( $invoice_id, '_ims_project_mode', true ),
        ];

        if ( is_array( $data['cc_emails'] ) ) {
            $data['cc_emails'] = implode( ', ', array_map( 'sanitize_email', $data['cc_emails'] ) );
        }

        $json_data = wp_json_encode( $data );
        if ( false === $json_data ) {
            return;
        }

        ?>
        <script type="text/javascript">
        (function(){
            var data = <?php echo $json_data; ?>;

            function onReady(fn){
                if (document.readyState !== 'loading'){
                    fn();
                } else {
                    document.addEventListener('DOMContentLoaded', fn);
                }
            }

            onReady(function(){
                // setField returns true when it believes it updated the control
                function setField(name, value){
                    try {
                        var selector = '[name="form_fields[' + name + ']"]';
                        var el = document.querySelector(selector);
                        if (!el) return false;

                        // Range slider hidden input (Ion.RangeSlider / irs)
                        if (el.classList && el.classList.contains('irs-hidden-input')) {
                            var num = Number(value);
                            if (isNaN(num)) num = 0;
                            var min = Number(el.getAttribute('data-min') || 0);
                            var max = Number(el.getAttribute('data-max') || 100);
                            if (max === min) max = min + 100;
                            num = Math.max(min, Math.min(max, num));

                            // Try jQuery ionRangeSlider API first
                            if (window.jQuery) {
                                try {
                                    var $el = window.jQuery(el);
                                    var inst = $el.data('ionRangeSlider') || $el.data('ionrangeslider') || null;
                                    if (inst && typeof inst.update === 'function') {
                                        inst.update({ from: num });
                                        el.value = String(num);
                                        el.dispatchEvent(new Event('input', { bubbles: true }));
                                        el.dispatchEvent(new Event('change', { bubbles: true }));
                                        return true;
                                    }
                                    // fallback to plugin method
                                    if (typeof $el.ionRangeSlider === 'function') {
                                        try {
                                            $el.ionRangeSlider('update', { from: num });
                                            el.value = String(num);
                                            el.dispatchEvent(new Event('input', { bubbles: true }));
                                            el.dispatchEvent(new Event('change', { bubbles: true }));
                                            return true;
                                        } catch(e){}
                                    }
                                } catch(e){}
                            }

                            // Fallback: update hidden input and visible .irs markup directly
                            try {
                                el.value = String(num);

                                // visible wrapper is usually previous sibling <span class="irs"> in Elementor markup
                                var wrapper = el.previousElementSibling;
                                if (wrapper && wrapper.classList && wrapper.classList.contains('irs')) {
                                    var percent = ((num - min) / (max - min)) * 100;
                                    if (percent < 0) percent = 0;
                                    if (percent > 100) percent = 100;

                                    var single = wrapper.querySelector('.irs-single');
                                    var handle = wrapper.querySelector('.irs-handle');
                                    var bar = wrapper.querySelector('.irs-bar');

                                    if (single) {
                                        var postfix = el.getAttribute('data-postfix') || '';
                                        single.innerText = String(num) + (postfix ? postfix : '');
                                        single.style.left = percent + '%';
                                    }
                                    if (handle) handle.style.left = percent + '%';
                                    if (bar) bar.style.width = percent + '%';
                                }

                                el.dispatchEvent(new Event('input', { bubbles: true }));
                                el.dispatchEvent(new Event('change', { bubbles: true }));
                                return true;
                            } catch(e){
                                return false;
                            }
                        }

                        // Selects
                        if (el.tagName.toLowerCase() === 'select') {
                            // if option exists, set; otherwise leave blank
                            el.value = (value === null || typeof value === 'undefined') ? '' : value;
                            el.dispatchEvent(new Event('change', { bubbles: true }));
                            return true;
                        }

                        // Generic inputs (text, email, date, hidden, textarea)
                        if ('value' in el) {
                            el.value = (value === null || typeof value === 'undefined') ? '' : value;
                            el.dispatchEvent(new Event('input', { bubbles: true }));
                            el.dispatchEvent(new Event('change', { bubbles: true }));
                            return true;
                        }

                        return false;
                    } catch(e){
                        return false;
                    }
                }

                var mapping = {
                    'invoice_id': 'invoice_id',
                    'bill_category': 'bill_category',
                    'invoice_date': 'invoice_date',
                    'submission_date': 'submission_date',
                    'milestone': 'milestone',
                    'basic_amount': 'basic_amount',
                    'gst_pct': 'gst_pct',
                    'passed_client': 'passed_client',
                    'retention_amount': 'retention_amount',
                    'gst_withheld': 'gst_withheld',
                    'tds_amount': 'tds_amount',
                    'gst_tds_amount': 'gst_tds_amount',
                    'bocw_amount': 'bocw_amount',
                    'low_depth_deduction': 'low_depth_deduction',
                    'liquidated_damages': 'liquidated_damages',
                    'sla_penalty': 'sla_penalty',
                    'penalty_amount': 'penalty_amount',
                    'other_deduction': 'other_deduction',
                    'amount_paid_by_client': 'amount_paid_by_client',
                    'invoice_status': 'invoice_status',
                    'payment_date': 'payment_date',
                    'to_email': 'to_email',
                    'cc_emails': 'cc_emails',
                    'invoice_location': 'invoice_location',
                    'invoice_project': 'invoice_project',
                    'project_mode': 'project_mode'
                };

                var requiredKeys = Object.keys(mapping);
                var attempts = 0;
                var maxAttempts = 10;
                var intervalMs = 250;

                var timer = setInterval(function(){
                    attempts++;
                    var successCount = 0;
                    for (var k in mapping) {
                        if (!mapping.hasOwnProperty(k)) continue;
                        var fname = mapping[k];
                        var val = data[k];
                        if (Array.isArray(val)) val = val.join(', ');
                        if (val === null || typeof val === 'undefined') continue;

                        // try to set
                        var ok = setField(fname, String(val));
                        if (ok) successCount++;
                    }

                    // If everything that we had data for was set, stop retrying
                    // (we count successes >= number of keys we had data for)
                    var keysWithData = requiredKeys.filter(function(k){ return (data[k] !== null && typeof data[k] !== 'undefined'); }).length;
                    if (successCount >= keysWithData || attempts >= maxAttempts) {
                        clearInterval(timer);
                    }
                }, intervalMs);

            });
        })();
        </script>
        <?php
    }

}

// initialize
Shortcodes::instance()->init();

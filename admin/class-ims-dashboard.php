<?php
namespace IMS;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * AdminDashboard
 * Creates the Invoices Dashboard admin page with various charts.
 */
class AdminDashboard {
    /** Singleton instance */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return AdminDashboard
     */
    public static function instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Hook into WP admin
     */
    public function init() {
        add_action( 'admin_menu',          [ $this, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    /**
     * Register Dashboard submenu under Invoices
     */
    public function register_menu() {
        add_submenu_page(
            'edit.php?post_type=invoice',
            __( 'Invoices Dashboard', 'invoice-management-system' ),
            __( 'Dashboard',           'invoice-management-system' ),
            'manage_options',
            'invoice-dashboard',
            [ $this, 'render_dashboard_page' ]
        );
    }

    /**
     * Enqueue Chart.js and dashboard data
     */
    public function enqueue_assets( $hook ) {
        if ( $hook !== 'invoice_page_invoice-dashboard' ) {
            return;
        }
        wp_enqueue_script( 'chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', [], '3.9.1', true );
        wp_enqueue_script( 'ims-dashboard-js', IMS_URL . 'admin/js/ims-dashboard.js', [ 'chartjs' ], IMS_VERSION, true );
        wp_localize_script( 'ims-dashboard-js', 'imsDashboardData', [
            'statusRatio'      => $this->get_status_ratio(),
            'amountByStatus'   => $this->get_amount_by_status(),
            'amountTimeline'   => $this->get_amount_timeline(),
            'countByMonth'     => $this->get_count_by_month(),
            'projectRatio'     => $this->get_taxonomy_ratio( 'project' ),
            'locationRatio'    => $this->get_taxonomy_ratio( 'location' ),
            'countByProject'   => $this->get_count_by_tax_status( 'project' ),
            'countByLocation'  => $this->get_count_by_tax_status( 'location' ),
        ] );
    }

    /**
     * Render the Dashboard page
     */
    public function render_dashboard_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Invoices Dashboard', 'invoice-management-system' ); ?></h1>
            <div id="ims-dashboard-charts">
                <canvas id="chart-status-ratio"></canvas>
                <canvas id="chart-amount-status"></canvas>
                <canvas id="chart-amount-timeline"></canvas>
                <canvas id="chart-count-month"></canvas>
                <canvas id="chart-project-ratio"></canvas>
                <canvas id="chart-location-ratio"></canvas>
                <canvas id="chart-count-project"></canvas>
                <canvas id="chart-count-location"></canvas>
            </div>
        </div>
        <?php
    }

    /**
     * Stub: ratio of invoices by status
     */
    private function get_status_ratio() {
        $statuses = ['pending','paid','cancel'];
        $data     = [];

        foreach ( $statuses as $status ) {
            $q = new \WP_Query([
                'post_type'      => 'ac_invoice',
                'post_status'    => 'publish',
                'meta_key'       => '_invoice_status',
                'meta_value'     => $status,
                'fields'         => 'ids',
                'posts_per_page' => -1,
            ]);
            $data[] = $q->found_posts;
        }

        return [
            'labels' => array_map( 'ucfirst', $statuses ),
            'data'   => $data,
        ];
    }

    /**
     * Stub: distribution of invoice amount by status
     */
    private function get_amount_by_status() {
        global $wpdb;
        $statuses = ['pending','paid','cancel'];
        $data     = [];

        foreach ( $statuses as $status ) {
            $sum = $wpdb->get_var( $wpdb->prepare("
                SELECT SUM( CAST(meta_value AS DECIMAL(10,2)) )
                FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->posts} p ON p.ID=pm.post_id
                WHERE pm.meta_key=%s
                AND p.post_type='ac_invoice'
                AND p.post_status='publish'
            ", '_invoice_amount' ) );
            // But filter by status:
            if ( $status ) {
                $sum = $wpdb->get_var( $wpdb->prepare("
                    SELECT SUM( CAST(amount.meta_value AS DECIMAL(10,2)) )
                    FROM {$wpdb->postmeta} amount
                    JOIN {$wpdb->posts} p ON p.ID=amount.post_id
                    JOIN {$wpdb->postmeta} stat ON stat.post_id=p.ID
                    WHERE amount.meta_key=%s
                    AND stat.meta_key=%s
                    AND stat.meta_value=%s
                    AND p.post_type='ac_invoice'
                    AND p.post_status='publish'
                ", '_invoice_amount', '_invoice_status', $status ) );
            }
            $data[] = (float) $sum;
        }

        return [
            'labels' => array_map( 'ucfirst', $statuses ),
            'data'   => $data,
        ];
    }

    /**
     * Stub: distribution of invoice amounts over months
     */
    private function get_amount_timeline() {
        global $wpdb;
        // Get sums per month
        $rows = $wpdb->get_results("
        SELECT DATE_FORMAT(meta_value, '%Y-%m') AS ym,
                SUM(CAST(amount.meta_value AS DECIMAL(10,2))) AS total
        FROM {$wpdb->postmeta} date
        JOIN {$wpdb->postmeta} amount ON amount.post_id=date.post_id
        JOIN {$wpdb->posts} p ON p.ID=date.post_id
        WHERE date.meta_key='_invoice_date'
            AND amount.meta_key='_invoice_amount'
            AND p.post_type='ac_invoice'
            AND p.post_status='publish'
        GROUP BY ym
        ORDER BY ym ASC
        ");
        $labels = []; $data = [];
        foreach ( $rows as $r ) {
        $labels[] = $r->ym;
        $data[]   = (float) $r->total;
        }

        return [ 'labels' => $labels, 'data' => $data ];
    }

    /**
     * Stub: count of invoices per status per month
     */
    private function get_count_by_month() {
        global $wpdb;
        // Fetch distinct months
        $months = $wpdb->get_col("
        SELECT DISTINCT DATE_FORMAT(meta_value,'%Y-%m') 
        FROM {$wpdb->postmeta} 
        WHERE meta_key='_invoice_date'
        ORDER BY 1
        ");
        $statuses = ['pending','paid','cancel'];
        $series = [];

        foreach ( $statuses as $status ) {
        $counts = [];
        foreach ( $months as $m ) {
            $cnt = $wpdb->get_var( $wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} d ON d.post_id=p.ID AND d.meta_key='_invoice_date' AND DATE_FORMAT(d.meta_value,'%%Y-%%m')=%s
            JOIN {$wpdb->postmeta} s ON s.post_id=p.ID AND s.meta_key='_invoice_status' AND s.meta_value=%s
            WHERE p.post_type='ac_invoice' AND p.post_status='publish'
            ", $m, $status ) );
            $counts[] = (int) $cnt;
        }
        $series[] = [
            'name' => ucfirst($status),
            'data' => $counts
        ];
        }

        return [ 'labels' => $months, 'series' => $series ];
    }

    /**
     * Stub: ratio of invoices across a taxonomy
     */
    private function get_taxonomy_ratio( $tax ) {
        $terms = get_terms([
        'taxonomy'   => $tax,
        'hide_empty' => false,
        ]);
        $labels = []; $data = [];
        foreach ( $terms as $term ) {
        $labels[] = $term->name;
        $data[]   = (int) $term->count;
        }
        return [ 'labels' => $labels, 'data' => $data ];
    }

    /**
     * Stub: count of invoices per status per taxonomy term
     */
    private function get_count_by_tax_status( $tax ) {
        $terms = get_terms([
        'taxonomy'   => $tax,
        'hide_empty' => false,
        ]);
        $statuses = ['pending','paid','cancel'];
        $labels   = wp_list_pluck( $terms, 'name' );
        $series   = [];

        foreach ( $statuses as $status ) {
        $counts = [];
        foreach ( $terms as $term ) {
            $q = new \WP_Query([
            'post_type'      => 'ac_invoice',
            'post_status'    => 'publish',
            'tax_query'      => [[
                'taxonomy' => $tax,
                'terms'    => $term->term_id,
            ]],
            'meta_query'     => [[
                'key'   => '_invoice_status',
                'value' => $status,
            ]],
            'fields'         => 'ids',
            'posts_per_page' => -1,
            ]);
            $counts[] = $q->found_posts;
        }
        $series[] = [
            'name' => ucfirst($status),
            'data' => $counts,
        ];
        }

        return [ 'labels' => $labels, 'series' => $series ];
    }
}

// Initialize
AdminDashboard::instance()->init();

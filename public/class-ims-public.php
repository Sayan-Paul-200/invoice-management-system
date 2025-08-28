<?php
namespace IMS;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Minimal Public display class for front-end behaviour.
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

    /**
     * Initialize front-end hooks.
     */
    public function init() {
        // enqueue public assets when appropriate
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );

        // If you want to register shortcodes centrally rather than in includes,
        // you can do so here. Your includes/class-ims-shortcodes.php already
        // registers the shortcode, so this is optional.
        // if ( class_exists( '\IMS\Shortcodes' ) ) {
        //     \IMS\Shortcodes::instance()->init();
        // }
    }

    /**
     * Enqueue front-end CSS/JS.
     */
    public function enqueue_assets() {
        if ( defined( 'IMS_URL' ) && defined( 'IMS_VERSION' ) ) {
            // public CSS (you already enqueue in shortcode, but this ensures
            // assets are available on other pages if needed)
            wp_enqueue_style( 'ims-public-css', IMS_URL . 'public/css/ims-public.css', [], IMS_VERSION );

            // public JS (if you need front-end behaviour)
            if ( file_exists( IMS_PATH . 'public/js/ims-public.js' ) ) {
                wp_enqueue_script( 'ims-public-js', IMS_URL . 'public/js/ims-public.js', [ 'jquery' ], IMS_VERSION, true );
            }
        }
    }
}

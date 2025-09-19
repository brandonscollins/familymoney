<?php
// includes/class-sfm-loader.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SFM_Loader {

    protected static $instance = null;

    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // Load text domain for translations.
        add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

        // Enqueue scripts and styles.
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

        // Add admin menu items.
        add_action( 'admin_menu', array( $this, 'add_admin_menu_items' ) );

        // Initialize other core plugin classes.
        // Child Manager and Transaction CPT are initialized first as they define data structures.
        SFM_Child_Manager::get_instance();
        SFM_Transaction_CPT::get_instance();
        SFM_Admin::get_instance();
        SFM_Frontend::get_instance();
        SFM_AJAX::get_instance();
    }

    /**
     * Load plugin text domain for internationalization.
     */
    public function load_textdomain() {
        load_plugin_textdomain( 'strategicli-family-money', false, dirname( plugin_basename( SFM_PLUGIN_DIR . 'strategicli-family-money.php' ) ) . '/languages' );
    }

    /**
     * Enqueue scripts and styles for the frontend.
     */
    public function enqueue_frontend_assets() {
        wp_enqueue_style( 'sfm-frontend-style', SFM_PLUGIN_URL . 'assets/css/sfm-frontend.css', array(), SFM_VERSION, 'all' );
        wp_enqueue_script( 'sfm-frontend-script', SFM_PLUGIN_URL . 'assets/js/sfm-frontend.js', array( 'jquery' ), SFM_VERSION, true ); // Load in footer.

        // Pass AJAX URL and nonce to frontend script.
        wp_localize_script( 'sfm-frontend-script', 'sfm_ajax_object', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'sfm_frontend_nonce' ), // Security nonce for AJAX requests.
        ) );
    }

    /**
     * Enqueue scripts and styles for the admin area.
     */
    public function enqueue_admin_assets() {
        wp_enqueue_style( 'sfm-admin-style', SFM_PLUGIN_URL . 'assets/css/sfm-admin.css', array(), SFM_VERSION, 'all' );
        // wp_enqueue_script( 'sfm-admin-script', SFM_PLUGIN_URL . 'assets/js/sfm-admin.js', array( 'jquery' ), SFM_VERSION, true ); // Uncomment if you add specific admin JS.
    }

    /**
     * Add admin menu items.
     * This registers the plugin's settings page under the 'Settings' menu.
     */
    public function add_admin_menu_items() {
        // Add a sub-menu under 'Settings' for managing plugin options.
        add_options_page(
            __( 'Strategicli Family Money Settings', 'strategicli-family-money' ), // Page title.
            __( 'Family Money', 'strategicli-family-money' ),                      // Menu title.
            'manage_options',                                                    // Capability required to access.
            'sfm-settings',                                                      // Unique slug for the page.
            array( SFM_Admin::get_instance(), 'render_settings_page' )           // Callback function to render the page.
        );
    }
}
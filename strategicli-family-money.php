<?php
/**
 * Plugin Name: Strategicli Family Money
 * Description: A plugin to track allowance and "banks" for each child, with transaction logging and reporting.
 * Version: 1.0.0
 * Author: Strategicli
 * Text Domain: strategicli-family-money
 *
 * @package StrategicliFamilyMoney
 */

namespace Strategicli\FamilyMoney;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Define Constants.
 */
define( 'SFM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SFM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SFM_PLUGIN_VERSION', '1.0.0' );
define( 'SFM_TEXT_DOMAIN', 'strategicli-family-money' );

/**
 * Autoload classes.
 *
 * A simple autoloader to include our classes as needed.
 * This function will check for class files in the 'includes' directory.
 *
 * @param string $class_name The name of the class to load.
 */
spl_autoload_register( function ( $class_name ) {
	// Only autoload classes within our namespace.
	if ( strpos( $class_name, 'Strategicli\\FamilyMoney\\' ) !== 0 ) {
		return;
	}

	$class_base_name = str_replace( 'Strategicli\\FamilyMoney\\', '', $class_name );
	// CORRECTED: Ensure 'sfm-' prefix is added to the filename.
	$file_path       = SFM_PLUGIN_DIR . 'includes/class-sfm-' . strtolower( str_replace( '_', '-', $class_base_name ) ) . '.php';

	if ( file_exists( $file_path ) ) {
		require_once $file_path;
	}
} );

/**
 * The main plugin class.
 * This class orchestrates the loading of all other components.
 */
class StrategicliFamilyMoney {

	/**
	 * Static instance of the class.
	 *
	 * @var StrategicliFamilyMoney|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance of the plugin.
	 *
	 * Ensures only one instance of the plugin class is loaded or can be loaded.
	 *
	 * @return StrategicliFamilyMoney
	*/
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * Initializes the plugin by setting up hooks and loading components.
	 */
	private function __construct() {
		$this->define_hooks();
	}

	/**
	 * Define the core functionality hooks.
	 */
	private function define_hooks() {
		// Activation hook.
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		// Deactivation hook.
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		// Initialize all components.
		add_action( 'plugins_loaded', array( $this, 'init_components' ) );
	}

	/**
	 * Plugin activation logic.
	 *
	 * This method runs when the plugin is activated. It ensures that our custom
	 * post types are registered immediately upon activation to prevent 'post type not found' errors.
	 */
	public function activate() {
		// During activation, the autoloader might not be fully initialized for all contexts.
		// Explicitly require the CPT class to ensure it's available for the activation hook.
		// This bypasses potential timing issues with the autoloader for this specific, critical call.
		require_once SFM_PLUGIN_DIR . 'includes/class-sfm-cpt.php';

		// Register CPTs on activation.
		// This ensures flush_rewrite_rules() works correctly after plugin activation.
		CPT::get_instance()->register_custom_post_types();
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation logic.
	 *
	 * This method runs when the plugin is deactivated. It flushes rewrite rules
	 * to ensure that any custom rewrite rules added by our CPTs are removed.
	 */
	public function deactivate() {
		flush_rewrite_rules();
	}

	/**
	 * Initialize all plugin components.
	 *
	 * This method is called on the 'plugins_loaded' action, ensuring that
	 * all necessary classes are available before other WordPress hooks fire.
	 */
	public function init_components() {
		Admin::get_instance();         // Handles admin area functionality.
		CPT::get_instance();           // Registers custom post types.
		Ajax::get_instance();          // Handles AJAX requests.
		Money::get_instance();         // Handles frontend display and shortcodes.
		Dashboard_Widget::get_instance(); // Registers the dashboard widget.
	}
}

// Initialize the plugin.
StrategicliFamilyMoney::get_instance();

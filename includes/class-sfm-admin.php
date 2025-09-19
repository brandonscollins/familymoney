<?php
/**
 * Admin Class for Strategicli Family Money.
 *
 * This class handles all admin-related functionalities, including:
 * - Adding main admin menu and sub-menu pages.
 * - Managing children via a dedicated settings page.
 * - Enqueueing admin-specific scripts and styles.
 * - Implementing settings options for frontend display.
 *
 * @package StrategicliFamilyMoney
 */

namespace Strategicli\FamilyMoney;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin class.
 */
class Admin {

	/**
	 * Static instance of the class.
	 *
	 * @var Admin|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance of the class.
	 *
	 * @return Admin
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
	 * Registers hooks for admin menu, enqueueing scripts/styles,
	 * and initializing settings.
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) ); // Register our settings.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_filter( 'manage_sfm_child_posts_columns', array( $this, 'set_sfm_child_columns' ) );
		add_action( 'manage_sfm_child_posts_custom_column', array( $this, 'render_sfm_child_columns' ), 10, 2 );
	}

	/**
	 * Adds the main admin menu and sub-menu pages for the plugin.
	 */
	public function add_admin_menu() {
		// Main menu item for the plugin. This will display our custom settings page by default.
		add_menu_page(
			__( 'Strategicli Family Money', SFM_TEXT_DOMAIN ), // Page title for the main menu.
			__( 'Family Money', SFM_TEXT_DOMAIN ),             // Menu title displayed in the sidebar.
			'manage_options',                                  // Capability required to access.
			'strategicli-family-money',                        // Menu slug.
			array( $this, 'render_settings_page' ),            // Callback function to render the settings page.
			'dashicons-money',                                 // Icon URL or Dashicon.
			25                                                 // Position in the menu.
		);

		// Add 'Settings' as the first submenu.
		// By using the same slug as the parent menu, this page becomes the default view
		// when clicking on the main 'Family Money' menu item.
		add_submenu_page(
			'strategicli-family-money',                 // Parent slug.
			__( 'Strategicli Family Money Settings', SFM_TEXT_DOMAIN ), // Page title for the settings submenu.
			__( 'Settings', SFM_TEXT_DOMAIN ),          // Menu title for the settings submenu.
			'manage_options',                           // Capability.
			'strategicli-family-money',                 // Slug for this submenu, same as parent.
			array( $this, 'render_settings_page' )      // Callback to render the settings page.
		);

		// IMPORTANT: We do NOT add explicit submenus for 'Children' and 'Transactions' here.
		// The 'show_in_menu' argument in register_post_type in class-sfm-cpt.php
		// automatically adds 'All Children', 'Add New Child', 'All Transactions',
		// and 'Add New Transaction' under the 'Family Money' top-level menu.
		// This prevents the duplication you observed.
	}

	/**
	 * Registers plugin settings using the WordPress Settings API.
	 */
	public function register_settings() {
		// Register a setting group.
		register_setting(
			'sfm_settings_group', // Option group.
			'sfm_display_options', // Option name (stores all settings in one array).
			array( $this, 'sanitize_sfm_display_options' ) // Sanitize callback.
		);

		// Add a settings section for display.
		add_settings_section(
			'sfm_display_section', // ID.
			__( 'Frontend Display Options', SFM_TEXT_DOMAIN ), // Title.
			null, // No callback needed for section intro.
			'strategicli-family-money' // Page slug on which to show the section.
		);

		// Add individual settings fields for display.
		add_settings_field(
			'sfm_hide_header', // ID.
			__( 'Hide "Current Balances" Header', SFM_TEXT_DOMAIN ), // Title.
			array( $this, 'sfm_hide_header_callback' ), // Callback to render the field.
			'strategicli-family-money', // Page slug.
			'sfm_display_section' // Section ID.
		);

		add_settings_field(
			'sfm_header_type', // ID.
			__( 'Header Display Type', SFM_TEXT_DOMAIN ), // Title.
			array( $this, 'sfm_header_type_callback' ), // Callback to render the field.
			'strategicli-family-money', // Page slug.
			'sfm_display_section' // Section ID.
		);
		
		// Add a new section for permissions.
		add_settings_section(
			'sfm_permissions_section',
			__( 'Permissions', SFM_TEXT_DOMAIN ),
			null,
			'strategicli-family-money'
		);

		// Add the new checkbox field for guest transactions.
		add_settings_field(
			'sfm_allow_guest_transactions',
			__( 'Allow Guest Submissions', SFM_TEXT_DOMAIN ),
			array( $this, 'sfm_allow_guest_transactions_callback' ),
			'strategicli-family-money',
			'sfm_permissions_section'
		);
	}

	/**
	 * Renders the checkbox field for hiding the header.
	 */
	public function sfm_hide_header_callback() {
		$options = get_option( 'sfm_display_options' );
		$checked = isset( $options['hide_header'] ) ? checked( 1, $options['hide_header'], false ) : '';
		echo '<input type="checkbox" id="sfm_hide_header" name="sfm_display_options[hide_header]" value="1"' . $checked . '>';
		echo '<label for="sfm_hide_header">' . esc_html__( 'Check to hide the header (e.g., "Current Balances") from the frontend display.', SFM_TEXT_DOMAIN ) . '</label>';
	}

	/**
	 * Renders the radio button field for header type (text or emoji).
	 */
	public function sfm_header_type_callback() {
		$options       = get_option( 'sfm_display_options' );
		$selected_type = isset( $options['header_type'] ) ? $options['header_type'] : 'text'; // Default to text.

		echo '<input type="radio" id="sfm_header_type_text" name="sfm_display_options[header_type]" value="text"' . checked( 'text', $selected_type, false ) . '>';
		echo '<label for="sfm_header_type_text">' . esc_html__( 'Show "Current Balances" Text', SFM_TEXT_DOMAIN ) . '</label><br>';

		echo '<input type="radio" id="sfm_header_type_emoji" name="sfm_display_options[header_type]" value="emoji"' . checked( 'emoji', $selected_type, false ) . '>';
		echo '<label for="sfm_header_type_emoji">' . esc_html__( 'Show Dollar Emoji 💰', SFM_TEXT_DOMAIN ) . '</label>';
	}

	/**
	 * Renders the checkbox for allowing guest transactions.
	 */
	public function sfm_allow_guest_transactions_callback() {
		$options = get_option( 'sfm_display_options' );
		$checked = isset( $options['allow_guest_transactions'] ) ? checked( 1, $options['allow_guest_transactions'], false ) : '';
		echo '<input type="checkbox" id="sfm_allow_guest_transactions" name="sfm_display_options[allow_guest_transactions]" value="1"' . $checked . '>';
		echo '<label for="sfm_allow_guest_transactions">' . esc_html__( 'Allow non-logged-in users to submit transactions.', SFM_TEXT_DOMAIN ) . '</label>';
		echo '<p class="description">' . esc_html__( 'By default, only logged-in WordPress users can add transactions.', SFM_TEXT_DOMAIN ) . '</p>';
	}

	/**
	 * Sanitizes the display options before saving.
	 *
	 * @param array $input The input array from the settings form.
	 * @return array The sanitized array.
	 */
	public function sanitize_sfm_display_options( $input ) {
		$sanitized_input = get_option( 'sfm_display_options', array() ); // Get existing options to merge with.

		// Sanitize hide_header checkbox.
		$sanitized_input['hide_header'] = isset( $input['hide_header'] ) ? 1 : 0;

		// Sanitize header_type radio buttons.
		$valid_header_types = array( 'text', 'emoji' );
		if ( isset( $input['header_type'] ) && in_array( $input['header_type'], $valid_header_types, true ) ) {
			$sanitized_input['header_type'] = sanitize_text_field( $input['header_type'] );
		} else {
			$sanitized_input['header_type'] = 'text'; // Default.
		}

		// Sanitize allow_guest_transactions checkbox.
		$sanitized_input['allow_guest_transactions'] = isset( $input['allow_guest_transactions'] ) ? 1 : 0;

		return $sanitized_input;
	}

	/**
	 * Renders the content of the plugin's Settings page.
	 * This page will provide instructions and future options.
	 */
	public function render_settings_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Strategicli Family Money Settings & Instructions', SFM_TEXT_DOMAIN ); ?></h1>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'sfm_settings_group' ); // Settings group name.
				do_settings_sections( 'strategicli-family-money' ); // Page slug for sections.
				submit_button();
				?>
			</form>

			<div class="sfm-admin-card">
				<h2><?php esc_html_e( 'Welcome to Strategicli Family Money!', SFM_TEXT_DOMAIN ); ?></h2>
				<p><?php esc_html_e( 'This plugin helps you track allowance and money balances for each child in your family. Below you\'ll find instructions on how to set it up and use it.', SFM_TEXT_DOMAIN ); ?></p>
			</div>

			<div class="sfm-admin-card">
				<h3><?php esc_html_e( 'Getting Started', SFM_TEXT_DOMAIN ); ?></h3>
				<ol>
					<li><strong><?php esc_html_e( 'Add Your Children:', SFM_TEXT_DOMAIN ); ?></strong> <?php echo wp_kses_post( __( 'Go to "Family Money > Children" and click "Add New" to create a profile for each child. Simply enter their name as the title.', SFM_TEXT_DOMAIN ) ); ?></li>
					<li><strong><?php esc_html_e( 'Record Transactions:', SFM_TEXT_DOMAIN ); ?></strong> <?php esc_html_e( 'You have two ways to add money to or remove money from a child\'s balance:', SFM_TEXT_DOMAIN ); ?>
						<ul>
							<li><?php esc_html_e( 'Admin Area:', SFM_TEXT_DOMAIN ); ?> <?php echo wp_kses_post( __( 'Go to "Family Money > All Transactions" and click "Add New". Select the child, enter the amount (e.g., 5.00 for +$5.00, -2.50 for -$2.50), and a reason/description.', SFM_TEXT_DOMAIN ) ); ?></li>
							<li><?php esc_html_e( 'Frontend Form:', SFM_TEXT_DOMAIN ); ?> <?php echo wp_kses_post( __( 'Embed the transaction form on any page using the shortcode: <code>[sfm_transaction_form]</code>. Remember to password protect this page in WordPress so only parents can access it.', SFM_TEXT_DOMAIN ) ); ?></li>
						</ul>
					</li>
					<li><strong><?php esc_html_e( 'View Balances:', SFM_TEXT_DOMAIN ); ?></strong> <?php echo wp_kses_post( __( 'Display current balances for all children on any page using the shortcode: <code>[sfm_allowance_display]</code>. Clicking on a child\'s balance will show their recent transaction history.', SFM_TEXT_DOMAIN ) ); ?></li>
					<li><strong><?php esc_html_e( 'Dashboard Overview:', SFM_TEXT_DOMAIN ); ?></strong> <?php esc_html_e( 'A quick summary of all children\'s balances is available right on your WordPress Dashboard under "Strategicli Family Money Balances".', SFM_TEXT_DOMAIN ); ?></li>
				</ol>
			</div>

			<div class="sfm-admin-card">
				<h3><?php esc_html_e( 'Shortcodes', SFM_TEXT_DOMAIN ); ?></h3>
				<p><code>[sfm_transaction_form]</code> - <?php esc_html_e( 'Displays the form for adding new allowance transactions.', SFM_TEXT_DOMAIN ); ?></p>
				<p><code>[sfm_allowance_display]</code> - <?php esc_html_e( 'Displays a list of all children with their current allowance totals. Clicking on a total will open a modal with recent transaction history.', SFM_TEXT_DOMAIN ); ?></p>
			</div>

			<div class="sfm-admin-card">
				<h3><?php esc_html_e( 'Styling and Dark Mode', SFM_TEXT_DOMAIN ); ?></h3>
				<p><?php echo wp_kses_post( __( 'For dark mode support, add the class `sfm-dark-mode` to a parent element (e.g., the `body` tag or a `div` wrapping the shortcode) where you want dark mode to apply.', SFM_TEXT_DOMAIN ) ); ?></p>
			</div>

		</div>
		<?php
	}

	/**
	 * Enqueues admin-specific scripts and styles.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public function enqueue_admin_scripts( $hook ) {
		// Enqueue admin CSS.
		wp_enqueue_style(
			'sfm-admin-style',
			SFM_PLUGIN_URL . 'assets/css/sfm-admin.css',
			array(),
			SFM_PLUGIN_VERSION
		);

		// No specific admin JS needed yet as main interactions are via CPT lists.
	}

	/**
	 * Sets custom columns for the 'sfm_child' post type list table.
	 *
	 * @param array $columns An array of column headings.
	 * @return array Modified column headings.
	 */
	public function set_sfm_child_columns( $columns ) {
		$new_columns                = array();
		$new_columns['cb']          = '<input type="checkbox" />';
		$new_columns['title']       = __( 'Child Name', SFM_TEXT_DOMAIN );
		$new_columns['sfm_balance'] = __( 'Current Balance', SFM_TEXT_DOMAIN );
		$new_columns['date']        = __( 'Date Added', SFM_TEXT_DOMAIN );
		return $new_columns;
	}

	/**
	 * Renders content for custom columns in the 'sfm_child' post type list table.
	 *
	 * @param string $column_name The name of the column to render.
	 * @param int    $post_id The ID of the current post.
	 */
	public function render_sfm_child_columns( $column_name, $post_id ) {
		if ( 'sfm_balance' === $column_name ) {
			$balance = Money::get_instance()->get_child_balance( $post_id );
			echo '<span class="sfm-balance-admin ' . ( $balance < 0 ? 'sfm-negative-balance' : '' ) . '">';
			echo '$' . number_format( (float) $balance, 2 );
			echo '</span>';
		}
	}
}


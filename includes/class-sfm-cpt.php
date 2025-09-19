<?php
/**
 * Custom Post Type Class for Strategicli Family Money.
 *
 * This class handles the registration of custom post types for children and transactions,
 * and manages their associated custom fields and meta boxes.
 *
 * @package StrategicliFamilyMoney
 */

namespace Strategicli\FamilyMoney;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CPT class.
 */
class CPT {

	/**
	 * Static instance of the class.
	 *
	 * @var CPT|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance of the class.
	 *
	 * @return CPT
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
	 * Registers hooks for custom post types and meta boxes.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'register_custom_post_types' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_transaction_meta_box' ) );
		add_action( 'save_post_sfm_transaction', array( $this, 'save_transaction_meta_data' ) );
	}

	/**
	 * Registers custom post types for 'Children' and 'Transactions'.
	 */
	public function register_custom_post_types() {
		$this->register_child_cpt();
		$this->register_transaction_cpt();
	}

	/**
	 * Registers the 'sfm_child' custom post type.
	 * This CPT will store individual child profiles.
	 */
	private function register_child_cpt() {
		$labels = array(
			'name'                  => _x( 'Children', 'Post Type General Name', SFM_TEXT_DOMAIN ),
			'singular_name'         => _x( 'Child', 'Post Type Singular Name', SFM_TEXT_DOMAIN ),
			'menu_name'             => __( 'Children', SFM_TEXT_DOMAIN ),
			'name_admin_bar'        => __( 'Child', SFM_TEXT_DOMAIN ),
			'archives'              => __( 'Child Archives', SFM_TEXT_DOMAIN ),
			'attributes'            => __( 'Child Attributes', SFM_TEXT_DOMAIN ),
			'parent_item_colon'     => __( 'Parent Child:', SFM_TEXT_DOMAIN ),
			'all_items'             => __( 'All Children', SFM_TEXT_DOMAIN ),
			'add_new_item'          => __( 'Add New Child', SFM_TEXT_DOMAIN ),
			'add_new'               => __( 'Add New', SFM_TEXT_DOMAIN ),
			'new_item'              => __( 'New Child', SFM_TEXT_DOMAIN ),
			'edit_item'             => __( 'Edit Child', SFM_TEXT_DOMAIN ),
			'update_item'           => __( 'Update Child', SFM_TEXT_DOMAIN ),
			'view_item'             => __( 'View Child', SFM_TEXT_DOMAIN ),
			'view_items'            => __( 'View Children', SFM_TEXT_DOMAIN ),
			'search_items'          => __( 'Search Child', SFM_TEXT_DOMAIN ),
			'not_found'             => __( 'No children found', SFM_TEXT_DOMAIN ),
			'not_found_in_trash'    => __( 'No children found in Trash', SFM_TEXT_DOMAIN ),
			'filter_items_list'     => __( 'Filter children list', SFM_TEXT_DOMAIN ),
			'items_list_navigation' => __( 'Children list navigation', SFM_TEXT_DOMAIN ),
			'items_list'            => __( 'Children list', SFM_TEXT_DOMAIN ),
		);
		$args   = array(
			'label'                 => __( 'Child', SFM_TEXT_DOMAIN ),
			'description'           => __( 'Individual child profiles for allowance tracking', SFM_TEXT_DOMAIN ),
			'labels'                => $labels,
			'supports'              => array( 'title' ), // Only need the title for the child's name.
			'hierarchical'          => false,
			'public'                => false, // Not publicly viewable on the frontend.
			'show_ui'               => true, // Show in admin UI.
			'show_in_menu'          => 'strategicli-family-money', // Link under our main plugin menu.
			'menu_position'         => 5,
			'show_in_admin_bar'     => false,
			'show_in_nav_menus'     => false,
			'can_export'            => true,
			'has_archive'           => false,
			'exclude_from_search'   => true,
			'publicly_queryable'    => false,
			'rewrite'               => false,
			'capability_type'       => 'post',
			'show_in_rest'          => true, // Enable for Gutenberg/REST API.
		);
		register_post_type( 'sfm_child', $args );
	}

	/**
	 * Registers the 'sfm_transaction' custom post type.
	 * This CPT will store individual allowance transactions.
	 */
	private function register_transaction_cpt() {
		$labels = array(
			'name'                  => _x( 'Transactions', 'Post Type General Name', SFM_TEXT_DOMAIN ),
			'singular_name'         => _x( 'Transaction', 'Post Type Singular Name', SFM_TEXT_DOMAIN ),
			'menu_name'             => __( 'Transactions', SFM_TEXT_DOMAIN ),
			'name_admin_bar'        => __( 'Transaction', SFM_TEXT_DOMAIN ),
			'archives'              => __( 'Transaction Archives', SFM_TEXT_DOMAIN ),
			'attributes'            => __( 'Transaction Attributes', SFM_TEXT_DOMAIN ),
			'parent_item_colon'     => __( 'Parent Transaction:', SFM_TEXT_DOMAIN ),
			'all_items'             => __( 'All Transactions', SFM_TEXT_DOMAIN ),
			'add_new_item'          => __( 'Add New Transaction', SFM_TEXT_DOMAIN ),
			'add_new'               => __( 'Add New', SFM_TEXT_DOMAIN ),
			'new_item'              => __( 'New Transaction', SFM_TEXT_DOMAIN ),
			'edit_item'             => __( 'Edit Transaction', SFM_TEXT_DOMAIN ),
			'update_item'           => __( 'Update Transaction', SFM_TEXT_DOMAIN ),
			'view_item'             => __( 'View Transaction', SFM_TEXT_DOMAIN ),
			'view_items'            => __( 'View Transactions', SFM_TEXT_DOMAIN ),
			'search_items'          => __( 'Search Transaction', SFM_TEXT_DOMAIN ),
			'not_found'             => __( 'No transactions found', SFM_TEXT_DOMAIN ),
			'not_found_in_trash'    => __( 'No transactions found in Trash', SFM_TEXT_DOMAIN ),
			'filter_items_list'     => __( 'Filter transactions list', SFM_TEXT_DOMAIN ),
			'items_list_navigation' => __( 'Transactions list navigation', SFM_TEXT_DOMAIN ),
			'items_list'            => __( 'Transactions list', SFM_TEXT_DOMAIN ),
		);
		$args   = array(
			'label'                 => __( 'Transaction', SFM_TEXT_DOMAIN ),
			'description'           => __( 'Financial transactions for children\'s allowance', SFM_TEXT_DOMAIN ),
			'labels'                => $labels,
			'supports'              => array( 'title' ), // Title can be a short description if needed.
			'hierarchical'          => false,
			'public'                => false, // Transactions are private.
			'show_ui'               => true,
			'show_in_menu'          => 'strategicli-family-money', // Link under our main plugin menu.
			'menu_position'         => 10,
			'show_in_admin_bar'     => false,
			'show_in_nav_menus'     => false,
			'can_export'            => true,
			'has_archive'           => false,
			'exclude_from_search'   => true,
			'publicly_queryable'    => false,
			'rewrite'               => false,
			'capability_type'       => 'post',
			'show_in_rest'          => true, // Enable for Gutenberg/REST API.
			'query_var'             => false, // Do not expose 'sfm_transaction' as a query variable.
			'show_in_admin_bar'     => false,
			'show_in_nav_menus'     => false,
		);
		register_post_type( 'sfm_transaction', $args );
	}

	/**
	 * Adds a meta box to the 'sfm_transaction' edit screen.
	 */
	public function add_transaction_meta_box() {
		add_meta_box(
			'sfm_transaction_details',
			__( 'Transaction Details', SFM_TEXT_DOMAIN ),
			array( $this, 'render_transaction_meta_box' ),
			'sfm_transaction',
			'normal',
			'high'
		);
	}

	/**
	 * Renders the content of the transaction meta box.
	 * This includes fields for child selection, amount, and reason/description.
	 *
	 * @param \WP_Post $post The current post object.
	 */
	public function render_transaction_meta_box( $post ) {
		// Add a nonce field so we can check it later.
		wp_nonce_field( 'sfm_save_transaction_meta', 'sfm_transaction_nonce' );

		// Get current meta values.
		$child_id = get_post_meta( $post->ID, '_sfm_child_id', true );
		$amount   = get_post_meta( $post->ID, '_sfm_amount', true );
		$reason   = get_post_meta( $post->ID, '_sfm_reason', true );

		// Get all children.
		$children_posts = get_posts(
			array(
				'post_type'      => 'sfm_child',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		?>
		<p>
			<label for="sfm_child_id"><?php esc_html_e( 'Child:', SFM_TEXT_DOMAIN ); ?></label>
			<select name="sfm_child_id" id="sfm_child_id" class="postbox">
				<option value=""><?php esc_html_e( 'Select a Child', SFM_TEXT_DOMAIN ); ?></option>
				<?php
				if ( ! empty( $children_posts ) ) {
					foreach ( $children_posts as $child_post ) {
						echo '<option value="' . esc_attr( $child_post->ID ) . '" ' . selected( $child_id, $child_post->ID, false ) . '>' . esc_html( $child_post->post_title ) . '</option>';
					}
				}
				?>
			</select>
		</p>

		<p>
			<label for="sfm_amount"><?php esc_html_e( 'Amount (USD, e.g., 5.00 for +$5.00, -2.50 for -$2.50):', SFM_TEXT_DOMAIN ); ?></label>
			<input type="number" step="0.01" name="sfm_amount" id="sfm_amount" value="<?php echo esc_attr( (float) $amount ); ?>" class="postbox" required />
		</p>

		<p>
			<label for="sfm_reason"><?php esc_html_e( 'Reason/Description:', SFM_TEXT_DOMAIN ); ?></label>
			<textarea name="sfm_reason" id="sfm_reason" rows="3" class="postbox"><?php echo esc_textarea( $reason ); ?></textarea>
		</p>
		<?php
	}

	/**
	 * Saves the custom meta data for 'sfm_transaction' post type.
	 *
	 * @param int $post_id The ID of the post being saved.
	 */
	public function save_transaction_meta_data( $post_id ) {
		// Check if our nonce is set.
		if ( ! isset( $_POST['sfm_transaction_nonce'] ) ) {
			return;
		}

		// Verify that the nonce is valid.
		if ( ! wp_verify_nonce( sanitize_key( $_POST['sfm_transaction_nonce'] ), 'sfm_save_transaction_meta' ) ) {
			return;
		}

		// If this is an autosave, our form has not been submitted, so we don't want to do anything.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check the user's permissions.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Sanitize and save the child ID.
		if ( isset( $_POST['sfm_child_id'] ) ) {
			$child_id = absint( wp_unslash( $_POST['sfm_child_id'] ) ); // Use absint for IDs.
			update_post_meta( $post_id, '_sfm_child_id', $child_id );
		}

		// Sanitize and save the amount.
		if ( isset( $_POST['sfm_amount'] ) ) {
			$amount = sanitize_text_field( wp_unslash( $_POST['sfm_amount'] ) );
			// Ensure it's a valid number and cast to float.
			if ( is_numeric( $amount ) ) {
				update_post_meta( $post_id, '_sfm_amount', (float) $amount );
			} else {
				// Fallback if not a valid number.
				update_post_meta( $post_id, '_sfm_amount', 0.00 );
			}
		}

		// Sanitize and save the reason.
		if ( isset( $_POST['sfm_reason'] ) ) {
			$reason = sanitize_text_field( wp_unslash( $_POST['sfm_reason'] ) );
			update_post_meta( $post_id, '_sfm_reason', $reason );
			// Also update the post title with the reason for easier viewing in admin.
			// Only update if the title is empty or hasn't been set manually.
			if ( empty( get_post( $post_id )->post_title ) || strpos( get_post( $post_id )->post_title, 'Auto Draft' ) !== false ) {
				wp_update_post(
					array(
						'ID'         => $post_id,
						'post_title' => $reason,
					)
				);
			}
		}
	}
}


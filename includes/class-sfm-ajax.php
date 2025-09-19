<?php
/**
 * AJAX Class for Strategicli Family Money.
 *
 * This class handles all AJAX requests for the plugin, including:
 * - Adding new allowance transactions from the frontend form.
 * - Retrieving a child's transaction history for the modal display.
 * - Retrieving all children's balances for a full refresh.
 *
 * @package StrategicliFamilyMoney
 */

namespace Strategicli\FamilyMoney;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ajax class.
 */
class Ajax {

	/**
	 * Static instance of the class.
	 *
	 * @var Ajax|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance of the class.
	 *
	 * @return Ajax
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
	 * Registers AJAX action hooks.
	 */
	private function __construct() {
		add_action( 'wp_ajax_sfm_add_transaction', array( $this, 'sfm_add_transaction' ) );
		add_action( 'wp_ajax_nopriv_sfm_add_transaction', array( $this, 'sfm_add_transaction' ) ); // For logged-out users.
		add_action( 'wp_ajax_sfm_get_transactions', array( $this, 'sfm_get_transactions' ) );
		add_action( 'wp_ajax_nopriv_sfm_get_transactions', array( $this, 'sfm_get_transactions' ) );
		add_action( 'wp_ajax_sfm_get_all_balances', array( $this, 'sfm_get_all_balances' ) );
		add_action( 'wp_ajax_nopriv_sfm_get_all_balances', array( $this, 'sfm_get_all_balances' ) );
	}

	/**
	 * Handles AJAX request to add a new transaction.
	 *
	 * Processes form data, sanitizes it, and inserts a new 'sfm_transaction' post.
	 */
	public function sfm_add_transaction() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'sfm_add_transaction_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Nonce verification failed.', SFM_TEXT_DOMAIN ) ) );
			return;
		}

		// Check user permissions based on plugin settings.
		$options        = get_option( 'sfm_display_options' );
		$guests_allowed = isset( $options['allow_guest_transactions'] ) && 1 === (int) $options['allow_guest_transactions'];

		if ( ! is_user_logged_in() && ! $guests_allowed ) {
			// Get the current page URL to redirect back to after login.
			$current_page_url = home_url( add_query_arg( null, null ) );
			$login_url        = wp_login_url( $current_page_url );

			$error_message = sprintf(
				// translators: %s is the login URL.
				wp_kses( __( 'You must be logged in to add transactions. Please <a href="%s">log in</a>.', SFM_TEXT_DOMAIN ), array( 'a' => array( 'href' => array() ) ) ),
				esc_url( $login_url )
			);
			wp_send_json_error( array( 'message' => $error_message ) );
			return;
		}

		// Sanitize and validate input.
		$child_id = isset( $_POST['child_id'] ) ? absint( wp_unslash( $_POST['child_id'] ) ) : 0;
		$amount   = isset( $_POST['amount'] ) ? sanitize_text_field( wp_unslash( $_POST['amount'] ) ) : '';
		$reason   = isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : '';

		if ( empty( $child_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Please select a child.', SFM_TEXT_DOMAIN ) ) );
		}
		if ( ! is_numeric( $amount ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid numeric amount.', SFM_TEXT_DOMAIN ) ) );
		}
		if ( empty( $reason ) ) {
			wp_send_json_error( array( 'message' => __( 'Please provide a reason for the transaction.', SFM_TEXT_DOMAIN ) ) );
		}

		// Ensure amount is a float.
		$amount = (float) $amount;

		// Create the transaction post.
		$post_data = array(
			'post_type'   => 'sfm_transaction',
			'post_title'  => $reason, // Use reason as title for easier identification.
			'post_status' => 'private', // IMPORTANT: Save as private so it's not public, but can be queried.
			'post_author' => get_current_user_id(), // Will be 0 for guest users, which is fine.
		);

		$post_id = wp_insert_post( $post_data );

		if ( is_wp_error( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Failed to create transaction post.', SFM_TEXT_DOMAIN ) ) );
		}

		// Save custom meta data.
		update_post_meta( $post_id, '_sfm_child_id', $child_id );
		update_post_meta( $post_id, '_sfm_amount', $amount );
		update_post_meta( $post_id, '_sfm_reason', $reason );

		wp_send_json_success( array( 'message' => __( 'Transaction added successfully!', SFM_TEXT_DOMAIN ) ) );
	}


	/**
	 * Handles AJAX request to get a child's transaction history.
	 *
	 * Fetches and returns a paginated list of transactions for a specific child.
	 */
	public function sfm_get_transactions() {
		// Verify nonce.
		if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['nonce'] ), 'sfm_get_transactions_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Nonce verification failed.', SFM_TEXT_DOMAIN ) ) );
		}

		// Publicly viewable, no capability check needed here as data is non-sensitive.
		// If sensitivity increases, add a capability check.

		// Sanitize and validate input.
		$child_id = isset( $_GET['child_id'] ) ? absint( wp_unslash( $_GET['child_id'] ) ) : 0;
		$page     = isset( $_GET['page'] ) ? absint( wp_unslash( $_GET['page'] ) ) : 1;

		if ( empty( $child_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Child ID is missing.', SFM_TEXT_DOMAIN ) ) );
		}

		$transactions_per_page = 10; // Number of transactions per page in the modal.

		$args = array(
			'post_type'      => 'sfm_transaction',
			'posts_per_page' => $transactions_per_page,
			'paged'          => $page,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'meta_query'     => array(
				array(
					'key'   => '_sfm_child_id',
					'value' => $child_id,
					'type'  => 'NUMERIC',
				),
			),
			'post_status'    => 'private', // Only query for 'private' status transactions.
		);

		$query               = new \WP_Query( $args );
		$transactions_data = array();

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$transaction_id = get_the_ID();
				$amount         = get_post_meta( $transaction_id, '_sfm_amount', true );
				$reason         = get_post_meta( $transaction_id, '_sfm_reason', true );
				$date           = get_the_date( 'M j, Y' ); // e.g., Jan 10, 2024

				$transactions_data[] = array(
					'id'          => $transaction_id,
					'amount'      => '$' . number_format( (float) $amount, 2 ),
					'is_positive' => ( (float) $amount >= 0 ),
					'reason'      => esc_html( $reason ),
					'date'        => $date,
				);
			}
			wp_reset_postdata(); // Restore original Post Data.
		}

		wp_send_json_success(
			array(
				'transactions'  => $transactions_data,
				'current_page'  => $page,
				'total_pages'   => $query->max_num_pages,
				'message'       => empty( $transactions_data ) ? __( 'No transactions found for this child.', SFM_TEXT_DOMAIN ) : '',
			)
		);
	}

	/**
	 * Handles AJAX request to get all children's current balances.
	 * This is used for the frontend refresh button.
	 */
	public function sfm_get_all_balances() {
		// Verify nonce.
		if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['nonce'] ), 'sfm_get_balance_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Nonce verification failed.', SFM_TEXT_DOMAIN ) ) );
		}

		// Publicly viewable, no capability check needed here.

		$children = get_posts(
			array(
				'post_type'      => 'sfm_child',
				'posts_per_page' => -1, // Get all children.
				'orderby'        => 'title',
				'order'          => 'ASC',
				'fields'         => 'ids', // Get only IDs for performance.
			)
		);

		$all_balances   = array();
		$money_instance = Money::get_instance(); // Get the Money class instance.

		if ( ! empty( $children ) ) {
			foreach ( $children as $child_id ) {
				$balance              = $money_instance->get_child_balance( $child_id );
				$all_balances[ $child_id ] = '$' . number_format( (float) $balance, 2 );
			}
		}

		wp_send_json_success(
			array(
				'balances' => $all_balances,
				'message'  => empty( $all_balances ) ? __( 'No children found.', SFM_TEXT_DOMAIN ) : __( 'Balances refreshed successfully.', SFM_TEXT_DOMAIN ),
			)
		);
	}
}

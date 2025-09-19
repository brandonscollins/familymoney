<?php
/**
 * Frontend Money Class for Strategicli Family Money.
 *
 * This class handles:
 * - Enqueueing frontend scripts and styles.
 * - Registering and rendering the [sfm_transaction_form] shortcode.
 * - Registering and rendering the [sfm_allowance_display] shortcode.
 * - Calculating individual child balances.
 * - Managing data for the transaction history modal.
 * - Applying display settings from the admin panel.
 *
 * @package StrategicliFamilyMoney
 */

namespace Strategicli\FamilyMoney;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Money class.
 */
class Money {

	/**
	 * Static instance of the class.
	 *
	 * @var Money|null
	 */
	private static $instance = null;

	/**
	 * Stores plugin display options.
	 *
	 * @var array
	 */
	private $display_options;

	/**
	 * Get the singleton instance of the class.
	 *
	 * @return Money
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
	 * Registers hooks for enqueueing scripts/styles and shortcodes.
	 * Loads display options.
	 */
	private function __construct() {
		$this->display_options = get_option( 'sfm_display_options', array() ); // Load options, default to empty array.

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ) );
		add_action( 'init', array( $this, 'register_shortcodes' ) );
		add_action( 'wp_footer', array( $this, 'render_transaction_history_modal' ) ); // IMPORTANT: Render modal in footer.
		add_action( 'wp_footer', array( $this, 'render_confirmation_modal' ) ); // Add confirmation modal to footer.
	}

	/**
	 * Enqueues frontend scripts and styles.
	 */
	public function enqueue_frontend_scripts() {
		// Enqueue frontend CSS.
		wp_enqueue_style(
			'sfm-frontend-style',
			SFM_PLUGIN_URL . 'assets/css/sfm-frontend.css',
			array(),
			SFM_PLUGIN_VERSION
		);

		// Enqueue Dashicons.
		wp_enqueue_style( 'dashicons' );

		// Enqueue frontend JavaScript.
		wp_enqueue_script(
			'sfm-money-script',
			SFM_PLUGIN_URL . 'assets/js/sfm-money.js',
			array( 'jquery' ), // Depends on jQuery for AJAX.
			SFM_PLUGIN_VERSION,
			true // Enqueue in the footer.
		);

		// Localize script with AJAX URL and nonce.
		wp_localize_script(
			'sfm-money-script',
			'sfm_ajax_object',
			array(
				'ajax_url'               => admin_url( 'admin-ajax.php' ),
				'add_transaction_nonce'  => wp_create_nonce( 'sfm_add_transaction_nonce' ),
				'get_transactions_nonce' => wp_create_nonce( 'sfm_get_transactions_nonce' ),
				'get_balance_nonce'      => wp_create_nonce( 'sfm_get_balance_nonce' ),
				'text_domain'            => SFM_TEXT_DOMAIN,
			)
		);
	}

	/**
	 * Registers the plugin's shortcodes.
	 */
	public function register_shortcodes() {
		add_shortcode( 'sfm_transaction_form', array( $this, 'render_transaction_form_shortcode' ) );
		add_shortcode( 'sfm_allowance_display', array( $this, 'render_allowance_display_shortcode' ) );
	}

	/**
	 * Renders the transaction submission form.
	 * Shortcode: [sfm_transaction_form]
	 *
	 * @return string The HTML output of the form.
	 */
	public function render_transaction_form_shortcode() {
		// Get all children to populate the dropdown.
		$children = get_posts(
			array(
				'post_type'      => 'sfm_child',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		ob_start(); // Start output buffering.
		?>
		<div class="sfm-form-wrapper">
			<h2 class="sfm-form-title"><?php esc_html_e( 'Record New Transaction', SFM_TEXT_DOMAIN ); ?></h2>
			<form id="sfm-transaction-form" class="sfm-form">
				<div class="sfm-form-group">
					<label for="sfm-child-select"><?php esc_html_e( 'Select Child:', SFM_TEXT_DOMAIN ); ?></label>
					<select id="sfm-child-select" name="child_id" required>
						<option value=""><?php esc_html_e( '— Select a child —', SFM_TEXT_DOMAIN ); ?></option>
						<?php
						if ( $children ) {
							foreach ( $children as $child ) {
								echo '<option value="' . esc_attr( $child->ID ) . '">' . esc_html( $child->post_title ) . '</option>';
							}
						} else {
							echo '<option value="">' . esc_html__( 'No children found. Please add children in the admin settings.', SFM_TEXT_DOMAIN ) . '</option>';
						}
						?>
					</select>
				</div>

				<div class="sfm-form-group">
					<label for="sfm-amount-input"><?php esc_html_e( 'Amount (USD, e.g., 5.00 for +$5.00, -2.50 for -$2.50):', SFM_TEXT_DOMAIN ); ?></label>
					<input type="number" step="0.01" id="sfm-amount-input" name="amount" required placeholder="0.00">
				</div>

				<div class="sfm-form-group">
					<label for="sfm-reason-input"><?php esc_html_e( 'Reason/Description:', SFM_TEXT_DOMAIN ); ?></label>
					<textarea id="sfm-reason-input" name="reason" rows="3" required placeholder="<?php esc_attr_e( 'e.g., Weekly allowance, New video game, Chores completed', SFM_TEXT_DOMAIN ); ?>"></textarea>
				</div>

				<button type="submit" class="sfm-submit-button"><?php esc_html_e( 'Add Transaction', SFM_TEXT_DOMAIN ); ?></button>

				<div id="sfm-form-message" class="sfm-message" style="display:none;"></div>
			</form>
		</div>
		<?php
		return ob_get_clean(); // Return the buffered content.
	}

	/**
	 * Renders the allowance display for all children.
	 * Shortcode: [sfm_allowance_display]
	 *
	 * @return string The HTML output of the allowance display.
	 */
	public function render_allowance_display_shortcode() {
		$children = get_posts(
			array(
				'post_type'      => 'sfm_child',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$hide_header = isset( $this->display_options['hide_header'] ) && 1 === (int) $this->display_options['hide_header'];
		$header_type = isset( $this->display_options['header_type'] ) ? $this->display_options['header_type'] : 'text';

		ob_start();
		?>
		<div class="sfm-allowance-display-wrapper">
			<div class="sfm-display-header">
				<?php if ( ! $hide_header ) : ?>
					<h2 class="sfm-display-title">
						<?php
						if ( 'emoji' === $header_type ) {
							echo '💰'; // Dollar emoji.
						} else {
							esc_html_e( 'Current Balances', SFM_TEXT_DOMAIN );
						}
						?>
					</h2>
				<?php endif; ?>
				<button id="sfm-refresh-balances" class="sfm-refresh-button" title="<?php esc_attr_e( 'Refresh Balances', SFM_TEXT_DOMAIN ); ?>">
					<span class="dashicons dashicons-update"></span>
				</button>
			</div>

			<?php
			if ( $children ) {
				echo '<ul class="sfm-child-list">';
				foreach ( $children as $child ) {
					$child_id = $child->ID;
					$balance  = $this->get_child_balance( $child_id );
					?>
					<li class="sfm-child-item">
						<span class="sfm-child-name"><?php echo esc_html( $child->post_title ); ?>:</span>
						<span class="sfm-child-balance <?php echo ( $balance < 0 ) ? 'sfm-negative-balance' : ''; ?>"
							data-child-id="<?php echo esc_attr( $child_id ); ?>"
							data-child-name="<?php echo esc_attr( $child->post_title ); ?>">
							<?php echo '$' . number_format( (float) $balance, 2 ); ?>
						</span>
					</li>
					<?php
				}
				echo '</ul>';
			} else {
				?>
				<p class="sfm-no-children"><?php esc_html_e( 'No children added yet. Please add children in the admin settings.', SFM_TEXT_DOMAIN ); ?></p>
				<?php
			}
			?>
		</div>

		<?php
		return ob_get_clean();
	}

	/**
	 * Calculates the total allowance for a specific child.
	 *
	 * @param int $child_id The ID of the child post.
	 * @return float The total balance for the child.
	 */
	public function get_child_balance( $child_id ) {
		$total_balance = 0.00;

		$transactions = get_posts(
			array(
				'post_type'      => 'sfm_transaction',
				'posts_per_page' => -1,
				'meta_query'     => array(
					array(
						'key'     => '_sfm_child_id',
						'value'   => $child_id,
						'compare' => '=',
						'type'    => 'NUMERIC',
					),
				),
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'post_status'    => 'private',
			)
		);

		if ( ! empty( $transactions ) ) {
			foreach ( $transactions as $transaction_id ) {
				$amount         = (float) get_post_meta( $transaction_id, '_sfm_amount', true );
				$total_balance += $amount;
			}
		}

		return round( $total_balance, 2 );
	}

	/**
	 * Renders the HTML structure for the transaction history modal.
	 * This method is hooked into `wp_footer`.
	 */
	public function render_transaction_history_modal() {
		?>
		<div id="sfm-transaction-history-modal" class="sfm-modal" style="display:none;">
			<div class="sfm-modal-content">
				<span class="sfm-modal-close">&times;</span>
				<h3 id="sfm-modal-title"></h3>
				<div class="sfm-modal-body">
					<ul id="sfm-modal-transactions">
						<!-- Transaction items will be loaded here via AJAX -->
					</ul>
					<div id="sfm-modal-pagination" class="sfm-modal-pagination">
						<button id="sfm-modal-prev" disabled><?php esc_html_e( 'Previous', SFM_TEXT_DOMAIN ); ?></button>
						<button id="sfm-modal-next"><?php esc_html_e( 'Next', SFM_TEXT_DOMAIN ); ?></button>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders the HTML structure for the success confirmation modal.
	 * This method is hooked into `wp_footer`.
	 */
	public function render_confirmation_modal() {
		?>
		<div id="sfm-confirmation-modal" class="sfm-modal" style="display:none;">
			<div class="sfm-modal-content sfm-success-modal-content">
				<span class="sfm-modal-close">&times;</span>
				<div class="sfm-success-icon">
					<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M0 0h24v24H0z" fill="none"/><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
				</div>
				<h3 id="sfm-confirmation-title"><?php esc_html_e( 'Success!', SFM_TEXT_DOMAIN ); ?></h3>
				<p id="sfm-confirmation-message"></p>
			</div>
		</div>
		<?php
	}
}

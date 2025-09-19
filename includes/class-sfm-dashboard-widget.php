<?php
/**
 * Dashboard Widget Class for Strategicli Family Money.
 *
 * This class handles the registration and display of a custom dashboard widget
 * that shows the running allowance totals for each child.
 *
 * @package StrategicliFamilyMoney
 */

namespace Strategicli\FamilyMoney;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dashboard_Widget class.
 */
class Dashboard_Widget {

	/**
	 * Static instance of the class.
	 *
	 * @var Dashboard_Widget|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance of the class.
	 *
	 * @return Dashboard_Widget
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
	 * Registers hooks for dashboard widget initialization and enqueueing scripts/styles.
	 */
	private function __construct() {
		add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_dashboard_widget_scripts' ) );
	}

	/**
	 * Adds the Strategicli Family Money dashboard widget.
	 */
	public function add_dashboard_widget() {
		wp_add_dashboard_widget(
			'sfm_dashboard_widget',                       // Widget slug.
			__( 'Strategicli Family Money Balances', SFM_TEXT_DOMAIN ), // Widget title.
			array( $this, 'render_dashboard_widget' ),     // Callback for the widget content.
			null,                                         // Optional callback for widget control (settings form).
			null                                          // Optional arguments.
		);
	}

	/**
	 * Renders the content of the dashboard widget.
	 * Displays a list of children with their current balances.
	 */
	public function render_dashboard_widget() {
		// Get all children.
		$children = get_posts(
			array(
				'post_type'      => 'sfm_child',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'fields'         => 'ids', // Get only IDs for performance.
			)
		);

		// Ensure the Money class is loaded to use its get_child_balance method.
		$money_instance = Money::get_instance();

		?>
		<div class="sfm-dashboard-widget-content">
			<?php
			if ( $children ) {
				echo '<ul class="sfm-dashboard-child-list">';
				foreach ( $children as $child_id ) {
					$child_name = get_the_title( $child_id );
					$balance    = $money_instance->get_child_balance( $child_id );
					?>
					<li class="sfm-dashboard-child-item">
						<span class="sfm-dashboard-child-name"><?php echo esc_html( $child_name ); ?>:</span>
						<span class="sfm-dashboard-child-balance <?php echo ( $balance < 0 ) ? 'sfm-negative-balance' : ''; ?>">
							<?php echo '$' . number_format( (float) $balance, 2 ); ?>
						</span>
					</li>
					<?php
				}
				echo '</ul>';
			} else {
				?>
				<p class="sfm-dashboard-no-children"><?php esc_html_e( 'No children added yet. Please add children in the Strategicli Family Money > Children section.', SFM_TEXT_DOMAIN ); ?></p>
				<?php
			}
			?>
			<p class="sfm-dashboard-add-transaction-link">
				<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=sfm_transaction' ) ); ?>" class="button button-primary">
					<?php esc_html_e( 'Add New Transaction', SFM_TEXT_DOMAIN ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Enqueues scripts and styles specifically for the dashboard widget.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public function enqueue_dashboard_widget_scripts( $hook ) {
		// Only load on the dashboard page.
		if ( 'index.php' !== $hook ) {
			return;
		}

		// Enqueue the admin CSS (can be reused for dashboard widget styling).
		wp_enqueue_style(
			'sfm-admin-style',
			SFM_PLUGIN_URL . 'assets/css/sfm-admin.css',
			array(),
			SFM_PLUGIN_VERSION
		);

		// If specific JS is needed for the dashboard widget, enqueue it here.
		// For now, no specific JS is required for this widget, it's purely display.
		// wp_enqueue_script(
		// 	'sfm-dashboard-script',
		// 	SFM_PLUGIN_URL . 'assets/js/sfm-admin.js', // Or a dedicated dashboard.js
		// 	array( 'jquery' ),
		// 	SFM_PLUGIN_VERSION,
		// 	true
		// );
	}
}


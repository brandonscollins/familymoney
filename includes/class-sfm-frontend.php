<?php
// includes/class-sfm-frontend.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SFM_Frontend {

    protected static $instance = null;

    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // Register shortcodes.
        add_shortcode( 'sfm_money_dashboard', array( $this, 'render_money_dashboard_shortcode' ) );
        add_shortcode( 'sfm_money_form', array( $this, 'render_money_form_shortcode' ) );
    }

    /**
     * Renders the main allowance dashboard widget.
     * Shortcode: [sfm_money_dashboard theme="dark"]
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output for the dashboard.
     */
    public function render_money_dashboard_shortcode( $atts ) {
        // Parse shortcode attributes.
        $atts = shortcode_atts(
            array(
                'theme' => 'light', // Default theme is 'light'.
            ),
            $atts,
            'sfm_money_dashboard'
        );

        // Start output buffering to capture HTML.
        ob_start();

        $children = SFM_Child_Manager::get_children(); // Get all children.
        $is_dark_mode = ( 'dark' === $atts['theme'] );
        $wrapper_class = 'sfm-wrapper';
        if ( $is_dark_mode ) {
            $wrapper_class .= ' sfm-dark-mode'; // Add dark mode class if requested.
        }
        ?>
        <div class="<?php echo esc_attr( $wrapper_class ); ?>">
            <h3 class="sfm-title"><?php _e( 'Family Allowance Balances', 'strategicli-family-money' ); ?></h3>
            <div class="sfm-children-list">
                <?php if ( ! empty( $children ) ) : ?>
                    <?php foreach ( $children as $child ) :
                        $balance = $this->calculate_child_balance( $child->term_id );
                        ?>
                        <div class="sfm-child-item">
                            <span class="sfm-child-name"><?php echo esc_html( $child->name ); ?>:</span>
                            <span class="sfm-child-balance"><?php echo esc_html( '$' . number_format( $balance, 2 ) ); ?></span>
                            <button class="sfm-view-transactions-button" data-child-id="<?php echo esc_attr( $child->term_id ); ?>" data-child-name="<?php echo esc_attr( $child->name ); ?>">
                                <?php _e( 'View History', 'strategicli-family-money' ); ?>
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php else : ?>
                    <p><?php _e( 'No children added yet. Please add children in the plugin settings (Settings > Family Money).', 'strategicli-family-money' ); ?></p>
                <?php endif; ?>
            </div>

            <div id="sfm-transaction-modal" class="sfm-modal">
                <div class="sfm-modal-content">
                    <span class="sfm-close-button">&times;</span>
                    <h3 id="sfm-modal-child-name"></h3>
                    <div id="sfm-modal-transactions"></div>
                    <button id="sfm-load-more-transactions" class="sfm-button" style="display:none;"><?php _e('Load More', 'strategicli-family-money'); ?></button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean(); // Return the buffered HTML.
    }

    /**
     * Calculates the current balance for a given child.
     *
     * @param int $child_id The term ID of the child (from sfm_child taxonomy).
     * @return float The calculated balance.
     */
    private function calculate_child_balance( $child_id ) {
        $balance = 0.00;

        // Query all transactions for the specified child.
        $args = array(
            'post_type'      => 'sfm_transaction',
            'post_status'    => 'private', // Only consider private transactions.
            'posts_per_page' => -1,        // Retrieve all matching transactions.
            'fields'         => 'ids',     // Only fetch post IDs for performance.
            'tax_query'      => array(
                array(
                    'taxonomy' => 'sfm_child',
                    'field'    => 'term_id',
                    'terms'    => $child_id,
                ),
            ),
            'meta_query' => array( // Ensure amounts and types exist.
                'relation' => 'AND',
                array(
                    'key'     => '_sfm_amount',
                    'compare' => 'EXISTS',
                ),
                array(
                    'key'     => '_sfm_type',
                    'compare' => 'EXISTS',
                ),
            ),
        );

        $transaction_ids = get_posts( $args );

        if ( ! empty( $transaction_ids ) ) {
            foreach ( $transaction_ids as $transaction_id ) {
                $amount = (float) get_post_meta( $transaction_id, '_sfm_amount', true );
                $type = get_post_meta( $transaction_id, '_sfm_type', true );

                if ( 'deposit' === $type ) {
                    $balance += $amount;
                } elseif ( 'withdrawal' === $type ) {
                    $balance -= $amount;
                }
            }
        }

        return $balance;
    }

    /**
     * Renders the transaction entry form.
     * Shortcode: [sfm_money_form]
     *
     * @return string HTML output for the form.
     */
    public function render_money_form_shortcode() {
        ob_start(); // Start output buffering.

        $children = SFM_Child_Manager::get_children(); // Get all children for the dropdown.
        ?>
        <div class="sfm-form-wrapper">
            <h3 class="sfm-form-title"><?php _e( 'Record New Transaction', 'strategicli-family-money' ); ?></h3>
            <form id="sfm-transaction-form" class="sfm-form">
                <p>
                    <label for="sfm_form_child_id"><?php _e( 'Select Child:', 'strategicli-family-money' ); ?></label><br>
                    <select id="sfm_form_child_id" name="sfm_child_id" required>
                        <option value="">-- <?php _e( 'Select a Child', 'strategicli-family-money' ); ?> --</option>
                        <?php
                        if ( ! empty( $children ) ) {
                            foreach ( $children as $child ) {
                                echo '<option value="' . esc_attr( $child->term_id ) . '">' . esc_html( $child->name ) . '</option>';
                            }
                        } else {
                            echo '<option value="" disabled>' . __( 'No children configured. Please add in settings.', 'strategicli-family-money' ) . '</option>';
                        }
                        ?>
                    </select>
                </p>
                <p>
                    <label for="sfm_form_amount"><?php _e( 'Amount (USD):', 'strategicli-family-money' ); ?></label><br>
                    <input type="number" step="0.01" min="0.01" id="sfm_form_amount" name="sfm_amount" required placeholder="e.g., 5.00" />
                </p>
                <p>
                    <label for="sfm_form_type"><?php _e( 'Transaction Type:', 'strategicli-family-money' ); ?></label><br>
                    <select id="sfm_form_type" name="sfm_type" required>
                        <option value="deposit"><?php _e( 'Deposit', 'strategicli-family-money' ); ?></option>
                        <option value="withdrawal"><?php _e( 'Withdrawal', 'strategicli-family-money' ); ?></option>
                    </select>
                </p>
                <p>
                    <label for="sfm_form_description"><?php _e( 'Description/Note:', 'strategicli-family-money' ); ?></label><br>
                    <textarea id="sfm_form_description" name="sfm_description" rows="3" placeholder="<?php esc_attr_e('e.g., Weekly allowance, Snack money', 'strategicli-family-money'); ?>"></textarea>
                </p>
                <p>
                    <button type="submit" class="sfm-button"><?php _e( 'Record Transaction', 'strategicli-family-money' ); ?></button>
                </p>
                <div id="sfm-form-message"></div>
            </form>
        </div>
        <?php
        return ob_get_clean(); // Return the buffered HTML.
    }
}
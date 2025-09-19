<?php
// includes/class-sfm-transaction-cpt.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SFM_Transaction_CPT {

    protected static $instance = null;

    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action( 'init', array( $this, 'register_cpt_and_taxonomy' ) );
        add_action( 'add_meta_boxes', array( $this, 'add_transaction_meta_box' ) );
        add_action( 'save_post_sfm_transaction', array( $this, 'save_transaction_meta' ), 10, 2 ); // Priority 10, 2 args.
        add_filter( 'manage_sfm_transaction_posts_columns', array( $this, 'set_custom_columns' ) );
        add_action( 'manage_sfm_transaction_posts_custom_column', array( $this, 'render_custom_columns' ), 10, 2 );
        add_filter( 'manage_edit-sfm_transaction_sortable_columns', array( $this, 'register_sortable_columns' ) );
        add_action( 'pre_get_posts', array( $this, 'sort_custom_columns' ) );
        add_filter( 'post_row_actions', array( $this, 'remove_cpt_row_actions' ), 10, 2 );
        add_filter( 'bulk_actions-edit-sfm_transaction', array( $this, 'remove_cpt_bulk_actions' ) );
        add_filter( 'views_edit-sfm_transaction', array( $this, 'remove_cpt_views' ) );
    }

    /**
     * Registers the 'sfm_transaction' custom post type.
     * This method is also called during plugin activation.
     */
    public static function register_cpt_and_taxonomy() {
		// error_log('DEBUG: register_cpt_and_taxonomy() started.'); // ERROR CHECKING
		
        $labels = array(
            'name'                  => _x( 'Transactions', 'Post Type General Name', 'strategicli-family-money' ),
            'singular_name'         => _x( 'Transaction', 'Post Type Singular Name', 'strategicli-family-money' ),
            'menu_name'             => __( 'Family Money', 'strategicli-family-money' ), // Main admin menu item.
            'name_admin_bar'        => __( 'Transaction', 'strategicli-family-money' ),
            'archives'              => __( 'Transaction Archives', 'strategicli-family-money' ),
            'attributes'            => __( 'Transaction Attributes', 'strategicli-family-money' ),
            'parent_item_colon'     => __( 'Parent Item:', 'strategicli-family-money' ),
            'all_items'             => __( 'All Transactions', 'strategicli-family-money' ),
            'add_new_item'          => __( 'Add New Transaction', 'strategicli-family-money' ),
            'add_new'               => __( 'Add New', 'strategicli-family-money' ),
            'new_item'              => __( 'New Transaction', 'strategicli-family-money' ),
            'edit_item'             => __( 'Edit Transaction', 'strategicli-family-money' ),
            'update_item'           => __( 'Update Transaction', 'strategicli-family-money' ),
            'view_item'             => __( 'View Transaction', 'strategicli-family-money' ),
            'view_items'            => __( 'View Transactions', 'strategicli-family-money' ),
            'search_items'          => __( 'Search Transaction', 'strategicli-family-money' ),
            'not_found'             => __( 'Not found', 'strategicli-family-money' ),
            'not_found_in_trash'    => __( 'Not found in Trash', 'strategicli-family-money' ),
            'featured_image'        => __( 'Featured Image', 'strategicli-family-money' ),
            'set_featured_image'    => __( 'Set featured image', 'strategicli-family-money' ),
            'remove_featured_image' => __( 'Remove featured image', 'strategicli-family-money' ),
            'use_featured_image'    => __( 'Use as featured image', 'strategicli-family-money' ),
            'insert_into_item'      => __( 'Insert into transaction', 'strategicli-family-money' ),
            'uploaded_to_this_item' => __( 'Uploaded to this transaction', 'strategicli-family-money' ),
            'items_list'            => __( 'Transactions list', 'strategicli-family-money' ),
            'items_list_navigation' => __( 'Transactions list navigation', 'strategicli-family-money' ),
            'filter_items_list'     => __( 'Filter transactions list', 'strategicli-family-money' ),
        );
        $args = array(
            'label'                 => __( 'Transaction', 'strategicli-family-money' ),
            'description'           => __( 'Family allowance transactions', 'strategicli-family-money' ),
            'labels'                => $labels,
            'supports'              => array( 'title' ), // We'll use 'title' for the description.
            'taxonomies'            => array( 'sfm_child' ), // Link to our custom taxonomy.
            'hierarchical'          => false,
            'public'                => false, // Important: Not publicly queryable via standard WP queries.
            'show_ui'               => true, // Show in admin UI.
            'show_in_menu'          => true, // Show in admin menu.
            'menu_position'         => 25, // Position in admin menu.
            'menu_icon'             => 'dashicons-money-alt', // Money icon for admin menu.
            'show_in_admin_bar'     => false, // No shortcut in admin bar.
            'show_in_nav_menus'     => false, // Not selectable in navigation menus.
            'can_export'            => true,
            'has_archive'           => false,
            'exclude_from_search'   => true, // Exclude from site search.
            'publicly_queryable'    => false,
            'rewrite'               => false, // No rewrite rules for single transactions.
            'capability_type'       => 'post', // Uses standard post capabilities.
            'query_var'             => false, // Don't allow querying by this CPT in URL.
            'map_meta_cap'          => true, // Map meta capabilities to primitive capabilities.
        );
        register_post_type( 'sfm_transaction', $args );
		// error_log('DEBUG: sfm_transaction CPT registered.'); // DEBUGGING

        // Ensure the child taxonomy is also registered when CPT is registered.
        // This makes sure it's available for selection when adding/editing transactions.
        SFM_Child_Manager::register_child_taxonomy();
		// error_log('DEBUG: sfm_child taxonomy registration attempted via Child Manager instance.'); // DEBUGGING

		// error_log('DEBUG: register_cpt_and_taxonomy() finished.'); // DEBUGGING
    }

    /**
     * Adds the meta box for transaction details on the 'sfm_transaction' edit screen.
     */
    public function add_transaction_meta_box() {
        add_meta_box(
            'sfm_transaction_details',                      // Unique ID of the meta box.
            __( 'Transaction Details', 'strategicli-family-money' ), // Title of the meta box.
            array( $this, 'render_transaction_meta_box' ),  // Callback function to render the box content.
            'sfm_transaction',                              // Post type to display the meta box on.
            'normal',                                       // Context (where on the screen).
            'high'                                          // Priority within the context.
        );
    }

    /**
     * Renders the HTML content for the transaction details meta box.
     *
     * @param WP_Post $post The current post object.
     */
    public function render_transaction_meta_box( $post ) {
        // Add a nonce field for security.
        wp_nonce_field( 'sfm_save_transaction_meta', 'sfm_transaction_nonce' );

        // Retrieve existing meta values.
        $amount      = get_post_meta( $post->ID, '_sfm_amount', true );
        $type        = get_post_meta( $post->ID, '_sfm_type', true ); // 'deposit' or 'withdrawal'.
        $description = get_post_meta( $post->ID, '_sfm_description', true );

        // Get the child term associated with this transaction.
        $terms = get_the_terms( $post->ID, 'sfm_child' );
        $selected_child_id = ! empty( $terms ) && ! is_wp_error( $terms ) ? $terms[0]->term_id : 0;

        // Get all available children from our taxonomy.
        $children = SFM_Child_Manager::get_children();
        ?>
        <p>
            <label for="sfm_amount"><?php _e( 'Amount (USD):', 'strategicli-family-money' ); ?></label><br>
            <input type="number" step="0.01" id="sfm_amount" name="sfm_amount" value="<?php echo esc_attr( $amount ); ?>" required min="0.01" />
        </p>
        <p>
            <label for="sfm_type"><?php _e( 'Type:', 'strategicli-family-money' ); ?></label><br>
            <select id="sfm_type" name="sfm_type" required>
                <option value="deposit" <?php selected( $type, 'deposit' ); ?>><?php _e( 'Deposit', 'strategicli-family-money' ); ?></option>
                <option value="withdrawal" <?php selected( $type, 'withdrawal' ); ?>><?php _e( 'Withdrawal', 'strategicli-family-money' ); ?></option>
            </select>
        </p>
        <p>
            <label for="sfm_child_id"><?php _e( 'Child:', 'strategicli-family-money' ); ?></label><br>
            <select id="sfm_child_id" name="sfm_child_id" required>
                <option value="">-- <?php _e( 'Select a Child', 'strategicli-family-money' ); ?> --</option>
                <?php
                if ( ! empty( $children ) ) {
                    foreach ( $children as $child ) {
                        echo '<option value="' . esc_attr( $child->term_id ) . '" ' . selected( $selected_child_id, $child->term_id, false ) . '>' . esc_html( $child->name ) . '</option>';
                    }
                }
                ?>
            </select>
        </p>
        <p>
            <label for="sfm_description"><?php _e( 'Description/Note:', 'strategicli-family-money' ); ?></label><br>
            <textarea id="sfm_description" name="sfm_description" rows="3" style="width:100%;" placeholder="<?php esc_attr_e('e.g., Weekly allowance, New video game, Chores', 'strategicli-family-money'); ?>"><?php echo esc_textarea( $description ); ?></textarea>
        </p>
        <?php
    }

    /**
     * Saves the custom meta data when an 'sfm_transaction' post is saved.
     *
     * @param int     $post_id The ID of the post being saved.
     * @param WP_Post $post    The post object.
     */
    public function save_transaction_meta( $post_id, $post ) {
        // Check if our nonce is set and valid for security.
        if ( ! isset( $_POST['sfm_transaction_nonce'] ) || ! wp_verify_nonce( $_POST['sfm_transaction_nonce'], 'sfm_save_transaction_meta' ) ) {
            return $post_id;
        }

        // If this is an autosave, our form has not been submitted, so we don't want to do anything.
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return $post_id;
        }

        // Check the user's permissions.
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return $post_id;
        }

        // Sanitize and save the meta fields.
        // Amount.
        if ( isset( $_POST['sfm_amount'] ) ) {
            $amount = floatval( $_POST['sfm_amount'] );
            // Ensure amount is not negative.
            $amount = max( 0.01, $amount );
            update_post_meta( $post_id, '_sfm_amount', $amount );
        }

        // Type (deposit/withdrawal).
        if ( isset( $_POST['sfm_type'] ) && in_array( $_POST['sfm_type'], array( 'deposit', 'withdrawal' ) ) ) {
            $type = sanitize_text_field( $_POST['sfm_type'] );
            update_post_meta( $post_id, '_sfm_type', $type );
        }

        // Description/Note.
        $description = '';
        if ( isset( $_POST['sfm_description'] ) ) {
            $description = sanitize_textarea_field( $_POST['sfm_description'] );
            update_post_meta( $post_id, '_sfm_description', $description );
        }
        
        // Also set the post_title to the description if provided, otherwise a default.
        // Important: Remove and re-add the action to prevent infinite loops when updating the post.
        remove_action( 'save_post_sfm_transaction', array( $this, 'save_transaction_meta' ), 10, 2 );
        wp_update_post( array(
            'ID'         => $post_id,
            'post_title' => ! empty( $description ) ? $description : __( '(No Description)', 'strategicli-family-money' ),
        ) );
        add_action( 'save_post_sfm_transaction', array( $this, 'save_transaction_meta' ), 10, 2 );


        // Save the child taxonomy term.
        if ( isset( $_POST['sfm_child_id'] ) && ! empty( $_POST['sfm_child_id'] ) ) {
            $child_id = intval( $_POST['sfm_child_id'] );
            // Use wp_set_object_terms to assign the child term to the transaction.
            // The third argument (false) means replace existing terms, not append.
            wp_set_object_terms( $post_id, array( $child_id ), 'sfm_child', false );
        } else {
            // If no child is selected, remove any existing child terms.
            wp_set_object_terms( $post_id, null, 'sfm_child' );
        }
    }

    /**
     * Sets custom columns for the 'sfm_transaction' list table in the admin.
     *
     * @param array $columns Existing columns.
     * @return array Modified columns.
     */
    public function set_custom_columns( $columns ) {
        $new_columns = array();
        $new_columns['cb']          = $columns['cb']; // Checkbox column.
        $new_columns['sfm_child']   = __( 'Child', 'strategicli-family-money' );
        $new_columns['sfm_amount']  = __( 'Amount (USD)', 'strategicli-family-money' );
        $new_columns['sfm_type']    = __( 'Type', 'strategicli-family-money' );
        $new_columns['title']       = __( 'Description/Note', 'strategicli-family-money' ); // Rename 'Title' to 'Description'.
        $new_columns['date']        = __( 'Date', 'strategicli-family-money' ); // Standard date column.

        return $new_columns;
    }

    /**
     * Renders the content for custom columns in the 'sfm_transaction' list table.
     *
     * @param string $column   The name of the column to render.
     * @param int    $post_id  The ID of the current post.
     */
    public function render_custom_columns( $column, $post_id ) {
        switch ( $column ) {
            case 'sfm_child':
                $terms = get_the_terms( $post_id, 'sfm_child' );
                if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
                    // Display the name of the first child term.
                    echo esc_html( $terms[0]->name );
                } else {
                    echo '&mdash;'; // Display a dash if no child is assigned.
                }
                break;
            case 'sfm_amount':
                $amount = get_post_meta( $post_id, '_sfm_amount', true );
                echo esc_html( '$' . number_format( (float)$amount, 2 ) );
                break;
            case 'sfm_type':
                $type = get_post_meta( $post_id, '_sfm_type', true );
                echo esc_html( ucfirst( $type ) ); // Capitalize 'deposit' or 'withdrawal'.
                break;
        }
    }

    /**
     * Registers custom columns as sortable in the 'sfm_transaction' list table.
     *
     * @param array $columns The existing sortable columns.
     * @return array Modified sortable columns.
     */
    public function register_sortable_columns( $columns ) {
        $columns['sfm_amount'] = 'sfm_amount'; // Make 'Amount' column sortable by '_sfm_amount' meta key.
        return $columns;
    }

    /**
     * Modifies the main query to handle custom column sorting for 'sfm_transaction'.
     *
     * @param WP_Query $query The current WP_Query object.
     */
    public function sort_custom_columns( $query ) {
        // Ensure we are in the admin area, on the main query, and for our custom post type.
        if ( ! is_admin() || ! $query->is_main_query() || 'sfm_transaction' !== $query->get( 'post_type' ) ) {
            return;
        }

        // If 'orderby' is set to 'sfm_amount', modify the query to sort by the meta key.
        if ( 'sfm_amount' === $query->get( 'orderby' ) ) {
            $query->set( 'meta_key', '_sfm_amount' ); // The meta key to sort by.
            $query->set( 'orderby', 'meta_value_num' ); // Sort numerically by meta value.
        }
    }

    /**
     * Removes unwanted row actions (e.g., 'View', 'Quick Edit') from the 'sfm_transaction' list table.
     * Since transactions are private and not publicly viewable, these actions are not relevant.
     *
     * @param array   $actions The array of row actions.
     * @param WP_Post $post    The current post object.
     * @return array Modified row actions.
     */
    public function remove_cpt_row_actions( $actions, $post ) {
        if ( 'sfm_transaction' === $post->post_type ) {
            unset( $actions['view'] ); // No frontend view for transactions.
            unset( $actions['inline hide-if-no-js'] ); // Remove 'Quick Edit'.
        }
        return $actions;
    }

    /**
     * Removes unwanted bulk actions (e.g., 'Edit') from the 'sfm_transaction' list table.
     *
     * @param array $actions The array of bulk actions.
     * @return array Modified bulk actions.
     */
    public function remove_cpt_bulk_actions( $actions ) {
        if ( isset( $actions['edit'] ) ) {
            unset( $actions['edit'] ); // Remove 'Edit' bulk action.
        }
        return $actions;
    }

    /**
     * Removes unwanted views (e.g., 'Published', 'Draft') from the 'sfm_transaction' list table.
     * Since all transactions are 'private', these views are redundant.
     *
     * @param array $views The array of list table views.
     * @return array Modified views.
     */
    public function remove_cpt_views( $views ) {
        if ( isset( $views['publish'] ) ) {
            unset( $views['publish'] );
        }
        if ( isset( $views['draft'] ) ) {
            unset( $views['draft'] );
        }
        if ( isset( $views['pending'] ) ) {
            unset( $views['pending'] );
        }
        if ( isset( $views['trash'] ) ) {
            unset( $views['trash'] );
        }
        // Keep 'All' view as it's useful.
        return $views;
    }
}
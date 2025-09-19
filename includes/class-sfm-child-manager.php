<?php
// includes/class-sfm-child-manager.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SFM_Child_Manager {

    protected static $instance = null;

    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // Register the custom taxonomy on WordPress 'init' hook.
        add_action( 'init', array( $this, 'register_child_taxonomy' ) );
    }

    /**
     * Registers the 'sfm_child' custom taxonomy.
     * This taxonomy will be used to categorize transactions by child.
     */
    public static function register_child_taxonomy() {
        $labels = array(
            'name'                       => _x( 'Children', 'taxonomy general name', 'strategicli-family-money' ),
            'singular_name'              => _x( 'Child', 'taxonomy singular name', 'strategicli-family-money' ),
            'search_items'               => __( 'Search Children', 'strategicli-family-money' ),
            'popular_items'              => __( 'Popular Children', 'strategicli-family-money' ),
            'all_items'                  => __( 'All Children', 'strategicli-family-money' ),
            'parent_item'                => null, // Not hierarchical (no parent/child terms).
            'parent_item_colon'          => null,
            'edit_item'                  => __( 'Edit Child', 'strategicli-family-money' ),
            'update_item'                => __( 'Update Child', 'strategicli-family-money' ),
            'add_new_item'               => __( 'Add New Child', 'strategicli-family-money' ),
            'new_item_name'              => __( 'New Child Name', 'strategicli-family-money' ),
            'separate_items_with_commas' => __( 'Separate children with commas', 'strategicli-family-money' ),
            'add_or_remove_items'        => __( 'Add or remove children', 'strategicli-family-money' ),
            'choose_from_most_used'      => __( 'Choose from the most used children', 'strategicli-family-money' ),
            'not_found'                  => __( 'No children found.', 'strategicli-family-money' ),
            'menu_name'                  => __( 'Children', 'strategicli-family-money' ),
        );

        $args = array(
            'hierarchical'          => false, // Children names are flat (tags-like).
            'labels'                => $labels,
            'show_ui'               => true,  // Show in admin UI.
            'show_admin_column'     => true,  // Show in admin post list table.
            'query_var'             => true,  // Allow queries by this taxonomy.
            'rewrite'               => false, // No public rewrite rules needed for this internal taxonomy.
            'capabilities' => array( // Control capabilities for managing terms.
                'manage_terms' => 'manage_options', // Only administrators can add/edit/delete children.
                'edit_terms'   => 'manage_options',
                'delete_terms' => 'manage_options',
                'assign_terms' => 'edit_posts',     // Any user who can edit posts can assign children to transactions.
            ),
            // The 'show_in_menu' will be set to false as we'll embed its UI in our main settings page.
            'show_in_menu' => false,
            'show_in_nav_menus' => false,
            'public'       => false, // Not publicly queryable.
            'meta_box_cb'  => false, // We'll manually handle the meta box for CPT.
        );

        // Register the taxonomy and associate it with our 'sfm_transaction' CPT.
        register_taxonomy( 'sfm_child', 'sfm_transaction', $args );
    }

    /**
     * Get all registered children (taxonomy terms).
     *
     * @return array Array of WP_Term objects, or empty array if none.
     */
    public static function get_children() {
        return get_terms( array(
            'taxonomy'   => 'sfm_child',
            'hide_empty' => false, // Include children even if they currently have no transactions.
            'orderby'    => 'name',
            'order'      => 'ASC',
        ) );
    }
}
<?php
// uninstall.php

// if uninstall.php is not called by WordPress, die
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    die;
}

/**
 * Delete all plugin data: custom post type entries and taxonomy terms.
 * This ensures a clean removal of all data created by the plugin.
 */

// Delete all 'sfm_transaction' custom post type entries.
$transactions = get_posts( array(
    'post_type'   => 'sfm_transaction',
    'numberposts' => -1, // Get all posts.
    'post_status' => 'any', // Include all statuses (private, trash, etc.).
    'fields'      => 'ids', // Only get post IDs for efficiency.
    'perm'        => 'any', // Needed to query all posts regardless of permission.
) );

if ( $transactions ) {
    foreach ( $transactions as $post_id ) {
        // Force delete each post, bypassing the trash.
        wp_delete_post( $post_id, true );
    }
}

// Delete all 'sfm_child' taxonomy terms.
$terms = get_terms( array(
    'taxonomy'   => 'sfm_child',
    'hide_empty' => false, // Ensure all terms are retrieved, even if not assigned to posts.
    'fields'     => 'ids', // Only get term IDs for efficiency.
) );

if ( $terms && ! is_wp_error( $terms ) ) {
    foreach ( $terms as $term_id ) {
        // Delete each term.
        wp_delete_term( $term_id, 'sfm_child' );
    }
}

// Optionally, delete any plugin options stored in wp_options table.
// If you add options (e.g., general settings for the plugin itself),
// you would delete them here using delete_option('your_option_name');
// For this plugin, currently no direct options are saved, only children terms and transactions.
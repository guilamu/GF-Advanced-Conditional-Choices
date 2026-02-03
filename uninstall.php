<?php
/**
 * Uninstall GF Advanced Conditional Choices
 *
 * Fired when the plugin is uninstalled.
 *
 * @package GF_Advanced_Conditional_Choices
 * @since   1.0.0
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/**
 * Clean up plugin data.
 *
 * Note: This plugin stores conditional logic data within Gravity Forms
 * field choices (in the form meta). We do NOT remove this data on uninstall
 * because:
 *
 * 1. The data is stored within GF form meta, not in separate options
 * 2. Users may want to reinstall the plugin later with their logic intact
 * 3. The conditional logic data is harmless when the plugin is inactive
 *
 * If you need to completely remove all conditional choice logic from forms,
 * you can use the following code snippet:
 *
 * ```php
 * $forms = GFAPI::get_forms();
 * foreach ( $forms as $form ) {
 *     $modified = false;
 *     foreach ( $form['fields'] as &$field ) {
 *         if ( ! empty( $field->choices ) ) {
 *             foreach ( $field->choices as &$choice ) {
 *                 if ( isset( $choice['conditionalLogic'] ) ) {
 *                     unset( $choice['conditionalLogic'] );
 *                     $modified = true;
 *                 }
 *             }
 *         }
 *     }
 *     if ( $modified ) {
 *         GFAPI::update_form( $form );
 *     }
 * }
 * ```
 */

// Remove any transients the plugin may have created.
global $wpdb;

$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        '%_transient_gf_acc_%',
        '%_transient_timeout_gf_acc_%'
    )
);

// Remove GitHub updater cache if exists.
delete_site_option( 'gf_acc_github_update_cache' );
delete_option( 'gf_acc_github_update_cache' );

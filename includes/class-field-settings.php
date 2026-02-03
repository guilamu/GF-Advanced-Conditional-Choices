<?php
/**
 * GF Advanced Conditional Choices - Field Settings
 *
 * Handles the integration with Gravity Forms field settings UI.
 *
 * @package GF_Advanced_Conditional_Choices
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class GF_ACC_Field_Settings
 *
 * Manages field settings hooks for the form editor.
 */
class GF_ACC_Field_Settings {

    /**
     * Initialize the field settings hooks.
     *
     * @return void
     */
    public static function init(): void {
        // The main logic button injection is handled via JavaScript in admin.js
        // This class provides helper hooks for the form editor
        
        add_filter( 'gform_tooltips', array( __CLASS__, 'add_tooltips' ) );
    }

    /**
     * Add custom tooltips.
     *
     * @param array $tooltips Existing tooltips.
     * @return array Modified tooltips.
     */
    public static function add_tooltips( array $tooltips ): array {
        $tooltips['acc_choice_logic'] = sprintf(
            '<h6>%s</h6>%s',
            esc_html__( 'Choice Conditional Logic', 'gf-advanced-conditional-choices' ),
            esc_html__( 'Configure conditions to show or hide this individual choice based on values from other fields.', 'gf-advanced-conditional-choices' )
        );

        return $tooltips;
    }
}

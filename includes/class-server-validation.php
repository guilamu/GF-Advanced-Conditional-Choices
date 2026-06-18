<?php
/**
 * GF Advanced Conditional Choices - Server Validation
 *
 * Handles server-side validation and sanitization of conditional choices.
 *
 * @package GF_Advanced_Conditional_Choices
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class GF_ACC_Server_Validation
 *
 * Validates that submitted choices are visible and sanitizes hidden choice values.
 */
class GF_ACC_Server_Validation {

    /**
     * Initialize the server validation hooks.
     *
     * @return void
     */
    public static function init(): void {
        add_filter( 'gform_validation', array( __CLASS__, 'validate_conditional_choices' ), 20 );
        add_action( 'gform_pre_submission', array( __CLASS__, 'sanitize_hidden_choices' ), 20 );
    }

    /**
     * Check if a form has any fields with conditional choice logic enabled.
     *
     * Used to skip processing on forms that don't use the plugin,
     * preventing false validation failures caused by sanitization mismatches.
     *
     * @param array $form The form object.
     * @return bool True if the form uses conditional choices on at least one choice.
     */
    private static function form_has_conditional_choices( array $form ): bool {
        if ( ! isset( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
            return false;
        }

        foreach ( $form['fields'] as $field ) {
            if ( ! in_array( $field->type, GF_Advanced_Conditional_Choices::SUPPORTED_FIELD_TYPES, true ) ) {
                continue;
            }

            if ( ! isset( $field->choices ) || ! is_array( $field->choices ) ) {
                continue;
            }

            foreach ( $field->choices as $choice ) {
                $logic = $choice['conditionalLogic'] ?? null;

                if ( $logic && ! empty( $logic['enabled'] ) ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Validate that submitted choice values are currently visible.
     *
     * @param array $validation_result The validation result.
     * @return array Modified validation result.
     */
    public static function validate_conditional_choices( array $validation_result ): array {
        $form = $validation_result['form'];

        // Skip forms that don't use conditional choices at all.
        if ( ! self::form_has_conditional_choices( $form ) ) {
            return $validation_result;
        }

        $values = self::get_submitted_values( $form );

        foreach ( $form['fields'] as &$field ) {
            // Skip unsupported field types
            if ( ! in_array( $field->type, GF_Advanced_Conditional_Choices::SUPPORTED_FIELD_TYPES, true ) ) {
                continue;
            }

            // Skip if field has no choices
            if ( ! isset( $field->choices ) || ! is_array( $field->choices ) ) {
                continue;
            }

            // Skip fields that don't have any conditional choice logic
            if ( ! self::field_has_conditional_choices( $field ) ) {
                continue;
            }

            // Skip if field is hidden by field-level conditional logic
            if ( GFFormsModel::is_field_hidden( $form, $field, array() ) ) {
                continue;
            }

            // Get visible choices
            $visible_choices = GF_ACC_Choice_Conditions::get_visible_choices( $form, $field, $values );

            // Get submitted value(s)
            $submitted_value = self::get_field_submitted_value( $field );

            // Check if required field has no visible choices
            if ( $field->isRequired && empty( $visible_choices ) ) {
                $field->failed_validation  = true;
                $field->validation_message = esc_html__(
                    'No options available. Please adjust your previous selections.',
                    'gf-advanced-conditional-choices'
                );
                $validation_result['is_valid'] = false;
                continue;
            }

            // Check if submitted value(s) are in visible choices
            if ( ! empty( $submitted_value ) ) {
                $submitted_values = is_array( $submitted_value ) ? $submitted_value : array( $submitted_value );

                foreach ( $submitted_values as $value ) {
                    if ( empty( $value ) ) {
                        continue;
                    }

                    if ( ! in_array( $value, $visible_choices, true ) ) {
                        $field->failed_validation  = true;
                        $field->validation_message = esc_html__(
                            'Please select a valid option.',
                            'gf-advanced-conditional-choices'
                        );
                        $validation_result['is_valid'] = false;
                        break;
                    }
                }
            }
        }

        $validation_result['form'] = $form;

        return $validation_result;
    }

    /**
     * Sanitize hidden choice values from the submission.
     *
     * @param array $form The form object.
     * @return void
     */
    public static function sanitize_hidden_choices( array $form ): void {
        // Skip forms that don't use conditional choices at all.
        if ( ! self::form_has_conditional_choices( $form ) ) {
            return;
        }

        $values = self::get_submitted_values( $form );

        foreach ( $form['fields'] as $field ) {
            // Skip unsupported field types
            if ( ! in_array( $field->type, GF_Advanced_Conditional_Choices::SUPPORTED_FIELD_TYPES, true ) ) {
                continue;
            }

            // Skip if field has no choices
            if ( ! isset( $field->choices ) || ! is_array( $field->choices ) ) {
                continue;
            }

            // Skip fields that don't have any conditional choice logic
            if ( ! self::field_has_conditional_choices( $field ) ) {
                continue;
            }

            // Get visible choices
            $visible_choices = GF_ACC_Choice_Conditions::get_visible_choices( $form, $field, $values );

            // Handle different field types
            if ( in_array( $field->type, array( 'checkbox', 'multi_choice' ), true ) ) {
                // Checkbox and Multi Choice fields use input_{field_id}_{choice_number}
                foreach ( $field->inputs as $input ) {
                    $input_name = 'input_' . str_replace( '.', '_', $input['id'] );
                    
                    if ( isset( $_POST[ $input_name ] ) ) {
                        $value = wp_unslash( $_POST[ $input_name ] );
                        
                        if ( ! in_array( $value, $visible_choices, true ) ) {
                            $_POST[ $input_name ] = '';
                        }
                    }
                }
            } else {
                // Radio, Select, Multi-select
                $input_name = 'input_' . $field->id;

                if ( isset( $_POST[ $input_name ] ) ) {
                    $value = $_POST[ $input_name ];

                    if ( is_array( $value ) ) {
                        // Multi-select
                        $_POST[ $input_name ] = array_filter(
                            wp_unslash( $value ),
                            function( $v ) use ( $visible_choices ) {
                                return in_array( $v, $visible_choices, true );
                            }
                        );
                    } else {
                        // Radio, Select
                        $value = wp_unslash( $value );
                        
                        if ( ! in_array( $value, $visible_choices, true ) ) {
                            $_POST[ $input_name ] = '';
                        }
                    }
                }
            }
        }
    }

    /**
     * Check if a specific field has any choices with conditional logic enabled.
     *
     * @param GF_Field $field The field object.
     * @return bool True if at least one choice has conditional logic enabled.
     */
    private static function field_has_conditional_choices( $field ): bool {
        if ( ! isset( $field->choices ) || ! is_array( $field->choices ) ) {
            return false;
        }

        foreach ( $field->choices as $choice ) {
            $logic = $choice['conditionalLogic'] ?? null;

            if ( $logic && ! empty( $logic['enabled'] ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all submitted field values from the form.
     *
     * Values are unslashed but NOT passed through sanitize_text_field(),
     * because that function strips tabs and compresses whitespace,
     * which would cause mismatches when comparing against choice values
     * stored in the form definition.
     *
     * @param array $form The form object.
     * @return array Associative array of field_id => value.
     */
    private static function get_submitted_values( array $form ): array {
        $values = array();

        foreach ( $form['fields'] as $field ) {
            if ( in_array( $field->type, array( 'checkbox', 'multi_choice' ), true ) && isset( $field->inputs ) ) {
                // Checkbox and Multi Choice fields have multiple inputs
                foreach ( $field->inputs as $input ) {
                    $input_name = 'input_' . str_replace( '.', '_', $input['id'] );
                    
                    if ( isset( $_POST[ $input_name ] ) ) {
                        $values[ $input['id'] ] = wp_unslash( $_POST[ $input_name ] );
                    }
                }
            } else {
                // Standard single-value fields
                $input_name = 'input_' . $field->id;
                
                if ( isset( $_POST[ $input_name ] ) ) {
                    $value = $_POST[ $input_name ];
                    
                    if ( is_array( $value ) ) {
                        $values[ $field->id ] = wp_unslash( $value );
                    } else {
                        $values[ $field->id ] = wp_unslash( $value );
                    }
                }
            }
        }

        return $values;
    }

    /**
     * Get the submitted value for a specific field.
     *
     * Values are unslashed but NOT passed through sanitize_text_field(),
     * because that function strips tabs and compresses whitespace,
     * which would cause mismatches when comparing against choice values
     * stored in the form definition.
     *
     * @param GF_Field $field The field object.
     * @return mixed The submitted value.
     */
    private static function get_field_submitted_value( $field ): mixed {
        if ( in_array( $field->type, array( 'checkbox', 'multi_choice' ), true ) && isset( $field->inputs ) ) {
            $values = array();
            
            foreach ( $field->inputs as $input ) {
                $input_name = 'input_' . str_replace( '.', '_', $input['id'] );
                
                if ( isset( $_POST[ $input_name ] ) && ! empty( $_POST[ $input_name ] ) ) {
                    $values[] = wp_unslash( $_POST[ $input_name ] );
                }
            }
            
            return $values;
        }

        $input_name = 'input_' . $field->id;

        if ( ! isset( $_POST[ $input_name ] ) ) {
            return '';
        }

        $value = $_POST[ $input_name ];

        if ( is_array( $value ) ) {
            return wp_unslash( $value );
        }

        return wp_unslash( $value );
    }
}

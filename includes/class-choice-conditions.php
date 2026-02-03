<?php
/**
 * GF Advanced Conditional Choices - Choice Conditions
 *
 * Utility class for evaluating choice conditional logic.
 *
 * @package GF_Advanced_Conditional_Choices
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class GF_ACC_Choice_Conditions
 *
 * Provides methods for evaluating choice conditional logic.
 */
class GF_ACC_Choice_Conditions {

    /**
     * Check if a choice should be visible based on its conditional logic.
     *
     * @param array $form   The form object.
     * @param array $logic  The conditional logic configuration.
     * @param array $values Submitted field values (field_id => value).
     * @return bool True if the choice should be visible.
     */
    public static function is_choice_visible( array $form, array $logic, array $values ): bool {
        if ( empty( $logic['enabled'] ) ) {
            return true; // No logic = always visible
        }

        if ( empty( $logic['rules'] ) || ! is_array( $logic['rules'] ) ) {
            return true; // No rules = always visible
        }

        $results = array();

        foreach ( $logic['rules'] as $rule ) {
            $results[] = self::evaluate_rule( $rule, $values );
        }

        $logic_type = $logic['logicType'] ?? 'all';

        if ( 'all' === $logic_type ) {
            // AND: All rules must pass
            $conditions_met = ! in_array( false, $results, true );
        } else {
            // OR: At least one rule must pass
            $conditions_met = in_array( true, $results, true );
        }

        // Handle actionType: 'show' (default) = visible when conditions met
        // 'hide' = hidden when conditions met (visible when conditions NOT met)
        $action_type = $logic['actionType'] ?? 'show';

        if ( 'hide' === $action_type ) {
            return ! $conditions_met;
        }

        return $conditions_met;
    }

    /**
     * Evaluate a single rule.
     *
     * @param array $rule   The rule configuration.
     * @param array $values Submitted field values.
     * @return bool True if the rule passes.
     */
    public static function evaluate_rule( array $rule, array $values ): bool {
        $field_id   = $rule['fieldId'] ?? '';
        $operator   = $rule['operator'] ?? 'is';
        $rule_value = $rule['value'] ?? '';

        // Get the field value
        $field_value = self::get_field_value( $field_id, $values );

        return self::compare_values( $field_value, $operator, $rule_value );
    }

    /**
     * Get a field value from the values array.
     *
     * Handles checkbox fields which may have multiple sub-inputs.
     *
     * @param string $field_id The field ID.
     * @param array  $values   Submitted field values.
     * @return mixed The field value.
     */
    private static function get_field_value( string $field_id, array $values ): mixed {
        // Direct match
        if ( isset( $values[ $field_id ] ) ) {
            return $values[ $field_id ];
        }

        // Check for checkbox sub-inputs (e.g., field 5 has inputs 5.1, 5.2, 5.3)
        $base_id = (int) $field_id;
        $checkbox_values = array();

        foreach ( $values as $key => $value ) {
            // Match patterns like "5.1", "5.2", "5_1", "5_2"
            if ( preg_match( '/^' . $base_id . '[._]\d+$/', $key ) ) {
                if ( ! empty( $value ) ) {
                    $checkbox_values[] = $value;
                }
            }
        }

        if ( ! empty( $checkbox_values ) ) {
            return $checkbox_values;
        }

        return '';
    }

    /**
     * Compare field value against rule value using the specified operator.
     *
     * @param mixed  $field_value The field value.
     * @param string $operator    The comparison operator.
     * @param string $rule_value  The rule value to compare against.
     * @return bool True if the comparison passes.
     */
    public static function compare_values( mixed $field_value, string $operator, string $rule_value ): bool {
        // Handle array values (checkboxes, multi-select)
        if ( is_array( $field_value ) ) {
            return self::compare_array_value( $field_value, $operator, $rule_value );
        }

        // Normalize for string comparison
        $val    = strtolower( trim( (string) $field_value ) );
        $target = strtolower( trim( $rule_value ) );

        return match ( $operator ) {
            'is'           => $val === $target,
            'isnot'        => $val !== $target,
            'contains'     => str_contains( $val, $target ),
            'starts_with'  => str_starts_with( $val, $target ),
            'ends_with'    => str_ends_with( $val, $target ),
            '>'            => is_numeric( $field_value ) && is_numeric( $rule_value ) && (float) $field_value > (float) $rule_value,
            '<'            => is_numeric( $field_value ) && is_numeric( $rule_value ) && (float) $field_value < (float) $rule_value,
            '>='           => is_numeric( $field_value ) && is_numeric( $rule_value ) && (float) $field_value >= (float) $rule_value,
            '<='           => is_numeric( $field_value ) && is_numeric( $rule_value ) && (float) $field_value <= (float) $rule_value,
            'is_empty'     => $val === '',
            'is_not_empty' => $val !== '',
            default        => false,
        };
    }

    /**
     * Compare array values (for checkboxes and multi-select).
     *
     * @param array  $field_values Array of selected values.
     * @param string $operator     The comparison operator.
     * @param string $rule_value   The rule value to compare against.
     * @return bool True if the comparison passes.
     */
    private static function compare_array_value( array $field_values, string $operator, string $rule_value ): bool {
        // Normalize values
        $normalized = array_map( function( $v ) {
            return strtolower( trim( (string) $v ) );
        }, $field_values );

        $target = strtolower( trim( $rule_value ) );

        return match ( $operator ) {
            'is'           => in_array( $target, $normalized, true ),
            'isnot'        => ! in_array( $target, $normalized, true ),
            'contains'     => array_reduce( $normalized, fn( $carry, $v ) => $carry || str_contains( $v, $target ), false ),
            'is_empty'     => empty( array_filter( $normalized, fn( $v ) => $v !== '' ) ),
            'is_not_empty' => ! empty( array_filter( $normalized, fn( $v ) => $v !== '' ) ),
            default        => false,
        };
    }

    /**
     * Get all visible choices for a field based on current values.
     *
     * @param array    $form   The form object.
     * @param GF_Field $field  The field object.
     * @param array    $values Submitted field values.
     * @return array Array of visible choice values.
     */
    public static function get_visible_choices( array $form, $field, array $values ): array {
        $visible = array();

        if ( ! isset( $field->choices ) || ! is_array( $field->choices ) ) {
            return $visible;
        }

        foreach ( $field->choices as $choice ) {
            $logic = $choice['conditionalLogic'] ?? null;

            if ( ! $logic || empty( $logic['enabled'] ) ) {
                // No logic = always visible
                $visible[] = $choice['value'];
                continue;
            }

            if ( self::is_choice_visible( $form, $logic, $values ) ) {
                $visible[] = $choice['value'];
            }
        }

        return $visible;
    }
}

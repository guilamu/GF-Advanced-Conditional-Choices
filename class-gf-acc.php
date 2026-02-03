<?php
/**
 * GF Advanced Conditional Choices - Main Class
 *
 * @package GF_Advanced_Conditional_Choices
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class GF_Advanced_Conditional_Choices
 *
 * Main plugin class that coordinates all functionality.
 */
class GF_Advanced_Conditional_Choices {

    /**
     * Singleton instance.
     *
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * Field types that support conditional choices.
     *
     * @var array
     */
    public const SUPPORTED_FIELD_TYPES = array(
        'radio',
        'checkbox',
        'select',
        'multiselect',
        'multi_choice',
    );

    /**
     * Field types that can be used as triggers for conditions.
     *
     * @var array
     */
    public const TRIGGER_FIELD_TYPES = array(
        'text',
        'textarea',
        'select',
        'multiselect',
        'radio',
        'checkbox',
        'number',
        'date',
        'hidden',
        'calculation',
        'product',
        'total',
        'quantity',
        'price',
        'multi_choice',
    );

    /**
     * Available comparison operators.
     *
     * @var array
     */
    public const OPERATORS = array(
        'is'           => 'is',
        'isnot'        => 'is not',
        'contains'     => 'contains',
        'starts_with'  => 'starts with',
        'ends_with'    => 'ends with',
        '>'            => 'greater than',
        '<'            => 'less than',
        '>='           => 'greater or equal',
        '<='           => 'less or equal',
        'is_empty'     => 'is empty',
        'is_not_empty' => 'is not empty',
    );

    /**
     * Get singleton instance.
     *
     * @return self
     */
    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks.
     *
     * @return void
     */
    private function init_hooks(): void {
        // Initialize sub-components
        GF_ACC_Field_Settings::init();
        GF_ACC_Server_Validation::init();

        // Admin scripts - use both hooks for compatibility
        add_action( 'gform_editor_js', array( $this, 'enqueue_editor_scripts' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'maybe_enqueue_admin_scripts' ) );

        // Frontend scripts
        add_action( 'gform_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ), 10, 2 );
    }

    /**
     * Maybe enqueue admin scripts on GF form editor pages.
     *
     * @return void
     */
    public function maybe_enqueue_admin_scripts(): void {
        $screen = get_current_screen();
        if ( ! $screen ) {
            return;
        }

        // Only on GF form editor pages
        if ( strpos( $screen->id, 'gf_edit_forms' ) === false && strpos( $screen->id, 'toplevel_page_gf_edit_forms' ) === false ) {
            return;
        }

        wp_enqueue_style(
            'gf-acc-admin',
            GF_ACC_URL . 'assets/css/admin.css',
            array(),
            GF_ACC_VERSION
        );
    }

    /**
     * Enqueue scripts for the form editor.
     *
     * @return void
     */
    public function enqueue_editor_scripts(): void {
        wp_enqueue_style(
            'gf-acc-admin',
            GF_ACC_URL . 'assets/css/admin.css',
            array(),
            GF_ACC_VERSION
        );

        wp_enqueue_script(
            'gf-acc-admin',
            GF_ACC_URL . 'assets/js/admin.js',
            array( 'jquery', 'gform_form_admin' ),
            GF_ACC_VERSION,
            true
        );

        wp_localize_script( 'gf-acc-admin', 'gfACCAdmin', array(
            'operators'   => self::get_operators_for_js(),
            'triggerTypes' => self::TRIGGER_FIELD_TYPES,
            'i18n'        => array(
                'modalTitle'         => __( 'Conditional Logic for choice', 'gf-advanced-conditional-choices' ),
                'enableLabel'        => __( 'Enable Conditional Logic', 'gf-advanced-conditional-choices' ),
                'show'               => __( 'Show', 'gf-advanced-conditional-choices' ),
                'hide'               => __( 'Hide', 'gf-advanced-conditional-choices' ),
                'thisChoiceIf'       => __( 'this choice if', 'gf-advanced-conditional-choices' ),
                'ofTheFollowing'     => __( 'of the following match:', 'gf-advanced-conditional-choices' ),
                'all'                => __( 'All', 'gf-advanced-conditional-choices' ),
                'any'                => __( 'Any', 'gf-advanced-conditional-choices' ),
                'selectField'        => __( '— Select Field —', 'gf-advanced-conditional-choices' ),
                'valuePlaceholder'   => __( 'Value', 'gf-advanced-conditional-choices' ),
                'addRule'            => __( 'Add Rule', 'gf-advanced-conditional-choices' ),
                'cancel'             => __( 'Cancel', 'gf-advanced-conditional-choices' ),
                'saveLogic'          => __( 'Save Logic', 'gf-advanced-conditional-choices' ),
                'configureTooltip'   => __( 'Configure Choice Logic', 'gf-advanced-conditional-choices' ),
            ),
        ) );
    }

    /**
     * Enqueue frontend scripts.
     *
     * @param array $form    The form object.
     * @param bool  $is_ajax Whether this is an AJAX request.
     * @return void
     */
    public function enqueue_frontend_scripts( array $form, bool $is_ajax ): void {
        // Check if form has any conditional choices
        $logic_map = $this->build_logic_map( $form );

        if ( empty( $logic_map['fields'] ) ) {
            return;
        }

        wp_enqueue_script(
            'gf-acc-frontend',
            GF_ACC_URL . 'assets/js/frontend.js',
            array( 'jquery' ),
            GF_ACC_VERSION,
            true
        );

        wp_localize_script( 'gf-acc-frontend', 'gfACCData', $logic_map );
    }

    /**
     * Build the logic map for frontend JavaScript.
     *
     * @param array $form The form object.
     * @return array Logic map data.
     */
    public function build_logic_map( array $form ): array {
        $fields = array();

        if ( ! isset( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
            return array(
                'formId' => $form['id'] ?? 0,
                'fields' => $fields,
                'i18n'   => $this->get_frontend_i18n(),
            );
        }

        foreach ( $form['fields'] as $field ) {
            if ( ! $this->is_supported_field( $field ) ) {
                continue;
            }

            if ( ! isset( $field->choices ) || ! is_array( $field->choices ) ) {
                continue;
            }

            $choices_with_logic = array();

            foreach ( $field->choices as $choice ) {
                $logic = $choice['conditionalLogic'] ?? null;

                if ( ! $logic || empty( $logic['enabled'] ) ) {
                    continue;
                }

                $choices_with_logic[ $choice['value'] ] = array(
                    'enabled'    => true,
                    'actionType' => $logic['actionType'] ?? 'show',
                    'logicType'  => $logic['logicType'] ?? 'all',
                    'rules'      => $logic['rules'] ?? array(),
                );
            }

            if ( ! empty( $choices_with_logic ) ) {
                $fields[ (string) $field->id ] = array(
                    'type'    => $field->type,
                    'choices' => $choices_with_logic,
                );
            }
        }

        return array(
            'formId' => $form['id'] ?? 0,
            'fields' => $fields,
            'i18n'   => $this->get_frontend_i18n(),
        );
    }

    /**
     * Check if a field type is supported.
     *
     * @param GF_Field|object $field The field object.
     * @return bool
     */
    public function is_supported_field( $field ): bool {
        return in_array( $field->type, self::SUPPORTED_FIELD_TYPES, true );
    }

    /**
     * Check if a field type can be used as a trigger.
     *
     * @param GF_Field|object $field The field object.
     * @return bool
     */
    public function is_trigger_field( $field ): bool {
        return in_array( $field->type, self::TRIGGER_FIELD_TYPES, true );
    }

    /**
     * Get operators formatted for JavaScript.
     *
     * @return array
     */
    public static function get_operators_for_js(): array {
        $operators = array();

        foreach ( self::OPERATORS as $value => $label ) {
            $operators[] = array(
                'value' => $value,
                'label' => __( $label, 'gf-advanced-conditional-choices' ),
            );
        }

        return $operators;
    }

    /**
     * Get frontend i18n strings.
     *
     * @return array
     */
    private function get_frontend_i18n(): array {
        return array(
            'invalidSelection'    => __( 'Please select a valid option.', 'gf-advanced-conditional-choices' ),
            'noOptionsAvailable'  => __( 'No options available. Please adjust your previous selections.', 'gf-advanced-conditional-choices' ),
        );
    }
}

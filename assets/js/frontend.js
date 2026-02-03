/**
 * GF Advanced Conditional Choices - Frontend JavaScript
 *
 * Handles live form conditional logic evaluation.
 */

(function($) {
    'use strict';

    // Bail if data not available
    if (typeof gfACCData === 'undefined' || !gfACCData.fields) {
        return;
    }

    var formId = gfACCData.formId;
    var fieldsConfig = gfACCData.fields;
    var i18n = gfACCData.i18n;

    // Debounce timer
    var debounceTimer = null;

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        // Initial evaluation
        evaluateAllConditions();

        // Clear invalid pre-populated values
        clearInvalidPrepopulated();
    });

    /**
     * Listen for field changes
     */
    $(document).on('change input', '#gform_wrapper_' + formId + ' :input', function() {
        // Debounce to avoid excessive evaluations
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(evaluateAllConditions, 50);
    });

    /**
     * Listen for form render (AJAX, initial load)
     */
    $(document).on('gform_post_render', function(event, renderedFormId) {
        if (parseInt(renderedFormId) === parseInt(formId)) {
            evaluateAllConditions();
            clearInvalidPrepopulated();
        }
    });

    /**
     * Listen for page navigation in multi-page forms
     */
    $(document).on('gform_page_loaded', function(event, loadedFormId, currentPage) {
        if (parseInt(loadedFormId) === parseInt(formId)) {
            evaluateAllConditions();
            validateHiddenSelections();
        }
    });

    /**
     * Listen for GF conditional logic updates
     */
    $(document).on('gform_post_conditional_logic', function(event, affectedFormId, fields, isInit) {
        if (parseInt(affectedFormId) === parseInt(formId)) {
            evaluateAllConditions();
        }
    });

    /**
     * Evaluate all conditional choice logic
     */
    function evaluateAllConditions() {
        $.each(fieldsConfig, function(fieldId, fieldConfig) {
            // Skip if field is hidden by field-level conditional logic
            if (isFieldHidden(fieldId)) {
                return;
            }

            $.each(fieldConfig.choices, function(choiceValue, logic) {
                if (!logic.enabled) {
                    return;
                }

                var isVisible = evaluateLogic(logic);
                setChoiceVisibility(fieldId, fieldConfig.type, choiceValue, isVisible);
            });
        });
    }

    /**
     * Check if a field is hidden by Gravity Forms conditional logic
     */
    function isFieldHidden(fieldId) {
        var $field = $('#field_' + formId + '_' + fieldId);
        return $field.length === 0 || $field.css('display') === 'none' || $field.hasClass('gf_hidden');
    }

    /**
     * Evaluate logic configuration
     */
    function evaluateLogic(logic) {
        var results = [];

        for (var i = 0; i < logic.rules.length; i++) {
            results.push(evaluateRule(logic.rules[i]));
        }

        var conditionsMet;
        if (logic.logicType === 'all') {
            // AND: All rules must pass
            conditionsMet = results.indexOf(false) === -1;
        } else {
            // OR: At least one rule must pass
            conditionsMet = results.indexOf(true) !== -1;
        }

        // If actionType is 'hide', invert the result
        // Show: visible when conditions are met
        // Hide: hidden when conditions are met (visible when NOT met)
        if (logic.actionType === 'hide') {
            return !conditionsMet;
        }
        return conditionsMet;
    }

    /**
     * Evaluate a single rule
     */
    function evaluateRule(rule) {
        var fieldValue = getFieldValue(rule.fieldId);
        var ruleValue = rule.value || '';

        return compareValues(fieldValue, rule.operator, ruleValue);
    }

    /**
     * Get the current value of a field
     */
    function getFieldValue(fieldId) {
        // Standard input (text, number, hidden, date, select)
        var $input = $('#input_' + formId + '_' + fieldId);
        if ($input.length) {
            return $input.val();
        }

        // Radio buttons
        var $radio = $('input[name="input_' + fieldId + '"]:checked');
        if ($radio.length) {
            return $radio.val();
        }

        // Checkboxes (return array of selected values)
        var $checkboxes = $('input[name^="input_' + fieldId + '."]:checked, input[name^="input_' + fieldId + '_"]:checked');
        if ($checkboxes.length) {
            var values = [];
            $checkboxes.each(function() {
                values.push($(this).val());
            });
            return values;
        }

        // Multi-select
        var $multiSelect = $('#input_' + formId + '_' + fieldId);
        if ($multiSelect.is('select[multiple]')) {
            return $multiSelect.val() || [];
        }

        return '';
    }

    /**
     * Compare field value against rule value
     */
    function compareValues(fieldValue, operator, ruleValue) {
        // Handle array values (checkboxes, multi-select)
        if (Array.isArray(fieldValue)) {
            return compareArrayValue(fieldValue, operator, ruleValue);
        }

        // Normalize for string comparison
        var val = String(fieldValue || '').toLowerCase().trim();
        var target = String(ruleValue || '').toLowerCase().trim();

        switch (operator) {
            case 'is':
                return val === target;
            case 'isnot':
                return val !== target;
            case 'contains':
                return val.indexOf(target) !== -1;
            case 'starts_with':
                return val.indexOf(target) === 0;
            case 'ends_with':
                return val.slice(-target.length) === target;
            case '>':
                return parseFloat(fieldValue) > parseFloat(ruleValue);
            case '<':
                return parseFloat(fieldValue) < parseFloat(ruleValue);
            case '>=':
                return parseFloat(fieldValue) >= parseFloat(ruleValue);
            case '<=':
                return parseFloat(fieldValue) <= parseFloat(ruleValue);
            case 'is_empty':
                return val === '';
            case 'is_not_empty':
                return val !== '';
            default:
                return false;
        }
    }

    /**
     * Compare array values (checkboxes, multi-select)
     */
    function compareArrayValue(fieldValues, operator, ruleValue) {
        // Normalize values
        var normalized = fieldValues.map(function(v) {
            return String(v || '').toLowerCase().trim();
        });
        var target = String(ruleValue || '').toLowerCase().trim();

        switch (operator) {
            case 'is':
                return normalized.indexOf(target) !== -1;
            case 'isnot':
                return normalized.indexOf(target) === -1;
            case 'contains':
                return normalized.some(function(v) {
                    return v.indexOf(target) !== -1;
                });
            case 'is_empty':
                return normalized.filter(function(v) { return v !== ''; }).length === 0;
            case 'is_not_empty':
                return normalized.filter(function(v) { return v !== ''; }).length > 0;
            default:
                return false;
        }
    }

    /**
     * Set visibility of a choice
     */
    function setChoiceVisibility(fieldId, fieldType, choiceValue, isVisible) {
        var $container = $('#field_' + formId + '_' + fieldId);

        if (fieldType === 'radio' || fieldType === 'checkbox' || fieldType === 'multi_choice') {
            // Find the input and its container
            var $input = $container.find('input[value="' + CSS.escape(choiceValue) + '"]');
            var $choice = $input.closest('li, .gchoice');

            if (isVisible) {
                $choice.show().attr('data-acc-hidden', 'false');
            } else {
                $choice.hide().attr('data-acc-hidden', 'true');
            }
        } else if (fieldType === 'select' || fieldType === 'multiselect') {
            // Find the option
            var $option = $container.find('option[value="' + CSS.escape(choiceValue) + '"]');

            if (isVisible) {
                $option.prop('disabled', false).show();
            } else {
                $option.prop('disabled', true).hide();

                // Deselect if currently selected
                if ($option.is(':selected')) {
                    $option.prop('selected', false);
                    $option.closest('select').trigger('change');
                }
            }
        }
    }

    /**
     * Clear pre-populated values that should be hidden
     */
    function clearInvalidPrepopulated() {
        validateHiddenSelections();
    }

    /**
     * Validate and clear hidden selections
     * Called on page navigation and initial load
     */
    function validateHiddenSelections() {
        $.each(fieldsConfig, function(fieldId, fieldConfig) {
            if (fieldConfig.type === 'radio') {
                var $selected = $('input[name="input_' + fieldId + '"]:checked');
                if ($selected.length && $selected.closest('[data-acc-hidden="true"]').length) {
                    $selected.prop('checked', false);
                }
            } else if (fieldConfig.type === 'checkbox' || fieldConfig.type === 'multi_choice') {
                $('input[name^="input_' + fieldId + '."]:checked, input[name^="input_' + fieldId + '_"]:checked').each(function() {
                    if ($(this).closest('[data-acc-hidden="true"]').length) {
                        $(this).prop('checked', false);
                    }
                });
            }
            // Select/multiselect already handled in setChoiceVisibility
        });
    }

    /**
     * CSS.escape polyfill for older browsers
     */
    if (!window.CSS || !CSS.escape) {
        window.CSS = window.CSS || {};
        CSS.escape = function(value) {
            if (arguments.length === 0) {
                throw new TypeError('`CSS.escape` requires an argument.');
            }
            var string = String(value);
            var length = string.length;
            var index = -1;
            var codeUnit;
            var result = '';
            var firstCodeUnit = string.charCodeAt(0);

            while (++index < length) {
                codeUnit = string.charCodeAt(index);
                if (codeUnit === 0x0000) {
                    result += '\uFFFD';
                    continue;
                }
                if (
                    (codeUnit >= 0x0001 && codeUnit <= 0x001F) ||
                    codeUnit === 0x007F ||
                    (index === 0 && codeUnit >= 0x0030 && codeUnit <= 0x0039) ||
                    (index === 1 && codeUnit >= 0x0030 && codeUnit <= 0x0039 && firstCodeUnit === 0x002D)
                ) {
                    result += '\\' + codeUnit.toString(16) + ' ';
                    continue;
                }
                if (index === 0 && length === 1 && codeUnit === 0x002D) {
                    result += '\\' + string.charAt(index);
                    continue;
                }
                if (
                    codeUnit >= 0x0080 ||
                    codeUnit === 0x002D ||
                    codeUnit === 0x005F ||
                    (codeUnit >= 0x0030 && codeUnit <= 0x0039) ||
                    (codeUnit >= 0x0041 && codeUnit <= 0x005A) ||
                    (codeUnit >= 0x0061 && codeUnit <= 0x007A)
                ) {
                    result += string.charAt(index);
                    continue;
                }
                result += '\\' + string.charAt(index);
            }
            return result;
        };
    }

})(jQuery);

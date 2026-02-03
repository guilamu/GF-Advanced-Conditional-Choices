/**
 * GF Advanced Conditional Choices - Admin JavaScript
 *
 * Handles the form editor UI, modal, and saving logic.
 */

(function($) {
    'use strict';

    // Bail if not in form editor or data not available
    if (typeof gfACCAdmin === 'undefined') {
        return;
    }

    var i18n = gfACCAdmin.i18n;
    var operators = gfACCAdmin.operators;
    var triggerTypes = gfACCAdmin.triggerTypes;

    // Supported field types for conditional choices
    var supportedTypes = ['radio', 'checkbox', 'select', 'multiselect', 'multi_choice'];

    // Track current field for button injection
    var currentFieldId = null;

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        // Listen for field settings load event
        $(document).on('gform_load_field_settings', onFieldSettingsLoaded);

        // Also listen for clicks on choice rows in case they get regenerated
        $(document).on('DOMNodeInserted', '#field_choices', function() {
            setTimeout(injectButtonsToCurrentField, 50);
        });
    });

    /**
     * Handle field settings being loaded (when a field is selected)
     */
    function onFieldSettingsLoaded(event, field, form) {
        if (!field || supportedTypes.indexOf(field.type) === -1) {
            currentFieldId = null;
            return;
        }

        currentFieldId = field.id;

        // Delay to allow GF to render the choices UI
        setTimeout(function() {
            injectButtonsToCurrentField();
        }, 100);
    }

    /**
     * Inject logic buttons to the currently selected field's choices
     */
    function injectButtonsToCurrentField() {
        var field = GetSelectedField();

        if (!field || supportedTypes.indexOf(field.type) === -1) {
            return;
        }

        if (!field.choices || field.choices.length === 0) {
            return;
        }

        // Find the choices container - GF uses different selectors in different versions
        var $container = $('#field_choices');
        if (!$container.length) {
            $container = $('#gfield_settings_choices_container');
        }

        $container.find('li').each(function(index) {
            var $row = $(this);

            // Skip if not a choice row (must have input for label)
            if (!$row.find('input.field-choice-text, input.field-choice-input').length) {
                return;
            }

            // Prevent duplicates
            if ($row.find('.gf-acc-btn').length > 0) {
                return;
            }

            var choice = field.choices[index];
            if (!choice) {
                return;
            }

            var hasLogic = choice.conditionalLogic && choice.conditionalLogic.enabled;
            var activeClass = hasLogic ? 'active' : '';
            var ruleCount = hasLogic && choice.conditionalLogic.rules ? choice.conditionalLogic.rules.length : 0;
            var badgeHtml = ruleCount > 0 ? '<span class="acc-badge">' + ruleCount + '</span>' : '';

            var logicBtn = $(
                '<button type="button" class="gf-acc-btn ' + activeClass + '" ' +
                'title="' + i18n.configureTooltip + '" ' +
                'data-index="' + index + '">' +
                '<span class="dashicons dashicons-randomize"></span>' +
                badgeHtml +
                '</button>'
            );

            // Insert before the add button (+ sign)
            var $addBtn = $row.find('.gf_insert_field_choice, .gform-choice__button--add');
            if ($addBtn.length) {
                $addBtn.first().before(logicBtn);
            } else {
                $row.append(logicBtn);
            }

            // Bind click event directly to this button
            logicBtn.on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                
                handleButtonClick(index);
            });
        });
    }

    /**
     * Handle button click
     */
    function handleButtonClick(choiceIndex) {
        var field = GetSelectedField();

        if (!field || !field.choices || !field.choices[choiceIndex]) {
            return;
        }

        var choice = field.choices[choiceIndex];

        // Initialize logic structure
        var logic = choice.conditionalLogic || {
            enabled: false,
            logicType: 'all',
            rules: [{ fieldId: '', operator: 'is', value: '' }]
        };

        // Ensure rules array exists
        if (!logic.rules || logic.rules.length === 0) {
            logic.rules = [{ fieldId: '', operator: 'is', value: '' }];
        }

        openModal(field, choiceIndex, choice, logic);
    }

    /**
     * Open modal when logic button is clicked (delegated event as fallback)
     */
    $(document).on('click', '.gf-acc-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();

        var choiceIndex = $(this).data('index');
        handleButtonClick(choiceIndex);
    });

    /**
     * Open the configuration modal
     */
    function openModal(field, choiceIndex, choice, logic) {
        // Build rules HTML
        var rulesHtml = '';
        for (var i = 0; i < logic.rules.length; i++) {
            rulesHtml += getRuleRowHtml(field.id, logic.rules[i], i);
        }

        // Build modal HTML
        var modalHtml = 
            '<div id="gf-acc-modal-overlay"></div>' +
            '<div id="gf-acc-modal">' +
                '<div class="gf-acc-modal-header">' +
                    '<h2>' + i18n.modalTitle + ' <span class="choice-label">"' + escapeHtml(choice.text) + '"</span></h2>' +
                    '<button type="button" class="gform-button gf-acc-close-modal" aria-label="Close"><i class="gform-button__icon gform-icon gform-icon--delete"></i></button>' +
                '</div>' +
                '<div class="gf-acc-modal-body">' +
                    '<div class="acc-enable-row">' +
                        '<label class="acc-toggle-label">' + i18n.enableLabel + '</label>' +
                        '<label class="acc-toggle-switch">' +
                            '<input type="checkbox" id="acc-enabled" ' + (logic.enabled ? 'checked' : '') + '>' +
                            '<span class="acc-toggle-slider"></span>' +
                        '</label>' +
                    '</div>' +
                    '<div id="acc-rules-container" style="' + (logic.enabled ? '' : 'display:none;') + '">' +
                        '<div class="acc-logic-type-row">' +
                            '<select id="acc-action-type">' +
                                '<option value="show"' + (logic.actionType !== 'hide' ? ' selected' : '') + '>' + i18n.show + '</option>' +
                                '<option value="hide"' + (logic.actionType === 'hide' ? ' selected' : '') + '>' + i18n.hide + '</option>' +
                            '</select> ' +
                            i18n.thisChoiceIf + ' ' +
                            '<select id="acc-logic-type">' +
                                '<option value="all"' + (logic.logicType === 'all' ? ' selected' : '') + '>' + i18n.all + '</option>' +
                                '<option value="any"' + (logic.logicType === 'any' ? ' selected' : '') + '>' + i18n.any + '</option>' +
                            '</select> ' +
                            i18n.ofTheFollowing +
                        '</div>' +
                        '<div id="acc-rules-list">' +
                            rulesHtml +
                        '</div>' +
                    '</div>' +
                '</div>' +
                '<div class="gf-acc-modal-footer">' +
                    '<button type="button" class="button button-secondary gf-acc-close-modal">' + i18n.cancel + '</button>' +
                    '<button type="button" class="button button-primary" id="acc-save-logic" ' +
                        'data-field-id="' + field.id + '" data-index="' + choiceIndex + '">' + i18n.saveLogic + '</button>' +
                '</div>' +
            '</div>';

        $('body').append(modalHtml);

        // Update value input visibility based on operator
        $('#acc-rules-list .acc-operator-select').each(function() {
            updateValueInputVisibility($(this));
        });
    }

    /**
     * Generate HTML for a rule row
     */
    function getRuleRowHtml(currentFieldId, rule, index) {
        rule = rule || { fieldId: '', operator: 'is', value: '' };

        var fieldOptions = getFieldOptions(currentFieldId, rule.fieldId);
        var operatorOptions = getOperatorOptions(rule.operator);

        var valueHidden = (rule.operator === 'is_empty' || rule.operator === 'is_not_empty') ? 'hidden' : '';
        
        // Get value input HTML (dropdown if field has choices, text input otherwise)
        var valueInputHtml = getValueInputHtml(rule.fieldId, rule.value, valueHidden);

        return (
            '<div class="acc-rule-row" data-rule-index="' + index + '">' +
                '<select class="acc-field-select" aria-label="Field">' +
                    fieldOptions +
                '</select>' +
                '<select class="acc-operator-select" aria-label="Operator">' +
                    operatorOptions +
                '</select>' +
                '<span class="acc-value-container">' + valueInputHtml + '</span>' +
                '<span class="acc-rule-buttons">' +
                    '<button type="button" class="acc-add-rule-btn" aria-label="Add rule">' +
                        '<span class="dashicons dashicons-plus-alt2"></span>' +
                    '</button>' +
                    '<button type="button" class="acc-remove-rule" aria-label="Remove rule">' +
                        '<span class="dashicons dashicons-minus"></span>' +
                    '</button>' +
                '</span>' +
            '</div>'
        );
    }

    /**
     * Get the value input HTML - returns dropdown if field has choices, text input otherwise
     */
    function getValueInputHtml(fieldId, currentValue, hiddenClass) {
        hiddenClass = hiddenClass || '';
        currentValue = currentValue || '';
        
        // Find the trigger field
        var triggerField = null;
        if (fieldId && typeof form !== 'undefined' && form.fields) {
            for (var i = 0; i < form.fields.length; i++) {
                if (form.fields[i].id == fieldId) {
                    triggerField = form.fields[i];
                    break;
                }
            }
        }
        
        // If trigger field has choices, return a dropdown
        if (triggerField && triggerField.choices && triggerField.choices.length > 0) {
            var options = '<option value="">' + i18n.valuePlaceholder + '</option>';
            for (var j = 0; j < triggerField.choices.length; j++) {
                var choice = triggerField.choices[j];
                var selected = (choice.value === currentValue) ? ' selected' : '';
                options += '<option value="' + escapeHtml(choice.value) + '"' + selected + '>' + escapeHtml(choice.text) + '</option>';
            }
            return '<select class="acc-value-select ' + hiddenClass + '" aria-label="Value">' + options + '</select>';
        }
        
        // Otherwise return a text input
        return '<input type="text" class="acc-value-input ' + hiddenClass + '" ' +
            'placeholder="' + i18n.valuePlaceholder + '" ' +
            'value="' + escapeHtml(currentValue) + '" ' +
            'aria-label="Value">';
    }

    /**
     * Get field options HTML
     */
    function getFieldOptions(currentFieldId, selectedFieldId) {
        var options = '<option value="">' + i18n.selectField + '</option>';

        if (typeof form === 'undefined' || !form.fields) {
            return options;
        }

        for (var i = 0; i < form.fields.length; i++) {
            var f = form.fields[i];

            // Skip current field and non-trigger types
            if (f.id == currentFieldId) {
                continue;
            }

            if (triggerTypes.indexOf(f.type) === -1) {
                continue;
            }

            var selected = (f.id == selectedFieldId) ? ' selected' : '';
            var label = f.label || 'Field ' + f.id;

            options += '<option value="' + f.id + '"' + selected + '>' + escapeHtml(label) + '</option>';
        }

        return options;
    }

    /**
     * Get operator options HTML
     */
    function getOperatorOptions(selectedOperator) {
        var options = '';

        for (var i = 0; i < operators.length; i++) {
            var op = operators[i];
            var selected = (op.value === selectedOperator) ? ' selected' : '';
            options += '<option value="' + op.value + '"' + selected + '>' + escapeHtml(op.label) + '</option>';
        }

        return options;
    }

    /**
     * Update value input visibility based on operator
     */
    function updateValueInputVisibility($operatorSelect) {
        var operator = $operatorSelect.val();
        var $row = $operatorSelect.closest('.acc-rule-row');
        var $valueInput = $row.find('.acc-value-input, .acc-value-select');

        if (operator === 'is_empty' || operator === 'is_not_empty') {
            $valueInput.addClass('hidden').val('');
        } else {
            $valueInput.removeClass('hidden');
        }
    }

    /**
     * Toggle rules container visibility
     */
    $(document).on('change', '#acc-enabled', function() {
        $('#acc-rules-container').toggle($(this).is(':checked'));
    });

    /**
     * Update value input when operator changes
     */
    $(document).on('change', '.acc-operator-select', function() {
        updateValueInputVisibility($(this));
    });

    /**
     * Update value input when field selection changes
     */
    $(document).on('change', '.acc-field-select', function() {
        var $row = $(this).closest('.acc-rule-row');
        var fieldId = $(this).val();
        var operator = $row.find('.acc-operator-select').val();
        var hiddenClass = (operator === 'is_empty' || operator === 'is_not_empty') ? 'hidden' : '';
        
        // Replace the value input with appropriate type
        var newValueHtml = getValueInputHtml(fieldId, '', hiddenClass);
        $row.find('.acc-value-container').html(newValueHtml);
    });

    /**
     * Add new rule row (from bottom button)
     */
    $(document).on('click', '#acc-add-rule', function() {
        var field = GetSelectedField();
        var index = $('#acc-rules-list .acc-rule-row').length;
        var newRow = getRuleRowHtml(field.id, null, index);

        $('#acc-rules-list').append(newRow);
    });

    /**
     * Add new rule row (from inline + button)
     */
    $(document).on('click', '.acc-add-rule-btn', function() {
        var field = GetSelectedField();
        var $currentRow = $(this).closest('.acc-rule-row');
        var index = $('#acc-rules-list .acc-rule-row').length;
        var newRow = getRuleRowHtml(field.id, null, index);

        $currentRow.after(newRow);
    });

    /**
     * Remove rule row
     */
    $(document).on('click', '.acc-remove-rule', function() {
        var $rulesList = $('#acc-rules-list');

        if ($rulesList.find('.acc-rule-row').length > 1) {
            $(this).closest('.acc-rule-row').remove();
        } else {
            // Reset the last rule instead of removing
            var $row = $(this).closest('.acc-rule-row');
            $row.find('.acc-field-select').val('');
            $row.find('.acc-operator-select').val('is');
            // Reset value container to empty text input
            var newValueHtml = getValueInputHtml('', '', '');
            $row.find('.acc-value-container').html(newValueHtml);
        }
    });

    /**
     * Close modal - use event delegation with stopPropagation
     */
    $(document).on('click', '.gf-acc-close-modal', function(e) {
        e.preventDefault();
        e.stopPropagation();
        closeModal();
    });
    
    $(document).on('click', '#gf-acc-modal-overlay', function(e) {
        e.preventDefault();
        e.stopPropagation();
        closeModal();
    });

    /**
     * Close modal on Escape key
     */
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('#gf-acc-modal').length) {
            closeModal();
        }
    });

    /**
     * Close the modal with animation
     */
    function closeModal() {
        var $modal = $('#gf-acc-modal');
        var $overlay = $('#gf-acc-modal-overlay');
        
        if (!$modal.length) {
            return;
        }
        
        // Add closing class to trigger slide-out animation
        $modal.addClass('closing');
        $overlay.addClass('closing');
        
        // Remove elements after animation completes
        setTimeout(function() {
            $modal.remove();
            $overlay.remove();
        }, 190);
    }

    /**
     * Save logic
     */
    $(document).on('click', '#acc-save-logic', function() {
        var choiceIndex = $(this).data('index');
        var field = GetSelectedField();

        if (!field || !field.choices) {
            return;
        }

        // Build rules array
        var rules = [];
        $('#acc-rules-list .acc-rule-row').each(function() {
            // Get value from either text input or dropdown
            var $valueInput = $(this).find('.acc-value-input');
            var $valueSelect = $(this).find('.acc-value-select');
            var value = $valueInput.length ? $valueInput.val() : ($valueSelect.length ? $valueSelect.val() : '');
            
            rules.push({
                fieldId: $(this).find('.acc-field-select').val(),
                operator: $(this).find('.acc-operator-select').val(),
                value: value
            });
        });

        // Build logic object
        var newLogic = {
            enabled: $('#acc-enabled').is(':checked'),
            actionType: $('#acc-action-type').val(),
            logicType: $('#acc-logic-type').val(),
            rules: rules
        };

        // Save to field
        field.choices[choiceIndex].conditionalLogic = newLogic;

        // Update button state and badge
        var $btn = $('.gf-acc-btn[data-index="' + choiceIndex + '"]');
        if (newLogic.enabled) {
            $btn.addClass('active');
            // Update or add badge
            var ruleCount = rules.length;
            var $badge = $btn.find('.acc-badge');
            if ($badge.length) {
                $badge.text(ruleCount);
            } else {
                $btn.append('<span class="acc-badge">' + ruleCount + '</span>');
            }
        } else {
            $btn.removeClass('active');
            // Remove badge
            $btn.find('.acc-badge').remove();
        }

        // Close modal
        closeModal();

        // Refresh the field preview
        if (typeof RefreshSelectedFieldPreview === 'function') {
            RefreshSelectedFieldPreview();
        }
    });

    /**
     * Escape HTML entities
     */
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

})(jQuery);

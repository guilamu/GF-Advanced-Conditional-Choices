# GF Advanced Conditional Choices — Implementation Guide

> **Version:** 1.0.0  
> **Author:** Guilamu  
> **Repository:** https://github.com/guilamu/gf-advanced-conditional-choices  
> **License:** AGPL-3.0

---

## Table of Contents

1. [Overview](#overview)
2. [Requirements](#requirements)
3. [Directory Structure](#directory-structure)
4. [Feature Specifications](#feature-specifications)
5. [Data Structures](#data-structures)
6. [Admin UI Implementation](#admin-ui-implementation)
7. [Frontend Implementation](#frontend-implementation)
8. [Server-Side Validation](#server-side-validation)
9. [Integrations](#integrations)
10. [Localization](#localization)
11. [File Templates](#file-templates)

---

## Overview

**GF Advanced Conditional Choices** adds conditional logic to **individual choices** within Gravity Forms choice-based fields. Unlike GF's built-in conditional logic (which shows/hides entire fields), this plugin allows each choice option to have its own visibility rules.

### Use Cases

- Show premium options only when user selects "Premium" plan
- Display region-specific choices based on country selection
- Chain dependent dropdowns (Country → State → City)
- Show/hide product variations based on other selections

### Target Field Types (Fields That Get Conditional Choices)

| Field Type | GF Type | Selector |
|------------|---------|----------|
| Radio Buttons | `radio` | `input[type="radio"]` |
| Checkboxes | `checkbox` | `input[type="checkbox"]` |
| Dropdown | `select` | `select:not([multiple])` |
| Multi-Select | `multiselect` | `select[multiple]` |

### Trigger Field Types (Fields That Drive Conditions)

| Field Type | GF Type | Value Retrieval |
|------------|---------|-----------------|
| Text / Textarea | `text`, `textarea` | Direct `.val()` |
| Dropdown | `select` | Direct `.val()` |
| Multi-Select | `multiselect` | Array `.val()` |
| Radio Buttons | `radio` | `:checked` value |
| Checkboxes | `checkbox` | Array of `:checked` values |
| Number | `number` | Numeric `.val()` |
| Date | `date` | Date string `.val()` |
| Calculated | `calculation` | Computed value |
| Product / Pricing | `product`, `total` | Price value |
| Hidden | `hidden` | Direct `.val()` |

---

## Requirements

| Requirement | Minimum Version |
|-------------|-----------------|
| WordPress | 6.0 |
| PHP | 8.0 |
| Gravity Forms | 2.7 |

### Rationale

- **WordPress 6.0**: Block editor stability, better REST API
- **PHP 8.0**: Named arguments, null-safe operator, match expressions, better performance
- **Gravity Forms 2.7**: Modern field framework, improved JavaScript hooks, better conditional logic APIs

---

## Directory Structure

```
gf-advanced-conditional-choices/
├── gf-advanced-conditional-choices.php    # Main plugin file (bootstrap)
├── class-gf-acc.php                       # Core add-on class
├── uninstall.php                          # Cleanup on uninstall
├── LICENSE                                # AGPL-3.0
├── README.md                              # User documentation
├── IMPLEMENTATION.md                      # This file
├── assets/
│   ├── css/
│   │   └── admin.css                      # Modal & editor styles
│   └── js/
│       ├── admin.js                       # Form editor logic (modal, UI)
│       └── frontend.js                    # Live form logic (rules engine)
├── includes/
│   ├── class-choice-conditions.php        # Logic evaluation utilities
│   ├── class-field-settings.php           # GF field settings hooks
│   ├── class-server-validation.php        # Server-side validation
│   └── class-github-updater.php           # GitHub auto-updates
└── languages/
    ├── gf-advanced-conditional-choices.pot
    ├── gf-advanced-conditional-choices-en_US.po
    ├── gf-advanced-conditional-choices-fr_FR.po
    └── gf-advanced-conditional-choices-fr_FR.mo
```

---

## Feature Specifications

### Logic Configuration

| Feature | Specification |
|---------|---------------|
| Logic Types | `all` (AND), `any` (OR) |
| Multiple Rules | Yes, unlimited rules per choice |
| Cross-Field | Yes, reference any other field in form |
| Cross-Page | Yes, conditions work across multi-page forms |

### Operators

| Operator | Key | Description | Applicable Types |
|----------|-----|-------------|------------------|
| Is | `is` | Exact match (case-insensitive) | All |
| Is Not | `isnot` | Not exact match | All |
| Contains | `contains` | Substring match | Text, Textarea |
| Starts With | `starts_with` | Prefix match | Text, Textarea |
| Ends With | `ends_with` | Suffix match | Text, Textarea |
| Greater Than | `>` | Numeric comparison | Number, Calculated, Pricing |
| Less Than | `<` | Numeric comparison | Number, Calculated, Pricing |
| Greater or Equal | `>=` | Numeric comparison | Number, Calculated, Pricing |
| Less or Equal | `<=` | Numeric comparison | Number, Calculated, Pricing |
| Is Empty | `is_empty` | No value set | All |
| Is Not Empty | `is_not_empty` | Has any value | All |

### Behavior Rules

| Scenario | Behavior |
|----------|----------|
| **Selected choice becomes hidden** | Keep selected, show validation error on submit: "Please select a valid option" |
| **Multi-page: return to modified page** | Re-evaluate conditions, auto-deselect choices that are now hidden |
| **Pre-populated hidden choice** | Clear the pre-populated value, show no selection |
| **Required field, all choices hidden** | Validation error: "No options available. Please adjust your previous selections." |
| **Hidden choices** | Excluded from client and server validation |
| **Cascading dependencies** | Re-evaluate all downstream conditions when any trigger field changes |
| **Field hidden by field-level logic** | Choice-level logic does NOT run (field not rendered) |
| **Dynamic/external choices** | Fully compatible — logic UI works on dynamically populated choices |

### Visual Indicators

| Element | Indicator |
|---------|-----------|
| Choice with active logic | Blue networking icon (dashicons-networking) with `.active` class |
| Choice row in editor | Logic button before add/remove icons |
| Modal title | "Conditional Logic for: [Choice Label]" |

---

## Data Structures

### Choice Conditional Logic Object

Stored in `$field->choices[$index]['conditionalLogic']`:

```php
[
    'enabled'   => true,                    // bool: Logic active?
    'logicType' => 'all',                   // string: 'all' (AND) or 'any' (OR)
    'rules'     => [                        // array: Rule objects
        [
            'fieldId'  => '3',              // string: Trigger field ID
            'operator' => 'is',             // string: Operator key
            'value'    => 'Premium'         // string: Comparison value
        ],
        [
            'fieldId'  => '5',
            'operator' => '>',
            'value'    => '100'
        ]
    ]
]
```

### Frontend Logic Map

Passed via `wp_localize_script`:

```javascript
window.gfACCData = {
    formId: 1,
    fields: {
        "4": {                              // Target field ID
            type: "radio",                  // Field type
            choices: {
                "option_a": {               // Choice value
                    enabled: true,
                    logicType: "all",
                    rules: [
                        { fieldId: "3", operator: "is", value: "Premium" }
                    ]
                },
                "option_b": {
                    enabled: true,
                    logicType: "any",
                    rules: [
                        { fieldId: "5", operator: ">", value: "100" },
                        { fieldId: "6", operator: "is_not_empty", value: "" }
                    ]
                }
            }
        }
    },
    i18n: {
        invalidSelection: "Please select a valid option.",
        noOptionsAvailable: "No options available. Please adjust your previous selections."
    }
};
```

---

## Admin UI Implementation

### Form Editor Integration

#### Hook: `gform_field_standard_settings`

Add a "Conditional Choices" section to the field settings panel (priority 10, position 50).

```php
add_action('gform_field_standard_settings', function($position, $form_id) {
    if ($position === 50) {
        // Render settings UI container
    }
}, 10, 2);
```

#### Hook: `gform_editor_js`

Inject admin.js and admin.css for the form editor.

### Modal UI Components

```html
<div id="gf-acc-modal-overlay"></div>
<div id="gf-acc-modal">
    <div class="modal-header">
        <h2>Conditional Logic for: <span class="choice-label"></span></h2>
        <button class="close-modal" aria-label="Close">&times;</button>
    </div>
    <div class="modal-body">
        <!-- Enable toggle -->
        <div class="acc-enable-row">
            <label>
                <input type="checkbox" id="acc-enabled">
                Enable conditional logic for this choice
            </label>
        </div>
        
        <!-- Rules container (shown when enabled) -->
        <div id="acc-rules-container">
            <div class="acc-logic-type">
                Show this choice if
                <select id="acc-logic-type">
                    <option value="all">All</option>
                    <option value="any">Any</option>
                </select>
                of the following match:
            </div>
            
            <div id="acc-rules-list">
                <!-- Rule rows inserted dynamically -->
            </div>
            
            <button type="button" id="acc-add-rule" class="button">
                + Add Rule
            </button>
        </div>
    </div>
    <div class="modal-footer">
        <button class="button button-secondary close-modal">Cancel</button>
        <button class="button button-primary" id="acc-save">Save Logic</button>
    </div>
</div>
```

### Rule Row Template

```html
<div class="acc-rule-row" data-rule-index="0">
    <select class="acc-field-select" aria-label="Field">
        <option value="">— Select Field —</option>
        <!-- Populated dynamically -->
    </select>
    
    <select class="acc-operator-select" aria-label="Operator">
        <option value="is">is</option>
        <option value="isnot">is not</option>
        <option value="contains">contains</option>
        <option value="starts_with">starts with</option>
        <option value="ends_with">ends with</option>
        <option value=">">&gt;</option>
        <option value="<">&lt;</option>
        <option value=">=">&ge;</option>
        <option value="<=">&le;</option>
        <option value="is_empty">is empty</option>
        <option value="is_not_empty">is not empty</option>
    </select>
    
    <input type="text" class="acc-value-input" placeholder="Value" aria-label="Value">
    
    <button type="button" class="acc-remove-rule" aria-label="Remove rule">
        <span class="dashicons dashicons-trash"></span>
    </button>
</div>
```

### JavaScript Events (admin.js)

| Event | Handler |
|-------|---------|
| `gform_load_field_choices` | Inject logic button into each choice row |
| Click `.gf-acc-btn` | Open modal with choice data |
| Click `#acc-add-rule` | Add new rule row |
| Click `.acc-remove-rule` | Remove rule row (min 1) |
| Click `#acc-save` | Save logic to field, close modal, refresh preview |
| Change `#acc-enabled` | Toggle rules container visibility |
| Change `.acc-operator-select` | Hide value input for `is_empty`/`is_not_empty` |

---

## Frontend Implementation

### Script Loading

#### Hook: `gform_enqueue_scripts`

```php
add_action('gform_enqueue_scripts', function($form, $is_ajax) {
    // Check if form has any conditional choices
    if (!self::form_has_conditional_choices($form)) {
        return;
    }
    
    wp_enqueue_script('gf-acc-frontend', ...);
    wp_localize_script('gf-acc-frontend', 'gfACCData', self::build_logic_map($form));
}, 10, 2);
```

### Event Bindings (frontend.js)

```javascript
// Initial evaluation
jQuery(document).ready(evaluateAllConditions);

// Field value changes
jQuery(document).on('change input', '#gform_wrapper_' + formId + ' :input', debounce(evaluateAllConditions, 50));

// Multi-page form navigation
jQuery(document).on('gform_page_loaded', function(event, formId, currentPage) {
    evaluateAllConditions();
    validateHiddenSelections();
});

// Form render (AJAX, initial)
jQuery(document).on('gform_post_render', function(event, formId) {
    evaluateAllConditions();
    clearInvalidPrepopulated();
});

// Conditional logic applied (field visibility)
jQuery(document).on('gform_post_conditional_logic', function(event, formId, fields, isInit) {
    evaluateAllConditions();
});
```

### Core Functions

#### `evaluateAllConditions()`

```javascript
function evaluateAllConditions() {
    Object.keys(gfACCData.fields).forEach(function(fieldId) {
        var fieldConfig = gfACCData.fields[fieldId];
        
        // Skip if field is hidden by field-level conditional logic
        if (isFieldHidden(fieldId)) return;
        
        Object.keys(fieldConfig.choices).forEach(function(choiceValue) {
            var logic = fieldConfig.choices[choiceValue];
            if (!logic.enabled) return;
            
            var isVisible = evaluateLogic(logic);
            setChoiceVisibility(fieldId, fieldConfig.type, choiceValue, isVisible);
        });
    });
}
```

#### `evaluateLogic(logic)`

```javascript
function evaluateLogic(logic) {
    var results = logic.rules.map(function(rule) {
        return evaluateRule(rule);
    });
    
    if (logic.logicType === 'all') {
        return results.every(Boolean);
    } else {
        return results.some(Boolean);
    }
}
```

#### `evaluateRule(rule)`

```javascript
function evaluateRule(rule) {
    var fieldValue = getFieldValue(rule.fieldId);
    var ruleValue = rule.value || '';
    
    // Normalize for comparison
    var val = String(fieldValue || '').toLowerCase().trim();
    var target = String(ruleValue).toLowerCase().trim();
    
    switch (rule.operator) {
        case 'is':
            return val === target;
        case 'isnot':
            return val !== target;
        case 'contains':
            return val.includes(target);
        case 'starts_with':
            return val.startsWith(target);
        case 'ends_with':
            return val.endsWith(target);
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
```

#### `getFieldValue(fieldId)`

```javascript
function getFieldValue(fieldId) {
    var formId = gfACCData.formId;
    var $field = jQuery('#input_' + formId + '_' + fieldId);
    
    // Standard input (text, number, hidden, date, select)
    if ($field.length) {
        return $field.val();
    }
    
    // Radio buttons
    var $radio = jQuery('input[name="input_' + fieldId + '"]:checked');
    if ($radio.length) {
        return $radio.val();
    }
    
    // Checkboxes (return array)
    var $checkboxes = jQuery('input[name^="input_' + fieldId + '."]:checked');
    if ($checkboxes.length) {
        return $checkboxes.map(function() {
            return jQuery(this).val();
        }).get();
    }
    
    // Multi-select
    var $multiSelect = jQuery('#input_' + formId + '_' + fieldId + '');
    if ($multiSelect.is('select[multiple]')) {
        return $multiSelect.val() || [];
    }
    
    // Calculated field (check for gformCalculateTotalPrice result)
    var calcValue = jQuery('#input_' + formId + '_' + fieldId).val();
    if (calcValue !== undefined) {
        return calcValue;
    }
    
    return '';
}
```

#### `setChoiceVisibility(fieldId, fieldType, choiceValue, isVisible)`

```javascript
function setChoiceVisibility(fieldId, fieldType, choiceValue, isVisible) {
    var formId = gfACCData.formId;
    var $container = jQuery('#field_' + formId + '_' + fieldId);
    
    if (fieldType === 'radio' || fieldType === 'checkbox') {
        // Find the <li> containing this choice
        var $input = $container.find('input[value="' + CSS.escape(choiceValue) + '"]');
        var $choice = $input.closest('li, .gchoice');
        
        if (isVisible) {
            $choice.show().attr('data-acc-hidden', 'false');
        } else {
            $choice.hide().attr('data-acc-hidden', 'true');
        }
    } else if (fieldType === 'select' || fieldType === 'multiselect') {
        // Find the <option> with this value
        var $option = $container.find('option[value="' + CSS.escape(choiceValue) + '"]');
        
        if (isVisible) {
            $option.prop('disabled', false).show();
            // Some browsers need this hack for hidden options
            $option.wrap(function() {
                return jQuery(this).parent().is('span.acc-hidden') ? '' : null;
            }).unwrap('span.acc-hidden');
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
```

#### `validateHiddenSelections()`

Called on page navigation to handle invalidated choices:

```javascript
function validateHiddenSelections() {
    Object.keys(gfACCData.fields).forEach(function(fieldId) {
        var fieldConfig = gfACCData.fields[fieldId];
        var formId = gfACCData.formId;
        
        if (fieldConfig.type === 'radio') {
            var $selected = jQuery('input[name="input_' + fieldId + '"]:checked');
            if ($selected.length && $selected.closest('[data-acc-hidden="true"]').length) {
                // Deselect the now-hidden choice
                $selected.prop('checked', false);
            }
        } else if (fieldConfig.type === 'checkbox') {
            jQuery('input[name^="input_' + fieldId + '."]:checked').each(function() {
                if (jQuery(this).closest('[data-acc-hidden="true"]').length) {
                    jQuery(this).prop('checked', false);
                }
            });
        }
        // Select/multiselect already handled in setChoiceVisibility
    });
}
```

#### `clearInvalidPrepopulated()`

Called on initial form render:

```javascript
function clearInvalidPrepopulated() {
    // Same logic as validateHiddenSelections but runs on gform_post_render
    validateHiddenSelections();
}
```

---

## Server-Side Validation

### Hook: `gform_validation`

```php
add_filter('gform_validation', [self::class, 'validate_conditional_choices'], 20);

public static function validate_conditional_choices($validation_result) {
    $form = $validation_result['form'];
    
    foreach ($form['fields'] as &$field) {
        if (!self::is_choice_field($field)) {
            continue;
        }
        
        if (!isset($field->choices) || !is_array($field->choices)) {
            continue;
        }
        
        // Skip if field is hidden by field-level conditional logic
        if (GFFormsModel::is_field_hidden($form, $field, [])) {
            continue;
        }
        
        $submitted_value = rgpost('input_' . $field->id);
        $visible_choices = self::get_visible_choices($form, $field);
        
        // Check if submitted value is in visible choices
        if (!empty($submitted_value)) {
            $submitted_values = is_array($submitted_value) ? $submitted_value : [$submitted_value];
            
            foreach ($submitted_values as $value) {
                if (!in_array($value, $visible_choices, true)) {
                    $field->failed_validation = true;
                    $field->validation_message = esc_html__(
                        'Please select a valid option.',
                        'gf-advanced-conditional-choices'
                    );
                    $validation_result['is_valid'] = false;
                    break;
                }
            }
        }
        
        // Check if required field has no visible choices
        if ($field->isRequired && empty($visible_choices)) {
            $field->failed_validation = true;
            $field->validation_message = esc_html__(
                'No options available. Please adjust your previous selections.',
                'gf-advanced-conditional-choices'
            );
            $validation_result['is_valid'] = false;
        }
    }
    
    $validation_result['form'] = $form;
    return $validation_result;
}
```

### Hook: `gform_pre_submission`

Strip hidden choice values before entry creation:

```php
add_action('gform_pre_submission', [self::class, 'sanitize_hidden_choices'], 20);

public static function sanitize_hidden_choices($form) {
    foreach ($form['fields'] as $field) {
        if (!self::is_choice_field($field)) {
            continue;
        }
        
        $visible_choices = self::get_visible_choices($form, $field);
        $input_name = 'input_' . $field->id;
        
        if (isset($_POST[$input_name])) {
            $value = $_POST[$input_name];
            
            if (is_array($value)) {
                $_POST[$input_name] = array_filter($value, function($v) use ($visible_choices) {
                    return in_array($v, $visible_choices, true);
                });
            } else {
                if (!in_array($value, $visible_choices, true)) {
                    $_POST[$input_name] = '';
                }
            }
        }
    }
}
```

### `get_visible_choices($form, $field)`

Evaluate which choices are currently visible based on submitted form values:

```php
private static function get_visible_choices($form, $field): array {
    $visible = [];
    
    foreach ($field->choices as $choice) {
        $logic = $choice['conditionalLogic'] ?? null;
        
        if (!$logic || empty($logic['enabled'])) {
            // No logic = always visible
            $visible[] = $choice['value'];
            continue;
        }
        
        if (self::evaluate_choice_logic($form, $logic)) {
            $visible[] = $choice['value'];
        }
    }
    
    return $visible;
}

private static function evaluate_choice_logic($form, array $logic): bool {
    $results = [];
    
    foreach ($logic['rules'] as $rule) {
        $results[] = self::evaluate_rule($form, $rule);
    }
    
    if ($logic['logicType'] === 'all') {
        return !in_array(false, $results, true);
    } else {
        return in_array(true, $results, true);
    }
}

private static function evaluate_rule($form, array $rule): bool {
    $field_id = $rule['fieldId'];
    $operator = $rule['operator'];
    $rule_value = $rule['value'] ?? '';
    
    // Get submitted value for the trigger field
    $field_value = self::get_submitted_field_value($form, $field_id);
    
    // Normalize
    $val = strtolower(trim((string) $field_value));
    $target = strtolower(trim((string) $rule_value));
    
    return match ($operator) {
        'is' => $val === $target,
        'isnot' => $val !== $target,
        'contains' => str_contains($val, $target),
        'starts_with' => str_starts_with($val, $target),
        'ends_with' => str_ends_with($val, $target),
        '>' => (float) $field_value > (float) $rule_value,
        '<' => (float) $field_value < (float) $rule_value,
        '>=' => (float) $field_value >= (float) $rule_value,
        '<=' => (float) $field_value <= (float) $rule_value,
        'is_empty' => $val === '',
        'is_not_empty' => $val !== '',
        default => false,
    };
}

private static function get_submitted_field_value($form, $field_id) {
    // Handle complex field IDs (e.g., 5.3 for name field)
    $input_name = 'input_' . $field_id;
    
    // Check for checkbox fields (input_5_1, input_5_2, etc.)
    $base_id = (int) $field_id;
    $checkbox_values = [];
    
    foreach ($_POST as $key => $value) {
        if (preg_match('/^input_' . $base_id . '_\d+$/', $key)) {
            $checkbox_values[] = $value;
        }
    }
    
    if (!empty($checkbox_values)) {
        return $checkbox_values;
    }
    
    return rgpost($input_name) ?? '';
}
```

---

## Integrations

### GitHub Auto-Updater

Use the standard GitHub updater class from the reference. Configure:

```php
private const GITHUB_USER = 'guilamu';
private const GITHUB_REPO = 'gf-advanced-conditional-choices';
private const PLUGIN_FILE = 'gf-advanced-conditional-choices/gf-advanced-conditional-choices.php';
private const PLUGIN_SLUG = 'gf-advanced-conditional-choices';
private const PLUGIN_NAME = 'GF Advanced Conditional Choices';
private const PLUGIN_DESCRIPTION = 'Add conditional logic to individual choices in Radio, Checkbox, Dropdown, and Multi-Select fields.';
private const REQUIRES_WP = '6.0';
private const TESTED_WP = '6.7';
private const REQUIRES_PHP = '8.0';
private const TEXT_DOMAIN = 'gf-advanced-conditional-choices';
private const CACHE_KEY = 'gf_acc_github_release';
```

### Guilamu Bug Reporter

Register the plugin with Bug Reporter:

```php
add_action('plugins_loaded', function() {
    if (class_exists('Guilamu_Bug_Reporter')) {
        Guilamu_Bug_Reporter::register([
            'slug'        => 'gf-advanced-conditional-choices',
            'name'        => 'GF Advanced Conditional Choices',
            'version'     => GF_ACC_VERSION,
            'github_repo' => 'guilamu/gf-advanced-conditional-choices',
        ]);
    }
}, 20);
```

Add plugin row meta link:

```php
add_filter('plugin_row_meta', function($links, $file) {
    if (plugin_basename(__FILE__) !== $file) {
        return $links;
    }

    if (class_exists('Guilamu_Bug_Reporter')) {
        $links[] = sprintf(
            '<a href="#" class="guilamu-bug-report-btn" data-plugin-slug="%s" data-plugin-name="%s">%s</a>',
            'gf-advanced-conditional-choices',
            esc_attr__('GF Advanced Conditional Choices', 'gf-advanced-conditional-choices'),
            esc_html__('🐛 Report a Bug', 'gf-advanced-conditional-choices')
        );
    } else {
        $links[] = sprintf(
            '<a href="%s" target="_blank">%s</a>',
            'https://github.com/guilamu/guilamu-bug-reporter/releases',
            esc_html__('🐛 Report a Bug (install Bug Reporter)', 'gf-advanced-conditional-choices')
        );
    }

    return $links;
}, 10, 2);
```

---

## Localization

### Translation Strings

#### Admin UI (admin.js / PHP)

| String | Context |
|--------|---------|
| `"Conditional Logic for: %s"` | Modal title |
| `"Enable conditional logic for this choice"` | Enable checkbox label |
| `"Show this choice if"` | Logic type prefix |
| `"All"` | Logic type option |
| `"Any"` | Logic type option |
| `"of the following match:"` | Logic type suffix |
| `"— Select Field —"` | Field dropdown placeholder |
| `"is"` | Operator |
| `"is not"` | Operator |
| `"contains"` | Operator |
| `"starts with"` | Operator |
| `"ends with"` | Operator |
| `"greater than"` | Operator (display for `>`) |
| `"less than"` | Operator (display for `<`) |
| `"greater or equal"` | Operator (display for `>=`) |
| `"less or equal"` | Operator (display for `<=`) |
| `"is empty"` | Operator |
| `"is not empty"` | Operator |
| `"Value"` | Input placeholder |
| `"Add Rule"` | Button text |
| `"Cancel"` | Button text |
| `"Save Logic"` | Button text |
| `"Configure Choice Logic"` | Button tooltip |

#### Validation Messages (PHP)

| String | Context |
|--------|---------|
| `"Please select a valid option."` | Invalid hidden choice selected |
| `"No options available. Please adjust your previous selections."` | Required field, all choices hidden |

### POT File Generation

```bash
wp i18n make-pot . languages/gf-advanced-conditional-choices.pot \
    --domain=gf-advanced-conditional-choices \
    --include="*.php,assets/js/*.js"
```

---

## File Templates

### Main Plugin File Header

```php
<?php
/**
 * Plugin Name: GF Advanced Conditional Choices
 * Plugin URI: https://github.com/guilamu/gf-advanced-conditional-choices
 * Description: Add conditional logic to individual choices in Radio, Checkbox, Dropdown, and Multi-Select fields.
 * Version: 1.0.0
 * Author: Guilamu
 * Author URI: https://github.com/guilamu
 * Text Domain: gf-advanced-conditional-choices
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * License: AGPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/agpl-3.0.html
 * Update URI: https://github.com/guilamu/gf-advanced-conditional-choices/
 */

if (!defined('ABSPATH')) {
    exit;
}

define('GF_ACC_VERSION', '1.0.0');
define('GF_ACC_PATH', plugin_dir_path(__FILE__));
define('GF_ACC_URL', plugin_dir_url(__FILE__));
define('GF_ACC_FILE', __FILE__);
```

### GitHub Actions Workflow

Create `.github/workflows/release.yml`:

```yaml
name: Release

on:
  push:
    tags:
      - 'v*'
      - '[0-9]+.[0-9]+.[0-9]+'

permissions:
  contents: write

jobs:
  release:
    runs-on: ubuntu-latest
    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Get version from tag
        id: get_version
        run: echo "VERSION=${GITHUB_REF#refs/tags/}" >> $GITHUB_OUTPUT

      - name: Create plugin zip
        run: |
          mkdir -p build/gf-advanced-conditional-choices
          
          rsync -av --exclude='.git' \
                    --exclude='.github' \
                    --exclude='build' \
                    --exclude='.gitignore' \
                    --exclude='IMPLEMENTATION.md' \
                    --exclude='composer.*' \
                    --exclude='package*.json' \
                    --exclude='node_modules' \
                    --exclude='.DS_Store' \
                    --exclude='screenshot*.*' \
                    ./ build/gf-advanced-conditional-choices/
          
          cp README.md build/gf-advanced-conditional-choices/ 2>/dev/null || true
          
          cd build
          zip -r ../gf-advanced-conditional-choices.zip gf-advanced-conditional-choices
          cd ..

      - name: Create GitHub Release
        uses: softprops/action-gh-release@v1
        with:
          files: gf-advanced-conditional-choices.zip
          generate_release_notes: true
        env:
          GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}
```

### uninstall.php

```php
<?php
/**
 * Uninstall script for GF Advanced Conditional Choices
 *
 * Removes all plugin data when the plugin is deleted.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete transients
delete_transient('gf_acc_github_release');

// Note: Choice conditional logic data is stored within Gravity Forms field meta.
// We do NOT delete form data as it could cause issues with existing forms.
// Conditional logic data will simply be ignored if the plugin is deactivated.
```

---

## Testing Checklist

### Admin UI

- [ ] Logic button appears on Radio, Checkbox, Dropdown, Multi-Select field choices
- [ ] Modal opens with correct choice label
- [ ] Field dropdown excludes current field and non-input fields (HTML, Section, Page)
- [ ] All operators display correctly
- [ ] Value input hides for `is_empty` and `is_not_empty` operators
- [ ] Add/Remove rule buttons work correctly
- [ ] Save persists data to field choices
- [ ] Active indicator shows on choices with enabled logic
- [ ] Cancel closes modal without saving

### Frontend - Basic

- [ ] Conditions evaluate correctly on page load
- [ ] Conditions re-evaluate on field value change
- [ ] Radio/Checkbox choices hide/show correctly
- [ ] Dropdown/Multi-Select options disable/enable correctly
- [ ] Pre-populated hidden choices are cleared

### Frontend - Advanced

- [ ] Cascading conditions work (A → B → C)
- [ ] Cross-page conditions work in multi-page forms
- [ ] Page navigation re-evaluates and clears invalid selections
- [ ] Field-level hidden fields don't evaluate choice logic
- [ ] All operators work correctly

### Server-Side

- [ ] Hidden choice values rejected on validation
- [ ] Hidden choice values stripped from entry
- [ ] Required field with all hidden choices shows proper error
- [ ] Valid visible choices pass validation

### Integrations

- [ ] GitHub updater detects new versions
- [ ] Update installs correctly
- [ ] Bug Reporter link appears in plugins list
- [ ] Bug Reporter modal works (if installed)

---

## Changelog

### 1.0.0 (Initial Release)

- **New:** Conditional logic for individual choices in Radio, Checkbox, Dropdown, and Multi-Select fields
- **New:** Support for All (AND) and Any (OR) logic with multiple rules
- **New:** 11 operators: is, is not, contains, starts with, ends with, >, <, >=, <=, is empty, is not empty
- **New:** Cross-page conditional logic for multi-page forms
- **New:** Cascading dependency support
- **New:** Server-side validation and sanitization
- **New:** GitHub auto-updates
- **New:** Guilamu Bug Reporter integration
- **New:** Translation-ready with French translation

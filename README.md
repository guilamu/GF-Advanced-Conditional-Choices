# GF Advanced Conditional Choices

Add conditional logic to individual choices within Gravity Forms Radio, Checkbox, Dropdown, Multi-Select, and Multiple Choice fields.

## Choice-Level Conditional Logic

- Apply visibility rules to individual choices, not just entire fields
- Use ALL (AND) or ANY (OR) logic types for flexible rule combinations
- Chain dependent dropdowns (Country → State → City)

## Cross-Field Conditions

- Reference any field in the form as a trigger for conditions
- Support text, number, dropdown, radio, checkbox, and more field types
- Works across multi-page forms with proper state management
- Cascading dependencies re-evaluate when trigger fields change

## Rich Operator Support

- Match operators: Is, Is Not, Contains, Starts With, Ends With
- Numeric comparisons: Greater Than, Less Than, Greater/Less or Equal
- State checks: Is Empty, Is Not Empty

## Key Features

- **Unlimited Rules:** Add as many conditions per choice as needed
- **Server Validation:** Hidden choices are excluded from validation
- **Multilingual:** Works with content in any language
- **Translation-Ready:** All strings are internationalized (French included)
- **Secure:** Nonce verification, capability checks, and data sanitization
- **GitHub Updates:** Automatic updates from GitHub releases

## Requirements

- WordPress 6.0 or higher
- PHP 8.0 or higher
- Gravity Forms 2.7 or higher

## Installation

1. Upload the `gf-advanced-conditional-choices` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** menu in WordPress
3. Edit any Gravity Forms form with choice-based fields
4. Click the logic icon next to any choice to configure conditional visibility

## FAQ

### Which field types support conditional choices?

Radio Buttons, Checkboxes, Dropdowns, Multi-Select, and Multiple Choice fields can have conditional logic applied to their individual choices.

### What happens when a selected choice becomes hidden?

The selection is preserved but a validation error is shown on submit: "Please select a valid option." Users must select a visible option to proceed.

### Can I use conditions across multi-page forms?

Yes, conditions work across pages. When returning to a page, conditions are re-evaluated and invalid selections are cleared automatically.

### Does this work with dynamically populated choices?

Yes, the logic UI works seamlessly with choices populated via hooks or external sources.

### Can I customize the validation messages?

Yes, use the `gf_acc_invalid_selection_message` filter:
```php
add_filter( 'gf_acc_invalid_selection_message', function( $message ) {
    return 'Please choose a different option.';
} );
```

### What happens if all choices are hidden on a required field?

A validation error is shown: "No options available. Please adjust your previous selections."

## Project Structure

```
.
├── gf-advanced-conditional-choices.php   # Main plugin file (bootstrap)
├── class-gf-acc.php                      # Core add-on class
├── uninstall.php                         # Cleanup on uninstall
├── README.md
├── LICENSE
├── assets
│   ├── css
│   │   └── admin.css                     # Modal & editor styles
│   └── js
│       ├── admin.js                      # Form editor logic
│       └── frontend.js                   # Live form conditions engine
├── includes
│   ├── class-choice-conditions.php       # Logic evaluation utilities
│   ├── class-field-settings.php          # GF field settings hooks
│   ├── class-server-validation.php       # Server-side validation
│   └── class-github-updater.php          # GitHub auto-updates
└── languages
    ├── gf-advanced-conditional-choices.pot        # Translation template
    ├── gf-advanced-conditional-choices-en_US.po   # English translation
    ├── gf-advanced-conditional-choices-fr_FR.po   # French translation (source)
    └── gf-advanced-conditional-choices-fr_FR.mo   # French translation (binary)
```

## Changelog

### 1.0.0
- Initial release
- Conditional logic for individual choices in Radio, Checkbox, Dropdown, Multi-Select, and Multiple Choice fields
- ALL/ANY logic types with unlimited rules per choice
- Full operator set: Is, Is Not, Contains, Starts With, Ends With, >, <, >=, <=, Is Empty, Is Not Empty
- Cross-field and cross-page condition support
- Server-side validation for hidden choices
- Visual indicator for choices with active logic
- French translation included
- GitHub auto-updater integration

## License

This project is licensed under the GNU Affero General Public License v3.0 (AGPL-3.0) - see the [LICENSE](LICENSE) file for details.

---

<p align="center">
  Made with love for the WordPress community
</p>

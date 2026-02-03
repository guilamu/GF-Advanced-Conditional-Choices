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

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'GF_ACC_VERSION', '1.0.0' );
define( 'GF_ACC_PATH', plugin_dir_path( __FILE__ ) );
define( 'GF_ACC_URL', plugin_dir_url( __FILE__ ) );
define( 'GF_ACC_FILE', __FILE__ );
define( 'GF_ACC_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Bootstrap the plugin after Gravity Forms is loaded.
 */
add_action( 'gform_loaded', array( 'GF_ACC_Bootstrap', 'load' ), 5 );

/**
 * Class GF_ACC_Bootstrap
 *
 * Handles plugin initialization and dependency checks.
 */
class GF_ACC_Bootstrap {

    /**
     * Minimum required Gravity Forms version.
     *
     * @var string
     */
    private const MIN_GF_VERSION = '2.7';

    /**
     * Load the plugin.
     *
     * @return void
     */
    public static function load(): void {
        // Check Gravity Forms version
        if ( ! self::is_gravityforms_supported() ) {
            add_action( 'admin_notices', array( __CLASS__, 'admin_notice_gf_version' ) );
            return;
        }

        // Load text domain
        load_plugin_textdomain(
            'gf-advanced-conditional-choices',
            false,
            dirname( GF_ACC_BASENAME ) . '/languages'
        );

        // Include required files
        self::includes();

        // Initialize the main class
        GF_Advanced_Conditional_Choices::get_instance();
    }

    /**
     * Check if Gravity Forms version is supported.
     *
     * @return bool
     */
    private static function is_gravityforms_supported(): bool {
        if ( ! class_exists( 'GFForms' ) ) {
            return false;
        }

        return version_compare( GFForms::$version, self::MIN_GF_VERSION, '>=' );
    }

    /**
     * Include required files.
     *
     * @return void
     */
    private static function includes(): void {
        require_once GF_ACC_PATH . 'class-gf-acc.php';
        require_once GF_ACC_PATH . 'includes/class-github-updater.php';
        require_once GF_ACC_PATH . 'includes/class-field-settings.php';
        require_once GF_ACC_PATH . 'includes/class-choice-conditions.php';
        require_once GF_ACC_PATH . 'includes/class-server-validation.php';
    }

    /**
     * Display admin notice for unsupported Gravity Forms version.
     *
     * @return void
     */
    public static function admin_notice_gf_version(): void {
        $message = sprintf(
            /* translators: %s: Minimum required Gravity Forms version */
            esc_html__(
                'GF Advanced Conditional Choices requires Gravity Forms version %s or higher. Please update Gravity Forms.',
                'gf-advanced-conditional-choices'
            ),
            self::MIN_GF_VERSION
        );

        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            $message
        );
    }
}

/**
 * Register with Guilamu Bug Reporter.
 */
add_action( 'plugins_loaded', function() {
    if ( class_exists( 'Guilamu_Bug_Reporter' ) ) {
        Guilamu_Bug_Reporter::register( array(
            'slug'        => 'gf-advanced-conditional-choices',
            'name'        => 'GF Advanced Conditional Choices',
            'version'     => GF_ACC_VERSION,
            'github_repo' => 'guilamu/gf-advanced-conditional-choices',
        ) );
    }
}, 20 );

/**
 * Add plugin row meta links.
 *
 * @param array  $links Plugin row meta links.
 * @param string $file  Plugin file.
 * @return array Modified links.
 */
add_filter( 'plugin_row_meta', function( array $links, string $file ): array {
    if ( GF_ACC_BASENAME !== $file ) {
        return $links;
    }

    if ( class_exists( 'Guilamu_Bug_Reporter' ) ) {
        $links[] = sprintf(
            '<a href="#" class="guilamu-bug-report-btn" data-plugin-slug="%s" data-plugin-name="%s">%s</a>',
            'gf-advanced-conditional-choices',
            esc_attr__( 'GF Advanced Conditional Choices', 'gf-advanced-conditional-choices' ),
            esc_html__( '🐛 Report a Bug', 'gf-advanced-conditional-choices' )
        );
    } else {
        $links[] = sprintf(
            '<a href="%s" target="_blank">%s</a>',
            'https://github.com/guilamu/guilamu-bug-reporter/releases',
            esc_html__( '🐛 Report a Bug (install Bug Reporter)', 'gf-advanced-conditional-choices' )
        );
    }

    return $links;
}, 10, 2 );

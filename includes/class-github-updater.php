<?php
/**
 * GitHub Auto-Updater
 *
 * Enables automatic updates from GitHub releases for WordPress plugins.
 *
 * @package GF_Advanced_Conditional_Choices
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class GF_ACC_GitHub_Updater
 *
 * Handles automatic updates from GitHub releases.
 */
class GF_ACC_GitHub_Updater {

    // =========================================================================
    // CONFIGURATION
    // =========================================================================

    /**
     * GitHub username or organization.
     *
     * @var string
     */
    private const GITHUB_USER = 'guilamu';

    /**
     * GitHub repository name.
     *
     * @var string
     */
    private const GITHUB_REPO = 'gf-advanced-conditional-choices';

    /**
     * Plugin file path relative to plugins directory.
     *
     * @var string
     */
    private const PLUGIN_FILE = 'gf-advanced-conditional-choices/gf-advanced-conditional-choices.php';

    /**
     * Plugin slug.
     *
     * @var string
     */
    private const PLUGIN_SLUG = 'gf-advanced-conditional-choices';

    /**
     * Plugin display name.
     *
     * @var string
     */
    private const PLUGIN_NAME = 'GF Advanced Conditional Choices';

    /**
     * Plugin description.
     *
     * @var string
     */
    private const PLUGIN_DESCRIPTION = 'Add conditional logic to individual choices in Radio, Checkbox, Dropdown, and Multi-Select fields.';

    /**
     * Minimum WordPress version required.
     *
     * @var string
     */
    private const REQUIRES_WP = '6.0';

    /**
     * WordPress version tested up to.
     *
     * @var string
     */
    private const TESTED_WP = '6.7';

    /**
     * Minimum PHP version required.
     *
     * @var string
     */
    private const REQUIRES_PHP = '8.0';

    /**
     * Text domain for translations.
     *
     * @var string
     */
    private const TEXT_DOMAIN = 'gf-advanced-conditional-choices';

    /**
     * Cache key prefix for GitHub release data.
     *
     * @var string
     */
    private const CACHE_KEY = 'gf_acc_github_release';

    /**
     * Cache expiration in seconds (12 hours).
     *
     * @var int
     */
    private const CACHE_EXPIRATION = 43200;

    /**
     * Optional GitHub token for private repos or to avoid rate limits.
     *
     * @var string
     */
    private const GITHUB_TOKEN = '';

    // =========================================================================
    // IMPLEMENTATION
    // =========================================================================

    /**
     * Initialize the updater.
     *
     * @return void
     */
    public static function init(): void {
        add_filter( 'update_plugins_github.com', array( self::class, 'check_for_update' ), 10, 4 );
        add_filter( 'plugins_api', array( self::class, 'plugin_info' ), 20, 3 );
        add_filter( 'upgrader_source_selection', array( self::class, 'fix_folder_name' ), 10, 4 );
        add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_thickbox' ) );
        add_filter( 'plugin_row_meta', array( self::class, 'plugin_row_meta' ), 10, 2 );
    }

    /**
     * Get release data from GitHub with caching.
     *
     * @return array|null Release data or null on failure.
     */
    private static function get_release_data(): ?array {
        $release_data = get_transient( self::CACHE_KEY );

        if ( false !== $release_data && is_array( $release_data ) ) {
            return $release_data;
        }

        $response = wp_remote_get(
            sprintf( 'https://api.github.com/repos/%s/%s/releases/latest', self::GITHUB_USER, self::GITHUB_REPO ),
            array(
                'user-agent' => 'WordPress/' . self::PLUGIN_SLUG,
                'timeout'    => 15,
                'headers'    => ! empty( self::GITHUB_TOKEN )
                    ? array( 'Authorization' => 'token ' . self::GITHUB_TOKEN )
                    : array(),
            )
        );

        if ( is_wp_error( $response ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( self::PLUGIN_NAME . ' Update Error: ' . $response->get_error_message() );
            }
            return null;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $response_code ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( self::PLUGIN_NAME . " Update Error: HTTP {$response_code}" );
            }
            return null;
        }

        $release_data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $release_data['tag_name'] ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( self::PLUGIN_NAME . ' Update Error: No tag_name in release' );
            }
            return null;
        }

        set_transient( self::CACHE_KEY, $release_data, self::CACHE_EXPIRATION );

        return $release_data;
    }

    /**
     * Get the download URL for the plugin package.
     *
     * @param array $release_data Release data from GitHub API.
     * @return string Download URL.
     */
    private static function get_package_url( array $release_data ): string {
        // Look for a custom .zip asset (preferred)
        if ( ! empty( $release_data['assets'] ) && is_array( $release_data['assets'] ) ) {
            foreach ( $release_data['assets'] as $asset ) {
                if (
                    isset( $asset['browser_download_url'] ) &&
                    isset( $asset['name'] ) &&
                    str_ends_with( $asset['name'], '.zip' )
                ) {
                    return $asset['browser_download_url'];
                }
            }
        }

        // Fallback to GitHub's auto-generated zipball
        return $release_data['zipball_url'] ?? '';
    }

    /**
     * Check for plugin updates from GitHub.
     *
     * @param array|false $update      The plugin update data.
     * @param array       $plugin_data Plugin headers.
     * @param string      $plugin_file Plugin file path.
     * @param array       $locales     Installed locales.
     * @return array|false Updated plugin data or false.
     */
    public static function check_for_update( $update, array $plugin_data, string $plugin_file, $locales ) {
        if ( self::PLUGIN_FILE !== $plugin_file ) {
            return $update;
        }

        $release_data = self::get_release_data();
        if ( null === $release_data ) {
            return $update;
        }

        $new_version = ltrim( $release_data['tag_name'], 'v' );

        if ( version_compare( $plugin_data['Version'], $new_version, '>=' ) ) {
            return $update;
        }

        return array(
            'version'      => $new_version,
            'package'      => self::get_package_url( $release_data ),
            'url'          => $release_data['html_url'],
            'tested'       => self::TESTED_WP,
            'requires_php' => self::REQUIRES_PHP,
            'compatibility' => new stdClass(),
            'icons'        => array(),
            'banners'      => array(),
        );
    }

    /**
     * Provide plugin information for the WordPress plugin details popup.
     *
     * @param false|object|array $res    The result object or array.
     * @param string             $action The type of information being requested.
     * @param object             $args   Plugin API arguments.
     * @return false|object Plugin information or false.
     */
    public static function plugin_info( $res, $action, $args ) {
        if ( 'plugin_information' !== $action ) {
            return $res;
        }

        if ( ! isset( $args->slug ) || self::PLUGIN_SLUG !== $args->slug ) {
            return $res;
        }

        $release_data = self::get_release_data();

        if ( null === $release_data ) {
            $plugin_file = WP_PLUGIN_DIR . '/' . self::PLUGIN_FILE;
            $plugin_data = get_plugin_data( $plugin_file, false, false );
            $current_version = $plugin_data['Version'] ?? '1.0.0';

            $res = new stdClass();
            $res->name = self::PLUGIN_NAME;
            $res->slug = self::PLUGIN_SLUG;
            $res->plugin = self::PLUGIN_FILE;
            $res->version = $current_version;
            $res->author = sprintf( '<a href="https://github.com/%s">%s</a>', self::GITHUB_USER, self::GITHUB_USER );
            $res->homepage = sprintf( 'https://github.com/%s/%s', self::GITHUB_USER, self::GITHUB_REPO );
            $res->requires = self::REQUIRES_WP;
            $res->tested = self::TESTED_WP;
            $res->requires_php = self::REQUIRES_PHP;
            $res->sections = array(
                'description' => self::PLUGIN_DESCRIPTION,
                'changelog'   => sprintf(
                    '<p>Unable to fetch changelog from GitHub. Visit <a href="https://github.com/%s/%s/releases" target="_blank">GitHub releases</a> for the latest changelog.</p>',
                    self::GITHUB_USER,
                    self::GITHUB_REPO
                ),
            );
            return $res;
        }

        $new_version = ltrim( $release_data['tag_name'], 'v' );

        $res = new stdClass();
        $res->name = self::PLUGIN_NAME;
        $res->slug = self::PLUGIN_SLUG;
        $res->plugin = self::PLUGIN_FILE;
        $res->version = $new_version;
        $res->author = sprintf( '<a href="https://github.com/%s">%s</a>', self::GITHUB_USER, self::GITHUB_USER );
        $res->homepage = sprintf( 'https://github.com/%s/%s', self::GITHUB_USER, self::GITHUB_REPO );
        $res->download_link = self::get_package_url( $release_data );
        $res->requires = self::REQUIRES_WP;
        $res->tested = self::TESTED_WP;
        $res->requires_php = self::REQUIRES_PHP;
        $res->last_updated = $release_data['published_at'] ?? '';
        $res->sections = array(
            'description' => self::PLUGIN_DESCRIPTION,
            'changelog'   => ! empty( $release_data['body'] )
                ? nl2br( esc_html( $release_data['body'] ) )
                : sprintf(
                    'See <a href="https://github.com/%s/%s/releases" target="_blank">GitHub releases</a> for changelog.',
                    self::GITHUB_USER,
                    self::GITHUB_REPO
                ),
        );

        return $res;
    }

    /**
     * Rename the extracted folder to match the expected plugin folder name.
     *
     * @param string      $source        File source location.
     * @param string      $remote_source Remote file source location.
     * @param WP_Upgrader $upgrader      WP_Upgrader instance.
     * @param array       $hook_extra    Extra arguments.
     * @return string|WP_Error The corrected source path or WP_Error.
     */
    public static function fix_folder_name( $source, $remote_source, $upgrader, $hook_extra ) {
        global $wp_filesystem;

        if ( ! isset( $hook_extra['plugin'] ) ) {
            return $source;
        }

        if ( self::PLUGIN_FILE !== $hook_extra['plugin'] ) {
            return $source;
        }

        $correct_folder = dirname( self::PLUGIN_FILE );
        $source_folder  = basename( untrailingslashit( $source ) );

        if ( $source_folder === $correct_folder ) {
            return $source;
        }

        $new_source = trailingslashit( $remote_source ) . $correct_folder . '/';

        if ( $wp_filesystem && $wp_filesystem->move( $source, $new_source ) ) {
            return $new_source;
        }

        if ( $wp_filesystem && $wp_filesystem->copy( $source, $new_source, true ) && $wp_filesystem->delete( $source, true ) ) {
            return $new_source;
        }

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( sprintf(
                '%s updater: failed to rename update folder from %s to %s',
                self::PLUGIN_NAME,
                $source,
                $new_source
            ) );
        }

        return new WP_Error(
            'rename_failed',
            __( 'Unable to rename the update folder. Please retry or update manually.', self::TEXT_DOMAIN )
        );
    }

    /**
     * Enqueue Thickbox for plugin details modal.
     *
     * @param string $hook Current admin page.
     * @return void
     */
    public static function enqueue_thickbox( string $hook ): void {
        if ( 'plugins.php' === $hook ) {
            add_thickbox();
        }
    }

    /**
     * Add View Details link to plugin row meta.
     *
     * @param array  $links Plugin row meta links.
     * @param string $file  Plugin file.
     * @return array Modified links.
     */
    public static function plugin_row_meta( array $links, string $file ): array {
        if ( self::PLUGIN_FILE !== $file ) {
            return $links;
        }

        $details_link = sprintf(
            '<a href="%s" class="thickbox open-plugin-details-modal" aria-label="%s" data-title="%s">%s</a>',
            esc_url(
                admin_url(
                    'plugin-install.php?tab=plugin-information&plugin=' . self::PLUGIN_SLUG .
                    '&TB_iframe=true&width=600&height=550'
                )
            ),
            esc_attr( sprintf( __( 'More information about %s', self::TEXT_DOMAIN ), self::PLUGIN_NAME ) ),
            esc_attr( self::PLUGIN_NAME ),
            esc_html__( 'View details', self::TEXT_DOMAIN )
        );

        return array_merge( $links, array( $details_link ) );
    }
}

// Initialize the updater
GF_ACC_GitHub_Updater::init();

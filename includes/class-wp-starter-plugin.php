<?php
/**
 * Main plugin class.
 *
 * @package WP_Starter_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WP_Starter_Plugin {

    /**
     * Plugin instance.
     *
     * @var WP_Starter_Plugin|null
     */
    private static $instance = null;

    /**
     * Admin page hook suffix.
     *
     * @var string
     */
    private $admin_hook = '';

    /**
     * Option key for storing redirects.
     */
    const OPTION_KEY = 'wp_starter_plugin_redirects';

    /**
     * Get the singleton instance.
     *
     * @return WP_Starter_Plugin
     */
    public static function instance() {
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
     * Register WordPress hooks.
     */
    private function init_hooks() {
        add_action( 'init', array( $this, 'load_textdomain' ) );
        add_action( 'init', array( $this, 'maybe_redirect' ), 99 );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'handle_redirect_form' ) );
        add_action( 'template_redirect', array( $this, 'maybe_redirect' ), 1 );
    }

    /**
     * Load plugin text domain for translations.
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'redirect-me',
            false,
            dirname( WP_STARTER_PLUGIN_BASENAME ) . '/languages'
        );
    }

    /**
     * Enqueue frontend assets.
     */
    public function enqueue_frontend_assets() {
        wp_enqueue_style(
            'wp-starter-plugin-frontend',
            WP_STARTER_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            WP_STARTER_PLUGIN_VERSION
        );

        wp_enqueue_script(
            'wp-starter-plugin-frontend',
            WP_STARTER_PLUGIN_URL . 'assets/js/frontend.js',
            array(),
            WP_STARTER_PLUGIN_VERSION,
            true
        );
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_admin_assets( $hook ) {
        if ( '' === $this->admin_hook || $this->admin_hook !== $hook ) {
            return;
        }
        wp_enqueue_style(
            'wp-starter-plugin-admin',
            WP_STARTER_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WP_STARTER_PLUGIN_VERSION
        );
    }

    /**
     * Register admin menu page.
     */
    public function register_admin_menu() {
        $this->admin_hook = add_options_page(
            __( 'Redirects', 'redirect-me' ),
            __( 'Redirects', 'redirect-me' ),
            'manage_options',
            'wp-starter-redirects',
            array( $this, 'render_admin_page' )
        );
    }

    /**
     * Handle redirect form submission.
     */
    public function handle_redirect_form() {
        if ( ! isset( $_POST['wp_starter_redirects_nonce'] ) ) {
            return;
        }
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_starter_redirects_nonce'] ) ), 'wp_starter_redirects_save' ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $redirects = array();

        $froms = isset( $_POST['redirect_from'] ) ? (array) $_POST['redirect_from'] : array();
        $tos   = isset( $_POST['redirect_to'] ) ? (array) $_POST['redirect_to'] : array();
        $codes = isset( $_POST['redirect_code'] ) ? (array) $_POST['redirect_code'] : array();

        $count = max( count( $froms ), count( $tos ), count( $codes ) );

        for ( $i = 0; $i < $count; $i++ ) {
            $from = isset( $froms[ $i ] ) ? sanitize_text_field( wp_unslash( $froms[ $i ] ) ) : '';
            $raw_to = isset( $tos[ $i ] ) ? trim( wp_unslash( $tos[ $i ] ) ) : '';
            if ( '' !== $raw_to && '/' === substr( $raw_to, 0, 1 ) ) {
                $to = sanitize_text_field( $raw_to );
            } else {
                $to = esc_url_raw( $raw_to );
            }
            $code = isset( $codes[ $i ] ) ? absint( $codes[ $i ] ) : 301;

            if ( '' === $from || '' === $to ) {
                continue;
            }
            if ( ! in_array( $code, array( 301, 302, 307, 308 ), true ) ) {
                $code = 301;
            }

            $redirects[] = array(
                'from' => $this->normalize_path( $from ),
                'to'   => $to,
                'code' => $code,
            );
        }

        update_option( self::OPTION_KEY, $redirects );

        add_settings_error(
            'wp_starter_redirects',
            'saved',
            __( 'Redirects saved.', 'redirect-me' ),
            'updated'
        );
    }

    /**
     * Render admin page.
     */
    public function render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $redirects = get_option( self::OPTION_KEY, array() );
        if ( ! is_array( $redirects ) ) {
            $redirects = array();
        }

        settings_errors( 'wp_starter_redirects' );
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <p><?php esc_html_e( 'Manage URL redirects. Enter the source path (e.g. /old-page) and the destination URL.', 'redirect-me' ); ?></p>

            <form method="post" action="">
                <?php wp_nonce_field( 'wp_starter_redirects_save', 'wp_starter_redirects_nonce' ); ?>
                <table class="widefat striped" id="wp-starter-redirects-table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="wp-starter-select-all" title="<?php esc_attr_e( 'Select all', 'redirect-me' ); ?>" /></th>
                            <th><?php esc_html_e( 'From (path)', 'redirect-me' ); ?></th>
                            <th><?php esc_html_e( 'To (URL)', 'redirect-me' ); ?></th>
                            <th><?php esc_html_e( 'Status code', 'redirect-me' ); ?></th>
                            <th><?php esc_html_e( 'Delete', 'redirect-me' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $redirects as $r ) : ?>
                            <tr>
                                <td><input type="checkbox" class="wp-starter-row-checkbox" /></td>
                                <td><input type="text" name="redirect_from[]" value="<?php echo esc_attr( $r['from'] ); ?>" class="regular-text" placeholder="/old-path" /></td>
                                <td><input type="text" name="redirect_to[]" value="<?php echo esc_attr( $r['to'] ); ?>" class="regular-text" placeholder="https://example.com/new-page" /></td>
                                <td>
                                    <select name="redirect_code[]">
                                        <?php foreach ( array( 301, 302, 307, 308 ) as $code ) : ?>
                                            <option value="<?php echo esc_attr( $code ); ?>" <?php selected( $r['code'], $code ); ?>><?php echo esc_html( $code ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><button type="button" class="button wp-starter-delete-row" style="color:#a00;border-color:#a00;"><?php esc_html_e( 'Delete', 'redirect-me' ); ?></button></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr>
                            <td><input type="checkbox" class="wp-starter-row-checkbox" /></td>
                            <td><input type="text" name="redirect_from[]" value="" class="regular-text" placeholder="/old-path" /></td>
                            <td><input type="text" name="redirect_to[]" value="" class="regular-text" placeholder="https://example.com/new-page" /></td>
                            <td>
                                <select name="redirect_code[]">
                                    <?php foreach ( array( 301, 302, 307, 308 ) as $code ) : ?>
                                        <option value="<?php echo esc_attr( $code ); ?>" <?php selected( 301, $code ); ?>><?php echo esc_html( $code ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><button type="button" class="button wp-starter-delete-row" style="color:#a00;border-color:#a00;"><?php esc_html_e( 'Delete', 'redirect-me' ); ?></button></td>
                        </tr>
                    </tbody>
                </table>
                <p>
                    <button type="button" class="button" id="wp-starter-add-row"><?php esc_html_e( 'Add row', 'redirect-me' ); ?></button>
                    <button type="button" class="button" id="wp-starter-delete-selected" style="color:#a00;border-color:#a00;"><?php esc_html_e( 'Delete Selected', 'redirect-me' ); ?></button>
                </p>
                <p class="submit">
                    <input type="submit" name="submit" class="button button-primary" value="<?php esc_attr_e( 'Save Redirects', 'redirect-me' ); ?>" />
                </p>
            </form>
        </div>
        <script>
            (function(){
                var table = document.getElementById('wp-starter-redirects-table');
                if (!table) return;
                var tbody = table.querySelector('tbody');
                var selectAll = document.getElementById('wp-starter-select-all');

                function rowCheckboxes() {
                    return Array.prototype.slice.call(tbody.querySelectorAll('.wp-starter-row-checkbox'));
                }

                if (selectAll) {
                    selectAll.addEventListener('change', function(){
                        rowCheckboxes().forEach(function(cb){ cb.checked = selectAll.checked; });
                    });
                }

                var addBtn = document.getElementById('wp-starter-add-row');
                if (addBtn) {
                    addBtn.addEventListener('click', function(){
                        var last = tbody.lastElementChild;
                        var clone = last.cloneNode(true);
                        clone.querySelectorAll('input').forEach(function(i){
                            if (i.type === 'checkbox') { i.checked = false; } else { i.value = ''; }
                        });
                        clone.querySelectorAll('select').forEach(function(s){ s.selectedIndex = 0; });
                        tbody.appendChild(clone);
                    });
                }

                var bulkBtn = document.getElementById('wp-starter-delete-selected');
                if (bulkBtn) {
                    bulkBtn.addEventListener('click', function(){
                        var checked = rowCheckboxes().filter(function(cb){ return cb.checked; });
                        if (!checked.length) return;
                        if (!window.confirm('<?php echo esc_js( __( 'Delete selected rows?', 'redirect-me' ) ); ?>')) return;
                        checked.forEach(function(cb){
                            var row = cb.closest('tr');
                            if (row && tbody.querySelectorAll('tr').length > 1) {
                                row.remove();
                            } else if (row) {
                                row.querySelectorAll('input').forEach(function(i){ if (i.type === 'checkbox') i.checked = false; else i.value = ''; });
                            }
                        });
                        if (selectAll) selectAll.checked = false;
                    });
                }

                table.addEventListener('click', function(e){
                    var target = e.target;
                    if (!target.classList.contains('wp-starter-delete-row')) return;
                    var row = target.closest('tr');
                    if (!row) return;
                    if (tbody.querySelectorAll('tr').length <= 1) {
                        row.querySelectorAll('input').forEach(function(i){ if (i.type === 'checkbox') i.checked = false; else i.value = ''; });
                        return;
                    }
                    row.remove();
                });
            })();
        </script>
        <?php
    }

    /**
     * Maybe redirect current request.
     */
    public function maybe_redirect() {
        if ( is_admin() ) {
            return;
        }

        $redirects = get_option( self::OPTION_KEY, array() );
        if ( ! is_array( $redirects ) || empty( $redirects ) ) {
            return;
        }

        $request_path = $this->get_request_path();

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'WP Starter Plugin: request path = ' . $request_path );
            error_log( 'WP Starter Plugin: redirects = ' . wp_json_encode( $redirects ) );
        }

        foreach ( $redirects as $r ) {
            if ( ! isset( $r['from'], $r['to'] ) ) {
                continue;
            }
            if ( $this->normalize_path( $r['from'] ) === $request_path ) {
                $code = isset( $r['code'] ) ? (int) $r['code'] : 301;
                if ( ! in_array( $code, array( 301, 302, 307, 308 ), true ) ) {
                    $code = 301;
                }
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'WP Starter Plugin: redirecting ' . $request_path . ' -> ' . $r['to'] . ' (' . $code . ')' );
                }
                $destination = $r['to'];
                if ( '/' === substr( $destination, 0, 1 ) && false === strpos( $destination, '://' ) ) {
                    $destination = home_url( $destination );
                }

                wp_redirect( $destination, $code );
                exit;
            }
        }
    }

    /**
     * Get normalized request path.
     *
     * @return string
     */
    private function get_request_path() {
        $uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
        $path = wp_parse_url( $uri, PHP_URL_PATH );
        if ( ! is_string( $path ) || '' === $path ) {
            $path = '/';
        }

        // Strip the home URL path so installs in subdirectories match.
        $home_path = wp_parse_url( home_url(), PHP_URL_PATH );
        if ( is_string( $home_path ) && '/' !== $home_path && '' !== $home_path ) {
            $home_path = rtrim( $home_path, '/' );
            if ( 0 === strpos( $path, $home_path ) ) {
                $path = substr( $path, strlen( $home_path ) );
                if ( '' === $path ) {
                    $path = '/';
                }
            }
        }

        return $this->normalize_path( $path );
    }

    /**
     * Normalize a path (trim trailing slash except root).
     *
     * @param string $path Path.
     * @return string
     */
    private function normalize_path( $path ) {
        $path = trim( (string) $path );

        // Strip any query string.
        if ( false !== strpos( $path, '?' ) ) {
            $path = strtok( $path, '?' );
        }

        // Allow full URLs in the "from" field; keep only the path.
        if ( false !== strpos( $path, '://' ) ) {
            $parsed = wp_parse_url( $path, PHP_URL_PATH );
            if ( is_string( $parsed ) && '' !== $parsed ) {
                $path = $parsed;
            }
        }

        if ( '' === $path ) {
            $path = '/';
        }
        if ( '/' !== $path ) {
            $path = rtrim( $path, '/' );
        }
        if ( 0 !== strpos( $path, '/' ) ) {
            $path = '/' . $path;
        }
        return $path;
    }

    /**
     * Plugin activation callback.
     */
    public static function activate() {
        if ( false === get_option( self::OPTION_KEY ) ) {
            add_option( self::OPTION_KEY, array() );
        }
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation callback.
     */
    public static function deactivate() {
        flush_rewrite_rules();
    }

    /**
     * Plugin uninstall callback.
     */
    public static function uninstall() {
        delete_option( self::OPTION_KEY );
    }
}

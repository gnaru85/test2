<?php
namespace TSDB;

/**
 * Admin settings and sync controls.
 */
class Admin_UI {
    protected $api_client;
    protected $sync_manager;
    protected $logger;

    public function __construct( Api_Client $api_client, Sync_Manager $sync_manager, Logger $logger ) {
        $this->api_client   = $api_client;
        $this->sync_manager = $sync_manager;
        $this->logger       = $logger;
    }

    public function init() {
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        add_action( 'wp_ajax_tsdb_sync_leagues', [ $this, 'ajax_sync_leagues' ] );
        add_action( 'wp_ajax_tsdb_seed', [ $this, 'ajax_seed' ] );
        add_action( 'wp_ajax_tsdb_delta', [ $this, 'ajax_delta' ] );
    }

    public function register_menu() {
        add_options_page( __( 'TheSportsDB', 'tsdb' ), __( 'TheSportsDB', 'tsdb' ), 'manage_options', 'tsdb', [ $this, 'settings_page' ] );
        add_management_page( __( 'TSDB Logs', 'tsdb' ), __( 'TSDB Logs', 'tsdb' ), 'manage_options', 'tsdb-logs', [ $this, 'logs_page' ] );
        add_management_page( __( 'TSDB Health', 'tsdb' ), __( 'TSDB Health', 'tsdb' ), 'manage_options', 'tsdb-health', [ $this, 'health_page' ] );
    }

    public function register_settings() {
        register_setting( 'tsdb', 'tsdb_api_key', [
            'type'              => 'string',
            'sanitize_callback' => [ $this, 'sanitize_api_key' ],
            'show_in_rest'      => false,
        ] );
        register_setting( 'tsdb', 'tsdb_default_sport', [
            'type'              => 'string',
            'sanitize_callback' => [ $this, 'sanitize_default_sport' ],
            'default'           => 'soccer',
        ] );
        register_setting( 'tsdb', 'tsdb_live_poll', [
            'type'              => 'integer',
            'sanitize_callback' => [ $this, 'sanitize_live_poll' ],
            'default'           => 30,
        ] );
    }

    public function sanitize_api_key( $value ) {
        $value = sanitize_text_field( $value );
        if ( '' === $value ) {
            delete_option( 'tsdb_api_key' );
            return '';
        }
        $encrypted = tsdb_encrypt( $value );
        delete_option( 'tsdb_api_key' );
        add_option( 'tsdb_api_key', $encrypted, '', 'no' );
        return $encrypted;
    }

    public function sanitize_default_sport( $value ) {
        $value = sanitize_text_field( $value ?: 'soccer' );
        delete_option( 'tsdb_default_sport' );
        add_option( 'tsdb_default_sport', $value, '', 'no' );
        return $value;
    }

    public function sanitize_live_poll( $value ) {
        $value = absint( $value );
        delete_option( 'tsdb_live_poll' );
        add_option( 'tsdb_live_poll', $value, '', 'no' );
        return $value;
    }

    public function enqueue_scripts( $hook ) {
        if ( 'settings_page_tsdb' !== $hook ) {
            return;
        }
        wp_enqueue_script( 'tsdb-admin', TSDB_URL . 'assets/admin.js', [ 'jquery' ], TSDB_VERSION, true );
        wp_localize_script( 'tsdb-admin', 'tsdbAdmin', [
            'rest'  => esc_url_raw( rest_url( 'tsdb/v1/ref/' ) ),
            'nonce' => wp_create_nonce( 'tsdb_sync' ),
        ] );
    }

    public function ajax_sync_leagues() {
        check_ajax_referer( 'tsdb_sync' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Unauthorized', 'tsdb' ), 403 );
        }
        $country = sanitize_text_field( $_POST['country'] ?? '' );
        $sport   = sanitize_text_field( $_POST['sport'] ?? '' );
        $count   = $this->sync_manager->sync_leagues( $country, $sport );
        if ( is_wp_error( $count ) ) {
            wp_send_json_error( $count->get_error_message() );
        }
        wp_send_json_success( [ 'message' => sprintf( __( '%d leagues synced', 'tsdb' ), $count ) ] );
    }

    public function ajax_seed() {
        check_ajax_referer( 'tsdb_sync' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Unauthorized', 'tsdb' ), 403 );
        }
        $league = sanitize_text_field( $_POST['league'] ?? '' );
        $season = sanitize_text_field( $_POST['season'] ?? '' );
        $result = $this->sync_manager->sync_seasons( $league );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }
        $result = $this->sync_manager->sync_teams( $league );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }
        $count = $this->sync_manager->sync_events( $league, $season );
        if ( is_wp_error( $count ) ) {
            wp_send_json_error( $count->get_error_message() );
        }
        wp_send_json_success( [ 'message' => sprintf( __( '%d events seeded', 'tsdb' ), $count ) ] );
    }

    public function ajax_delta() {
        check_ajax_referer( 'tsdb_sync' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Unauthorized', 'tsdb' ), 403 );
        }
        $league = sanitize_text_field( $_POST['league'] ?? '' );
        $season = sanitize_text_field( $_POST['season'] ?? '' );
        $count  = $this->sync_manager->sync_events( $league, $season );
        if ( is_wp_error( $count ) ) {
            wp_send_json_error( $count->get_error_message() );
        }
        wp_send_json_success( [ 'message' => sprintf( __( '%d events refreshed', 'tsdb' ), $count ) ] );
    }

    public function settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'TheSportsDB Settings', 'tsdb' ); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'tsdb' ); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="tsdb_api_key">API Key</label></th>
                        <td><input name="tsdb_api_key" type="text" id="tsdb_api_key" value="<?php echo esc_attr( tsdb_get_api_key() ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tsdb_default_sport">Default Sport</label></th>
                        <td><input name="tsdb_default_sport" type="text" id="tsdb_default_sport" value="<?php echo esc_attr( get_option( 'tsdb_default_sport', 'soccer' ) ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tsdb_live_poll">Live Poll Interval (s)</label></th>
                        <td><input name="tsdb_live_poll" type="number" id="tsdb_live_poll" value="<?php echo esc_attr( get_option( 'tsdb_live_poll', 30 ) ); ?>" class="small-text"></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            <h2><?php esc_html_e( 'Sync Data', 'tsdb' ); ?></h2>
            <div id="tsdb_sync_controls">
                <select id="tsdb_country"></select>
                <select id="tsdb_sport"></select>
                <select id="tsdb_league"></select>
                <select id="tsdb_season"></select>
                <button id="tsdb_sync_btn" class="button button-primary"><?php esc_html_e( 'Sync Selected', 'tsdb' ); ?></button>
                <button id="tsdb_seed_btn" class="button"><?php esc_html_e( 'Seed League', 'tsdb' ); ?></button>
                <button id="tsdb_delta_btn" class="button"><?php esc_html_e( 'Refresh Events', 'tsdb' ); ?></button>
            </div>
        </div>
        <?php
    }

    public function logs_page() {
        $level = isset( $_GET['level'] ) ? sanitize_text_field( $_GET['level'] ) : '';
        if ( isset( $_GET['export'] ) ) {
            $format = sanitize_text_field( $_GET['export'] );
            $logs   = $this->logger->get_logs( $level ?: null );
            if ( 'csv' === $format ) {
                header( 'Content-Type: text/csv' );
                header( 'Content-Disposition: attachment; filename=tsdb-logs.csv' );
                $out = fopen( 'php://output', 'w' );
                fputcsv( $out, [ 'ts', 'level', 'source', 'message', 'context' ] );
                foreach ( $logs as $row ) {
                    fputcsv( $out, [ $row['ts'], $row['level'], $row['source'], $row['message'], $row['context_json'] ] );
                }
                fclose( $out );
            } else {
                header( 'Content-Type: application/json' );
                header( 'Content-Disposition: attachment; filename=tsdb-logs.json' );
                echo wp_json_encode( $logs );
            }
            exit;
        }
        $logs = $this->logger->get_logs( $level ?: null );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'TSDB Logs', 'tsdb' ); ?></h1>
            <form method="get" style="margin-bottom:1em;">
                <input type="hidden" name="page" value="tsdb-logs" />
                <select name="level">
                    <option value=""><?php esc_html_e( 'All Levels', 'tsdb' ); ?></option>
                    <option value="debug" <?php selected( $level, 'debug' ); ?>><?php esc_html_e( 'Debug', 'tsdb' ); ?></option>
                    <option value="info" <?php selected( $level, 'info' ); ?>><?php esc_html_e( 'Info', 'tsdb' ); ?></option>
                    <option value="warning" <?php selected( $level, 'warning' ); ?>><?php esc_html_e( 'Warning', 'tsdb' ); ?></option>
                    <option value="error" <?php selected( $level, 'error' ); ?>><?php esc_html_e( 'Error', 'tsdb' ); ?></option>
                </select>
                <button class="button"><?php esc_html_e( 'Filter', 'tsdb' ); ?></button>
            </form>
            <p>
                <a href="?page=tsdb-logs&amp;export=json<?php echo $level ? '&amp;level=' . urlencode( $level ) : ''; ?>" class="button">Export JSON</a>
                <a href="?page=tsdb-logs&amp;export=csv<?php echo $level ? '&amp;level=' . urlencode( $level ) : ''; ?>" class="button">Export CSV</a>
            </p>
            <table class="widefat">
                <thead>
                <tr>
                    <th><?php esc_html_e( 'Time', 'tsdb' ); ?></th>
                    <th><?php esc_html_e( 'Level', 'tsdb' ); ?></th>
                    <th><?php esc_html_e( 'Source', 'tsdb' ); ?></th>
                    <th><?php esc_html_e( 'Message', 'tsdb' ); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ( $logs as $row ) : ?>
                    <tr>
                        <td><?php echo esc_html( $row['ts'] ); ?></td>
                        <td><?php echo esc_html( $row['level'] ); ?></td>
                        <td><?php echo esc_html( $row['source'] ); ?></td>
                        <td><?php echo esc_html( $row['message'] ); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function health_page() {
        global $wpdb;
        $data = [
            'php_version' => PHP_VERSION,
            'wp_version'  => get_bloginfo( 'version' ),
            'leagues'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}tsdb_leagues" ),
            'events'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}tsdb_events" ),
            'logs'        => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}tsdb_logs" ),
        ];
        if ( isset( $_GET['export'] ) ) {
            header( 'Content-Type: application/json' );
            header( 'Content-Disposition: attachment; filename=tsdb-health.json' );
            echo wp_json_encode( $data );
            exit;
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'TSDB System Health', 'tsdb' ); ?></h1>
            <p><a href="?page=tsdb-health&amp;export=json" class="button">Export JSON</a></p>
            <table class="widefat">
                <thead><tr><th><?php esc_html_e( 'Metric', 'tsdb' ); ?></th><th><?php esc_html_e( 'Value', 'tsdb' ); ?></th></tr></thead>
                <tbody>
                    <tr><td>PHP</td><td><?php echo esc_html( $data['php_version'] ); ?></td></tr>
                    <tr><td>WordPress</td><td><?php echo esc_html( $data['wp_version'] ); ?></td></tr>
                    <tr><td><?php esc_html_e( 'Leagues', 'tsdb' ); ?></td><td><?php echo esc_html( $data['leagues'] ); ?></td></tr>
                    <tr><td><?php esc_html_e( 'Events', 'tsdb' ); ?></td><td><?php echo esc_html( $data['events'] ); ?></td></tr>
                    <tr><td><?php esc_html_e( 'Log Entries', 'tsdb' ); ?></td><td><?php echo esc_html( $data['logs'] ); ?></td></tr>
                </tbody>
            </table>
        </div>
        <?php
    }
}

<?php
namespace TSDB;

/**
 * Admin settings and sync controls.
 */
class Admin_UI {
    protected $api_client;
    protected $sync_manager;

    public function __construct( Api_Client $api_client, Sync_Manager $sync_manager ) {
        $this->api_client   = $api_client;
        $this->sync_manager = $sync_manager;
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
    }

    public function register_settings() {
        register_setting( 'tsdb', 'tsdb_api_key', [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'show_in_rest' => false ] );
        register_setting( 'tsdb', 'tsdb_default_sport', [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => 'soccer' ] );
        register_setting( 'tsdb', 'tsdb_live_poll', [ 'type' => 'integer', 'sanitize_callback' => 'absint', 'default' => 30 ] );
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
        $league = sanitize_text_field( $_POST['league'] ?? '' );
        $season = sanitize_text_field( $_POST['season'] ?? '' );
        $this->sync_manager->sync_seasons( $league );
        $this->sync_manager->sync_teams( $league );
        $count = $this->sync_manager->sync_events( $league, $season );
        if ( is_wp_error( $count ) ) {
            wp_send_json_error( $count->get_error_message() );
        }
        wp_send_json_success( [ 'message' => sprintf( __( '%d events seeded', 'tsdb' ), $count ) ] );
    }

    public function ajax_delta() {
        check_ajax_referer( 'tsdb_sync' );
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
                        <td><input name="tsdb_api_key" type="text" id="tsdb_api_key" value="<?php echo esc_attr( get_option( 'tsdb_api_key', '' ) ); ?>" class="regular-text"></td>
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
}

<?php
/**
 * Plugin Name: TheSportsDB Sync
 * Description: Multisport synchronization plugin for TheSportsDB premium API.
 * Version: 0.1.0
 * Author: OpenAI
 * Text Domain: tsdb
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Define plugin constants.
if ( ! defined( 'TSDB_VERSION' ) ) {
    define( 'TSDB_VERSION', '0.1.0' );
}
if ( ! defined( 'TSDB_PATH' ) ) {
    define( 'TSDB_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'TSDB_URL' ) ) {
    define( 'TSDB_URL', plugin_dir_url( __FILE__ ) );
}

// Simple PSR-4 style autoloader for plugin classes.
spl_autoload_register( function ( $class ) {
    if ( 0 !== strpos( $class, 'TSDB\\' ) ) {
        return;
    }

    $path = TSDB_PATH . 'includes/' . strtolower( str_replace( 'TSDB\\', '', $class ) );
    $path = str_replace( '\\', '/', $path ) . '.php';

    if ( file_exists( $path ) ) {
        include $path;
    }
} );

/**
 * Initialize plugin services.
 */
function tsdb_init_plugin() {
    // Instantiate core services.
    $logger       = new TSDB\Logger();
    $rate_limiter = new TSDB\Rate_Limiter();
    $api_client   = new TSDB\Api_Client( $logger, $rate_limiter );
    $sync_manager = new TSDB\Sync_Manager( $api_client, $logger );
    $rest_api     = new TSDB\Rest_API( $api_client );
    $admin_ui     = new TSDB\Admin_UI( $api_client, $sync_manager );

    // Boot services.
    $rest_api->register_routes();
    $admin_ui->init();
    $sync_manager->init_cron();
}
add_action( 'plugins_loaded', 'tsdb_init_plugin' );

/**
 * Plugin activation: create required database tables.
 */
function tsdb_activate() {
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $tables = [];

    $prefix = $wpdb->prefix . 'tsdb_';
    $tables["{$prefix}leagues"] = "CREATE TABLE {$prefix}leagues (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        ext_id varchar(50) NOT NULL,
        sport varchar(50) NOT NULL,
        country varchar(100) NOT NULL,
        name varchar(255) NOT NULL,
        season_current varchar(25) DEFAULT NULL,
        logo_url text DEFAULT NULL,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY ext_id (ext_id)
    ) $charset_collate";

    $tables["{$prefix}seasons"] = "CREATE TABLE {$prefix}seasons (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        league_id bigint(20) unsigned NOT NULL,
        name varchar(100) NOT NULL,
        year_start smallint NOT NULL,
        year_end smallint NOT NULL,
        ext_id varchar(50) NOT NULL,
        PRIMARY KEY (id),
        KEY league_id (league_id)
    ) $charset_collate";

    $tables["{$prefix}teams"] = "CREATE TABLE {$prefix}teams (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        league_id bigint(20) unsigned NOT NULL,
        name varchar(255) NOT NULL,
        short_name varchar(100) DEFAULT NULL,
        ext_id varchar(50) NOT NULL,
        badge_url text DEFAULT NULL,
        venue_id bigint(20) unsigned DEFAULT NULL,
        country varchar(100) DEFAULT NULL,
        founded int DEFAULT NULL,
        socials_json longtext DEFAULT NULL,
        PRIMARY KEY (id),
        KEY league_id (league_id),
        KEY ext_id (ext_id)
    ) $charset_collate";

    $tables["{$prefix}players"] = "CREATE TABLE {$prefix}players (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        team_id bigint(20) unsigned NOT NULL,
        name varchar(255) NOT NULL,
        pos varchar(10) DEFAULT NULL,
        ext_id varchar(50) NOT NULL,
        thumb_url text DEFAULT NULL,
        number varchar(20) DEFAULT NULL,
        nationality varchar(100) DEFAULT NULL,
        dob date DEFAULT NULL,
        bio_json longtext DEFAULT NULL,
        PRIMARY KEY (id),
        KEY team_id (team_id),
        KEY ext_id (ext_id)
    ) $charset_collate";

    $tables["{$prefix}venues"] = "CREATE TABLE {$prefix}venues (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        city varchar(100) DEFAULT NULL,
        country varchar(100) DEFAULT NULL,
        ext_id varchar(50) NOT NULL,
        image_url text DEFAULT NULL,
        capacity int DEFAULT NULL,
        lat decimal(10,6) DEFAULT NULL,
        lng decimal(10,6) DEFAULT NULL,
        PRIMARY KEY (id),
        KEY ext_id (ext_id)
    ) $charset_collate";

    $tables["{$prefix}events"] = "CREATE TABLE {$prefix}events (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        league_id bigint(20) unsigned NOT NULL,
        season_id bigint(20) unsigned NOT NULL,
        home_id bigint(20) unsigned NOT NULL,
        away_id bigint(20) unsigned NOT NULL,
        venue_id bigint(20) unsigned DEFAULT NULL,
        ext_id varchar(50) NOT NULL,
        status varchar(20) NOT NULL DEFAULT 'scheduled',
        utc_start datetime NOT NULL,
        home_score int DEFAULT NULL,
        away_score int DEFAULT NULL,
        round varchar(50) DEFAULT NULL,
        stage varchar(50) DEFAULT NULL,
        elapsed int DEFAULT NULL,
        period varchar(20) DEFAULT NULL,
        ref_json longtext DEFAULT NULL,
        tv_json longtext DEFAULT NULL,
        odds_json longtext DEFAULT NULL,
        weather_json longtext DEFAULT NULL,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP,
        checksum varchar(32) DEFAULT NULL,
        PRIMARY KEY (id),
        KEY league_season (league_id,season_id),
        KEY status_start (status, utc_start),
        KEY ext_id (ext_id)
    ) $charset_collate";

    $tables["{$prefix}event_timeline"] = "CREATE TABLE {$prefix}event_timeline (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        event_id bigint(20) unsigned NOT NULL,
        minute int DEFAULT NULL,
        type varchar(50) NOT NULL,
        team_id bigint(20) unsigned DEFAULT NULL,
        player_id bigint(20) unsigned DEFAULT NULL,
        assist_id bigint(20) unsigned DEFAULT NULL,
        detail_json longtext DEFAULT NULL,
        PRIMARY KEY (id),
        KEY event_id (event_id)
    ) $charset_collate";

    $tables["{$prefix}event_stats"] = "CREATE TABLE {$prefix}event_stats (
        event_id bigint(20) unsigned NOT NULL,
        stats_json longtext DEFAULT NULL,
        PRIMARY KEY (event_id)
    ) $charset_collate";

    $tables["{$prefix}standings"] = "CREATE TABLE {$prefix}standings (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        league_id bigint(20) unsigned NOT NULL,
        season_id bigint(20) unsigned NOT NULL,
        table_json longtext DEFAULT NULL,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP,
        checksum varchar(32) DEFAULT NULL,
        PRIMARY KEY (id),
        KEY league_season (league_id,season_id)
    ) $charset_collate";

    $tables["{$prefix}broadcast"] = "CREATE TABLE {$prefix}broadcast (
        league_id bigint(20) unsigned NOT NULL,
        season_id bigint(20) unsigned NOT NULL,
        date_utc date NOT NULL,
        country varchar(100) NOT NULL,
        channel varchar(200) NOT NULL,
        payload_json longtext DEFAULT NULL,
        PRIMARY KEY (league_id, season_id, date_utc, country, channel)
    ) $charset_collate";

    $tables["{$prefix}cache"] = "CREATE TABLE {$prefix}cache (
        cache_key varchar(191) NOT NULL,
        value mediumtext NOT NULL,
        ttl int NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (cache_key)
    ) $charset_collate";

    $tables["{$prefix}logs"] = "CREATE TABLE {$prefix}logs (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        ts datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        level varchar(20) NOT NULL,
        source varchar(100) NOT NULL,
        message text NOT NULL,
        context_json longtext DEFAULT NULL,
        PRIMARY KEY (id),
        KEY ts (ts)
    ) $charset_collate";

    foreach ( $tables as $sql ) {
        dbDelta( $sql );
    }
}
register_activation_hook( __FILE__, 'tsdb_activate' );

/**
 * Deactivation: clear scheduled events.
 */
function tsdb_deactivate() {
    wp_clear_scheduled_hook( 'tsdb_cron_tick' );
}
register_deactivation_hook( __FILE__, 'tsdb_deactivate' );


<?php
namespace TSDB;

/**
 * WP-CLI commands for TSDB operations.
 */
class CLI {
    protected $sync_manager;
    protected $cache;

    public function __construct( Sync_Manager $sync_manager, Cache_Store $cache ) {
        $this->sync_manager = $sync_manager;
        $this->cache        = $cache;
    }

    /**
     * Register commands with WP-CLI.
     */
    public function register() {
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            \WP_CLI::add_command( 'tsdb', $this );
        }
    }

    /**
     * Seed data for a league and season.
     *
     * ## OPTIONS
     *
     * <league>
     * : External league ID.
     *
     * <season>
     * : Season string.
     */
    public function seed( $args, $assoc_args ) {
        list( $league, $season ) = $args;
        $this->sync_manager->sync_seasons( $league );
        $this->sync_manager->sync_teams( $league );
        $count = $this->sync_manager->sync_events( $league, $season );
        if ( is_wp_error( $count ) ) {
            \WP_CLI::error( $count->get_error_message() );
        }
        \WP_CLI::success( sprintf( '%d events seeded', $count ) );
    }

    /**
     * Trigger live synchronization pass.
     */
    public function live( $args, $assoc_args ) {
        $this->sync_manager->cron_tick();
        \WP_CLI::success( 'Live sync triggered' );
    }

    /**
     * Purge caches and logs.
     */
    public function purge( $args, $assoc_args ) {
        $this->cache->flush();
        global $wpdb;
        $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}tsdb_logs" );
        \WP_CLI::success( 'Cache and logs purged' );
    }
}

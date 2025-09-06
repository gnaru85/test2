<?php
namespace TSDB;

/**
 * WP-CLI commands for TSDB operations.
 */
class CLI {
    protected $sync_manager;
    protected $cache;
    protected $logger;

    public function __construct( Sync_Manager $sync_manager, Cache_Store $cache, Logger $logger ) {
        $this->sync_manager = $sync_manager;
        $this->cache        = $cache;
        $this->logger       = $logger;
    }

    /**
     * Register commands with WP-CLI.
     */
    public function register() {
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            \WP_CLI::add_command( 'tsdb seed', [ $this, 'seed' ] );
            \WP_CLI::add_command( 'tsdb live', [ $this, 'live' ] );
            \WP_CLI::add_command( 'tsdb purge', [ $this, 'purge' ] );
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
        $result = $this->sync_manager->sync_seasons( $league );
        if ( is_wp_error( $result ) ) {
            \WP_CLI::error( $result->get_error_message() );
        }
        $result = $this->sync_manager->sync_teams( $league );
        if ( is_wp_error( $result ) ) {
            \WP_CLI::error( $result->get_error_message() );
        }
        $count = $this->sync_manager->sync_events( $league, $season );
        if ( is_wp_error( $count ) ) {
            \WP_CLI::error( $count->get_error_message() );
        }
        $this->logger->info( 'cli', 'Seeded league', [ 'league' => $league, 'season' => $season, 'events' => $count ] );
        \WP_CLI::success( sprintf( '%d events seeded', $count ) );
    }

    /**
     * Trigger live synchronization pass.
     */
    public function live( $args, $assoc_args ) {
        $this->sync_manager->cron_tick();
        $this->logger->info( 'cli', 'Live sync triggered' );
        \WP_CLI::success( 'Live sync triggered' );
    }

    /**
     * Purge caches and logs.
     */
    public function purge( $args, $assoc_args ) {
        $this->cache->flush();
        global $wpdb;
        $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}tsdb_logs" );
        $this->logger->info( 'cli', 'Cache and logs purged' );
        \WP_CLI::success( 'Cache and logs purged' );
    }
}

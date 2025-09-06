<?php
namespace TSDB;

/**
 * Handles synchronization jobs with TheSportsDB API.
 */
class Sync_Manager {
    protected $api;
    protected $logger;

    public function __construct( Api_Client $api, Logger $logger ) {
        $this->api    = $api;
        $this->logger = $logger;
    }

    public function init_cron() {
        add_action( 'tsdb_cron_tick', [ $this, 'cron_tick' ] );
        if ( ! wp_next_scheduled( 'tsdb_cron_tick' ) ) {
            wp_schedule_event( time(), 'minute', 'tsdb_cron_tick' );
        }
        add_filter( 'cron_schedules', function ( $schedules ) {
            if ( ! isset( $schedules['minute'] ) ) {
                $schedules['minute'] = [ 'interval' => 60, 'display' => __( 'Every Minute' ) ];
            }
            return $schedules;
        } );
    }

    public function cron_tick() {
        // Placeholder for cron jobs: update live events etc.
        $this->logger->info( 'cron', 'tick' );
    }

    /**
     * Import leagues by country and sport.
     */
    public function sync_leagues( $country, $sport ) {
        $data = $this->api->get( '/search_all_leagues.php', [ 'c' => $country, 's' => $sport ] );
        if ( is_wp_error( $data ) ) {
            return $data;
        }
        if ( empty( $data['countrys'] ) ) {
            return 0;
        }
        global $wpdb;
        $table = $wpdb->prefix . 'tsdb_leagues';
        $count = 0;
        foreach ( $data['countrys'] as $row ) {
            $wpdb->replace(
                $table,
                [
                    'ext_id'        => $row['idLeague'],
                    'sport'         => $row['strSport'],
                    'country'       => $row['strCountry'],
                    'name'          => $row['strLeague'],
                    'season_current'=> $row['strCurrentSeason'],
                    'logo_url'      => $row['strLogo'],
                ],
                [ '%s', '%s', '%s', '%s', '%s', '%s' ]
            );
            $count++;
        }
        return $count;
    }
}

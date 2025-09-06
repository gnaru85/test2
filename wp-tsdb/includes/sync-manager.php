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
        $this->logger->info( 'cron', 'tick' );
        global $wpdb;
        $leagues = $wpdb->get_results( "SELECT ext_id, season_current FROM {$wpdb->prefix}tsdb_leagues" );
        foreach ( $leagues as $league ) {
            if ( ! empty( $league->season_current ) ) {
                $this->sync_events( $league->ext_id, $league->season_current );
            }
        }
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

    /**
     * Import seasons for a league.
     *
     * @param string $league_ext_id External league ID.
     * @return int|\WP_Error
     */
    public function sync_seasons( $league_ext_id ) {
        $data = $this->api->get( '/search_all_seasons.php', [ 'id' => $league_ext_id ] );
        if ( is_wp_error( $data ) ) {
            return $data;
        }
        if ( empty( $data['seasons'] ) ) {
            return 0;
        }
        global $wpdb;
        $league_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}tsdb_leagues WHERE ext_id = %s", $league_ext_id ) );
        if ( ! $league_id ) {
            return new \WP_Error( 'tsdb_missing_league', __( 'League not found', 'tsdb' ) );
        }
        $table = $wpdb->prefix . 'tsdb_seasons';
        $count = 0;
        foreach ( $data['seasons'] as $row ) {
            $name = $row['strSeason'] ?? '';
            if ( ! $name ) {
                continue;
            }
            $year_start = $year_end = 0;
            if ( preg_match( '/(\d{4})[\/-](\d{4})/', $name, $m ) ) {
                $year_start = intval( $m[1] );
                $year_end   = intval( $m[2] );
            }
            $wpdb->replace(
                $table,
                [
                    'league_id' => $league_id,
                    'name'      => $name,
                    'year_start'=> $year_start,
                    'year_end'  => $year_end,
                    'ext_id'    => $row['idSeason'] ?? $name,
                ],
                [ '%d', '%s', '%d', '%d', '%s' ]
            );
            $count++;
        }
        return $count;
    }

    /**
     * Import teams for a league.
     *
     * @param string $league_ext_id External league ID.
     * @return int|\WP_Error
     */
    public function sync_teams( $league_ext_id ) {
        $data = $this->api->get( '/lookup_all_teams.php', [ 'id' => $league_ext_id ] );
        if ( is_wp_error( $data ) ) {
            return $data;
        }
        if ( empty( $data['teams'] ) ) {
            return 0;
        }
        global $wpdb;
        $league_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}tsdb_leagues WHERE ext_id = %s", $league_ext_id ) );
        if ( ! $league_id ) {
            return new \WP_Error( 'tsdb_missing_league', __( 'League not found', 'tsdb' ) );
        }
        $table = $wpdb->prefix . 'tsdb_teams';
        $count = 0;
        foreach ( $data['teams'] as $row ) {
            $wpdb->replace(
                $table,
                [
                    'league_id'   => $league_id,
                    'name'        => $row['strTeam'] ?? '',
                    'short_name'  => $row['strTeamShort'] ?? null,
                    'ext_id'      => $row['idTeam'] ?? '',
                    'badge_url'   => $row['strTeamBadge'] ?? null,
                    'venue_id'    => null,
                    'country'     => $row['strCountry'] ?? null,
                    'founded'     => isset( $row['intFormedYear'] ) ? intval( $row['intFormedYear'] ) : null,
                    'socials_json'=> ! empty( $row['strTwitter'] ) || ! empty( $row['strFacebook'] ) || ! empty( $row['strInstagram'] ) ? wp_json_encode( [
                        'twitter'   => $row['strTwitter'] ?? '',
                        'facebook'  => $row['strFacebook'] ?? '',
                        'instagram' => $row['strInstagram'] ?? '',
                    ] ) : null,
                ],
                [ '%d','%s','%s','%s','%s','%d','%s','%d','%s' ]
            );
            $count++;
        }
        return $count;
    }

    /**
     * Import events for a league and season.
     *
     * @param string $league_ext_id External league ID.
     * @param string $season Season name, e.g. '2023-2024'.
     * @return int|\WP_Error
     */
    public function sync_events( $league_ext_id, $season ) {
        $data = $this->api->get( '/eventsseason.php', [ 'id' => $league_ext_id, 's' => $season ] );
        if ( is_wp_error( $data ) ) {
            return $data;
        }
        if ( empty( $data['events'] ) ) {
            return 0;
        }
        global $wpdb;
        $league_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}tsdb_leagues WHERE ext_id = %s", $league_ext_id ) );
        if ( ! $league_id ) {
            return new \WP_Error( 'tsdb_missing_league', __( 'League not found', 'tsdb' ) );
        }
        $season_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}tsdb_seasons WHERE league_id = %d AND name = %s", $league_id, $season ) );
        if ( ! $season_id ) {
            $this->sync_seasons( $league_ext_id );
            $season_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}tsdb_seasons WHERE league_id = %d AND name = %s", $league_id, $season ) );
        }
        $table      = $wpdb->prefix . 'tsdb_events';
        $team_table = $wpdb->prefix . 'tsdb_teams';
        $count = 0;
        foreach ( $data['events'] as $row ) {
            $home_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$team_table} WHERE ext_id = %s", $row['idHomeTeam'] ?? '' ) );
            $away_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$team_table} WHERE ext_id = %s", $row['idAwayTeam'] ?? '' ) );
            if ( ! $home_id || ! $away_id ) {
                $this->sync_teams( $league_ext_id );
                $home_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$team_table} WHERE ext_id = %s", $row['idHomeTeam'] ?? '' ) );
                $away_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$team_table} WHERE ext_id = %s", $row['idAwayTeam'] ?? '' ) );
            }
            if ( ! $home_id || ! $away_id ) {
                continue;
            }
            $utc_start = null;
            if ( ! empty( $row['dateEvent'] ) ) {
                $time      = $row['strTime'] ?? '00:00:00';
                $utc_start = gmdate( 'Y-m-d H:i:s', strtotime( $row['dateEvent'] . ' ' . $time . ' UTC' ) );
            }
            $wpdb->replace(
                $table,
                [
                    'league_id'  => $league_id,
                    'season_id'  => $season_id,
                    'home_id'    => $home_id,
                    'away_id'    => $away_id,
                    'venue_id'   => null,
                    'ext_id'     => $row['idEvent'] ?? '',
                    'status'     => $row['strStatus'] ?? ( isset( $row['intHomeScore'] ) ? 'finished' : 'scheduled' ),
                    'utc_start'  => $utc_start,
                    'home_score' => isset( $row['intHomeScore'] ) ? intval( $row['intHomeScore'] ) : null,
                    'away_score' => isset( $row['intAwayScore'] ) ? intval( $row['intAwayScore'] ) : null,
                    'round'      => $row['intRound'] ?? null,
                    'stage'      => $row['strStage'] ?? null,
                ],
                [ '%d','%d','%d','%d','%d','%s','%s','%s','%d','%d','%s','%s' ]
            );
            $count++;
        }
        return $count;
    }
}

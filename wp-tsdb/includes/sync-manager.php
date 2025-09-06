<?php
namespace TSDB;

/**
 * Handles synchronization jobs with TheSportsDB API.
 */
class Sync_Manager {
    protected $api;
    protected $logger;
    protected $cache;
    protected $media;
    /** @var array<string,int> Timestamp of the next sync for each league */
    protected $next_runs = [];

    /** @var array<string> List of leagues currently eligible for polling */
    protected $active_leagues = [];

    public function __construct( Api_Client $api, Logger $logger, Cache_Store $cache, Media_Importer $media ) {
        $this->api    = $api;
        $this->logger = $logger;
        $this->cache  = $cache;
        $this->media  = $media;
        $this->next_runs     = get_option( 'tsdb_sync_next_runs', [] );
        $this->active_leagues = get_option( 'tsdb_active_leagues', [] );
    }

    public function init_cron() {
        add_action( 'tsdb_cron_tick', [ $this, 'cron_tick' ] );
        add_action( 'tsdb_sync_league', [ $this, 'run_league_sync' ], 10, 1 );
        add_action( 'tsdb_sync_event_details', [ $this, 'sync_event_details' ], 10, 1 );
        add_filter( 'cron_schedules', function ( $schedules ) {
            if ( ! isset( $schedules['minute'] ) ) {
                $schedules['minute'] = [ 'interval' => 60, 'display' => __( 'Every Minute' ) ];
            }
            return $schedules;
        } );
        if ( ! wp_next_scheduled( 'tsdb_cron_tick' ) ) {
            wp_schedule_event( time(), 'minute', 'tsdb_cron_tick' );
        }
        if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
            add_action( 'init', [ $this, 'recover_disabled_cron' ] );
        }
    }

    public function cron_tick() {
        $this->logger->info( 'cron', 'tick' );
        $this->update_active_leagues();
        $now = time();
        foreach ( $this->active_leagues as $ext_id ) {
            $next = $this->next_runs[ $ext_id ] ?? 0;
            if ( $next <= $now && ! wp_next_scheduled( 'tsdb_sync_league', [ $ext_id ] ) ) {
                wp_schedule_single_event( $now, 'tsdb_sync_league', [ $ext_id ] );
            }
        }
    }

    public function recover_disabled_cron() {
        $timestamp = wp_next_scheduled( 'tsdb_cron_tick' );
        if ( $timestamp && $timestamp <= time() ) {
            $this->logger->warning( 'cron', 'recovering missed cron tick' );
            wp_unschedule_event( $timestamp, 'tsdb_cron_tick' );
            $this->cron_tick();
            wp_schedule_event( time() + MINUTE_IN_SECONDS, 'minute', 'tsdb_cron_tick' );
        }
    }

    protected function update_active_leagues() {
        global $wpdb;
        $start = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );
        $end   = gmdate( 'Y-m-d H:i:s', time() + 14 * DAY_IN_SECONDS );
        $events = $wpdb->prefix . 'tsdb_events';
        $leagues = $wpdb->prefix . 'tsdb_leagues';
        $rows = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT l.ext_id FROM {$events} e JOIN {$leagues} l ON e.league_id = l.id\n"
            . "WHERE ( e.utc_start BETWEEN %s AND %s ) OR e.status IN ('live','inplay')",
            $start,
            $end
        ) );
        $this->active_leagues = $rows;
        update_option( 'tsdb_active_leagues', $rows );
        foreach ( array_keys( $this->next_runs ) as $key ) {
            if ( ! in_array( $key, $rows, true ) ) {
                wp_clear_scheduled_hook( 'tsdb_sync_league', [ $key ] );
                unset( $this->next_runs[ $key ] );
            }
        }
        update_option( 'tsdb_sync_next_runs', $this->next_runs );
    }

    protected function schedule_next_sync( $league_ext_id ) {
        global $wpdb;
        $now      = time();
        $interval = DAY_IN_SECONDS;
        $events   = $wpdb->prefix . 'tsdb_events';
        $leagues  = $wpdb->prefix . 'tsdb_leagues';
        $live = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$events} e JOIN {$leagues} l ON e.league_id = l.id\n"
            . "WHERE l.ext_id = %s AND e.status IN ('live','inplay')",
            $league_ext_id
        ) );
        if ( $live ) {
            $interval = rand( 30, 60 );
        } else {
            $today_start = gmdate( 'Y-m-d 00:00:00', $now );
            $today_end   = gmdate( 'Y-m-d 23:59:59', $now );
            $today = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$events} e JOIN {$leagues} l ON e.league_id = l.id\n"
                . "WHERE l.ext_id = %s AND e.utc_start BETWEEN %s AND %s",
                $league_ext_id,
                $today_start,
                $today_end
            ) );
            if ( $today ) {
                $interval = 10 * MINUTE_IN_SECONDS;
            } else {
                $future  = gmdate( 'Y-m-d H:i:s', $now + 14 * DAY_IN_SECONDS );
                $upcoming = $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$events} e JOIN {$leagues} l ON e.league_id = l.id\n"
                    . "WHERE l.ext_id = %s AND e.utc_start BETWEEN %s AND %s",
                    $league_ext_id,
                    gmdate( 'Y-m-d H:i:s', $now ),
                    $future
                ) );
                if ( $upcoming ) {
                    $interval = HOUR_IN_SECONDS;
                } else {
                    $finished = $wpdb->get_var( $wpdb->prepare(
                        "SELECT COUNT(*) FROM {$events} e JOIN {$leagues} l ON e.league_id = l.id\n"
                        . "WHERE l.ext_id = %s AND e.status = 'finished' AND e.utc_start >= %s",
                        $league_ext_id,
                        gmdate( 'Y-m-d H:i:s', $now - DAY_IN_SECONDS )
                    ) );
                    if ( $finished ) {
                        $interval = HOUR_IN_SECONDS;
                    } else {
                        $idx = array_search( $league_ext_id, $this->active_leagues, true );
                        if ( false !== $idx ) {
                            unset( $this->active_leagues[ $idx ] );
                            update_option( 'tsdb_active_leagues', $this->active_leagues );
                        }
                        unset( $this->next_runs[ $league_ext_id ] );
                        update_option( 'tsdb_sync_next_runs', $this->next_runs );
                        return;
                    }
                }
            }
        }
        $timestamp = $now + $interval;
        $this->next_runs[ $league_ext_id ] = $timestamp;
        update_option( 'tsdb_sync_next_runs', $this->next_runs );
        wp_schedule_single_event( $timestamp, 'tsdb_sync_league', [ $league_ext_id ] );
    }

    /**
     * Reschedule a league sync after hitting the API rate limit.
     *
     * @param string $league_ext_id External league ID.
     */
    protected function requeue_sync( $league_ext_id ) {
        $timestamp = $this->api->get_rate_limiter()->next_available();
        if ( $timestamp <= time() ) {
            $timestamp = time() + MINUTE_IN_SECONDS;
        }
        $this->next_runs[ $league_ext_id ] = $timestamp;
        update_option( 'tsdb_sync_next_runs', $this->next_runs );
        wp_schedule_single_event( $timestamp, 'tsdb_sync_league', [ $league_ext_id ] );
    }

    public function run_league_sync( $league_ext_id ) {
        // Refresh seasons and teams before syncing events.
        $result = $this->sync_seasons( $league_ext_id );
        if ( is_wp_error( $result ) ) {
            if ( 'tsdb_rate_limited' === $result->get_error_code() ) {
                $this->requeue_sync( $league_ext_id );
            }
            return;
        }

        $result = $this->sync_teams( $league_ext_id );
        if ( is_wp_error( $result ) ) {
            if ( 'tsdb_rate_limited' === $result->get_error_code() ) {
                $this->requeue_sync( $league_ext_id );
            }
            return;
        }

        $result = $this->sync_players( $league_ext_id );
        if ( is_wp_error( $result ) ) {
            if ( 'tsdb_rate_limited' === $result->get_error_code() ) {
                $this->requeue_sync( $league_ext_id );
            }
            return;
        }

        $result = $this->sync_venues( $league_ext_id );
        if ( is_wp_error( $result ) ) {
            if ( 'tsdb_rate_limited' === $result->get_error_code() ) {
                $this->requeue_sync( $league_ext_id );
            }
            return;
        }

        global $wpdb;
        $season = $wpdb->get_var( $wpdb->prepare(
            "SELECT season_current FROM {$wpdb->prefix}tsdb_leagues WHERE ext_id = %s",
            $league_ext_id
        ) );
        if ( $season ) {
            $result = $this->sync_events( $league_ext_id, $season );
            if ( is_wp_error( $result ) ) {
                if ( 'tsdb_rate_limited' === $result->get_error_code() ) {
                    $this->requeue_sync( $league_ext_id );
                }
                return;
            }
        }
        $this->schedule_next_sync( $league_ext_id );
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
            $existing = $wpdb->get_var( $wpdb->prepare( "SELECT logo_id FROM {$table} WHERE ext_id = %s", $row['idLeague'] ) );
            $logo_id  = $this->media->import( $row['strLogo'], $existing );
            $wpdb->replace(
                $table,
                [
                    'ext_id'        => $row['idLeague'],
                    'sport'         => $row['strSport'],
                    'country'       => $row['strCountry'],
                    'name'          => $row['strLeague'],
                    'season_current'=> $row['strCurrentSeason'],
                    'logo_id'       => $logo_id,
                    'logo_url'      => $row['strLogo'],
                ],
                [ '%s', '%s', '%s', '%s', '%s', '%d', '%s' ]
            );
            $count++;
        }
        $this->media->cleanup_orphans();
        return $count;
    }

    /**
     * Import players for all teams in a league.
     *
     * @param string $league_ext_id External league ID.
     * @return int|\WP_Error
     */
    public function sync_players( $league_ext_id ) {
        global $wpdb;
        $league_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}tsdb_leagues WHERE ext_id = %s", $league_ext_id ) );
        if ( ! $league_id ) {
            return new \WP_Error( 'tsdb_missing_league', __( 'League not found', 'tsdb' ) );
        }

        $teams = $wpdb->get_results( $wpdb->prepare( "SELECT id, ext_id FROM {$wpdb->prefix}tsdb_teams WHERE league_id = %d", $league_id ), ARRAY_A );
        $table = $wpdb->prefix . 'tsdb_players';
        $count = 0;

        foreach ( $teams as $team ) {
            $data = $this->api->get( '/lookup_all_players.php', [ 'id' => $team['ext_id'] ] );
            if ( is_wp_error( $data ) ) {
                return $data;
            }
            if ( empty( $data['player'] ) ) {
                continue;
            }
            foreach ( $data['player'] as $row ) {
                $existing = $wpdb->get_var( $wpdb->prepare( "SELECT thumb_id FROM {$table} WHERE ext_id = %s", $row['idPlayer'] ?? '' ) );
                $thumb_id = $this->media->import( $row['strCutout'] ?? ( $row['strThumb'] ?? '' ), $existing );
                $wpdb->replace(
                    $table,
                    [
                        'team_id'     => $team['id'],
                        'name'        => $row['strPlayer'] ?? '',
                        'pos'         => $row['strPosition'] ?? null,
                        'ext_id'      => $row['idPlayer'] ?? '',
                        'thumb_id'    => $thumb_id,
                        'thumb_url'   => $row['strCutout'] ?? ( $row['strThumb'] ?? null ),
                        'number'      => $row['strNumber'] ?? null,
                        'nationality' => $row['strNationality'] ?? null,
                        'dob'         => $row['dateBorn'] ?? null,
                        'bio_json'    => ! empty( $row['strDescriptionEN'] ) ? wp_json_encode( [ 'description' => $row['strDescriptionEN'] ] ) : null,
                    ],
                    [ '%d','%s','%s','%s','%d','%s','%s','%s','%s','%s' ]
                );
                $count++;
            }
            $this->cache->delete( 'players_' . $team['ext_id'] );
        }
        $this->media->cleanup_orphans();
        return $count;
    }

    /**
     * Import venues for teams in a league.
     *
     * @param string $league_ext_id External league ID.
     * @return int|\WP_Error
     */
    public function sync_venues( $league_ext_id ) {
        global $wpdb;
        $league_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}tsdb_leagues WHERE ext_id = %s", $league_ext_id ) );
        if ( ! $league_id ) {
            return new \WP_Error( 'tsdb_missing_league', __( 'League not found', 'tsdb' ) );
        }

        $teams = $wpdb->get_results( $wpdb->prepare( "SELECT id, ext_id FROM {$wpdb->prefix}tsdb_teams WHERE league_id = %d", $league_id ), ARRAY_A );
        $venue_table = $wpdb->prefix . 'tsdb_venues';
        $team_table  = $wpdb->prefix . 'tsdb_teams';
        $count = 0;

        foreach ( $teams as $team ) {
            $team_data = $this->api->get( '/lookupteam.php', [ 'id' => $team['ext_id'] ] );
            if ( is_wp_error( $team_data ) ) {
                return $team_data;
            }
            $team_row = $team_data['teams'][0] ?? null;
            if ( ! $team_row || empty( $team_row['strStadium'] ) ) {
                continue;
            }

            $venue_data = $this->api->get( '/searchvenues.php', [ 'v' => $team_row['strStadium'] ] );
            if ( is_wp_error( $venue_data ) ) {
                return $venue_data;
            }
            $venue = $venue_data['venues'][0] ?? null;
            if ( ! $venue ) {
                continue;
            }

            $existing_image = $wpdb->get_var( $wpdb->prepare( "SELECT image_id FROM {$venue_table} WHERE ext_id = %s", $venue['idVenue'] ) );
            $image_id       = $this->media->import( $venue['strThumb'] ?? '', $existing_image );

            $wpdb->replace(
                $venue_table,
                [
                    'name'     => $venue['strVenue'] ?? '',
                    'city'     => $venue['strCity'] ?? null,
                    'country'  => $venue['strCountry'] ?? null,
                    'ext_id'   => $venue['idVenue'] ?? '',
                    'image_id' => $image_id,
                    'image_url'=> $venue['strThumb'] ?? null,
                    'capacity' => isset( $venue['intCapacity'] ) ? intval( $venue['intCapacity'] ) : null,
                    'lat'      => isset( $venue['strLat'] ) ? floatval( $venue['strLat'] ) : null,
                    'lng'      => isset( $venue['strLong'] ) ? floatval( $venue['strLong'] ) : null,
                ],
                [ '%s','%s','%s','%s','%d','%s','%d','%f','%f' ]
            );

            $venue_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$venue_table} WHERE ext_id = %s", $venue['idVenue'] ) );
            if ( $venue_id ) {
                $wpdb->update( $team_table, [ 'venue_id' => $venue_id ], [ 'id' => $team['id'] ], [ '%d' ], [ '%d' ] );
            }
            $this->cache->delete( 'team_' . $team['ext_id'] );
            $this->cache->delete( 'venue_' . ( $venue['idVenue'] ?? '' ) );
            $count++;
        }

        $this->media->cleanup_orphans();
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
            $existing = $wpdb->get_var( $wpdb->prepare( "SELECT badge_id FROM {$table} WHERE ext_id = %s", $row['idTeam'] ?? '' ) );
            $badge_id = $this->media->import( $row['strTeamBadge'] ?? '', $existing );
            $wpdb->replace(
                $table,
                [
                    'league_id'   => $league_id,
                    'name'        => $row['strTeam'] ?? '',
                    'short_name'  => $row['strTeamShort'] ?? null,
                    'ext_id'      => $row['idTeam'] ?? '',
                    'badge_id'    => $badge_id,
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
                [ '%d','%s','%s','%s','%d','%s','%d','%s','%d','%s' ]
            );
            $count++;
        }
        $this->media->cleanup_orphans();
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

            if ( ! empty( $row['idEvent'] ) ) {
                $this->cache->delete( 'event_' . $row['idEvent'] );
                $status = $row['strStatus'] ?? ( isset( $row['intHomeScore'] ) ? 'finished' : 'scheduled' );
                if ( 'finished' === $status && ! wp_next_scheduled( 'tsdb_sync_event_details', [ $row['idEvent'] ] ) ) {
                    $has_stats = $wpdb->get_var( $wpdb->prepare(
                        "SELECT 1 FROM {$wpdb->prefix}tsdb_event_stats s JOIN {$table} e ON s.event_id = e.id WHERE e.ext_id = %s",
                        $row['idEvent']
                    ) );
                    if ( ! $has_stats ) {
                        wp_schedule_single_event( time() + 5 * MINUTE_IN_SECONDS, 'tsdb_sync_event_details', [ $row['idEvent'] ] );
                    }
                }
            }

            $count++;
        }
        if ( $count ) {
            $this->cache->delete( 'fixtures_' . $league_id . '_scheduled' );
            $this->cache->delete( 'fixtures_' . $league_id . '_live' );
            $this->cache->delete( 'fixtures_' . $league_id . '_inplay' );
            $this->cache->delete( 'fixtures_' . $league_id . '_finished' );
            $this->cache->delete( 'live_' . $league_id );
            $this->cache->delete( 'live_all' );
            // League standings depend on finished events.
            // Purge cached standings so they can be recalculated on next request.
            $this->cache->delete( 'standings_' . $league_ext_id . '_' . $season );
        }
        return $count;
    }

    /**
     * Fetch and store detailed post-match data for an event.
     *
     * @param string $event_ext_id External event ID.
     * @return void|\WP_Error
     */
    public function sync_event_details( $event_ext_id ) {
        global $wpdb;
        $events    = $wpdb->prefix . 'tsdb_events';
        $timeline  = $wpdb->prefix . 'tsdb_event_timeline';
        $stats_tbl = $wpdb->prefix . 'tsdb_event_stats';
        $tv_tbl    = $wpdb->prefix . 'tsdb_broadcast';

        $event = $wpdb->get_row( $wpdb->prepare( "SELECT id, league_id, season_id, utc_start FROM {$events} WHERE ext_id = %s", $event_ext_id ) );
        if ( ! $event ) {
            return new \WP_Error( 'tsdb_missing_event', __( 'Event not found', 'tsdb' ) );
        }

        // Timeline
        $res = $this->api->get( '/eventstimeline.php', [ 'id' => $event_ext_id ], true );
        if ( ! is_wp_error( $res ) && ! empty( $res['timeline'] ) ) {
            $wpdb->delete( $timeline, [ 'event_id' => $event->id ], [ '%d' ] );
            foreach ( $res['timeline'] as $row ) {
                $wpdb->insert(
                    $timeline,
                    [
                        'event_id'   => $event->id,
                        'minute'     => isset( $row['intTime'] ) ? intval( $row['intTime'] ) : null,
                        'type'       => $row['strTimeline'] ?? '',
                        'team_id'    => isset( $row['idTeam'] ) ? intval( $row['idTeam'] ) : null,
                        'player_id'  => isset( $row['idPlayer'] ) ? intval( $row['idPlayer'] ) : null,
                        'assist_id'  => isset( $row['idAssist'] ) ? intval( $row['idAssist'] ) : null,
                        'detail_json'=> wp_json_encode( $row ),
                    ],
                    [ '%d','%d','%s','%d','%d','%d','%s' ]
                );
            }
        }

        // Stats
        $res = $this->api->get( '/lookupeventstats.php', [ 'id' => $event_ext_id ] );
        if ( ! is_wp_error( $res ) && ! empty( $res['eventstats'] ) && 'Patreon Only' !== $res['eventstats'] ) {
            $wpdb->replace(
                $stats_tbl,
                [
                    'event_id'   => $event->id,
                    'stats_json' => wp_json_encode( $res['eventstats'] ),
                ],
                [ '%d', '%s' ]
            );
        }

        // Broadcast / TV info
        $res = $this->api->get( '/lookuptv.php', [ 'id' => $event_ext_id ] );
        if ( ! is_wp_error( $res ) && ! empty( $res['tvevent'] ) ) {
            foreach ( $res['tvevent'] as $row ) {
                $wpdb->replace(
                    $tv_tbl,
                    [
                        'league_id'    => $event->league_id,
                        'season_id'    => $event->season_id,
                        'date_utc'     => $row['dateEvent'] ?? gmdate( 'Y-m-d', strtotime( $event->utc_start ) ),
                        'country'      => $row['strCountry'] ?? '',
                        'channel'      => $row['strChannel'] ?? '',
                        'payload_json' => wp_json_encode( $row ),
                    ],
                    [ '%d','%d','%s','%s','%s','%s' ]
                );
            }
        }
    }
}

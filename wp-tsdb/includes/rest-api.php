<?php
namespace TSDB;

/**
 * Minimal REST API endpoints for blocks and admin UI.
 */
class Rest_API {
    protected $api;
    protected $cache;

    const TTL_LEAGUES  = HOUR_IN_SECONDS;
    const TTL_FIXTURE_SCHEDULED = 10 * MINUTE_IN_SECONDS;
    const TTL_FIXTURE_LIVE      = 30; // seconds
    const TTL_FIXTURE_FINISHED  = HOUR_IN_SECONDS;
    const TTL_STANDINGS = 10 * MINUTE_IN_SECONDS;
    const TTL_TEAM      = DAY_IN_SECONDS;
    const TTL_EVENT     = 10 * MINUTE_IN_SECONDS;
    const TTL_PLAYERS   = DAY_IN_SECONDS;
    const TTL_VENUE     = DAY_IN_SECONDS;
    const TTL_H2H       = DAY_IN_SECONDS;
    const TTL_TV        = HOUR_IN_SECONDS;

    public function __construct( Api_Client $api, Cache_Store $cache ) {
        $this->api   = $api;
        $this->cache = $cache;
    }

    /**
     * Register all REST API routes for the plugin.
     *
     * @return void
     */
    public function register_routes() {
        add_action( 'rest_api_init', function () {
            register_rest_route( 'tsdb/v1', '/leagues', [
                'methods'  => 'GET',
                'callback' => [ $this, 'get_leagues' ],
                'permission_callback' => [ $this, 'permissions_check' ],
            ] );
            register_rest_route( 'tsdb/v1', '/fixtures', [
                'methods'  => 'GET',
                'callback' => [ $this, 'get_fixtures' ],
                'permission_callback' => [ $this, 'permissions_check' ],
            ] );
            register_rest_route( 'tsdb/v1', '/ref/countries', [
                'methods'  => 'GET',
                'callback' => [ $this, 'remote_countries' ],
                'permission_callback' => [ $this, 'permissions_check' ],
            ] );
            register_rest_route( 'tsdb/v1', '/ref/sports', [
                'methods'  => 'GET',
                'callback' => [ $this, 'remote_sports' ],
                'permission_callback' => [ $this, 'permissions_check' ],
            ] );
            register_rest_route( 'tsdb/v1', '/ref/leagues', [
                'methods'  => 'GET',
                'callback' => [ $this, 'remote_leagues' ],
                'permission_callback' => [ $this, 'permissions_check' ],
            ] );
            register_rest_route( 'tsdb/v1', '/ref/seasons', [
                'methods'  => 'GET',
                'callback' => [ $this, 'remote_seasons' ],
                'permission_callback' => [ $this, 'permissions_check' ],
            ] );
            register_rest_route( 'tsdb/v1', '/live', [
                'methods'  => 'GET',
                'callback' => [ $this, 'get_live' ],
                'permission_callback' => [ $this, 'permissions_check' ],
                'args'     => [
                    'league' => [
                        'description'       => 'Internal league ID.',
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ] );
            register_rest_route( 'tsdb/v1', '/standings', [
                'methods'  => 'GET',
                'callback' => [ $this, 'get_standings' ],
                'permission_callback' => [ $this, 'permissions_check' ],
                'args'     => [
                    'league' => [
                        'description'       => 'External league ID.',
                        'type'              => 'integer',
                        'required'          => true,
                        'sanitize_callback' => 'absint',
                    ],
                    'season' => [
                        'description'       => 'Season identifier.',
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ] );
            register_rest_route( 'tsdb/v1', '/team/(?P<id>\d+)', [
                'methods'  => 'GET',
                'callback' => [ $this, 'get_team' ],
                'permission_callback' => [ $this, 'permissions_check' ],
                'args'     => [
                    'id' => [
                        'description'       => 'Team external ID.',
                        'type'              => 'integer',
                        'required'          => true,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ] );
            register_rest_route( 'tsdb/v1', '/team/(?P<id>\d+)/players', [
                'methods'  => 'GET',
                'callback' => [ $this, 'get_players' ],
                'permission_callback' => [ $this, 'permissions_check' ],
                'args'     => [
                    'id' => [
                        'description'       => 'Team external ID.',
                        'type'              => 'integer',
                        'required'          => true,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ] );
            register_rest_route( 'tsdb/v1', '/venue/(?P<id>\d+)', [
                'methods'  => 'GET',
                'callback' => [ $this, 'get_venue' ],
                'permission_callback' => [ $this, 'permissions_check' ],
                'args'     => [
                    'id' => [
                        'description'       => 'Venue external ID.',
                        'type'              => 'integer',
                        'required'          => true,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ] );
            register_rest_route( 'tsdb/v1', '/event/(?P<id>\d+)', [
                'methods'  => 'GET',
                'callback' => [ $this, 'get_event' ],
                'permission_callback' => [ $this, 'permissions_check' ],
                'args'     => [
                    'id' => [
                        'description'       => 'Event external ID.',
                        'type'              => 'integer',
                        'required'          => true,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ] );
            register_rest_route( 'tsdb/v1', '/h2h', [
                'methods'  => 'GET',
                'callback' => [ $this, 'get_h2h' ],
                'permission_callback' => [ $this, 'permissions_check' ],
                'args'     => [
                    'team1' => [
                        'description'       => 'First team internal ID.',
                        'type'              => 'integer',
                        'required'          => true,
                        'sanitize_callback' => 'absint',
                    ],
                    'team2' => [
                        'description'       => 'Second team internal ID.',
                        'type'              => 'integer',
                        'required'          => true,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ] );
            register_rest_route( 'tsdb/v1', '/tv', [
                'methods'  => 'GET',
                'callback' => [ $this, 'get_tv' ],
                'permission_callback' => [ $this, 'permissions_check' ],
                'args'     => [
                    'country' => [
                        'description'       => 'Country code for TV listings.',
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ] );
            register_rest_route( 'tsdb/v1', '/broadcast', [
                'methods'  => 'GET',
                'callback' => [ $this, 'get_broadcast' ],
                'permission_callback' => [ $this, 'permissions_check' ],
                'args'     => [
                    'event' => [
                        'description'       => 'Event external ID.',
                        'type'              => 'integer',
                        'required'          => true,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ] );
            register_rest_route( 'tsdb/v1', '/cache', [
                'methods'  => 'DELETE',
                'callback' => [ $this, 'purge_cache' ],
                'permission_callback' => [ $this, 'permissions_check' ],
            ] );
        } );
    }

    public function permissions_check( $request ) {
        $nonce = $request->get_header( 'X-WP-Nonce' );
        return $nonce && wp_verify_nonce( $nonce, 'tsdb_sync' ) && current_user_can( 'manage_options' );
    }

    /**
     * Send response with ETag support.
     *
     * @param \WP_REST_Request $request Request object.
     * @param mixed             $data    Response data.
     *
     * @return \WP_REST_Response
     */
    protected function etag_response( $request, $data ) {
        $etag  = '"' . md5( wp_json_encode( $data ) ) . '"';
        $match = $request->get_header( 'If-None-Match' );
        if ( $match && trim( $match ) === $etag ) {
            return new \WP_REST_Response( null, 304, [ 'ETag' => $etag ] );
        }
        $response = rest_ensure_response( $data );
        $response->header( 'ETag', $etag );
        return $response;
    }

    public function get_leagues( $request ) {
        global $wpdb;
        $country = sanitize_text_field( $request->get_param( 'country' ) );
        $sport   = sanitize_text_field( $request->get_param( 'sport' ) );
        $table   = $wpdb->prefix . 'tsdb_leagues';
        $where   = '1=1';
        $args    = [];
        if ( $country ) {
            $where  .= ' AND country = %s';
            $args[]  = $country;
        }
        if ( $sport ) {
            $where  .= ' AND sport = %s';
            $args[]  = $sport;
        }
        $cache_key = 'leagues_' . md5( $country . '_' . $sport );
        $rows      = $this->cache->get( $cache_key );
        if ( false === $rows ) {
            $sql  = $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where} ORDER BY name", $args );
            $rows = $wpdb->get_results( $sql );
            $this->cache->set( $cache_key, $rows, self::TTL_LEAGUES );
        }
        foreach ( $rows as $row ) {
            $row->logo_url = $row->logo_id ? wp_get_attachment_url( $row->logo_id ) : null;
        }
        return rest_ensure_response( $rows );
    }

    public function get_fixtures( $request ) {
        global $wpdb;
        $league = absint( $request->get_param( 'league' ) );
        $status = sanitize_text_field( $request->get_param( 'status' ) );
        $table     = $wpdb->prefix . 'tsdb_events';
        $cache_key = 'fixtures_' . $league . '_' . $status;
        $rows      = $this->cache->get( $cache_key );
        if ( false === $rows ) {
            $sql  = $wpdb->prepare( "SELECT * FROM {$table} WHERE league_id=%d AND status=%s ORDER BY utc_start ASC", $league, $status );
            $rows = $wpdb->get_results( $sql );
            $ttl  = self::TTL_FIXTURE_SCHEDULED;
            if ( in_array( $status, [ 'live', 'inplay' ], true ) ) {
                $ttl = self::TTL_FIXTURE_LIVE;
            } elseif ( 'finished' === $status ) {
                $ttl = self::TTL_FIXTURE_FINISHED;
            }
            $this->cache->set( $cache_key, $rows, $ttl );
        }
        $team_ids = [];
        foreach ( $rows as $row ) {
            $team_ids[] = $row->home_id;
            $team_ids[] = $row->away_id;
        }
        $team_ids = array_unique( array_map( 'intval', $team_ids ) );
        $badges = [];
        if ( $team_ids ) {
            $placeholders = implode( ',', array_fill( 0, count( $team_ids ), '%d' ) );
            $team_table   = $wpdb->prefix . 'tsdb_teams';
            $sql = $wpdb->prepare( "SELECT id, badge_id FROM {$team_table} WHERE id IN ($placeholders)", $team_ids );
            $results = $wpdb->get_results( $sql );
            foreach ( $results as $r ) {
                $badges[ $r->id ] = $r->badge_id;
            }
        }
        foreach ( $rows as $row ) {
            $row->home_badge = isset( $badges[ $row->home_id ] ) && $badges[ $row->home_id ] ? wp_get_attachment_url( $badges[ $row->home_id ] ) : null;
            $row->away_badge = isset( $badges[ $row->away_id ] ) && $badges[ $row->away_id ] ? wp_get_attachment_url( $badges[ $row->away_id ] ) : null;
        }
        return rest_ensure_response( $rows );
    }

    /**
     * Retrieve live fixtures.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public function get_live( $request ) {
        global $wpdb;
        $league   = absint( $request->get_param( 'league' ) );
        $table    = $wpdb->prefix . 'tsdb_events';
        $where    = "status IN ('live','inplay')";
        $args     = [];
        if ( $league ) {
            $where .= ' AND league_id=%d';
            $args[] = $league;
        }
        $cache_key = 'live_' . ( $league ? $league : 'all' );
        $rows      = $this->cache->get( $cache_key );
        if ( false === $rows ) {
            $sql  = $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where} ORDER BY utc_start ASC", $args );
            $rows = $wpdb->get_results( $sql );
            $this->cache->set( $cache_key, $rows, self::TTL_FIXTURE_LIVE );
        }
        $team_ids = [];
        foreach ( $rows as $row ) {
            $team_ids[] = $row->home_id;
            $team_ids[] = $row->away_id;
        }
        $team_ids = array_unique( array_map( 'intval', $team_ids ) );
        $badges   = [];
        if ( $team_ids ) {
            $placeholders = implode( ',', array_fill( 0, count( $team_ids ), '%d' ) );
            $team_table   = $wpdb->prefix . 'tsdb_teams';
            $sql          = $wpdb->prepare( "SELECT id, badge_id FROM {$team_table} WHERE id IN ($placeholders)", $team_ids );
            $results      = $wpdb->get_results( $sql );
            foreach ( $results as $r ) {
                $badges[ $r->id ] = $r->badge_id;
            }
        }
        foreach ( $rows as $row ) {
            $row->home_badge = isset( $badges[ $row->home_id ] ) && $badges[ $row->home_id ] ? wp_get_attachment_url( $badges[ $row->home_id ] ) : null;
            $row->away_badge = isset( $badges[ $row->away_id ] ) && $badges[ $row->away_id ] ? wp_get_attachment_url( $badges[ $row->away_id ] ) : null;
        }
        return $this->etag_response( $request, $rows );
    }

    /**
     * Retrieve league standings via API.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response|\WP_Error
     */
    public function get_standings( $request ) {
        $league    = absint( $request->get_param( 'league' ) );
        $season    = sanitize_text_field( $request->get_param( 'season' ) );
        $cache_key = 'standings_' . $league . '_' . $season;
        $data      = $this->cache->get( $cache_key );
        if ( false === $data ) {
            $res = $this->api->get( '/lookuptable.php', [ 'l' => $league, 's' => $season ] );
            if ( ! is_wp_error( $res ) && ! empty( $res['table'] ) ) {
                $data = $res['table'];
            } else {
                $data = $this->compute_standings( $league, $season );
            }
            $this->cache->set( $cache_key, $data, self::TTL_STANDINGS );
        }
        return $this->etag_response( $request, $data );
    }

    /**
     * Compute standings from local event data when API lacks info.
     *
     * @param int    $league_ext_id External league ID.
     * @param string $season_name    Season identifier.
     * @return array
     */
    protected function compute_standings( $league_ext_id, $season_name ) {
        global $wpdb;
        $league_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}tsdb_leagues WHERE ext_id = %s", $league_ext_id ) );
        if ( ! $league_id ) {
            return [];
        }
        if ( ! $season_name ) {
            $season_name = $wpdb->get_var( $wpdb->prepare( "SELECT season_current FROM {$wpdb->prefix}tsdb_leagues WHERE id = %d", $league_id ) );
        }
        $season_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}tsdb_seasons WHERE league_id = %d AND name = %s", $league_id, $season_name ) );
        if ( ! $season_id ) {
            return [];
        }
        $teams_table  = $wpdb->prefix . 'tsdb_teams';
        $events_table = $wpdb->prefix . 'tsdb_events';
        $teams = $wpdb->get_results( $wpdb->prepare( "SELECT id, ext_id, name FROM {$teams_table} WHERE league_id = %d", $league_id ), ARRAY_A );
        if ( ! $teams ) {
            return [];
        }
        $stats = [];
        foreach ( $teams as $t ) {
            $stats[ (int) $t['id'] ] = [
                'teamid'         => $t['ext_id'],
                'name'           => $t['name'],
                'played'         => 0,
                'win'            => 0,
                'loss'           => 0,
                'draw'           => 0,
                'goalsfor'       => 0,
                'goalsagainst'   => 0,
                'goalsdifference'=> 0,
                'total'          => 0,
            ];
        }
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT home_id, away_id, home_score, away_score FROM {$events_table} WHERE league_id = %d AND season_id = %d AND status = 'finished' AND home_score IS NOT NULL AND away_score IS NOT NULL", $league_id, $season_id ) );
        foreach ( $rows as $row ) {
            if ( ! isset( $stats[ $row->home_id ] ) || ! isset( $stats[ $row->away_id ] ) ) {
                continue;
            }
            $home = &$stats[ $row->home_id ];
            $away = &$stats[ $row->away_id ];
            $home['played']++;
            $away['played']++;
            $home['goalsfor']     += (int) $row->home_score;
            $home['goalsagainst'] += (int) $row->away_score;
            $away['goalsfor']     += (int) $row->away_score;
            $away['goalsagainst'] += (int) $row->home_score;
            if ( $row->home_score > $row->away_score ) {
                $home['win']++;
                $away['loss']++;
                $home['total'] += 3;
            } elseif ( $row->home_score < $row->away_score ) {
                $away['win']++;
                $home['loss']++;
                $away['total'] += 3;
            } else {
                $home['draw']++;
                $away['draw']++;
                $home['total']++;
                $away['total']++;
            }
        }
        foreach ( $stats as &$s ) {
            $s['goalsdifference'] = $s['goalsfor'] - $s['goalsagainst'];
        }
        unset( $s );
        usort( $stats, function ( $a, $b ) {
            if ( $a['total'] === $b['total'] ) {
                if ( $a['goalsdifference'] === $b['goalsdifference'] ) {
                    return $b['goalsfor'] <=> $a['goalsfor'];
                }
                return $b['goalsdifference'] <=> $a['goalsdifference'];
            }
            return $b['total'] <=> $a['total'];
        } );
        return array_values( $stats );
    }

    /**
     * Retrieve a team from the API.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response|\WP_Error
     */
    public function get_team( $request ) {
        $id        = absint( $request['id'] );
        $cache_key = 'team_' . $id;
        $data      = $this->cache->get( $cache_key );
        if ( false === $data ) {
            $res = $this->api->get( '/lookupteam.php', [ 'id' => $id ] );
            if ( is_wp_error( $res ) ) {
                return $res;
            }
            $data = $res['teams'][0] ?? null;
            $this->cache->set( $cache_key, $data, self::TTL_TEAM );
        }
        if ( $data ) {
            global $wpdb;
            $badge_id = $wpdb->get_var( $wpdb->prepare( "SELECT badge_id FROM {$wpdb->prefix}tsdb_teams WHERE ext_id = %s", $data['idTeam'] ?? '' ) );
            $data['badge_url'] = $badge_id ? wp_get_attachment_url( $badge_id ) : null;
        }
        return $this->etag_response( $request, $data );
    }

    /**
     * Retrieve players for a team from the local database.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public function get_players( $request ) {
        global $wpdb;
        $ext_id    = absint( $request['id'] );
        $cache_key = 'players_' . $ext_id;
        $data      = $this->cache->get( $cache_key );
        if ( false === $data ) {
            $team_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}tsdb_teams WHERE ext_id = %s", $ext_id ) );
            if ( $team_id ) {
                $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}tsdb_players WHERE team_id = %d ORDER BY name", $team_id ), ARRAY_A );
                foreach ( $rows as &$row ) {
                    if ( $row['thumb_id'] ) {
                        $row['thumb_url'] = wp_get_attachment_url( $row['thumb_id'] );
                    }
                }
                unset( $row );
                $data = $rows;
            } else {
                $data = [];
            }
            $this->cache->set( $cache_key, $data, self::TTL_PLAYERS );
        }
        return $this->etag_response( $request, $data );
    }

    /**
     * Retrieve an event from the API.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response|\WP_Error
     */
    public function get_event( $request ) {
        $id        = absint( $request['id'] );
        $cache_key = 'event_' . $id;
        $data      = $this->cache->get( $cache_key );
        if ( false === $data ) {
            $res = $this->api->get( '/lookupevent.php', [ 'id' => $id ] );
            if ( is_wp_error( $res ) ) {
                return $res;
            }
            $data = $res['events'][0] ?? null;
            $this->cache->set( $cache_key, $data, self::TTL_EVENT );
        }
        if ( $data ) {
            global $wpdb;
            $team_table    = $wpdb->prefix . 'tsdb_teams';
            $event_table   = $wpdb->prefix . 'tsdb_events';
            $timeline_tbl  = $wpdb->prefix . 'tsdb_event_timeline';
            $stats_tbl     = $wpdb->prefix . 'tsdb_event_stats';
            $home_badge_id = $wpdb->get_var( $wpdb->prepare( "SELECT badge_id FROM {$team_table} WHERE ext_id = %s", $data['idHomeTeam'] ?? '' ) );
            $away_badge_id = $wpdb->get_var( $wpdb->prepare( "SELECT badge_id FROM {$team_table} WHERE ext_id = %s", $data['idAwayTeam'] ?? '' ) );
            $data['home_badge'] = $home_badge_id ? wp_get_attachment_url( $home_badge_id ) : null;
            $data['away_badge'] = $away_badge_id ? wp_get_attachment_url( $away_badge_id ) : null;

            $event_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$event_table} WHERE ext_id = %s", $id ) );
            if ( $event_id ) {
                $rows = $wpdb->get_results( $wpdb->prepare( "SELECT minute, type, team_id, player_id, assist_id, detail_json FROM {$timeline_tbl} WHERE event_id = %d ORDER BY minute", $event_id ) );
                foreach ( $rows as $r ) {
                    $r->detail_json = $r->detail_json ? json_decode( $r->detail_json, true ) : null;
                }
                $data['timeline'] = $rows;
                $stats = $wpdb->get_var( $wpdb->prepare( "SELECT stats_json FROM {$stats_tbl} WHERE event_id = %d", $event_id ) );
                $data['stats'] = $stats ? json_decode( $stats, true ) : null;
            }
        }
        return $this->etag_response( $request, $data );
    }

    /**
     * Retrieve a venue from the local database.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public function get_venue( $request ) {
        global $wpdb;
        $ext_id   = absint( $request['id'] );
        $cache_key = 'venue_' . $ext_id;
        $data      = $this->cache->get( $cache_key );
        if ( false === $data ) {
            $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}tsdb_venues WHERE ext_id = %s", $ext_id ), ARRAY_A );
            if ( $row && $row['image_id'] ) {
                $row['image_url'] = wp_get_attachment_url( $row['image_id'] );
            }
            $data = $row;
            $this->cache->set( $cache_key, $data, self::TTL_VENUE );
        }
        return $this->etag_response( $request, $data );
    }

    /**
     * Retrieve head-to-head fixtures between two teams.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public function get_h2h( $request ) {
        global $wpdb;
        $team1 = absint( $request->get_param( 'team1' ) );
        $team2 = absint( $request->get_param( 'team2' ) );
        $table     = $wpdb->prefix . 'tsdb_events';
        $cache_key = 'h2h_' . $team1 . '_' . $team2;
        $payload   = $this->cache->get( $cache_key );
        if ( false === $payload ) {
            $sql    = $wpdb->prepare( "SELECT * FROM {$table} WHERE (home_id=%d AND away_id=%d) OR (home_id=%d AND away_id=%d) ORDER BY utc_start DESC", $team1, $team2, $team2, $team1 );
            $rows   = $wpdb->get_results( $sql );
            $summary = [
                $team1 => [ 'wins' => 0, 'losses' => 0, 'draws' => 0, 'for' => 0, 'against' => 0 ],
                $team2 => [ 'wins' => 0, 'losses' => 0, 'draws' => 0, 'for' => 0, 'against' => 0 ],
            ];
            foreach ( $rows as $r ) {
                if ( null === $r->home_score || null === $r->away_score ) {
                    continue;
                }
                $summary[ $r->home_id ]['for']     += (int) $r->home_score;
                $summary[ $r->home_id ]['against'] += (int) $r->away_score;
                $summary[ $r->away_id ]['for']     += (int) $r->away_score;
                $summary[ $r->away_id ]['against'] += (int) $r->home_score;
                if ( $r->home_score > $r->away_score ) {
                    $summary[ $r->home_id ]['wins']++;
                    $summary[ $r->away_id ]['losses']++;
                } elseif ( $r->home_score < $r->away_score ) {
                    $summary[ $r->away_id ]['wins']++;
                    $summary[ $r->home_id ]['losses']++;
                } else {
                    $summary[ $r->home_id ]['draws']++;
                    $summary[ $r->away_id ]['draws']++;
                }
            }
            $payload = [ 'matches' => $rows, 'summary' => $summary ];
            $this->cache->set( $cache_key, $payload, self::TTL_H2H );
        } else {
            $rows    = $payload['matches'];
            $summary = $payload['summary'];
        }
        $team_ids = [];
        foreach ( $rows as $row ) {
            $team_ids[] = $row->home_id;
            $team_ids[] = $row->away_id;
        }
        $team_ids = array_unique( array_map( 'intval', $team_ids ) );
        $badges   = [];
        if ( $team_ids ) {
            $placeholders = implode( ',', array_fill( 0, count( $team_ids ), '%d' ) );
            $team_table   = $wpdb->prefix . 'tsdb_teams';
            $sql          = $wpdb->prepare( "SELECT id, badge_id FROM {$team_table} WHERE id IN ($placeholders)", $team_ids );
            $results      = $wpdb->get_results( $sql );
            foreach ( $results as $r ) {
                $badges[ $r->id ] = $r->badge_id;
            }
        }
        foreach ( $rows as $row ) {
            $row->home_badge = isset( $badges[ $row->home_id ] ) && $badges[ $row->home_id ] ? wp_get_attachment_url( $badges[ $row->home_id ] ) : null;
            $row->away_badge = isset( $badges[ $row->away_id ] ) && $badges[ $row->away_id ] ? wp_get_attachment_url( $badges[ $row->away_id ] ) : null;
        }
        $payload['matches'] = $rows;
        return $this->etag_response( $request, $payload );
    }

    /**
     * Retrieve TV listings for upcoming events.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response|\WP_Error
     */
    public function get_tv( $request ) {
        $country   = sanitize_text_field( $request->get_param( 'country' ) );
        $cache_key = 'tv_' . md5( $country );
        $data      = $this->cache->get( $cache_key );
        if ( false === $data ) {
            $res = $this->api->get( '/eventstv.php', [ 'c' => $country ], true );
            if ( is_wp_error( $res ) ) {
                return $res;
            }
            $data = $res['tvevents'] ?? [];
            $this->cache->set( $cache_key, $data, self::TTL_TV );
        }
        return $this->etag_response( $request, $data );
    }

    /**
     * Retrieve broadcast information for an event from the local database.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public function get_broadcast( $request ) {
        global $wpdb;
        $event_ext = absint( $request->get_param( 'event' ) );
        $cache_key = 'broadcast_' . $event_ext;
        $data      = $this->cache->get( $cache_key );
        if ( false === $data ) {
            $events = $wpdb->prefix . 'tsdb_events';
            $tv_tbl = $wpdb->prefix . 'tsdb_broadcast';
            $event  = $wpdb->get_row( $wpdb->prepare( "SELECT league_id, season_id, utc_start FROM {$events} WHERE ext_id = %s", $event_ext ) );
            if ( $event ) {
                $date = gmdate( 'Y-m-d', strtotime( $event->utc_start ) );
                $rows = $wpdb->get_results( $wpdb->prepare( "SELECT country, channel, payload_json FROM {$tv_tbl} WHERE league_id = %d AND season_id = %d AND date_utc = %s", $event->league_id, $event->season_id, $date ) );
                $data = [];
                foreach ( $rows as $row ) {
                    $payload = $row->payload_json ? json_decode( $row->payload_json, true ) : [];
                    if ( isset( $payload['idEvent'] ) && (string) $payload['idEvent'] !== (string) $event_ext ) {
                        continue;
                    }
                    $payload['country'] = $row->country;
                    $payload['channel'] = $row->channel;
                    $data[] = $payload;
                }
            } else {
                $data = [];
            }
            $this->cache->set( $cache_key, $data, self::TTL_TV );
        }
        return $this->etag_response( $request, $data );
    }

    public function remote_countries() {
        $data = $this->api->countries();
        return rest_ensure_response( $data['countries'] ?? [] );
    }

    public function remote_sports() {
        $data = $this->api->sports();
        return rest_ensure_response( $data['sports'] ?? [] );
    }

    public function remote_leagues( $request ) {
        $country = sanitize_text_field( $request->get_param( 'country' ) );
        $sport   = sanitize_text_field( $request->get_param( 'sport' ) );
        $data    = $this->api->leagues( $country, $sport );
        return rest_ensure_response( $data['countrys'] ?? [] );
    }

    public function remote_seasons( $request ) {
        $league = sanitize_text_field( $request->get_param( 'league' ) );
        $data   = $this->api->seasons( $league );
        return rest_ensure_response( $data['seasons'] ?? [] );
    }

    public function purge_cache() {
        $this->cache->flush();
        return rest_ensure_response( [ 'purged' => true ] );
    }
}

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
    const TTL_H2H       = DAY_IN_SECONDS;
    const TTL_TV        = HOUR_IN_SECONDS;

    public function __construct( Api_Client $api, Cache_Store $cache ) {
        $this->api   = $api;
        $this->cache = $cache;
    }

    public function register_routes() {
        add_action( 'rest_api_init', function () {
            register_rest_route( 'tsdb/v1', '/leagues', [
                'methods'  => 'GET',
                'callback' => [ $this, 'get_leagues' ],
                'permission_callback' => '__return_true',
            ] );
            register_rest_route( 'tsdb/v1', '/fixtures', [
                'methods'  => 'GET',
                'callback' => [ $this, 'get_fixtures' ],
                'permission_callback' => '__return_true',
            ] );
            register_rest_route( 'tsdb/v1', '/ref/countries', [
                'methods'  => 'GET',
                'callback' => [ $this, 'remote_countries' ],
                'permission_callback' => '__return_true',
            ] );
            register_rest_route( 'tsdb/v1', '/ref/sports', [
                'methods'  => 'GET',
                'callback' => [ $this, 'remote_sports' ],
                'permission_callback' => '__return_true',
            ] );
            register_rest_route( 'tsdb/v1', '/ref/leagues', [
                'methods'  => 'GET',
                'callback' => [ $this, 'remote_leagues' ],
                'permission_callback' => '__return_true',
            ] );
            register_rest_route( 'tsdb/v1', '/ref/seasons', [
                'methods'  => 'GET',
                'callback' => [ $this, 'remote_seasons' ],
                'permission_callback' => '__return_true',
            ] );
            register_rest_route( 'tsdb/v1', '/live', [
                'methods'  => 'GET',
                'callback' => [ $this, 'get_live' ],
                'permission_callback' => '__return_true',
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
                'permission_callback' => '__return_true',
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
                'permission_callback' => '__return_true',
                'args'     => [
                    'id' => [
                        'description'       => 'Team external ID.',
                        'type'              => 'integer',
                        'required'          => true,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ] );
            register_rest_route( 'tsdb/v1', '/event/(?P<id>\d+)', [
                'methods'  => 'GET',
                'callback' => [ $this, 'get_event' ],
                'permission_callback' => '__return_true',
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
                'permission_callback' => '__return_true',
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
                'permission_callback' => '__return_true',
                'args'     => [
                    'country' => [
                        'description'       => 'Country code for TV listings.',
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ] );
            register_rest_route( 'tsdb/v1', '/cache', [
                'methods'  => 'DELETE',
                'callback' => [ $this, 'purge_cache' ],
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                },
            ] );
        } );
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
        return $this->etag_response( $request, $rows );
    }

    /**
     * Retrieve league standings via API.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response|\WP_Error
     */
    public function get_standings( $request ) {
        $league = absint( $request->get_param( 'league' ) );
        $season = sanitize_text_field( $request->get_param( 'season' ) );
        $cache_key = 'standings_' . $league . '_' . $season;
        $data      = $this->cache->get( $cache_key );
        if ( false === $data ) {
            $res = $this->api->get( '/lookuptable.php', [ 'l' => $league, 's' => $season ] );
            if ( is_wp_error( $res ) ) {
                return $res;
            }
            $data = $res['table'] ?? [];
            $this->cache->set( $cache_key, $data, self::TTL_STANDINGS );
        }
        return $this->etag_response( $request, $data );
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
        $table = $wpdb->prefix . 'tsdb_events';
        $cache_key = 'h2h_' . $team1 . '_' . $team2;
        $rows      = $this->cache->get( $cache_key );
        if ( false === $rows ) {
            $sql  = $wpdb->prepare( "SELECT * FROM {$table} WHERE (home_id=%d AND away_id=%d) OR (home_id=%d AND away_id=%d) ORDER BY utc_start DESC", $team1, $team2, $team2, $team1 );
            $rows = $wpdb->get_results( $sql );
            $this->cache->set( $cache_key, $rows, self::TTL_H2H );
        }
        return $this->etag_response( $request, $rows );
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

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
            register_rest_route( 'tsdb/v1', '/cache', [
                'methods'  => 'DELETE',
                'callback' => [ $this, 'purge_cache' ],
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                },
            ] );
        } );
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

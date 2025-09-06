<?php
namespace TSDB;

/**
 * Minimal REST API endpoints for blocks and admin UI.
 */
class Rest_API {
    protected $api;

    public function __construct( Api_Client $api ) {
        $this->api = $api;
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
        $sql = $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where} ORDER BY name", $args );
        $rows = $wpdb->get_results( $sql );
        return rest_ensure_response( $rows );
    }

    public function get_fixtures( $request ) {
        global $wpdb;
        $league = absint( $request->get_param( 'league' ) );
        $status = sanitize_text_field( $request->get_param( 'status' ) );
        $table  = $wpdb->prefix . 'tsdb_events';
        $sql    = $wpdb->prepare( "SELECT * FROM {$table} WHERE league_id=%d AND status=%s ORDER BY utc_start ASC", $league, $status );
        $rows   = $wpdb->get_results( $sql );
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
}

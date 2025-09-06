<?php
namespace TSDB;

/**
 * Simple API client for TheSportsDB.
 */
class Api_Client {
    protected $logger;
    protected $rate_limiter;
    protected $base_v1 = 'https://www.thesportsdb.com/api/v1/json';
    protected $base_v2 = 'https://www.thesportsdb.com/api/v2/json';

    public function __construct( Logger $logger, Rate_Limiter $rate_limiter ) {
        $this->logger       = $logger;
        $this->rate_limiter = $rate_limiter;
    }

    /**
     * Perform GET request to API.
     *
     * @param string $endpoint Endpoint path e.g. '/search_all_leagues.php'.
     * @param array  $params   Query parameters.
     * @param bool   $v2       Whether to use v2 API.
     *
     * @return array|WP_Error
     */
    public function get( $endpoint, $params = [], $v2 = false ) {
        $key = get_option( 'tsdb_api_key' );
        if ( empty( $key ) ) {
            return new \WP_Error( 'tsdb_no_key', __( 'API key not set', 'tsdb' ) );
        }
        if ( ! $this->rate_limiter->allow() ) {
            return new \WP_Error( 'tsdb_rate_limited', __( 'Rate limit exceeded', 'tsdb' ) );
        }

        $base = $v2 ? $this->base_v2 : $this->base_v1;
        $url  = sprintf( '%s/%s%s', $base, $key, $endpoint );
        if ( ! empty( $params ) ) {
            $url = add_query_arg( $params, $url );
        }
        $response = wp_remote_get( $url, [ 'timeout' => 20 ] );
        if ( is_wp_error( $response ) ) {
            $this->logger->error( 'api', $response->get_error_message() );
            return $response;
        }
        $code = wp_remote_retrieve_response_code( $response );
        if ( $code >= 400 ) {
            $this->logger->error( 'api', 'HTTP ' . $code . ' for ' . $endpoint );
            return new \WP_Error( 'tsdb_http_' . $code, 'HTTP ' . $code );
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        return $data;
    }

    /**
     * Convenience wrapper to list all sports.
     *
     * @return array|\WP_Error
     */
    public function sports() {
        return $this->get( '/all_sports.php' );
    }

    /**
     * List all countries supported by the API.
     *
     * @return array|\WP_Error
     */
    public function countries() {
        return $this->get( '/all_countries.php' );
    }

    /**
     * List leagues for a country and sport.
     *
     * @param string $country Country name.
     * @param string $sport   Sport name.
     *
     * @return array|\WP_Error
     */
    public function leagues( $country, $sport ) {
        return $this->get( '/search_all_leagues.php', [ 'c' => $country, 's' => $sport ] );
    }

    /**
     * List seasons for a league.
     *
     * @param string $league_id External league ID.
     *
     * @return array|\WP_Error
     */
    public function seasons( $league_id ) {
        return $this->get( '/search_all_seasons.php', [ 'id' => $league_id ] );
    }
}

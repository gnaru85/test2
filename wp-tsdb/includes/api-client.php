<?php
namespace TSDB;

/**
 * Simple API client for TheSportsDB.
 */
class Api_Client {
    protected $logger;
    protected $rate_limiter;
    protected $cache;
    protected $base_v1 = 'https://www.thesportsdb.com/api/v1/json';
    protected $base_v2 = 'https://www.thesportsdb.com/api/v2/json';

    const TTL_SPORTS    = DAY_IN_SECONDS;
    const TTL_COUNTRIES = DAY_IN_SECONDS;
    const TTL_LEAGUES   = DAY_IN_SECONDS;
    const TTL_SEASONS   = DAY_IN_SECONDS;

    public function __construct( Logger $logger, Rate_Limiter $rate_limiter, Cache_Store $cache ) {
        $this->logger       = $logger;
        $this->rate_limiter = $rate_limiter;
        $this->cache        = $cache;
    }

    /**
     * Perform GET request to API.
     *
     * Retries up to three times with exponential backoff (15s, 30s, 60s) when
     * requests fail.
     *
     * @param string $endpoint Endpoint path e.g. '/search_all_leagues.php'.
     * @param array  $params   Query parameters.
     * @param bool   $v2       Whether to use v2 API.
     *
     * @return array|WP_Error
     */
    public function get( $endpoint, $params = [], $v2 = false ) {
        $key = \tsdb_get_api_key();
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

        $response = null;
        $delay    = 15;
        for ( $attempt = 0; $attempt < 4; $attempt++ ) {
            if ( $attempt > 0 ) {
                $this->logger->warning( 'api', 'retrying in ' . $delay . 's' );
                sleep( $delay );
                $delay = min( $delay * 2, 60 );
            }

            $response = wp_remote_get( $url, [ 'timeout' => 20 ] );
            if ( is_wp_error( $response ) ) {
                $this->logger->error( 'api', $response->get_error_message() );
            } else {
                $code = wp_remote_retrieve_response_code( $response );
                if ( $code < 400 ) {
                    break; // success
                }
                $this->logger->error( 'api', 'HTTP ' . $code . ' for ' . $endpoint );
                if ( 429 === $code ) {
                    $response = new \WP_Error( 'tsdb_rate_limited', 'HTTP 429' );
                } elseif ( $code >= 500 && $code < 600 ) {
                    $response = new \WP_Error( 'tsdb_server_error', 'HTTP ' . $code );
                } else {
                    $response = new \WP_Error( 'tsdb_http_' . $code, 'HTTP ' . $code );
                }
            }

            if ( $attempt === 3 ) {
                return $response;
            }
        }

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        return $data;
    }

    /**
     * Expose the rate limiter instance.
     */
    public function get_rate_limiter() {
        return $this->rate_limiter;
    }

    /**
     * Convenience wrapper to list all sports.
     *
     * @return array|\WP_Error
     */
    public function sports() {
        $key  = 'sports';
        $data = $this->cache->get( $key );
        if ( false !== $data ) {
            return $data;
        }
        $data = $this->get( '/all_sports.php' );
        if ( ! is_wp_error( $data ) ) {
            $this->cache->set( $key, $data, self::TTL_SPORTS );
        }
        return $data;
    }

    /**
     * List all countries supported by the API.
     *
     * @return array|\WP_Error
     */
    public function countries() {
        $key  = 'countries';
        $data = $this->cache->get( $key );
        if ( false !== $data ) {
            return $data;
        }
        $data = $this->get( '/all_countries.php' );
        if ( ! is_wp_error( $data ) ) {
            $this->cache->set( $key, $data, self::TTL_COUNTRIES );
        }
        return $data;
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
        $key  = 'leagues_' . md5( $country . '_' . $sport );
        $data = $this->cache->get( $key );
        if ( false !== $data ) {
            return $data;
        }
        $data = $this->get( '/search_all_leagues.php', [ 'c' => $country, 's' => $sport ] );
        if ( ! is_wp_error( $data ) ) {
            $this->cache->set( $key, $data, self::TTL_LEAGUES );
        }
        return $data;
    }

    /**
     * List seasons for a league.
     *
     * @param string $league_id External league ID.
     *
     * @return array|\WP_Error
     */
    public function seasons( $league_id ) {
        $key  = 'seasons_' . $league_id;
        $data = $this->cache->get( $key );
        if ( false !== $data ) {
            return $data;
        }
        $data = $this->get( '/search_all_seasons.php', [ 'id' => $league_id ] );
        if ( ! is_wp_error( $data ) ) {
            $this->cache->set( $key, $data, self::TTL_SEASONS );
        }
        return $data;
    }

    /**
     * Fetch lineup information for a specific event.
     *
     * @param int $event_id External event ID.
     *
     * @return array|\WP_Error
     */
    public function event_lineup( $event_id ) {
        return $this->get( '/lookuplineup.php', [ 'id' => $event_id ] );
    }

}

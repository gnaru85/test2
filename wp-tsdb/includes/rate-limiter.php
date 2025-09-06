<?php
namespace TSDB;

/**
 * Simple token bucket rate limiter.
 *
 * Persists token count and next available timestamp so that consumers can
 * determine when they may resume API requests.
 */
class Rate_Limiter {
    protected $limit_per_min = 90; // tokens per minute
    protected $option_key    = 'tsdb_rate_limit';

    /**
     * Attempt to consume a single token.
     *
     * @return bool Whether a token was consumed.
     */
    public function allow() {
        $now  = time();
        $data = get_option(
            $this->option_key,
            [ 'tokens' => $this->limit_per_min, 'next' => $now + MINUTE_IN_SECONDS ]
        );

        if ( $now >= (int) $data['next'] ) {
            // Bucket has refreshed.
            $data['tokens'] = $this->limit_per_min;
            $data['next']   = $now + MINUTE_IN_SECONDS;
        }

        if ( $data['tokens'] <= 0 ) {
            update_option( $this->option_key, $data );
            return false;
        }

        $data['tokens']--;
        update_option( $this->option_key, $data );
        return true;
    }

    /**
     * Retrieve the timestamp when a new token bucket will become available.
     *
     * @return int Unix timestamp.
     */
    public function next_available() {
        $data = get_option(
            $this->option_key,
            [ 'tokens' => $this->limit_per_min, 'next' => time() ]
        );
        return (int) $data['next'];
    }
}

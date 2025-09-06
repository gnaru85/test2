<?php
namespace TSDB;

/**
 * Simple token bucket rate limiter stored in transients.
 */
class Rate_Limiter {
    protected $limit_per_min = 90; // tokens per minute
    protected $bucket_key    = 'tsdb_tokens';

    public function allow() {
        $tokens = get_transient( $this->bucket_key );
        $tokens = false === $tokens ? $this->limit_per_min : (int) $tokens;
        if ( $tokens <= 0 ) {
            return false;
        }
        set_transient( $this->bucket_key, $tokens - 1, MINUTE_IN_SECONDS );
        return true;
    }
}

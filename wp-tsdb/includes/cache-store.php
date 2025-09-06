<?php
namespace TSDB;

/**
 * Persistent cache with object cache fallback to DB table.
 */
class Cache_Store {
    protected $group = 'tsdb';
    protected $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'tsdb_cache';
    }

    /**
     * Retrieve a value from cache.
     *
     * @param string $key Cache key.
     * @return mixed|false
     */
    public function get( $key ) {
        $found = false;
        $value = wp_cache_get( $key, $this->group, false, $found );
        if ( $found ) {
            return $value;
        }
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT value, ttl, UNIX_TIMESTAMP(created_at) AS created FROM {$this->table} WHERE cache_key=%s", $key ) );
        if ( ! $row ) {
            return false;
        }
        if ( $row->ttl > 0 && ( $row->created + $row->ttl ) < time() ) {
            $this->delete( $key );
            return false;
        }
        $value = maybe_unserialize( $row->value );
        $ttl   = $row->ttl > 0 ? ( $row->created + $row->ttl ) - time() : 0;
        if ( $ttl > 0 ) {
            wp_cache_set( $key, $value, $this->group, $ttl );
        }
        return $value;
    }

    /**
     * Store a value in cache.
     *
     * @param string $key   Cache key.
     * @param mixed  $value Value to store.
     * @param int    $ttl   Time to live in seconds.
     */
    public function set( $key, $value, $ttl = 0 ) {
        wp_cache_set( $key, $value, $this->group, $ttl );
        global $wpdb;
        $wpdb->replace(
            $this->table,
            [
                'cache_key'  => $key,
                'value'      => maybe_serialize( $value ),
                'ttl'        => (int) $ttl,
                'created_at' => current_time( 'mysql', 1 ),
            ],
            [ '%s', '%s', '%d', '%s' ]
        );
    }

    /**
     * Delete an entry from cache.
     *
     * @param string $key Cache key.
     */
    public function delete( $key ) {
        wp_cache_delete( $key, $this->group );
        global $wpdb;
        $wpdb->delete( $this->table, [ 'cache_key' => $key ], [ '%s' ] );
    }

    /**
     * Flush all cache entries.
     */
    public function flush() {
        wp_cache_flush();
        global $wpdb;
        $wpdb->query( "TRUNCATE TABLE {$this->table}" );
    }
}

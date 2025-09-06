<?php
namespace TSDB;

/**
 * Basic logger writing into custom table with structured levels.
 */
class Logger {

    /** Log level constants */
    const LEVEL_DEBUG   = 'debug';
    const LEVEL_INFO    = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR   = 'error';

    /**
     * Write a log entry.
     *
     * @param string $level   Log level.
     * @param string $source  Source identifier.
     * @param string $message Message string.
     * @param array  $context Optional structured context.
     */
    public function log( $level, $source, $message, $context = [] ) {
        global $wpdb;
        $table = $wpdb->prefix . 'tsdb_logs';
        $wpdb->insert(
            $table,
            [
                'level'        => $level,
                'source'       => $source,
                'message'      => $message,
                'context_json' => wp_json_encode( $context ),
            ],
            [ '%s', '%s', '%s', '%s' ]
        );
    }

    public function debug( $source, $message, $context = [] ) {
        $this->log( self::LEVEL_DEBUG, $source, $message, $context );
    }

    public function info( $source, $message, $context = [] ) {
        $this->log( self::LEVEL_INFO, $source, $message, $context );
    }

    public function warning( $source, $message, $context = [] ) {
        $this->log( self::LEVEL_WARNING, $source, $message, $context );
    }

    public function error( $source, $message, $context = [] ) {
        $this->log( self::LEVEL_ERROR, $source, $message, $context );
    }

    /**
     * Retrieve recent logs.
     *
     * @param string|null $level Optional level filter.
     * @param int         $limit Number of rows to return.
     *
     * @return array[]
     */
    public function get_logs( $level = null, $limit = 100 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'tsdb_logs';
        $sql   = "SELECT ts, level, source, message, context_json FROM {$table}";
        $args  = [];
        if ( $level ) {
            $sql  .= ' WHERE level = %s';
            $args[] = $level;
        }
        $sql   .= ' ORDER BY ts DESC LIMIT %d';
        $args[] = $limit;
        $query  = $args ? $wpdb->prepare( $sql, ...$args ) : $sql;
        return $wpdb->get_results( $query, ARRAY_A );
    }
}

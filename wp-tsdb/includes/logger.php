<?php
namespace TSDB;

/**
 * Basic logger writing into custom table.
 */
class Logger {
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

    public function error( $source, $message, $context = [] ) {
        $this->log( 'error', $source, $message, $context );
    }

    public function info( $source, $message, $context = [] ) {
        $this->log( 'info', $source, $message, $context );
    }
}

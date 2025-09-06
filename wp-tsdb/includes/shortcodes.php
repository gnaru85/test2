<?php
/**
 * Shortcode handlers for TSDB blocks.
 */
namespace TSDB;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register plugin shortcodes.
 */
function register_shortcodes() {
    add_shortcode( 'tsdb_live_fixtures', __NAMESPACE__ . '\\shortcode_live_fixtures' );
    add_shortcode( 'tsdb_live_event', __NAMESPACE__ . '\\shortcode_live_event' );
    add_shortcode( 'tsdb_live_standings', __NAMESPACE__ . '\\shortcode_live_standings' );
}
add_action( 'init', __NAMESPACE__ . '\\register_shortcodes' );

/**
 * Render live fixtures via shortcode.
 *
 * Usage: [tsdb_live_fixtures league="123" status="live"]
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function shortcode_live_fixtures( $atts ) {
    $atts = shortcode_atts(
        [
            'league' => 0,
            'status' => 'live',
        ],
        $atts,
        'tsdb_live_fixtures'
    );
    wp_enqueue_script( 'tsdb-live-fixtures' );
    return '<div class="tsdb-live-fixtures" data-tsdb="' . esc_attr( wp_json_encode( $atts ) ) . '"></div>';
}

/**
 * Render live event via shortcode.
 *
 * Usage: [tsdb_live_event event="123" status="live"]
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function shortcode_live_event( $atts ) {
    $atts = shortcode_atts(
        [
            'event'  => 0,
            'status' => 'live',
        ],
        $atts,
        'tsdb_live_event'
    );
    wp_enqueue_script( 'tsdb-live-event' );
    return '<div class="tsdb-live-event" data-tsdb="' . esc_attr( wp_json_encode( $atts ) ) . '"></div>';
}

/**
 * Render league standings via shortcode.
 *
 * Usage: [tsdb_live_standings league="123" season="2023" status="live"]
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function shortcode_live_standings( $atts ) {
    $atts = shortcode_atts(
        [
            'league' => 0,
            'season' => '',
            'status' => 'live',
        ],
        $atts,
        'tsdb_live_standings'
    );
    wp_enqueue_script( 'tsdb-live-standings' );
    return '<div class="tsdb-live-standings" data-tsdb="' . esc_attr( wp_json_encode( $atts ) ) . '"></div>';
}

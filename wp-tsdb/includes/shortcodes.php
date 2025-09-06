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
    add_shortcode( 'tsdb_team', __NAMESPACE__ . '\\shortcode_team' );
    add_shortcode( 'tsdb_player', __NAMESPACE__ . '\\shortcode_player' );
    add_shortcode( 'tsdb_league_table', __NAMESPACE__ . '\\shortcode_league_table' );
    add_shortcode( 'tsdb_h2h', __NAMESPACE__ . '\\shortcode_h2h' );
    add_shortcode( 'tsdb_tv_schedule', __NAMESPACE__ . '\\shortcode_tv_schedule' );
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

/**
 * Render team info via shortcode.
 *
 * Usage: [tsdb_team team="123" status="live"]
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function shortcode_team( $atts ) {
    $atts = shortcode_atts(
        [
            'team'   => 0,
            'status' => 'live',
        ],
        $atts,
        'tsdb_team'
    );
    wp_enqueue_script( 'tsdb-team' );
    return '<div class="tsdb-team" data-tsdb="' . esc_attr( wp_json_encode( $atts ) ) . '"></div>';
}

/**
 * Render player info via shortcode.
 *
 * Usage: [tsdb_player player="123" status="live"]
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function shortcode_player( $atts ) {
    $atts = shortcode_atts(
        [
            'player' => 0,
            'status' => 'live',
        ],
        $atts,
        'tsdb_player'
    );
    wp_enqueue_script( 'tsdb-player' );
    return '<div class="tsdb-player" data-tsdb="' . esc_attr( wp_json_encode( $atts ) ) . '"></div>';
}

/**
 * Render league table via shortcode.
 *
 * Usage: [tsdb_league_table league="123" season="2023" status="live"]
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function shortcode_league_table( $atts ) {
    $atts = shortcode_atts(
        [
            'league' => 0,
            'season' => '',
            'status' => 'live',
        ],
        $atts,
        'tsdb_league_table'
    );
    wp_enqueue_script( 'tsdb-league-table' );
    return '<div class="tsdb-league-table" data-tsdb="' . esc_attr( wp_json_encode( $atts ) ) . '"></div>';
}

/**
 * Render head-to-head via shortcode.
 *
 * Usage: [tsdb_h2h team1="1" team2="2" status="live"]
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function shortcode_h2h( $atts ) {
    $atts = shortcode_atts(
        [
            'team1'  => 0,
            'team2'  => 0,
            'status' => 'live',
        ],
        $atts,
        'tsdb_h2h'
    );
    wp_enqueue_script( 'tsdb-h2h' );
    return '<div class="tsdb-h2h" data-tsdb="' . esc_attr( wp_json_encode( $atts ) ) . '"></div>';
}

/**
 * Render TV schedule via shortcode.
 *
 * Usage: [tsdb_tv_schedule country="US" status="live"]
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function shortcode_tv_schedule( $atts ) {
    $atts = shortcode_atts(
        [
            'country' => '',
            'status'  => 'live',
        ],
        $atts,
        'tsdb_tv_schedule'
    );
    wp_enqueue_script( 'tsdb-tv-schedule' );
    return '<div class="tsdb-tv-schedule" data-tsdb="' . esc_attr( wp_json_encode( $atts ) ) . '"></div>';
}

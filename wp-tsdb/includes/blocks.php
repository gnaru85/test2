<?php
/**
 * Block registrations for TSDB plugin.
 */
namespace TSDB;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register custom blocks and their scripts.
 */
function register_blocks() {
    wp_register_script(
        'tsdb-live-fixtures',
        TSDB_URL . 'blocks/live-fixtures.js',
        [],
        TSDB_VERSION,
        true
    );

    wp_register_script(
        'tsdb-live-event',
        TSDB_URL . 'blocks/live-event.js',
        [],
        TSDB_VERSION,
        true
    );

    wp_register_script(
        'tsdb-live-standings',
        TSDB_URL . 'blocks/live-standings.js',
        [],
        TSDB_VERSION,
        true
    );

    wp_register_script(
        'tsdb-team',
        TSDB_URL . 'blocks/team.js',
        [],
        TSDB_VERSION,
        true
    );

    wp_register_script(
        'tsdb-player',
        TSDB_URL . 'blocks/player.js',
        [],
        TSDB_VERSION,
        true
    );

    wp_register_script(
        'tsdb-league-table',
        TSDB_URL . 'blocks/league-table.js',
        [],
        TSDB_VERSION,
        true
    );

    wp_register_script(
        'tsdb-h2h',
        TSDB_URL . 'blocks/h2h.js',
        [],
        TSDB_VERSION,
        true
    );

    wp_register_script(
        'tsdb-tv-schedule',
        TSDB_URL . 'blocks/tv-schedule.js',
        [],
        TSDB_VERSION,
        true
    );

    if ( function_exists( 'register_block_type' ) ) {
        register_block_type( 'tsdb/live-fixtures', [
            'editor_script' => 'tsdb-live-fixtures',
            'script'        => 'tsdb-live-fixtures',
            'render_callback' => __NAMESPACE__ . '\\render_live_fixtures_block',
        ] );

        register_block_type( 'tsdb/live-event', [
            'editor_script' => 'tsdb-live-event',
            'script'        => 'tsdb-live-event',
            'render_callback' => __NAMESPACE__ . '\\render_live_event_block',
        ] );

        register_block_type( 'tsdb/live-standings', [
            'editor_script' => 'tsdb-live-standings',
            'script'        => 'tsdb-live-standings',
            'render_callback' => __NAMESPACE__ . '\\render_live_standings_block',
        ] );

        register_block_type( 'tsdb/team', [
            'editor_script' => 'tsdb-team',
            'script'        => 'tsdb-team',
            'render_callback' => __NAMESPACE__ . '\\render_team_block',
        ] );

        register_block_type( 'tsdb/player', [
            'editor_script' => 'tsdb-player',
            'script'        => 'tsdb-player',
            'render_callback' => __NAMESPACE__ . '\\render_player_block',
        ] );

        register_block_type( 'tsdb/league-table', [
            'editor_script' => 'tsdb-league-table',
            'script'        => 'tsdb-league-table',
            'render_callback' => __NAMESPACE__ . '\\render_league_table_block',
        ] );

        register_block_type( 'tsdb/h2h', [
            'editor_script' => 'tsdb-h2h',
            'script'        => 'tsdb-h2h',
            'render_callback' => __NAMESPACE__ . '\\render_h2h_block',
        ] );

        register_block_type( 'tsdb/tv-schedule', [
            'editor_script' => 'tsdb-tv-schedule',
            'script'        => 'tsdb-tv-schedule',
            'render_callback' => __NAMESPACE__ . '\\render_tv_schedule_block',
        ] );
    }
}
add_action( 'init', __NAMESPACE__ . '\\register_blocks' );

/**
 * Server-side render callback for live fixtures block.
 *
 * @param array $attributes Block attributes.
 * @return string HTML markup for block container.
 */
function render_live_fixtures_block( $attributes = [] ) {
    $league = isset( $attributes['league'] ) ? intval( $attributes['league'] ) : 0;
    $status = isset( $attributes['status'] ) ? sanitize_text_field( $attributes['status'] ) : 'live';
    $data   = [
        'league' => $league,
        'status' => $status,
    ];
    return '<div class="tsdb-live-fixtures" data-tsdb="' . esc_attr( wp_json_encode( $data ) ) . '"></div>';
}

/**
 * Server-side render callback for live event block.
 *
 * @param array $attributes Block attributes.
 * @return string HTML markup for block container.
 */
function render_live_event_block( $attributes = [] ) {
    $event  = isset( $attributes['event'] ) ? intval( $attributes['event'] ) : 0;
    $status = isset( $attributes['status'] ) ? sanitize_text_field( $attributes['status'] ) : 'live';
    $data   = [
        'event'  => $event,
        'status' => $status,
    ];
    return '<div class="tsdb-live-event" data-tsdb="' . esc_attr( wp_json_encode( $data ) ) . '"></div>';
}

/**
 * Server-side render callback for live standings block.
 *
 * @param array $attributes Block attributes.
 * @return string HTML markup for block container.
 */
function render_live_standings_block( $attributes = [] ) {
    $league = isset( $attributes['league'] ) ? intval( $attributes['league'] ) : 0;
    $season = isset( $attributes['season'] ) ? sanitize_text_field( $attributes['season'] ) : '';
    $status = isset( $attributes['status'] ) ? sanitize_text_field( $attributes['status'] ) : 'live';
    $data   = [
        'league' => $league,
        'season' => $season,
        'status' => $status,
    ];
    return '<div class="tsdb-live-standings" data-tsdb="' . esc_attr( wp_json_encode( $data ) ) . '"></div>';
}

/**
 * Server-side render callback for team block.
 *
 * @param array $attributes Block attributes.
 * @return string HTML markup for block container.
 */
function render_team_block( $attributes = [] ) {
    $team   = isset( $attributes['team'] ) ? intval( $attributes['team'] ) : 0;
    $status = isset( $attributes['status'] ) ? sanitize_text_field( $attributes['status'] ) : 'live';
    $data   = [
        'team'   => $team,
        'status' => $status,
    ];
    return '<div class="tsdb-team" data-tsdb="' . esc_attr( wp_json_encode( $data ) ) . '"></div>';
}

/**
 * Server-side render callback for player block.
 *
 * @param array $attributes Block attributes.
 * @return string HTML markup for block container.
 */
function render_player_block( $attributes = [] ) {
    $player = isset( $attributes['player'] ) ? intval( $attributes['player'] ) : 0;
    $status = isset( $attributes['status'] ) ? sanitize_text_field( $attributes['status'] ) : 'live';
    $data   = [
        'player' => $player,
        'status' => $status,
    ];
    return '<div class="tsdb-player" data-tsdb="' . esc_attr( wp_json_encode( $data ) ) . '"></div>';
}

/**
 * Server-side render callback for league table block.
 *
 * @param array $attributes Block attributes.
 * @return string HTML markup for block container.
 */
function render_league_table_block( $attributes = [] ) {
    $league = isset( $attributes['league'] ) ? intval( $attributes['league'] ) : 0;
    $season = isset( $attributes['season'] ) ? sanitize_text_field( $attributes['season'] ) : '';
    $status = isset( $attributes['status'] ) ? sanitize_text_field( $attributes['status'] ) : 'live';
    $data   = [
        'league' => $league,
        'season' => $season,
        'status' => $status,
    ];
    return '<div class="tsdb-league-table" data-tsdb="' . esc_attr( wp_json_encode( $data ) ) . '"></div>';
}

/**
 * Server-side render callback for head-to-head block.
 *
 * @param array $attributes Block attributes.
 * @return string HTML markup for block container.
 */
function render_h2h_block( $attributes = [] ) {
    $team1  = isset( $attributes['team1'] ) ? intval( $attributes['team1'] ) : 0;
    $team2  = isset( $attributes['team2'] ) ? intval( $attributes['team2'] ) : 0;
    $status = isset( $attributes['status'] ) ? sanitize_text_field( $attributes['status'] ) : 'live';
    $data   = [
        'team1'  => $team1,
        'team2'  => $team2,
        'status' => $status,
    ];
    return '<div class="tsdb-h2h" data-tsdb="' . esc_attr( wp_json_encode( $data ) ) . '"></div>';
}

/**
 * Server-side render callback for TV schedule block.
 *
 * @param array $attributes Block attributes.
 * @return string HTML markup for block container.
 */
function render_tv_schedule_block( $attributes = [] ) {
    $country = isset( $attributes['country'] ) ? sanitize_text_field( $attributes['country'] ) : '';
    $status  = isset( $attributes['status'] ) ? sanitize_text_field( $attributes['status'] ) : 'live';
    $data    = [
        'country' => $country,
        'status'  => $status,
    ];
    return '<div class="tsdb-tv-schedule" data-tsdb="' . esc_attr( wp_json_encode( $data ) ) . '"></div>';
}

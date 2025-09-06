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

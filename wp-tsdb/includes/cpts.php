<?php
namespace TSDB;

/**
 * Register custom post types for leagues, teams and events.
 */
function register_cpts() {
    $supports = [ 'title', 'editor', 'thumbnail', 'excerpt' ];

    register_post_type( 'tsdb_league', [
        'label'       => __( 'Leagues', 'tsdb' ),
        'public'      => true,
        'has_archive' => true,
        'rewrite'     => [ 'slug' => 'league' ],
        'show_in_rest'=> true,
        'supports'    => $supports,
    ] );

    register_post_type( 'tsdb_team', [
        'label'       => __( 'Teams', 'tsdb' ),
        'public'      => true,
        'has_archive' => true,
        'rewrite'     => [ 'slug' => 'team' ],
        'show_in_rest'=> true,
        'supports'    => $supports,
    ] );

    register_post_type( 'tsdb_event', [
        'label'       => __( 'Events', 'tsdb' ),
        'public'      => true,
        'has_archive' => true,
        'rewrite'     => [ 'slug' => 'event' ],
        'show_in_rest'=> true,
        'supports'    => $supports,
    ] );
}
add_action( 'init', __NAMESPACE__ . '\\register_cpts' );

/**
 * Locate template files allowing theme overrides.
 *
 * @param string $file Template filename.
 * @return string Full path to template.
 */
function locate_template( $file ) {
    $template = \locate_template( [ $file ] );
    if ( ! $template ) {
        $template = TSDB_PATH . 'templates/' . $file;
    }
    return $template;
}

/**
 * Template loader to allow plugin templates to be overridden by theme.
 *
 * @param string $template Default template.
 * @return string
 */
function template_loader( $template ) {
    if ( is_singular( 'tsdb_league' ) ) {
        return locate_template( 'single-tsdb_league.php' );
    }
    if ( is_singular( 'tsdb_team' ) ) {
        return locate_template( 'single-tsdb_team.php' );
    }
    if ( is_singular( 'tsdb_event' ) ) {
        return locate_template( 'single-tsdb_event.php' );
    }
    return $template;
}
add_filter( 'template_include', __NAMESPACE__ . '\\template_loader' );

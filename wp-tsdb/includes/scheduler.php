<?php
namespace TSDB;

/**
 * Wrapper around Action Scheduler with WP-Cron fallback.
 */
class Scheduler {
    /**
     * Schedule a recurring action.
     *
     * @param string $hook     Hook name.
     * @param int    $interval Interval in seconds.
     * @param array  $args     Optional arguments.
     */
    public function schedule_recurring( $hook, $interval, $args = [] ) {
        if ( function_exists( 'as_schedule_recurring_action' ) ) {
            if ( ! as_next_scheduled_action( $hook, $args ) ) {
                as_schedule_recurring_action( time(), $interval, $hook, $args );
            }
        } else {
            $schedule = 'tsdb_' . $interval;
            add_filter( 'cron_schedules', function ( $schedules ) use ( $schedule, $interval ) {
                if ( ! isset( $schedules[ $schedule ] ) ) {
                    $schedules[ $schedule ] = [ 'interval' => $interval, 'display' => 'TSDB ' . $interval ];
                }
                return $schedules;
            } );
            if ( ! wp_next_scheduled( $hook, $args ) ) {
                wp_schedule_event( time(), $schedule, $hook, $args );
            }
        }
    }

    /**
     * Schedule a single action.
     *
     * @param int    $timestamp Unix timestamp.
     * @param string $hook      Hook name.
     * @param array  $args      Optional arguments.
     */
    public function schedule_single( $timestamp, $hook, $args = [] ) {
        if ( function_exists( 'as_schedule_single_action' ) ) {
            as_schedule_single_action( $timestamp, $hook, $args );
        } else {
            wp_schedule_single_event( $timestamp, $hook, $args );
        }
    }

    /**
     * Retrieve next scheduled timestamp for a hook.
     *
     * @param string $hook Hook name.
     * @param array  $args Optional arguments.
     * @return int|false
     */
    public function next_scheduled( $hook, $args = [] ) {
        if ( function_exists( 'as_next_scheduled_action' ) ) {
            return as_next_scheduled_action( $hook, $args );
        }
        return wp_next_scheduled( $hook, $args );
    }

    /**
     * Unschedule a hook.
     *
     * @param string $hook Hook name.
     * @param array  $args Optional arguments.
     */
    public function unschedule( $hook, $args = [] ) {
        if ( function_exists( 'as_unschedule_action' ) ) {
            as_unschedule_action( $hook, $args );
        } else {
            wp_clear_scheduled_hook( $hook, $args );
        }
    }
}

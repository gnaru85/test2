<?php
namespace TSDB;

/**
 * Helper for importing remote images into WordPress media library.
 */
class Media_Importer {
    /**
     * Download an image and create a WP attachment.
     * Deduplicates by stored hash meta and cleans up replaced attachments.
     *
     * @param string $url Remote image URL.
     * @param int    $existing_id Existing attachment ID to replace.
     * @return int|null Attachment ID or null on failure.
     */
    public function import( $url, $existing_id = 0 ) {
        if ( empty( $url ) ) {
            if ( $existing_id ) {
                wp_delete_attachment( $existing_id, true );
            }
            return null;
        }
        $tmp = download_url( $url );
        if ( is_wp_error( $tmp ) ) {
            return $existing_id ?: null;
        }
        $hash    = md5_file( $tmp );
        $current = $this->find_by_hash( $hash );
        if ( $current ) {
            @unlink( $tmp );
            if ( $existing_id && $existing_id != $current ) {
                wp_delete_attachment( $existing_id, true );
            }
            return $current;
        }
        $file = [
            'name'     => basename( parse_url( $url, PHP_URL_PATH ) ),
            'tmp_name' => $tmp,
        ];
        $id = media_handle_sideload( $file, 0 );
        if ( is_wp_error( $id ) ) {
            @unlink( $tmp );
            return $existing_id ?: null;
        }
        update_post_meta( $id, '_tsdb_hash', $hash );
        $meta = wp_generate_attachment_metadata( $id, get_attached_file( $id ) );
        wp_update_attachment_metadata( $id, $meta );
        if ( $existing_id && $existing_id != $id ) {
            wp_delete_attachment( $existing_id, true );
        }
        return $id;
    }

    protected function find_by_hash( $hash ) {
        $posts = get_posts( [
            'post_type'  => 'attachment',
            'post_status'=> 'inherit',
            'numberposts'=> 1,
            'fields'     => 'ids',
            'meta_key'   => '_tsdb_hash',
            'meta_value' => $hash,
        ] );
        return $posts ? (int) $posts[0] : 0;
    }

    /**
     * Remove attachments imported by TSDB that are no longer referenced.
     */
    public function cleanup_orphans() {
        global $wpdb;
        $ids = array_merge(
            $wpdb->get_col( "SELECT logo_id FROM {$wpdb->prefix}tsdb_leagues WHERE logo_id IS NOT NULL" ),
            $wpdb->get_col( "SELECT badge_id FROM {$wpdb->prefix}tsdb_teams WHERE badge_id IS NOT NULL" ),
            $wpdb->get_col( "SELECT thumb_id FROM {$wpdb->prefix}tsdb_players WHERE thumb_id IS NOT NULL" ),
            $wpdb->get_col( "SELECT image_id FROM {$wpdb->prefix}tsdb_venues WHERE image_id IS NOT NULL" )
        );
        $ids = array_map( 'intval', $ids );
        $attachments = get_posts( [
            'post_type'  => 'attachment',
            'post_status'=> 'inherit',
            'numberposts'=> -1,
            'fields'     => 'ids',
            'meta_key'   => '_tsdb_hash',
        ] );
        foreach ( $attachments as $att_id ) {
            if ( ! in_array( $att_id, $ids, true ) ) {
                wp_delete_attachment( $att_id, true );
            }
        }
    }
}

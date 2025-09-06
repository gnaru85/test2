<?php
/**
 * Helper functions for API key encryption.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Encrypt a value using libsodium if available.
 *
 * @param string $value Plain text value.
 *
 * @return string Encrypted value or original string if encryption unavailable.
 */
function tsdb_encrypt( $value ) {
    if ( ! is_string( $value ) ) {
        $value = (string) $value;
    }
    if ( function_exists( 'sodium_crypto_secretbox' ) ) {
        $key   = substr( hash( 'sha256', wp_salt( 'auth' ), true ), 0, SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
        $nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
        $box   = sodium_crypto_secretbox( $value, $nonce, $key );
        return base64_encode( $nonce . $box );
    }
    return $value;
}

/**
 * Decrypt a value using libsodium if available.
 *
 * @param string $value Encrypted value.
 *
 * @return string Decrypted value or original string on failure.
 */
function tsdb_decrypt( $value ) {
    if ( function_exists( 'sodium_crypto_secretbox_open' ) ) {
        $decoded = base64_decode( $value, true );
        if ( $decoded && strlen( $decoded ) > SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
            $nonce = substr( $decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
            $box   = substr( $decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
            $key   = substr( hash( 'sha256', wp_salt( 'auth' ), true ), 0, SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
            $plain = sodium_crypto_secretbox_open( $box, $nonce, $key );
            if ( false !== $plain ) {
                return $plain;
            }
        }
    }
    return $value;
}

/**
 * Retrieve the decrypted API key.
 *
 * @return string API key or empty string.
 */
function tsdb_get_api_key() {
    $encrypted = get_option( 'tsdb_api_key', '' );
    if ( ! $encrypted ) {
        return '';
    }
    return tsdb_decrypt( $encrypted );
}

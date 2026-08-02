<?php
/**
 * Encrypted partner credentials for hotel booking distribution channels.
 */

if (!function_exists('itm_hotel_booking_distribution_encryption_key')) {
    function itm_hotel_booking_distribution_encryption_key() {
        return hash('sha256', (defined('DB_PASS') ? DB_PASS : 'itmanagement') . 'itm_hbd_secrets_v1', true);
    }
}

if (!function_exists('itm_hotel_booking_distribution_encrypt_secret')) {
    function itm_hotel_booking_distribution_encrypt_secret($plain) {
        $plain = (string) $plain;
        if ($plain === '') {
            return '';
        }
        if (!function_exists('itm_encrypt')) {
            return null;
        }
        return itm_encrypt($plain, itm_hotel_booking_distribution_encryption_key());
    }
}

if (!function_exists('itm_hotel_booking_distribution_decrypt_secret')) {
    function itm_hotel_booking_distribution_decrypt_secret($encrypted) {
        $encrypted = (string) $encrypted;
        if ($encrypted === '') {
            return '';
        }
        if (!function_exists('itm_decrypt')) {
            return '';
        }
        $decrypted = itm_decrypt($encrypted, itm_hotel_booking_distribution_encryption_key());
        return $decrypted === false ? '' : (string) $decrypted;
    }
}

if (!function_exists('itm_hotel_booking_distribution_generate_signing_secret')) {
    function itm_hotel_booking_distribution_generate_signing_secret() {
        try {
            return 'whsec_' . bin2hex(random_bytes(24));
        } catch (Exception $e) {
            return 'whsec_' . sha1(uniqid('whsec_', true));
        }
    }
}

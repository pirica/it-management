<?php
/**
 * TOTP (RFC 6238) helpers — PHPGangsta_GoogleAuthenticator-compatible implementation
 * plus encrypted storage helpers for employees.totp_secret.
 */

if (!class_exists('PHPGangsta_GoogleAuthenticator', false)) {
    /**
     * PHP Class for handling Google Authenticator 2-factor authentication.
     *
     * @author Michael Kliewe
     * @copyright 2012 Michael Kliewe
     * @license http://www.opensource.org/licenses/bsd-license.php BSD License
     *
     * @link http://www.phpgangsta.de/
     */
    class PHPGangsta_GoogleAuthenticator
    {
        protected $_codeLength = 6;

        /**
         * Create new secret.
         * 16 characters, randomly chosen from the allowed base32 characters.
         *
         * @param int $secretLength
         *
         * @return string
         */
        public function createSecret($secretLength = 16)
        {
            $validChars = $this->_getBase32LookupTable();

            if ($secretLength < 16 || $secretLength > 128) {
                throw new Exception('Bad secret length');
            }
            $secret = '';
            $rnd = false;
            if (function_exists('random_bytes')) {
                $rnd = random_bytes($secretLength);
            } elseif (function_exists('mcrypt_create_iv')) {
                $rnd = mcrypt_create_iv($secretLength, MCRYPT_DEV_URANDOM);
            } elseif (function_exists('openssl_random_pseudo_bytes')) {
                $rnd = openssl_random_pseudo_bytes($secretLength, $cryptoStrong);
                if (!$cryptoStrong) {
                    $rnd = false;
                }
            }
            if ($rnd !== false) {
                for ($i = 0; $i < $secretLength; ++$i) {
                    $secret .= $validChars[ord($rnd[$i]) & 31];
                }
            } else {
                throw new Exception('No source of secure random');
            }

            return $secret;
        }

        /**
         * Calculate the code, with given secret and point in time.
         *
         * @param string   $secret
         * @param int|null $timeSlice
         *
         * @return string
         */
        public function getCode($secret, $timeSlice = null)
        {
            if ($timeSlice === null) {
                $timeSlice = floor(time() / 30);
            }

            $secretkey = $this->_base32Decode($secret);

            $time = chr(0) . chr(0) . chr(0) . chr(0) . pack('N*', $timeSlice);
            $hm = hash_hmac('SHA1', $time, $secretkey, true);
            $offset = ord(substr($hm, -1)) & 0x0F;
            $hashpart = substr($hm, $offset, 4);

            $value = unpack('N', $hashpart);
            $value = $value[1];
            $value = $value & 0x7FFFFFFF;

            $modulo = pow(10, $this->_codeLength);

            return str_pad($value % $modulo, $this->_codeLength, '0', STR_PAD_LEFT);
        }

        /**
         * Get QR-Code URL for image.
         *
         * @param string $name
         * @param string $secret
         * @param string|null $title
         * @param array  $params
         *
         * @return string
         */
        public function getQRCodeGoogleUrl($name, $secret, $title = null, $params = array())
        {
            $width = !empty($params['width']) && (int) $params['width'] > 0 ? (int) $params['width'] : 200;
            $height = !empty($params['height']) && (int) $params['height'] > 0 ? (int) $params['height'] : 200;
            $level = !empty($params['level']) && array_search($params['level'], array('L', 'M', 'Q', 'H')) !== false ? $params['level'] : 'M';

            $urlencoded = urlencode('otpauth://totp/' . $name . '?secret=' . $secret . '');
            if (isset($title)) {
                $urlencoded .= urlencode('&issuer=' . urlencode($title));
            }

            return "https://api.qrserver.com/v1/create-qr-code/?data=$urlencoded&size=${width}x${height}&ecc=$level";
        }

        /**
         * Check if the code is correct.
         *
         * @param string   $secret
         * @param string   $code
         * @param int      $discrepancy
         * @param int|null $currentTimeSlice
         *
         * @return bool
         */
        public function verifyCode($secret, $code, $discrepancy = 1, $currentTimeSlice = null)
        {
            if ($currentTimeSlice === null) {
                $currentTimeSlice = floor(time() / 30);
            }

            $code = preg_replace('/\s+/', '', (string)$code);
            if (strlen($code) != 6) {
                return false;
            }

            for ($i = -$discrepancy; $i <= $discrepancy; ++$i) {
                $calculatedCode = $this->getCode($secret, $currentTimeSlice + $i);
                if ($this->timingSafeEquals($calculatedCode, $code)) {
                    return true;
                }
            }

            return false;
        }

        /**
         * @param int $length
         *
         * @return PHPGangsta_GoogleAuthenticator
         */
        public function setCodeLength($length)
        {
            $this->_codeLength = $length;

            return $this;
        }

        /**
         * @param string $secret
         *
         * @return bool|string
         */
        protected function _base32Decode($secret)
        {
            if (empty($secret)) {
                return '';
            }

            $base32chars = $this->_getBase32LookupTable();
            $base32charsFlipped = array_flip($base32chars);

            $paddingCharCount = substr_count($secret, $base32chars[32]);
            $allowedValues = array(6, 4, 3, 1, 0);
            if (!in_array($paddingCharCount, $allowedValues)) {
                return false;
            }
            for ($i = 0; $i < 4; ++$i) {
                if ($paddingCharCount == $allowedValues[$i] &&
                    substr($secret, -($allowedValues[$i])) != str_repeat($base32chars[32], $allowedValues[$i])) {
                    return false;
                }
            }
            $secret = str_replace('=', '', $secret);
            $secret = str_split($secret);
            $binaryString = '';
            for ($i = 0; $i < count($secret); $i = $i + 8) {
                $x = '';
                if (!in_array($secret[$i], $base32chars)) {
                    return false;
                }
                for ($j = 0; $j < 8; ++$j) {
                    $x .= str_pad(base_convert(@$base32charsFlipped[@$secret[$i + $j]], 10, 2), 5, '0', STR_PAD_LEFT);
                }
                $eightBits = str_split($x, 8);
                for ($z = 0; $z < count($eightBits); ++$z) {
                    $binaryString .= (($y = chr(base_convert($eightBits[$z], 2, 10))) || ord($y) == 48) ? $y : '';
                }
            }

            return $binaryString;
        }

        /**
         * @return array
         */
        protected function _getBase32LookupTable()
        {
            return array(
                'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H',
                'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P',
                'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X',
                'Y', 'Z', '2', '3', '4', '5', '6', '7',
                '=',
            );
        }

        /**
         * @param string $safeString
         * @param string $userString
         *
         * @return bool
         */
        private function timingSafeEquals($safeString, $userString)
        {
            if (function_exists('hash_equals')) {
                return hash_equals($safeString, $userString);
            }
            $safeLen = strlen($safeString);
            $userLen = strlen($userString);

            if ($userLen != $safeLen) {
                return false;
            }

            $result = 0;

            for ($i = 0; $i < $userLen; ++$i) {
                $result |= (ord($safeString[$i]) ^ ord($userString[$i]));
            }

            return $result === 0;
        }
    }
}

if (!function_exists('itm_totp_instance')) {
    function itm_totp_instance()
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new PHPGangsta_GoogleAuthenticator();
        }

        return $instance;
    }
}

if (!function_exists('itm_totp_encryption_key')) {
    /**
     * Why: TOTP secrets must be recoverable server-side without a user vault session.
     */
    function itm_totp_encryption_key()
    {
        return hash('sha256', (defined('DB_PASS') ? DB_PASS : 'itmanagement') . 'itm_totp_v1', true);
    }
}

if (!function_exists('itm_totp_encrypt_secret')) {
    function itm_totp_encrypt_secret($plainSecret)
    {
        if (!function_exists('itm_encrypt')) {
            return null;
        }
        $plain = (string)$plainSecret;
        if ($plain === '') {
            return '';
        }

        return itm_encrypt($plain, itm_totp_encryption_key());
    }
}

if (!function_exists('itm_totp_decrypt_secret')) {
    function itm_totp_decrypt_secret($encryptedSecret)
    {
        if (!function_exists('itm_decrypt')) {
            return '';
        }
        $encrypted = (string)$encryptedSecret;
        if ($encrypted === '') {
            return '';
        }
        $decrypted = itm_decrypt($encrypted, itm_totp_encryption_key());

        return is_string($decrypted) ? $decrypted : '';
    }
}

if (!function_exists('itm_totp_normalize_code')) {
    function itm_totp_normalize_code($code)
    {
        return preg_replace('/\D+/', '', (string)$code);
    }
}

if (!function_exists('itm_totp_employee_has_enabled')) {
    function itm_totp_employee_has_enabled(array $employeeRow)
    {
        return (int)($employeeRow['totp_enabled'] ?? 0) === 1
            && trim((string)($employeeRow['totp_secret'] ?? '')) !== '';
    }
}

if (!function_exists('itm_totp_plain_secret_from_employee_row')) {
    function itm_totp_plain_secret_from_employee_row(array $employeeRow)
    {
        if (!itm_totp_employee_has_enabled($employeeRow)) {
            return '';
        }

        return itm_totp_decrypt_secret((string)($employeeRow['totp_secret'] ?? ''));
    }
}

if (!function_exists('itm_totp_verify_plain_secret')) {
    function itm_totp_verify_plain_secret($plainSecret, $code, $discrepancy = 1)
    {
        $secret = trim((string)$plainSecret);
        if ($secret === '') {
            return false;
        }

        return itm_totp_instance()->verifyCode($secret, itm_totp_normalize_code($code), (int)$discrepancy);
    }
}

if (!function_exists('itm_totp_verify_employee_code')) {
    function itm_totp_verify_employee_code(array $employeeRow, $code, $discrepancy = 1)
    {
        if (!itm_totp_employee_has_enabled($employeeRow)) {
            return true;
        }

        $plain = itm_totp_plain_secret_from_employee_row($employeeRow);
        if ($plain === '') {
            return false;
        }

        return itm_totp_verify_plain_secret($plain, $code, $discrepancy);
    }
}

if (!function_exists('itm_totp_create_setup_secret')) {
    function itm_totp_create_setup_secret($length = 16)
    {
        return itm_totp_instance()->createSecret((int)$length);
    }
}

if (!function_exists('itm_totp_build_provisioning_uri')) {
    function itm_totp_build_provisioning_uri($accountLabel, $secret, $issuer = null)
    {
        $label = rawurlencode((string)$accountLabel);
        $secret = (string)$secret;
        $uri = 'otpauth://totp/' . $label . '?secret=' . $secret;
        if ($issuer !== null && trim((string)$issuer) !== '') {
            $uri .= '&issuer=' . rawurlencode((string)$issuer);
        }

        return $uri;
    }
}

if (!function_exists('itm_totp_build_qr_image_url')) {
    function itm_totp_build_qr_image_url($accountLabel, $secret, $issuer = null, array $params = array())
    {
        return itm_totp_instance()->getQRCodeGoogleUrl(
            (string)$accountLabel,
            (string)$secret,
            $issuer,
            $params
        );
    }
}

if (!function_exists('itm_totp_require_valid_code_or_error')) {
    /**
     * @return array{ok:bool,error:string}
     */
    function itm_totp_require_valid_code_or_error(array $employeeRow, $code)
    {
        if (!itm_totp_employee_has_enabled($employeeRow)) {
            return ['ok' => true, 'error' => ''];
        }

        $normalized = itm_totp_normalize_code($code);
        if ($normalized === '' || strlen($normalized) !== 6) {
            return ['ok' => false, 'error' => 'A valid 6-digit authenticator code is required.'];
        }

        if (!itm_totp_verify_employee_code($employeeRow, $normalized)) {
            return ['ok' => false, 'error' => 'Incorrect authenticator code.'];
        }

        return ['ok' => true, 'error' => ''];
    }
}

<?php
/**
 * SAML 2.0 service provider helpers (HTTP-Redirect login + HTTP-POST ACS).
 */

if (!function_exists('itm_saml_encryption_key')) {
    function itm_saml_encryption_key()
    {
        return hash('sha256', (defined('DB_PASS') ? DB_PASS : 'itmanagement') . 'itm_company_sso_saml_v1', true);
    }
}

if (!function_exists('itm_saml_default_config')) {
    /**
     * @return array<string, mixed>
     */
    function itm_saml_default_config()
    {
        $base = defined('BASE_URL') ? rtrim((string) BASE_URL, '/') . '/' : 'http://localhost/it-management/';

        return [
            'idp_entity_id' => '',
            'idp_sso_url' => '',
            'idp_x509_cert' => '',
            'sp_entity_id' => $base,
            'name_id_format' => 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress',
            'attribute_username' => 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/name',
            'attribute_email' => 'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress',
        ];
    }
}

if (!function_exists('itm_saml_normalize_config')) {
    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    function itm_saml_normalize_config(array $config)
    {
        $defaults = itm_saml_default_config();
        $merged = array_merge($defaults, $config);
        $merged['idp_entity_id'] = trim((string) ($merged['idp_entity_id'] ?? ''));
        $merged['idp_sso_url'] = trim((string) ($merged['idp_sso_url'] ?? ''));
        $merged['idp_x509_cert'] = trim((string) ($merged['idp_x509_cert'] ?? ''));
        $merged['sp_entity_id'] = trim((string) ($merged['sp_entity_id'] ?? ''));
        if ($merged['sp_entity_id'] === '') {
            $merged['sp_entity_id'] = (string) $defaults['sp_entity_id'];
        }
        $merged['name_id_format'] = trim((string) ($merged['name_id_format'] ?? ''));
        if ($merged['name_id_format'] === '') {
            $merged['name_id_format'] = (string) $defaults['name_id_format'];
        }
        $merged['attribute_username'] = trim((string) ($merged['attribute_username'] ?? ''));
        $merged['attribute_email'] = trim((string) ($merged['attribute_email'] ?? ''));

        return $merged;
    }
}

if (!function_exists('itm_saml_encrypt_config')) {
    /**
     * @param array<string, mixed> $config
     */
    function itm_saml_encrypt_config(array $config)
    {
        if (!function_exists('itm_encrypt')) {
            return null;
        }
        $normalized = itm_saml_normalize_config($config);
        $json = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return null;
        }

        return itm_encrypt($json, itm_saml_encryption_key());
    }
}

if (!function_exists('itm_saml_decrypt_config')) {
    /**
     * @return array<string, mixed>|null
     */
    function itm_saml_decrypt_config($encrypted)
    {
        $encrypted = trim((string) $encrypted);
        if ($encrypted === '' || !function_exists('itm_decrypt')) {
            return null;
        }
        $json = itm_decrypt($encrypted, itm_saml_encryption_key());
        if ($json === false || $json === '') {
            return null;
        }
        $decoded = json_decode($json, true);

        return is_array($decoded) ? itm_saml_normalize_config($decoded) : null;
    }
}

if (!function_exists('itm_saml_acs_url')) {
    function itm_saml_acs_url()
    {
        $base = defined('BASE_URL') ? rtrim((string) BASE_URL, '/') . '/' : 'http://localhost/it-management/';

        return $base . 'sso-saml-acs.php';
    }
}

if (!function_exists('itm_saml_generate_request_id')) {
    function itm_saml_generate_request_id()
    {
        return '_' . bin2hex(random_bytes(16));
    }
}

if (!function_exists('itm_saml_build_authn_request')) {
    /**
     * @param array<string, mixed> $config
     */
    function itm_saml_build_authn_request(array $config, $requestId = null)
    {
        $config = itm_saml_normalize_config($config);
        $requestId = $requestId !== null ? trim((string) $requestId) : itm_saml_generate_request_id();
        if ($requestId === '') {
            $requestId = itm_saml_generate_request_id();
        }
        $issueInstant = gmdate('Y-m-d\TH:i:s\Z');
        $destination = htmlspecialchars($config['idp_sso_url'], ENT_QUOTES, 'UTF-8');
        $acsUrl = htmlspecialchars(itm_saml_acs_url(), ENT_QUOTES, 'UTF-8');
        $issuer = htmlspecialchars($config['sp_entity_id'], ENT_QUOTES, 'UTF-8');
        $requestIdEsc = htmlspecialchars($requestId, ENT_QUOTES, 'UTF-8');

        return [
            'request_id' => $requestId,
            'xml' => '<samlp:AuthnRequest xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" '
                . 'xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" '
                . 'ID="' . $requestIdEsc . '" Version="2.0" IssueInstant="' . $issueInstant . '" '
                . 'Destination="' . $destination . '" AssertionConsumerServiceURL="' . $acsUrl . '" '
                . 'ProtocolBinding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST">'
                . '<saml:Issuer>' . $issuer . '</saml:Issuer>'
                . '</samlp:AuthnRequest>',
        ];
    }
}

if (!function_exists('itm_saml_redirect_login_url')) {
    /**
     * @param array<string, mixed> $config
     */
    function itm_saml_redirect_login_url(array $config, $requestId = null)
    {
        $config = itm_saml_normalize_config($config);
        if ($config['idp_sso_url'] === '') {
            return '';
        }
        $built = itm_saml_build_authn_request($config, $requestId);
        $deflated = gzdeflate($built['xml']);
        if ($deflated === false) {
            return '';
        }
        $encoded = base64_encode($deflated);
        $relayState = rawurlencode((string) ($_GET['relay'] ?? ''));
        $url = $config['idp_sso_url'];
        $separator = strpos($url, '?') === false ? '?' : '&';

        return $url . $separator . 'SAMLRequest=' . rawurlencode($encoded) . ($relayState !== '' ? '&RelayState=' . $relayState : '');
    }
}

if (!function_exists('itm_saml_xml_first_value')) {
    function itm_saml_xml_first_value(DOMXPath $xpath, $query, DOMNode $context = null)
    {
        $nodeList = $context instanceof DOMNode ? $xpath->query($query, $context) : $xpath->query($query);
        if (!$nodeList || $nodeList->length < 1) {
            return '';
        }
        $node = $nodeList->item(0);

        return trim((string) ($node instanceof DOMNode ? $node->textContent : ''));
    }
}

if (!function_exists('itm_saml_verify_xml_signature')) {
    function itm_saml_verify_xml_signature(DOMDocument $doc, $certPem)
    {
        $certPem = trim((string) $certPem);
        if ($certPem === '') {
            return false;
        }
        if (strpos($certPem, 'BEGIN CERTIFICATE') === false) {
            $certPem = "-----BEGIN CERTIFICATE-----\n" . chunk_split(preg_replace('/\s+/', '', $certPem), 64, "\n") . "-----END CERTIFICATE-----\n";
        }
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
        $signatureNodes = $xpath->query('//ds:Signature');
        if (!$signatureNodes || $signatureNodes->length < 1) {
            return false;
        }
        $signatureNode = $signatureNodes->item(0);
        if (!$signatureNode instanceof DOMElement) {
            return false;
        }
        $signedInfoNodes = $xpath->query('ds:SignedInfo', $signatureNode);
        $signatureValueNodes = $xpath->query('ds:SignatureValue', $signatureNode);
        if (!$signedInfoNodes || $signedInfoNodes->length < 1 || !$signatureValueNodes || $signatureValueNodes->length < 1) {
            return false;
        }
        $signedInfoNode = $signedInfoNodes->item(0);
        $signatureValue = trim((string) $signatureValueNodes->item(0)->textContent);
        if ($signatureValue === '' || !$signedInfoNode instanceof DOMNode) {
            return false;
        }
        $canonicalSignedInfo = $signedInfoNode->C14N(true, false);
        $signatureBinary = base64_decode(str_replace(["\r", "\n", ' '], '', $signatureValue), true);
        if ($signatureBinary === false) {
            return false;
        }
        $publicKey = openssl_pkey_get_public($certPem);
        if ($publicKey === false) {
            return false;
        }
        $verified = openssl_verify($canonicalSignedInfo, $signatureBinary, $publicKey, OPENSSL_ALGO_SHA256);
        if (PHP_VERSION_ID < 80000 && is_resource($publicKey)) {
            openssl_free_key($publicKey);
        }

        return $verified === 1;
    }
}

if (!function_exists('itm_saml_parse_response')) {
    /**
     * @param array<string, mixed> $config
     * @return array{ok:bool,error?:string,subject?:string,username?:string,email?:string,attributes?:array<string,string>}
     */
    function itm_saml_parse_response($samlResponseB64, array $config, $expectedRequestId = null)
    {
        $config = itm_saml_normalize_config($config);
        $raw = base64_decode((string) $samlResponseB64, true);
        if ($raw === false || $raw === '') {
            return ['ok' => false, 'error' => 'Invalid SAML response payload.'];
        }

        $previous = libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $loaded = $doc->loadXML($raw, LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            return ['ok' => false, 'error' => 'Could not parse SAML response XML.'];
        }

        if (!itm_saml_verify_xml_signature($doc, $config['idp_x509_cert'])) {
            return ['ok' => false, 'error' => 'SAML response signature verification failed.'];
        }

        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('samlp', 'urn:oasis:names:tc:SAML:2.0:protocol');
        $xpath->registerNamespace('saml', 'urn:oasis:names:tc:SAML:2.0:assertion');

        $statusCode = itm_saml_xml_first_value($xpath, '//samlp:Status/samlp:StatusCode/@StatusCode');
        if ($statusCode !== '' && stripos($statusCode, 'Success') === false) {
            return ['ok' => false, 'error' => 'SAML authentication was not successful.'];
        }

        if ($expectedRequestId !== null && trim((string) $expectedRequestId) !== '') {
            $inResponseTo = itm_saml_xml_first_value($xpath, '//samlp:Response/@InResponseTo');
            if ($inResponseTo !== '' && $inResponseTo !== (string) $expectedRequestId) {
                return ['ok' => false, 'error' => 'SAML response does not match the login request.'];
            }
        }

        $subject = itm_saml_xml_first_value($xpath, '//saml:Subject/saml:NameID');
        $attributes = [];
        $attrNodes = $xpath->query('//saml:Attribute');
        if ($attrNodes) {
            foreach ($attrNodes as $attrNode) {
                if (!$attrNode instanceof DOMElement) {
                    continue;
                }
                $attrName = trim((string) $attrNode->getAttribute('Name'));
                if ($attrName === '') {
                    continue;
                }
                $value = itm_saml_xml_first_value($xpath, 'saml:AttributeValue', $attrNode);
                if ($value !== '') {
                    $attributes[$attrName] = $value;
                }
            }
        }

        $username = '';
        $email = '';
        if ($config['attribute_username'] !== '' && isset($attributes[$config['attribute_username']])) {
            $username = (string) $attributes[$config['attribute_username']];
        }
        if ($config['attribute_email'] !== '' && isset($attributes[$config['attribute_email']])) {
            $email = (string) $attributes[$config['attribute_email']];
        }
        if ($email === '' && filter_var($subject, FILTER_VALIDATE_EMAIL)) {
            $email = $subject;
        }
        if ($username === '' && $email !== '') {
            $username = strstr($email, '@', true) ?: $email;
        }
        if ($username === '' && $subject !== '') {
            $username = $subject;
        }

        return [
            'ok' => true,
            'subject' => $subject,
            'username' => $username,
            'email' => $email,
            'attributes' => $attributes,
        ];
    }
}

if (!function_exists('itm_saml_auth_attempt')) {
    /**
     * @return array{ok:bool,employee?:array<string,mixed>,error?:string,saml_user?:array<string,mixed>}
     */
    function itm_saml_auth_attempt(mysqli $conn, int $companyId, $samlResponseB64, $expectedRequestId = null)
    {
        $companyId = (int) $companyId;
        if ($companyId <= 0) {
            return ['ok' => false, 'error' => 'Company is required for SSO login.'];
        }

        $company = itm_sso_fetch_company_row($conn, $companyId);
        if (!$company || (int) ($company['sso_enabled'] ?? 0) !== 1) {
            return ['ok' => false, 'error' => 'SSO is not enabled for this company.'];
        }
        if (strtolower(trim((string) ($company['sso_provider'] ?? 'ldap'))) !== 'saml') {
            return ['ok' => false, 'error' => 'SAML SSO is not configured for this company.'];
        }

        $config = itm_saml_decrypt_config($company['sso_config_json_encrypted'] ?? '');
        if (!is_array($config) || $config['idp_sso_url'] === '' || $config['idp_x509_cert'] === '') {
            return ['ok' => false, 'error' => 'SAML is not configured for this company.'];
        }

        $parsed = itm_saml_parse_response($samlResponseB64, $config, $expectedRequestId);
        if (empty($parsed['ok'])) {
            return ['ok' => false, 'error' => (string) ($parsed['error'] ?? 'SAML authentication failed.')];
        }

        $samlUser = [
            'sso_subject' => (string) ($parsed['subject'] ?? ''),
            'username' => (string) ($parsed['username'] ?? ''),
            'email' => (string) ($parsed['email'] ?? ''),
        ];
        if ($samlUser['sso_subject'] === '' && $samlUser['email'] !== '') {
            $samlUser['sso_subject'] = $samlUser['email'];
        }

        $employee = itm_ldap_match_or_provision_employee($conn, $companyId, $samlUser);
        if (!is_array($employee)) {
            return [
                'ok' => false,
                'error' => 'No matching employee account was found for this SAML user.',
                'saml_user' => $samlUser,
            ];
        }

        if (trim((string) ($employee['sso_subject'] ?? '')) === '' && $samlUser['sso_subject'] !== '') {
            $employeeId = (int) ($employee['id'] ?? 0);
            if ($employeeId > 0) {
                $subject = $samlUser['sso_subject'];
                $updateStmt = mysqli_prepare(
                    $conn,
                    'UPDATE employees SET sso_subject = ? WHERE id = ? AND company_id = ? LIMIT 1'
                );
                if ($updateStmt) {
                    mysqli_stmt_bind_param($updateStmt, 'sii', $subject, $employeeId, $companyId);
                    mysqli_stmt_execute($updateStmt);
                    mysqli_stmt_close($updateStmt);
                    $employee['sso_subject'] = $subject;
                }
            }
        }

        return ['ok' => true, 'employee' => $employee, 'saml_user' => $samlUser];
    }
}

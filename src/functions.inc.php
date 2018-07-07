<?php

/**
 * Sanitise data to only allow alphanumeric characters.
 * @param string $input
 * @param string $additionalCharacters
 * @return string
 */
function san(string $input, string $additionalCharacters = ''): string
{
    return preg_replace('/[^a-zA-Z0-9'.$additionalCharacters.']/', '', $input);
}

/**
 * @param string $ipAddress
 * @return string
 */
function sanIp($ipAddress): string
{
    return preg_replace('/[^a-fA-F0-9\\[\\]\\.\\:]/', '', $ipAddress);
}

/**
 * @param $hostAddress
 * @return string
 */
function sanHost(string $hostAddress): string
{
    return preg_replace('/[^a-zA-Z0-9\\.\\-\\:\\/]/', '', $hostAddress);
}

/**
 * Output an API error and exit.
 * @param mixed $data
 * @return void
 */
function apiErr($data): void
{
    global $_config;
    echo json_encode(["status" => "error", "data" => $data, "coin" => $_config['coin']]);
    exit;
}

/**
 * Output an API 'ok' response and exit.
 * @param mixed $data
 * @return void
 */
function apiEcho($data): void
{
    global $_config;
    echo json_encode(["status" => "ok", "data" => $data, "coin" => $_config['coin']]);
    exit;
}

/**
 * Log function, this only shows in the CLI.
 * @param string $data
 * @return void
 *
 * @todo Convert to Monolog
 * @link https://github.com/pxgamer/arionum/issues/3
 */
function _log(string $data): void
{
    $date = date("[Y-m-d H:i:s]");
    $trace = debug_backtrace();
    $loc = count($trace) - 1;
    $file = substr($trace[$loc]['file'], strrpos($trace[$loc]['file'], "/") + 1);

    $res = "$date ".$file.":".$trace[$loc]['line'];

    if (!empty($trace[$loc]['class'])) {
        $res .= "---".$trace[$loc]['class'];
    }

    if (!empty($trace[$loc]['function']) && $trace[$loc]['function'] != '_log') {
        $res .= '->'.$trace[$loc]['function'].'()';
    }

    $res .= " $data \n";
    if (php_sapi_name() === 'cli') {
        echo $res;
    }

    global $_config;
    if ($_config['enable_logging'] == true) {
        @file_put_contents($_config['log_file'], $res, FILE_APPEND);
    }
}

/**
 * Convert a PEM key to hexadecimal.
 * @param string $data
 * @return string
 */
function pemToHex(string $data): string
{
    $data = str_replace("-----BEGIN PUBLIC KEY-----", "", $data);
    $data = str_replace("-----END PUBLIC KEY-----", "", $data);
    $data = str_replace("-----BEGIN EC PRIVATE KEY-----", "", $data);
    $data = str_replace("-----END EC PRIVATE KEY-----", "", $data);
    $data = str_replace("\n", "", $data);

    $data = base64_decode($data);
    $data = bin2hex($data);

    return $data;
}

/**
 * Convert a hexadecimal key to PEM.
 * @param string $data
 * @param bool   $isPrivateKey
 * @return string
 */
function hexToPem(string $data, bool $isPrivateKey = false): string
{
    $data = hex2bin($data);
    $data = base64_encode($data);

    if ($isPrivateKey) {
        return "-----BEGIN EC PRIVATE KEY-----\n".$data."\n-----END EC PRIVATE KEY-----";
    }

    return "-----BEGIN PUBLIC KEY-----\n".$data."\n-----END PUBLIC KEY-----";
}


/**
 * Encode a string to Base58.
 * @param string $string
 * @return bool|string
 * @author Stephen Hill
 * @link https://github.com/stephen-hill/base58php
 */
function base58Encode(string $string)
{
    $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
    $base = strlen($alphabet);

    // Type validation
    if (is_string($string) === false) {
        return false;
    }

    // If the string is empty, then the encoded string is obviously empty
    if (strlen($string) === 0) {
        return '';
    }

    // Convert the byte array into an arbitrary-precision decimal.
    // Do this by performing a base256 to base10 conversion.
    $hex = unpack('H*', $string);
    $hex = reset($hex);
    $decimal = gmp_init($hex, 16);

    // This loop now performs base 10 to base 58 conversion
    // The remainder or modulo on each loop becomes a base 58 character
    $output = '';
    while (gmp_cmp($decimal, $base) >= 0) {
        list($decimal, $mod) = gmp_div_qr($decimal, $base);
        $output .= $alphabet[gmp_intval($mod)];
    }

    // If there's still a remainder, append it
    if (gmp_cmp($decimal, 0) > 0) {
        $output .= $alphabet[gmp_intval($decimal)];
    }

    // Reverse the encoded data
    $output = strrev($output);

    // Add leading zeros
    $bytes = str_split($string);
    foreach ($bytes as $byte) {
        if ($byte === "\x00") {
            $output = $alphabet[0].$output;
            continue;
        }
        break;
    }
    return (string)$output;
}

/**
 * Decode a Base58 string.
 * @param string $base58
 * @return bool|string
 * @author Stephen Hill
 * @link https://github.com/stephen-hill/base58php
 */
function base58Decode(string $base58)
{
    $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
    $base = strlen($alphabet);

    // Type Validation
    if (is_string($base58) === false) {
        return false;
    }

    // If the string is empty, then the decoded string is obviously empty
    if (strlen($base58) === 0) {
        return '';
    }
    $indexes = array_flip(str_split($alphabet));
    $chars = str_split($base58);

    // Check for invalid characters in the supplied base58 string
    foreach ($chars as $char) {
        if (isset($indexes[$char]) === false) {
            return false;
        }
    }

    // Convert from base58 to base10
    $decimal = gmp_init($indexes[$chars[0]], 10);
    for ($i = 1, $l = count($chars); $i < $l; $i++) {
        $decimal = gmp_mul($decimal, $base);
        $decimal = gmp_add($decimal, $indexes[$chars[$i]]);
    }

    // Convert from base10 to base256 (8-bit byte array)
    $output = '';
    while (gmp_cmp($decimal, 0) > 0) {
        list($decimal, $byte) = gmp_div_qr($decimal, 256);
        $output = pack('C', gmp_intval($byte)).$output;
    }

    // Now we need to add leading zeros
    foreach ($chars as $char) {
        if ($indexes[$char] === 0) {
            $output = "\x00".$output;
            continue;
        }
        break;
    }

    return $output;
}

/**
 * Convert a PEM key to the Base58 version used by Arionum.
 * @param string $data
 * @return string
 */
function pemToCoin(string $data): string
{
    $data = str_replace("-----BEGIN PUBLIC KEY-----", "", $data);
    $data = str_replace("-----END PUBLIC KEY-----", "", $data);
    $data = str_replace("-----BEGIN EC PRIVATE KEY-----", "", $data);
    $data = str_replace("-----END EC PRIVATE KEY-----", "", $data);
    $data = str_replace("\n", "", $data);
    $data = base64_decode($data);

    return base58Encode($data);
}

/**
 * Convert an Arionum Base58 key to PEM format.
 * @param string $data
 * @param bool   $isPrivateKey
 * @return string
 */
function coinToPem(string $data, $isPrivateKey = false)
{
    $data = base58Decode($data);
    $data = base64_encode($data);

    $dat = str_split($data, 64);
    $data = implode("\n", $dat);

    if ($isPrivateKey) {
        return "-----BEGIN EC PRIVATE KEY-----\n".$data."\n-----END EC PRIVATE KEY-----\n";
    }
    return "-----BEGIN PUBLIC KEY-----\n".$data."\n-----END PUBLIC KEY-----\n";
}

/**
 * Sign data with the private key.
 * @param string $data
 * @param string $key
 * @return bool|string
 */
function ecSign($data, string $key)
{
    // Transform the base58 key format to PEM
    $privateKey = coinToPem($key, true);

    $pKey = openssl_pkey_get_private($privateKey);

    $k = openssl_pkey_get_details($pKey);

    openssl_sign($data, $signature, $pKey, OPENSSL_ALGO_SHA256);

    // The signature will be base58 encoded
    return base58Encode($signature);
}

/**
 * Verify a signature with a public key.
 * @param string $data
 * @param string $signature
 * @param string $key
 * @return bool
 */
function ecVerify(string $data, string $signature, string $key)
{
    // Transform the Base58 key to PEM
    $publicKey = coinToPem($key);

    $signature = base58Decode($signature);

    $pKey = openssl_pkey_get_public($publicKey);

    $result = openssl_verify($data, $signature, $pKey, OPENSSL_ALGO_SHA256);

    if ($result === 1) {
        return true;
    }

    return false;
}

/**
 * Post data to a URL endpoint (usually a peer).
 * The data is an array that is JSON encoded and sent as a data parameter.
 * @param string $url
 * @param array  $data
 * @param int    $timeout
 * @param bool   $debug
 * @return bool
 */
function peerPost(string $url, array $data = [], int $timeout = 60, bool $debug = false): bool
{
    global $_config;
    if ($debug) {
        echo "\nPeer post: $url\n";
    }
    $postdata = http_build_query(
        [
            'data' => json_encode($data),
            "coin" => $_config['coin'],
        ]
    );

    $opts = [
        'http' =>
            [
                'timeout' => $timeout,
                'method'  => 'POST',
                'header'  => 'Content-type: application/x-www-form-urlencoded',
                'content' => $postdata,
            ],
    ];

    $context = stream_context_create($opts);

    $result = file_get_contents($url, false, $context);
    if ($debug) {
        echo "\nPeer response: $result\n";
    }
    $res = json_decode($result, true);

    // the function will return false if something goes wrong
    if ($res['status'] != "ok" || $res['coin'] != $_config['coin']) {
        return false;
    }
    return $res['data'];
}

/**
 * Convert hexadecimal data to Base58.
 * @param string $hex
 * @return string
 */
function hexToCoin(string $hex): string
{
    $data = hex2bin($hex);
    return base58Encode($data);
}

/**
 * Convert Base58 data to hexadecimal.
 * @param string $data
 * @return string
 */
function coinToHex(string $data): string
{
    $bin = base58Decode($data);
    return bin2hex($bin);
}

<?php

use StephenHill\Base58;

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
 * Convert a PEM key to the Base58 version used by Arionum.
 * @param string $data
 * @return string
 * @throws Exception
 */
function pemToCoin(string $data): string
{
    $data = str_replace("-----BEGIN PUBLIC KEY-----", "", $data);
    $data = str_replace("-----END PUBLIC KEY-----", "", $data);
    $data = str_replace("-----BEGIN EC PRIVATE KEY-----", "", $data);
    $data = str_replace("-----END EC PRIVATE KEY-----", "", $data);
    $data = str_replace("\n", "", $data);
    $data = base64_decode($data);

    return (new Base58())->encode($data);
}

/**
 * Convert an Arionum Base58 key to PEM format.
 * @param string $data
 * @param bool   $isPrivateKey
 * @return string
 * @throws Exception
 */
function coinToPem(string $data, $isPrivateKey = false)
{
    $data = (new Base58())->decode($data);
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
 * @throws Exception
 */
function ecSign($data, string $key)
{
    // Transform the base58 key format to PEM
    $privateKey = coinToPem($key, true);

    $pKey = openssl_pkey_get_private($privateKey);

    openssl_sign($data, $signature, $pKey, OPENSSL_ALGO_SHA256);

    // The signature will be base58 encoded
    return (new Base58())->encode($signature);
}

/**
 * Verify a signature with a public key.
 * @param string $data
 * @param string $signature
 * @param string $key
 * @return bool
 * @throws Exception
 */
function ecVerify(string $data, string $signature, string $key)
{
    // Transform the Base58 key to PEM
    $publicKey = coinToPem($key);

    $signature = (new Base58())->decode($signature);

    $pKey = openssl_pkey_get_public($publicKey);

    $result = openssl_verify($data, $signature, $pKey, OPENSSL_ALGO_SHA256);

    if ($result === 1) {
        return true;
    }

    return false;
}

/**
 * Convert hexadecimal data to Base58.
 * @param string $hexadecimalData
 * @return string
 * @throws Exception
 */
function hexToCoin(string $hexadecimalData): string
{
    $data = hex2bin($hexadecimalData);
    return (new Base58())->encode($data);
}

/**
 * Convert Base58 data to hexadecimal.
 * @param string $data
 * @return string
 * @throws Exception
 */
function coinToHex(string $data): string
{
    $bin = (new Base58())->decode($data);
    return bin2hex($bin);
}

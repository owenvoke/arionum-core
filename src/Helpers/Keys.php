<?php

namespace Arionum\Core\Helpers;

use StephenHill\Base58;

class Keys
{
    /**
     * Convert a PEM key to hexadecimal.
     * @param string $data
     * @return string
     */
    public static function pemToHex(string $data): string
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
    public static function hexToPem(string $data, bool $isPrivateKey = false): string
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
     * @throws \Exception
     */
    public static function pemToCoin(string $data): string
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
     * @throws \Exception
     */
    public static function coinToPem(string $data, $isPrivateKey = false)
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
     * @throws \Exception
     */
    public static function ecSign($data, string $key)
    {
        // Transform the base58 key format to PEM
        $privateKey = self::coinToPem($key, true);

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
     * @throws \Exception
     */
    public static function ecVerify(string $data, string $signature, string $key)
    {
        // Transform the Base58 key to PEM
        $publicKey = self::coinToPem($key);

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
     * @throws \Exception
     */
    public static function hexToCoin(string $hexadecimalData): string
    {
        $data = hex2bin($hexadecimalData);
        return (new Base58())->encode($data);
    }

    /**
     * Convert Base58 data to hexadecimal.
     * @param string $data
     * @return string
     * @throws \Exception
     */
    public static function coinToHex(string $data): string
    {
        $bin = (new Base58())->decode($data);
        return bin2hex($bin);
    }
}

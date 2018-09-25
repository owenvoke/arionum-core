<?php

namespace Arionum\Core\Helpers;

/**
 * Class Sanitise
 */
class Sanitise
{
    /**
     * Sanitise data to only allow alphanumeric characters.
     * @param string $input
     * @param string $additionalCharacters
     * @return string
     */
    public static function alphanumeric(string $input, string $additionalCharacters = ''): string
    {
        return preg_replace('/[^a-zA-Z0-9'.$additionalCharacters.']/', '', $input);
    }

    /**
     * @param string $ipAddress
     * @return string
     */
    public static function ip($ipAddress): string
    {
        return preg_replace('/[^a-fA-F0-9\\[\\]\\.\\:]/', '', $ipAddress);
    }

    /**
     * @param $hostAddress
     * @return string
     */
    public static function host(string $hostAddress): string
    {
        return preg_replace('/[^a-zA-Z0-9\\.\\-\\:\\/]/', '', $hostAddress);
    }
}

<?php

namespace Arionum\Core;

use Arionum\Core\Exceptions\ConfigPropertyNotFoundException;

/**
 * Class Config
 */
class Config
{
    /**
     * @var array
     */
    protected static $properties;

    /**
     * @param array $properties
     */
    public function setGlobal(array $properties = [])
    {
        static::$properties = $properties;
    }

    /**
     * @param string $key
     * @return mixed|null
     * @throws ConfigPropertyNotFoundException
     */
    public static function get(string $key)
    {
        if (key_exists($key, static::$properties)) {
            return static::$properties[$key];
        }

        throw new Exceptions\ConfigPropertyNotFoundException();
    }

    /**
     * @param string $key
     * @param mixed  $value
     * @return void
     */
    public static function set(string $key, $value)
    {
        static::$properties[$key] = $value;
    }
}

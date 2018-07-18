<?php

namespace Arionum\Arionum;

use Arionum\Arionum\Exceptions\ConfigPropertyNotFoundException;

/**
 * Class Config
 */
class Config
{
    /**
     * @var array
     */
    protected $properties;

    /**
     * Config constructor.
     * @param array $properties
     */
    public function __construct(array $properties = [])
    {
        $this->properties = $properties;
    }

    /**
     * @param string $key
     * @return mixed|null
     * @throws ConfigPropertyNotFoundException
     */
    public function get(string $key)
    {
        if (key_exists($key, $this->properties)) {
            return $this->properties[$key];
        }

        throw new Exceptions\ConfigPropertyNotFoundException();
    }

    /**
     * @param string $key
     * @param mixed  $value
     * @return $this
     */
    public function set(string $key, $value)
    {
        $this->properties[$key] = $value;

        return $this;
    }
}

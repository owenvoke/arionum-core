<?php

namespace Arionum\Arionum;

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
     */
    public function get(string $key)
    {
        return $this->$key ?? null;
    }

    /**
     * @param string $key
     * @param mixed  $value
     * @return $this
     */
    public function set(string $key, $value)
    {
        $this->$key = $value;

        return $this;
    }
}

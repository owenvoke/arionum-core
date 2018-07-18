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
     * @throws \Exception
     */
    public function get(string $key)
    {
        if (property_exists($this, $key)) {
            return $this->$key;
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
        $this->$key = $value;

        return $this;
    }
}

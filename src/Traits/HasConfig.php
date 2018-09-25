<?php

namespace Arionum\Core\Traits;

use Arionum\Core\Config;

/**
 * Trait HasConfig
 */
trait HasConfig
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * @param Config|null $config
     * @return $this
     */
    protected function setConfig(Config $config = null)
    {
        $this->config = $config;

        return $this;
    }
}

<?php

namespace Arionum\Arionum;

use Arionum\Arionum\Traits\HasConfig;
use Arionum\Arionum\Traits\HasDatabase;

/**
 * Class Model
 */
class Model
{
    use HasConfig, HasDatabase;

    /**
     * Model constructor.
     * @param Config $config
     * @param DB     $database
     */
    public function __construct(Config $config, DB $database)
    {
        $this->setConfig($config);
        $this->setDatabase($database);
    }
}

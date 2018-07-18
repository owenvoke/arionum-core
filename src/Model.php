<?php

namespace Arionum\Arionum;

/**
 * Class Model
 */
class Model
{
    /**
     * @var Config
     */
    protected $config;
    /**
     * @var DB
     */
    protected $database;

    /**
     * Model constructor.
     * @param Config $config
     * @param DB     $database
     */
    public function __construct(Config $config, DB $database)
    {
        $this->config = $config;
        $this->database = $database;
    }
}

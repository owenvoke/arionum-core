<?php

namespace Arionum\Arionum;

/**
 * Class Model
 */
class Model
{
    /**
     * @var DB
     */
    protected $database;

    /**
     * Model constructor.
     * @param DB $database
     */
    public function __construct(DB $database)
    {
        $this->database = $database;
    }
}

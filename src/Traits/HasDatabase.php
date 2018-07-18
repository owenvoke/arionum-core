<?php

namespace Arionum\Arionum\Traits;

use Arionum\Arionum\DB;

/**
 * Trait HasDatabase
 */
trait HasDatabase
{
    /**
     * @var DB
     */
    protected $database;

    /**
     * @param DB|null $database
     * @return HasDatabase
     */
    protected function setDatabase(DB $database = null)
    {
        $this->database = $database;

        return $this;
    }
}

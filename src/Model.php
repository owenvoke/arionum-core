<?php

namespace Arionum\Core;

use Arionum\Core\Traits\HasDatabase;

/**
 * Class Model
 */
class Model
{
    use HasDatabase;

    /**
     * Model constructor.
     * @param DB $database
     */
    public function __construct(DB $database)
    {
        $this->setDatabase($database);
    }
}

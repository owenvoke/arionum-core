<?php

namespace Arionum\Arionum;

use PDO;
use PDOException;

/**
 * Class DB
 *
 * A simple wrapper for PDO.
 */
class DB extends PDO
{
    /**
     * @var string
     */
    private $error;
    /**
     * @var string
     */
    private $sql;
    /**
     * @var array|string
     */
    private $bind;
    /**
     * @var int
     */
    private $debugger = 0;
    /**
     * @var string
     */
    public $working = 'yes';

    /**
     * DB constructor.
     * @param string $dsn
     * @param string $user
     * @param string $password
     * @param int    $debugLevel
     */
    public function __construct(string $dsn, string $user = '', string $password = '', int $debugLevel = 0)
    {
        $options = [
            PDO::ATTR_PERSISTENT       => true,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_ERRMODE          => PDO::ERRMODE_EXCEPTION,
        ];
        $this->debugger = $debugLevel;
        try {
            parent::__construct($dsn, $user, $password, $options);
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            die('Could not connect to the DB - '.$this->error);
        }
    }

    /**
     * Debug the database connection.
     */
    private function debug(): void
    {
        if (!$this->debugger) {
            return;
        }
        $error = ['Error' => $this->error];
        if (!empty($this->sql)) {
            $error['SQL Statement'] = $this->sql;
        }
        if (!empty($this->bind)) {
            $error['Bind Parameters'] = trim(print_r($this->bind, true));
        }

        $backtrace = debug_backtrace();
        if (!empty($backtrace)) {
            foreach ($backtrace as $info) {
                if ($info['file'] != __FILE__) {
                    $error['Backtrace'] = $info['file'].' at line '.$info['line'];
                }
            }
        }
        $msg = '';
        $msg .= "SQL Error\n".str_repeat('-', 50);
        foreach ($error as $key => $val) {
            $msg .= "\n\n$key:\n$val";
        }

        if ($this->debugger) {
            echo nl2br($msg);
        }
    }

    /**
     * @param array|string $bind
     * @param string       $sql
     * @return array
     */
    private function cleanup($bind, string $sql = ''): array
    {
        if (!is_array($bind)) {
            if (!empty($bind)) {
                $bind = [$bind];
            } else {
                $bind = [];
            }
        }

        foreach ($bind as $key => $val) {
            if (str_replace($key, '', $sql) == $sql) {
                unset($bind[$key]);
            }
        }
        return $bind;
    }

    /**
     * @param string       $sql
     * @param array|string $bind
     * @return bool|mixed
     */
    public function single(string $sql, $bind = '')
    {
        $this->sql = trim($sql);
        $this->bind = $this->cleanup($bind, $sql);
        $this->error = '';

        try {
            $pdoStatement = $this->prepare($this->sql);
            if ($pdoStatement->execute($this->bind) !== false) {
                return $pdoStatement->fetchColumn();
            }
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            $this->debug();
        }

        return false;
    }

    /**
     * Run a SQL statement.
     * @param string       $sql
     * @param array|string $bind
     * @return array|bool|int
     */
    public function run(string $sql, $bind = '')
    {
        $this->sql = trim($sql);
        $this->bind = $this->cleanup($bind, $sql);
        $this->error = '';

        try {
            $pdoStatement = $this->prepare($this->sql);
            if ($pdoStatement->execute($this->bind) !== false) {
                if (preg_match('/^('.implode('|', ['select', 'describe', 'pragma']).') /i', $this->sql)) {
                    return $pdoStatement->fetchAll(PDO::FETCH_ASSOC);
                } elseif (preg_match('/^('.implode('|', ['delete', 'insert', 'update']).') /i', $this->sql)) {
                    return $pdoStatement->rowCount();
                }
            }
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            $this->debug();
        }

        return false;
    }

    /**
     * Retrieve a row from the database.
     * @param string       $sql
     * @param array|string $bind
     * @return array|bool|int|mixed
     */
    public function row(string $sql, $bind = '')
    {
        $query = $this->run($sql, $bind);
        if (count($query) == 0) {
            return false;
        }
        if (count($query) > 1) {
            return $query;
        }
        if (count($query) === 1) {
            return $query[0];
        }

        return false;
    }
}

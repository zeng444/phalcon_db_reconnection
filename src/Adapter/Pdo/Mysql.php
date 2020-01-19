<?php

namespace Janfish\Phalcon\Db\Adapter\Pdo;

use Phalcon\Db\Adapter\Pdo\Mysql as PdoMysql;

/**
 * Author:Robert
 *
 * Class Mysql
 * @package Core\Db\Adapter\Pdo
 */
class Mysql extends PdoMysql
{

    /**
     * Author:Robert
     *
     * @var int
     */
    private $_reconnectTime = 0;

    /**
     * MySQL server has gone away
     * https://dev.mysql.com/doc/refman/5.7/en/client-error-reference.html#error_cr_server_gone_error
     */
    const  CR_SERVER_GONE_ERROR = 2006;

    /**
     *  Lost connection to MySQL server during query
     * https://dev.mysql.com/doc/refman/5.7/en/client-error-reference.html#error_cr_server_lost
     */
    const  CR_SERVER_LOST = 2013;

    /**
     * MAX_RETRY_CONNECT_COUNT
     */
    protected $maxRetryConnectTime = 2;


    /**
     * Mysql constructor.
     * @param $descriptor
     */
    public function __construct($descriptor)
    {
        if (is_array($descriptor)) {
            if (isset($descriptor['max_retry_connect'])) {
                $this->maxRetryConnectTime = $descriptor['max_retry_connect'];
            }
        }
        parent::__construct($descriptor);
    }

    /**
     * Author:Robert
     *
     * @param $error
     * @return bool
     */
    public function isConnectionError($error): bool
    {
        return isset($error[1]) && in_array($error[1], [self::CR_SERVER_GONE_ERROR, self::CR_SERVER_LOST]);
    }


    /**
     * Author:Robert
     *
     * @return bool
     */
    public function reconnect(): bool
    {
        if ($this->_reconnectTime >= $this->maxRetryConnectTime) {
            throw new \PDOException('Retry connection failed');
        }
        if (!$this->connect()) {
            $this->_reconnectTime++;
        } else {
            $this->_reconnectTime = 0;
        }
        return true;
    }

    /**
     * Author:Robert
     *
     * @param bool $nesting
     * @return bool
     */
    public function begin($nesting = true): bool
    {
        try {
            return parent::begin($nesting);
        } catch (\PDOException $exception) {
            if (!$this->isConnectionError($exception->errorInfo)) {
                throw $exception;
            }
            $this->reconnect();
            return $this->begin($nesting);
        }
    }
//
    //    /**
    //     * Author:Robert
    //     *
    //     * @param bool $nesting
    //     * @return bool|\Exception|\PDOException
    //     */
    //    public function rollback($nesting = true)
    //    {
    //        try {
    //            return parent::rollback($nesting);
    //        } catch (\PDOException $exception) {
    //            if (!$this->isConnectionError($exception->errorInfo)) {
    //                throw $exception;
    //            }
    //            $this->reconnect();
    //            return $exception;
    //        }
    //    }
    //
    //    /**
    //     * Author:Robert
    //     *
    //     * @param bool $nesting
    //     * @return bool|\Exception|\PDOException
    //     */
    //    public function commit($nesting = true)
    //    {
    //        try {
    //            return parent::commit($nesting);
    //        } catch (\PDOException $exception) {
    //            if (!$this->isConnectionError($exception->errorInfo)) {
    //                throw $exception;
    //            }
    //            $this->reconnect();
    //            return $exception;
    //        }
    //    }

    /**
     * Author:Robert
     *
     * @param string $sqlStatement
     * @param null $bindParams
     * @param null $bindTypes
     * @return bool|\Phalcon\Db\ResultInterface
     */
    public function query($sqlStatement, $bindParams = null, $bindTypes = null)
    {
        try {
            return parent::query($sqlStatement, $bindParams, $bindTypes);
        } catch (\PDOException $exception) {
            if (!$this->isConnectionError($exception->errorInfo)) {
                throw $exception;
            }
            $this->connect();
            return $this->query($sqlStatement, $bindParams, $bindTypes);
        }
    }

    /**
     * Author:Robert
     *
     * @param string $sqlStatement
     * @param null $bindParams
     * @param null $bindTypes
     * @return bool
     */
    public function execute($sqlStatement, $bindParams = null, $bindTypes = null): bool
    {
        try {
            return parent::execute($sqlStatement, $bindParams, $bindTypes);
        } catch (\PDOException $exception) {
            if (!$this->isConnectionError($exception->errorInfo) || $this->_transactionLevel > 0) {
                throw $exception;
            }
            $this->connect();
            return $this->execute($sqlStatement, $bindParams, $bindTypes);
        }
    }

    /**
     * Author:Robert
     *
     * @param $table
     * @param $data
     * @param bool $duplicateUpdate
     * @return bool
     */
    public function batchInsertAsDict($table, $data, $duplicateUpdate = false, $noUpdate = []): bool
    {
        $bind = [];
        $sql = [];
        $data = is_int(key($data)) ? $data : [$data];
        foreach ($data as $index => $items) {
            $holder = [];
            foreach ($items as $name => $value) {
                $pn = $name.$index;
                $holder[] = ':'.$pn;
                $bind[$pn] = $value;
            }
            $sql[] = '('.implode($holder, ',').')';
        }
        $fields = array_keys($data[0]);
        $field = '`'.implode($fields, '`,`').'`';
        $sql = "INSERT INTO $table ($field)VALUES".implode($sql, ',');
        if ($duplicateUpdate) {
            $duplicateUpdateSql = [];
            foreach ($fields as $field) {
                if (!$noUpdate || !in_array($field, $noUpdate)) {
                    $duplicateUpdateSql[] = "`$field`=VALUES(`$field`)";
                }
            }
            $sql .= 'ON DUPLICATE KEY UPDATE '.implode($duplicateUpdateSql, ',');
        }
        return $this->execute($sql, $bind);
    }
}

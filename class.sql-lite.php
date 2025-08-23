<?php

/**
 *  MySQL Routines LIGHT
 *    
 *  updt. 03/21  
 *
 */


class MyQueryLite
{
    var $currentTable = FALSE;  // table after check
    var $qryCnt = 0;        // queries counter
    var $errors  = FALSE;
    var $PDO;
    var $CONNECTED = FALSE;
    var $PDOEXISTS = FALSE;
    var $NumRows = 0;
    var $last_insert_id = 0;
    var $lastInsertId = 0;
    var $qry = '';
    var $table = FALSE;
    var $VALUES = array();
    var $fetchMode = 5; // PDO::FETCH_OBJ
    var $timestamp = 0;

    function __construct($autoConnect = TRUE)
    {
        if ($autoConnect && defined("SQL") && defined("DB")) {
            return $this->connect(SQL, DB, USER, PASS);
        }
        return FALSE;
    }

    function connect()
    {
        if (!defined("SQL")) {
            $this->error("No SQL connection defined");

            return;
        }

        $dsn = sprintf('mysql:dbname=%s;host=%s', DB, SQL);
        $con = TRUE;

        try {
            $PDO = new PDO($dsn, USER, PASS);

            $PDO->exec("SET NAMES utf8");
            $PDO->exec("SET SQL_MODE='ALLOW_INVALID_DATES'");
        } catch (PDOException $e) {
            $this->error('Connection failed: ' . $e->getMessage());
            $con = FALSE;
        }

        if ($con) {
            $this->PDO = $PDO;
        }
    }

    function testPDO()
    {
        return !is_null($this->PDO);
    }

    function error($msg = FALSE)
    {
        if ($msg === FALSE) {
            return $this->errors;
        }

        if (!$this->errors) {
            $this->errors = array();
        }

        $this->errors[microtime(true)] = $msg;
    }

    function qry($qry = FALSE)
    {
        if (!$qry) {
            return $this->qry;
        }

        $this->qry = $qry;
    }

    /**
     *  Main call
     *  
     *  - result as OBJECTS
     */

    function runPDO($query = FALSE, $values = FALSE, $wrong_result = FALSE)
    {
        $this->NumRows = 0;

        // -- SET QUERY

        if ($query === FALSE) {
            $query = $this->qry();
        }

        $query = trim($query);

        // -- catch command

        if (!preg_match("/^[A-Z]{3,6}/i", $query, $m)) {
            $this->error("No QUERY");

            return $wrong_result;
        }

        $COMMAND = strtoupper($m[0]);

        // -- connection test

        if (!$this->testPDO()) {
            $this->error("No PDO.");

            $result = $wrong_result;
        } else {
            $result = TRUE;

            try {
                $sth = $this->PDO->prepare($query);

                $this->PDO->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                if (!is_array($values)) {
                    $sth->execute();
                } else {
                    $sth->execute($values);
                }
            } catch (Exception $e) {
                $this->error($e->getMessage());
                $this->error($query);

                $result = $wrong_result;
            }
        }

        if ($COMMAND === 'SELECT' || $COMMAND === 'SHOW' || $COMMAND === 'DESCRIBE') {
            if (!$result) {
                return $wrong_result;
            }

            $result = $sth->fetchAll(PDO::FETCH_OBJ);

            $this->NumRows = $sth->rowCount();
        } else if ($COMMAND === 'INSERT') {
            if (!$result) {
                return 0;
            }

            $this->last_insert_id = $this->PDO->lastInsertId();

            $result = $this->last_insert_id;
        } else if ($COMMAND === 'UPDATE' || $COMMAND === 'DELETE') {
            if (!$result) {
                return 0;
            }

            $this->NumRows = $sth->rowCount();

            $result = $this->NumRows;
        }

        $this->qryCnt++;

        return $result;
    }

    /**
     *  uni caller pro funkce, který vyžadují aby bylo nastaveno
     *  
     */

    function testValuesAndTable($values = array(), $table = FALSE)
    {
        if (!is_array($values) || count($values) == 0) {
            $this->error("NO VALUES");

            return FALSE;
        }

        if (!$table) {
            $table = $this->table;
        }

        if (!$table) {
            $this->error("insertPDO > NO TABLE");

            return FALSE;
        }

        $this->currenTable = $table;

        return TRUE;
    }


    /**
     *  table update
     *  
     *  (int)Affected rows
     *  
     */

    function updatePDO($values = array(), $table = FALSE, $key = "id", $WherePlus = "")
    {
        if (!$this->testValuesAndTable($values, $table)) {
            return 0;
        }

        if (!isset($values[$key])) {
            $this->error("No `key` value in values ({$key}).");

            return 0;
        }

        $WHERE = sprintf(' `%s` = :%s ', $key, $key);

        $update = array();

        foreach ($values as $k => $v) {
            if ($k != $key) {
                $update[] = sprintf('`%s` = :%s', $k, $k);
            }
        }

        $query = sprintf('UPDATE `%s` SET %s WHERE %s %s', $this->currentTable(), implode(",", $update), $WHERE, $WherePlus);

        return $this->runPDO($query, $values, 0);
    }

    function update($values = array(), $table = FALSE, $key = "id", $WherePlus = "")
    {
        return $this->updatePDO($values, $table, $key, $WherePlus);
    }

    // Shortcut for getting 1 $row from $result 

    function getRow($query = FALSE, $values = FALSE)
    {
        $result = $this->runPDO($query, $values);

        if ($this->NumRows > 0) {
            return $result[0];
        }

        // -- DEPENDING ON $this->fetchMode return empty

        if ($this->fetchMode == PDO::FETCH_OBJ) {
            return new StdClass();
        } elseif ($this->fetchMode == PDO::FETCH_ASSOC) {
            return array();
        } elseif ($this->fetchMode == PDO::FETCH_NUM) {
            return array();
        }
    }

    /**
     *  PDO INSERT - query build 
     *  
     *  based on values array and table name
     *  
     */

    function insertPDO($values = array(), $table = FALSE, $justQuery = FALSE)
    {
        if (!$this->testValuesAndTable($values, $table)) {
            return 0;
        }

        $query = sprintf("INSERT INTO `%s` (`%s`) VALUES (:%s)", $this->currentTable, implode("`,`", array_keys($values)), implode(",:", array_keys($values)));

        if ($justQuery) {
            return $query;
        }

        return $this->runPDO($query, $values, 0);
    }

    // -- alias

    function insert($values = array(), $table = FALSE, $justQuery = FALSE)
    {
        return $this->insertPDO($values, $table, $justQuery);
    }
}

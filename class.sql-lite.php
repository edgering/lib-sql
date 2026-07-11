<?php

/**
 *  EDGERING SQL CLASS LITE
 *
 *  One-file lightweight variant derived from class.sql.php.
 *  It keeps core SQL operations and primary method names,
 *  without formatter/sanitizer dependencies.
 */

class MyQueryLite
{
    var $PDO;

    var $CONNECTED = FALSE;
    var $PDOEXISTS = FALSE;

    var $table = FALSE;

    var $NumRows = 0;

    var $last_insert_id = 0;
    var $lastInsertId = 0;

    var $qry = '';
    var $VALUES = array();

    var $errors = array();
    var $debug = array();

    var $LogDir = "log/";

    var $fetchMode = 5; // PDO::FETCH_OBJ
    var $timestamp = 0;

    var $error = FALSE; // proxy

    function __construct($autoConnect = TRUE)
    {
        if ($autoConnect && defined("SQL") && defined("DB") && defined("USER") && defined("PASS")) {
            return $this->connect(SQL, DB, USER, PASS);
        }

        return FALSE;
    }

    function startTimer()
    {
        $this->timestamp = microtime(true);
    }

    function stopTimer()
    {
        if ($this->timestamp == 0) {
            $this->debug("Timer was not started.");
            return 0;
        }

        return sprintf("%s ms", round((microtime(true) - $this->timestamp) * 1000, 5));
    }

    function tryPDO()
    {
        if (!$this->PDOEXISTS = extension_loaded('pdo')) {
            $this->error("PDO extension is not loaded.");
        }

        return $this->PDOEXISTS;
    }

    function connect($HOST, $DB, $USR, $PWD)
    {
        if (!($this->CONNECTED = $this->tryPDO())) {
            return $this->error("PDO extension is not loaded.");
        }

        $dsn = sprintf('mysql:dbname=%s;host=%s', $DB, $HOST);

        try {
            $this->PDO = new PDO($dsn, $USR, $PWD);

            $this->PDO->exec("SET NAMES utf8mb4");
            $this->PDO->exec("SET sql_mode = ''");

            $this->fetchMode = PDO::FETCH_OBJ;
            $this->CONNECTED = TRUE;
        } catch (PDOException $e) {
            $this->CONNECTED = FALSE;
            $this->error('Connection failed: ' . $e->getMessage());

            return FALSE;
        }

        $this->debug("Connected to database.");

        return TRUE;
    }

    function exec($query = '')
    {
        if (!$this->isConnected()) {
            return $this->error("Not connected to database.");
        }

        return $this->PDO->exec($query);
    }

    function warningOn()
    {
        return $this->addAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
    }

    function addAttribute($attribute, $value)
    {
        if (!$this->isConnected()) {
            return $this->error("Not connected to database.");
        }

        return $this->PDO->setAttribute($attribute, $value);
    }

    function setFetchMode($mode = PDO::FETCH_OBJ)
    {
        if (!$this->isConnected()) {
            return $this->error("Not connected to database.");
        }

        $this->fetchMode = $mode;

        return $this->PDO->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, $mode);
    }

    function fetchArray()
    {
        return $this->setFetchMode(2); // PDO::FETCH_ASSOC
    }

    function fetchObject()
    {
        return $this->setFetchMode(5); // PDO::FETCH_OBJ
    }

    function isConnected()
    {
        return $this->CONNECTED;
    }

    function update($data = FALSE, $ByKey = "id", $WherePlus = "")
    {
        return $this->updatePDO($data, $ByKey, $WherePlus);
    }

    function insert($values = FALSE, $table = FALSE)
    {
        return $this->insertPDO($values, $table);
    }

    function result($query = FALSE, $values = FALSE, $command = FALSE)
    {
        return $this->runPDO($query, $values, FALSE, $command);
    }

    function table($table = FALSE)
    {
        return $this->setTable($table);
    }

    function value($key, $value, $reset = FALSE)
    {
        return $this->addValue($key, $value, $reset);
    }

    function qry($query = FALSE)
    {
        if ($query === FALSE) {
            return $this->qry;
        }

        $this->qry = trim($query);

        return $this->qry;
    }

    function reset($hard = TRUE)
    {
        $this->NumRows = 0;
        $this->lastInsertId = 0;
        $this->last_insert_id = 0;

        if ($hard) {
            $this->qry = '';
            $this->VALUES = array();
        }
    }

    function getCommand($query = '')
    {
        return preg_match("/^([a-z]+( ROW)?)/i", trim($query), $m) ? strtoupper($m[1]) : FALSE;
    }

    function getQuery($query = FALSE)
    {
        if (($query === FALSE || $query == '') && $this->qry != '') {
            return trim($this->qry);
        }

        return trim($query);
    }

    function getTable($table = FALSE)
    {
        return (!$table && $this->table != '') ? $this->table : $table;
    }

    function setTable($table = FALSE)
    {
        if ($table === FALSE || $table == '') {
            return $this->error("Table name must be a non-empty string.");
        }

        $this->table = $table;
        $this->debug("Table set to: {$table}");

        return $this->table;
    }

    function getValues($values = FALSE)
    {
        if ($values === FALSE && count($this->VALUES) > 0) {
            return $this->VALUES;
        }

        return is_array($values) ? $values : array();
    }

    function values($values = array())
    {
        if (!is_array($values)) {
            return $this->error("Values must be an array.");
        }

        $this->VALUES = $values;

        return $this->VALUES;
    }

    function addValue($key, $value, $reset = FALSE)
    {
        if (!is_string($key) || $key == '') {
            return $this->error("Key must be a non-empty string.");
        }

        if ($reset) {
            $this->VALUES = array();
        }

        $this->VALUES[$key] = $value;

        return $this->VALUES;
    }

    function getFieldValue($k, $v)
    {
        if (!is_string($v)) {
            return sprintf(":%s", $k);
        }

        $v = trim($v);

        if (preg_match("/^([A-Z_]+)\((.*)?\)$/i", $v)) {
            return $v;
        }

        if (preg_match("/^DEFAULT/", $v)) {
            return $v;
        }

        if (preg_match("/^%(.*)%$/", $v, $m)) {
            return sprintf(":%s", $m[1]);
        }

        if (strpos($v, '`') !== FALSE) {
            return $v;
        }

        return sprintf(":%s", $k);
    }

    function updatePDO($values = FALSE, $key = "id", $WherePlus = "", $table = FALSE)
    {
        $this->reset(FALSE);

        if (!($table = $this->getTable($table))) {
            return $this->error("No table set for update.");
        }

        $values = $this->getValues($values);

        if (count($values) < 2) {
            return $this->error("No values set for update in table {$table}.");
        }

        if (!isset($values[$key]) || !$values[$key]) {
            return $this->error("No key {$key} set in values for table {$table}.");
        }

        $update = array();

        foreach ($values as $k => $v) {
            if ($k == $key) {
                continue;
            }

            $val = $this->getFieldValue($k, $v);

            if ($val[0] !== ':') {
                unset($values[$k]);
            }

            $update[] = sprintf('`%s` = %s', $k, $val);
        }

        if (!count($update)) {
            return $this->error("No values to update in table {$table} with key {$key}.");
        }

        $query = sprintf('UPDATE `%s` SET %s WHERE `%s` = :%s %s', $table, implode(",", $update), $key, $key, $WherePlus);

        return $this->runPDO($query, $values, $table, "UPDATE");
    }

    function insertPDO($values = array(), $table = FALSE)
    {
        $this->reset(FALSE);

        if (!($table = $this->getTable($table))) {
            return $this->error("No table set for insert.");
        }

        $values = $this->getValues($values);

        if (!count($values)) {
            return $this->error("No values set for insert in table {$table}.");
        }

        $VALS = array();
        $KEYS = array();

        foreach ($values as $k => $v) {
            $val = $this->getFieldValue($k, $v);

            if ($val[0] !== ':') {
                unset($values[$k]);
            }

            $KEYS[] = $k;
            $VALS[] = $val;
        }

        $query = sprintf("INSERT INTO `%s` (`%s`) VALUES(%s)", $table, implode("`,`", $KEYS), implode(",", $VALS));

        return $this->runPDO($query, $values, $table, "INSERT");
    }

    function runPDO($qry = FALSE, $val = FALSE, $table = FALSE, $command = FALSE)
    {
        $this->startTimer();

        $VALUES = $this->getValues($val);
        $QUERY = $this->getQuery($qry);

        $this->reset();

        if (!$this->isConnected()) {
            return $this->error("Not connected to database.");
        }

        if (empty($QUERY)) {
            return $this->error("No query set for execution.");
        }

        if (!$command) {
            $command = $this->getCommand($QUERY);
        }

        try {
            $sth = $this->PDO->prepare($QUERY);

            if ($sth === false) {
                $errorInfo = $this->PDO->errorInfo();
                $this->error("SQL Prepare Error: " . (isset($errorInfo[2]) ? $errorInfo[2] : 'Unknown error'));
                $this->log();

                return $this->emptyResult($command);
            }

            $sth->execute($VALUES);

            $tmp = $sth->errorInfo();
            if ($sth !== false && $tmp[0] !== '00000') {
                $this->error("SQL Error: " . $tmp[2]);
                $this->log();

                return $this->emptyResult($command);
            }
        } catch (Exception $e) {
            $this->error($e->getMessage());
            $this->log();

            return $this->emptyResult($command);
        }

        $this->debug("Query executed in: " . $this->stopTimer());

        $this->NumRows = $sth->rowCount();

        if ($command === 'SELECT' || $command === 'SHOW' || $command === 'DESCRIBE') {
            $result = $sth->fetchAll($this->fetchMode);
            return $result !== false ? $result : array();
        }

        if ($command === 'UPDATE' || $command === 'DELETE') {
            return $this->NumRows;
        }

        if ($command === 'INSERT') {
            $this->last_insert_id = $this->PDO->lastInsertId();
            $this->lastInsertId = $this->last_insert_id;

            return $this->last_insert_id;
        }

        if ($command === 'SELECT ROW') {
            $row = $sth->fetch($this->fetchMode);
            if ($row !== false) {
                return $row;
            }

            return $this->emptyResult($command);
        }

        return $this->error("Unknown command: {$command} in query: {$this->qry}");
    }

    function emptyResult($command = 'SELECT')
    {
        if ($command === 'SELECT ROW' && $this->fetchMode === PDO::FETCH_OBJ) {
            return new stdClass();
        }

        if ($command === 'INSERT' || $command === 'UPDATE' || $command === 'DELETE') {
            return 0;
        }

        return array();
    }

    function ResultAsArray($ById = TRUE, $value = "")
    {
        if (is_bool($ById)) {
            $ById = $ById ? "id" : "";
        }

        return $this->Load2Array(FALSE, $value, $ById);
    }

    function Load2Array($result = FALSE, $value = "", $key = "id")
    {
        if (!$result) {
            $result = $this->runPDO();
        }

        if (!is_array($result) || !count($result)) {
            return array();
        }

        $first_row = reset($result);

        if (is_object($first_row)) {
            $result = array_map(function ($row) {
                return (array)$row;
            }, $result);
        }

        if ($value == "" && $key == "") {
            return $result;
        }

        $first_row = (array)$first_row;

        if ($value == "") {
            if (!isset($first_row[$key])) {
                return $this->error("Key {$key} not found in result set.");
            }

            return array_combine(array_column($result, $key), $result);
        }

        if ($key == "") {
            return $this->error("Key must be specified.");
        }

        if (!isset($first_row[$key]) || !isset($first_row[$value])) {
            return $this->error("Key {$key} or value {$value} not found in result set.");
        }

        return array_combine(array_column($result, $key), array_column($result, $value));
    }

    function SelectAll($table = FALSE, $keys = "*", $plus = "LIMIT 500")
    {
        if ($table = $this->getTable($table)) {
            $query = sprintf('SELECT %s FROM `%s` %s', $keys, $table, $plus);
            return $this->runPDO($query, FALSE, $table, 'SELECT');
        }

        return $this->emptyResult();
    }

    function GetRow($query = '', $values = FALSE)
    {
        $this->reset(FALSE);

        $query = $this->getQuery($query);

        if (!preg_match("/^(SELECT|SHOW)/i", $query)) {
            return $this->emptyResult("SELECT ROW");
        }

        return $this->runPDO($query, $values, FALSE, 'SELECT ROW');
    }

    function GetRowById($id = 0, $table = FALSE, $keys = "*")
    {
        $this->reset(FALSE);

        if (!$table = $this->getTable($table)) {
            return $this->error("No table set for GetRowById.");
        }

        $query = sprintf("SELECT %s FROM `%s` WHERE id = %d LIMIT 1", $keys, $table, $id);

        return $this->runPDO($query, FALSE, $table, 'SELECT ROW');
    }

    function showLastQuery($hidden = FALSE, $query = NULL)
    {
        if ($query === NULL) {
            $query = $this->qry;
        }

        $this->echo($query, $hidden);
        $this->echo(var_export($this->VALUES, TRUE), $hidden);
    }

    function logLastQuery()
    {
        $this->debug($this->qry);
        $this->debug(var_export($this->VALUES, TRUE));
    }

    function logQuery()
    {
        $this->logLastQuery();
    }

    function debugOutput($query = '', $hidden = TRUE)
    {
        $this->showLastQuery($hidden, $query);
    }

    function log($dir = FALSE)
    {
        if ($this->LogDir === FALSE) {
            return FALSE;
        }

        if (!$dir) {
            $dir = $this->LogDir;
        }

        if (!is_dir($dir)) {
            return $this->error("Log directory does not exist: {$dir}");
        }

        $this->error($this->qry);
        $this->error(var_export($this->VALUES, TRUE));

        $file = sprintf("%ssql-%s.log", $dir, date("Y-m-d"));

        @file_put_contents($file, $this->ResultError() . "\n", FILE_APPEND | LOCK_EX);

        return TRUE;
    }

    function ResultError()
    {
        $message = sprintf("%s > %s", date("* Y-m-d H:i:s"), __FILE__);

        if (!count($this->errors)) {
            $message .= "\nNO ERRORS";
        } else {
            $message .= "\n" . implode("\n", $this->errors);
            $message .= "\n" . implode("\n", $this->debug);
        }

        return $message;
    }

    function error($message = NULL, $show = TRUE)
    {
        if ($message === NULL) {
            if ($show) {
                $this->echo($this->errors, !$show);
            }

            return $this->errors;
        }

        $this->errors[] = $message;
        $this->error = $message;

        return FALSE;
    }

    function debug($message = NULL, $show = TRUE)
    {
        if ($message === NULL) {
            if ($show) {
                $this->echo($this->debug, !$show);
            }

            return $this->debug;
        }

        $this->debug[] = $message;

        return $message;
    }

    function echo($what, $hidden = FALSE)
    {
        if ($hidden) {
            echo "<!--\n";
        } else {
            echo "<pre>";
        }

        print_r($what);

        if ($hidden) {
            echo "\n-->";
        } else {
            echo "</pre>";
        }
    }

    function info($hidden = FALSE)
    {
        $this->echo($this, $hidden);
    }
}

if (!class_exists('MyQuery', FALSE)) {
    class_alias('MyQueryLite', 'MyQuery');
}

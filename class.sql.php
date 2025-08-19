<?php

/**
 *  EDGERING SQL CLASS 
 *
 *  @param DB, SQL, USER, PASS
 *
 *  - set LogDir to FALSE to not create log files
 */

require_once __DIR__ . "/SqlFormatter.php";

class MyQuery
{
    var $PDO;

    var $CONNECTED = FALSE;
    var $PDOEXISTS = FALSE;

    var $table = false;

    var $NumRows = 0;

    var $last_insert_id = 0;
    var $lastInsertId = 0;

    var $qry = '';
    var $VALUES = array();

    var $errors = array();
    var $debug  = array();

    var $LogDir = "log/";

    var $fetchMode = 5; // PDO::FETCH_OBJ
    var $timestamp = 0;

    /**
     *  Constructor 
     *  
     */

    function __construct($autoConnect = TRUE)
    {
        if ($autoConnect && defined("SQL") && defined("DB")) {
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

    /**
     *  PDO & CONNECTION CHECK
     */

    function tryPDO()
    {
        if (!$this->PDOEXISTS = extension_loaded('pdo')) {
            $this->error("PDO extension is not loaded.");
        }

        return $this->PDOEXISTS;
    }

    /**
     *  Connect to database via PDO
     */

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

            // $this->PDO->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);

            $this->fetchMode = PDO::FETCH_OBJ;
        } catch (PDOException $e) {

            $this->CONNECTED = FALSE;
            $this->error('Connection failed: ' . $e->getMessage());

            return FALSE;
        }

        $this->debug("Connected to database.");

        return TRUE;
    }

    /**
     *  PDO Attributes & values
     */

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
        return $this->setFetchMode(5);
    }

    /**
     *  Some shortcuts
     */

    function isConnected()
    {
        return $this->CONNECTED;
    }

    function update($data = FALSE, $ByKey = "id", $WherePlus = "")
    {
        return $this->updatePDO($data, $ByKey, $WherePlus);
    }

    function insert($values = FALSE, $table = false)
    {
        return $this->insertPDO($values, $table);
    }

    function result($query = FALSE, $values = FALSE, $command = FALSE)
    {
        return $this->runPDO($query, $values, FALSE, $command);
    }

    function table($table = false)
    {
        return $this->setTable($table);
    }

    function value($key, $value, $reset = FALSE)
    {
        return $this->addValue($key, $value, $reset);
    }

    /**
     *  Reset values and query
     */

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

    /**
     *  MATCH QUERY COMMAND
     *      
     */

    function getCommand($query = '')
    {
        return preg_match("/^([a-z]+( ROW)?)/i", trim($query), $m) ? strtoupper($m[1]) : FALSE;
    }

    /**
     *  GET QUERY
     *  
     *  - get stored query if no query is set          
     */

    function getQuery($query = FALSE)
    {
        if (($query === FALSE || $query == '') && $this->qry != '') {
            return trim($this->qry);
        }

        return trim($query);
    }

    /**
     *  GET TABLE
     *  
     *  - get stored table if no table is set
     */

    function getTable($table = false)
    {
        return (!$table && $this->table != '') ? $this->table : $table;
    }

    function setTable($table = false)
    {
        if ($table === FALSE || $table == '') {
            return $this->error("Table name must be a non-empty string.");
        }

        $this->table = $table;
        $this->debug("Table set to: {$table}");

        return $this->table;
    }


    /** 
     *  GET VALUES
     */

    function getValues($values = FALSE)
    {
        if ($values === FALSE && count($this->VALUES) > 0) {
            return $this->VALUES;
        }

        return is_array($values) ? $values : array();
    }

    /**
     *  SET VALUES
     * 
     *  - use empty call to reset values
     * 
     */

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

    /**
     *  FIELD VALUE CHECKER
     * 
     *  - check if value is a function, default, or placeholder
     *  - return formatted value for query
     */

    function getFieldValue($k, $v)
    {
        $v = trim($v);

        // -- catch functions & return as is
        // -- i.e: NOW() or CONCAT('value1', 'value2')

        if (preg_match("/^([A-Z_]+)\((.*)?\)$/i", $v, $m)) {
            return $v;
        }

        // -- catch default values
        // -- i.e: DEFAULT(`field`) || DEFAULT

        if (preg_match("/^DEFAULT/", $v)) {
            return $v; // return as is
        }

        // -- catch placeholder
        // -- i.e: %placeholder%

        if (preg_match("/^%(.*)%$/", $v, $m)) {
            return sprintf(":%s", $m[1]);
        }

        // -- catch if field is used inside value
        // -- i.e: `field` + 5; (just detect `)

        if (strpos($v, '`') !== FALSE) {
            return $v;
        }

        return sprintf(":%s", $k);
    }

    /**
     *  PDO UPDATE 
     *  
     *  based on values array and table name
     *  
     */

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

            if ($k == $key) continue;

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

    /**
     *  PDO INSERT 
     *  
     *  based on values array and table name
     *  
     */

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


    /**
     *  MAIN PDO QUERY CALL
     *  
     *  @param $table - deprecate but still used due compatibility
     * 
     */

    function runPDO($query = FALSE, $values = FALSE, $table = FALSE, $command = FALSE)
    {
        $this->startTimer();
        $this->reset(FALSE);

        if (!$this->isConnected()) {
            return $this->error("Not connected to database.");
        }

        $this->NumRows = 0;
        $this->last_insert_id = 0;
        $this->lastInsertId = 0;

        if (empty($this->qry = $this->getQuery($query))) {
            return $this->error("No query set for execution.");
        }

        $this->VALUES = $this->getValues($values);

        if (!$command) {
            $command = $this->getCommand($this->qry);
        }

        try {

            $sth = $this->PDO->prepare($this->qry);
            $sth->execute($this->VALUES);

            $tmp = $sth->errorInfo();

            if (!preg_match("/^([0]+)$/", $tmp[0])) {

                $this->error("SQL Error: " . $tmp[2]);
                $this->log();

                return $this->emptyResult($command);
            }
        } catch (Exception $e) {

            $this->error($e->getMessage());
            $this->log();

            return $this->emptyResult($command);
        }

        $this->debug("Query executed in " . $this->stopTimer());

        $this->NumRows = $sth->rowCount();

        if ($command === 'SELECT' || $command === 'SHOW' || $command === 'DESCRIBE') {
            $result = $sth->fetchAll($this->fetchMode);
            return $result !== false ? $result : array();
        }

        // For UPDATE and DELETE, return the number of affected rows.

        if ($command === 'UPDATE' || $command === 'DELETE') {
            return $this->NumRows;
        }

        if ($command === 'INSERT') {

            $this->last_insert_id = $this->PDO->lastInsertId();
            $this->lastInsertId = $this->last_insert_id;

            // Return the auto-incremented primary key value from the last insert operation
            return $this->last_insert_id;
        }

        if ($command === 'SELECT ROW') {
            $row = $sth->fetch($this->fetchMode);

            if ($row !== false) {
                return $row;
            }

            if ($this->fetchMode === PDO::FETCH_OBJ) {
                return new stdClass();
            }

            return array();
        }

        return $this->error("Unknown command: {$command} in query: {$this->qry}");
    }

    /**
     *  Empty result
     * 
     *  - return expected format of result to not break code
     * 
     */

    function emptyResult($command = 'SELECT')
    {
        $result = array();

        if ($command === 'SELECT ROW' && $this->fetchMode === PDO::FETCH_OBJ) {
            $result = new stdClass();
        } else if ($command === 'INSERT' || $command === 'UPDATE' || $command === 'DELETE') {
            $result = 0;
        }

        return $result;
    }

    /**
     *  Another shortcuts
     */

    /**
     *  Translate ResultAsArray to associative array
     * 
     *  - use to get associative array by key and value     
     * 
     *  - TODO: prepare query 
     */

    function ResultAsArray($ById = true, $value = "")
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

        // -- Just return as array, nothing more to do

        if ($value == "" && $key == "") {
            return $result;
        }

        $first_row = (array)$first_row;

        // -- Map the result to associative array by selected key
        // -- [0] = array("id" => 1, "name" => "John") => [1] = array("id" => 1, "name" => "John")

        if ($value == "") {
            if (!isset($first_row[$key])) {
                return $this->error("Key {$key} not found in result set.");
            }
            // -- Map result to associative array by key
            $result = array_combine(
                array_column($result, $key),
                $result
            );

            return $result;
        }

        // -- Map the result to associative array by selected key and value
        // -- [0] = array("id" => 1, "name" => "John") => [1] = "John"

        if ($key == "") {
            return $this->error("Key must be specified.");
        }

        if (!isset($first_row[$key]) || !isset($first_row[$value])) {
            return $this->error("Key {$key} or value {$value} not found in result set.");
        }

        return array_combine(
            array_column($result, $key),
            array_column($result, $value)
        );
    }

    /**
     *  Shortcut to select all records from table
     */

    function SelectAll($table = false, $keys = "*", $plus = "LIMIT 500")
    {
        if ($table = $this->getTable($table)) {
            $query = sprintf('SELECT %s FROM `%s` %s', $keys, $table, $plus);
            return $this->runPDO($query, FALSE, $table, 'SELECT');
        }

        return $this->emptyResult();
    }

    /**
     *  TO GET SINGLE RECORD FROM TABLE
     * 
     *  - by query
     * 
     */

    function GetRow($query = '', $values = FALSE)
    {
        $this->reset(FALSE);

        $query = $this->getQuery($query);

        if (!preg_match("/^(SELECT|SHOW)/i", $query)) {
            return $this->emptyResult("SELECT ROW");
        }

        return $this->runPDO($query, $values, false, 'SELECT ROW');
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

    /**
     *  DEBUGGING AND LOGGING
     */

    function showLastQuery($hidden = FALSE, $query = NULL)
    {
        if ($query === NULL) {
            $query = $this->qry;
        }

        if (!$hidden) {
            $query = SqlFormatter::format($query);
        }

        $this->echo($query, $hidden);
        $this->echo(var_export($this->VALUES, TRUE), $hidden);
    }

    function logLastQuery()
    {
        $this->debug($this->qry);
        $this->debug(var_export($this->VALUES, TRUE));
    }

    function logQuery() //  alias for logLastQuery
    {
        $this->logLastQuery();
    }

    function debugOutput($query = '', $hidden = true)
    {
        $this->showLastQuery($hidden, $query);
    }

    // -- errro log

    function log($dir = FALSE)
    {
        if (!$dir) {
            $dir = $this->LogDir;
        }

        if (!is_dir($dir)) {
            return $this->error("Log directory does not exist: {$dir}");
        }

        $this->error($this->qry);
        $this->error(var_export($this->VALUES, TRUE));

        $file = sprintf("%ssql-%s.log", $this->LogDir, date("Y-m-d"));

        @file_put_contents($file, $this->ResultError() . "\n", FILE_APPEND | LOCK_EX);
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

    /**
     *  Error and Debug handling
     * 
     *  GETTER AND SETTER FOR ERRORS & DEBUG
     */

    function error($message = NULL, $show = TRUE)
    {
        if ($message === NULL) {

            if ($show) {
                $this->echo($this->errors, !$show);
            }

            return $this->errors;
        }

        $this->errors[] = $message;

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

    function info($hidden = false)
    {
        $this->echo($this, $hidden);
    }

    /**
     *  SANITIZE VALUES BY TYPE
     * 
     *  - sanitize values for insert or update
     * 
     */

    function sanitizeInsert($values = array(), $table = false)
    {
        return $this->sanitizeValues($values, TRUE, $table);
    }

    function sanitizeUpdate($values = array(), $table = false)
    {
        return $this->sanitizeValues($values, FALSE, $table);
    }

    function sanitizeValues($values = array(), $setEmptyDefault = FALSE, $table = false)
    {
        if (!$table = $this->getTable($table)) {
            return $values;
        }

        foreach ($this->runPDO("SHOW COLUMNS FROM {$table}") as $row) {
            if (!isset($values[$row->Field])) continue;

            // -- catch function as value

            if (preg_match("/\(\)$/", $values[$row->Field])) {
                continue;
            }

            if (
                preg_match("/(DATE|STAMP)/i", $row->Type)
                && preg_match("/^(([0-9]{2})\.([0-9]{2})\.([0-9]{4}))/", $values[$row->Field], $m)
            ) {
                $datum = sprintf("%s-%s-%s", $m[4], $m[3], $m[2]);
                $values[$row->Field] = str_replace($m[1], $datum, $values[$row->Field]);

                if (preg_match("/^00/", $values[$row->Field])) {
                    // -- clean for next step 

                    $values[$row->Field] = '';
                }
            }

            // -- handle empty values                    

            if ($values[$row->Field] !== '') continue;

            if ($row->{"Null"} === 'YES') {
                $values[$row->Field] = NULL;
            } else if ($row->{"Default"} !== NULL && $row->{"Default"} !== '') {
                $values[$row->Field] = sprintf('DEFAULT(`%s`)', $row->Field);
            } else if (preg_match("/INT|BOO|DEC|FLO|DOU/i", $row->Type)) {
                $values[$row->Field] = 0;
            }
        }

        return $values;
    }
}

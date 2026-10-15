<?php

if (!defined('APPLICATION_LOADED') || !APPLICATION_LOADED) {
    die('No direct script access.');
}

class Mysql {

    public $conn = null;
    public $connections = array();
    public $queries = array();

    public function __construct() {
        $ms = microtime(true);
        if(!class_exists('mysqli')) {
            throw new Exception("No MYSQLi extension");
        }
        mysqli_report(MYSQLI_REPORT_OFF); //PHP 8.1+ throws on query errors by default; this code expects false
        $this->conn = new mysqli(HOST, USER, PASS, DATABASE);
        if ($this->conn->connect_error) {
            throw new Exception("MySQL connection failed: " . $this->conn->connect_error);
            exit;
        } else {
            $this->conn->set_charset("utf8");
            $res = "Connected to MySQL";
        }
        $ms = microtime(true) - $ms;
        if (DEBUG_MODE === true) {
            $this->connections[] = array(
                'result' => $res,
                'time' => number_format($ms, 5, '.', ''));
        }
    }

    public function escape($value) {
        return trim(mysqli_real_escape_string($this->conn, (string) $value));
    }

    /*
     * Safe value helpers - every value that reaches SQL must pass through one of these
     */

    public function quote($value) { //quoted string literal, or NULL
        if ($value === null) {
            return 'NULL';
        }
        return "'" . mysqli_real_escape_string($this->conn, (string) $value) . "'";
    }

    public function intList($values) { //comma separated integers for IN(), never empty
        if (!is_array($values)) {
            $values = explode(',', (string) $values);
        }
        $ints = array();
        foreach ($values as $value) {
            if (trim((string) $value) !== '') {
                $ints[] = (int) $value;
            }
        }
        return empty($ints) ? '0' : implode(',', array_unique($ints));
    }

    public function onlyColumns($data, $allowed) { //drop every key that is not an allowed column
        return array_intersect_key($data, array_flip($allowed));
    }

    public function insertSql($table, $data) {
        $columns = array();
        $values = array();
        foreach ($data as $column => $value) {
            $columns[] = '`' . str_replace('`', '', $column) . '`';
            $values[] = $this->quote($value);
        }
        return "INSERT INTO `$table` (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ")";
    }

    public function setSql($data) { //SET part of UPDATE
        $sets = array();
        foreach ($data as $column => $value) {
            $sets[] = '`' . str_replace('`', '', $column) . '` = ' . $this->quote($value);
        }
        return implode(', ', $sets);
    }

    public function query($query) {
        $ms = microtime(true);
        $result = $this->conn->query($query);
        $ms = microtime(true) - $ms;
        if (DEBUG_MODE === true) {
            $this->queries[] = array(
                'query' => $query,
                'time' => number_format($ms, 5, '.', ''));
        }
        return $result;
    }

}

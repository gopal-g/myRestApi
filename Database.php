<?php

/* URL https://github.com/gopal-g/PDO-phpclass
 * Author  Blackburn027
 * Generic PDO Connection Class for PHP
 */


Class Database {
    /* Properties of database class
     * @Host
     * @User
     * @Passwrd
     * @Name
     * --------
     * @$Link - Holds the PDO connection object after successful connection
     * @Error - Holds the Exception incase of error while connection / query
     * @Isconnected - Boolean to tell connection status
     * @para
     */

    protected $link;
    protected $hostname;
    protected $user;
    protected $pass;
    protected $db;
    private $_query;
    private $_result;
    public $error;
    public $_data = array();
    public $isConnected = FALSE;

    /*
     * Construct which loads the db class with the supplied parameters
     * Initiates the PDO Connection on load of class
     * Sets the Db Status Flag / Response Code - Depending on success / failure
     */

    public
            function __construct($host, $username, $password, $db) {

        $this->hostname = $host;
        $this->user = $username;
        $this->pass = $password;
        $this->db = $db;
        if (!$this->connect()) {
            http_response_code($this->error_data('getCode'));
        } else {
            $this->isConnected = TRUE;
        }
    }

    private
            function connect() {

        try {

            $this->link = new PDO("mysql:host=" . $this->hostname . ";dbname=" . $this->db . "", "$this->user", "$this->pass");
            $this->link->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            return TRUE;
        } catch (Exception $exc) {

            $this->error = $exc;

            $this->logging();

            return FALSE;
        }
    }

    private
            function logging() {

        $log_string = "Code:" . $this->error_data('getCode') . "<br/>Message:" . $this->error_data('getMessage') . "<br/>Trace:" . $this->error_data('getTraceAsString');

        error_log($log_string);

        return $log_string;
    }

    public
            function error_data($method = 'getMessage') {
//        print_r($this->error);
        return $this->error->$method();
    }

    public
            function flush() {
//        $this->error ='';
        $this->_result = '';
        $this->_query = '';
        $this->_data = array();
    }

    public
            function composeSelect($table, $fields = array(), $conditions = array(), $options = array()) {

        if (!$table) {
            $this->error = 'Invalid DB Parameters';
            return false;
        }

        $field = (empty($fields)) ? ' * ' : $this->buildSelectFields($fields);

        $cond = (empty($conditions)) ? '' : $this->buildConditions($conditions);

        $this->_query = "SELECT " . $field . " FROM " . $table . " " . $cond;

        return ($this->_query);
    }

    private
            function buildSelectFields($fields) {

        $csep = '';
        $fieldString = '';

        if (is_array($fields)) {

            foreach ($fields as $val) {

                $fieldString .= $csep;

                $fieldString .='`' . $val . '`';

                $csep = ' , ';
            }
        } else {
            $fieldString = '*';
        }

        return $fieldString;
    }

    public
            function composeInsert($table, $fields = array(), $conditions = array(), $options = array()) {

        $fieldset = $this->buildInsertFields($fields);

        $valueset = $this->buildInsertValues($fields);

        $this->_query = 'INSERT INTO ' . $table . ' (' . $fieldset . ') VALUES (' . $valueset . ')';
    }

    public
            function buildInsertFields($fields = array()) {
        $csep = '';
        $fieldString = '';

        if (is_array($fields)) {

            foreach ($fields as $val) {

                $fieldString .= $csep;

                $fieldString .='`' . $val . '`';

                $csep = ' , ';
            }
        } else {
            $fieldString = '';
        }

        return $fieldString;
    }

    public
            function buildInsertValues($values = array()) {
        $csep = '';
        $valueString = '';

        if (is_array($values)) {

            foreach ($values as $val) {

                $valueString .= $csep;

                $valueString .=':' . $val . '';

                $csep = ' , ';
            }
        } else {
            $valueString = '';
        }

        return $valueString;
    }

    public
            function composeUpdate($table, $fields = array(), $conditions = array(), $options = array()) {

        $fieldset = $this->buildUpdatePairs($fields);

        $cond = $this->buildConditions($conditions);

        $this->_query = 'UPDATE ' . $table . ' SET ' . $fieldset . '' . $cond;

        return $this->_query;
    }

    private
            function buildUpdatePairs($fields) {
        $csep = '';

        $fieldString = '';

        if (is_array($fields)) {

            foreach ($fields as $val) {

                $fieldString .= $csep;

                $fieldString .='`' . $val . '` = :' . $val . '';

                $csep = ' , ';
            }
        } else {
            $fieldString = '';
        }

        return $fieldString;
    }

    private
            function buildConditions($conditions) {

        $and = '';
        $conditionString = '';

        if (is_array($conditions) && !empty($conditions)) {



            $conditionString = " WHERE ";

            foreach ($conditions as $key) {

                $conditionString .= $and;
                $conditionString .= $key . ' = :' . ($key) . '';
                $and = ' AND ';
            }
        } else {
            $conditionString = '';
        }

        return $conditionString;
    }

    public
            function prepareFields($keyValuePair) {

        $tempArr = array();

        if (!is_array($keyValuePair)) {
            return (array(':' . $keyValuePair => $keyValuePair));
        }

        foreach ($keyValuePair as $key => $val) {
            $tempArr[':' . $key] = $val;
        }

        return $tempArr;
    }

    public
            function composeDelete($table, $conditions = array(), $options = array()) {

//        $cond = $this->buildConditions($conditions);

        $this->_query = 'DELETE  FROM ' . $table . ' WHERE ' . $this->buildConditions($conditions);

        return $this->_query;
    }

    public
            function query($wheredata = array()) {
        try {
            $tempQuery = $this->link->prepare($this->_query);
            $tempQuery->execute($wheredata);

            $this->_result = $tempQuery;

            return TRUE;
        } catch (Exception $exc) {

            $this->error = $exc;

            $this->logging();

            return FALSE;
        }
    }

    public
            function get($method = 'fetchAll', $fetchMode = PDO::FETCH_ASSOC) {

        $this->_data['rows'] = 0;

        $this->_data['data'] = array();

        $this->_result->setFetchMode($fetchMode);

        if (in_array($method, get_class_methods($this->_result))) {

            $this->_data['rows'] = $this->_result->rowCount();

            $this->_data['data'] = $this->_result->$method();
        }


        return $this->_data;
    }

    public
            function last_query() {
        return $this->_query;
    }

    public
            function last_insert_id() {
        return ($this->link->lastInsertId());
    }

    public
            function affected_rows() {
        return ($this->_result->rowCount());
    }

}
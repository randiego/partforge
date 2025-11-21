<?php
/**
 *
 * PartForge Enterprise Groupware for recording parts and assemblies by serial number and version along with associated test data and comments.
 *
 * Copyright (C) 2013-2021 Randall C. Black <randy@blacksdesign.com>
 *
 * This file is part of PartForge
 *
 * PartForge is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * PartForge is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with PartForge.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @license GPL-3.0+ <http://spdx.org/licenses/GPL-3.0+>
 */

class DbSchema {   // singleton

    private $_dictionary;

    protected static $_instance = null;
    protected $_fieldtypes = array();
    protected $_sort_order = array();
    protected $_tablenames = array();
    protected $_primaryindexname = array();
    protected $_nonprimaryfieldnames = array();
    protected $_join_fields_and_tables = array();
    protected $_tree_table_params = null;
    protected $_tree_data = array();
    protected $_rel_dep_on_table_array = array();
    protected $_rel_table_is_dep_on_array = array();
    protected $_itemversion_object_cache = array();
    private $_has_json_support = null;
    protected static $_db_link = null;

    protected function __construct()
    {
        global $DICTIONARY;
        include(dirname(__FILE__).'/fielddictionary.php');
        $this->_dictionary = $DICTIONARY;
        $this->connectFullAccess();

    }

    public function hasJsonSupport() {
        if (is_null($this->_has_json_support)) {
            $records = $this->getRecords('', "SELECT VERSION() AS ver;");
            $record = reset($records);
            $arr = explode('-', $record['ver']);
            if (isset($arr[1]) && (substr($arr[1], 0, strlen('MariaDB'))=='MariaDB')) {
                $this->_has_json_support = version_compare($arr[0], '10.2.3') >= 0;
            } else {
                $this->_has_json_support = version_compare($arr[0], '5.7.2') >= 0;
            }
        }
        return $this->_has_json_support;
    }

    public function connectReadOnly()
    {
        $config = Zend_Registry::get('config');
        $this->connectMySql($config->db_params->host, $config->db_params->ro_username, $config->db_params->ro_password, $config->db_params->dbname);
    }

    public function connectFullAccess()
    {
        $config = Zend_Registry::get('config');
        $this->connectMySql($config->db_params->host, $config->db_params->username, $config->db_params->password, $config->db_params->dbname);
        if ($config->local_testing) {  // to give enough time for stepping through debugger
            $result = mysqli_query(self::$_db_link, "SET SESSION wait_timeout=600;");
        }
        // the default is really low for this.  Could set globally once, but not sure how to remember
        $result = mysqli_query(self::$_db_link, "SET SESSION group_concat_max_len = 100000;");
    }

    protected function connectMySql($host, $username, $password, $dbname)
    {
        self::$_db_link = mysqli_connect($host, $username, $password, $dbname);
        mysqli_set_charset(self::$_db_link, "utf8mb4");
        if (!self::$_db_link) {
            if (mysqli_errno(self::$_db_link) == 1203) { // too many connections
                $msg = 'Error connecting to database: '.mysqli_error(self::$_db_link).'; The server is too busy at the moment. You may click refresh in your browser or try again later.';
            } else {
                $msg = 'Error connecting to database: '.mysqli_error(self::$_db_link).'; You may click refresh in your browser or try again later.';
            }
            // we should assume that we do not have any framework ready for a call to showdialog if this is the first useable of DbSchema
            echo block_text_html($msg);
            die();
        }
    }

    public function getConnectionLink()
    {
        return self::$_db_link;
    }

    public static function getInstance()
    {
        if (null === self::$_instance) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * This is a common interface for loading/caching an DBTableRowItemVersion record fetched by itemversion_id.
     * It's just for efficiency when displaying itemview pages and mainly used in EventStream
     * @param integer $itemversion_id
     * @return DBTableRowItemVersion object:
     */
    public function getItemVersionCachedRecordById($itemversion_id)
    {
        if (!isset($this->_itemversion_object_cache[$itemversion_id])) {
            $this->_itemversion_object_cache[$itemversion_id] = new DBTableRowItemVersion(false, null);
            $this->_itemversion_object_cache[$itemversion_id]->getRecordById($itemversion_id);
        }
        return $this->_itemversion_object_cache[$itemversion_id];
    }

    private function dbFieldTypeToSchemaType($dbfieldtypes)
    {
        $out = array();
        foreach ($dbfieldtypes as $name => $dbfieldtype) {
            $type_parse = preg_split('/[()]/', $dbfieldtype['Type']);
            $isnotnull = (!$dbfieldtype['Null'] || $dbfieldtype['Null'] == 'NO');
            $mytype = $type_parse[0];
            if ($dbfieldtype['Type']=='int(1)') {
                $mytype = 'boolean';
            }
            if ($dbfieldtype['Type']=='longtext') {
                $mytype = 'text';
            }
            if ($dbfieldtype['Key']=='PRI') {
                $mytype = 'id';
            }
            $out[$name] = array(
                'dbtype' => $dbfieldtype,
                'caption' => ucwords(str_replace('_', ' ', $name)),
                'type' => $mytype,    // type = varchar or text
                'required' => $isnotnull,
                'default' => $dbfieldtype['Default'],
                                );
            if (isset($type_parse[1]) && $type_parse[1]) {
                $out[$name]['len'] = $type_parse[1];
            }
        }
        return $out;
    }

    public function resetFieldTypesCache($tablename)
    {
        if (isset($this->_fieldtypes[$tablename])) {
            unset($this->_fieldtypes[$tablename]);
        }
    }

    public function getFieldTypes($tablename)
    {
        if (!isset($this->_fieldtypes[$tablename])) {
            // get from mysql definitions first
            $this->_fieldtypes[$tablename] = $this->dbFieldTypeToSchemaType($this->getRecords('Field', "SHOW FIELDS FROM $tablename"));
            // then override with dictionary
            if (isset($this->_dictionary['tables'][$tablename]['fields'])) {
                foreach ($this->_dictionary['tables'][$tablename]['fields'] as $fieldname => $attributes ) {
                    if (is_array($attributes)) {
                        $this->_fieldtypes[$tablename][$fieldname] = array_merge($this->_fieldtypes[$tablename][$fieldname], $attributes);
                    }
                }
            }
        }
        return $this->_fieldtypes[$tablename];
    }

    public function getSortOrder($tablename)
    {
        if (!isset($this->_sort_order[$tablename])) {
            $this->_sort_order[$tablename] = isset($this->_dictionary['tables'][$tablename]['sort_order']) ? $this->_dictionary['tables'][$tablename]['sort_order'] : $this->getDefaultDescriptionField($tablename);
        }
        return $this->_sort_order[$tablename];
    }

    public function getJoins($tablename)
    {
        /*
         type = {incoming, outgoing}
         options = array(jo_link, jo_orphans_only, jo_add, jo_delete, jo_detach)
        */
        return !empty($this->_dictionary['tables'][$tablename]['joins']) ? $this->_dictionary['tables'][$tablename]['joins'] : array();
    }

    public function getFieldType($tablename, $fieldname)
    {
        $types = $this->getFieldTypes($tablename);
        return $types[$fieldname];
    }

    public function getTableNames()
    {
        if (empty($this->_tablenames)) {
            $config = Zend_Registry::get('config');
            $recs = $this->getRecords('Tables_in_'.$config->db_params->dbname, "SHOW TABLES");
            $this->_tablenames = array_keys($recs);
        }
        return $this->_tablenames;
    }

    public function getPrimaryIndexName($tablename)
    {
        if (!isset($this->_primaryindexname[$tablename])) {
            foreach ($this->getFieldTypes($tablename) as $fieldname => $record) {
                if ($record['type']=='id') {
                    $this->_primaryindexname[$tablename] = $fieldname;
                    break;
                }
            }
            if (!isset($this->_primaryindexname[$tablename])) {
                throw new Exception("primary index not found for table {$tablename} in DbSchema::getPrimaryIndexName()");
            }
        }
        return $this->_primaryindexname[$tablename];
    }

    public function getRelationshipTree($subheading = null)
    {
        if (empty($subheading)) {
            return $this->_dictionary['tree'];
        }

        foreach ($this->_dictionary['tree'] as $tree_branch) {
            if ($tree_branch['heading']==$subheading) {
                return $tree_branch['children'];
            }
        }
        throw new Exception("subheading {$subheading} not found in DbSchema::getRelationshipTree()");
    }

    /*
      $parent_index is set if we want to associate the row object with a specific branch
      of the tree when it appears in multiple nodes.  $parent_index is the name of the
      field in the current table that points to a parent record.
    */
    public function dbTableRowObjectFactory($tablename, $ignore_joins = false, $parent_index = null)
    {
        // see if there is a specific class to handle requests to edit this table and return an object
        if (isset($this->_dictionary['tables'][$tablename]['class']) && class_exists($this->_dictionary['tables'][$tablename]['class'])) {
            $classname = $this->_dictionary['tables'][$tablename]['class'];
            return new $classname($ignore_joins, $parent_index);
        } else {
            return new DBTableRow($tablename, $ignore_joins, $parent_index);
        }
    }

    public function getJoinFieldsAndTables($tablename)
    {
        if (!isset($this->_join_fields_and_tables[$tablename])) {
            $this->_join_fields_and_tables[$tablename] = array();
            // gets joins from joins array
            foreach ($this->getJoins($tablename) as $join_name => $join_link) {
                $dbtable = DbSchema::getInstance()->dbTableRowObjectFactory($join_link['rhs_table'], true);  // joined table can't also join
                $this->_join_fields_and_tables[$tablename][$join_name] = $join_link;
                $this->_join_fields_and_tables[$tablename][$join_name]['rhs_dbtableobj'] = $dbtable;
                if (!isset($join_link['mode'])) {
                    $this->_join_fields_and_tables[$tablename][$join_name]['mode'] = 'R';
                }
                if (!isset($join_link['options'])) {
                    $this->_join_fields_and_tables[$tablename][$join_name]['options'] = array();
                }
            }
        }
        return $this->_join_fields_and_tables[$tablename];
    }

    public function getRelationships()
    {
        // find all table relationships and put in a single list.
        $out = array();
        // outgoing join matches
        foreach ($this->getTableNames() as $searchtablename) {
            foreach ($this->getJoinFieldsAndTables($searchtablename) as $target) {
                if ('outgoing'==$target['type']) {
                    $out[] = array('table' => $target['rhs_table'], 'index' => $target['rhs_index'],'dep_table' => $searchtablename, 'dep_index' => $target['lhs_index']);
                } elseif (('incoming'==$target['type'])) {
                    $out[] = array('table' => $searchtablename, 'index' => $target['lhs_index'],'dep_table' => $target['rhs_table'], 'dep_index' => $target['rhs_index']);
                }
            }
        }
        // tree dependencies
        $tree_data = $this->getTreeData();
        foreach ($tree_data['relationships'] as $relationship) {
            $out[] = $relationship;
        }
        // remove dups (note: array_unique() not meant for multidim arrays)
        $out2 = array();
        foreach ($out as $d) {
            $out2[md5(serialize($d))] = $d;
        }
        return $out2;
    }

    /*
     This returns only technical dependencies (dep_table, dep_index) in the DB where invalid pointers would result
     if a record at table,index was deleted.  That is, it returns only tables that point to $tablename.
    */
    public function getRelationshipsDependentOnTable($tablename)
    {
        if (!isset($this->_rel_dep_on_table_array[$tablename])) {
            $out2 = array();
            foreach ($this->getRelationships() as $key => $relationship) {
                if ($relationship['table']==$tablename) {
                    $out2[$key] = $relationship;
                }
            }
            $this->_rel_dep_on_table_array[$tablename] = $out2;
        }
        return $this->_rel_dep_on_table_array[$tablename];
    }

    public function getRelationshipsTableIsDependentOn($tablename)
    {
        if (!isset($this->_rel_table_is_dep_on_array[$tablename])) {
            $out2 = array();
            foreach ($this->getRelationships() as $key => $relationship) {
                if ($relationship['dep_table']==$tablename) {
                    $out2[$key] = $relationship;
                }
            }
            $this->_rel_table_is_dep_on_array[$tablename] = $out2;
        }
        return $this->_rel_table_is_dep_on_array[$tablename];
    }

    public function getTreeData()
    {
        if (empty($this->_tree_data)) {
            $this->_tree_data['relationships'] = array();
            $this->_tree_data['tableparams'] = array();
            $this->walkTreeForRelationships($this->getRelationshipTree(), '', '', $this->_tree_data['relationships'], $this->_tree_data['tableparams']);
        }
        return $this->_tree_data;
    }

    /*
     returns the tree node parameters identified by $tablename and $parent_index.
     If $parent_index is not given or not found, then return the first node for the $tablename.
    */
    public function getTreeTableParams($tablename, $parent_index = null)
    {
        if (!isset($this->_tree_table_params[$tablename])) {
            $tree_data = $this->getTreeData();
            $this->_tree_table_params[$tablename] = array();
            foreach ($tree_data['tableparams'] as $tree_datum) {
                if ($tree_datum['table']==$tablename) {
                    $this->_tree_table_params[$tree_datum['table']][$tree_datum['parent_index']] = $tree_datum;
                }
            }
            if (empty($this->_tree_table_params[$tablename])) {
                $this->_tree_table_params[$tablename] = array(array()); // empty means we searched already and found nothing
            }
        }
        return empty($parent_index) ? reset($this->_tree_table_params[$tablename]) : (isset($this->_tree_table_params[$tablename][$parent_index]) ? $this->_tree_table_params[$tablename][$parent_index] : reset($this->_tree_table_params[$tablename]));
    }

    protected function walkTreeForRelationships($children, $parent_table_name, $parent_index_name, &$results_array, &$tableparams)
    {
        foreach ($children as $child) {
            if (isset($child['heading'])) {
                $this->walkTreeForRelationships($child['children'], '', '', $results_array, $tableparams);
            } else {
                $child['parent_table_in_tree'] = $parent_table_name; // this cannot be overridden
                if (!isset($child['index'])) {
                    $child['index'] = $this->getPrimaryIndexName($child['table']);
                }
                if (!isset($child['parent_index_in_parent'])) {
                    $child['parent_index_in_parent'] = $parent_index_name;
                }
                if (!isset($child['parent_index'])) {
                    $child['parent_index'] = $child['parent_index_in_parent'];
                }
                if (!isset($child['parent_table'])) {
                    $child['parent_table'] = $parent_table_name;
                }
                if (!isset($child['parent_calls_me'])) {
                    $child['parent_calls_me'] = array();
                }
                if (!isset($child['parent_calls_me']['singular'])) {
                    $child['parent_calls_me']['singular'] = ucwords($this->getNiceTableName($child['table']));
                }
                if (!isset($child['parent_calls_me']['plural'])) {
                    $child['parent_calls_me']['plural'] = ucwords($this->getNiceTableName($child['table']).'s');
                }

                if ($child['parent_table']!='') {
                    $results_array[] = array('table' => $child['parent_table'],
                                             'index' => $child['parent_index_in_parent'],
                                             'dep_table' => $child['table'],
                                             'dep_index' => $child['parent_index'],
                                             'type' => 'parent');
                }
                if (isset($child['linkto_table'])) {
                    if (!isset($child['linkto_index_in_parent'])) {
                        $child['linkto_index_in_parent'] = $this->getPrimaryIndexName($child['linkto_table']);
                    }
                    if (!isset($child['linkto_index'])) {
                        $child['linkto_index'] = $child['linkto_index_in_parent'];
                    }
                    if (!isset($child['linkto_calls_me'])) {
                        $child['linkto_calls_me'] = array();
                    }
                    if (!isset($child['linkto_calls_me']['singular'])) {
                        $child['linkto_calls_me']['singular'] = ucwords('Linked as '.$this->getNiceTableName($child['table']));
                    }
                    if (!isset($child['linkto_calls_me']['plural'])) {
                        $child['linkto_calls_me']['plural'] = ucwords('Links as '.$this->getNiceTableName($child['table']));
                    }
                    $results_array[] = array('table' => $child['linkto_table'],
                                             'index' => $child['linkto_index_in_parent'],
                                             'dep_table' => $child['table'],
                                             'dep_index' => $child['linkto_index'],
                                             'type' => 'link');
                    $this->_link_to_params[$tablename];
                }
                $tempparams = $child;
                unset($tempparams['children']);
                $tableparams[] = $tempparams;
                $this->walkTreeForRelationships($child['children'], $child['table'], $child['index'], $results_array, $tableparams);
            }
        }
    }

    function getFirstTextFieldName($tablename)
    {
        $records = $this->getFieldTypes($tablename);
        foreach ($records as $fieldname => $record) {
            if (str_contains($record['type'], 'text') || str_contains($record['type'], 'varchar')) {
                return $fieldname;
            }
        }
        return '';
    }

    public function getDefaultDescriptionField($tablename)
    {
        $fieldname = $this->getFirstTextFieldName($tablename);
        if ($fieldname) {
            return $fieldname;
        } else {
            return $this->getPrimaryIndexName($tablename);
        }
    }

    public function getNiceTableName($tablename)
    {
        // will appear as Edit {tablename}, or New {tablename}
        return isset($this->_dictionary['tables'][$tablename]['caption']) ? $this->_dictionary['tables'][$tablename]['caption'] : ucwords($tablename);
    }

    private function handle_mysql_error($source_text)
    {
        $err = mysqli_errno(self::$_db_link);
        $msg = mysqli_error(self::$_db_link);
        throw new Exception("Mysql Error #{$err}, {$msg} in $source_text");
    }

    private function logQuery($query)
    {
        if (Zend_Registry::get('config')->db_query_dump_file) {
            file_put_contents(Zend_Registry::get('config')->db_query_dump_file, $query.";\r\n\r\n\r\n", FILE_APPEND);
        }
    }

    public function getRecords($idfieldname, $query)
    {
 // function will throw exception on error
        $out = array();
        $this->logQuery($query);
        $result = mysqli_query(self::$_db_link, $query);
        if ($result) {
            $num_results = mysqli_num_rows($result);
            for ($i=0; $i < $num_results; $i++) {
                $row = mysqli_fetch_assoc($result);
                if ($idfieldname=='') {
                    $out[] = $row;
                } else {
                    $out[$row[$idfieldname]] = $row;
                }
            }
            //mysqli_free_result($result);
        } else {
            $this->handle_mysql_error("DbSchema::getRecords($idfieldname,$query)");
        }
        return $out;
    }

    public function mysqlQuery($query)
    {
        $this->logQuery($query);
        $result = @mysqli_query(self::$_db_link, $query);
        if (!$result) {
            $this->handle_mysql_error("DbSchema::mysqlQuery($query)");
        }
        return mysqli_affected_rows(self::$_db_link);
    }

    public function nonPrimaryFieldNames($tablename)
    {
        if (!isset($this->_nonprimaryfieldnames[$tablename])) {
            $this->_nonprimaryfieldnames[$tablename] = array_diff(array_keys($this->getFieldTypes($tablename)), array( $this->getPrimaryIndexName($tablename)));
        }
        return $this->_nonprimaryfieldnames[$tablename];
    }

    public function isNotNullType($tablename, $fieldname)
    {
        $type = $this->getFieldType($tablename, $fieldname);
        return (!$type['dbtype']['Null'] || $type['dbtype']['Null'] == 'NO');
    }

    public function varToEscapedMysqlLiteral($tablename, $field, $value, $fieldtype = null)
    {
        $slashes_var = is_string($value) ? addslashes($value) : $value;
        if ($fieldtype==null) {
            $fieldtype = $this->getFieldType($tablename, $field);
        }
        $dbtype = $fieldtype['dbtype'];
        $type_parse = preg_split('/[()]/', $dbtype['Type']);
        $is_null_str = (($slashes_var==='') || is_null($slashes_var));
        if ($dbtype['Type'] == 'datetime') {
            $quoted_lit = !$is_null_str ? "'".time_to_mysqldatetime(strtotime($slashes_var))."'" : "DEFAULT";
        } else if ($dbtype['Type'] == 'date') {
            $quoted_lit = !$is_null_str ? "'".time_to_mysqldate(strtotime($slashes_var))."'" : "DEFAULT";
        } else if (($type_parse[0]=='int')) { // some numeric format, make sure it is set to zero
            $quoted_lit = !$is_null_str ? "'".round($slashes_var)."'" : ($this->isNotNullType($tablename, $field) ? "'0'" : "NULL");
        } else if (in_array($type_parse[0], array('float','calculated'))) { // some numeric format, make sure it is set to zero
            $quoted_lit = !$is_null_str ? "'".$slashes_var."'" : ($this->isNotNullType($tablename, $field) ? "'0'" : "NULL");
        } else if ($field=='passwd') {
            $quoted_lit = "password('".$slashes_var."')";
        } else if (is_bool($slashes_var)) {
            $quoted_lit = "'".((int) $slashes_var)."'";
        } else if (is_array($slashes_var)) {
            $quoted_lit = "'".serialize($slashes_var)."'";
        } else {
            $quoted_lit = "'".trim($slashes_var)."'";
        }
        return $quoted_lit;
    }

    public function saveRecord($tablename, &$fields, $fieldnames = array(), $idfieldname = '', $handle_err_dups_too = true)
    {
 // function will raise an exception if an error occurs
        if (!is_array($fieldnames)) {
            throw new Exception('no fields to save in DbSchema::saveRecord()');
        }
        if (count($fieldnames)==0) {
            $fieldnames = $this->nonPrimaryFieldNames($tablename);
        }
        if (''==$idfieldname) {
            $idfieldname = $this->getPrimaryIndexName($tablename);
        }

        if ($fields[$idfieldname] == 'new') {
            // write new
            $valarray = array();
            foreach ($fieldnames as $field) {
                $valarray[] = $this->varToEscapedMysqlLiteral($tablename, $field, $fields[$field]);
            }

            $query = "insert into {$tablename} (".implode(',', $fieldnames).") values (".implode(',', $valarray).")";
            $this->logQuery($query);
            $result = mysqli_query(self::$_db_link, $query);
            // if an insert fails, that probably means this email is already present, so just update instead

            if (!$result) {
                if (mysqli_errno(self::$_db_link)!=1062) {
                    $this->handle_mysql_error("DbSchema::saveRecord()-1");
                } else if ($handle_err_dups_too) {
                    $this->handle_mysql_error("DbSchema::saveRecord()-2");
                }
            } else {
                $fields[$idfieldname] = mysqli_insert_id(self::$_db_link);
            }
        } else {
            $queryarray = array();
            foreach ($fieldnames as $field) {
                $queryarray[] = $field."=".$this->varToEscapedMysqlLiteral($tablename, $field, $fields[$field]);
            }
            $query = "update {$tablename} set ".implode(',', $queryarray)." where {$idfieldname}='".$fields[$idfieldname]."'";
            $this->logQuery($query);
            $result = mysqli_query(self::$_db_link, $query);
            if (!$result) {
                if (mysqli_errno(self::$_db_link)!=1062) {
                    $this->handle_mysql_error("DbSchema::saveRecord()-3");
                } else if ($handle_err_dups_too) {
                    $this->handle_mysql_error("DbSchema::saveRecord()-4");
                }
            }
        }
    }

    public function deleteRecord($tablename, $idfieldvalue, $idfieldname, $limit_clause = 'LIMIT 1')
    {
        $query = "delete from {$tablename} where {$idfieldname}='{$idfieldvalue}' {$limit_clause}";
        $this->logQuery($query);
        $result = mysqli_query(self::$_db_link, $query);
        if (!$result) {
            $this->handle_mysql_error("DbSchema::deleteRecord($tablename,$idfieldvalue,$idfieldname)");
        }
    }


    public function getTableNamesWithNonPrimaryField($fieldname)
    {
        $out = array();
        foreach ($this->getTableNames() as $tablename) {
            foreach ($this->nonPrimaryFieldNames($tablename) as $testfield) {
                if ($testfield == $fieldname) {
                        $out[] = $tablename;
                        break;
                }
            }
        }
        return $out;
    }


}


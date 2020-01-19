<?php
/**
 *
 * PartForge Enterprise Groupware for recording parts and assemblies by serial number and version along with associated test data and comments.
 *
 * Copyright (C) 2013-2020 Randall C. Black <randy@blacksdesign.com>
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
    class DBTableRow extends TableRow {
        
        protected $_table = null;
        protected $_idField = null;
        protected $_dbschema = null;
        protected $_join_fields_and_tables = null;
        protected $_fieldtypes_from_joined_table = array();
        protected $_ignore_joins = false;
        protected $_join_options = array();
        protected $_tree_table_params = null; // holds tree table params for this table (see fielddictionary.php)
        protected $_fieldlayout = array();

        public function __construct($table, $ignore_joins=false, $parent_index=null)
        {
            parent::__construct();
            $this->_join_fields_and_tables = null;
            $this->_fieldtypes_from_joined_table = array();
            $this->_join_options = array();
            $this->_fieldlayout = array();
            
            $this->_dbschema = DbSchema::getInstance();
            $this->_table = $table;
            $this->_ignore_joins = $ignore_joins;
            $this->setFieldTypes($this->getFieldTypesFromDb());
            $this->setFieldTypes($this->getFieldTypesFromJoinedTables());
            $this->mapJoinsToFieldTypes();
            
            $this->_tree_table_params = $this->_dbschema->getTreeTableParams($table, $parent_index);
            
            $this->mapTreeParamsToFieldTypes();
            $this->_idField = $this->_dbschema->getPrimaryIndexName($this->_table);
            $this->initDataFromJoinedTables();
            // assign default values
            $this->assignDefaults();
        }
        
        /*
         * The input is assumed to be query variables from an initialize[] array.
         * This would be a typical step when initializing the tablerow object
         * for a new (unsaved) record.  Override this to do advanced initialization
         * which depends onthe specific values passed in.
         */
        public function processPostedInitializeVars($initialize_array) {
        	foreach($initialize_array as $field => $value) {
        		// make sure we don't do something stupid and end up overwriting some other record accidentally
        		if (($field!=$this->getIndexName()) || ($this->getIndexValue()=='new')) {
        			$this->{$field} = $value;
        		}
        	}        	
        }
        
        public function getSortOrder() {
            return $this->_dbschema->getSortOrder($this->getTableName());
        }
        
        public function hasDedicatedSortOrderField() {
            return ($this->getFieldAttribute($this->getSortOrder(),'type')=='sort_order');
        }
        
        public function mapJoinsToFieldTypes() {
            if (!$this->_ignore_joins) {
                foreach($this->getJoinFieldsAndTables() as $join_name => $target) {
                    $type = $this->getFieldType($target['lhs_index']);
                    if (('outgoing'==$target['type']) && !empty($type)) {
                        $this->setFieldAttribute($target['lhs_index'],'type','left_join');
                        $this->setFieldAttribute($target['lhs_index'],'join_name',$join_name);
                    }
                }
            }
        }
        
        public function mapTreeParamsToFieldTypes() {
            if (isset($this->_tree_table_params['linkto_table'])) {
                $this->setFieldAttribute($this->_tree_table_params['linkto_index'],'type','enum');
                $this->setFieldAttribute($this->_tree_table_params['linkto_index'],'options','getParentLinkItems');
            }
        }
        
        public function getParentPointerIndexName() {
            return isset($this->_tree_table_params['parent_index']) ? $this->_tree_table_params['parent_index'] : '';
        }
        
        public function getTreeParams() {
            return $this->_tree_table_params;
        }
        
        public function getParentRecord() {
            if (!empty($this->_tree_table_params['parent_index'])) {
                $ParentRow = $this->_dbschema->dbTableRowObjectFactory($this->_tree_table_params['parent_table'],false,$this->_tree_table_params['parent_index']);
                $ParentRow->getRecordById($this->{$this->_tree_table_params['parent_index']});
                return $ParentRow;
            } else {
                return null;
            }
        }
        
        /*
          If there is a parent record, get its description
        */
        
        public function getParentCoreDescription() {
            $ParentRow = $this->getParentRecord();
            if ($ParentRow!=null) {
                return $ParentRow->getCoreDescription();
            } else {
                return '';
            }
        }
        
        /*
          this returns a generic list of linked items for display in a list box when this table
          has a linkto_table entry in the dbtree
        */
        public function getParentLinkItems() {
            if (!isset($this->_tree_table_params['linkto_table'])) return array();
            if (!isset($this->_tree_table_params['linkto_items_array'])) {
                $Records = new DBRecords($this->_dbschema->dbTableRowObjectFactory($this->_tree_table_params['linkto_table']),'','');
                $Records->getRecordsById('');
                $pfield = $this->_tree_table_params['linkto_index_in_parent'];
                $this->_tree_table_params['linkto_items_array'] = array();
                foreach($Records->keys() as $key) {
                    $Obj = $Records->getRowObject($key);
                    if (isset($this->_tree_table_params['linkto_desc_field'])) {
                        $text = $Obj->{$this->_tree_table_params['linkto_desc_field']};
                    } else {
                        $text = $Obj->getCoreDescription();      //TODO: should this have context parameters??
                    }
                    $this->_tree_table_params['linkto_items_array'][$Obj->{$pfield}] = $text;
                }
            }
            return $this->_tree_table_params['linkto_items_array'];
        }
        
        /*
         This description includes only core data in this table, not data from related tables.  Override to
         change the core description of the data. 
        */
        
        public function getCoreDescription() {
            if (isset($this->_tree_table_params['linkto_index'])) { // simple link with no core fields
                return ''; // a link usually has no core information
            } elseif (isset($this->_tree_table_params['desc_func']) && method_exists($this,$this->_tree_table_params['desc_func'])) {
                $desc_func = $this->_tree_table_params['desc_func'];
                return $this->$desc_func();
            } else {
                return $this->{$this->getDescriptionFieldName()};
            }            
        }
        
        /*
         Returns parameters to link to this record.  If this is joined to a parent record in the heirarchy.
        */
        public function getLinkParamsToSelf() {
            return array('table' => $this->getTableName(), 'index' => $this->getIndexName(), 'index_value' => $this->getIndexValue());
        }
        
        /*
          This sets a session variable that uniquely associates this record with a timestamp.
          Use with getTouchedRecently() to judge if this record was recently touched.
        */
        public function startSelfTouchedTimer() {
            self::startATouchedTimer($this->getTableName(), $this->getIndexValue());
        }
        
        static function startATouchedTimer($scope_key, $index_value) {
        	$_SESSION['dbtablerow_timer'][$scope_key] = array('index_value' => $index_value, 'time' => script_time());
        }
        
        static function wasItemTouchedRecently($scope_key, $index_value, $max_time=null) {
        	if (empty($max_time)) $max_time = 60;
        	if (isset($_SESSION['dbtablerow_timer'][$scope_key])) {
        		$arr = $_SESSION['dbtablerow_timer'][$scope_key];
        		if (($index_value==$arr['index_value'])) {
        			return script_time() - $arr['time'] < $max_time;
        		}
        	}
        	return false;
        }
        
        public function getTouchedRecently($max_time=null, $index_value=null, $tablename=null) {
            if (empty($tablename)) $tablename = $this->getTableName();
            if (empty($index_value)) $index_value = $this->getIndexValue();
            return self::wasItemTouchedRecently($tablename, $index_value, $max_time);
        }
        
        /*
          get list of possible actions
        */
        public function getListOfDetailActions() {
            $config = Zend_Registry::get('config');
            $actions = array();
            if (Zend_Registry::get('customAcl')->isAllowed($_SESSION['account']->getRole(),'table:'.$this->getTableName(),'edit')) {
            	$actions['editview'] = array('buttonname' => 'Edit/View', 'privilege' => 'view');
            } else {
            	$actions['editview'] = array('buttonname' => 'View', 'privilege' => 'view');
            }
            	
            if (!isset($this->_fieldtypes['record_created']) || empty($this->record_created)
                 || (strtotime($this->record_created) + $config->delete_grace_in_sec > script_time()) || AdminSettings::getInstance()->delete_override) {
                $actions['delete'] = array('buttonname' => 'Delete', 'privilege' => 'delete', 'confirm' => 'Are you sure you want to delete this?');
            } else {
                $actions['delete'] = array('buttonname' => 'Delete (Blocked)', 'privilege' => 'delete', 'alert' => 'This record is older than '.(integer)($config->delete_grace_in_sec/3600).' hours.  If you want to delete it, you must go to the Settings menu and enable Delete Override.');
            }
            return $actions;
        }
        
        /*
         returns descriptions of components of the item description broken into description, table, index, index_value
         The indexes for the return array are of the form parenttable__pointerfield.
        */
        public function getShortDescriptionAsArray($context_parent_table=null,$context_parent_index=null) {
            $relationships = $this->_dbschema->getRelationshipsTableIsDependentOn($this->getTableName());
            
            $out = array();
            $out[''] = array('desc_html' => TextToHtml($this->getCoreDescription()), 'link_params' => $this->getLinkParamsToSelf());
            foreach($relationships as $relationship) {
                $TableRow = $this->_dbschema->dbTableRowObjectFactory($relationship['table']);
                $index_value = $this->{$relationship['dep_index']};
                if (($relationship['index']==$TableRow->getIndexName()) && is_numeric($index_value) && $TableRow->getRecordById($index_value)) {
                    $desc_prefix = $this->getFieldAttribute($relationship['dep_index'],'caption');
                    $out[$relationship['table'].'__'.$relationship['dep_index']] = array('desc_prefix' => $desc_prefix, 'desc_html' => TextToHtml($TableRow->getCoreDescription()), 'link_params' => $TableRow->getLinkParamsToSelf(), 'DBTableRow' => $TableRow);
                }
            }
            // don't include part of description that describes the current context since that is redundent
            if (!empty($context_parent_table) && !empty($context_parent_index)) {
                unset($out[$context_parent_table.'__'.$context_parent_index]);
            }
            
            return $out;
        }
        
        /*
          This formats a description line from the array version of description
          $desc_arr is like return array from getShortDescriptionAsArray().
        */
        public function descriptionArrayToHtml($desc_arr) {
            $desc_chunks = array();
            if (!empty($desc_arr['']['desc_html'])) {
                $desc_chunks[] = $desc_arr['']['desc_html'];
            }
            unset($desc_arr['']);

            if (count($desc_arr)>0) {
                $link_desc_arr = array();
                foreach($desc_arr as $key => $desc) {
                    $desc_chunks[] = $desc['desc_html'];
                }
            }
            return implode(', ',$desc_chunks);
        }

        /*
         This gets a description of the record that is context dependent.
         The $context_table_and_dep_index is the name of the table record from which the description is being requested
         concatinated with the dep_index name in the current table that points to the parent.
         The description returned need not include information from that table since it is redundant for
         the viewer.  
        */
        public function getShortDescriptionHtml($context_parent_table=null,$context_parent_index=null) {
            $desc_arr = $this->getShortDescriptionAsArray($context_parent_table,$context_parent_index);
            return $this->descriptionArrayToHtml($desc_arr);
        }
        
        public function getDescriptionFieldName() {
            return isset($this->_tree_table_params['desc_field']) ? $this->_tree_table_params['desc_field'] : $this->_dbschema->getDefaultDescriptionField($this->_table);
        }
        
        /*
         unlike the getRelationshipsDependentOnTable(), this also looks at dependent records of incoming joins
         but it does not look at joins.
        */
        public function getActiveParentAndLinkRelationships() {
            
            $relationships = $this->_dbschema->getRelationshipsDependentOnTable($this->getTableName());
            foreach($this->getJoinFieldsAndTables() as $join_name => $target) {
                if ($this->joinFieldsAreActive($join_name)) {
                    $relationships += $this->_dbschema->getRelationshipsDependentOnTable($target['rhs_table']);
                }
            }
            
            // do not include relationships that are active joins since they are one-to-one.
            $active_joins = $this->getActiveJoins();
            foreach($relationships as $index => $relationship) {
                foreach($active_joins as $join_name => $target) {
                    if (($target['rhs_table']==$relationship['dep_table']) && ($target['rhs_index']==$relationship['dep_index'])) {
                        unset($relationships[$index]);
                    }
                }
            }
            return $relationships;
        }
        
        /*
            crude reordering method.  Use as utility when overriding getDependentRecordsCollection()
            to change the order.  if reorderDependentRecordsCollection($dependents,array('rubricdimension','completedrubric'))
            called from with the rubricquestionnaire would make sure that the dependent list of rubricdimension
            was first in the list and completedrubric was second.  If $pad_remainder_at_top and the $dep_tables list does
            not include all the dependents, then pad put the non-specified items at the top when done.
        */
        public function reorderDependentRecordsCollection($dependents,$dep_tables,$pad_remainder_at_top=false) {
            $new_array = array();
            foreach($dep_tables as $dep_table) {
                foreach($dependents as $key => $dependent) {
                    if ($dep_table==$dependent['relationship']['dep_table']) {
                        unset($dependents[$key]);
                        $new_array[$key] = $dependent;
                    }
                }
            }
            // now if there are any remaining source, copy remaining ones over at the end
            return $pad_remainder_at_top ? array_merge($dependents,$new_array) : array_merge($new_array,$dependents);
        }
        
        public function getDependentRecordsCollection() {
            $dependents = array();
            foreach($this->getActiveParentAndLinkRelationships() as $relationship) {
                $Records = new DBRecords($this->_dbschema->dbTableRowObjectFactory($relationship['dep_table'],false,$relationship['dep_index']), $relationship['dep_index'], '');
                $parent_index_value = $this->{$relationship['index']};
                if (!is_numeric($parent_index_value)) {
                    $parent_index_value = '$'.$relationship['index']; // use the field name instead of index value.  We might be able to do something useful with it later
                } else {
                    $Records->getRecordsById($parent_index_value);
                }
                $dependents[] = array('relationship' => $relationship, 'DBRecords' => $Records, 'parent_index_value' => $parent_index_value);
            }
            
            return $this->removeDuplicateDependents($dependents);
        }
        
        /*
          Only want to return one of each relationship and a parent type should take precidence over other types.
          remove duplicates of array in table,index,dep_index,dep_table  and give priority to type=parent
        */
        protected function removeDuplicateDependents($dependents) {
            $out = array();
            foreach($dependents as $dependent) {
                $arr = $dependent['relationship'];
                $unique_part = array('table' => $arr['table'], 'index' => $arr['index'], 'dep_table' => $arr['dep_table'], 'dep_index' => $arr['dep_index']);
                $key = md5(serialize($unique_part));
                if (!isset($out[$key]) || (isset($dependent['relationship']['type']) && ($dependent['relationship']['type']=='parent'))) {
                    $out[$key] = $dependent;
                }
            }
            return $out;
        }
        
        public function getDependentRecordsBeforeDelete(DbTableRow $DbTableRowContext) { // the $DbTableRowContext is the record from witch this deletion is being requested
            $dependents = array();
            foreach($this->_dbschema->getRelationshipsDependentOnTable($this->getTableName()) as $relationship) {
                $Records = new DBRecords($this->_dbschema->dbTableRowObjectFactory($relationship['dep_table'],false,$relationship['dep_index']), $relationship['dep_index'], '');
                $Records->getRecordsById($DbTableRowContext->{$relationship['index']});
                if (($relationship['dep_table']==$DbTableRowContext->getTableName())) {
                    foreach($Records->keys() as $key) {
                        // exclude the self record.
                        if (($Records->getRowObject($key)->getIndexValue()==$DbTableRowContext->getIndexValue())) {
                            $Records->unsetItem($key);
                        }
                    }
                }
                if (count($Records->keys())>0) {
                    $dependents[] = array('relationship' => $relationship, 'DBRecords' => $Records, 'parent_index_value' => $this->{$relationship['index']});
                }
            }
            return $this->removeDuplicateDependents($dependents);
        }
        
        public function initDataFromJoinedTables() {
            $joins = $this->getJoinFieldsAndTables();
            foreach($joins as $join_name => $target) {
                foreach($target['rhs_dbtableobj']->getFieldNames() as $fieldname) {
                    $this->_fields["{$target['field_prefix']}__{$fieldname}"] = $target['rhs_dbtableobj']->{$fieldname};
                }
 //               $this->_fields[$target['lhs_index']] = $target['rhs_dbtableobj']->getIndexValue(); // wrong.  Not sure why I put this here
            }
        }
        
        public function getJoinFieldsAndTables() {
            if ($this->_ignore_joins) {
                return array();
            } elseif (!is_array($this->_join_fields_and_tables)) {
                $this->_join_fields_and_tables = $this->_dbschema->getJoinFieldsAndTables($this->_table);
            }
            return $this->_join_fields_and_tables;
        }
        
        public function assignDefaults() {
            foreach($this->getFieldTypes() as $fieldname => $fieldtype) {
                    switch(true) {
                        case isset($fieldtype['default']):
                            $this->_fields[$fieldname] = $fieldtype['default'];
                            break;
                        case ($fieldname=='record_created'):
                            $this->_fields[$fieldname] = time_to_mysqldatetime(script_time());
                            break;
                        case ($fieldtype['type']=='sort_order'):
                            $this->_fields[$fieldname] = 1000000; // hopefully forces sort order to end by default
                            break;
                        case ($fieldtype['type']=='id'):
                            $this->_fields[$fieldname] = 'new';
                            break;
                        case (true):
                            $this->_fields[$fieldname] = null;
                    }
            }
        }
        
        public function assignFromFormSubmission($in_params,&$merge_params) {
            if (isset($in_params['form']) && isset($in_params[$this->getIndexName()]) && ($merge_params[$this->getIndexName()]!=$in_params[$this->getIndexName()])) {
                return false;
            }
            return parent::assignFromFormSubmission($in_params,$merge_params);
        }
        
        
        public function fetchHiddenTableAndIndexFormTags() {
            return '<INPUT TYPE="hidden" NAME="'.$this->getIndexName().'" VALUE="'.$this->getIndexValue().'">
                    <INPUT TYPE="hidden" NAME="table" VALUE="'.$this->getTableName().'">
                    ';
        }
        
        protected function getFieldTypesFromDb() {
            return $this->_dbschema->getFieldTypes($this->_table);
        }
        
        public function getFieldTypesFromJoinedTables($join_mode=null) {
            $out = array();
            foreach($this->getJoinFieldsAndTables() as $join_name => $target) {
                if (($join_mode==null) || ($join_mode==$target['mode'])) {
                    $out = array_merge($out,$this->getFieldTypesFromJoinedTable($join_name));
                }
            }
            return $out;
        }
        
        public function getFieldTypesFromJoinedTable($join_name) {
            if ($this->_ignore_joins) {
                return array();
            } elseif (!isset($this->_fieldtypes_from_joined_table[$join_name])) {
                $this->_fieldtypes_from_joined_table[$join_name] = array();
                $fields_tables = $this->getJoinFieldsAndTables();
                $target = $fields_tables[$join_name];
                foreach($target['rhs_dbtableobj']->getFieldTypes() as $fieldname => $fieldtype) {
                    if (isset($fieldtype['options']) && !is_array($fieldtype['options'])) {
                        // $fieldtype['options'] is a method name and dot notation indicates where it is
                        $fieldtype['options'] = $join_name.'.'.$fieldtype['options'];
                    }
                    $this->_fieldtypes_from_joined_table[$join_name][$fieldname] = $fieldtype;
                }
                $this->_fieldtypes_from_joined_table[$join_name] = prefix_array_keys($this->_fieldtypes_from_joined_table[$join_name],"{$target['field_prefix']}__");
            }
            return $this->_fieldtypes_from_joined_table[$join_name];            
        }
        
        public function getFieldNamesFromJoinedTables($join_mode=null) {
            return array_keys($this->getFieldTypesFromJoinedTables($join_mode));
        }

        public function getFieldNamesFromJoinedTable($join_name) {
            return array_keys($this->getFieldTypesFromJoinedTable($join_name)); 
        }
        
        
        
        // do any preprocessing after reading table row.  For example, unserialize objects
        protected function onAfterGetRecord(&$record_vars) {
            return true;
        }
        
        protected function onAfterSaveRecord() {
            return true;
        }
        
        public function getRecord($query) {
            $records = $this->_dbschema->getRecords($this->_idField,$query);
            if (count($records)>0) {
                $record = reset($records);
                if ($this->onAfterGetRecord($record)) {
                    $this->assign($record);
                    return true;
                } else {
                    return false;
                }
            } else {
                return false;
            }
        }
        
        public function getRecordById($id) {
            $DBTableRowQuery = new DBTableRowQuery($this);
            $DBTableRowQuery->setLimitClause('LIMIT 1')->addSelectors(array($this->_idField => $id));
            return $this->getRecord($DBTableRowQuery->getQuery());
        }
        
        public function reload() {
            $this->getRecordById($this->_fields[$this->_idField]);
        }

        public function getRecordWhere($where) {
            return $this->getRecord("select * from {$this->_table} where ({$where}) LIMIT 1");
        }
        
        public function saveJoinedTableFields($fieldnames,$join_type) {
            $joins = $this->getJoinFieldsAndTables();
            foreach($joins as $join_name => $target) {
                if (($target['type']==$join_type) && ('RW'==$target['mode'])) {
                    $join_save_fieldnames = array();

                    /*
                      for incoming links, the main record has already been saved, so map the current value of the lhs_index
                      into the join tables pointer (unless it is inadvertently its primary index).  But only do this if
                      if we are really saving something in this joined table otherwise it makes it look like the joined
                      table should be editable.
                    */
                    
                    if (('incoming'==$target['type']) && ($target['rhs_index']!=$target['rhs_dbtableobj']->getIndexName())) {
                        $will_save = false;
                        foreach($target['rhs_dbtableobj']->getFieldNames() as $fieldname) {
                            if (in_array("{$target['field_prefix']}__{$fieldname}",$fieldnames)) {
                                $will_save = true;
                                break;
                            }
                        }
                        
                        if ($will_save) {
                            $this->_fields["{$target['field_prefix']}__{$target['rhs_index']}"] = $this->{$target['lhs_index']};
                        }
                    }
                    
                    foreach($target['rhs_dbtableobj']->getFieldNames() as $fieldname) {
                        if (in_array("{$target['field_prefix']}__{$fieldname}",$fieldnames)) {
                            $join_save_fieldnames[] = $fieldname;
                            $target['rhs_dbtableobj']->{$fieldname} = $this->_fields["{$target['field_prefix']}__{$fieldname}"];
                        }
                    }
                    
                    if (!empty($join_save_fieldnames)) {
                        $target['rhs_dbtableobj']->save($join_save_fieldnames);
                        // map back the recently added index value into prefixed fields
                        $this->_fields["{$target['field_prefix']}__{$target['rhs_index']}"] = $target['rhs_dbtableobj']->{$target['rhs_index']};
                        $this->_fields["{$target['field_prefix']}__{$target['rhs_dbtableobj']->getIndexName()}"] = $target['rhs_dbtableobj']->getIndexValue();
                        if (('outgoing'==$target['type']) && ($target['lhs_index']!=$this->getIndexName())) {
                            $this->_fields[$target['lhs_index']] = $target['rhs_dbtableobj']->{$target['rhs_index']};
                        }
                    }
                }
            }
        }
        
        /*
          Read in all records with the same parent point index, then renumber the sort order field and save out.
        */
        public function renumberAndSaveSortOrderFields() {
            $PeerRecords = new DBRecords(DbSchema::getInstance()->dbTableRowObjectFactory($this->getTableName(),false,$this->getParentPointerIndexName()), $this->getParentPointerIndexName(), '');
            $PeerRecords->getRecordsById($this->{$this->getParentPointerIndexName()},$this->getSortOrder());
            $i = 0;
            foreach($PeerRecords->keys() as $key) {
                $i = $i + 10;
                $RowObj = $PeerRecords->getRowObject($key);
                $RowObj->{$this->getSortOrder()} = $i;
                $RowObj->save(array($this->getSortOrder()));
                if ($key==$this->getIndexValue()) {
                    $this->{$this->getSortOrder()} = $RowObj->{$this->getSortOrder()};
                }
            }
        }
        
        public function save($fieldnames=array(),$handle_err_dups_too=true) { // function will raise an exception if an error occurs
            if (!is_array($fieldnames)) {
                throw new Exception('no fields to save in DBTableRow->save()');
            }
            if (count($fieldnames)==0) {
                $fieldnames = $this->nonPrimaryFieldNames();
            }
            $this->verifyFieldsExist($fieldnames);
            
            $this->saveJoinedTableFields($fieldnames,'outgoing'); // save records with outging navigation first so we get the right ID number
            
            // save only those fields in the current list that are not from joins
            $this->_dbschema->saveRecord($this->_table,$this->_fields,array_diff($fieldnames,$this->getFieldNamesFromJoinedTables()),$this->_idField,$handle_err_dups_too);
            
            $this->saveJoinedTableFields($fieldnames,'incoming'); // save records with incoming navigation after so we can give them our primary index value
            
            if (!$this->onAfterSaveRecord()) {
                throw new Exception('::onAfterSaveRecord() returned false in DBTableRow::save()');
            }
        }
        
        /*
            Used in the UI to decide if save needs to be called.
        */
        public function hasChanged($fieldnames=array()) {
            if (count($fieldnames)==0) {
                $fieldnames = $this->nonPrimaryFieldNames();
            }
            $tablename = $this->getTableName();
            $SavedRow = $this->_dbschema->dbTableRowObjectFactory($tablename);
            $SavedRow->getRecordById($this->getIndexValue());
            $has_changed = false;
            foreach($fieldnames as $fieldname) {
                $fieldtype = $this->getFieldType($fieldname);
                $savedrow_lit = $this->_dbschema->varToEscapedMysqlLiteral($tablename,$fieldname, $SavedRow->{$fieldname},$fieldtype);
                $dbtable_lit = $this->_dbschema->varToEscapedMysqlLiteral($tablename,$fieldname,$this->{$fieldname},$fieldtype);
                if ($savedrow_lit!=$dbtable_lit) {
                    $has_changed = true;
                    break;
                }
            }
            return $has_changed;
        }
        
        public function delete() {
            $this->_dbschema->deleteRecord($this->_table,$this->_fields[$this->_idField],$this->_idField);
            $this->_fields = array($this->_idField => 'new');
        }
        
        /*
          This is called to attempt to delete an incoming join on this record.  Return
          parameters are used for syncronizing with controller.
        */
        public function deleteIncomingJoin($join_name) {
            $out = array('update_field_list' => array(), 'blocking_dependents' => array());
            $joins = $this->getJoinFieldsAndTables();
            $target = $joins[$join_name];
            if ($this->joinFieldsAreActive($join_name)) {
                $rhs_index = $target['field_prefix'].'__'.$target['rhs_index'];
                $rhs_primary_index = $target['field_prefix'].'__'.$target['rhs_dbtableobj']->getIndexName();
                if ($this->{$rhs_primary_index}=='new') {
                    // we haven't saved this join record yet, so deleting is easy...
                    $this->{$rhs_index} = null;
                    $out['update_field_list'][] = $rhs_index;
                } else {
                    $TempJoinRow = DbSchema::getInstance()->dbTableRowObjectFactory($target['rhs_table'],true);
                    if ($TempJoinRow->getRecordById($this->{$rhs_primary_index})) {
                        $dependents = $TempJoinRow->getDependentRecordsBeforeDelete($this);
                        $out['blocking_dependents'] = $dependents;
                        if (empty($dependents)) {
                            $TempJoinRow->delete();
                            $this->{$rhs_index} = null;
                            $out['update_field_list'][] = $rhs_index;
                        }
                    }
                }
            }
            return $out;
        }
        
        public function nonPrimaryFieldNames() {
        // return non-primary without any join fields included
            return $this->_dbschema->nonPrimaryFieldNames($this->_table);
        }
        
        /*
          get default fields that are to be edited in the editview.
          This includes basically nonPrimaryFieldNames from both the base and joined records
          unlike getSaveFieldNames(), this does not include the primary index or incoming join pointers
        */
        public function getEditFieldNames($join_names=null) {
            $joins = $this->getJoinFieldsAndTables();
            if ($join_names==null) {
                $join_names = array_merge(array(''),array_keys($joins));
            }
            $out = array();
            foreach($join_names as $join_name) {
                if (''==$join_name) {
                    $fieldtypes = $this->getFieldTypes();
                    foreach($this->nonPrimaryFieldNames() as $fieldname) {
                        if ((!isset($fieldtypes[$fieldname]['mode']) || ($fieldtypes[$fieldname]['mode']=='RW'))
                        	&& (!isset($fieldtypes[$fieldname]['exclude_on_layout']))) $out[] = $fieldname;
                    }
                } elseif (!$this->_ignore_joins) {
                    $target = $joins[$join_name];
                    
                    if (('RW'==$target['mode'])) {
                        $backpointer_field = $target['type']=='incoming' ? $target['field_prefix'].'__'.$target['rhs_index'] : '';
                        foreach($target['rhs_dbtableobj']->getEditFieldNames(array('')) as $fieldname) {
                            $out[] = $target['field_prefix'].'__'.$fieldname;
                        }
                        $out = array_diff($out,array($backpointer_field));
                    }                    
                }
            }
            return $out;
        }
        
        /*
          get default fields that are to be saved during a save operation.
          This includes both fields in this table and joined ones if appropriate.
          the list depends on join field setting and the type of join too.
        */
        public function getSaveFieldNames($join_names=null) {
            $joins = $this->getJoinFieldsAndTables();
            if ($join_names==null) {
                $join_names = array_merge(array(''),array_keys($joins));
            }
            $out = array();
            foreach($join_names as $join_name) {
                if (''==$join_name) {
                    $fieldtypes = $this->getFieldTypes();
                    foreach(array_merge($this->nonPrimaryFieldNames(),array($this->getIndexName())) as $fieldname) {
                        if (!isset($fieldtypes[$fieldname]['mode']) || ($fieldtypes[$fieldname]['mode']=='RW')) $out[] = $fieldname;
                    }
                    
                } else {
                    if ($this->joinFieldsAreActive($join_name)) {
                        $target = $joins[$join_name];
                        foreach($target['rhs_dbtableobj']->getSaveFieldNames(array('')) as $fieldname) {
                            $out[] = $target['field_prefix'].'__'.$fieldname;
                        }                        
                    }                    
                }
            }
            return $out;
        }
        
        /*
          not sure where else to put this.  call with editConstraint('addDependent','admin')
          Return false if, it is OK to do operation.  We normally use this to prevent editing
          after a certain amount of time has passed.
          operations include addDependent, save
        */
        public function isEditOperationBlocked($operation,$dep_table) {
            return false;
        }
        
        /*
          Get field layout for this table.  Use default list of fields if necessary.
          Also, remove any parent pointing field from the list.
        */
        function getEditViewFieldLayout($default_fieldnames,$parent_fields_to_remove, $layout_key=null) {
        	
        	if (!is_array($parent_fields_to_remove)) {
        		$parent_fields_to_remove = ($parent_fields_to_remove=='') ? array() : array($parent_fields_to_remove);
        	}
        	
            $layout_key = empty($layout_key) ? $this->getTableName() : $layout_key;
            if (empty($this->_fieldlayout)) {
                global $FIELDLAYOUT;
                include(dirname(__FILE__).'/fieldlayout.php');
                $this->_fieldlayout = $FIELDLAYOUT;
            }
            $fieldlayout_tbl = $this->_fieldlayout[$layout_key]['editview'];
            if (!is_array($fieldlayout_tbl)) {
                if (!empty($parent_fields_to_remove)) {
                        $default_fieldnames = array_diff($default_fieldnames,$parent_fields_to_remove);
                }
                $fieldlayout_tbl = array_chunk($default_fieldnames,2);
            } else {
                if (!empty($parent_fields_to_remove)) {
                    foreach($fieldlayout_tbl as $row_index => $row) {
                        foreach($row as $col_index => $col) {
                            $dbfield = is_array($col) ? (isset($col['dbfield']) ? $col['dbfield'] : '') : $col;
                            if (in_array($dbfield,$parent_fields_to_remove)) {
                                unset($fieldlayout_tbl[$row_index][$col_index]);
                                if (empty($fieldlayout_tbl[$row_index])) unset($fieldlayout_tbl[$row_index]);
//                                break 2;
                            }
                        }
                    }
                }
            }
            return $fieldlayout_tbl;
        }        

        public function joinFieldsAreActive($join_name) {
            $joins = $this->getJoinFieldsAndTables();
            $target = $joins[$join_name];
            return (('outgoing' == $target['type']) && !empty($this->{$target['lhs_index']}) && ('RW' == $target['mode']))
                    || (('incoming' == $target['type']) && !empty($this->{$target['field_prefix'].'__'.$target['rhs_index']}) && ('RW' == $target['mode']));
        }        
        
        public function getActiveJoins() {
            $out = array();
            foreach($this->getJoinFieldsAndTables() as $join_name => $target) {
                if ($this->joinFieldsAreActive($join_name)) { // there is a non-zero join table
                    $out[$join_name] = $target;
                }
            }
            return $out;
        }
        
        public function getAddableIncomingJoins() {
            $out = array();
            foreach($this->getJoinFieldsAndTables() as $join_name => $target) {
                if (!$this->joinFieldsAreActive($join_name) && ('incoming' == $target['type']) && ('RW' == $target['mode'])) { 
                    $out[$join_name] = $target;
                }
            }
            return $out;
        }

        public function getJoinOptions($join_name,$include_only_orphans) {
            if (!isset($this->_join_options[$join_name])) {
                $joins = $this->getJoinFieldsAndTables();
                $target = $joins[$join_name];
                $DbTableObj = DbSchema::getInstance()->dbTableRowObjectFactory($target['rhs_table'],true);
                $ChildRecords = new DBRecords($DbTableObj,'','');
                $self_index = $this->{$this->getIndexName()};
                $and_where = $include_only_orphans ? "AND (({$this->_table}.{$target['lhs_index']} IS NULL) OR ({$this->_table}.{$this->getIndexName()}='{$self_index}'))" : "";
                $ChildRecords->getRecords(
                    "SELECT {$target['rhs_table']}.* FROM {$target['rhs_table']}
                    LEFT JOIN {$this->_table} on {$this->_table}.{$target['lhs_index']}={$target['rhs_table']}.{$target['rhs_index']}
                    WHERE (1=1){$and_where}
                    ORDER BY {$ChildRecords->order_by}"
                    );
                $this->_join_options[$join_name] = array();
                foreach($ChildRecords->keys() as $index) {
                    $this->_join_options[$join_name][$index] = $ChildRecords->getRowObject($index)->getCoreDescription();
                }
            }
            return $this->_join_options[$join_name];
        }
        
        public function getJoinSelectOptions($fieldname) {
            $fieldtype = $this->getFieldType($fieldname);
            $fieldvalue = $this->{$fieldname};
            $joins = $this->getJoinFieldsAndTables();
            $target = $joins[$fieldtype['join_name']];
            $pretty_name = ucwords(str_replace('_',' ',$fieldtype['join_name']));
            $out = array();
            if (in_array('jo_add',$target['options'])) {
                $out['new'] = 'New '.$pretty_name;
            }
            if (in_array('jo_link',$target['options'])) {
                $out += $this->getJoinOptions($fieldtype['join_name'],in_array('jo_orphans_only',$target['options'])); // union
            }
            if (in_array('jo_detach',$target['options']) && !empty($fieldvalue)) {
                $out['detach'] = 'Detach this '.$pretty_name;
            }
            if (in_array('jo_delete',$target['options']) && is_numeric($fieldvalue)) {
                $out['delete'] = 'Delete this '.$pretty_name;
            }
            return $out;
        }

        public function getIndexName() {
            return $this->_idField;
        }
        
        public function getIndexValue() {
            return $this->{$this->_idField};
        }
        
        public function getTableName() {
            return $this->_table;
        }
        
        public function isSaved() {
            return ($this->_fields[$this->_idField] != 'new');
        }
        
    }


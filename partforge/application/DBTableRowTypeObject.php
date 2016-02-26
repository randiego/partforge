<?php
/**
 *
 * PartForge Enterprise Groupware for recording parts and assemblies by serial number and version along with associated test data and comments.
 *
 * Copyright (C) 2013-2015 Randall C. Black <randy@blacksdesign.com>
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

    class DBTableRowTypeObject extends DBTableRow {
        
        public function __construct($ignore_joins=false,$parent_index=null) {
            parent::__construct('typeobject',$ignore_joins,$parent_index);            
            
      //      $this->user_id = $_SESSION['account']->user_id;
        }
        
       
       	/*
       		  The data dictionary encodes data just like the fielddictionary.php file.
       	*/
        
        
       /*
         fieldtype values := {
            caption*
            subcaption
            type*         =  {text, varchar, boolean, datetime, date, enum, multiple, sort_order,
                                print_function, left_join, id, ft_other}  ft_other means there is custom code
            options       // for enum type, is an array of key value pairs, or the name of a method
            input_rows
            input_cols
            print_width
            print_function_name    // these function have parameter ($value, is_html)
            disabled
            onchange_js          // js to be put in OnChange handler
            onclick_js          // js to be put in OnClick handler
            join_name           // same as key in dictionary join array
            exclude_on_layout     // don't show on the default layout.  
            mode      R means that it is not saved or editable.  RW (default) means it is editable and saveable
         }
         * = required
         
         for example:
         $tr = new TableRow();
         $tr->setFieldType('item_color', array('type' => 'enum', 'options' => array('Red' => 'Red','Green' => 'Green','Blue' => 'Blue') )); 
        */
        

        /**
         * This returns an array of fieldnames that are NOT allowed to be used for user-defined components or data dictionary fields
         */
        static function getReservedFieldNames() {        
        	return array('action','controller','itemobject_id','itemversion_id','typeobject_id','typeversion_id',
        			'item_serial_number','cached_serial_number_value','record_locator','effective_date','disposition','user_id','record_created',
        			'dictionary_overrides','item_data','itemcomponent_id','belongs_to_itemversion_id','can_have_itemobject_id',
        			'component_name','hidden_components_array','hidden_properties_array','component_subfield','embedded_in_typeobject_id','partnumber_alias',
        			'preview_definition_flag');
        }
        
        /**
         * create cached version of the next serial number fields. 
         * @param int $typeobject_id specifty this if you only want to update the cached value for single typeobject.
         */
        static function updateCachedNextSerialNumberFields($typeobject_id=null) {
        	$where = is_numeric($typeobject_id) ?  " WHERE typeobject.typeobject_id='{$typeobject_id}'" : '';
        	$records = DbSchema::getInstance()->getRecords('',"
				SELECT typeversion.*
				FROM typeobject
				LEFT JOIN typeversion on typeversion.typeversion_id = typeobject.cached_current_typeversion_id
				LEFT JOIN typecategory on typecategory.typecategory_id = typeversion.typecategory_id {$where}");
        	
        	foreach($records as $record) {
        		$serial_number_format = extract_prefixed_keys($record, 'serial_number_');
        		$SerialNumber = SerialNumberType::typeFactory($serial_number_format);
        		$out = !$record['is_user_procedure'] && $SerialNumber->supportsGetNextNumber() ? $SerialNumber->getNextSerialNumber($record['typeversion_id']) : '';
        		DbSchema::getInstance()->mysqlQuery("UPDATE typeobject SET cached_next_serial_number='".addslashes($out)."' WHERE typeobject_id='".$record['typeobject_id']."'");
        	}        	
        }
        
        /**
         * create a cached version of the count of hidden fields.
         * @param int or null $typeobject_id which typeobject record should we update the field for
         */
        static function updateCachedHiddenFieldCount($typeobject_id=null) {
        	$where = is_numeric($typeobject_id) ?  " WHERE typeobject.typeobject_id='{$typeobject_id}'" : '';
        	$records = DbSchema::getInstance()->getRecords('',"
        			SELECT typeversion.*
        			FROM typeobject
        			LEFT JOIN typeversion on typeversion.typeversion_id = typeobject.cached_current_typeversion_id
        			LEFT JOIN typecategory on typecategory.typecategory_id = typeversion.typecategory_id {$where}");
        	foreach($records as $record) {
        		$TypeVersion = new DBTableRowTypeVersion();
        		if ($TypeVersion->getCurrentRecordByObjectId($record['typeobject_id'])) {
        			$fields = $TypeVersion->getHiddenFieldnames();
        			DbSchema::getInstance()->mysqlQuery("UPDATE typeobject SET cached_hidden_fields='".count($fields)."' WHERE typeobject_id='".$record['typeobject_id']."'");
        		}
        	}
        }
        
        /**
         * Returns a (possibly)nested array of fields that define the 
         * @param int $typeobject_id
         * @param date string $effective_date set to null if you want the latest version
         * @param int $max_depth 0 means use only first level.  null means do them all.
         * @param int $level used for recursion
         * @return array of array
         */
        static function getTypeObjectFullNestedArray($typeobject_id, &$errors,$effective_date=null, $max_depth=null,$level=0,$parents = array()) {
        	$out = array();
        	$TypeVersion = new DBTableRowTypeVersion();
        	if ($TypeVersion->getCurrentRecordByObjectId($typeobject_id,$effective_date)) {
        		$out = $TypeVersion->getExportDefinitionFields();
        		if (isset($out['components'])) {
	        		foreach($out['components'] as $fieldname => $def) {
	        			$expanded_components = array();
	        			foreach($out['components'][$fieldname]['can_have_typeobject_id'] as $can_have_typeobject_id) {
	        			
		        			if (in_array($can_have_typeobject_id,$parents)) {
		        				$errors[] = 'Self reference detected at typeobject IDs ['.implode(',',$parents).','.$typeobject_id.'].  '.$out['type_part_number'].' has itself as a component or subcomponent.';
		        			} else if (is_null($max_depth) || ($max_depth >$level)) {
					        	$new_parents = array_merge($parents, array($typeobject_id));
		        				$expanded_components[] = self::getTypeObjectFullNestedArray($can_have_typeobject_id,$errors,$effective_date,$max_depth,$level+1,$new_parents);
		        			} else {
		        				$expanded_components[] = $can_have_typeobject_id;
		        			}
		        			
	        			}
	        			
	        			$out['components'][$fieldname]['can_have_typeobject_id'] = $expanded_components;
	        		}
        		}
        	}
        	return $out;
        }
        
        static function getTypeObjectArrayOfLinkedProcedures($typeobject_id, $effective_date=null) {
        	$errors = array();
        	$out = array();
        	$TypeVersion = new DBTableRowTypeVersion();
        	if ($TypeVersion->getCurrentRecordByObjectId($typeobject_id,$effective_date)) {
        		$procedure_records = getTypesThatReferenceThisType($TypeVersion->typeversion_id,1);  // procedures only
        		foreach($procedure_records as $record) {
        			$out[] = self::getTypeObjectFullNestedArray($record['typeobject_id'], $errors, $effective_date, 0);
        		}
        	}
        	return $out;
        }
        
    }

<?php
/**
 *
 * PartForge Enterprise Groupware for recording parts and assemblies by serial number and version along with associated test data and comments.
 *
 * Copyright (C) 2013-2016 Randall C. Black <randy@blacksdesign.com>
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

    class DBTableRowTypeVersion extends DBTableRow {
        
        public function __construct($ignore_joins=false,$parent_index=null) {
            parent::__construct('typeversion',$ignore_joins,$parent_index);   
            $this->user_id = $_SESSION['account']->user_id;
            $this->versionstatus = 'D';
            $this->typeobject_id = 'new';
        }
        
        
        public function isCurrentVersion($typeversion_id=null) {
        	if (is_null($typeversion_id)) $typeversion_id = $this->typeversion_id;
        	// yuck.  There are some cases where we don't have the field to__cached_current_typeversion_id.
        	if (!isset($this->to__cached_current_typeversion_id)) {
        		$recs = DbSchema::getInstance()->getRecords('typeobject_id',"select * from typeobject where typeobject_id='{$this->typeobject_id}' LIMIT 1");
        		if (count($recs)==1) {
        			$rec = reset($recs);
        			$this->to__cached_current_typeversion_id = $rec['cached_current_typeversion_id'];
        		}
        	}
        	return $this->to__cached_current_typeversion_id==$typeversion_id;
        }
        
        public function isObsolete() {
        	return $this->to__typedisposition=='B';
        }

        // 4078-001 (Jan 14, 2001 13:03:00).  Note that this function is called with a bare minimum of fields defined.
        public function getCoreDescription() {
        	$newtag = $this->isCurrentVersion() ? ', Current' : (($this->versionstatus=='D') ? ', Draft' : (($this->versionstatus=='R') ? ', Review' : ''));
        	if (AdminSettings::getInstance()->use_any_typeversion_id) {
        		return self::formatPartNumberDescription($this->type_part_number).' ('.date('M j, Y G:i',strtotime($this->effective_date)).')'.$newtag;
        	} else {
        		return date('M j, Y G:i',strtotime($this->effective_date)).$newtag;
        	}
        }        
                
        // this will make sure the field cached_current_typeversion_id in the table is up to date.  It returns this value as well.
        static public function updateCachedCurrentTypeVersionId($typeobject_id) {
			$TypeObject = new DBTableRow('typeobject');
			if ($TypeObject->getRecordById($typeobject_id)) {
				// ideally we can assign an active version
	        	$recs = DbSchema::getInstance()->getRecords('',"select typeversion_id, effective_date from typeversion where (typeobject_id='{$typeobject_id}') and (versionstatus='A') order by effective_date desc, typeversion_id desc LIMIT 1");
	        	// if that doesn't work, then get a draft or review one.
	        	if (count($recs)==0) {
		        	$recs = DbSchema::getInstance()->getRecords('',"select typeversion_id, effective_date from typeversion where typeobject_id='{$typeobject_id}' order by effective_date desc, typeversion_id desc LIMIT 1");
	        	}
	        	if (count($recs)==1) {
		        	$rec = reset($recs);
		        	$TypeObject->cached_current_typeversion_id = $rec['typeversion_id'];
		        	$TypeObject->save(array('cached_current_typeversion_id'));
		        	return $TypeObject->cached_current_typeversion_id;
	        	}
			}
			return null;
        }
        
        /**
         * Returns a listing of the different types of fields that the user can enter into the dictionary definition.
         * It also contains the help text and the various options for each attribute for those field types.  Used mainly for
         * constructing the definition editor. 
         * @return array of array 
         */
        static public function typesListing() {
        	$default_prop = array('caption' => array('type' => 'string', 'help' => 'Normally the name of your field is presented by removing the underscores from the name field and capitalizing words.  If you want a different name presented to the user, enter it here.  HTML markup is allowed, including <a href="http://en.wikipedia.org/wiki/List_of_XML_and_HTML_character_entity_references" target="_blank">special character entities</a> like &amp;Omega; (&Omega;).  Not all HTML is correctly processed, so be sure to test your markup by inspecting the definition page and the PDF view after saving!'), 
			        			'subcaption' => array('type' => 'string', 'help' => 'This text goes under the caption field.  HTML is allowed here too.  See above.'), 
			        			'featured' => array('type' => 'pickone', 'values' => array('0','1'), 'help' => '1 = show this value in headline descriptions of this part or procedure.  By making a handful (say, 1 to 3) of your fields featured, you provide a nice at-a-glance summary of this part or procedure while sparing viewers the gory details.'));
        	
        	$out = array(
        		'varchar' => array('parameters' => array('len' => array('type' => 'string', 'help' => 'Maximum length that can be entered.  Leave blank for no limits.'), 'required' => array('type' => 'pickone', 'values' => array('0','1'), 'help' => '1 = warn user if they leave this field blank'), 'unique' => array('type' => 'pickone', 'values' => array('0','1'), 'help' => 'If true, when the user enters a non-blank value, then this value must be unique for all current instances of this part.  This will normally be used for alternate unique serial numbers.'), 'input_cols' => array('help' => 'the width of the input box.')), 'help' => 'This data type is used to represent a single line of text.'),
        		'text' => array('parameters' => array('required' => array('type' => 'pickone', 'values' => array('0','1'), 'help' => '1 = warn user if they leave this field blank'), 'input_cols' => array('help' => 'the width of the input box.'), 'input_rows' => array()), 'help' => 'This data type is used to represent a multiline block of text.'),
        		'enum' => array('parameters' => array('options' => array('type' => 'hashtable'), 'required' => array('type' => 'pickone', 'values' => array('0','1'), 'help' => '1 = warn user if they leave this field blank')), 'help' => 'This data type provides a drop-down selector box where you can select one item from the dropdown.  The options list contains the items the user can select from.  Each line represents one choice.  The line is in the form Value=Description.  The Value is what is stored in the database, the Description is what is presented to the user in the list.'),
        		'boolean' => array('parameters' => array('required' => array('type' => 'pickone', 'values' => array('0','1'), 'help' => '1 = warn user if they leave this field blank')), 'help' => 'This data type represents a boolean with a Yes, No radio button pair.  For new records, neither Yes or No is selected.'),
          		'date' => array('parameters' => array('required' => array('type' => 'pickone', 'values' => array('0','1'), 'help' => '1 = warn user if they leave this field blank')), 'help' => 'This data type provides a calender dropdown for entering a date.'),
        		'datetime' => array('parameters' => array('required' => array('type' => 'pickone', 'values' => array('0','1'), 'help' => '1 = warn user if they leave this field blank')), 'help' => 'This data type provides an enhanced calendar/time drop down for enter a full date/time expression.'),
        		'float' => array('parameters' => array('required' => array('type' => 'pickone', 'values' => array('0','1'), 'help' => '1 = warn user if they leave this field blank'), 'minimum' => array('type' => 'string', 'help' => 'Warn the user if they enter a value less than this.  Leave blank if no minimum.'), 'maximum' => array('type' => 'string', 'help' => 'Warn the user if they enter a value greater than this.  Leave blank if no maximum.'), 'units' => array('type' => 'string', 'help' => 'Unit of measure (example: %) that will appear in the subcaption.  See above for special characters.')), 'help' => 'This data type is used for entering a number.  If minimum or maximum fields are entered, the user is warned when values are out of range.  A user can still enter a value out of range, but they will be warned and a red message shown.  A subcaption is automatically generated that indicates the allowed input range and units.  '),
        		'component_subfield' => array('parameters' => array('component_name' => array('type' => 'string', 'help' => 'This is the component containing the subfield'),  'embedded_in_typeobject_id' => array('type' => 'string', 'help' => 'If the component is define allowing more than one type, you have to specify which type.'),  'component_subfield' => array('type' => 'string', 'help' => 'The name of the field as it appears within the component object.'),    'required' => array('type' => 'pickone', 'values' => array('0','1'), 'help' => '1 = warn user if they leave this field blank')), 'help' => 'If you have any components defined, this data type can be used to represent a field within one of the components.  This provides a convenient way for the user to edit the fields within one of the associated components as if it were part of this record.  Changes made to this field by the user will force a new version of the associated component.  You must enter the component name exactly as it appears in your list of compoents.  Similarly, the component_subfield must be an exact match of the fieldname in the dictionary of the component.'),
        	);
        	
        	// map in defaults with lower priority
        	foreach($out as $type => $def) {
        		$out[$type]['parameters'] = array_merge($default_prop,$out[$type]['parameters']);
        	}
        	return $out;
        }
        
        public function assignFromFormSubmission($in_params,&$merge_params) {
			if (isset($in_params['list_of_typecomponents'])) {
				$merge_params['list_of_typecomponents'] = $in_params['list_of_typecomponents'];
			}
			
			if (isset($in_params['type_part_numbers']) && is_array($in_params['type_part_numbers'])) {
				$merge_params['type_part_number'] = implode('|',str_replace('|','!',$in_params['type_part_numbers']));
			}
				
			if (isset($in_params['type_descriptions']) && is_array($in_params['type_descriptions'])) {
				$merge_params['type_description'] = implode('|',str_replace('|','!',$in_params['type_descriptions']));
			}
			
        	return parent::assignFromFormSubmission($in_params,$merge_params);
        }
        
        /**
         * Get by typeversion_id and group concat in the component values packed as list_of_typecomponents
         * @see DBTableRow::getRecordById()
         */
        
        public function getRecordById($id) {
            $DBTableRowQuery = new DBTableRowQuery($this);
            $DBTableRowQuery->setLimitClause('LIMIT 1')->addSelectors(array($this->_idField => $id));
            $DBTableRowQuery->addSelectFields(array("CAST((SELECT GROUP_CONCAT(concat(typecomponent.typecomponent_id,',',typecomponent.component_name,',',(
  SELECT GROUP_CONCAT(DISTINCT typecomponent_typeobject.can_have_typeobject_id ORDER BY typecomponent_typeobject.can_have_typeobject_id SEPARATOR '|') FROM typecomponent_typeobject WHERE typecomponent_typeobject.typecomponent_id=typecomponent.typecomponent_id
),',',CONVERT(HEX(IFNULL(typecomponent.caption,'')),CHAR),',',CONVERT(HEX(IFNULL(typecomponent.subcaption,'')),CHAR),',',IFNULL(typecomponent.featured,0),',',IFNULL(typecomponent.required,0))  ORDER BY typecomponent.component_name SEPARATOR ';') FROM typecomponent WHERE typecomponent.belongs_to_typeversion_id=typeversion.typeversion_id) AS CHAR) as list_of_typecomponents"));
            $DBTableRowQuery->addSelectFields("(SELECT count(*) FROM partnumbercache WHERE typeversion.typeversion_id=partnumbercache.typeversion_id) as partnumber_count");
            $DBTableRowQuery->addJoinClause("LEFT JOIN user ON user.user_id=typeversion.user_id")
				            ->addSelectFields('user.login_id');          
            return $this->getRecord($DBTableRowQuery->getQuery());
        }    

        /**
       	 * This will attempt to fetch an typeversion record given the typeobject_id.  This is done one of two completely different ways.
       	 * If $effective_date is specified, then it tries to get the most recent one in effect as of the date regardless of if it is active.
       	 * Without the effective_date, the typeversion_id specified by the cached_current_typeversion_id is obtained.
       	 * 
       	 * TODO: Should refactor so these are single mysql calls.
         * 
         * @param int $typeobject_id
         * @param unknown_type $effective_date
         * @return boolean
         */
        public function getCurrentRecordByObjectId($typeobject_id,$effective_date=null) {
        	$the_typeversion_id = null;
        	if (!is_null($effective_date)) {
        		$effective_date = time_to_mysqldatetime(strtotime($effective_date));
        		$records = $this->_dbschema->getRecords('',"SELECT typeversion_id from typeversion
        				WHERE typeobject_id='".addslashes($typeobject_id)."'
        				and versionstatus='A' and effective_date=(select MAX(effective_date) from typeversion where typeobject_id='".addslashes($typeobject_id)."' and effective_date<='{$effective_date}')
        				LIMIT 1");
        		if (count($records)==1) {
        			$record = reset($records);
        			$the_typeversion_id = $record['typeversion_id'];
        		}
        	}

        	/*
        	 * We try again if the above failed, or for the first time if no effective_date
        	*/
        	if (is_null($the_typeversion_id)) {
        		$records = $this->_dbschema->getRecords('',"SELECT * FROM typeobject WHERE typeobject_id='{$typeobject_id}' LIMIT 1");
        		if (count($records)==1) {
        			$record = reset($records);
        			$the_typeversion_id = $record['cached_current_typeversion_id'];
        		} else {
        			$the_typeversion_id = null;
        		}
        	}
        	 
        	if (!is_null($the_typeversion_id)) {
        		return $this->getRecordById($the_typeversion_id);
        	} else {
        		return false;
        	}
        }      
        
        function getCurrentActiveRecordByObjectId($typeobject_id) {
        	if ($this->getCurrentRecordByObjectId($typeobject_id)) {
        		if ($this->versionstatus=='A') {
        			return true;
        		}
        	}
        	return false;
        }
        
        /** unpacks the list of type components from the DB query results field and returns as a list of types.
         *
         * @param unknown_type $list_of_typecomponents
         * @return multitype:multitype:string multitype:
         */
        static public function groupConcatComponentsToFieldTypes($list_of_typecomponents, $calcCaption=true) {
        	// process the components list.
        	$out = array();
        	foreach(explode(';',$list_of_typecomponents) as $typecomponent) {
        		if (!empty($typecomponent)) {
        			list($typecomponent_id, $component_name, $can_have_typeobject_id_packed, $caption, $subcaption, $featured, $required) = explode(',',$typecomponent); 
        			$can_have_typeobject_id = $can_have_typeobject_id_packed ? explode('|',$can_have_typeobject_id_packed) : array();
        			$type_array = array('type' => 'component', 'can_have_typeobject_id' => $can_have_typeobject_id, 'subcaption' => '', 'required' => 0, 'featured' => 1); // superficially is like an enum or something.
        			$type_array['caption'] = !$caption && $calcCaption ? ucwords(str_replace('_', ' ', $component_name)) : hextobin($caption);
        			if ($subcaption) $type_array['subcaption'] = hextobin($subcaption);
        			if (!is_null($featured)) $type_array['featured'] = $featured;
        			if (!is_null($required)) $type_array['required'] = $required;
        			$out[$component_name] = $type_array;
        		}
        	}
        	return $out;
        }
        
        static public function formatSubfieldPrefix($component_name,$typeobject_id) {
        	return $component_name.'(to/'.$typeobject_id.')';
        }
        
        /**
         * This will return an array of fieldtypes with keys like "component_name(to/234).myfieldname" instead
         * of the normal "impedance" field names.  This is needed for exporting with deep recursion.
         * This naming provides a unique namespace for the fields. 
         */
        static public function getAllPossibleComponentExtendedFieldNames($typeobject_id) {
        	$records = DbSchema::getInstance()->getRecords('',"
        		SELECT DISTINCT tc1.component_name, tv2.typeversion_id
				FROM typeversion as tv1
				LEFT JOIN typecomponent as tc1 on tc1.belongs_to_typeversion_id = tv1.typeversion_id
				LEFT JOIN typecomponent_typeobject on typecomponent_typeobject.typecomponent_id = tc1.typecomponent_id
        		LEFT JOIN typeversion as tv2 on tv2.typeobject_id = typecomponent_typeobject.can_have_typeobject_id
				WHERE (tv1.typeobject_id = '{$typeobject_id}') and tc1.typecomponent_id IS NOT NULL");
        	$out = array();
        	foreach($records as $record) {
        		$TV = DbSchema::getInstance()->dbTableRowObjectFactory('typeversion',false,'');
        		$TV->getRecordById($record['typeversion_id']);
        		//  including the (to/n) makes sure we've listed out all the possibilities because we don't want mycomp(to/5).myfield to
        		// step on mycomp(to/10).myfield since they are potentiall completely different types.
        		$out = array_merge($out,prefix_array_keys($TV->getItemFieldTypes(true,true),self::formatSubfieldPrefix($record['component_name'],$TV->typeobject_id).'.'));       		
        	}
        	return $out;
        }
        
        public function nextSerialNumber() {
        	$typeversion_digest = $this->getLoadedTypeVersionDigest(true);
        	$SerialNumber = SerialNumberType::typeFactory($typeversion_digest['serial_number_format']);
        	return $SerialNumber->getNextSerialNumber($this->typeversion_id);
        }
        
        static public function filterFeaturedFieldTypes($fieldtypes, $force_include_components=false) {
        	$out = array();
			foreach($fieldtypes as $fieldname => $fieldtype) {
				if ((isset($fieldtype['featured']) && $fieldtype['featured'] == 1) || (($fieldtype['type']=='component') && $force_include_components)) { 
					$out[$fieldname] = $fieldtype;
				}
			}
			return $out;
         }
		
		/**
		 * returns the fieldtypes array for properties and components of a DBTableRowItemVersion if it 
		 * had a typeversion_id equal to $this->typeversion_id.  It not only including feature fields,
		 * it also returns the standard fields like item_serial_number.  However, it doesn't return the
		 * psuedo field record_locator.
		 */
		public function getItemFieldTypes($include_nonfeatured_fields, $include_header_fields, $force_include_components=false) {
			$digest = $this->getLoadedTypeVersionDigest(false);
			$out = array();
			if ($include_header_fields) {
				$EmptyItemVersion = DbSchema::getInstance()->dbTableRowObjectFactory('itemversion',false,'');
				$header_fields = array('typeversion_id','effective_date');
				if ($digest['has_a_serial_number']) $header_fields[] = 'item_serial_number';
				if ($digest['has_a_disposition']) $header_fields[] = 'disposition';
				foreach($header_fields as $header_field) {
					$out[$header_field] = $EmptyItemVersion->getFieldType($header_field);
				}
			}
			if ($include_nonfeatured_fields) {
				$out  = array_merge($out,$digest['fieldtypes']);
			} else {
				$out = array_merge($out,self::filterFeaturedFieldTypes($digest['fieldtypes'], $force_include_components));
			}
			return $out;
		}
		
		/**
		 * Returns an array that contains the number of times each field is used in the itemcomponent, itemversion, or itemversionarchive tables.
		 */
		public function getItemInstanceCounts() {
			$type_digest = $this->getLoadedTypeVersionDigest(false);

			$select = array();
			foreach($type_digest['addon_component_fields'] as $fieldname) {
				$select[] = "SUM((SELECT count(*) FROM itemcomponent WHERE iv.itemversion_id=itemcomponent.belongs_to_itemversion_id AND itemcomponent.component_name='{$fieldname}')) as  {$fieldname}";
			}
			
			foreach($type_digest['addon_property_fields'] as $fieldname) {
				$like_something = fetch_like_query('"'.$fieldname.'":');
				$like_null = fetch_like_query('"'.$fieldname.'":null');
				$like_blank = fetch_like_query('"'.$fieldname.'":""');
				$select[] = "SUM((IF((iv.item_data {$like_something}) and NOT (iv.item_data {$like_null}) and NOT (iv.item_data {$like_blank}), 1, 0) +
  (SELECT count(*) FROM itemversionarchive as iva WHERE iv.itemversion_id=iva.itemversion_id 
       AND (iva.item_data {$like_something}) and NOT (iva.item_data {$like_null}) and NOT (iva.item_data {$like_blank})) )) as {$fieldname}";
			}		
			
			if (count($select)>0) {
				$out = DbSchema::getInstance()->getRecords('',"SELECT ".implode(', ',$select)." FROM itemversion as iv WHERE iv.typeversion_id='{$this->typeversion_id}'");
				$out = reset($out);
			} else {
				$out = array();
			}
			return $out;
		}

		/**
		 * Returns an array keyed by typeobject_id (or the target component) and containing the number of times that specific typeobject_id is assigned
		 * to this $fieldname for this specific typeversion_id.
		 * @param string $fieldname
		 * @return array
		 */
		public function getComponentIDCounts($fieldname) {
			$out = array();
			$type_digest = $this->getLoadedTypeVersionDigest(false);
			$fieldtype = $type_digest['fieldtypes'][$fieldname];
			if ($fieldtype['type']=='component') {
				$typeobject_ids = $fieldtype['can_have_typeobject_id'];

				$select = array();
				foreach($typeobject_ids as $typeobject_id) {
					$select[] = "SUM((
									SELECT count(*) FROM itemcomponent 
									LEFT JOIN itemobject AS io_them ON io_them.itemobject_id=itemcomponent.has_an_itemobject_id
									LEFT JOIN itemversion AS iv_them ON iv_them.itemversion_id=io_them.cached_current_itemversion_id
                                    LEFT JOIN typeversion AS tv_them ON tv_them.typeversion_id=iv_them.typeversion_id
									WHERE iv.itemversion_id=itemcomponent.belongs_to_itemversion_id AND itemcomponent.component_name='{$fieldname}' AND tv_them.typeobject_id='{$typeobject_id}')) as  id_{$typeobject_id}";
				}

				$counts = array();
				if (count($select)>0) {
					$result = DbSchema::getInstance()->getRecords('',"SELECT ".implode(', ',$select)." FROM itemversion as iv WHERE iv.typeversion_id='{$this->typeversion_id}'");
					$counts = reset($result);
					foreach($typeobject_ids as $typeobject_id) {
						$result_field = "id_{$typeobject_id}";
						if (isset($counts[$result_field])) {
							$out[$typeobject_id] = $counts[$result_field];
						}
					}
				}
			}			
			return $out;
		}
		
		/**
		 * Returns an array indexed by option key name for the specified enum field $fieldname containing the number of times that
		 * specific key is assigned to items with this specific typeversion_id.
		 * @param string $fieldname
		 * @return multitype:Ambigous <>
		 */
		public function getEnumKeyCounts($fieldname) {
			$out = array();
			$type_digest = $this->getLoadedTypeVersionDigest(false);
			$fieldtype = $type_digest['fieldtypes'][$fieldname];
			if (($fieldtype['type']=='enum') && is_array($fieldtype['options'])) {
				$optkeys = array_keys($fieldtype['options']);
				$select = array();
				foreach($optkeys as $optnum => $optkey) {
					$json_snippet = json_encode(array($fieldname => $optkey));
					$json_snippet = substr($json_snippet,1,strlen($json_snippet)-2);
					$like_value = fetch_like_query($json_snippet);
					$select[] = "SUM((IF((iv.item_data {$like_value}), 1, 0) +
						(SELECT count(*) FROM itemversionarchive as iva WHERE iv.itemversion_id=iva.itemversion_id AND (iva.item_data {$like_value})) )) as {$fieldname}_{$optnum}";						
				}
				$counts = array();
				if (count($select)>0) {
					$result = DbSchema::getInstance()->getRecords('',"SELECT ".implode(', ',$select)." FROM itemversion as iv WHERE iv.typeversion_id='{$this->typeversion_id}'");
					$counts = reset($result);
					foreach($optkeys as $optnum => $optkey) {
						$result_field = "{$fieldname}_{$optnum}";
						if (isset($counts[$result_field])) {
							$out[$optkey] = $counts[$result_field];
						}
					}
				}
			} 
			return $out;
		}
		
		
		/**
		 * This returns an array keyed by property name where the value is an array of typeversion_ids where the property is referred to
		 * in a componentsubfield definition.  In otherwords this tells you what typeversion definitions you will need to edit
		 * if you want to rename or delete a property from the current typeversion.
		 */
		public function getTypeVersionInstancesWhereFieldIsASubField() {
			// so a first pass where we hunt for an definitions where both the property name and type=component_subfield appears in the same record
			$json_snippet = json_encode(array('type' => 'component_subfield'));
			$json_snippet = substr($json_snippet,1,strlen($json_snippet)-2);
			$like_value = fetch_like_query($json_snippet);			
			$typeversion_ids = DbSchema::getInstance()->getRecords('typeversion_id',"SELECT typeversion_id FROM typeversion WHERE (type_data_dictionary {$like_value})");
			$out = array();
			foreach($typeversion_ids as $typeversion_id => $rec) {
				$TV = new DBTableRowTypeVersion();
				$TV->getRecordById($typeversion_id);
				$digest = $TV->getLoadedTypeVersionDigest(false);
				foreach($digest['addon_component_subfields'] as $fieldname) {
					$fieldtype = $digest['fieldtypes'][$fieldname];
					if ($fieldtype['embedded_in_typeobject_id']==$this->typeobject_id) {
						$propname = $fieldtype['component_subfield'];
						if (!isset($out[$propname])) $out[$propname] = array();
						$out[$propname][] = $typeversion_id;
					}
				}
			}
			return $out;
		}
		
		/**
		 * This returns a list of property and component fieldnames that are referenced in either other types or items.
		 * @param boolean $include_subfields includes type dependencies where another type definition has a component_subfield referring to us.
		 * @return multitype:
		 */
		public function getWriteProtectedFieldnames($include_types_only=true) {
			$out = array_keys($this->getTypeVersionInstancesWhereFieldIsASubField());
			if (!$include_types_only) $out = array_unique(array_merge($out, array_keys(array_filter($this->getItemInstanceCounts()))));
			return array_values($out);  // ensures that what comes out is an array type
		}
		
        
        public function getLoadedTypeVersionDigest($skip_components) {
        	return self::getTypeVersionDigestFromFields($this->list_of_typecomponents, $this->tc__has_a_serial_number, $this->tc__has_a_disposition, $this->getArray(), $skip_components);
        }
        
        /*
         * converts the raw typeversion record fields and associated typecomponents to the correct internal variables
         * 
         * If there are component subfields present in the dictionary, then this will also load the detailed type
         * information from the relevant typeversion records.
         * $skip_components = true means that we will not read component type information, thus saving some db access.
         */
        static public function getTypeVersionDigestFromFields($list_of_typecomponents, $has_a_serial_number, $has_a_disposition, $all_typeversion_fields, $skip_components) {
        	 
        	$type_data_dictionary = $all_typeversion_fields['type_data_dictionary'];
        	$type_form_layout = $all_typeversion_fields['type_form_layout'];
        	 
        	$out = array();
        	$out['typeversion_id'] = $all_typeversion_fields['typeversion_id'];
        	$out['effective_date'] = $all_typeversion_fields['effective_date'];
        	$out['partnumber_count'] = $all_typeversion_fields['partnumber_count'];
        	$out['has_a_serial_number'] = $has_a_serial_number;
        	$out['has_a_disposition'] = $has_a_disposition;
        	$out['serial_number_format'] = extract_prefixed_keys($all_typeversion_fields, 'serial_number_');
        	$out['addon_property_fields'] = array();      // contains data dictionary items that are NOT in the addon_component_subfields list.
        	$out['addon_component_fields'] = array();     // contains only component field names
        	$out['addon_component_subfields'] = array();  // fieldnames that happen to be component subfields.  if a field appears here, it doesn't in the addon_property_fields list
        	$out['dictionary_field_layout'] = json_decode($type_form_layout, true);
        	$out['fieldtypes'] = array();							// fieldtypes array for components, properties, and component subfields
        	$out['components_in_defined_subfields'] = array();		// what different components are referenced by component_subfields?
        	 
        	$fieldtypes = array();
        	 
        	// process the components list.
        	if (!$skip_components) {
        		$component_types = DBTableRowTypeVersion::groupConcatComponentsToFieldTypes($list_of_typecomponents);
        		$fieldtypes = array_merge($fieldtypes,$component_types);
        		$out['addon_component_fields'] = array_keys($component_types);
        	}
        	 
        	// process the type dictionary.  This might also contain add-on properties for the components, so don't clobber the ones already read in above
        	$pn = array();
        	$subfieldtype = array(); // subfield name
        	$component_typeversions_to_load = array(); // type versions we should load.  Note that each entry is an array of typeobject_ids (normally 1 entry)
        	if ($type_data_dictionary) {
	        	foreach(json_decode($type_data_dictionary, true) as $fieldname => $type_array) {
	        		if (in_array($fieldname,$out['addon_component_fields'])) { // this name matches a component_name
	        			// a component
	        			unset($type_array['type']); // we can overwrite anything except the field type.
	        			$fieldtypes[$fieldname] = array_merge($fieldtypes[$fieldname],$type_array);
	        		} else if (!$skip_components && isset($type_array['component_name']) && isset($type_array['component_subfield']) && (true)) {
	        			// make sure the component exists before adding it as a candidate subfieldtype.
	        			if (in_array($type_array['component_name'],$out['addon_component_fields'])) {
	        				$to_ids = $fieldtypes[$type_array['component_name']]['can_have_typeobject_id'];
	        				$component_typeversions_to_load[$type_array['component_name']] = $to_ids;
	        				if (!isset($type_array['caption'])) $type_array['caption'] = ucwords(str_replace('_', ' ', $fieldname));
	        				if (!isset($type_array['embedded_in_typeobject_id']) && (count($to_ids)>0)) {
	        					$type_array['embedded_in_typeobject_id'] = reset($to_ids);
	        				}
	        				$subfieldtype[$fieldname] = $type_array;
	        			}
	        		} else if (!isset($type_array['component_name']) || !isset($type_array['component_subfield'])) {
	        			// a simple property
	        			if (!isset($type_array['caption'])) $type_array['caption'] = ucwords(str_replace('_', ' ', $fieldname));
	        			$fieldtypes[$fieldname] = $type_array;
	        			$pn[] = $fieldname;
	        		}
	        	}
        	}
        	$out['addon_property_fields'] = $pn;
        	 
        	// process the subfieldnames if present.  This will NOT be processed if $skip_components is true (see above)
        	$typeversion_digests_by_to_id = array();
        	foreach($component_typeversions_to_load as $component_name => $typeobject_ids) {
        		foreach($typeobject_ids as $typeobject_id) {
        			$TV = DbSchema::getInstance()->dbTableRowObjectFactory('typeversion',false,'');
        			// this check should never hit.  Makes me feel better to have it here.
        			if (!$all_typeversion_fields['effective_date']) throw new Exception("all_typeversion_fields['effective_date'] is empty");
        			$TV->getCurrentRecordByObjectId($typeobject_id,$all_typeversion_fields['effective_date']);
        			$typeversion_digests_by_to_id[$typeobject_id] = $TV->getLoadedTypeVersionDigest(true); // true=skip component loading
        		}
        	}
        	
        	$sn = array();
        	// meld the subfield type information with the type information in the current dictionary so we can override things like caption.
        	foreach($subfieldtype as $fieldname => $fieldtype_in_current_dictionary) {
        		$component_name = $fieldtype_in_current_dictionary['component_name'];
        		$embedded_in_typeobject_id = $fieldtype_in_current_dictionary['embedded_in_typeobject_id'];
        		$tvd = $typeversion_digests_by_to_id[$embedded_in_typeobject_id];
        		$subfieldname = $fieldtype_in_current_dictionary['component_subfield'];
        		if (in_array($subfieldname,$tvd['addon_property_fields'])) {
        			/*
        			 looks like this really exists.  So we should include it as a component_subfield.
        			$tvd['fieldtypes'][$subfieldname] is the original fieldtype in the component for this field.
        			We should use it's definition as the basis for our new fieldtype.
        			*/
        			$fieldtype_in_component_dictionary = $tvd['fieldtypes'][$subfieldname];
        			unset($fieldtype_in_current_dictionary['type']); // we cannot alter the "type" parameter from the original, so don't allow override
        			unset($fieldtype_in_component_dictionary['component_name']); // similarly, we can't allow these parameters to step on the ones in this dictionary.
        			unset($fieldtype_in_component_dictionary['embedded_in_typeobject_id']);
        			unset($fieldtype_in_component_dictionary['component_subfield']);
        			$fieldtypes[$fieldname] = array_merge($fieldtype_in_component_dictionary,$fieldtype_in_current_dictionary);
        			$sn[] = $fieldname;
        			// keep track of which components we actually needed for the subfield definitions.  This will be useful later.
        			if (!in_array($component_name,$out['components_in_defined_subfields'])) $out['components_in_defined_subfields'][] = $component_name;
        		}
        	}
        	 
        	$out['addon_component_subfields'] = $sn;
        	$out['fieldtypes'] = $fieldtypes;
        	return $out;
        	 
        }
        
        /**
         * 
         * @param array $fieldlayout_tbl is the layout in array form.  If empty, one will be constructed from the fieldnames list.
         * @param array $default_fieldnames is a list of fieldnames to use if the layout is not expicitely specified
         * @param array $fields_to_remove a list of fields that should be pruned from the layout.
         * @param string $layout_key identifier for the layout (itemview, editview).  Might not be using this here.
         * @return array layout structure
         */
        static function addDefaultsToAndPruneFieldLayout($fieldlayout_tbl,$default_fieldnames,$fields_to_remove, $layout_key=null) {
        	
        	
			if (!is_array($fieldlayout_tbl)) {
				$fieldlayout_tbl = array();
				
				foreach(array_chunk($default_fieldnames,2) as $row) {
					$rowout = array('type' => 'columns', 'columns' => array());
					foreach($row as $col) {
						$rowout['columns'][] = array('name' => $col);
					}
					$fieldlayout_tbl[] = $rowout;
				}
			}
			
			// remove entries that should be
        	if (!empty($fields_to_remove)) {
	        	foreach($fieldlayout_tbl as $row_index => $row) {
	        		$type = $row['type'];  // html or columns
	        		if ($type=='columns') {
	        			foreach($row['columns'] as $field_index => $field) {
	        				$name = $field['name'];
	        				if (in_array($name, $fields_to_remove)) {
	        					unset($fieldlayout_tbl[$row_index]['columns'][$field_index]);
	        					if (empty($fieldlayout_tbl[$row_index]['columns'])) unset($fieldlayout_tbl[$row_index]);    // if this was the last column for this row, remove the row too
	        				}
	        			}
	        		}
	        	}
        	}
        	
        	return $fieldlayout_tbl;
        }
              
        static function buildListOfItemFieldNames($typeversion_digest) {
        	$out = array('effective_date','record_locator','item_serial_number','typeversion_id','disposition','partnumber_alias');
        	$out = array_merge($out,$typeversion_digest['addon_property_fields']);
        	$out = array_merge($out,$typeversion_digest['addon_component_fields']);
        	$out = array_merge($out,$typeversion_digest['addon_component_subfields']);
        	return $out;        	
        }
        

        /**
         * returns only fields that can be used by referencing types as component subfields
         * @return array of fieldnames
         */
        public function getFieldsAllowsAsSubFields() {
        	$type_digest = $this->getLoadedTypeVersionDigest(false);
        	return $type_digest['addon_property_fields'];        	
        }
        
        static function getNumberOfItemsForTypeVersion($typeversion_id, $partnumber_alias = null) {
        	// if  $partnumber_alias is specified, we include only those that also have the specific allias
        	$and_where = is_null($partnumber_alias) ? '' : " AND (itemversion.partnumber_alias='{$partnumber_alias}')";
        	$query = "SELECT count(*) FROM itemversion
        	WHERE (itemversion.typeversion_id='{$typeversion_id}')".$and_where;
        	$record = reset(DbSchema::getInstance()->getRecords('',$query));
        	return $record['count(*)'];
        }
        
        /**
         * The point of this is to return a list of all the different types.  If there are aliases, those are returned
         * both individually and as a group.  When returned individually the key is like 123a0 where 123=itemobject_id
         * and 0 = partnumber_alias
         * @param DBTableRowUser $userobj
         * @param unknown_type $is_user_procedure
         * @return array
         */
        static function getListOfTypePartNumberRecordsWAliasesAllowedToUser(DBTableRowUser $userobj,$is_user_procedure) {
        	$ids_codes = $userobj->getDataTerminalObjectIds();
        	$data_term_and_where = count($ids_codes)==0 ? '' : ' and (typeversion.typeobject_id IN ('.implode(',',$ids_codes).')) ';
        	$is_user_procedure = $is_user_procedure ? '1' : '0';
        	$query = "
        	SELECT unionof.* FROM
        	
        	(       	 
	        	( SELECT typeversion.typeversion_id, typeversion.typeobject_id, typeobject.typedisposition, partnumbercache.part_description as desc_only, concat(partnumbercache.part_number,' (',partnumbercache.part_description,')') as description, concat(typeobject.typeobject_id,'a',partnumbercache.partnumber_alias) as itemkey
	        	FROM typeobject
	        	LEFT JOIN partnumbercache on partnumbercache.typeversion_id = typeobject.cached_current_typeversion_id
	        	LEFT JOIN typeversion ON typeversion.typeversion_id=typeobject.cached_current_typeversion_id
	        	LEFT JOIN typecategory on typecategory.typecategory_id = typeversion.typecategory_id
	        	WHERE (typecategory.is_user_procedure='{$is_user_procedure}') {$data_term_and_where}
	        	     AND ( (SELECT count(*) FROM partnumbercache pn WHERE pn.typeversion_id=typeversion.typeversion_id)>1) )
        	     
            UNION
            
	            (SELECT typeversion.typeversion_id, typeversion.typeobject_id, typeobject.typedisposition, (SELECT GROUP_CONCAT( png.part_description ORDER BY png.part_number ASC SEPARATOR ', ') FROM partnumbercache png WHERE png.typeversion_id=typeobject.cached_current_typeversion_id ORDER BY png.part_number) as desc_only, (SELECT GROUP_CONCAT( png.part_number ORDER BY png.part_number ASC SEPARATOR ', ') FROM partnumbercache png WHERE png.typeversion_id=typeobject.cached_current_typeversion_id ORDER BY png.part_number) as description, typeobject.typeobject_id as itemkey 
		        	FROM typeobject
		        	LEFT JOIN typeversion ON typeversion.typeversion_id=typeobject.cached_current_typeversion_id
		        	LEFT JOIN typecategory on typecategory.typecategory_id = typeversion.typecategory_id
		        	WHERE (typecategory.is_user_procedure='{$is_user_procedure}') {$data_term_and_where} 
		        	AND ( (SELECT count(*) FROM partnumbercache pn WHERE pn.typeversion_id=typeversion.typeversion_id)>1) )
	        	
	        UNION
		        ( SELECT typeversion.typeversion_id, typeversion.typeobject_id, typeobject.typedisposition, partnumbercache.part_description as desc_only, concat(partnumbercache.part_number,' (',partnumbercache.part_description,')') as description, typeobject.typeobject_id as itemkey
	        	FROM typeobject
	        	LEFT JOIN partnumbercache on partnumbercache.typeversion_id = typeobject.cached_current_typeversion_id
	        	LEFT JOIN typeversion ON typeversion.typeversion_id=typeobject.cached_current_typeversion_id
	        	LEFT JOIN typecategory on typecategory.typecategory_id = typeversion.typecategory_id
	        	WHERE (typecategory.is_user_procedure='{$is_user_procedure}') {$data_term_and_where}
	        	     AND ( (SELECT count(*) FROM partnumbercache pn WHERE pn.typeversion_id=typeversion.typeversion_id)=1) )
	        	
	        ) unionof
            	     
        	ORDER BY unionof.description
        	";
        	$records = DbSchema::getInstance()->getRecords('itemkey',$query);
        	return $records;
        }     

        /**
         * Return an array of formatted names of the different types.  For the most part the keys are typeobject_ids
         * but they can also be 123a0 type format (see above).
         * The list will include all unique values of the key so be used primarily for filtering results on the itemlistview page.  
         * The list might be restricted depending on the usertype.
         * @param DBTableRowUser $userobj
         * @param boolean $is_user_procedure 0 = return part numbers, 1 = return procedures 
         * @param boolean $include_aliases include non-numeric keys which represent single aliases.
         * @return array of strings
         */
        static function getPartNumbersWAliasesAllowedToUser(DBTableRowUser $userobj,$is_user_procedure, $include_aliases=true) {
        	$records = self::getListOfTypePartNumberRecordsWAliasesAllowedToUser($userobj,$is_user_procedure);
        	 
        	$out = array();
        	foreach($records as $record_id => $record) {
        		if ($include_aliases || is_numeric($record_id)) {
	        		$obsolete = $record['typedisposition']=='B' ? ' [Obsolete]' : '';
	        		$out[$record_id] = $record['description'].$obsolete;
        		}
        	}
        	return $out;
        }        
       
        
        static function isTypeCategoryAProcedure($typecategory_id) {
        	$records = DbSchema::getInstance()->getRecords('typecategory_id',"select * FROM typecategory WHERE typecategory_id='{$typecategory_id}'");
        	return $records[$typecategory_id]['is_user_procedure']==1;
        }
        
        /**
         * Creates a fully qualified URL that will take the browser to the itemlistview or procedurelistview.
         * This url must be parseable by /struct/lv action.
         * @param int $typeobject_id (this specifies the category selector view)
         * @param bool $show_matrix (this specifies if the matrix view is to be shown if a part view)
         * @return string which is the absolute URL
         */
        static function getListViewAbsoluteUrl($typeobject_id,$show_matrix=null) {
        	$locator = '/struct/lv/to/'.$typeobject_id;
        	if (!is_null($show_matrix)) $locator .= '/mat/'.($show_matrix ? '1' : '0');
        	return Zend_Controller_Front::getInstance()->getRequest()->getScheme().'://'.Zend_Controller_Front::getInstance()->getRequest()->getHttpHost().Zend_Controller_Front::getInstance()->getRequest()->getBaseUrl().$locator;
        }
        
        public function getFieldnameAllowedForLayout() {
        	$type_digest = $this->getLoadedTypeVersionDigest(false);
        	$defined = array();
        	$defined = array_merge($defined,$type_digest['addon_property_fields']);
        	$defined = array_merge($defined,$type_digest['addon_component_fields']);
        	$defined = array_merge($defined,$type_digest['addon_component_subfields']);
        	return $defined;
        }
               
        /**
         * returns a list of fieldname that have been defined but do not show up in the layout.
         * @return array of fieldnames:
         */
        public function getHiddenFieldnames() {
        	$type_digest = $this->getLoadedTypeVersionDigest(false);
        	$defined = $this->getFieldnameAllowedForLayout();
        	// remove all entries that are not in the layout
            if (is_array($type_digest['dictionary_field_layout'])) {
                foreach($type_digest['dictionary_field_layout'] as $row_index => $row) {
                    $type = $row['type'];  // html or columns
                    if ($type=='columns') {
                        foreach($row['columns'] as $field_index => $field) {
                            $defined = array_diff($defined,array($field['name']));
                        }
                    }
                }
                return $defined;
            } else {
                return array();
            }
        	
        }
        
        /**
         * creates a fully qualified URL that will take a browser to the itemdefinitionview page for a specific typeobject.
         * If the current typeversion is NOT the most current one, then specify the typeversion number instead.
         * The assumption is that under 99.9 % of cases, one wants to link to the head.  Only in the case where one
         * is looking at a non-current version is it OK to assume we mean we want to link to a non-current version.
         * @return string
         */
        public function absoluteUrl() {
        	$use_typeobject_id = (isset($this->to__cached_current_typeversion_id) && is_numeric($this->to__cached_current_typeversion_id) && ($this->to__cached_current_typeversion_id==$this->typeversion_id));
        	return $use_typeobject_id ? formatAbsoluteLocatorUrl('to',$this->typeobject_id) : formatAbsoluteLocatorUrl('tv',$this->typeversion_id);
        }
        
        /*
        * if anything has changed, this saves a new version of the record rather than overwriting
        * function will raise an exception if an error occurs
        */
        public function saveVersioned($user_id=null,$handle_err_dups_too=true) {
        		
        	if ($user_id==null) $user_id = $_SESSION['account']->user_id;
        	$typeversion_id = $this->typeversion_id;
        	 
        	$fieldnames = $this->getSaveFieldNames();
        
        	/*
        	 * if this is a new item instance, then first create the type instance
        	* record then save the record as a new record.
        	*/
        	if (!$this->isSaved()) {
        		$TypeObject = new DBTableRow('typeobject');
        		$TypeObject->save();
        		$this->typeobject_id = $TypeObject->typeobject_id;
        	}

                
        	$this->typeversion_id = 'new';
        	$this->user_id = $user_id;
        	$this->record_created = time_to_mysqldatetime(script_time());
        	$this->modified_by_user_id = $user_id;
        	$this->record_modified = time_to_mysqldatetime(script_time());
        	parent::save($fieldnames,$handle_err_dups_too);

        	// these are the components that we want to make sure this version has
        	$component_types = self::groupConcatComponentsToFieldTypes($this->list_of_typecomponents, false);

        	foreach($component_types as $fieldname => $component_type) {
        		$Comp = new DBTableRow('typecomponent');
        		$Comp->component_name = $fieldname;
        		$Comp->belongs_to_typeversion_id = $this->typeversion_id;
        		$Comp->caption = $component_type['caption'];
        		$Comp->subcaption = $component_type['subcaption'];
        		$Comp->featured = $component_type['featured'];
        		$Comp->required = $component_type['required'];        		
        		$Comp->save();
        		
        		// save the can_have_a_typeobject_id records
        		foreach($component_type['can_have_typeobject_id'] as $typeobject_id) {
        			$this->_dbschema->mysqlQuery("INSERT INTO typecomponent_typeobject (typecomponent_id,can_have_typeobject_id) VALUES ('{$Comp->typecomponent_id}','{$typeobject_id}')");
        		}
        	}
        	
        	self::saveOrRebuildPartNumberCache($this->typeversion_id, $this->type_part_number, $this->type_description);

        	$_SESSION['most_recent_new_typeversion_id'] = $this->typeversion_id;
        	$this->getRecordById($this->typeversion_id);
        	self::updateCachedCurrentTypeVersionId($this->typeobject_id);

        }
        
        /**
         * Use this instead of isSaved() in certain instances where you want to know if the next
         * call to save this object will result in a new item instance of an existing object, or
         * a completely new itemobject.
         * @return boolean
         */
        public function isExistingObject() {
        	return is_numeric($this->typeobject_id);
        }        
        
        static function saveOrRebuildPartNumberCache($typeversion_id, $type_part_number, $type_description) {
        	$records_to_delete = DbSchema::getInstance()->getRecords('partnumber_id',"SELECT * FROM partnumbercache WHERE typeversion_id='{$typeversion_id}'");
        	$part_numbers = explode('|',$type_part_number);
        	$part_descriptions = explode('|',$type_description);
        	if (count($part_number)==count($part_description)) {
        		$pns_to_save = array();
        		foreach($part_numbers as $index => $pn) {
        			$pns_to_save[$index] = array('partnumber_alias' => $index, 'part_number' => $part_numbers[$index], 'part_description' => $part_descriptions[$index]);
        		}
        		
        		// remove items from the delete and save list if they are identical.  
        		foreach($records_to_delete as $partnumber_id => $pnrecord) {
        			$to_save = $pns_to_save[$pnrecord['partnumber_alias']];
        			if (isset($pns_to_save[$pnrecord['partnumber_alias']])
        					 &&  ($to_save['part_number']==$pnrecord['part_number'])
        					 &&  ($to_save['part_description']==$pnrecord['part_description'])) {
        				unset($records_to_delete[$partnumber_id]);
        				unset($pns_to_save[$pnrecord['partnumber_alias']]);
        			} else if (isset($pns_to_save[$pnrecord['partnumber_alias']])) {
        				// only the number and description are different.  So we can update
        				$PartNumRec = new DBTableRow('partnumbercache');
        				if ($PartNumRec->getRecordById($partnumber_id)) {
        					$PartNumRec->part_number = $to_save['part_number'];
        					$PartNumRec->part_description = $to_save['part_description'];
        					$PartNumRec->save(array('part_number','part_description'));
	        				unset($records_to_delete[$partnumber_id]);
	        				unset($pns_to_save[$pnrecord['partnumber_alias']]);
        				}
        			}
        		}
        		
        		// delete any from the delete list that are still there
        		foreach($records_to_delete as $partnumber_id => $pnrecord) {
        			$PartNumRec = new DBTableRow('partnumbercache');
        			if ($PartNumRec->getRecordById($partnumber_id)) {
        				$PartNumRec->delete();
        			}
        		}        

        		// add any left in the $pns_to_save array
        		foreach($pns_to_save as $index => $to_save) {
        			$PartNumRec = new DBTableRow('partnumbercache');
        			$PartNumRec->partnumber_alias = $index;
        			$PartNumRec->typeversion_id = $typeversion_id;
        			$PartNumRec->part_number = $to_save['part_number'];
        			$PartNumRec->part_description = $to_save['part_description'];
        			$PartNumRec->save();
        		}
        	}
        }
        
        /*
        * this is a traditional save.  It does not do any versioning.  It is called when the "correct this record" is pressed.
        * It deletes then resaves the typecomponent records when called. 
        *
        * function will raise an exception if an error occurs
        */
        public function save($fieldnames=array(),$handle_err_dups_too=true) {
        	 
        	// by default we will include all the fields here.
        	if (count($fieldnames)==0) {
        		$fieldnames = $this->getSaveFieldNames();
        	}
        
        	// this is where we would pack any field to be saved.
        
        
        	// if this is a new item instance, then first create the itemobject record then save the record as a new record
        	if (!$this->isSaved()) {
        		$TypeObject = new DBTableRow('typeobject');
        		$TypeObject->save();
        		$this->typeobject_id = $TypeObject->typeobject_id;
        		// although these are normally set by the constructor, we force them here in case this was a copied record
        		$this->user_id = $_SESSION['account']->user_id;
        		$this->record_created = time_to_mysqldatetime(script_time());
        	}
        	
        	if (in_array('modified_by_user_id', $fieldnames)) {
        		$this->modified_by_user_id = $_SESSION['account']->user_id;
        	}
        	if (in_array('record_modified', $fieldnames)) {
        		$this->record_modified = time_to_mysqldatetime(script_time());
        	}
        	 
        	parent::save($fieldnames,$handle_err_dups_too);
        		
        		
        	/*
        	 *  Save component typecomponent records as needed.  Don't mindlessly delete and re-add, but
        	 *  check first to make sure there are actually changes.
        	 */
        	
        	// load the possibly multiple typeobject_ids into |-separated list in the field can_have_typeobject_id
        	$comp_records_to_delete = DbSchema::getInstance()->getRecords('typecomponent_id',"SELECT typecomponent_id, belongs_to_typeversion_id, (
  SELECT GROUP_CONCAT(DISTINCT typecomponent_typeobject.can_have_typeobject_id ORDER BY typecomponent_typeobject.can_have_typeobject_id SEPARATOR '|') FROM typecomponent_typeobject WHERE typecomponent_typeobject.typecomponent_id=typecomponent.typecomponent_id
) as can_have_typeobject_id, component_name, IFNULL(caption,'') as caption, IFNULL(subcaption,'') as subcaption, IFNULL(featured,0) as featured, IFNULL(required,0) as required FROM typecomponent WHERE belongs_to_typeversion_id='{$this->typeversion_id}'");
        	
        	$components_to_save = array();
        	foreach(self::groupConcatComponentsToFieldTypes($this->list_of_typecomponents, false) as $fieldname => $component_type) {
        		$components_to_save[$fieldname] = $component_type;
        	}
        		
        	// remove items from the delete and save list if they are identical.
        	foreach($comp_records_to_delete as $typecomponent_id => $typecomponent) {
        		$component_name_on_disk = $typecomponent['component_name'];
        		$to_save = $components_to_save[$component_name_on_disk];
        		// flatten the list of typeobject_ids
        		$a = $to_save['can_have_typeobject_id'];
        		if (!is_array($a)) $a = array($a);
        		sort($a);
        		$to_save['can_have_typeobject_id'] = implode('|',$a);
        		// if this component is one we are going to turn around and recreated anyway, then remove it from both $comp_records_to_delete and $components_to_save
        		if (isset($components_to_save[$component_name_on_disk]) 
        				&& ($typecomponent['can_have_typeobject_id']==$to_save['can_have_typeobject_id'])
		        		&& ($typecomponent['caption']==$to_save['caption']) && ($typecomponent['subcaption']==$to_save['subcaption'])
        				&& ($typecomponent['featured']==$to_save['featured']) && ($typecomponent['required']==$to_save['required'])) {
        			unset($comp_records_to_delete[$typecomponent_id]);
        			unset($components_to_save[$component_name_on_disk]);
        		}
        	}
        		
        	// delete any from the delete list that are still there
        	foreach($comp_records_to_delete as $typecomponent_id => $typecomponent) {
        		$Comp = new DBTableRow('typecomponent');
        		$Comp->getRecordById($typecomponent_id);
        		$Comp->delete();
        		DbSchema::getInstance()->mysqlQuery("DELETE typecomponent_typeobject FROM typecomponent_typeobject
        		    WHERE typecomponent_typeobject.typecomponent_id='{$typecomponent_id}'");        		
        	}
        		
        	// save any that are left in the $component_to_save list
        	foreach($components_to_save as $fieldname => $component_type) {
        		$Comp = new DBTableRow('typecomponent');
        		$Comp->component_name = $fieldname;
        		$Comp->belongs_to_typeversion_id = $this->typeversion_id;
        		$Comp->caption = $component_type['caption'];
        		$Comp->subcaption = $component_type['subcaption'];
        		$Comp->featured = $component_type['featured'];
        		$Comp->required = $component_type['required'];
        		$Comp->save();
        		foreach($component_type['can_have_typeobject_id'] as $typeobject_id) {
        			$this->_dbschema->mysqlQuery("INSERT INTO typecomponent_typeobject (typecomponent_id,can_have_typeobject_id) VALUES ('{$Comp->typecomponent_id}','{$typeobject_id}')");
        		}        		
        	}
        	
        	self::saveOrRebuildPartNumberCache($this->typeversion_id, $this->type_part_number, $this->type_description);
        		
        	$_SESSION['most_recent_new_typeversion_id'] = $this->typeversion_id;
        	$this->getRecordById($this->typeversion_id);
        	self::updateCachedCurrentTypeVersionId($this->typeobject_id);
        	DBTableRowTypeObject::updateCachedNextSerialNumberFields($this->typeobject_id);
        	DBTableRowTypeObject::updateCachedHiddenFieldCount($this->typeobject_id);
        }
        
 
        /**
         * how many versions of the current type are there?
         * @return integer
         */
        public function numberOfTypesDefined() {
        	$query = "SELECT count(*) FROM typeversion WHERE typeobject_id='{$this->typeobject_id}'";
        	$record = reset(DbSchema::getInstance()->getRecords('',$query));
        	return $record['count(*)'];
        }
        
        
        /**
         * Find out if we are authorized to delete this typeversion record now or if we could if we set the delete override.
         * The possibilities are none, or one or the other.  We use this to show an appropriate button.
         * @return multitype:boolean
         */
        public function getDeleteAuthorization() {
        	$can_deleteblocked = false;
        	$can_delete = false;
        	$time = strtotime($this->record_created);
        	$inside_grace_period = $time + Zend_Registry::get('config')->delete_grace_in_sec > script_time();
        	$is_draft = $this->versionstatus=='D';
        	if (($_SESSION['account']->getRole() == 'Admin')) {
        		if ($inside_grace_period || $is_draft) {
        			$can_delete = true;
        			$can_deleteblocked = false;
        		} else { // outside of grace zone
        			if (AdminSettings::getInstance()->delete_override) {
        				$can_delete = true;
        				$can_deleteblocked = false;
        			} else {
        				$can_delete = false;
        				$can_deleteblocked = true;
        			}
        		}
        	} else { // not an admin
        		if ((Zend_Registry::get('customAcl')->isAllowed($_SESSION['account']->getRole(),'ui:caneditdefinitions')
        				&& ($_SESSION['account']->user_id == $this->user_id) && ($inside_grace_period || $is_draft))) {
        			$can_delete = true;
        			$can_deleteblocked = false;
        		}
        	}
        	return array('can_delete' => $can_delete, 'can_deleteblocked' => $can_deleteblocked);
        }
        
        
        /**
         * Is it legal to delete this typeversion record.  Check various things to make sure.
         * @return boolean true if ok to delete
         */
        public function allowedToDelete() {
        	$result = true;
        	if (self::getNumberOfItemsForTypeVersion($this->typeversion_id)>0) $result = false;

        	if ($this->numberOfTypesDefined()<2) {
	        	// we will not allow deleting if there are others referencing us and we are the last of our kind.
	        	if ((count(getTypesThatReferenceThisType($this->typeversion_id, null))>0)) $result = false;
	
	        	$commment_records = DbSchema::getInstance()->getRecords('',"SELECT * FROM typecomment where typeobject_id='{$this->typeobject_id}'");
	        	if (count($commment_records)>0) $result = false;
        	}
        	

        	return $result;
        }      
        
        public function allowedToRevertToDraft() {
        	return self::getNumberOfItemsForTypeVersion($this->typeversion_id)==0;
        }
        
        /**
         * delete this typeversion and associated records.  If this is the only version left, then delete the typeobject record too.
         * @see DBTableRow::delete()
         */
        public function delete() {
        	$typeobject_id = $this->typeobject_id;
        	$typeversion_id = $this->typeversion_id;
        	parent::delete();
        	DbSchema::getInstance()->mysqlQuery("DELETE typecomponent_typeobject FROM typecomponent_typeobject 
        			INNER JOIN typecomponent ON typecomponent.typecomponent_id=typecomponent_typeobject.typecomponent_id
		        	WHERE typecomponent.belongs_to_typeversion_id='{$typeversion_id}'");
        	DbSchema::getInstance()->mysqlQuery("delete from typecomponent where belongs_to_typeversion_id='{$typeversion_id}'");
        	// if we have just deleted the last typeversion, then we should cleanup the whole typeobject
        	$tv_records = DbSchema::getInstance()->getRecords('',"SELECT * FROM typeversion where typeobject_id='{$typeobject_id}'");
        	if (count($tv_records)==0) {
        		DbSchema::getInstance()->mysqlQuery("delete from typeobject where typeobject_id='{$typeobject_id}'");
        		/*
        		 * note that these next deletes should only do something under unusual circumstance,
        		* since normally we would not be allowed to delete when comments or references remained
        		*/
        		DbSchema::getInstance()->mysqlQuery("delete from typecomponent_typeobject where can_have_typeobject_id='{$typeobject_id}'");
        		DbSchema::getInstance()->mysqlQuery("delete from typecomment where typeobject_id='{$typeobject_id}'");
        	} else {
        		self::updateCachedCurrentTypeVersionId($typeobject_id);
        	}
        }        
        

        /**
         * hunt through current typeversions for all typeobjects except for us.  The part number should not match the current part number
         * for any others.
         * @param string $part_number
         * @return boolean true if there are others that match
         */
        public function partNumberAlreadyUsed($part_number) {

        	$part_number_slashes = addslashes($part_number);
        	$records = $this->_dbschema->getRecords('',"
        			SELECT other_to.typeobject_id
        			FROM typeobject as other_to
        			LEFT JOIN partnumbercache as other_pn ON other_pn.typeversion_id=other_to.cached_current_typeversion_id
        			WHERE (other_to.typeobject_id!='{$this->typeobject_id}') AND (other_pn.part_number='{$part_number_slashes}')
        			");
        	        	 
        	return count($records) > 0;
        }

        public function validateFields($fieldnames,&$errormsg) {
        	if (!self::isTypeCategoryAProcedure($this->typecategory_id) && in_array('serial_number_type',$fieldnames) && !is_null($this->serial_number_type)) {
        		$snfields = extract_prefixed_keys($this->getArray(), 'serial_number_');
        		foreach($snfields as $field => $value) {
        			if (in_array($field,$fieldnames)) unset($fieldnames[array_search($field,$fieldnames)]);
        		}
        		$SNType = SerialNumberType::typeFactory($snfields);
        		$SNType->validateSerialNumberType($errormsg);
        	}
        	
        	if (in_array('type_part_number',$fieldnames)) {
	        	if (count(explode('|',$this->type_part_number))!=count(array_unique(explode('|',$this->type_part_number)))) {
	        		$errormsg[] = 'You have duplicate item numbers.  Please make all your part/procedure numbers different.';
	        	}
        		foreach(explode('|',$this->type_part_number) as $idx => $part_number) {
        			if (!$part_number) {
        				$errormsg[] = 'Part/Procedure Number cannot be blank.';
        			} else if ($this->partNumberAlreadyUsed($part_number)) {
	        			$errormsg[] = 'The item number ('.$part_number.') is already in use.  Please use a different one.';
	        		}
        		}
        		unset($fieldnames[array_search('type_part_number',$fieldnames)]);
        	}
        	
        	if (in_array('type_description',$fieldnames)) {
        		foreach(explode('|',$this->type_description) as $idx => $description) {
        			if (!$description) {
        				$errormsg[] = 'Part/Procedure Name cannot be blank.';
        			}
        		}
        		unset($fieldnames[array_search('type_description',$fieldnames)]);
        	}
        	
        	parent::validateFields($fieldnames,$errormsg);
        } 
       
        
        public function formatPrintField($fieldname, $is_html=true, $nowrap=false) {
        	$fieldtype = $this->getFieldType($fieldname);
        	$value = $this->$fieldname;
        	switch($fieldname) {
        		case 'type_part_number' :
        			$values = explode('|',$value);
        			$out = '';
        			foreach($values as $idx => $val) {
        				if ($is_html) {
        					$out .= ($idx > 0 ? '<br />'.$val.' <i>[Alias '.$idx.']</i>' : $val);
        				} else {
        					$out .= ($idx > 0 ? "\r\n".$val.' [Alias '.$idx.']' : $val);
        				}
        			}
        			return $out;
        		case 'type_description' :
        			$values = explode('|',$value);
        			return implode($is_html ? '<br />' : "\r\n",$values);
        		default:
        			return parent::formatPrintField($fieldname, $is_html, $nowrap);
        	}
        } 
        
        /**
         * Delete the last alias
         */
        public function deleteAlias() {
        	$pns = explode('|',$this->type_part_number);
        	$pds = explode('|',$this->type_description);
        	if (count($pns)>1) {
        		unset($pns[count($pns)-1]);
        		unset($pds[count($pds)-1]);
        		$this->type_part_number = implode('|',$pns);
        		$this->type_description = implode('|',$pds);
        	}
        }
        
        /**
         * Add a new alias
         */
        public function addAlias() {
        	$this->type_part_number .= '|';
        	$this->type_description .= '|';
        }   

        /**
         * Smartly formats the general case of params separated by |
         * If $type_description is null, then this assumes we are just formatting the part number only
         * @param string $type_part_number
         * @param string $type_description
         */
        static public function formatPartNumberDescription($type_part_number, $type_description=null) {
        	$ns = explode('|',$type_part_number);
        	$ds = explode('|',$type_description);
        	$numbercomp = count($ns)<5 ? implode(', ',$ns) : implode(', ',array_slice($ns,0,3)).',...,'.$ns[count($ns)-1];
        	$desccomp = $ds[0].(count($ds)>1 ? '...' : '');
        	return is_null($type_description) ? $numbercomp : $numbercomp.' ('.$desccomp.')';
        }
        
        static public function formatSubActiveDefinitionStatus($typedisposition, $versionstatus, $is_current_version) {
        	$statustext = '';
        	$statusclass = '';
        	$definitiondescription = '';
        	if ($typedisposition=='B') {
        		$statustext = 'Obsolete';
        		$statusclass = 'Obsolete';
        		$definitiondescription = 'All version of this definition are Obsolete.  Items that have already been entered into the system are marked Obsolete and no new items of this type can be added to the system.';
        	} else if (!$is_current_version) {
        		if ($versionstatus=='D') {
        			$statustext = 'Draft';
        			$statusclass = 'Draft';
        			$definitiondescription = 'This is a Draft version of the definition.  Users will not be able to add new items to the system using this Draft version until it is made Active by releasing it.';
        		} else if ($versionstatus=='R') {
        			$statustext = 'Review';
        			$statusclass = 'DefReview';
        			$definitiondescription = 'This is a Review version of the definition.  This definition cannot be changed and users cannot add new items to the system using this version until it is made Active by releasing it.';
        		}
        	} else {  // is current version but still these inactive versionstatuses
        		if ($versionstatus=='D') {
        			$statustext = 'Draft';
        			$statusclass = 'Draft';
        			$definitiondescription = 'This is a Draft definition.  Users will not be able to add new items of this type to the system until this draft is made Active by releasing it.';
        		} else if ($versionstatus=='R') {
        			$statustext = 'Review';
        			$statusclass = 'DefReview';
        			$definitiondescription = 'This definition is in Review.  This definition cannot be changed and users cannot add new items of this type to the system until this version is made Active by releasing it.';
        		}
        	}
        	return array($statustext, $statusclass, $definitiondescription);
        }
                
        /**
         * helper for the edit and view pages for the editing the types.  This takes care of figurout out what fields should be shown
         * and what sensible captions should be.         * 
         * @param boolen $editable should we show an edit form or a display table
         * @param boolean $for_pdf do html that is taylored for rendering to PDF output
         * @return string html of the table
         */
        public function fetchHeaderEditTableRowsHtml($editable, $for_pdf) {
        	$is_a_part = $this->typecategory_id==2;
        	$html = '';
        	$html .= fetchEditTableTR(array(array('effective_date')), $this, '', false)."\r\n";
        	$html .= fetchEditTableTR(array(array('typecategory_id')), $this, '', $editable)."\r\n";
        	if ($this->typecategory_id) {
        	
        		if ($editable) {
        			$pns = explode('|',$this->type_part_number);
        			$pds = explode('|',$this->type_description);
        			foreach($pns as $idx => $pn) {
        				$btns = array();
        				$last_entry = ($idx == count($pns)-1);
        				$can_delete_this_alias = $last_entry && (count($pns)>1) && (self::getNumberOfItemsForTypeVersion($this->typeversion_id, $idx)==0);
        				if ($last_entry && $is_a_part) $btns[] = linkify('#', 'Add alias','Add a new alias','minibutton2',"document.theform.btnOnChange.value='addalias'; packupFormVars(); $('form').submit(); return false;");
        				if ($can_delete_this_alias) $btns[] = linkify('#', 'Delete alias','remove this alias','minibutton2',"if (confirm('Delete this alias?')) {document.theform.btnOnChange.value='deletealias'; packupFormVars(); $('form').submit();} return false;");
        				if ($last_entry && (count($pns)>1) && !$can_delete_this_alias) $btns[] = '<span class="paren">Delete is not an option because this alias is being used.</span>';
        				
        				$caption = ($is_a_part ? 'Part Number' : 'Procedure Number').($idx>0 ? ' Alias '.$idx : '');
        				$subcaption = $idx==0 ? ($is_a_part ? '' : '<br><span class="paren">e.g., TP-ABC-BURN-IN</span>') : ''; 
        				$html .= '<tr class="proc_num_row"><th>'.$caption.'<span class="req_field"> *</span>:'.$subcaption.'</th><td colspan="3">
                                  <input class="inputboxclass" type="text" maxlength="64" size="50" value="'.$pn.'" name="type_part_numbers['.$idx.']">
                                  </td></tr>';
        				
        				$caption = ($is_a_part ? 'Part Name' : 'Procedure Name').($idx>0 ? ' Alias '.$idx : '');
        				$subcaption = $idx==0 ? ($is_a_part ? '<br><span class="paren">First Letter Caps</span>' : '<br><span class="paren">First Letter Caps.  Phrase like an action or report name: "Board Calibration" or "Calibration Datasheet"</span>') : '';
        				$html .= '<tr class="proc_name_row"><th>'.$caption.'<span class="req_field"> *</span>:'.$subcaption.'</th><td colspan="3">
                                  <input class="inputboxclass" type="text" maxlength="64" size="50" value="'.(isset($pds[$idx]) ? $pds[$idx] : '').'" name="type_descriptions['.$idx.']">
                                  '.(count($btns)>0 ? '<br />'.implode(' ',$btns) : '').'</td></tr>';
        			}
        		} else {
        			$pns = explode('|',$this->type_part_number);
        			$pds = explode('|',$this->type_description);
        			$nums_and_desc = array();
        			foreach($pns as $idx => $pn) {
        				$nums_and_desc[] = $pn.(isset($pds[$idx]) ? ' ('.$pds[$idx].')' : ''); 
        			}
        			$plural = count($pns) > 1 ? 'Numbers & Names' : 'Number & Name';
        			$partnumslist = $for_pdf ? implode('<br />',$nums_and_desc) : '<p>'.implode('</p><p>',$nums_and_desc).'</p>';
        			$html .= '<tr><th>'.($is_a_part ? 'Part '.$plural : 'Procedure '.$plural).':</th>
						<td colspan="3">'.$partnumslist.'</td>
								</tr>';        			 
        		}

        		
        		if ($is_a_part) {
        			$html .= fetchEditTableTR(array(array('serial_number_type')), $this, '', $editable);
        			$typeversion_digest = $this->getLoadedTypeVersionDigest(true);
        			$SerialNumber = SerialNumberType::typeFactory($typeversion_digest['serial_number_format']);
        			foreach($SerialNumber->getParamCaptions() as $fieldname => $params) {
        				if ($params['used']) {
        					$this->setFieldAttribute($fieldname,'caption',$params['caption']);
        					$this->setFieldAttribute($fieldname,'subcaption',$editable ? $params['subcaption'] : '');
        					$html .= fetchEditTableTR(array(array($fieldname)), $this, '', $editable)."\r\n";
        				}
        			}
        		}
        	}
   
        	return $html;  	
        }
        
        public function fetchFullDefinitionSheetHeader($for_pdf=false) {
        	$username = DBTableRowUser::getFullName($this->user_id);
        	$mod_username = DBTableRowUser::getFullName($this->modified_by_user_id);
        	list($statustext, $statusclass, $definitiondescription) = self::formatSubActiveDefinitionStatus($this->to__typedisposition, $this->versionstatus, $this->isCurrentVersion());
        	$header_html .= '<table class="edittable defheader">
    					 <col class="table_label_width">
    					 <col class="table_value_width">
    					 <col class="table_label_width">
    					 <col class="table_value_width">
    			<tr><th>Author:</th><td colspan="3">'.$username.'</td></tr>'."\r\n".'
    			<tr><th>Modified By:</th><td colspan="3">'.$mod_username.'</td></tr>'."\r\n".'
    			<tr><th>Modified On:</th><td colspan="3">'.$this->formatPrintField('record_modified').'</td></tr>'."\r\n".
        	    			$this->fetchHeaderEditTableRowsHtml(false,$for_pdf).'
    			<tr><th>Type Version ID:</th><td colspan="3">'.$this->typeversion_id.'</td></tr>'."\r\n".'
    			<tr><th>Type Object ID:</th><td colspan="3">'.$this->typeobject_id.'</td></tr>'."\r\n".'
	    		<tr><th>Locator:</th><td colspan="3">'.$this->absoluteUrl().'</td></tr>'."\r\n".'
	    		'.($statustext ? '<tr><th>Status:</th><td colspan="3"><span class="disposition '.$statusclass.'">'.$statustext.'</span></td></tr>'."\r\n" : '').'
	    				</table>';
        	return $header_html;    	
        }
        
        
        public function getFieldTypeGroomedForShow($fieldname, $components_as_links=false, $show_subcaption=false, $show_caption=false) {
        	// these are defaults so we can know not to waste paper when values equal them.
        	$typedigest = $this->getLoadedTypeVersionDigest(false);
        	
        	$defaults = array();
        	$defaults['required'] = 0;
        	$defaults['featured'] = 0;
        	$defaults['unique'] = 0;
        	$defaults['subcaption'] = 0;
        	
        	$fieldtype = array('name' => $fieldname);
        	$fieldtype = array_merge($fieldtype,$typedigest['fieldtypes'][$fieldname]);
        	// unset these since we already show these
        	if (!$show_caption) unset($fieldtype['caption']);
        	if (!$show_subcaption) unset($fieldtype['subcaption']);
        	unset($fieldtype['mode']);  // unset this for legacy reasons.  It is left behind but we don't use it.
        	if (isset($fieldtype['can_have_typeobject_id'])) {
        		$ids = $fieldtype['can_have_typeobject_id'];
        		unset($fieldtype['can_have_typeobject_id']);
        		$fieldtype['Type Object ID'] = array();
        		foreach($ids as $id) {
        			$SubTypeVersion = new DBTableRowTypeVersion();
        			$SubTypeVersion->getCurrentRecordByObjectId($id);
        			$comp_name = TextToHtml(DBTableRowTypeVersion::formatPartNumberDescription($SubTypeVersion->type_part_number,$SubTypeVersion->type_description));
        			$fieldtype['Type Object ID'][$id] = ($components_as_links ? linkify($SubTypeVersion->absoluteUrl(),$comp_name,'view definition for '.$comp_name) : $comp_name);
        		}
        	}
        	
        	if (isset($fieldtype['embedded_in_typeobject_id'])) {
        		$id = $fieldtype['embedded_in_typeobject_id'];
        		$component_name = $fieldtype['component_name'];
        		unset($fieldtype['embedded_in_typeobject_id']);
        		unset($fieldtype['component_name']);
        		$SubTypeVersion = new DBTableRowTypeVersion();
        		$SubTypeVersion->getCurrentRecordByObjectId($id);
        		$comp_name = TextToHtml(DBTableRowTypeVersion::formatPartNumberDescription($SubTypeVersion->type_part_number, $SubTypeVersion->type_description));
        		$fieldtype['Subfield Of'] = $component_name.' ['.$id.'='.($components_as_links ? linkify($SubTypeVersion->absoluteUrl(),$comp_name,'view definition for '.$comp_name) : $comp_name).']';
        	}
        	
        	if (isset($fieldtype['component_subfield'])) {
        		$fieldtype['Subfield Name'] =  $fieldtype['component_subfield'];
        		unset($fieldtype['component_subfield']);
        	}
        	
        	// prune the params that are not defaults to remove clutter
        	foreach($defaults as $defname => $defval) {
        		if (isset($fieldtype[$defname]) && ($fieldtype[$defname]==$defval)) unset($fieldtype[$defname]);
        	}
        	return $fieldtype;
        }
        
        /**
         * This returns html formatted list of the data dictionary parameters for the the specified $fieldname.
         * It is used in the Definition Sheet as documentation of the configuration of a given field.
         * @param string $fieldname the name of the field that you want to definition for.
         * @return string
         */
        public function fetchLayoutFieldParamsHtml($fieldname, $components_as_links=false, $show_subcaption=false) {
                	 
        	$typedigest = $this->getLoadedTypeVersionDigest(false);
        	if (!isset($typedigest['fieldtypes'][$fieldname])) {
	        	return '';
        	} else {
        		
        		$fieldtype = $this->getFieldTypeGroomedForShow($fieldname, $components_as_links, $show_subcaption);
        		
        		$out = '';
        		foreach($fieldtype as $name => $value) {
        			if (is_array($value)) {
        				$vals = array();
        				foreach($value as $k => $v) {
        					$vals[] = $k.'='.$v;
        				}
        				$value = "<br />&nbsp;\r\n".implode("<br />&nbsp;\r\n",$vals);
        			}
        			$out .= '<b>'.$name.':</b> <i>'.$value.'</i><br />'."\r\n";
        		}
        	}
        	return $out;
        }

        /**
         * This outputs a listing of type definition fields,primarily for the types/versions API call.
         * @return array of array.
         */
        public function getExportDefinitionFields() {
        	$out = array();
        	
        	$passthrough_fields = array('typeversion_id','typeobject_id','type_part_number','type_description','effective_date','user_id','login_id','record_created','modified_by_user_id', 'record_modified');
        	foreach($passthrough_fields as $fieldname) {
        		$out[$fieldname] = $this->{$fieldname};
        	}
        	
        	$out['type_category_name'] = $this->tc__typecategory_name;

        	$digest = $this->getLoadedTypeVersionDigest(false);
        	if ($digest['has_a_serial_number']) {
        		$out['serial_number_format'] = $digest['serial_number_format'];
        	}
        	
        	// we only want to write out not standard fields.  The standard ones like item_serial_number are redundent.
        	$dict = array();
        	$allowed_attr = self::typesListing();
        	foreach(array_merge($digest['addon_property_fields'],$digest['addon_component_subfields']) as $fieldname) {
        		$dict[$fieldname] = $digest['fieldtypes'][$fieldname];
        		// get rid of any attributes that are not explicitely in the self::typesListing()
        		if (isset($allowed_attr[ $dict[$fieldname]['type'] ]['parameters'])) { // some weird cases were the type is not defined.  Roll with it.
	        		$dict[$fieldname] = array('type' => $dict[$fieldname]['type']) + array_intersect_key($dict[$fieldname], $allowed_attr[ $dict[$fieldname]['type'] ]['parameters']);
        		}
        	}
        	$comp = array();
        	foreach($digest['addon_component_fields'] as $fieldname) {
        		// unlike the properties we don't prune the list of attributes.  (mainly because we don't have a ready-made listing of allowed attributes for components!)
        		$comp[$fieldname] = $digest['fieldtypes'][$fieldname];
        	}
        	if (count($dict)>0) $out['dictionary'] = $dict;
        	if (count($comp)>0) $out['components'] = $comp;
        	$out['layout'] = $digest['dictionary_field_layout'];
        	
        	return $out;
        } 

        public function formatPartNumbersConcat() {
        	$pns = explode('|',$this->type_part_number);
        	$pds = explode('|',$this->type_description);
        	$nums_and_desc = array();
        	foreach($pns as $idx => $pn) {
        		$nums_and_desc[] = $pn.(isset($pds[$idx]) ? ' ('.$pds[$idx].')' : '');
        	}
        	return implode(', ',$nums_and_desc);        	
        }    
        
        public function fetchLayoutFieldParamsHtmlOneLine($fieldname, $components_as_links=false, $show_subcaption=false, $show_caption=false) {
        	$fieldtype = $this->getFieldTypeGroomedForShow($fieldname, $components_as_links, $show_subcaption, $show_caption);
        	$params = array();
        	foreach($fieldtype as $name => $value) {
        		if (is_array($value)) {
        			$vals = array();
        			foreach($value as $k => $v) {
        				$vals[] = $k.'='.$v;
        			}
        			$value = implode(", ",$vals);
        		}
        		$params[] = $name.': "'.$value.'"';
        	}
        	return implode(', ',$params);
        }
        
        /*
         * Turns the layout structure which is intrinsically two column into a flat
         * (identical to JS function)
        */
        public function layoutToFlatArray() {
        	$typedigest = $this->getLoadedTypeVersionDigest(true);
        	$fieldlayout =  DBTableRowTypeVersion::addDefaultsToAndPruneFieldLayout($typedigest['dictionary_field_layout'],DBTableRowTypeVersion::buildListOfItemFieldNames($typedigest), array());
        	
        	$out = array();
        	foreach($fieldlayout as $row) {
        		if ($row["type"]=='columns') {
        			foreach($row['columns'] as $column) {
        				$outitem = array();
        				$outitem['type'] = 'columns';
        				$outitem['column'] = $column;
        				$outitem['layout-width'] = (count($row['columns'])==1) ? 2 : 1;
        				$out[] = $outitem;
        			}
        		} else {
        			$outitem = $row;
        			$outitem['layout-width'] = 2;
        			$out[] = $outitem;
        		}
        	}
        	return $out;
        }
        
        public function allLayoutColumnNames() {
        	$out = array();
        	foreach($this->layoutToFlatArray() as $row) {
        		if ($row['type']=='columns') {
        			$out[] = $row['column']['name'];
        		}
        	}
        	return $out;
        }
        
        public function allLayoutHtmlBlocks() {
        	$out = array();
        	foreach($this->layoutToFlatArray() as $row) {
        		if ($row['type']=='html') {
        			$out[] = $row['html'];
        		}
        	}
        	return $out;
        }        
        
        /**
         * Use <del> and <ins> tags to annotate the difference between two blocks of text.
         * @param text $was
         * @param text $is
         * @return string the $was text with the markup tags
         */
        static function markupDiffBetweenTextBlocks($was,$is) {
        	require_once("PHPFineDiff/finediff.php");
        	$opcodes = FineDiff::getDiffOpcodes($was, $is, FineDiff::wordDelimiters /* , default granularity is set to character */);
        	return FineDiff::renderDiffToHTMLFromOpcodes($was, $opcodes);
        }        

        /**
         * give this method another object of the same type, and it finds any differences
         * and output them as a textual description of the change.  This is used in generating
         * the difference messages in the EventStream.
         */
        public function typeDifferencesFromHtml(self $CompareItem) {
        	$list = array();
        	
        	// header changes.
        	checkWasChangedField($list ,'Part Number/Description', $CompareItem->formatPartNumbersConcat(), $this->formatPartNumbersConcat());
        	checkWasChangedField($list ,'Item Type', $CompareItem->formatPrintField('typecategory_id'), $this->formatPrintField('typecategory_id'));
        	         	
        	$is_now_a_part = $this->typecategory_id==2;
        	$was_part = $CompareItem->typecategory_id==2;
        	if ($is_now_a_part) {
        		checkWasChangedField($list ,'Serial Number Type', $was_part ? $CompareItem->formatPrintField('serial_number_type') : null, $this->formatPrintField('serial_number_type'));
        		
        		if ($was_part) {
        			$typeversion_digest = $CompareItem->getLoadedTypeVersionDigest(true);
        			$SerialNumber = SerialNumberType::typeFactory($typeversion_digest['serial_number_format']);   
        			$was_params = $SerialNumber->getParamCaptions();
        		}
        		
        		$typeversion_digest = $this->getLoadedTypeVersionDigest(true);
        		$SerialNumber = SerialNumberType::typeFactory($typeversion_digest['serial_number_format']);
        		foreach($SerialNumber->getParamCaptions() as $fieldname => $params) {
        			if ($params['used']) {
        				checkWasChangedField($list ,$params['caption'], ($was_part && $was_params[$fieldname]['used']) ? $CompareItem->formatPrintField($fieldname) : null, $this->formatPrintField($fieldname));
        			}
        		}
        	}
        	 
        	// dictionary items
        	
        	$this_typeversion_digest = $this->getLoadedTypeVersionDigest(false);
        	$this_fieldtypes = $this_typeversion_digest['fieldtypes'];
        	$compare_typeversion_digest = $CompareItem->getLoadedTypeVersionDigest(false);
        	$compare_fieldtypes = $compare_typeversion_digest['fieldtypes'];
        	
        	$this_fields = $this->getFieldnameAllowedForLayout();
        	$compare_fields = $CompareItem->getFieldnameAllowedForLayout();
        	

        	$fields_deleted = array_diff($compare_fields,$this_fields);
        	foreach($fields_deleted as $fieldname) {
        		checkWasChangedDefinition($list ,$compare_fieldtypes[$fieldname]['caption'], $CompareItem->fetchLayoutFieldParamsHtmlOneLine($fieldname,false,true), null);
        	}
        	$fields_added = array_diff($this_fields,$compare_fields);
        	foreach($fields_added as $fieldname) {
        		checkWasChangedDefinition($list ,$this_fieldtypes[$fieldname]['caption'], null, $this->fetchLayoutFieldParamsHtmlOneLine($fieldname,false,true));
        	}
        	
        	foreach(array_intersect($this_fields,$compare_fields) as $fieldname) {
        		// in case the caption itself changed...
        		$show_caption = (strcmp($this_fieldtypes[$fieldname]['caption'], $compare_fieldtypes[$fieldname]['caption']) !== 0);
        		checkWasChangedDefinition($list ,$this_fieldtypes[$fieldname]['caption'], $CompareItem->fetchLayoutFieldParamsHtmlOneLine($fieldname,false,true, $show_caption), $this->fetchLayoutFieldParamsHtmlOneLine($fieldname,false,true, $show_caption));
        	}
        	
        	// layout items
        	
        	
	        $this_layoutfields = $this->allLayoutColumnNames();
	        $compare_layoutfields = $CompareItem->allLayoutColumnNames();
	        
	        if (implode('|',$this_layoutfields)!=implode('|',$compare_layoutfields)) {
	        	if ((count($fields_deleted)==0) && (count($fields_added)==0)) {
	        		$list[] = 'Field Layout Changed';
	        	}
	        }
        	
	        $this_layoutblocks = $this->allLayoutHtmlBlocks();
	        $compare_layoutblocks = $CompareItem->allLayoutHtmlBlocks();
	        
	        $layoutblocks_added = array_diff($this_layoutblocks,$compare_layoutblocks);
	        $layoutblocks_deleted = array_diff($compare_layoutblocks,$this_layoutblocks);
	        foreach($layoutblocks_added as $i => $add_block) {
	        	foreach($layoutblocks_deleted as $j => $del_block) {
	        		$pct_similar = 0.;
	        		similar_text(substr($add_block,0,200),substr($del_block,0,200),$pct_similar);
	        		$is_similar = $pct_similar > 50.;
	        		if ($is_similar) {
	        			unset($layoutblocks_added[$i]);
	        			unset($layoutblocks_deleted[$j]);
	        			$list[] = '<b>Text</b> changed: '.markupDiffBetweenTextBlocks($del_block,$add_block);
	        		} 
	        	}
	        }
	        
	        // for those left, show as adds and deletes
	        foreach($layoutblocks_added as $i => $add_block) {
	        	// show as add
	        	$list[] = '<b>Text</b> added: <ins>'.TextToHtml($add_block).'</ins>';
	        }
	        
	        foreach($layoutblocks_deleted as $j => $del_block) {
	        	// show as delete
	        	$list[] = '<b>Text</b> deleted: <del>'.TextToHtml($del_block).'</del>';
	        }
	         
        	return count($list)>1 ? '<ul class="changelist"><li>'.implode('</li><li>',$list).'</li></ul>' : implode(',',$list);
        }
       
        /**
         * Does various checks to see if items of this typeversion_id can be safely upgraded to the targettypeversion_id
         * without loss of data.  It returns an array of warning messages---empty if it looks ok to go.
         * @param self $TargetTypeVersion
         * @return array of warnings
         */
        public function canVersionBeUpgradedSafelyTo(self $TargetTypeVersion) {
        	$fail_msg = array();
        	$ItemCounts = array_filter($this->getItemInstanceCounts());  // removes fieldnames that are not used
        	$fields_used = array_keys($ItemCounts);
        	
        	
        	// dictionary items
        	 
        	$targ_typeversion_digest = $TargetTypeVersion->getLoadedTypeVersionDigest(false);
        	$targ_fieldtypes = $targ_typeversion_digest['fieldtypes'];
        	$this_typeversion_digest = $this->getLoadedTypeVersionDigest(false);
        	$this_fieldtypes = $this_typeversion_digest['fieldtypes'];
        	 
        	$targ_fields = $TargetTypeVersion->getFieldnameAllowedForLayout();
        	$this_fields = $this->getFieldnameAllowedForLayout();
        	 
        	/*
        	 * Error if fields deleted where there is at least one of these field defined in the items.
        	 */ 
        	
        	$fields_deleted = array_diff($this_fields,$targ_fields);
        	$problem_fields = array_intersect($fields_deleted, $fields_used);
        	if (count($problem_fields)) $fail_msg[] = 'There are fields ('.implode(', ',$problem_fields).') in this version that are not in the target version.';
        	
        	/*
        	 * Error if fields defined in the items, AND type has changed. 
        	 * Or, if an enum, existing option keys are not found in the target defintion.  
        	 * Or, if a component and existing component types are not defined in the target.
        	 */

        	foreach(array_intersect($targ_fields, $this_fields, $fields_used) as $fieldname) {
                $targ_fieldtype = $targ_fieldtypes[$fieldname];
                $this_fieldtype = $this_fieldtypes[$fieldname];
        		if ($targ_fieldtype['type']!=$this_fieldtype['type']) {
        			$fail_msg[] = "The field '{$fieldname}' would change types from ".$this_fieldtype['type'].' to '.$targ_fieldtype['type'].'.';
        		} else {
        			if ($this_fieldtype['type']=='enum') {
        				$targ_keys_defined = is_array($targ_fieldtype['options']) ? array_keys($targ_fieldtype['options']) : array();
        				$this_keys_defined = is_array($this_fieldtype['options']) ? array_keys($this_fieldtype['options']) : array();
        				$keys_have_changed = implode('|',$targ_keys_defined)!=implode('|',$this_keys_defined);
        				if ($keys_have_changed) {
                            $counts = $this->getEnumKeyCounts($fieldname);
                            $sum = array_sum($counts);
		        			$keys_used = array_keys(array_filter($counts));
		        			$keys_without_a_home = array_diff($keys_used,$targ_keys_defined);
		        			if (count($keys_without_a_home)>0) {
		        				$fail_msg[] = "The enum field '{$fieldname}' has {$sum} assigned key values (".implode(', ',$keys_without_a_home).") that are not defined in the target version.";
		        			}
        				}
        			} else if ($this_fieldtype['type']=='component') {
        				$targ_typeobject_ids = $targ_fieldtype['can_have_typeobject_id'];
        				$this_typeobject_ids = $this_fieldtype['can_have_typeobject_id'];
        				if (implode(',',$targ_typeobject_ids)!=implode(',',$this_typeobject_ids)) {
                            $counts = $this->getComponentIDCounts($fieldname);
                            $sum = array_sum($counts);
        					$ids_used = array_keys(array_filter($counts));
                            $ids_without_a_home = array_diff($ids_used,$targ_typeobject_ids);
                            if (count($ids_without_a_home)>0) {
                                $fail_msg[] = "The component '{$fieldname}' has {$sum} assigned key values (Type Object IDs = ".implode(', ',$ids_without_a_home).") that are not defined in the target version.";
                            }
        				}
        			}
        		}
        	}
        	
        	/*
        	 * Error if part number list of aliases has been shrunken.  (this really should be smarter and actually see what aliases are used.)
        	 */
        	
        	if (count(explode('|',$TargetTypeVersion->type_part_number))<count(explode('|',$this->type_part_number))) {
        		$fail_msg[] = "There are fewer Part Number Aliases defined in the target than in this version.";
        	}
        	 
        	return $fail_msg;
        }

        
        static function upgradeItemVersions($from_typeversion_id, $to_typeversion_id) {
        	$records = DbSchema::getInstance()->getRecords('itemversion_id',"SELECT itemversion_id FROM itemversion WHERE typeversion_id='{$from_typeversion_id}'");
        	$count = 0;
        	foreach($records as $itemversion_id => $record) {
        		$IV = new DBTableRowItemVersion();
        		if ($IV->getRecordById($itemversion_id)) {
        			$IV->typeversion_id = $to_typeversion_id;
        			// we are being a little sneaky here: normally this would be called with 'user_id' included so each item would show
        			// that we upgraded the itemversion.  But this is unpleasant because all those versions then appear to be owned by me.
        			//  This is weird, so instead, we leave the original owner's name and just show the change date and version change.
        			$IV->save(array('typeversion_id','record_created'));
        			$count++;
        		}
        	}
        	return $count;
        }
        
        
    }

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

class ReportDataItemListView extends ReportDataWithCategory {
	
	private $can_edit = false;
	private $can_delete = false;
	private $addon_fields_list = array();
	private $export_user_records = array();
	private $is_user_procedure = false;
	private $view_category = '';
	private $output_all_versions = false;
	private $_showing_search_results = false;
	private $_show_proc_matrix = true;
	private $_proc_matrix_column_keys = array();
	private $_override_itemversion_id = null;
	private $_recent_row_age = null;
	private $_has_aliases = false;
	
	/**
	 * If $output_all_versions is true, then there is 1 output row per itemversion_id.  If false, then 1 output row per itemobject_id.
	 * @param boolean $initialize_for_export
	 * @param boolean $output_all_versions
	 * @param boolean $is_user_procedure
	 */
	public function __construct($initialize_for_export=false, $output_all_versions=false, $is_user_procedure=false, $showing_search_results=false, $override_view_category=null, $override_itemversion_id=null, $display_only=false) {
	//	if (!is_null($override_view_category) && ($override_view_category==='')) $override_view_category = '*';
		$this->_showing_search_results = $showing_search_results;
		if ($output_all_versions) {
			parent::__construct('itemversion');
		} else {
			parent::__construct('itemobject');
		}
		$this->pref_view_category_name = $is_user_procedure ? 'pref_proc_view_category' : 'pref_part_view_category';
		$this->is_user_procedure = $is_user_procedure;
		$this->output_all_versions = $output_all_versions;
		$this->_override_itemversion_id = $override_itemversion_id;
		$this->_recent_row_age = Zend_Registry::get('config')->recent_row_age;
		
		// this little dance is to make sure that we get a valid view_category.
		$this->view_category = is_null($override_view_category) ? $_SESSION['account']->getPreference($this->pref_view_category_name) : $override_view_category;
		if ($this->_showing_search_results) $this->view_category = '*';
		if (is_null($this->_override_itemversion_id)) {
			$this->category_array = $this->category_choices_array($_SESSION['account']->getRole());
			$this->view_category = $this->ensure_category($this->view_category);
			$matrix_selector_visible = ($this->view_category!='*') && !$this->is_user_procedure;
			$this->_show_proc_matrix = $_SESSION['account']->getPreference('chkShowProcMatrix') && $matrix_selector_visible && !$this->output_all_versions;
		} else {
			// we are in a special mode when overriding itemversion.
			$this->category_array = array();
			$this->_show_proc_matrix = false;
		}
		
		if (($this->view_category!='*')) {
			$show_all_fields = $_SESSION['account']->getPreference('chkShowAllFields');
			list($this->addon_fields_list,$this->_has_aliases) = $this->getAddOnFieldsForTypeObjectId($this->view_category,$show_all_fields,false,false,$this->is_user_procedure);
		}
		
		$show_early_part_numbers_column = ($this->view_category=='*') || ($this->_showing_search_results);
		$show_associated_sn_column = $this->is_user_procedure && ($this->view_category=='*');
		$show_item_sn_column = !$this->is_user_procedure;
		$show_change_date_column_early = !$initialize_for_export && $this->is_user_procedure;
		$show_change_date_column_late = (!$initialize_for_export && !$this->is_user_procedure) || ($initialize_for_export && !$this->output_all_versions);
		$show_disposition_column = $this->is_user_procedure;
		$show_created_on_date = (!$initialize_for_export && !$this->is_user_procedure) || ($initialize_for_export && !$this->output_all_versions);
		$show_created_by_date = ($initialize_for_export && !$this->output_all_versions);
		$show_proc_matrix_columns = ($this->view_category!='*') && $this->_show_proc_matrix;
		$show_late_part_numbers_column = !$show_early_part_numbers_column && $this->_has_aliases;
		
		
		$this->last_select_class = 'rowlight';
		
		
		$this->title = $this->is_user_procedure ? 'List of Procedures' : 'List of Parts';
		if ($show_early_part_numbers_column) {
			$this->fields['part_number'] 	= array('display'=> ($this->is_user_procedure ? 'Procedure Number' : 'Part Number'),		'key_asc'=>'partnumbercache.part_number,iv__item_serial_number', 'key_desc'=>'partnumbercache.part_number desc,iv__item_serial_number');
			$this->fields['part_description'] 	= array('display'=> ($this->is_user_procedure ? 'Name' : 'Part Name'),		'key_asc'=>'partnumbercache.part_description', 'key_desc'=>'partnumbercache.part_description desc');
		}
		
		if ($show_change_date_column_early) {
			if (!$show_early_part_numbers_column) $this->default_sort_key = 'last_change_date desc';
			$this->fields['last_change_date'] 	= array('display'=>($this->is_user_procedure ? 'Last Change' : 'Last Change'),		'key_asc'=>'last_change_date', 'key_desc'=>'last_change_date desc', 'start_key' => 'key_desc');
			$this->fields['last_changed_by'] 	= array('display'=>($this->is_user_procedure ? 'User' : 'Changed By'),		'key_asc'=>'last_changed_by', 'key_desc'=>'last_changed_by desc');
		}
		

		if ($show_associated_sn_column) $this->fields['component_serial_numbers'] 	= array('display'=> 'Associated Serial Number(s)',		'key_asc'=>'component_serial_numbers', 'key_desc'=>'component_serial_numbers desc');
		if ($show_item_sn_column) {
			if ($display_only && !$show_early_part_numbers_column && !$show_change_date_column_early) $this->default_sort_key = 'iv__item_serial_number desc';
			$this->fields['iv__item_serial_number'] = array('display'=> 'Item Serial Number', 'key_asc'=>'iv__item_serial_number', 'key_desc'=>'iv__item_serial_number desc');
		}

		if ($show_late_part_numbers_column) {
			$this->fields['part_number'] 	= array('display'=> ($this->is_user_procedure ? 'Procedure Number' : 'Part Number'),		'key_asc'=>'partnumbercache.part_number,iv__item_serial_number', 'key_desc'=>'partnumbercache.part_number desc,iv__item_serial_number');
			$this->fields['part_description'] 	= array('display'=> ($this->is_user_procedure ? 'Name' : 'Part Name'),		'key_asc'=>'partnumbercache.part_description', 'key_desc'=>'partnumbercache.part_description desc');
		}
		
		
		if (($this->view_category!='*')) {
			foreach($this->addon_fields_list as $fieldname => $fieldtype) {
				$this->fields[$fieldname]    = array('display' => $fieldtype['caption']);
			}
		}
		
		if ($show_proc_matrix_columns) {
			$type_records_refer_to_us = $this->getProcedureRecordsForTheCategory($this->view_category);
			foreach($type_records_refer_to_us as $proctyperec) {
				$key = 'ref_procedure_typeobject_id_'.$proctyperec['typeobject_id'];
				$obs = $proctyperec['typedisposition']=='B' ? ' [Obsolete]' : '';
				$this->fields[$key] = array('display' => $proctyperec['type_description'].$obs);
				$this->_proc_matrix_column_keys[] = $key;
			}
		}
		
		if ($show_disposition_column) {
			$this->fields['iv__disposition'] 	= array('display'=> 'Disposition',		'key_asc'=>'iv__disposition', 'key_desc'=>'iv__disposition desc');
		}
		
		
		// dates
		if ($initialize_for_export) {
			$this->fields['iv__effective_date'] 	= array('display'=>($this->is_user_procedure ? 'Completed on Date' : 'Effective Date'),		'key_asc'=>'iv__effective_date', 'key_desc'=>'iv__effective_date desc', 'start_key' => 'key_desc');
			$this->fields['modified_by_name'] 	= array('display'=>($this->is_user_procedure ? 'User' : 'Modified By'),		'key_asc'=>'modified_by_name', 'key_desc'=>'modified_by_name desc');
			if (!$this->output_all_versions) {
				$this->fields['last_comment_date'] 	= array('display' => 'Last Comment Date');
				$this->fields['last_ref_date'] 	= array('display' => 'Last Reference Date');				
							
			}
		}
		
		if ($show_created_on_date) $this->fields['first_ref_date'] 	= array('display' => 'Created On Date','key_asc'=>'first_ref_date', 'key_desc'=>'first_ref_date desc', 'start_key' => 'key_desc');
		if ($show_created_by_date) $this->fields['created_by'] 	= array('display' => 'Created By');	
		
		if ($show_change_date_column_late) {
			$this->fields['last_change_date'] 	= array('display'=>($this->is_user_procedure ? 'Last Change' : 'Last Change'),		'key_asc'=>'last_change_date', 'key_desc'=>'last_change_date desc', 'start_key' => 'key_desc');
			$this->fields['last_changed_by'] 	= array('display'=>($this->is_user_procedure ? 'User' : 'Changed By'),		'key_asc'=>'last_changed_by', 'key_desc'=>'last_changed_by desc');
		}
					
		if ($initialize_for_export) {
			foreach($this->fields as $field => $params) {
				$this->csvfields[$field] = $params['display'];
			}
			if ($this->view_category!='*') {
				list($this->all_fields,$has_aliases) = $this->getAddOnFieldsForTypeObjectId($this->view_category,true,true,true);
				foreach($this->all_fields as $fieldname => $fieldtype) {
					$comp_suffix = $fieldtype['type']=='component' ? ' (to/'.implode('|',$fieldtype['can_have_typeobject_id']).')' : '';
					if (str_contains($fieldname, '.')) {
						$a = explode('.',$fieldname);
						$this->csvfields[$fieldname] = $a[0].'->'.$fieldtype['caption'].$comp_suffix;
					} else {
						$this->csvfields[$fieldname]    = $fieldtype['caption'].$comp_suffix;
					}
				}
			}
			$this->csvfields['itemobject_id'] = 'itemobject_id';
			$this->csvfields['itemversion_id'] = 'itemversion_id';
			$this->csvfields['typeversion_id'] = 'typeversion_id';
			$this->csvfields['user_id'] = 'user_id';
			
			$this->export_user_records = DbSchema::getInstance()->getRecords('user_id',"SELECT * FROM user");
			
		}
		
		$this->search_box_label = $this->is_user_procedure ? 'proc. number, SN, or locator' : 'part number, SN, or locator';
	}
	
	public function getViewCategory() {
		return $this->view_category;
	}
	
	protected function getProcedureRecordsForTheCategory($typeobject_id) {
		$TypeObject = DbSchema::getInstance()->dbTableRowObjectFactory('typeobject',false,'');
		$TypeObject->getRecordById($typeobject_id);
		return getTypesThatReferenceThisType($TypeObject->cached_current_typeversion_id);
	}


	/**
	 * gets all the fieldnames for this typeobject_id.  If requested (not request), gets only featured fields.
	 * If there is more than one typeversion, it will amalgamate all fields from all typeversions for the specified
	 * $typeobject_id.
	 * @param integer $typeobject_id
	 * @param boolean $featured_fields_only
	 * @return multitype:
	 */
	
	protected function getAddOnFieldsForTypeObjectId($typeobject_id, $get_nonfeatured_local_fields, $get_subfields, $get_header_fields, $force_include_components=false) {
		$TypeObject = DbSchema::getInstance()->dbTableRowObjectFactory('typeversion',false,'');
		$DBTableRowQuery = new DBTableRowQuery($TypeObject);
		$DBTableRowQuery->addAndWhere("and typeversion.typeobject_id='".$typeobject_id."'");
		$typerecords = DbSchema::getInstance()->getRecords('',$DBTableRowQuery->getQuery());
		$has_aliases = false;
		$out = array();
		foreach($typerecords as $typerecord) {
			$TypeVersion = DbSchema::getInstance()->dbTableRowObjectFactory('typeversion',false,'');
			if ($TypeVersion->getRecordById($typerecord['typeversion_id'])) {
				$out = array_merge($out,($get_nonfeatured_local_fields ? $TypeVersion->getItemFieldTypes(true,$get_header_fields, $force_include_components) : $TypeVersion->getItemFieldTypes(false,$get_header_fields, $force_include_components)));
				if ($TypeVersion->partnumber_count > 1) $has_aliases = true;
			}
			if ($get_subfields) {
				$out = array_merge($out,DBTableRowTypeVersion::getAllPossibleComponentExtendedFieldNames($typerecord['typeobject_id']));
			}
		}
		return array($out,$has_aliases);
	}
	
	public function getSearchAndWhere($search_string,$DBTableRowQuery) {
		$and_where = '';
		if (!is_null($this->_override_itemversion_id) && is_numeric($this->_override_itemversion_id)) {
			$and_where .=  " and (itemversion_id='{$this->_override_itemversion_id}')";
		}
		$and_where .=  $this->is_user_procedure ? " and (typecategory.is_user_procedure='1')" : " and (typecategory.is_user_procedure!='1')";
		if ($search_string) {
			$like_value = fetch_like_query($search_string,'%','%');
			$or_arr = array();
			$or_arr[] = "currtypeversion.type_part_number {$like_value}";
			if ($this->is_user_procedure) {
				$or_arr[] = "EXISTS (SELECT *
					FROM itemcomponent					
					LEFT JOIN itemobject AS io_them ON io_them.itemobject_id=itemcomponent.has_an_itemobject_id
					LEFT JOIN itemversion AS iv_them ON iv_them.itemversion_id=io_them.cached_current_itemversion_id						
					WHERE (itemcomponent.belongs_to_itemversion_id=itemobject.cached_current_itemversion_id) and (iv_them.item_serial_number {$like_value}))";
			} else {
				if ($this->output_all_versions) {
					$or_arr[] = "itemversion.item_serial_number {$like_value}";
				} else {
					$or_arr[] = "{$DBTableRowQuery->getJoinAlias('itemversion')}.item_serial_number {$like_value}";
				}
			}
			$or = implode(' or ', $or_arr);
			$and_where .= " and ($or)";
		}  else {
			if ($this->view_category!='*') {
				$and_where .= " and (currtypeversion.typeobject_id='{$this->view_category}')";
			} elseif ($_SESSION['account']->hasLimitedVisibilityOfTypes()) {
				$ids_codes = $_SESSION['account']->getDataTerminalObjectIds();
				if (count($ids_codes)>0) {
					$and_where .= " and (currtypeversion.typeobject_id IN (".implode(',',$ids_codes)."))";
				}
			}
		}
        return $and_where;
    }
    
    protected function category_choices_array($role) {
    	$fulllist = DBTableRowTypeVersion::getPartNumbersWAliasesAllowedToUser($_SESSION['account'],$this->is_user_procedure);
    	$options = array();
    	$favorites = $_SESSION['account']->getNumericFavorites($this->pref_view_category_name.'_fav');
    	$cnt = 0;
    	foreach($favorites as $io_fav) {
    		if (isset($fulllist[$io_fav])) {
    			$options += array('fav'.$io_fav => $fulllist[$io_fav]);
    			$cnt++;
    		}
    	}
    	if ($cnt>0) $options += array('' => '');
    	$options += array('*' => ($this->is_user_procedure ? 'All Procedures' : 'All Parts'));
    	$options = $options + $fulllist;
    	return $options;
    }

    // ensures returned category is reasonable and if not, sets it to a good default
    public function ensure_category($category) {
    	if (!is_numeric($category) && ($category!='*')) {
    		preg_match('/^fav([0-9]+)$/',$category,$out);
    		if (isset($out[1])) $category = $out[1];
    	}
    	return parent::ensure_category($category);
    }    
    
    protected function addExtraJoins(&$DBTableRowQuery, $skipDateProcessing=false) {
    	if ($this->output_all_versions) {
    		// add type version info
    		$DBTableRowQuery->addJoinClause("LEFT JOIN typeversion as currtypeversion on currtypeversion.typeversion_id = itemversion.typeversion_id")
    						->addSelectFields('currtypeversion.typeobject_id,currtypeversion.type_part_number,currtypeversion.type_description, itemversion.item_serial_number as iv__item_serial_number');
    		
    		$DBTableRowQuery->addJoinClause("LEFT JOIN partnumbercache ON partnumbercache.typeversion_id=itemversion.typeversion_id AND partnumbercache.partnumber_alias=itemversion.partnumber_alias")
				    		->addSelectFields('partnumbercache.part_number, partnumbercache.part_description');   		
    		
    		// add typecategory info
    		$DBTableRowQuery->addJoinClause("LEFT JOIN typecategory on typecategory.typecategory_id = currtypeversion.typecategory_id")
    						->addSelectFields('typecategory.is_user_procedure');
    		
			// add user's name
			$DBTableRowQuery->addJoinClause("LEFT JOIN user on user.user_id = itemversion.user_id")
							->addSelectFields("TRIM(CONCAT(user.first_name,' ',user.last_name)) as modified_by_name");
    	} else {
    	
			// add type version info
			$DBTableRowQuery->addJoinClause("LEFT JOIN typeversion as currtypeversion on currtypeversion.typeversion_id = {$DBTableRowQuery->getJoinAlias('itemversion')}.typeversion_id")
							->addSelectFields('currtypeversion.typeobject_id,currtypeversion.type_part_number,currtypeversion.type_description');

			$DBTableRowQuery->addJoinClause("LEFT JOIN partnumbercache ON partnumbercache.typeversion_id={$DBTableRowQuery->getJoinAlias('itemversion')}.typeversion_id AND partnumbercache.partnumber_alias={$DBTableRowQuery->getJoinAlias('itemversion')}.partnumber_alias")
							->addSelectFields('partnumbercache.part_number, partnumbercache.part_description');
			
			// add typecategory info
			$DBTableRowQuery->addJoinClause("LEFT JOIN typecategory on typecategory.typecategory_id = currtypeversion.typecategory_id")
			->addSelectFields('typecategory.is_user_procedure');
			
			// add user's name
			$DBTableRowQuery->addJoinClause("LEFT JOIN user on user.user_id = {$DBTableRowQuery->getJoinAlias('itemversion')}.user_id")
							->addSelectFields("TRIM(CONCAT(user.first_name,' ',user.last_name)) as modified_by_name");
			
			/*
			 * This is to include the latest change date and person in the output.  This is done by chosing the latest of three dates: last_comment_date, last_ref_date, last_effective_date
			 * To do the comparison in sql need (among other things) this logic:
			 * 
			 * IF ( AA IS NULL OR  AA <= CC, IF (BB IS NULL OR BB <= CC, CC, BB ), IF (BB IS NULL OR BB <= AA, AA, BB) )
			 * 
			 * This handles the case where AA or BB can be null (i.e., no comments or referenced components)
			 * 
			 * AA = last_comment_tbl.bb_comment_added      =>   TRIM(CONCAT(last_comment_tbl.bb_user_first_name,' ',last_comment_tbl.bb_user_last_name))
			 * BB = last_ref_tbl.bb_iv_effective_date     =>    TRIM(CONCAT(last_ref_tbl.bb_iv_user_first_name,' ',last_ref_tbl.bb_iv_user_last_name))
			 * CC = {$iv_alias}.effective_date            =>    TRIM(CONCAT(user.first_name,' ',user.last_name))
			 */

			if (!$skipDateProcessing) {
				$iv_alias = $DBTableRowQuery->getJoinAlias('itemversion');
				$DBTableRowQuery->addSelectFields("
										IF ( itemobject.cached_last_comment_date IS NULL OR  itemobject.cached_last_comment_date <= {$iv_alias}.effective_date, 
											IF (itemobject.cached_last_ref_date IS NULL OR itemobject.cached_last_ref_date <= {$iv_alias}.effective_date, {$iv_alias}.effective_date, itemobject.cached_last_ref_date ), 
											IF (itemobject.cached_last_ref_date IS NULL OR itemobject.cached_last_ref_date <= itemobject.cached_last_comment_date, itemobject.cached_last_comment_date, itemobject.cached_last_ref_date) ) as last_change_date,
										IF ( itemobject.cached_last_comment_date IS NULL OR  itemobject.cached_last_comment_date <= {$iv_alias}.effective_date, 
											IF (itemobject.cached_last_ref_date IS NULL OR itemobject.cached_last_ref_date <= {$iv_alias}.effective_date, TRIM(CONCAT(user.first_name,' ',user.last_name)), itemobject.cached_last_ref_person ), 
											IF (itemobject.cached_last_ref_date IS NULL OR itemobject.cached_last_ref_date <= itemobject.cached_last_comment_date, itemobject.cached_last_comment_person, itemobject.cached_last_ref_person) ) as last_changed_by,
										itemobject.cached_last_comment_date as last_comment_date,
										itemobject.cached_last_ref_date as last_ref_date,
										itemobject.cached_first_ver_date as first_ref_date,
										itemobject.cached_created_by as created_by,
										{$iv_alias}.effective_date as last_effective_date");
			}
			

    	}
		/*
		 * might also want to left join any components linked to us
		 */
		
		
	}
	
	public function get_records($queryvars, $searchstr,$limitstr) {
		
		if ($this->output_all_versions) {
			$DBTableRowQuery = new DBTableRowQuery($this->dbtable);
			$DBTableRowQuery->addSelectFields('itemversion.effective_date as iv__effective_date, itemversion.itemversion_id as iv__itemversion_id, itemversion.disposition as iv__disposition');
			$DBTableRowQuery->setOrderByClause("ORDER BY {$this->get_sort_key($queryvars,true)}")
							->setLimitClause($limitstr);
			
			$DBTableRowQuery->addAndWhere($this->getSearchAndWhere($searchstr,$DBTableRowQuery));
			/*
			 * for the current itemversion, stich together serial numbers.  Tje 
			 */
			$DBTableRowQuery->addSelectFields("
						(SELECT GROUP_CONCAT(iv_them.item_serial_number) 
						FROM itemcomponent
						LEFT JOIN itemobject AS io_them ON io_them.itemobject_id=itemcomponent.has_an_itemobject_id
						LEFT JOIN itemversion AS iv_them ON iv_them.itemversion_id=io_them.cached_current_itemversion_id
						WHERE itemcomponent.belongs_to_itemversion_id=itemversion.itemversion_id) as component_serial_numbers"
			);
			$this->addExtraJoins($DBTableRowQuery);
		} else {
			$DBTableRowQuery = new DBTableRowQuery($this->dbtable);
			$DBTableRowQuery->setOrderByClause("ORDER BY {$this->get_sort_key($queryvars,true)}")
							->setLimitClause($limitstr)
							->addAndWhere($this->getSearchAndWhere($searchstr,$DBTableRowQuery));
			/*
			 * display the related component serial number(s) for the current itemobject.  I don't think the
			 * concats will do anything here.
			 */
			$DBTableRowQuery->addSelectFields("
						(SELECT GROUP_CONCAT(iv_them.item_serial_number) 
						FROM itemcomponent
						LEFT JOIN itemobject AS io_them ON io_them.itemobject_id=itemcomponent.has_an_itemobject_id
						LEFT JOIN itemversion AS iv_them ON iv_them.itemversion_id=io_them.cached_current_itemversion_id
						WHERE itemcomponent.belongs_to_itemversion_id=itemobject.cached_current_itemversion_id) as component_serial_numbers"
			);
			
			if ($this->_show_proc_matrix) {

				// we will only see the latest version of the procedure
				$DBTableRowQuery->addSelectFields("
						(SELECT GROUP_CONCAT(CONCAT(tv_proc.typeobject_id,',',iv_proc.itemobject_id,',',iv_proc.disposition,',',io_proc.cached_first_ver_date) ORDER BY io_proc.cached_first_ver_date SEPARATOR ';')
						FROM itemcomponent
						LEFT JOIN itemversion AS iv_proc ON iv_proc.itemversion_id=itemcomponent.belongs_to_itemversion_id
						LEFT JOIN typeversion AS tv_proc ON tv_proc.typeversion_id=iv_proc.typeversion_id
						LEFT JOIN typecategory as tc_proc ON tc_proc.typecategory_id=tv_proc.typecategory_id
						LEFT JOIN itemobject AS io_proc ON io_proc.itemobject_id=iv_proc.itemobject_id
						WHERE (itemcomponent.has_an_itemobject_id=itemobject.itemobject_id and tc_proc.is_user_procedure='1') && (iv_proc.itemversion_id=io_proc.cached_current_itemversion_id)) as all_procedure_object_ids"
				);
			}
			
			$this->addExtraJoins($DBTableRowQuery);
		}
		return DbSchema::getInstance()->getRecords('',$DBTableRowQuery->getQuery());
	}

	public function get_records_count(&$queryvars, $searchstr) {
		$DBTableRowQuery = new DBTableRowQuery($this->dbtable);
		$DBTableRowQuery->addAndWhere( $this->getSearchAndWhere($searchstr,$DBTableRowQuery) );
		$this->addExtraJoins($DBTableRowQuery, true);
		$DBTableRowQuery->setSelectFields('count(*)');
		$records = DbSchema::getInstance()->getRecords('',$DBTableRowQuery->getQuery());
		$record = reset($records);
		return $record['count(*)'];
	}
	
	public function make_directory_detail($queryvars, &$record,&$buttons_arr,&$detail_out,UrlCallRegistry $navigator) {
		parent::make_directory_detail($queryvars, $record,$buttons_arr,$detail_out,$navigator);
		$query_params = array();
		$query_params['itemversion_id'] = $record['iv__itemversion_id'];
        $query_params['return_url'] = $navigator->getCurrentViewUrl();
        $query_params['resetview'] = 1;
        $edit_url = $navigator->getCurrentViewUrl('itemview','',$query_params);
        // the following links have superfluis table params--oh well.
        $buttons_arr[] = linkify( $edit_url, 'View', 'View','listrowlink');
        		
		foreach(array_keys($this->display_fields($navigator,$queryvars)) as $fieldname) {
			$detail_out[$fieldname] = isset($record[$fieldname]) ? TextToHtml($record[$fieldname]) : null;
		}
		
		$detail_out['iv__item_serial_number'] = linkify( $edit_url, $record['iv__item_serial_number'], 'View');
		$detail_out['type_part_number'] = TextToHtml(DBTableRowTypeVersion::formatPartNumberDescription($record['type_part_number']));
		
		$last_change_date_str = date('M j, Y G:i',strtotime($record['last_change_date']));
		$detail_out['last_change_date'] = empty($record['last_change_date']) ? '' : ($this->is_user_procedure ? linkify($edit_url,$last_change_date_str,'View') : $last_change_date_str);
		$detail_out['first_ref_date'] = empty($record['first_ref_date']) ? '' : date('M j, Y G:i',strtotime($record['first_ref_date']));
		
		// used for the csv export of all versions
		$detail_out['iv__effective_date'] = empty($record['iv__effective_date']) ? '' : date('M j, Y G:i',strtotime($record['iv__effective_date']));
		
		$record_is_not_selected_category = ($this->view_category!='*') && ($this->view_category!=$record['typeobject_id']);
		
		// It would be a little more efficient if I only instantiated $ItemVersion if I needed it but more fragile.
		
		$need_to_load_ItemVersion = (count($this->addon_fields_list)>0) || $this->is_user_procedure;
		
		$errormsg = array();
		if ($need_to_load_ItemVersion) {
			$ItemVersion = DbSchema::getInstance()->dbTableRowObjectFactory('itemversion',false,'');
			$ItemVersion->_navigator = $navigator;
			$ItemVersion->getRecordById($record['iv__itemversion_id']);
			$ItemVersion->validateFields($ItemVersion->getSaveFieldNames(),$errormsg);
			$ItemVersion->applyDictionaryOverridesToFieldTypes();
		}
		
		if ($need_to_load_ItemVersion && (count($this->addon_fields_list)>0)) {
			foreach($this->addon_fields_list as $fieldname => $fieldtype) {
				if (isset($ItemVersion->{$fieldname})) {
					$detail_out[$fieldname] = $ItemVersion->formatPrintField($fieldname, true, false, true);
				}
				$fieldtype2 = $ItemVersion->getFieldType($fieldname);
				if (isset($fieldtype2['error']) || isset($errormsg[$fieldname])) {
					$detail_out['td_class'][$fieldname] = 'cell_error';
				}
				// mark the fields that are really not defined for this type
				if ($record_is_not_selected_category) {
					$detail_out['td_class'][$fieldname] = 'na';
				}
			}
		}
		
		if (count($this->_proc_matrix_column_keys)>0) {
			$matrix_query_params = $query_params;
			unset($matrix_query_params['itemversion_id']);
			$out = array();
			$ref_recs = explode(';',$record['all_procedure_object_ids']);
			foreach($ref_recs as $ref_rec) {
				$proc_arr = explode(',',$ref_rec);
				if (count($proc_arr)==4) {
					list($proc_to,$proc_io,$proc_disposition,$proc_effective_date) = $proc_arr;
					$key = 'ref_procedure_typeobject_id_'.$proc_to;
					if (!isset($out[$key])) {
						$out[$key] = array();
					}
					$matrix_query_params['itemobject_id'] = $proc_io;
					$edit_url = $navigator->getCurrentViewUrl('itemview','',$matrix_query_params);
					$title = date('M j, Y G:i',strtotime($proc_effective_date)).' - '.(isset($this->fields[$key]['display']) ? $this->fields[$key]['display'] : '');
					$out[$key][] = linkify($edit_url,DBTableRowItemVersion::renderDisposition($this->dbtable->getFieldType('iv__disposition'),$proc_disposition,true,'<span class="disposition Black">No Disposition</span>'),$title);
				}
			}
			// this outputs the procedures into each cell, but colors background dark if the wrong category (typeobject_id)
			foreach($this->_proc_matrix_column_keys as $key) {
				if (isset($out[$key])) {
					$detail_out[$key] = implode(' ',$out[$key]);
				}
				if ($record_is_not_selected_category) {
					$detail_out['td_class'][$key] = 'na';
				}
			}
			foreach($out as $key => $disp_array) {
				$detail_out[$key] = implode(' ',$disp_array);
			}
						
		}
		$detail_out['iv__disposition'] = DBTableRowItemVersion::renderDisposition($this->dbtable->getFieldType('iv__disposition'),$record['iv__disposition']);
		if ($need_to_load_ItemVersion && isset($errormsg['disposition'])) $detail_out['td_class']['iv__disposition'] = 'cell_error';
		
		$detail_out['tr_class'] .= DBTableRow::wasItemTouchedRecently('itemversion'.$record['typeobject_id'], $record['iv__itemversion_id']) ? ' '.$this->last_select_class : '';
		$recently_changed_row = script_time() - strtotime($record['last_change_date']) < $this->_recent_row_age;
		if ($recently_changed_row) {
			$detail_out['tr_class'] .= ' recently_changed_row';
			$detail_out['td_class']['last_change_date'] = 'em';
		}
		
	}
	
	public function make_export_detail($queryvars, &$record,&$detail_out) {
		foreach($this->csvfields as $field => $description) {
			$detail_out[$field] = isset($record[$field]) ? $record[$field] : null;
		}
		$ItemVersion = DbSchema::getInstance()->dbTableRowObjectFactory('itemversion',false,'');
		$ItemVersion->getRecordById($record['iv__itemversion_id']);
		foreach($ItemVersion->getFieldTypes() as $fieldname => $fieldtype) {
			if (isset($ItemVersion->{$fieldname}) && (trim($ItemVersion->{$fieldname})!=='')) {
				if ($fieldtype['type']=='component') {
					$value_array = $ItemVersion->getComponentValueAsArray($fieldname);
					$detail_out[$fieldname] = $value_array[$ItemVersion->{$fieldname}];
				} else {
					$detail_out[$fieldname] = $ItemVersion->{$fieldname};
				}
				
				// if this is a component, then we want to drill deep and get subfields.
				if ($fieldtype['type']=='component') {
					$SubIV = DbSchema::getInstance()->dbTableRowObjectFactory('itemversion',false,'');
					$SubIV->getCurrentRecordByObjectId($ItemVersion->{$fieldname},$ItemVersion->effective_date);
					foreach($SubIV->getFieldTypes() as $subfieldname => $subfieldtype) {
						if ($subfieldtype['type']=='component') {
							$value_array = $SubIV->getComponentValueAsArray($subfieldname);
							$detail_out[DBTableRowTypeVersion::formatSubfieldPrefix($fieldname, $SubIV->tv__typeobject_id).'.'.$subfieldname] = $value_array[$SubIV->{$subfieldname}];
						} else {				
							$detail_out[DBTableRowTypeVersion::formatSubfieldPrefix($fieldname, $SubIV->tv__typeobject_id).'.'.$subfieldname] = $SubIV->{$subfieldname};
						}
					}
				}
			}
		}
		
		if (count($this->_proc_matrix_column_keys)>0) {
			$out = array();
			$ref_recs = explode(';',$record['all_procedure_object_ids']);
			foreach($ref_recs as $ref_rec) {
				list($proc_to,$proc_io,$proc_disposition,$proc_effective_date) = explode(',',$ref_rec);
				$key = 'ref_procedure_typeobject_id_'.$proc_to;
				if (!isset($out[$key])) {
					$out[$key] = array();
				}
				$out[$key][] = DBTableRowItemVersion::renderDisposition($this->dbtable->getFieldType('iv__disposition'),$proc_disposition,false,'N/A');
			}
			foreach($out as $key => $disp_array) {
				$detail_out[$key] = implode(';',$disp_array);
			}		
		}		

		if (isset($this->export_user_records[$ItemVersion->user_id])) {
			$detail_out['user_id'] = $this->export_user_records[$ItemVersion->user_id]['login_id'];
		}
		
		return true;
	}
	
	
}

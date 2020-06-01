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

class ReportDataPartListView extends ReportDataWithCategory {
	
//	private $can_edit = false;
	private $_recent_row_age = null;
	
	public function __construct() {
		parent::__construct('typeobject');
		
		$this->last_select_class = 'rowlight';
		$this->_recent_row_age = Zend_Registry::get('config')->recent_row_age;
		
		$this->title = 'List of Definitions';
		$this->fields['part_number'] 	= array('display'=>'Number',		'key_asc'=>'part_number', 'key_desc'=>'part_number desc');
		$this->fields['part_description'] 	= array('display'=>'Name',		'key_asc'=>'part_description', 'key_desc'=>'part_description desc');
		$this->fields['typecategory_name'] 	= array('display'=>'Type',		'key_asc'=>'typecategory_name', 'key_desc'=>'typecategory_name desc');
		$this->fields['sn_type'] 	= array('display'=>'Serial Number<br  />Helper Caption');
		$this->fields['next_sn'] 	= array('display'=>'Next<br />Serial Number');
		$this->fields['tv__record_modified'] 	= array('display'=>'Modified Date',		'key_asc'=>'tv__record_modified', 'key_desc'=>'tv__record_modified desc', 'start_key' => 'key_desc');
		$this->fields['modified_by_name'] 	= array('display'=>'Modified By',		'key_asc'=>'modified_by_name', 'key_desc'=>'modified_by_name desc');
		$this->fields['item_count'] 	= array('display'=>'Item<br />Count',		'key_asc'=>'item_count', 'key_desc'=>'item_count desc', 'start_key' => 'key_desc');
		$this->fields['linked_parts'] 	= array('display'=>'Linked<br />Parts',		'key_asc'=>'linked_parts', 'key_desc'=>'linked_parts desc', 'start_key' => 'key_desc');
		$this->fields['linked_procedures'] 	= array('display'=>'Linked<br />Procs',		'key_asc'=>'linked_procedures', 'key_desc'=>'linked_procedures desc', 'start_key' => 'key_desc');
		
		$this->search_box_label = 'part number, desc., or locator';
	}
        
	public function getSearchAndWhere($search_string,$DBTableRowQuery) {
		$and_where = '';
		if ($search_string) {
			$or_arr = array();
			$like_value = fetch_like_query($search_string,'%','%');
			$or_arr[] = "partnumbercache.part_number {$like_value}";
			$or_arr[] = "partnumbercache.part_description {$like_value}";
			$or = implode(' or ', $or_arr);
			$and_where .= " and ($or)";
		} 
        return $and_where;
    }
    
    protected function addExtraJoins(&$DBTableRowQuery) {

		// add user's name
		$DBTableRowQuery->addJoinClause("LEFT JOIN user on user.user_id = {$DBTableRowQuery->getJoinAlias('typeversion')}.modified_by_user_id")
						->addSelectFields("TRIM(CONCAT(user.first_name,' ',user.last_name)) as modified_by_name");
		// add type (Part or Procedure)
		$DBTableRowQuery->addJoinClause("LEFT JOIN typecategory on typecategory.typecategory_id = {$DBTableRowQuery->getJoinAlias('typeversion')}.typecategory_id")
						->addSelectFields("typecategory.is_user_procedure, typecategory.typecategory_name");
		$DBTableRowQuery->addSelectFields("(SELECT count(*)	FROM typeobject as typeobjecttarg, typeversion as typeversiontarg, typecomponent_typeobject, typecomponent, typecategory
							WHERE (typeobject.typeobject_id=typecomponent_typeobject.can_have_typeobject_id)
							AND (typecomponent.typecomponent_id=typecomponent_typeobject.typecomponent_id)
							AND (typecomponent.belongs_to_typeversion_id=typeversiontarg.typeversion_id)
							AND (typeversiontarg.typeversion_id=typeobjecttarg.cached_current_typeversion_id)
							AND (typeversiontarg.typecategory_id=typecategory.typecategory_id)
							AND (typecategory.is_user_procedure=1)) as linked_procedures");
		$DBTableRowQuery->addSelectFields("(SELECT count(*)	FROM typeobject as typeobjecttarg, typeversion as typeversiontarg, typecomponent_typeobject, typecomponent, typecategory
							WHERE (typeobject.typeobject_id=typecomponent_typeobject.can_have_typeobject_id)
							AND (typecomponent.typecomponent_id=typecomponent_typeobject.typecomponent_id)
							AND (typecomponent.belongs_to_typeversion_id=typeversiontarg.typeversion_id)
							AND (typeversiontarg.typeversion_id=typeobjecttarg.cached_current_typeversion_id)
							AND (typeversiontarg.typecategory_id=typecategory.typecategory_id)
							AND (typecategory.is_user_procedure=0)) as linked_parts");
		$DBTableRowQuery->addSelectFields("cached_item_count as item_count");
		// add muliplicity of part numbers
		$DBTableRowQuery->addJoinClause("LEFT JOIN partnumbercache on partnumbercache.typeversion_id = typeobject.cached_current_typeversion_id")
						->addSelectFields("partnumbercache.*, (SELECT GROUP_CONCAT( png.part_number SEPARATOR ', ') FROM partnumbercache png WHERE png.typeversion_id=typeobject.cached_current_typeversion_id AND (png.partnumber_alias!=partnumbercache.partnumber_alias) ORDER BY png.part_number) as aliasparts");
		
    }
	
	public function get_records($queryvars, $searchstr,$limitstr) {
		$DBTableRowQuery = new DBTableRowQuery($this->dbtable);
		$DBTableRowQuery->setOrderByClause("ORDER BY {$this->get_sort_key($queryvars,true)}")
						->setLimitClause($limitstr)
						->addAndWhere($this->getSearchAndWhere($searchstr,$DBTableRowQuery));
		$this->addExtraJoins($DBTableRowQuery);
		
		return DbSchema::getInstance()->getRecords('',$DBTableRowQuery->getQuery());
	}

	public function get_records_count(&$queryvars, $searchstr) {
		$DBTableRowQuery = new DBTableRowQuery($this->dbtable);
		$DBTableRowQuery->addAndWhere( $this->getSearchAndWhere($searchstr,$DBTableRowQuery) );
		$this->addExtraJoins($DBTableRowQuery);
		$DBTableRowQuery->setSelectFields('count(*)');
		$records = DbSchema::getInstance()->getRecords('',$DBTableRowQuery->getQuery());
		$record = reset($records);
		return $record['count(*)'];
	}

	public function make_directory_detail($queryvars, &$record,&$buttons_arr,&$detail_out,UrlCallRegistry $navigator) {
		parent::make_directory_detail($queryvars, $record,$buttons_arr,$detail_out,$navigator);
		$query_params = array();
		$query_params['typeversion_id'] = $record['tv__typeversion_id'];
        $query_params['return_url'] = $navigator->getCurrentViewUrl();
        $query_params['resetview'] = 1;
        $edit_url = $navigator->getCurrentViewUrl('itemdefinitionview','',$query_params);
        // the following links have superfluis table params--oh well.
        $buttons_arr[] = linkify( $edit_url, 'View', 'View','listrowlink');
        
		foreach(array_keys($this->display_fields($navigator,$queryvars)) as $fieldname) {
			$detail_out[$fieldname] = isset($record[$fieldname]) ? TextToHtml($record[$fieldname]) : null;
		}
		
		$detail_out['tv__record_modified'] = empty($record['tv__record_modified']) ? '' : date('M j, Y G:i',strtotime($record['tv__record_modified']));
		
		$list_url =  DBTableRowTypeVersion::getListViewAbsoluteUrl($record['tv__typeobject_id'],$_SESSION['account']->getPreference('chkShowProcMatrix'));
		$detail_out['item_count'] = linkify($list_url,$record['item_count'],'List all items of this type');
		
		$serial_number_format = extract_prefixed_keys(extract_prefixed_keys($record, 'tv__serial_number_'),'tv__',true);
		$SerialNumber = SerialNumberType::typeFactory($serial_number_format);
		$detail_out['td_class']['sn_type'] = 'sn_caption_cell';
		
		list($statustext, $statusclass, $definitiondescription) = DBTableRowTypeVersion::formatSubActiveDefinitionStatus($record['typedisposition'], $record['tv__versionstatus'], $record['tv__typeversion_id']==$record['cached_current_typeversion_id']);
		
		if ($statusclass) {
			$detail_out['typecategory_name'] = $record['typecategory_name'].' <span class="disposition '.$statusclass.'">'.$statustext.'</span>';
		}
		
		$detail_out['sn_type'] = $record['is_user_procedure'] ? '' : $SerialNumber->getHelperCaption();
		$detail_out['next_sn'] = $record['cached_next_serial_number'];

		$aliases = $record['aliasparts'] ? '<span class="paren"><br />also '.$record['aliasparts'].'</span>' : '';
		$detail_out['part_number'] = linkify( $edit_url, $record['part_number'], 'View').$aliases;
		
		if ($record['is_user_procedure']) {
			if ($record['linked_parts']==0) $detail_out['linked_parts'] = '';
			if ($record['linked_procedures']==0) $detail_out['linked_procedures'] = '';
		}
		
		$detail_out['tr_class'] .= DBTableRow::wasItemTouchedRecently('typeversion', $record['tv__typeversion_id']) ? ' '.$this->last_select_class : '';
		$recently_changed_row = script_time() - strtotime($record['tv__record_modified']) < $this->_recent_row_age;
		if ($recently_changed_row) {
			$detail_out['tr_class'] .= ' recently_changed_row';
			$detail_out['td_class']['tv__record_modified'] = 'em';
		}		
		if (Zend_Registry::get('config')->warn_of_hidden_fields && $record['cached_hidden_fields']) {
			$detail_out['tr_class'] .= ' error_row';
		}
		
		if ($record['typedisposition']=='B') {
			$detail_out['tr_class'] .= ' definition_subactive';
		}
		
		
		
	}
	
}

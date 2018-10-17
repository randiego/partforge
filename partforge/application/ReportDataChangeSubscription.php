<?php
/**
 *
 * PartForge Enterprise Groupware for recording parts and assemblies by serial number and version along with associated test data and comments.
 *
 * Copyright (C) 2013-2018 Randall C. Black <randy@blacksdesign.com>
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

class ReportDataChangeSubscription extends ReportDataWithCategory {
	
	private $_user_id;
	
	public function __construct() {
		parent::__construct('changesubscription');
		$this->_user_id = $_SESSION['account']->user_id;
		
		$this->show_button_column = true;
		$this->default_sort_key = 'partnumbercache.part_number';
		
		$this->title = 'Manage My Watchlist';
		
		$this->fields['part_number'] 	= array('display'=> 'Number',		'key_asc'=>'partnumbercache.part_number', 'key_desc'=>'partnumbercache.part_number desc');
		$this->fields['part_description'] 	= array('display'=> 'Name',		'key_asc'=>'partnumbercache.part_description', 'key_desc'=>'partnumbercache.part_description desc');

		$this->fields['procedure_date'] 	= array('display'=> 'Procedure',		'key_asc'=>'procedure_date', 'key_desc'=>'procedure_date desc', 'start_key' => 'key_desc');
		$this->fields['item_serial_number'] 	= array('display'=> 'Part',		'key_asc'=>'item_serial_number', 'key_desc'=>'item_serial_number desc');
		$this->fields['watching_changes_to'] 	= array('display'=> 'Watching Changes To');
		$this->fields['notify_instantly'] 	= array('display'=> 'Notify<br />Instantly');
		$this->fields['notify_daily'] 	= array('display'=> 'Notify<br />Daily');
		
		$this->search_box_label = 'number,SN,user,change,locator';

	}
        
	public function getSearchAndWhere($search_string,$DBTableRowQuery) {
		$and_where = '';
		if ($search_string) {
			$or_arr = array();
			$like_value = fetch_like_query($search_string,'%','%');
			$start_like_value = fetch_like_query($search_string,'','%');
			$or_arr[] = "partnumbercache.part_number {$start_like_value}";
			$or_arr[] = "partnumbercache.part_description {$like_value}";
			$or_arr[] = "itemversion.item_serial_number {$like_value}";
			$or = implode(' or ', $or_arr);
			$and_where .= " and ($or)";
		} 
        return $and_where;
    }
    
    protected function addExtraJoins(&$DBTableRowQuery) {

    	$DBTableRowQuery->addJoinClause("LEFT JOIN typeobject to_to on to_to.typeobject_id = changesubscription.typeobject_id")
				    	->addJoinClause("LEFT JOIN typeversion to_tv on to_tv.typeversion_id=to_to.cached_current_typeversion_id")
				    	->addSelectFields("to_tv.typeversion_id")
				    	->addJoinClause("LEFT JOIN typecategory to_tc on to_tc.typecategory_id = to_tv.typecategory_id");
				    	 
    	
    	$DBTableRowQuery->addJoinClause("LEFT JOIN itemobject on itemobject.itemobject_id = changesubscription.itemobject_id")
				    	->addJoinClause("LEFT JOIN itemversion on itemversion.itemversion_id=itemobject.cached_current_itemversion_id")
					    ->addSelectFields("itemversion.itemversion_id")
				    	->addJoinClause("LEFT JOIN typeversion io_tv on io_tv.typeversion_id=itemversion.typeversion_id")
				    	->addJoinClause("LEFT JOIN typecategory io_tc on io_tc.typecategory_id = io_tv.typecategory_id")
				    	->addSelectFields("IFNULL(io_tc.is_user_procedure,to_tc.is_user_procedure) as is_user_procedure, IF(itemobject.itemobject_id IS NULL,'tv','iv') as locator_prefix")
				    	->addJoinClause("LEFT JOIN partnumbercache ON partnumbercache.typeversion_id=IFNULL(itemversion.typeversion_id, to_to.cached_current_typeversion_id) AND partnumbercache.partnumber_alias=IFNULL(itemversion.partnumber_alias,0)")
				    	->addSelectFields('partnumbercache.part_number, partnumbercache.part_description')
				    	->addSelectFields('IF(io_tc.is_user_procedure=1, itemobject.cached_first_ver_date, "") as procedure_date')
				    	->addSelectFields('IF(io_tc.is_user_procedure=1,"",itemversion.item_serial_number) as item_serial_number')
				    	->addSelectFields('IF((io_tc.is_user_procedure IS NULL) and (to_tc.is_user_procedure=1) ,"All Procedures + Definition",
						    			   IF((io_tc.is_user_procedure IS NULL) and (to_tc.is_user_procedure=0) ,"All Parts + Definition",
					    			       IF((io_tc.is_user_procedure IS NOT NULL) and (io_tc.is_user_procedure=1) ,"Procedure","Part"))) as watching_changes_to');
    	 		
    }
	
	public function get_records($queryvars, $searchstr,$limitstr) {
		$DBTableRowQuery = new DBTableRowQuery($this->dbtable);
		$DBTableRowQuery->setOrderByClause("ORDER BY {$this->get_sort_key($queryvars,true)}")
						->setLimitClause($limitstr)
						->addAndWhere(" and (changesubscription.user_id='{$this->_user_id}')")
						->addAndWhere($this->getSearchAndWhere($searchstr,$DBTableRowQuery));
		$this->addExtraJoins($DBTableRowQuery);
		
		return DbSchema::getInstance()->getRecords('',$DBTableRowQuery->getQuery());
	}

	public function get_records_count(&$queryvars, $searchstr) {
		$DBTableRowQuery = new DBTableRowQuery($this->dbtable);
		$DBTableRowQuery->addAndWhere( $this->getSearchAndWhere($searchstr,$DBTableRowQuery) );
		$DBTableRowQuery->addAndWhere(" and (changesubscription.user_id='{$this->_user_id}')");		
		$this->addExtraJoins($DBTableRowQuery);
		$DBTableRowQuery->setSelectFields('count(*)');
		$records = DbSchema::getInstance()->getRecords('',$DBTableRowQuery->getQuery());
		$record = reset($records);
		return $record['count(*)'];
	}

	public function make_directory_detail($queryvars, &$record,&$buttons_arr,&$detail_out,UrlCallRegistry $navigator) {
		parent::make_directory_detail($queryvars, $record,$buttons_arr,$detail_out,$navigator);

		switch($record['locator_prefix']) {
			case 'iv' : $edit_url = UrlCallRegistry::formatViewUrl('iv/'.$record['itemversion_id'],'struct'); break;
			case 'tv' : $edit_url = UrlCallRegistry::formatViewUrl('tv/'.$record['typeversion_id'],'struct'); break;
		}
		
		foreach(array_keys($this->display_fields($navigator,$queryvars)) as $fieldname) {
			$detail_out[$fieldname] = TextToHtml($record[$fieldname]);
		}

		if (in_array($record['change_code'], array('AIR','AIP')) ) {
			$detail_out['change_code_name'] .= ' '.$record['desc_text'];
		}
		$detail_out['change_code_name'] = '<div style="display: block; width:400px; max-width:400px;">'.$detail_out['change_code_name'].'</div>';
		
		$detail_out['changed_on'] = empty($record['changed_on']) ? '' : date('M j, Y G:i',strtotime($record['changed_on']));
		
		$detail_out['procedure_date'] = empty($record['procedure_date']) ? ($record['is_user_procedure'] ? '*' : '') : linkify( $edit_url, date('M j, Y G:i',strtotime($record['procedure_date'])), 'View');
		$detail_out['item_serial_number'] = empty($record['item_serial_number']) ? (!$record['is_user_procedure'] ? '*' : '') : linkify( $edit_url, $record['item_serial_number'], 'View');
		
		if ($record['locator_prefix']=='tv') {
			$detail_out['part_number'] = linkify( $edit_url, $record['part_number'], 'View');
			$detail_out['part_description'] = linkify( $edit_url, $record['part_description'], 'View');
		}
		
		
		$detail_out['notify_instantly'] = '<input type="checkbox" class="notifyInstantly" data-key="'.$record['changesubscription_id'].'" '.($record['notify_instantly'] ? '   checked="checked"' : '').'>';
		$detail_out['notify_daily'] = '<input type="checkbox" class="notifyDaily" data-key="'.$record['changesubscription_id'].'" '.($record['notify_daily'] ? '   checked="checked"' : '').'>';
		
		$edit_url = $navigator->getCurrentHandlerUrl('btnDelete','','',array('changesubscription_id' => $record['changesubscription_id']));
		// the following links have superfluis table params--oh well.
		$buttons_arr[] = linkify( $edit_url, '<IMG src="'.Zend_Controller_Front::getInstance()->getRequest()->getBaseUrl().'/images/deleteicon.png" width="16" height="16" border="0" alt="Delete this from your watch list">', 'Delete this from your watch list','', 'return confirm(\'Stop watching this?\');');		
		
	}
	
}

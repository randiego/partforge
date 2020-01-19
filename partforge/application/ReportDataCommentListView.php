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

class ReportDataCommentListView extends ReportDataWithCategory {
	
	private $can_edit = false;
	private $can_delete = false;
	private $baseUrl = '';
	private $_recent_row_age = null;
	
	public function __construct() {
		parent::__construct('comment');
		$this->baseUrl = Zend_Controller_Front::getInstance()->getRequest()->getBaseUrl();
		
		$this->last_select_class = 'rowlight';
		$this->_recent_row_age = Zend_Registry::get('config')->recent_row_age;
		
		$this->show_button_column = false;
		$this->default_sort_key = 'comment_added desc';
		$this->title = 'List of Comments';
		
		$this->fields['comment_added'] 	= array('display'=>'Comment Date',		'key_asc'=>'comment_added', 'key_desc'=>'comment_added desc', 'start_key' => 'key_desc');
		$this->fields['created_by_name'] 	= array('display'=>'User',		'key_asc'=>'created_by_name', 'key_desc'=>'created_by_name desc');
		$this->fields['typecategory_name'] 	= array('display'=> 'Type',		'key_asc'=>'typecategory_name', 'key_desc'=>'typecategory_name desc');
		
		$this->fields['part_number'] 	= array('display'=> 'Number',		'key_asc'=>'partnumbercache.part_number', 'key_desc'=>'partnumbercache.part_number desc');
		$this->fields['part_description'] 	= array('display'=> 'Name',		'key_asc'=>'partnumbercache.part_description', 'key_desc'=>'partnumbercache.part_description desc');
		
		
		$this->fields['procedure_date'] 	= array('display'=> 'Procedure<br />With<br />Comment',		'key_asc'=>'procedure_date', 'key_desc'=>'procedure_date desc', 'start_key' => 'key_desc');
		$this->fields['item_serial_number'] 	= array('display'=> 'Part<br />With<br />Comment',		'key_asc'=>'item_serial_number', 'key_desc'=>'item_serial_number desc');
		$this->fields['comment_text'] 	= array('display'=> 'Comment');
		$this->fields['documents_packed'] 	= array('display'=> 'Attachments');
		
		$this->search_box_label = 'number,SN,user,comment,locator';

	}
        
	public function getSearchAndWhere($search_string,$DBTableRowQuery) {
		$and_where = '';
		if ($search_string) {
			$or_arr = array();
			$like_value = fetch_like_query($search_string,'%','%');
			$start_like_value = fetch_like_query($search_string,'','%');
			$or_arr[] = "partnumbercache.part_number {$start_like_value}";
			$or_arr[] = "itemversion.item_serial_number {$like_value}";
			$or_arr[] = "TRIM(CONCAT(user.first_name,' ',user.last_name)) {$like_value}";
			$or_arr[] = "comment.comment_text {$like_value}";
			$or = implode(' or ', $or_arr);
			$and_where .= " and ($or)";
		} 
        return $and_where;
    }
    
    protected function addExtraJoins(&$DBTableRowQuery) {

		// add user's name
		$DBTableRowQuery->addJoinClause("LEFT JOIN user on user.user_id = comment.user_id")
						->addSelectFields("TRIM(CONCAT(user.first_name,' ',user.last_name)) as created_by_name");
		
		// add item version info
		$DBTableRowQuery->addJoinClause("LEFT JOIN itemobject on itemobject.itemobject_id = comment.itemobject_id")
						->addSelectFields('itemobject.cached_current_itemversion_id, itemobject.cached_first_ver_date as procedure_date');
		$DBTableRowQuery->addJoinClause("LEFT JOIN itemversion on itemversion.itemversion_id = itemobject.cached_current_itemversion_id")
						->addSelectFields('itemversion.item_serial_number');
		
		$iv_alias = 'itemversion'; //$DBTableRowQuery->getJoinAlias('itemversion');
		$DBTableRowQuery->addSelectFields("
				IF ( itemobject.cached_last_comment_date IS NULL OR  itemobject.cached_last_comment_date <= {$iv_alias}.effective_date,
					IF (itemobject.cached_last_ref_date IS NULL OR itemobject.cached_last_ref_date <= {$iv_alias}.effective_date, {$iv_alias}.effective_date, itemobject.cached_last_ref_date ),
					IF (itemobject.cached_last_ref_date IS NULL OR itemobject.cached_last_ref_date <= itemobject.cached_last_comment_date, itemobject.cached_last_comment_date, itemobject.cached_last_ref_date) ) as last_change_date");
		
		$DBTableRowQuery->addJoinClause("LEFT JOIN typeversion on typeversion.typeversion_id = itemversion.typeversion_id")
						->addSelectFields('typeversion.typecategory_id, typeversion.typeobject_id');
		
		$DBTableRowQuery->addJoinClause("LEFT JOIN partnumbercache ON partnumbercache.typeversion_id=itemversion.typeversion_id AND partnumbercache.partnumber_alias=itemversion.partnumber_alias")
						->addSelectFields('partnumbercache.part_number, partnumbercache.part_description');
		
		$DBTableRowQuery->addJoinClause("LEFT JOIN typecategory on typecategory.typecategory_id = typeversion.typecategory_id")
						->addSelectFields('typecategory.is_user_procedure, typecategory.typecategory_name');
		
		// add the packed documents field
		$config = Zend_Registry::get('config');
		$DBTableRowQuery->addSelectFields("(SELECT GROUP_CONCAT(
				CONCAT(document.document_id,',',document.document_filesize,',',CONVERT(HEX(document.document_displayed_filename),CHAR),',',CONVERT(HEX(document.document_stored_filename),CHAR),',',document.document_stored_path,',',document.document_file_type,',',document.document_thumb_exists) 
					SEPARATOR ';') FROM document WHERE (document.comment_id = comment.comment_id) and (document.document_path_db_key='{$config->document_path_db_key}')) as documents_packed");
				
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
        $query_params['itemversion_id'] = $record['cached_current_itemversion_id'];
        $query_params['return_url'] = $navigator->getCurrentViewUrl();
        $query_params['resetview'] = 1;
        $edit_url = $navigator->getCurrentViewUrl('itemview','',$query_params);        
        
		foreach(array_keys($this->display_fields($navigator,$queryvars)) as $fieldname) {
			$detail_out[$fieldname] = TextToHtml($record[$fieldname]);
		}
		$detail_out['item_serial_number'] = linkify( $edit_url, $record['item_serial_number'], 'View');
		list($comment_html,$dummy) = EventStream::textToHtmlWithEmbeddedCodes($record['comment_text'], $navigator, 'ET_COM');
		$detail_out['comment_text'] = '<div class="excerpt" style="display: block; width:400px; max-width:400px;">'.$comment_html.'</div>';
		
		$detail_out['comment_added'] = empty($record['comment_added']) ? '' : date('M j, Y G:i',strtotime($record['comment_added']));
		$detail_out['procedure_date'] = (empty($record['procedure_date']) || !$record['is_user_procedure']) ? '' : linkify( $edit_url, date('M j, Y G:i',strtotime($record['procedure_date'])), 'View');
		
		
		if ($record['documents_packed']) {
			$documents_html = '<div style="max-width:400px;"><div class="bd-event-documents">';
			$documents_html .= EventStream::documentsPackedToFileGallery($this->baseUrl,'id'.$record['comment_id'], $record['documents_packed']);
			$documents_html .= '</div></div>';
		} else {
			$documents_html = '';
		}
		$detail_out['documents_packed'] = $documents_html;
		$detail_out['tr_class'] .= DBTableRow::wasItemTouchedRecently('itemversion'.$record['typeobject_id'], $record['cached_current_itemversion_id']) ? ' '.$this->last_select_class : '';
		$recently_changed_row = script_time() - strtotime($record['last_change_date']) < $this->_recent_row_age;
		if ($recently_changed_row) {
			$detail_out['tr_class'] .= ' recently_changed_row';
		}
	}
	
}

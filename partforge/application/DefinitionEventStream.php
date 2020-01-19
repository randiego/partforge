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

function defeventstream_cmp($a, $b)
{
	$typeord = array('ET_CHG'=>1, 'ET_PARTREF' => 2, 'ET_PROCREF' => 3, 'ET_COM' => 4);
	if (strtotime($a['effective_date']) < strtotime($b['effective_date'])) {
		return -1;
	} elseif (strtotime($a['effective_date']) > strtotime($b['effective_date'])) {
		return 1;
	} else {
		if (strtotime($a['record_created']) < strtotime($b['record_created'])) {
			return -1;
		} elseif (strtotime($a['record_created']) > strtotime($b['record_created'])) {
			return 1;
		} else {
			if ($typeord[$a['event_type_id']] < $typeord[$b['event_type_id']]) {
				return -1;
			} elseif ($typeord[$a['event_type_id']] > $typeord[$b['event_type_id']]) {
				return 1;
			} else {
				return 0;
			}
		}
	}
}

class DefinitionEventStream {
	
	private $_typeobject_id;
	
	public function __construct($typeobject_id) {
		$this->_typeobject_id = $typeobject_id;
	}
	
	public function assembleStreamArray() {
		$config = Zend_Registry::get('config');
		
		$holdComponents=array();
		
		$out = array();
		
		/*
		 * We start with all the versions of this type object.
		 */
		
		// we want the current type version so that we can compare any draft
		$CurrentActiveTypeVersion = new DBTableRowTypeVersion();
		$current_version_active = $CurrentActiveTypeVersion->getCurrentActiveRecordByObjectId($this->_typeobject_id);
		
		$query = "SELECT * FROM typeversion WHERE typeobject_id='{$this->_typeobject_id}' ORDER BY effective_date";
		$records = DbSchema::getInstance()->getRecords('typeversion_id',$query);
		$PreviousActiveTypeVersion = null;
		$is_first = true;
		foreach($records as $typeversion_id => $record) {
			// get typeversion record
			$TypeVersion = DbSchema::getInstance()->dbTableRowObjectFactory('typeversion',false,'');
			$TypeVersion->getRecordById($typeversion_id);

			// build new event record
			$itemevent = array();
			$itemevent['event_type_id'] = 'ET_CHG';
			$itemevent['this_typeversion_id'] = $typeversion_id;
			$itemevent['effective_date'] = $TypeVersion->effective_date;
			$itemevent['record_created'] = $TypeVersion->record_created;
			$itemevent['record_modified'] = $TypeVersion->record_modified;
			$itemevent['versionstatus'] = $TypeVersion->versionstatus;
			$itemevent['user_id'] = $TypeVersion->user_id;

			// make sure that unless we have another description...
			if ($is_first) {
				$itemevent['event_description'] = 'Created.';
				$itemevent['referenced_typeversion_id'] = null;
				$is_first = false;
			}
			
			if ($TypeVersion->versionstatus=='A') {
				// if there was another one active, then we want to compare to it, otherwise call it released.
				if (!is_null($PreviousActiveTypeVersion)) {
					$itemevent['event_description'] = '<html>'.$TypeVersion->typeDifferencesFromHtml($PreviousActiveTypeVersion).'</html>';
					$itemevent['referenced_typeversion_id'] = $PreviousActiveTypeVersion->typeversion_id;
				} else {
					$itemevent['event_description'] = 'Released.';
					$itemevent['referenced_typeversion_id'] = null;
				}
			} else {  // not active, so we always compare to current active one if the current one is active
				// only load this on demand, and only then if we didn't load it already.
				if ($current_version_active) {
					$types = array('D' => 'Draft', 'R' => 'Review Version');
					$diff_html = $TypeVersion->typeDifferencesFromHtml($CurrentActiveTypeVersion);
					if ($diff_html) $diff_html .= '<hr /><div class="chglistfooter">'.$types[$TypeVersion->versionstatus].' redlines are compared to Active Version.</div>';
					$itemevent['event_description'] = $diff_html ? '<html>'.$diff_html.'</html>' : '';
					$itemevent['referenced_typeversion_id'] = $CurrentActiveTypeVersion->typeversion_id;
				} else {
					$itemevent['event_description'] = '';
					$itemevent['referenced_typeversion_id'] = null;						
				}
				
			}		

			$out[] = $itemevent;

			// we only want to compare to non-draft versions
			if ($TypeVersion->versionstatus=='A') $PreviousActiveTypeVersion = $TypeVersion;
		}


		/*
		 * Add Comments
		 */
		$query = "
				SELECT typecomment.*
				FROM typecomment
				WHERE typecomment.typeobject_id='{$this->_typeobject_id}'
			";
		$records = DbSchema::getInstance()->getRecords('comment_id',$query);
		foreach($records as $comment_id => $record) {
			$itemevent = array();
			$itemevent['event_type_id'] = 'ET_COM';
			$itemevent['effective_date'] = $record['comment_added'];
			$itemevent['record_created'] = $record['record_created'];
			$itemevent['user_id'] = $record['user_id'];
            $itemevent['proxy_user_id'] = $record['proxy_user_id'];
			$itemevent['event_description'] = $record['comment_text'];
			$itemevent['comment_id'] = $comment_id;
				
			$out[] = $itemevent;
				
		}
		
		/*
		 * Add user names
		 */
		
		$records = DbSchema::getInstance()->getRecords('user_id',"SELECT user_id, first_name, last_name FROM user");	
		foreach($out as $idx => $outrec) {
			$out[$idx]['first_name'] = $records[$outrec['user_id']]['first_name'];
			$out[$idx]['last_name'] = $records[$outrec['user_id']]['last_name'];
		}	
		
		uasort($out,'defeventstream_cmp');
		
		return $out;
	
	}
		
	/**
	 * Takes the lines output from ::eventStreamRecordsToLines() and renders it into the stream view (RHS of itemview). 
	 * @param DBTableRowTypeVersion $dbtable
	 * @param array $lines
	 * @return array of rendered html blocks in chronological order.
	 */
	public static function eventStreamLinesToHtml($dbtable,$lines,$navigator) {
		
		$dbtable->_navigator = $navigator; 
		$baseUrl = Zend_Controller_Front::getInstance()->getRequest()->getBaseUrl();
		$user_records = DbSchema::getInstance()->getRecords('user_id',"SELECT user_id, first_name, last_name FROM user");
		$layout_rows = array();
				
		foreach($lines as $line_idx => $line) {
		
			$datetime = time_to_bulletdate(strtotime($line['effective_date']));
			$select_radio_html = '';
			$edit_buttons_html = '';
			$alt_edit_date_html = '';
		
			$status_badge_html = '';
			$background_class = '';
			if ($line['event_type_id']=='ET_CHG') {
				$background_class = 'bd-type-def-change';
				$this_itemversion_id = $line['this_itemversion_id'];
				$selected = $line['is_selected_version'] ? ' checked="checked"' : '';
				$onclick_html = " onClick=\"window.location='".$line['version_url']."'\"";
				$select_radio_html = '<div class="bd-event-select-button"><input class="radioclass" type="radio" name="itemversion_ck" value="'.$this_itemversion_id.'" id="itemversion_ck_'.$this_itemversion_id.'"'.$selected.$onclick_html.' /></div>';
				$edit_buttons_html = '<div class="bd-edit">'.implode('',$line['edit_links']).'</div>';
				$one_hour = 3600;
				// if entering a date in the future, that's weird.  If entering a date more than one day past, that's weird too.
				$is_weird_record_modified_date = (strtotime($line['record_modified']) + $one_hour < strtotime($line['effective_date']))
				     || (strtotime($line['record_modified']) - 25*$one_hour > strtotime($line['effective_date']));
				$editing_msg = '';
				if ($is_weird_record_modified_date) {
					// there are not changes, but we should at least say something about the odd date
					$editing_msg = ''.time_to_bulletdate(strtotime($line['record_modified']),false);
				}
				if ($editing_msg) {
					$alt_edit_date_html = '<div class="bd-dateline-edited">('.$editing_msg.')</div>';
				}
				
				if (in_array($line['versionstatus'], array('D','R'))) {
					list($statustext, $statusclass, $definitiondescription) = DBTableRowTypeVersion::formatSubActiveDefinitionStatus('A', $line['versionstatus'], false);
					$status_badge_html = '<div class="bd-edit"><span class="disposition '.$statusclass.'">'.$statustext.'</span></div>';
					$background_class = 'bd-type-def-change draft-color';
				}
			}
		
			if ($line['event_type_id']=='ET_COM') {
				$background_class = 'bd-type-comment';
				$edit_buttons_html = '<div class="bd-edit">'.implode('',$line['edit_links']).'</div>';
			}
		
			$subcomments_html = '';
		
			$dimmed_class = $line['is_future_version'] ? ' bd-dimmed' : '';

			
				
			$highlight_class = $line['recently_edited'] ? (in_array($line['event_type_id'],array('ET_PROCREF','ET_PARTREF')) ? ' event_afterglow_r' : ' event_afterglow_c') : '';
			$layout_rows[] = '<li class="bd-event-row '.$background_class.$dimmed_class.$highlight_class.'">
				'.$select_radio_html.'
				<div class="bd-event-content'.($alt_edit_date_html ? ' bd-with-edit-date' : '').'">
				<div class="bd-event-type"></div>
				<div class="bd-event-whowhen"><div class="bd-byline">'.strtoupper($line['user_name_html']).'</div><div class="bd-dateline">'.$datetime.'</div>'.$alt_edit_date_html.'</div>
				<div class="bd-event-message">
				'.$edit_buttons_html.$status_badge_html.'
				'.$line['event_html'].'
				</div>
				'.$subcomments_html.'
				</div>
				</li>';
		}
		return $layout_rows;
	}
	
	public static function renderEventStreamHtmlForPdfFromLines($lines,$show_procedures = true) {
		
		$layout_rows = array();
		foreach($lines as $line) {
		
			$datetime = time_to_bulletdate(strtotime($line['effective_date']), false);
			$procedure_disposition_html = '';
		
			if (in_array($line['event_type_id'],array('ET_CHG','ET_COM')) && !$line['is_future_version']) {
				$layout_rows[] = array('<b>'.strtoupper($line['user_name_html']).'</b><br /> <i>'.$datetime.'</i>', $line['event_html'], $procedure_disposition_html);
			}
		}
		return $layout_rows;
		
	}
	
	/**
	 * This takes a record representation of the stream array from ::assemblyStreamArray() and generates an array of lines.
	 */
	public static function eventStreamRecordsToLines($records, $dbtable, $navigator=null) {
		
		
		$return_url = !is_null($navigator) ? $navigator->getCurrentViewUrl(null,null,array('typeversion_id' => $dbtable->typeversion_id)) : '';
		
		/*
		 * Determine the return url for version delete operations.
		*/
		$delete_failed_return_url = !is_null($navigator) ? $navigator->getCurrentViewUrl(null,null,array('typeobject_id' => $dbtable->typeobject_id)) : '';
		$delete_done_return_url = $delete_failed_return_url;
		$version_count = 0;
		foreach($records as $record) {
			if (($record['event_type_id']=='ET_CHG')) $version_count++;
		}
		if ($version_count==1) {
			$BreadCrumbs = new BreadCrumbsManager();
			$delete_done_return_url = $BreadCrumbs->getPreviousUrl();
		}
		
		/*
		 * these two fields help me decide when to show the entries dimmed in the case were we have selected an old version.
		*/
		$previously_found_active_version = false;
		$is_future_version = false;
		$lines = array();
		foreach($records as $record) {
			$is_selected_version = $record['this_typeversion_id']==$dbtable->typeversion_id;
		
			if ($previously_found_active_version && ($record['event_type_id']=='ET_CHG') && !$is_selected_version ) {
				$is_future_version = true;
			}
			$error = '';
			if ($record['error_message']) {
				list($event_description,$event_description_array) = EventStream::textToHtmlWithEmbeddedCodes($record['error_message'], $navigator, $record['event_type_id']);
				$error = '<div class="event_error">'.$event_description.'</div>';
			}
		
			$links = array();
		
			$version_url = '';
			if (($record['event_type_id']=='ET_CHG')) {
				$query_params = array();
				$query_params['typeversion_id'] = $record['this_typeversion_id'];
				$version_url = !is_null($navigator) ? $navigator->getCurrentViewUrl('itemdefinitionview','',$query_params) : '';
			}
		
			list($event_description,$event_description_array) = EventStream::textToHtmlWithEmbeddedCodes($record['event_description'], $navigator, $record['event_type_id']);
			$line = array(
					'event_type_id' => $record['event_type_id'],
					'this_typeversion_id' => $record['this_typeversion_id'],
					'user_name_html' => TextToHtml(DBTableRowUser::concatNames($record)),
					'version_url' => $version_url,
					'is_selected_version' => $is_selected_version,
					'event_html'=> $event_description.$error,
					'event_description_array' => $event_description_array,
					'effective_date' => $record['effective_date'],
					'record_created' => $record['record_created'],
					'record_modified' => $record['record_modified'],
					'versionstatus' => $record['versionstatus'],
					'is_future_version' => $is_future_version,
					'recently_edited' => false);
		
			$line['edit_links'] = array();
			if (($record['event_type_id']=='ET_CHG')) {
				$is_a_procedure = DBTableRowTypeVersion::isTypeCategoryAProcedure($dbtable->typecategory_id);
				$is_current_version = $dbtable->isCurrentVersion($record['this_typeversion_id']);
				$record_created = $record['record_created'];
// This is where editing controls go
//				$line['edit_links'] = !is_null($navigator) ? DBTableRowTypeVersion::typeversionEditLinks($navigator, $return_url, $delete_failed_return_url, $delete_done_return_url, $dbtable->typeobject_id, $record['this_typeversion_id'], $record_created, $record['user_id'],$is_a_procedure,$is_current_version) : array();
			}
		
			if ($record['event_type_id']=='ET_COM') {
				$line['edit_links'] = !is_null($navigator) ? DBTableRowTypeComment::commentEditLinks($navigator, $return_url, $dbtable->typeobject_id, $record['comment_id'], $record['effective_date'],$record['user_id']) : '';
				if (DBTableRow::wasItemTouchedRecently('typecomment', $record['comment_id'])) {
					$line['recently_edited'] = true;
				}
			}
		
		
			$lines[] = $line;
		
		
			if (($record['event_type_id']=='ET_CHG') && $is_selected_version) {
				$previously_found_active_version = true;  //
			}
		
		}
		
		return $lines;
				
	}
	
}

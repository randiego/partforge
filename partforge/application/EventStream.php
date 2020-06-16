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

function eventstream_cmp($a, $b)
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


class EventStream {
	
	/*
	 * Builds and format the eventsteam.  At one time the eventstream was saved to a database table, so some of the structure
	 * was motivated by that pattern.  Now it's done in arrays.
	 */
        
	private $_itemobject_id;
	
	public function __construct($itemobject_id) {
		$this->_itemobject_id = $itemobject_id;
	}
	
	/**
	 * This is used to estimate the number of records that will be returned from getNestedEventStreamRecords().  It is an overestimate
	 * because it does not remove double counts for indented records, but it is still useful for deciding if we will truncate the results.
	 * @param DBTableRowItemVersion $ItemVersion
	 * @return mixed
	 */
	static public function getNestedEventStreamRecordCount(DBTableRowItemVersion $ItemVersion, $end_date=null) {
		$count = 0;
		$EventStream = new self($ItemVersion->itemobject_id);
		
		$count += $EventStream->getAssembleStreamArrayRecordCount('',$end_date);
		if (!$ItemVersion->is_user_procedure) {		
			foreach($ItemVersion->getCurrentlySetComponentValues() as $componentname => $itemobject_id) {
				$ComponentObj = $ItemVersion->getComponentAsIVObject($componentname);
				if (!is_null($ComponentObj) && $ComponentObj->hasASerialNumber()) {
					$EventStream = new self($itemobject_id);
					$count += $EventStream->getAssembleStreamArrayRecordCount($componentname, $end_date);
				}
			}
		}
		return $count;
	}
	
	/**
	 * puts together a top and nested event stream items for none procedures.  It looks like procedures don't make much sense to view an indented list.
	 * @param DBTableRowItemVersion $ItemVersion
	 * @return Ambigous <multitype:, multitype:multitype:string unknown NULL  multitype:string NULL unknown  >
	 */
	static public function getNestedEventStreamRecords(DBTableRowItemVersion $ItemVersion, $end_date=null) {
		$streamlines = array();
		$EventStream = new self($ItemVersion->itemobject_id);
		
		$streamlines = $EventStream->assembleStreamArray('',$end_date);
		if (!$ItemVersion->is_user_procedure) {
	
			// build list of parent itemversion_ids so that we can eliminate dups from component lists
			$parent_itemversion_ids = array();
			foreach($streamlines as $idx => $streamline) {
				if ( in_array($streamline['event_type_id'],array('ET_CHG','ET_PROCREF','ET_PARTREF'))) $parent_itemversion_ids[] = $streamline['this_itemversion_id'];
			}
	
			foreach($ItemVersion->getCurrentlySetComponentValues() as $componentname => $itemobject_id) {
				$ComponentObj = $ItemVersion->getComponentAsIVObject($componentname);
				if (!is_null($ComponentObj) && $ComponentObj->hasASerialNumber()) {
					$EventStream = new self($itemobject_id);
					$morelines = $EventStream->assembleStreamArray($componentname,$end_date);
		
					// remove any duplicates of top-level items.
					foreach($morelines as $idx => $moreline) {
						if (in_array($moreline['event_type_id'],array('ET_CHG','ET_PROCREF','ET_PARTREF')) && in_array($moreline['this_itemversion_id'],$parent_itemversion_ids)) {
							unset($morelines[$idx]);
						}
					}
					$streamlines = array_merge($streamlines,$morelines);
				}
			}
			uasort($streamlines,'eventstream_cmp');
		}
		return $streamlines;
	}
	
	public function getVersionRecords($end_date) {
		$end_date_and_where = is_null($end_date) ? '' : " and (effective_date >= '".time_to_mysqldatetime(strtotime($end_date))."')";
		$query = "SELECT *, (SELECT count(*) FROM itemversionarchive WHERE itemversionarchive.itemversion_id=itemversion.itemversion_id) as archive_count,
		(SELECT min(record_created) FROM itemversionarchive WHERE itemversionarchive.itemversion_id=itemversion.itemversion_id) as oldest_record_created
		FROM itemversion WHERE itemobject_id='{$this->_itemobject_id}' {$end_date_and_where} ORDER BY effective_date";
		return DbSchema::getInstance()->getRecords('itemversion_id',$query);
	}
	
	public function getAssembleStreamArrayRecordCount($indented_component_name='', $end_date=null) {
		$count = 0;
		
		// version
		$end_date_and_where = is_null($end_date) ? '' : " and (effective_date >= '".time_to_mysqldatetime(strtotime($end_date))."')";
		$query = "SELECT count(*) as record_count FROM itemversion WHERE itemobject_id='{$this->_itemobject_id}' {$end_date_and_where}";
		$records = DbSchema::getInstance()->getRecords('',$query);
		$record = reset($records);
		$count += $record['record_count'];
		
		// get itemversion records that include us as components.  Include comments and documents as well.
		$end_date_and_where = is_null($end_date) ? '' : " and (iv_them.effective_date >= '".time_to_mysqldatetime(strtotime($end_date))."')";
		$query = "SELECT count(*) as record_count FROM itemcomponent
					LEFT JOIN itemversion AS iv_them ON iv_them.itemversion_id=itemcomponent.belongs_to_itemversion_id
					WHERE itemcomponent.has_an_itemobject_id='{$this->_itemobject_id}' {$end_date_and_where}";		
		$records = DbSchema::getInstance()->getRecords('',$query);
		$record = reset($records);
		$count += $record['record_count'];
		
		// comments
		$end_date_and_where = is_null($end_date) ? '' : " and (comment.comment_added >= '".time_to_mysqldatetime(strtotime($end_date))."')";
		$query = "SELECT count(*) as record_count FROM comment WHERE itemobject_id='{$this->_itemobject_id}' {$end_date_and_where}";
		$records = DbSchema::getInstance()->getRecords('',$query);
		$record = reset($records);
		$count += $record['record_count'];
		
		return $count;
	}
		
	/**
	 * Create a chronology of changes to the field values indexed by fieldname.  This will be used
	 * to decorate the print fields in the itemview table.
	 * @param DBTableRowItemVersion $ItemVersionBase
	 * @return array of array of array(value, itemversion Id, date change was made)
	 */
	static public function changeHistoryforFields(DBTableRowItemVersion $ItemVersionBase) {

		$out = array();

		$query = "SELECT *, (SELECT count(*) FROM itemversionarchive WHERE itemversionarchive.itemversion_id=itemversion.itemversion_id) as archive_count,
		(SELECT min(record_created) FROM itemversionarchive WHERE itemversionarchive.itemversion_id=itemversion.itemversion_id) as oldest_record_created
		FROM itemversion WHERE itemobject_id='{$ItemVersionBase->itemobject_id}' ORDER BY effective_date";
		$records = DbSchema::getInstance()->getRecords('itemversion_id',$query);
		$PreviousItemVersion = DbSchema::getInstance()->dbTableRowObjectFactory('itemversion',false,'');
		$PreviousItemVersion->typeversion_id = $ItemVersionBase->typeversion_id;
		foreach($records as $itemversion_id => $record) {
			// get itemversion record
			$ItemVersion = DbSchema::getInstance()->getItemVersionCachedRecordById($itemversion_id);
			$fieldchanges = $ItemVersion->itemDifferencesFrom($PreviousItemVersion, false, true);
			$datestr = date('M j, Y',strtotime($ItemVersion->effective_date));
			foreach($fieldchanges as $fieldname => $value) {
				if (!isset($out[$fieldname])) { 
					$out[$fieldname] = array(array($value,$itemversion_id, $ItemVersion->effective_date));
				} else {
					$out[$fieldname][] = array($value,$itemversion_id, $ItemVersion->effective_date);
				}
			}

			$PreviousItemVersion = $ItemVersion;
		}
		
		$prunedout = array();
		foreach($out as $fieldname => $changes) {
			if ((count($changes)==1)) {
				unset($changes[0]);
			}
			if (count($changes) > 0) $prunedout[$fieldname] = $changes;
		}
		return $prunedout;
	}
	
	/**
	 * Create the html that gets appended to the html field value shown in the itemview form
	 */
	static public function changeHistoryToHtmlPrintFieldDecoration($singlefieldhistory) {
		$html = '';
		end($singlefieldhistory);     // which is the last element?
		$lastindex = key($singlefieldhistory);
		foreach($singlefieldhistory as $index => $change) {
			$fmtdate = linkify('#', date('M j, Y',strtotime($change[2])),"Scroll to Version",'paren',"scrollRightSideToVersionId($change[1])");
			$formattedchange = ($index==$lastindex) && (substr($change[0],0,6)=='set to') ?  'last set '.$fmtdate : $change[0].' on '.$fmtdate;
			$html .= '<span class="paren"><br />'.$formattedchange.'</span>';
		}
		return $html;
	}

	public function assembleStreamArray($indented_component_name='', $end_date=null) {
		$config = Zend_Registry::get('config');
		
		$holdComponents=array();
		
		$out = array();
	
		/*
		 * create array entries in the eventstream.  We start with all the versions of this item object.
		 */
		$end_date_and_where = is_null($end_date) ? '' : " and (effective_date >= '".time_to_mysqldatetime(strtotime($end_date))."')";
		$query = "SELECT *, (SELECT count(*) FROM itemversionarchive WHERE itemversionarchive.itemversion_id=itemversion.itemversion_id) as archive_count,
				(SELECT min(record_created) FROM itemversionarchive WHERE itemversionarchive.itemversion_id=itemversion.itemversion_id) as oldest_record_created 
				FROM itemversion WHERE itemobject_id='{$this->_itemobject_id}' {$end_date_and_where} ORDER BY effective_date";
		$records = DbSchema::getInstance()->getRecords('itemversion_id',$query);
		$is_first = true;
		foreach($records as $itemversion_id => $record) {
			// get itemversion record
			$ItemVersion = DbSchema::getInstance()->getItemVersionCachedRecordById($itemversion_id);
				
			// build new event record
			$itemevent = array();
			$itemevent['event_type_id'] = 'ET_CHG';
			if ($indented_component_name) {
				$itemevent['indented_component_name'] = $indented_component_name;
			}
			$itemevent['this_itemversion_id'] = $itemversion_id;
			$itemevent['effective_date'] = $ItemVersion->effective_date;
			$itemevent['record_created'] = $ItemVersion->record_created;
			$itemevent['archive_count'] = $record['archive_count'];
			$itemevent['oldest_record_created'] = $record['oldest_record_created'];
			$itemevent['user_id'] = $ItemVersion->user_id;
			$itemevent['proxy_user_id'] = $ItemVersion->proxy_user_id;
			$itemevent['description_is_html'] = false;
			
				
			if ($is_first) {
				$comps = array();
				$ComponentItemVersion = DbSchema::getInstance()->dbTableRowObjectFactory('itemversion',false,'');
				foreach($ItemVersion->getCurrentlySetComponentValues() as $comp_name => $comp_value_itemobject_id) {
					$ComponentItemVersion->getCurrentRecordByObjectId($comp_value_itemobject_id,$ItemVersion->effective_date);
					$comps[] = "Associated with <itemversion>{$ComponentItemVersion->itemversion_id}</itemversion>.";
				}
				$created_title = $indented_component_name ? 'Created '.$ItemVersion->tv__type_description.'.' : 'Created.';
				$itemevent['event_description'] = $created_title."\r\n".implode("\r\n",$comps);
				
				$itemevent['referenced_itemversion_id'] = null;
				$is_first = false;
			} else {
				$itemevent['event_description'] = $ItemVersion->itemDifferencesFrom($PreviousItemVersion);
				$itemevent['description_is_html'] = true;
				$itemevent['referenced_itemversion_id'] = $PreviousItemVersion->itemversion_id;
			}				
			
			$out[] = $itemevent;
				
			$PreviousItemVersion = $ItemVersion;
		}
		
	
		/*
		 * Now take care of the ref as component entries
		*/
	
		// get itemversion records that include us as components.  Include comments and documents as well.
		$end_date_and_where = is_null($end_date) ? '' : " and (iv_them.effective_date >= '".time_to_mysqldatetime(strtotime($end_date))."')";
		$query = "
				
				SELECT 
					iv_them.*, iv_them.effective_date as them_effective_date,
					(
						SELECT 
							GROUP_CONCAT(
								CONCAT(themcomment.user_id,'&',themcomment.comment_added,'&',CONVERT(HEX(themcomment.comment_text),CHAR),'&',IFNULL((SELECT 
									GROUP_CONCAT(
										CONCAT(document.document_id,',',document.document_filesize,',',CONVERT(HEX(document.document_displayed_filename),CHAR),',',CONVERT(HEX(document.document_stored_filename),CHAR),',',document.document_stored_path,',',document.document_file_type,',',document.document_thumb_exists) 
										SEPARATOR ';'
									) 
								FROM document WHERE (document.comment_id = themcomment.comment_id) and (document.document_path_db_key='{$config->document_path_db_key}')),''))
								SEPARATOR '|')
						FROM comment as themcomment WHERE themcomment.itemobject_id=iv_them.itemobject_id
					) as comments_packed
				FROM itemcomponent
				LEFT JOIN itemversion AS iv_them ON iv_them.itemversion_id=itemcomponent.belongs_to_itemversion_id
				WHERE itemcomponent.has_an_itemobject_id='{$this->_itemobject_id}' {$end_date_and_where} ORDER BY iv_them.effective_date";
		$records = DbSchema::getInstance()->getRecords('itemversion_id',$query);
		foreach($records as $itemversion_id => $record) {
			// get itemversion record for the item that has included us as a component
			$ItemVersion = new DBTableRowItemVersion();
			$ItemVersion->getRecordById($itemversion_id);
				
			// need to get the dictionary for this and see if there are any featured fields that we need to display.
				
				
			// build new event record
			$itemevent = array();
			$itemevent['event_type_id'] = $ItemVersion->is_user_procedure ? 'ET_PROCREF' : 'ET_PARTREF';
			if ($indented_component_name) {
				$itemevent['indented_component_name'] = $indented_component_name;
			}
			$itemevent['this_itemversion_id'] = $itemversion_id;
			$itemevent['this_itemobject_id'] = $ItemVersion->itemobject_id;
			$itemevent['this_typeobject_id'] = $ItemVersion->tv__typeobject_id;
			if ($ItemVersion->is_user_procedure && !$config->show_old_proc_version_in_eventstream && (strtotime($ItemVersion->io__cached_first_ver_date)!=strtotime($ItemVersion->effective_date))) {
				$itemevent['effective_date'] = $ItemVersion->io__cached_first_ver_date;
				$itemevent['actual_effective_date'] = $ItemVersion->effective_date;
			} else {
				$itemevent['effective_date'] = $ItemVersion->effective_date;
			}
			$itemevent['record_created'] = $ItemVersion->record_created;
			$itemevent['disposition'] = $ItemVersion->disposition;
			$itemevent['user_id'] = $ItemVersion->user_id;
			/*
			 * The idea here is that if we are referenced by a procedure, then the results of the procedure will
			 * be interesting to us and we want to see the details.  On the other hand if we have been referenced
			 * by an assembly, then there is probably not a ton of information in in the assembly that would be
			 * of interest.  If anything, it would be the other way around.  It would be interesting to see a summary
			 * of the new subcomponent data presented at the top-level assembly event stream.
			 */
			if (($record['comments_packed']) && $ItemVersion->is_user_procedure) {
				$itemevent['comments_packed'] = $record['comments_packed'];
			}	
			$version_age = $ItemVersion->isCurrentVersion() ? '' : ' an old version of ';
			$itemevent['description_is_html'] = false;
			$itemevent['event_description'] = ($ItemVersion->event_stream_reference_prefix).$version_age." <itemversion>{$itemversion_id}</itemversion>";
			$itemevent['referenced_itemversion_id'] = null;
			$itemevent['is_current_version'] = $ItemVersion->isCurrentVersion();
			
			
			$arr = $itemevent;
			// the 3rd || handles left over entries let through by the query when we want to remove older entries ($end_date not null)
			$hide_this_entry = (!$ItemVersion->isCurrentVersion() && $ItemVersion->is_user_procedure && !$config->show_old_proc_version_in_eventstream)
								|| (!$ItemVersion->isCurrentVersion() && !$ItemVersion->is_user_procedure && !$config->show_old_part_version_in_eventstream)
								|| (!is_null($end_date) && (strtotime( $itemevent['effective_date']) < strtotime($end_date) ));
			if (!$hide_this_entry) {				
				$out[] = $arr;
			}
		}
		

	
	
		// add comments
		$end_date_and_where = is_null($end_date) ? '' : " and (comment.comment_added >= '".time_to_mysqldatetime(strtotime($end_date))."')";
		$query = "
				
				SELECT comment.*, (SELECT GROUP_CONCAT(
				CONCAT(document.document_id,',',document.document_filesize,',',CONVERT(HEX(document.document_displayed_filename),CHAR),',',CONVERT(HEX(document.document_stored_filename),CHAR),',',document.document_stored_path,',',document.document_file_type,',',document.document_thumb_exists) 
					SEPARATOR ';') FROM document WHERE (document.comment_id = comment.comment_id) and (document.document_path_db_key='{$config->document_path_db_key}')) as documents_packed FROM comment
				WHERE itemobject_id='{$this->_itemobject_id}' {$end_date_and_where}
			";
		$records = DbSchema::getInstance()->getRecords('comment_id',$query);
		foreach($records as $comment_id => $record) {
			$itemevent = array();
			$itemevent['event_type_id'] = 'ET_COM';
			if ($indented_component_name) {
				$itemevent['indented_component_name'] = $indented_component_name;
			}
			$itemevent['effective_date'] = $record['comment_added'];
			$itemevent['record_created'] = $record['record_created'];
			$itemevent['user_id'] = $record['user_id'];
            $itemevent['proxy_user_id'] = $record['proxy_user_id'];
            $itemevent['description_is_html'] = false;
			$itemevent['event_description'] = $record['comment_text'];
			if ($record['documents_packed']) {
				$itemevent['documents_packed'] = $record['documents_packed'];
			}
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
		
		uasort($out,'eventstream_cmp');
		
		return $out;
	
	}
	
	/**
	 * This is a fairly generic function that does a text_to_unwrappedhtml() but passes
	 * through stuff inside <html>My plain html</html>
	 * @param string $text plain text
	 * @return string html
	 */
	public static function textToHtmlwithEmbeddedHtml($text) {
		/*
		 * get all the <html> groups before we convert to html
		 * $out_plain[0] has the whole opening and closing tag. $out_plain[1] has only the enclosed itemversion_id
		 * note that the ims flags mean case insensitive, . includes new lines, multiline (probably no effect).  See http://php.net/manual/en/reference.pcre.pattern.modifiers.php
		 */
		$match = preg_match_all('/<html>(.+?)<\/html>/ims',$text,$out_plain);
		
		$html = text_to_unwrappedhtml($text);
		
		// get all the <html> groups after we convert to html.  
		$match = preg_match_all('/\&lt;html\&gt;(.+?)\&lt;\/html\&gt;/ims',$html,$out_html);
		
		// we then go one by one and replace converted content with the body of the original <html> tag
		foreach($out_html[0] as $sub_index => $overconverted_html) {
			$html = str_ireplace($overconverted_html, $out_plain[1][$sub_index], $html);
		}
		return $html;
	}
	
	/**
	 * Converts the short hand [io/nn] to <itemversion>nn</itemversion>.  Does something similar with [io/nn].
	 * This also handles full url Link to Page style links.
	 * @param string $event_description
	 * @return string
	 */
	public static function embeddedLocatorsToItemVersionTags($event_description) {

		// io
		$match = preg_match_all('/\[io\/(\d+)\]/i',$event_description,$out);
		// $out[0] has the whole opening and closing tag.  $out[1] has only the enclosed itemversion_id
		foreach($out[1] as $sub_index => $itemobject_id) {
			$itemversion_id = DBTableRowItemVersion::getItemVersionIdFromByObjectId($itemobject_id);
			if (is_numeric($itemversion_id)) {
				$event_description = str_ireplace($out[0][$sub_index],'<itemversion>'.$itemversion_id.'</itemversion>',$event_description);
			}
		}
		
		// full io locator url
		$match = preg_match_all('/'.preg_quote(formatAbsoluteLocatorUrl('io',''),'/').'(\d+)/i',$event_description,$out);
		// $out[0] has the whole opening and closing tag.  $out[1] has only the enclosed itemversion_id
		foreach($out[1] as $sub_index => $itemobject_id) {
			$itemversion_id = DBTableRowItemVersion::getItemVersionIdFromByObjectId($itemobject_id);
			if (is_numeric($itemversion_id)) {
				$event_description = str_ireplace($out[0][$sub_index],'<itemversion>'.$itemversion_id.'</itemversion>',$event_description);
			}
		}
		
		// iv
		$match = preg_match_all('/\[iv\/(\d+)\]/i',$event_description,$out);
		// $out[0] has the whole opening and closing tag.  $out[1] has only the enclosed itemversion_id
		foreach($out[1] as $sub_index => $itemversion_id) {
			$event_description = str_ireplace($out[0][$sub_index],'<itemversion>'.$itemversion_id.'</itemversion>',$event_description);
		}

		// full iv locator url
		$match = preg_match_all('/'.preg_quote(formatAbsoluteLocatorUrl('iv',''),'/').'(\d+)/i',$event_description,$out);
		// $out[0] has the whole opening and closing tag.  $out[1] has only the enclosed itemversion_id
		foreach($out[1] as $sub_index => $itemversion_id) {
			$event_description = str_ireplace($out[0][$sub_index],'<itemversion>'.$itemversion_id.'</itemversion>',$event_description);
		}
		return $event_description;
	}
	
	/**
	 * make a string like http://www.google.com into <html><a href="http://www.google.com">http://www.google.com</a></html> or similar
	 * It does not replace links like www.google.com (without the scheme).
	 * @param string $event_description.
	 */
	public static function embeddedLinksToHtmlTags($event_description) {
		// see http://stackoverflow.com/questions/287144/need-a-good-regex-to-convert-urls-to-links-but-leave-existing-links-alone
		/*
		 * This regex has changed many times and is never quite right.  It almost certainly needs to be done a different way.
		 * stuff to check: httpsds   https://www.google.com  
		 */
		return preg_replace( '@(?!(?!.*?<a)[^<]*<\/a>)(https?|ftp|file):[-A-Z0-9+&#/%=~_|$?!,.]*[A-Z0-9+&#/%=~_|$]@i', '<html><a href="\0" target="_blank" title="open link in new tab">\0</a></html>', $event_description );
	}
	
	
	/**
	 * Converts the markdown <itemversion>nn</itemversion> tags to <html></html> tags.
	 * @param string $event_description plain text but with some markdown
	 * @param navigator $navigator
	 * @param string $event_type ('ET_PROCREF', or ...)
	 * @param array $event_description_array
	 * @return mixed array($event_description still in plain text but now with <html>tags inserted for the itemversion markdown.)
	 */
	public static function itemversionMarkupToHtmlTags($event_description, $navigator, $event_type, &$event_description_array, $include_html_wrapper=true) {
		$event_description_array = array();
		$match = preg_match_all('/<itemversion>([0-9]+)<\/itemversion>/i',$event_description,$out);
		// $out[0] has the whole opening and closing tag.  $out[1] has only the enclosed itemversion_id
		foreach($out[1] as $sub_index => $itemversion_id) {
			$ItemVersion = DbSchema::getInstance()->getItemVersionCachedRecordById($itemversion_id);
			$event_description_array[$itemversion_id] = array();
			$serial_identifier = empty($ItemVersion->item_serial_number) ? '' : ' ('.TextToHtml($ItemVersion->item_serial_number).')';
			$part_name = TextToHtml($ItemVersion->tv__type_description).$serial_identifier;
			$features = array();
			$features_structured = array();
			// additional description:
			if (in_array($event_type,array('ET_PROCREF','ET_PARTREF'))) {
				foreach($ItemVersion->getFeaturedFieldTypes() as $fieldname => $fieldtype) {
					if (trim($ItemVersion->{$fieldname})!=='') {
						$features[] = '<li><span class="label">'.$ItemVersion->formatFieldnameNoColon($fieldname).':</span> <span class="value">'.$ItemVersion->formatPrintField($fieldname, true, true, true).'</span></li>';
						$features_structured[] = array('name' => $ItemVersion->formatFieldnameNoColon($fieldname), 'value' => $ItemVersion->formatPrintField($fieldname, true, true, true));
					}
				}
				$featuresstr = implode('',$features);
				if ($featuresstr!='') $featuresstr = ' <ul>'.$featuresstr.'</ul>';
			} else if ($event_type=='ET_CHG') {
				$featuresstr = '';
			} else { // ET_COM
				foreach($ItemVersion->getFeaturedFieldTypes() as $fieldname => $fieldtype) {
					if (trim($ItemVersion->{$fieldname})!=='') {
						$features[] = $fieldname.' = '.$ItemVersion->{$fieldname};
						$features_structured[] = array('name' => $fieldname, 'value' => $ItemVersion->{$fieldname});
					}
				}
				$featuresstr = implode(', ',$features);
				if ($featuresstr!='') $featuresstr = ' ('.$featuresstr.')';
			}
				
			$query_params = array();
			$query_params['itemversion_id'] = $itemversion_id;
			$query_params['resetview'] = 1;
			$edit_url = !is_null($navigator) ? $navigator->getCurrentViewUrl('itemview','',$query_params) : '';
			$link = linkify( $edit_url, $part_name, "go to ".$part_name.", ItemVersion:".$itemversion_id);
			$used_on = ($include_html_wrapper ? '<html>' : '').$link.$featuresstr.($include_html_wrapper ? '</html>' : '');
			$event_description = str_ireplace($out[0][$sub_index],$used_on,$event_description);
			$event_description_array[$itemversion_id]['url'] = $edit_url;
			$event_description_array[$itemversion_id]['features'] = $features_structured;
			$event_description_array[$itemversion_id]['fields'] = $ItemVersion->getArray();
		}
		return $event_description;
	}
	
	/*
	 * This converts any embedded links  into real links to itemversion records.  It actually returns an array.
	 * the first item is formatted Html.  The second is a structured array containing the parsed objects and
	 * data.  $navigator can be null, in which case this is a display only thing that is returned.
	 * $description_is_html true means that the only conversion to be done is expanding <itemversion> markup.
	 */
	public static function textToHtmlWithEmbeddedCodes($event_description, $navigator, $event_type, $description_is_html=false) {		
		// convert to htmlentities, then pick out all the <itemversion>NNN</itemversion> tags. (Note that these are themselves converted to entities)
		$event_description_array = array();
		if (!$description_is_html) $event_description = self::embeddedLocatorsToItemVersionTags($event_description);
		if (!$description_is_html) $event_description = self::embeddedLinksToHtmlTags($event_description);
		$event_description = self::itemversionMarkupToHtmlTags($event_description, $navigator, $event_type, $event_description_array, !$description_is_html);
		if (!$description_is_html) $event_description = self::textToHtmlwithEmbeddedHtml($event_description);
		return array($event_description,$event_description_array);
	}
	
	/*
	public function number_to_kb_format($int) {
		return number_format(ceil($int / 1024)).' KB';
	}
	*/
	
	public static function documentsPackedToFileGallery($baseUrl,$slideshow_unique_id, $documents_packed) {
		$thumbs = $files = array();
		foreach(explode(';',$documents_packed) as $document_packed) {
			list($document_id,$document_filesize,$document_displayed_filename,$document_stored_filename,$document_stored_path,$document_file_type,$document_thumb_exists) = explode(',',$document_packed);
			$document_stored_filename = hextobin($document_stored_filename); // we had to store this earlier for safety
			$document_displayed_filename = hextobin($document_displayed_filename); // we had to store this earlier for safety
			$filename_url = $baseUrl.'/items/documents/'.$document_id."?fmt=medium";
			$size = number_format(ceil($document_filesize / 1024)).' KB';
			
			$fname = $document_displayed_filename.' <span class="size">'.$size.'</span>';
			if ($document_thumb_exists) {
				$title = $document_displayed_filename;
				$thumb_url = $baseUrl.'/items/documents/'.$document_id."?fmt=thumbnail";
				$thumbs[] = '<span class="bd-event-document"><a data-dialog="'.$slideshow_unique_id.'" title="'.$title.'" href="'.$filename_url.'">
						<img style="border:0;" src="'.$thumb_url.'"></a></span>';
			} else {
				$title = 'click to open '.$document_displayed_filename.' ('.$size.')';
				$icon_img = '<IMG style="vertical-align:middle;" src="'.Zend_Controller_Front::getInstance()->getRequest()->getBaseUrl().'/images/'.DBTableRowDocument::findIconFileName($document_file_type, $document_displayed_filename).'" width="16" height="16" border="0" alt="delete">';
				$files[] = '<div class="bd-event-document"><a title="'.$title.'" href="'.$filename_url.'" target="_blank">'.$icon_img.' '.$fname.'</a></div>';
			}
		}
		return implode('',$files).implode('',$thumbs);
	}
	
	/**
	 * Takes the lines output from ::eventStreamRecordsToLines() and renders it into the stream view (RHS of itemview). 
	 * @param DBTableRowItemVersion $dbtable
	 * @param array $lines
	 * @return array of rendered html blocks in chronological order.
	 */
	public static function eventStreamLinesToHtml($dbtable,$lines,$navigator) {
		$dbtable->_navigator = $navigator; // need this for generating print view.
		$baseUrl = Zend_Controller_Front::getInstance()->getRequest()->getBaseUrl();
		$user_records = DbSchema::getInstance()->getRecords('user_id',"SELECT user_id, first_name, last_name FROM user");
		$event_type_to_class = array('ET_CHG' => 'bd-type-change', 'ET_PROCREF' => 'bd-type-link-proc', 'ET_PARTREF' => 'bd-type-link-part', 'ET_COM' => 'bd-type-comment');
		$layout_rows = array();
		
		// see if there are any indented items
		$has_indents = false;
		foreach($lines as $line) {
			if (!empty($line['indented_component_name'])) {
				$has_indents = true;
				break;
			}
		}
		
		foreach($lines as $line_idx => $line) {
		
			$datetime = time_to_bulletdate(strtotime($line['effective_date']));
			$select_radio_html = '';
			$edit_buttons_html = '';
			$documents_html = '';
			$alt_edit_date_html = '';
		
			if ($line['event_type_id']=='ET_CHG') {
				$this_itemversion_id = $line['this_itemversion_id'];
				$selected = $line['is_selected_version'] ? ' checked="checked"' : '';
				$onclick_html = " onClick=\"window.location='".$line['version_url']."'\"";
				$select_radio_html = '<div class="bd-event-select-button"><input class="radioclass" type="radio" name="itemversion_ck" value="'.$this_itemversion_id.'" id="itemversion_ck_'.$this_itemversion_id.'"'.$selected.$onclick_html.' /></div>';
				$edit_buttons_html = '<div class="bd-edit">'.implode('',$line['edit_links']).'</div>';
				$one_hour = 3600;
				// if entering a date in the future, that's weird.  If entering a date more than one day past, that's weird too.
				$is_weird_record_created_date = (strtotime($line['record_created']) + $one_hour < strtotime($line['effective_date']))
						|| (strtotime($line['record_created']) - 25*$one_hour > strtotime($line['effective_date']));
				$editing_msg = '';
				if ($line['archive_count']>0) {
					$editing_msg = 'Edited '.linkify('#',($line['archive_count']==1 ? 'Once' : $line['archive_count'].' Times'),'show the changes made to this version...','unversioned_pop_link').'<div class="unversioned_pop_div" id="unversioned_pop_'.$this_itemversion_id.'" title="Unversioned Changes" style="display: none;"></div>';    
				} else if ($is_weird_record_created_date) {
					// there are not changes, but we should at least say something about the odd date
					$editing_msg = 'Added: '.time_to_bulletdate(strtotime($line['record_created']),false);
				}
				if ($editing_msg && empty($line['indented_component_name'])) {
					$alt_edit_date_html = '<div class="bd-dateline-edited">('.$editing_msg.')</div>';
				}
			}
		
			if ($line['event_type_id']=='ET_COM') {
				if ($line['documents_packed']) {
					$documents_html = '<div class="bd-event-documents">';
					$documents_html .= self::documentsPackedToFileGallery($baseUrl,'id'.$line_idx, $line['documents_packed']);
					$documents_html .= '</div>';
				} else {
					$documents_html = '';
				}
				$edit_buttons_html = '<div class="bd-edit">'.implode('',$line['edit_links']).'</div>';
			}
		
			$subcomments_html = '';
			$procedure_disposition_html = '';
			if (in_array($line['event_type_id'],array('ET_PARTREF','ET_PROCREF'))) {
				if ($line['comments_packed']) {
					$subcomments_html .= '<div class="bd-event-subcomments-container"><ul class="bd-event-subcomments">';
					foreach(explode('|',$line['comments_packed']) as $subcomment) {
						list($user_id,$comment_added,$comment_text,$subdocuments_packed) = explode('&',$subcomment);
						$subdatetime = time_to_bulletdate(strtotime($comment_added));
						$comment_text = hextobin($comment_text); // we had packed this earlier for safety
						$subcomments_html .= '<li class="bd-event-subcomment">
								<div class="bd-subcomment-who-message">
								<span class="bd-subcomment-byline">'.DBTableRowUser::concatNames($user_records[$user_id]).':</span>'.TextToHtml($comment_text).'
										</div>
						
										';
						if ($subdocuments_packed) {
							$subcomments_html .= '<div class="bd-subcomment-documents">';
							$subcomments_html .= self::documentsPackedToFileGallery($baseUrl,'id'.$line_idx, $subdocuments_packed);
							$subcomments_html .= '</div>';
						}
						$subcomments_html .= '<div class="bd-subcomment-when">'.$subdatetime.'</div></li>';
					}
					$subcomments_html .= '</ul></div>';
		
				}
				$procedure_disposition_html = '<div class="bd-edit">'.DBTableRowItemVersion::renderDisposition($dbtable->getFieldType('disposition'),$line['disposition']).'</div>';

				// if this is a procedure
				$editing_msg = '';
				if (isset($line['actual_effective_date'])) {
					$editing_msg = 'Edit: '.time_to_bulletdate(strtotime($line['actual_effective_date']),false);
				}
				if ($editing_msg && empty($line['indented_component_name'])) {
					$alt_edit_date_html = '<div class="bd-dateline-edited">('.$editing_msg.')</div>';
				}				
			}
		
		
			$dimmed_class = $line['is_future_version'] ? ' bd-dimmed' : '';
			
			
			$indented_target_link = '';
			$indent_class = '';
			if (!empty($line['indented_component_name'])) {
				$indent_class = ' bd-event-indented';
				$indented_target_link = '<div class="bd-indent-component-link">'.$dbtable->formatPrintField($line['indented_component_name']).'</div>';
				$select_radio_html = '';
				$edit_buttons_html = '';
			} else if ($has_indents) {
				$indent_class = ' bd-event-outdented';
			}
			
			
			$highlight_class = $line['recently_edited'] ? (in_array($line['event_type_id'],array('ET_PROCREF','ET_PARTREF')) ? ' event_afterglow_r' : ' event_afterglow_c') : '';
			$layout_rows[] = '<li class="bd-event-row '.$event_type_to_class[$line['event_type_id']].$dimmed_class.$highlight_class.$indent_class.'">
				'.$select_radio_html.''.$indented_target_link.'
				<div class="bd-event-content'.($alt_edit_date_html ? ' bd-with-edit-date' : '').'">
				<div class="bd-event-type"></div>
				<div class="bd-event-whowhen"><div class="bd-byline">'.strtoupper($line['user_name_html']).'</div><div class="bd-dateline">'.$datetime.'</div>'.$alt_edit_date_html.'</div>
				<div class="bd-event-message">
				'.$edit_buttons_html.$documents_html.$procedure_disposition_html.'
				'.$line['event_html'].'
				</div>
				'.$subcomments_html.'
				</div>
				</li>';
		}
		return $layout_rows;
	}
	
	/**
	 * Takes the lines output from ::eventStreamRecordsToLines() and renders it into simple html that is meant to be rendered
	 * as PDF in ItemViewPDF.
	 * @param DBTableRowItemVersion $dbtable
	 * @param array $lines
	 * @return array of rendered html blocks in chronological order.
	 */
	public static function renderEventStreamHtmlForPdfFromLines($dbtable,$lines,$show_procedures = true) {
		$baseUrl = Zend_Controller_Front::getInstance()->getRequest()->getScheme().'://'.Zend_Controller_Front::getInstance()->getRequest()->getHttpHost().Zend_Controller_Front::getInstance()->getRequest()->getBaseUrl();
		$event_type_to_class = array('ET_CHG' => 'bd-type-change', 'ET_PROCREF' => 'bd-type-link-proc', 'ET_PARTREF' => 'bd-type-link-part', 'ET_COM' => 'bd-type-comment');
		$layout_rows = array();
		foreach($lines as $line) {
	
			$datetime = time_to_bulletdate(strtotime($line['effective_date']), false);
			$documents_html = '';
			$procedure_disposition_html = '';
				
			$show_line = false;
	
			if ($line['event_type_id']=='ET_CHG') {
				$show_line = true;
			}
	
			if ($line['event_type_id']=='ET_COM') {
				if ($line['documents_packed']) {
					$documents_html = '<ul>';
					foreach(explode(';',$line['documents_packed']) as $document_packed) {
						list($document_id,$document_filesize,$document_displayed_filename,$document_stored_filename,$document_stored_path,$document_file_type,$document_thumb_exists) = explode(',',$document_packed);
						$document_stored_filename = hextobin($document_stored_filename); // we had to store this earlier for safety
						$document_displayed_filename = hextobin($document_displayed_filename); // we had to store this earlier for safety
						$filename_url = $baseUrl.'/items/documents/'.$document_id."?fmt=medium";
						$documents_html .= '<li><a href="'.$filename_url.'">'.$document_displayed_filename.'</a></li>';
					}
					$documents_html .= '</ul>';
				}
				$show_line = true;
			}
			
			
			if ($show_procedures && in_array($line['event_type_id'],array('ET_PROCREF'))) {
				$show_line = true;
				$procedure_disposition_html = DBTableRowItemVersion::renderDisposition($dbtable->getFieldType('disposition'),$line['disposition'],false);
			
				// if this is a procedure
				$editing_msg = '';
				if (isset($line['actual_effective_date'])) {
					$editing_msg = 'Edit: '.time_to_bulletdate(strtotime($line['actual_effective_date']),false);
				}
				if ($editing_msg && empty($line['indented_component_name'])) {
					$alt_edit_date_html = '<div class="bd-dateline-edited">('.$editing_msg.')</div>';
				}
			}
			
			if ($line['is_future_version']) $show_line = false;
	
			if ($show_line) {
				$layout_rows[] = array('<b>'.strtoupper($line['user_name_html']).'</b><br /> <i>'.$datetime.'</i>', $line['event_html'].' '.$documents_html, $procedure_disposition_html);
			}
		}
		return $layout_rows;
	}
	
	/**
	 * This takes a record representation of the stream array from ::assemblyStreamArray() and generates both an array of lines and an array that
	 * clusters together referring procedures.  The array of lines ($lines) is meant to be rendered into the html event stream
	 * on the itemview page using self::eventStreamLinesToHtml() and $references_by_typeobject_id is meant to be turned into
	 * the dashboard view using self::renderDashBoardView(). The return value is an array.
	 * @param array $records  these are the records that come from the assembleSteamArray()
	 * @param unknown_type $dbtable
	 * @param unknown_type $navigator  we allow this to be null in case we want to use this to generate a view only page like pdf
	 * @return $lines, $references_by_typeobject_id
	 */
	public static function eventStreamRecordsToLines($records,$dbtable, $navigator=null) {
	
		$references_by_typeobject_id = array();
		$return_url = !is_null($navigator) ? $navigator->getCurrentViewUrl(null,null,array('itemversion_id' => $dbtable->itemversion_id)) : '';
		
		/*
		 * Determine the return url for version delete operations. 
		 */
		$delete_failed_return_url = !is_null($navigator) ? $navigator->getCurrentViewUrl(null,null,array('itemobject_id' => $dbtable->itemobject_id)) : '';
		$delete_done_return_url = $delete_failed_return_url;
		$version_count = 0;
		foreach($records as $record) {
			if (($record['event_type_id']=='ET_CHG') && (!isset($record['indented_component_name']))) $version_count++;
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
			$is_selected_version = isset($record['this_itemversion_id']) && ($record['this_itemversion_id']==$dbtable->itemversion_id);

			if ($previously_found_active_version && ($record['event_type_id']=='ET_CHG') && !$is_selected_version && empty($record['indented_component_name'])) {
				$is_future_version = true;
			}
			$error = '';
			if (isset($record['error_message'])) {
				list($event_description,$event_description_array) = EventStream::textToHtmlWithEmbeddedCodes($record['error_message'], $navigator, $record['event_type_id']);
				$error = '<div class="event_error">'.$event_description.'</div>';
			}

			$links = array();

			$version_url = '';
			if (($record['event_type_id']=='ET_CHG')) {
				$query_params = array();
				$query_params['itemversion_id'] = $record['this_itemversion_id'];
				$version_url = !is_null($navigator) ? $navigator->getCurrentViewUrl('itemview','',$query_params) : '';
			}

			list($event_description,$event_description_array) = EventStream::textToHtmlWithEmbeddedCodes($record['event_description'], $navigator, $record['event_type_id'], $record['description_is_html']);
			$line = array(
					'event_type_id' => isset($record['event_type_id']) ? $record['event_type_id'] : null,
					'this_itemversion_id' => isset($record['this_itemversion_id']) ? $record['this_itemversion_id'] : null,
					'user_name_html' => TextToHtml(DBTableRowUser::concatNames($record)),
					'version_url' => $version_url,
					'is_selected_version' => $is_selected_version,
					'event_html'=> $event_description.$error,
					'event_description_array' => $event_description_array,
					'effective_date' => isset($record['effective_date']) ? $record['effective_date'] : null,
					'record_created' => isset($record['record_created']) ? $record['record_created'] : null,
					'archive_count' => isset($record['archive_count']) ? $record['archive_count'] : null,
					'oldest_record_created' => isset($record['oldest_record_created']) ? $record['oldest_record_created'] : null,
					'is_future_version' => $is_future_version,
					'documents_packed'=>'',
					'comments_packed' =>'',
					'recently_edited' => false);
			if (!empty($record['indented_component_name'])) $line['indented_component_name'] = $record['indented_component_name'];

			if (($record['event_type_id']=='ET_CHG')) {
				$is_a_procedure = DBTableRowTypeVersion::isTypeCategoryAProcedure($dbtable->tv__typecategory_id);
				$is_current_version = $dbtable->io__cached_current_itemversion_id==$record['this_itemversion_id'];	
				$record_created = $record['record_created'];
				if ($record['archive_count']>0) {
					$record_created = $record['oldest_record_created'];
				}			
				$line['edit_links'] = !is_null($navigator) ? DBTableRowItemVersion::itemversionEditLinks($navigator, $return_url, $delete_failed_return_url, $delete_done_return_url, $dbtable->itemobject_id, $record['this_itemversion_id'], $record_created, $record['user_id'],$record['proxy_user_id'],$is_a_procedure,$is_current_version) : array();
			}
			
			
			if (in_array($record['event_type_id'],array('ET_PROCREF','ET_PARTREF'))) {
				if (isset($record['comments_packed']) && $record['comments_packed']) {
					$line['comments_packed'] = $record['comments_packed'];
				}
				if (isset($record['actual_effective_date'])) $line['actual_effective_date'] = $record['actual_effective_date'];
				if (DBTableRow::wasItemTouchedRecently('itemversion'.$record['this_typeobject_id'], $record['this_itemversion_id'])) {
					$line['recently_edited'] = true;
				}
				$line['disposition'] = $record['disposition'];
				$line['is_current_version'] = $record['is_current_version'];
				$line['this_itemobject_id'] = $record['this_itemobject_id'];
				
				if (empty($record['indented_component_name'])) {
					if(!isset($references_by_typeobject_id[$record['this_typeobject_id']])) $references_by_typeobject_id[$record['this_typeobject_id']] = array();
					$references_by_typeobject_id[$record['this_typeobject_id']][] = $line;
				}
				
			}

			if ($record['event_type_id']=='ET_COM') {
				$line['edit_links'] = !is_null($navigator) ? DBTableRowComment::commentEditLinks($navigator, $return_url, $dbtable->itemobject_id, $record['comment_id'], $record['effective_date'],$record['user_id'],$record['proxy_user_id']) : array();
				if (isset($record['documents_packed']) && $record['documents_packed']) {
					$line['documents_packed'] = $record['documents_packed'];
				}
				if (DBTableRow::wasItemTouchedRecently('comment', $record['comment_id'])) {
					$line['recently_edited'] = true;
				}
			}


			$lines[] = $line;


			if (($record['event_type_id']=='ET_CHG') && $is_selected_version) {
				$previously_found_active_version = true;  //
			}

		}

		return array($lines,$references_by_typeobject_id);

	}
		
	/**
	 * takes a list of reference procedures generated from ::eventStreamRecordsToLines()
	 * and formats this into a nice dashboard view for display on the itemview page. 
	 * @param DBTableRowItemVersion $dbtable This is a reference to the parent object
	 * @param array $procedure_records_by_typeobject_id This is a list of those procedure types that are allowed for this particular item
	 * @param array $references_by_typeobject_id  This is a list of all the procedures that actually exist for this item
	 * @return string
	 */
	static public function renderDashboardView($dbtable, $procedure_records, $references_by_typeobject_id, $more_to_show_msg_html='') {
		$html_dashboard = '';

		// ensures only one entry per typeobject_id
		$procedure_records_by_typeobject_id = array();
		foreach($procedure_records as $record) {
			$procedure_records_by_typeobject_id[$record['typeobject_id']] = $record;
		}		
		
		foreach($procedure_records_by_typeobject_id as $typeobject_id => $procedure_record) {
			$references = isset($references_by_typeobject_id[$typeobject_id]) ? $references_by_typeobject_id[$typeobject_id] : array();
			$link = Zend_Registry::get('customAcl')->isAllowed($_SESSION['account']->getRole(),'table:itemversion','add')
			? ' '.linkify($procedure_record['add_url'],'add','','minibutton2').' ' : '';
			$html_dashboard .= '<h3 class="itemview-proc-head">'.$procedure_record['type_description'].$link.'</h3>';
		
			if (count($references)>0) {
		
				$html_dashboard .= '<table class="sublisttable"><colgroup><col class="col1"><col class="col2"><col class="col3"></colgroup>';
				$row = array();
				foreach($references as $reference) {
					$feat = array();
					foreach($reference['event_description_array'] as $iv => $item) {
						
						// if this is a procedure
						$alt_edit_date_html = '';
						if (isset($reference['actual_effective_date'])) {
							$alt_edit_date_html = '<div class="bd-dateline-edited">(Edit: '.time_to_bulletdate(strtotime($reference['actual_effective_date']),false).')</div>';
						}
												
						$row['Date'] = linkify($item['url'], time_to_bulletdate(strtotime($reference['effective_date']),false)).$alt_edit_date_html;
						foreach($item['features'] as $feature) {
							$feat[] = $feature['name'].':&nbsp;<b>'.$feature['value'].'</b>';
						}
						 
					}
					$row['Data'] = count($feat)==0 ? '' : '<ul class="bd-bullet_features"><li>'.implode('</li><li>',$feat).'</li></ul>';
					$row['Pass/Fail'] = DBTableRowItemVersion::renderDisposition($dbtable->getFieldType('disposition'),$reference['disposition']);
					$dimmed_class_attr = $reference['is_future_version'] ? ' class="bd-dimmed"' : '';
					$html_dashboard .= '<tr'.$dimmed_class_attr.'>
				<td>'.nbsp_ifblank($row['Date']).'</td>
	        	<td>'.nbsp_ifblank($row['Data']).'</td>
	        	<td>'.nbsp_ifblank($row['Pass/Fail']).'</td>
	        	</tr>';
				}
				$html_dashboard .= '</table>';
				if ($more_to_show_msg_html) $html_dashboard .= $more_to_show_msg_html;
			} else {
				if ($more_to_show_msg_html) {
					$html_dashboard .= $more_to_show_msg_html;
				} else {
					$html_dashboard .= '<p>No Results.</p>';
				}
			}
		}
		
		return $html_dashboard;
	}
	
	/**
	 * This builds a set of arrays that are useful for building link to Where Used.  As input it uses the output of EventStream::eventStreamRecordsToLines()
	 * @param array $references_by_typeobject_id see eventStreamRecordsToLines
	 * @return array of structures of the form  array('url' => , 'name' => , 'title' => );
	 */
	static public function getUsedOnEntries($references_by_typeobject_id) {
		$usedon = array();
		// I want to group where used buttons by typeobject ID but then only show one per itemobject_id
		foreach($references_by_typeobject_id as $typeobject_id => $lines) {
			foreach($lines as $line) {
				if (($line['event_type_id']=='ET_PARTREF')) { 
					// the order is such that the last assignment will be the most recent (and relevant?)
					foreach($line['event_description_array'] as $arr) {
						$sn = $arr['fields']['item_serial_number'];
						$desc = $arr['fields']['tv__type_description'];
						if ($line['is_current_version']) {
							$usedon[$line['this_itemobject_id']] = array('url' => $arr['url'], 'name' => 'Used On '.$sn, 'title' => 'Used on '.$desc.': '.$sn);
						} else {
							$usedon[$line['this_itemobject_id']] = array('url' => $arr['url'], 'name' => 'Was Used On '.$sn, 'title' => 'Was used on an old version of '.$desc.': '.$sn);
						}
					}
				}
			}
		}	
		return $usedon;	
	}
	
	static public function monthsOfHistoryOptions($max_months) {
		// return 1, 3, 6, 12, 24, 36, 48, ... to above max.
		$out = array(1 => '1 Month');
		if ($max_months > 1) $out[3] = '3 Months';
		if ($max_months > 3) $out[6] = '6 Months';
		if ($max_months > 6) $out[12] = '1 Year';
		$years = 1;
		while ($max_months > $years*12) {
			$years++;
			$out[$years*12] = $years.' Years';
		};
		
		// the last entry should be 'ALL' instead of the number of months.
		$last = array_pop($out);
		$out['ALL'] = $last.' (All)';
		
		foreach($out as $key => $text) {
			$out[$key] = $text.' of History';
		}
		return $out;
	}
	
	static public function roundToNearestMonthsOfHistory($month, $max_months) {
		$out_month = 1;
		foreach(array_keys(self::monthsOfHistoryOptions($max_months)) as $round_month) {
			if ($round_month > $month) {
				$out_month = $round_month;
				break;
			}
		}
		return $out_month;
	}
	
	static public function tooBigRecordCount() {
		return 800;
	}
	
	static public function getTooBigStartingNumMonths(DBTableRowItemVersion $ItemVersion, $length_items) {
		$length_months = $ItemVersion->getTotalMonthsOfHistory();
		$target_starter_count = self::tooBigRecordCount() / 2;
		return self::roundToNearestMonthsOfHistory($target_starter_count/$length_items * $length_months, $length_months);		
	}
	
	static public function getEarliestDateOfHistory($ItemVersion, $months_param) {
		$effective_date_exists = ($ItemVersion->effective_date && (strtotime($ItemVersion->effective_date) != -1));
		$months_in_seconds = $months_param*30*24*3600;
		return time_to_mysqldatetime($effective_date_exists ? strtotime($ItemVersion->effective_date) - $months_in_seconds : script_time() - $months_in_seconds);
	}
	
}

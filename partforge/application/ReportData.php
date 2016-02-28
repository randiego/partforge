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

$baseurl = Zend_Controller_Front::getInstance()->getRequest()->getBaseUrl();
define( 'SORTED_IMG_TAG', '<IMG SRC="'.$baseurl.'/images/sort_asc_large.png" align="absmiddle" BORDER="0" ALT="sorted ascending">' );
define( 'SORTED2_IMG_TAG', '<IMG SRC="'.$baseurl.'/images/sort_asc_med.png" align="absmiddle" BORDER="0" ALT="sorted key 2 ascending">' );
define( 'SORTED3_IMG_TAG', '<IMG SRC="'.$baseurl.'/images/sort_asc_small.png" align="absmiddle" BORDER="0" ALT="sorted key 3 ascending">' );
define( 'SORTED_DESC_IMG_TAG', '<IMG SRC="'.$baseurl.'/images/sort_desc_large.png" align="absmiddle" BORDER="0" ALT="sorted descending">' );
define( 'SORTED2_DESC_IMG_TAG', '<IMG SRC="'.$baseurl.'/images/sort_desc_med.png" align="absmiddle" BORDER="0" ALT="sorted key 2 descending">' );
define( 'SORTED3_DESC_IMG_TAG', '<IMG SRC="'.$baseurl.'/images/sort_desc_small.png" align="absmiddle" BORDER="0" ALT="sorted key 3 descending">' );

abstract class ReportData {
	public $title = '';
	public $fields = array();
	public $csvfields = array(); // CSV field and column name for output
	public $found_import_fields = array();
	public $show_button_column = true;
	public $update_datetime;
	public $show_row_enumeration = true;
	public $use_override_subtitle = false;
	public $default_sort_key='';
	public $search_box_label='';
	public $group_rows = false;
	public $row_break_group_fields = array();
	
	public function override_subtitle_html($numrows) {
		return '';
	}
	
	public function get_records($queryvars, $searchstr,$limitstr) {
		return array();
	}
	public function get_records_count(&$queryvars, $searchstr) {
		return count($this->get_records($queryvars, $searchstr,''));
	}
	public function make_directory_detail(&$queryvars, &$record,&$buttons_arr,&$detail_out,UrlCallRegistry $navigator) {
	}

	public function make_export_detail($queryvars, &$record,&$detail_out) {
		foreach($this->csvfields as $field => $description) {
			$detail_out[$field] = $record[$field];
		}
		return true;
	}
	

/*
	This creates the title tag field description for the sort header.
	$field is the field name in the sort_key entry.  If there is a separator bar |
	then use the string after the bar.  Or, override this method and select based
	on $field.
*/	
	protected function formatSortKeyName($field) {
		$line = explode('|',$field);
		if (count($line)>1) {
			$field = $line[1]; // item after separator is caption for sorting
		} else {
			$field = trim(str_replace(' desc',' (descending)',$field));
			if (preg_match("/([a-zA-Z0-9_]+.){0}([a-zA-Z0-9_() ]+)$/",$field,$arr)) {
				$field = $arr[2];
			}
			$field = format_field_generic($field);
		}
		
		return $field;
	}
	
	private function format_sort_key($sort_key) {
		$fields = explode(',',$sort_key);
		foreach($fields as $index => $field) {
			$fields[$index] = $this->formatSortKeyName($field);
		}
		return implode(', ',$fields);
	}

	public function display_fields(UrlCallRegistry $navigator,$queryvars,$get_legend_only=false) {
		$out = array();
		$curr_sort_array = explode(',', $this->get_sort_key($queryvars));
		$legend_array = array();
		foreach($this->fields as $fieldname => $fielddesc) {
			
			$help_html = $fielddesc['displaylink'] ? $fielddesc['displaylink'].' ' : '';
			if ($fielddesc['display']) {
				$new_sort_array = $curr_sort_array;
				if (isset($fielddesc['key_asc'])) {
					$colkeys1and2 = array();
					$colkeys1 = explode(',',$fielddesc['key_asc']);
					$colkeys1and2[] = $colkeys1[0];
					
					// is there are an alternate direction available?
					$colkeys2 = array();
					if (isset($fielddesc['key_desc'])) {
						$colkeys2 = explode(',',$fielddesc['key_desc']);
						if (count($colkeys2)>0) $colkeys1and2[] = $colkeys2[0];
					}

					if (isset($fielddesc['sort_key_fixed']) && $fielddesc['sort_key_fixed']) {
						$new_sort_array = empty($colkeys2) || (implode(',',$new_sort_array)!=implode(',',$colkeys1)) ? $colkeys1 : $colkeys2;
					} elseif (in_array($new_sort_array[0],$colkeys1and2)) {
						$new_sort_array[0] = !isset($colkeys2[0]) || ($new_sort_array[0]!=$colkeys1[0]) ? $colkeys1[0] : $colkeys2[0];
					} else { // not a primary key
						// make sure we don't have any form of duplicates in the existing keys before we add ours.
						if (isset($new_sort_array[1]) && in_array($new_sort_array[1],$colkeys1and2)) unset($new_sort_array[1]);
						if (isset($new_sort_array[2]) && in_array($new_sort_array[2],$colkeys1and2)) unset($new_sort_array[2]);
						
						// the default key used on the first click is ascending (key_asc) unless this condition is true:
						$initial_click_is_key_desc = (isset($fielddesc['start_key']) && ($fielddesc['start_key']=='key_desc') && isset($fielddesc['key_desc']));
						// put the new key in position $new_sort_array[0]
						array_unshift($new_sort_array, $initial_click_is_key_desc ? $colkeys2[0] : $colkeys1[0]);
					}
					$new_sort_array = array_slice($new_sort_array,0,3);
					$new_sort_keys = implode(',',$new_sort_array);
					$out[$fieldname] = $help_html.linkify($navigator->getCurrentHandlerUrl('btnChangeSortKey','','',array('sort_key' => $new_sort_keys)),$fielddesc['display'],"Sort listing by ".$this->format_sort_key($new_sort_keys));
					
					
					// now indicate which are the second and third sort columns
					if ((count($curr_sort_array)>0) && in_array($curr_sort_array[0],$colkeys1and2)) {
						$out[$fieldname] = $out[$fieldname].'&nbsp;'.($curr_sort_array[0]==$colkeys1[0] ? SORTED_IMG_TAG : SORTED_DESC_IMG_TAG);
						$legend_array[1] = true;
					} elseif ((count($curr_sort_array)>1) && in_array($curr_sort_array[1],$colkeys1and2)) {
						$out[$fieldname] = $out[$fieldname].'&nbsp;'.($curr_sort_array[1]==$colkeys1[0] ? SORTED2_IMG_TAG : SORTED2_DESC_IMG_TAG);
						$legend_array[2] = true;
					} elseif ((count($curr_sort_array)>2) && in_array($curr_sort_array[2],$colkeys1and2)) {
						$out[$fieldname] = $out[$fieldname].'&nbsp;'.($curr_sort_array[2]==$colkeys1[0] ? SORTED3_IMG_TAG : SORTED3_DESC_IMG_TAG);
						$legend_array[3] = true;
					}
				} else {
					$out[$fieldname] = $help_html.$fielddesc['display'];
				}
			}
		}
		if ($get_legend_only) {
			$legend_items = array(1 => SORTED_IMG_TAG.' = primary sort key', 2 => SORTED2_IMG_TAG.' = 2nd sort key', 3 => SORTED3_IMG_TAG.' = 3rd sort key');
			foreach($legend_items as $legend_index => $legend_text) {
				if (!isset($legend_array[$legend_index])) unset($legend_items[$legend_index]); 
			}
			if (count($legend_items)==1) $legend_items = array();
			return $legend_items;
		} else {
			return $out;
		}
	}
	
	public function get_sort_keys($first_key_only=false) {
		$out = array();
		foreach($this->fields as $fieldname => $fielddesc) {
			if ($fielddesc['key_asc']) {
				if ($first_key_only) {
					$arr = explode('|',$fielddesc['key_asc']);
					$out[$fieldname] = $arr[0];
				} else {
					$out[$fieldname] = $fielddesc['key_asc'];
				}
			}
		}
		return $out;
	}
	
	/**
	 * get the sort key from the query vars but make sure that every field is a valid sort key from the sort_key and key_desc column header parameters
	 * @param unknown_type $queryvars
	 * @param unknown_type $strip_caption_for_sql
	 * @return string|mixed
	 */
	public function get_sort_key($queryvars,$strip_caption_for_sql=false) {
		$sort_keys = array();
		foreach($this->fields as $fieldname => $fielddesc) {
			if ($fielddesc['key_asc']) {
				$sort_keys[] = $fielddesc['key_asc'];
			}
			if ($fielddesc['key_desc']) {
				$sort_keys[] = $fielddesc['key_desc'];
			}
		}
		
		
		if (count($sort_keys)==0) {
			return '';
		} else {
			if (in_array($queryvars['sort_key'],$sort_keys)) {
				$out_sort_key = $queryvars['sort_key'];

			} else {
				// make sure the individual fields are represented somewhere in the other sort keys
				$haystack = array();
				foreach($sort_keys as $sort_key) {
					$haystack = array_merge($haystack,explode(',',$sort_key));
				}
				foreach(explode(',',$queryvars['sort_key']) as $key) {
					if (!in_array($key,$haystack)) {
						$default = $this->get_default_sort_key();
						if (!empty($default)) {
							return $default;
						} else {
							return reset($sort_keys);
						}
					}
				}
				$out_sort_key =  $queryvars['sort_key'];
			}
			// when this function is called to build the "order by" sql clause then we need to strip the stuff after the separator bar
			if ($strip_caption_for_sql) {
				$out_key_items = explode(',',$out_sort_key);
				foreach($out_key_items as $idx => $key_item) {
					$line = explode('|',$key_item);
					$out_key_items[$idx] = $line[0];
				}
				$out_sort_key = implode(',',$out_key_items);
			}
			
			return $out_sort_key;
		}
	}
	
	public function get_default_sort_key() {
		if ($this->default_sort_key) {
			return $this->default_sort_key;
		} else {
			$sort_keys = $this->get_sort_keys();
			if (count($sort_keys)==0) {
				return '';
			} else {
				return reset($sort_keys);
			}
		}
	}
	
}

?>

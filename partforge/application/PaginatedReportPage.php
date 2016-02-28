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

class PaginatedReportPage {
	protected $queryvars;
	protected $_report_data_obj;
	public $search_btn_table = '';
	protected $_navigator;
	protected $rows_per_page_array = array('5','10','30','50','100','500','1000');
	
	public function __construct($queryvars,ReportDataWithCategory $table_obj, $navigator) {
		$this->queryvars = $queryvars;
		$this->_report_data_obj = $table_obj;
		$this->_navigator = $navigator;
	}
	
	/*
	  the following is the handler for fairly generic webpage events
	*/
	public function sort_and_search_handler() {
		$handle = false;
		 
		if (isset($this->queryvars['btnSearch']) || ($this->queryvars['search_string'] != '')) {
			$handle = true;
		}

		/*
		 list of mutually exclusive actions:
		*/
		switch (true)
		{
			case isset($this->queryvars['btnChangeSortKey']):
				$handle = true;
				break;
			case ($this->queryvars['btnOnChange'] == 'rowschange'):
				$_SESSION['account']->pref_rows_per_page = $this->queryvars['rows_per_page'];
				$_SESSION['account']->save(array('pref_rows_per_page'));
				$handle = true;
				break;
		}
		if ($handle) {
			unset($this->queryvars['pageno']); // all handled commands but this one require resetting to the first page which this does
			$this->_navigator->jumpToView();
		}
	}

	
	protected function title_html($numrows) {
		$html = $this->_report_data_obj->title;
		if ($this->queryvars['search_string'] || $this->queryvars['search_date_to'] || $this->queryvars['search_date_from']) {
			$hold_prop_params = $this->_navigator->getPropagatingParamNames();
			$unsearchlink_html = linkify($this->_navigator->unsetPropagatingParam(array('search_string','search_date_to','search_date_from'))->getCurrentViewUrl(),'clear search','go back to viewing all '.$this->_report_data_obj->title.' entries');
			$this->_navigator->setPropagatingParamNames($hold_prop_params); // put it back for the rest of the links on this page
			$matching = $numrows.' records';
			if ($this->queryvars['search_string']) {
				$matching .= ' matching "'.TextToHtml($this->queryvars['search_string']).'"';
			}
			if ($this->queryvars['search_date_from']) {
				$matching .= ' from '.$this->queryvars['search_date_from'];
			}
			if ($this->queryvars['search_date_to']) {
				$matching .= ' to '.$this->queryvars['search_date_to'];
			}
			$html .= ' - Search Results<br><span class="undertitleparen">'.$matching.' ('.$unsearchlink_html.')</span>';
		} elseif ($this->_report_data_obj->use_override_subtitle) {
			$html .= '<br><span class="smallundertitleparen">'.$this->_report_data_obj->override_subtitle_html($numrows).'</span>';
		} else {
			$text = array();
			if ($this->_report_data_obj->show_row_enumeration) {
				$text[] = 'Total records: '.$numrows;
			}
				
			$html .= '<br><span class="smallundertitleparen">'.implode('.  ',$text).'</span>';
		}
		return '<h1 style="clear: right;">'.$html.'</h1>';
	}

	protected function set_selection_defaults() {
		//  make sure each of the filter view setting are pointing to something sensible
		$temp = $_SESSION['account']->getArray();
		if ($temp['pref_rows_per_page'] != ($_SESSION['account']->pref_rows_per_page = make_unknown_a_number_between($_SESSION['account']->pref_rows_per_page, reset($this->rows_per_page_array), end($this->rows_per_page_array)))) {
			$_SESSION['account']->save(array('pref_rows_per_page'));
		}
	}
	
	protected function rows_select_tag_if_enough_rows($numrows, $rows_per_page) {
		$select_array = array();
		$selected = '';
		foreach($this->rows_per_page_array as $rows) {
			$select_array[$rows] = $rows.' rows per page';
			if ($rows_per_page == $rows) {
				$selected = $rows;
			}
		}
		
		return ($numrows > reset($this->rows_per_page_array)) ? format_select_tag($select_array,'rows_per_page',array('rows_per_page' => $selected),"document.theform.btnOnChange.value='rowschange';document.theform.submit();return false;") : '';
	}
	

	protected function right_pagination_url($pageno, $lastpage, $navigator) {
		$params = $navigator->getPropagatingParamValues();
		if ($pageno == $lastpage) {
			return '';
		} else {
			$params['pageno'] = $pageno+1;
			return $navigator->getCurrentViewUrl('','',$params);
		}
	}	

	protected function build_pagination_line_html($pageno, $lastpage, $navigator) {
		$pagination_elements = array();
		$params = $navigator->getPropagatingParamValues();
		$max_pages = 9; // max number of pages to show (should be odd)
	
		if ($pageno > 1) {
			$params['pageno'] = $pageno-1;
			$pagination_elements[] = linkify($navigator->getCurrentViewUrl('','',$params), 'Previous', 'Previous Page');
			$pagination_elements[] = '<span>&bull;</span>';
		}
		
		$nshowing = $lastpage > $max_pages ? $max_pages : $lastpage;
		$nside = (int)(($max_pages - 1)/2);
		if ($pageno <= $nside) {
			$start = 1;
			$end = $nshowing;
		} elseif ($lastpage - $pageno <= $nside) {
			$start = 1 + $lastpage - $nshowing;
			$end = $lastpage;
		} else {
			$start = $pageno - $nside;
			$end = $pageno + $nside;
		}
		
		if ($start>1) { // if we are not showing 1 already, then want to show it now.
			$params['pageno'] = 1;
			$url = $navigator->getCurrentViewUrl('','',$params);
			$pagination_elements[] = linkify($url, "1", 'Goto First Page');
			if ($start != 2) $pagination_elements[] = '<span>&hellip;</span>';  // handles odd case where ellipsis would separate conseq nums.
		}
		
		for($i=$start; $i<=$end; $i++) {
			$params['pageno'] = $i;
			$url = $navigator->getCurrentViewUrl('','',$params);
			if ($pageno==$i) {
				$pagination_elements[] = '<span>'.$i.'</span>';
			} else {
				$pagination_elements[] = linkify($url, $i, 'Goto Page '.$i.' of '.$lastpage);
			}
		}
		
		if ($end<$lastpage) { // if we are not showing 1 already, then want to show it now.
			$params['pageno'] = $lastpage;
			$url = $navigator->getCurrentViewUrl('','',$params);
			if ($lastpage - $end != 1) $pagination_elements[] = '<span>&hellip;</span>';  // handles odd case where ellipsis would separate conseq nums.
			$pagination_elements[] = linkify($url, $lastpage, 'Goto Last Page');
		}
				
		if ($pageno < $lastpage) {
			$params['pageno'] = $pageno+1;
			$pagination_elements[] = '<span>&bull;</span>';
			$pagination_elements[] = linkify($navigator->getCurrentViewUrl('','',$params), 'Next','Next Page');
		}
	
		return '<span class="paginationGroup">'.implode('',$pagination_elements).'</span>';
	}	
	
	protected function build_limit_and_pagination_params($current_pageno, $rows_per_page, $numrows, &$top_record_number, &$limit, &$paginationline_html, &$right_pagination_url, $navigator) {
		$lastpage = ceil($numrows/$rows_per_page);
		$pageno = make_unknown_a_number_between($current_pageno, 1, $lastpage);
		$top_record_number = ($pageno - 1) * $rows_per_page;
	
		$limit = 'LIMIT ' .$top_record_number.',' .$rows_per_page;
			
		if (($lastpage > 1)) {
			$paginationline_html = $this->build_pagination_line_html($pageno, $lastpage, $navigator);
			$right_pagination_url = $this->right_pagination_url($pageno, $lastpage, $navigator);
		} else {
			$paginationline_html = '';
			$right_pagination_url = '';
		}
	}
	
	protected function fetch_search_button_table($top_text, $bottom_text) {
		$top_html = $top_text ? '<span class="paren">'.$top_text.'</span><br>' : '';
		$bottom_html = $bottom_text ? '<br><span class="paren">'.$bottom_text.'</span>' : '';
		return '<div id="itemviewSearchBlock">	
			'.$top_html.$this->fetch_search_button().$bottom_html.'
			</div>';
	}
	
	protected function fetch_search_button() {
		$baseurl = Zend_Controller_Front::getInstance()->getRequest()->getBaseUrl();
		$dbtable = new TableRow();
		$dbtable->setFieldType('search_string',array('Type'=>'char(32)'));
		$dbtable->search_string = $this->queryvars['search_string'];
		if ($this->queryvars['search_string'] || $this->queryvars['search_date_to'] || $this->queryvars['search_date_from']) {
			$hold_prop_params = $this->_navigator->getPropagatingParamNames();
			$unsearchlink_html = linkify($this->_navigator->unsetPropagatingParam(array('search_string','search_date_to','search_date_from'))->getCurrentViewUrl(),'<IMG src="'.$baseurl.'/images/deleteicon.png" width="16" height="16" border="0" alt="clear search">','clear search and go back to viewing all '.$this->_report_data_obj->title.' entries');
			$this->_navigator->setPropagatingParamNames($hold_prop_params); // put it back
		} else {
			$unsearchlink_html = '';
		}
		
		return $dbtable->formatInputTag('search_string').$unsearchlink_html.'
			<INPUT class="searchbutton" TYPE="submit" VALUE="Search" NAME="btnSearch">';
	}
	
	public function fetch_form_body_html($overtable_html = '', $paginatioline_html_addon = '') {
		$html = '';
		
		$this->set_selection_defaults();
		
		$numrows = $this->_report_data_obj->get_records_count($this->queryvars, $this->queryvars['search_string']);
		$this->build_limit_and_pagination_params($this->queryvars['pageno'],$_SESSION['account']->pref_rows_per_page, $numrows, $top_record_number, $limit, $paginationline_html, $right_pagination_url, $this->_navigator);
	
		$records = $this->_report_data_obj->get_records($this->queryvars, $this->queryvars['search_string'],$limit);
		
		$html .= $this->title_html($numrows);
		
		$html .= $overtable_html; // for alternate buttons, for example.
		
		// search input box
		$searchblock = $this->fetch_search_button_table('', '');
		$html .= $searchblock.js_wrapper("
			$(document).ready(function() {
				$('#search_string').watermark('".$this->_report_data_obj->search_box_label."');
			});
		");
		
		$page_line_array = array();
		if ($paginationline_html) $page_line_array[] = $paginationline_html;
		if ($paginatioline_html_addon) $page_line_array[] = $paginatioline_html_addon;

		$html .= '<div class="paginationline">'.implode('<span class="separator">&bull;</span>',$page_line_array).'</div>';
		$header_fields_array = $this->_report_data_obj->display_fields($this->_navigator,$this->queryvars);
		$legend_array = $this->_report_data_obj->display_fields($this->_navigator,$this->queryvars, true);
		
		if (count($records)>0) {

			// count the columns in case we will be doing row grouping
			$column_count = 0;
			if ($this->_report_data_obj->show_row_enumeration) $column_count++;
			$column_count += count($header_fields_array);
			if ($this->_report_data_obj->show_button_column) $column_count++;
			$table_html = '';
			$table_html .= '<TABLE class="'.($this->_report_data_obj->group_rows ? 'listtablegrouped' : 'listtable').'">
					'.str_repeat('<COL>',$column_count).'
					<TR>'.($this->_report_data_obj->show_row_enumeration ? '<TH>&nbsp;</TH>' : '').'
					<TH>'.implode('</TH><TH>',$header_fields_array).'</TH>
					'.($this->_report_data_obj->show_button_column ? '<TH><span class="paren">'.implode('<br>',$legend_array).'</span></TH>' : '').'
					</TR>';

			
			$detail_out = array();
			$detail_out['line_number'] = 0;
			$detail_out['record_number'] = $top_record_number;
			foreach($records as $record) {
				++$detail_out['line_number'];
				++$detail_out['record_number'];
				$detail_out['tr_class'] = ($detail_out['line_number'] % 2 == 0) ? 'even' : 'odd';
				$detail_out['td_class'] = array();
				$buttons = array();
				$display = array();
				$this->_report_data_obj->make_directory_detail($this->queryvars, $record, $buttons, $detail_out,$this->_navigator);

				// add extra row before this record.
				if ($detail_out['fmt_row_break']) {
					$table_html .= '<TR class="row_break"><TD COLSPAN="'.$column_count.'"></TD></TR>'; 
				}
				
				$table_html .= '<TR class="'.$detail_out['tr_class'].'">'.($this->_report_data_obj->show_row_enumeration ? '<TD class="enumeration">'.$detail_out['record_number'].'</TD>' : '');
				foreach(array_keys($header_fields_array) as $fieldname) {
					if ($this->_report_data_obj->group_rows && !$detail_out['fmt_row_break'] && in_array($fieldname,$this->_report_data_obj->row_break_group_fields)) {
						$table_html .= '<TD>&nbsp;</TD>';
					} else {
						$table_html .= '<TD'.(isset($detail_out['td_class'][$fieldname]) ? ' class="'.$detail_out['td_class'][$fieldname].'"' : '').'>'.$detail_out[$fieldname].'</TD>';
					}
				}
				
				if ($this->_report_data_obj->show_button_column) {
					if ($this->_report_data_obj->group_rows && !$detail_out['fmt_row_break'] && in_array('buttons',$this->_report_data_obj->row_break_group_fields)) {
						$table_html .= '<TD>&nbsp;</TD>';
					} else {
						$table_html .= '<TD>'.nbsp_ifblank(implode('&nbsp;',$buttons)).'</TD>';
					}
				}
				
				$table_html .= '</TR>';
			}
			$table_html .= $this->_report_data_obj->group_rows ? '<TR class="row_bottom"><TD COLSPAN="'.$column_count.'"></TD></TR>' : ''; 
			$table_html .= '</TABLE>';
		} else {
			$table_html = '<div class="noentriesfound">No Entries Found</div>';
		}
			
		$html .= '
		<input type="hidden" name="sort_key" value="'.$this->queryvars['sort_key'].'">
		<input type="hidden" name="btnOnChange" value="">
		<table border="0" cellspacing="5" cellpadding="0" style="margin-left: -4px;">
		<tr> 
			<td colspan=2> 
				'.$table_html.'
			</td>
		</tr>
		<tr><td nowrap valign="MIDDLE">'.$this->rows_select_tag_if_enough_rows($numrows, $_SESSION['account']->pref_rows_per_page).'</td><td align="right">'.(($right_pagination_url && (count($header_fields_array) > 4)) ? linkify($right_pagination_url,'&raquo;&raquo;','Next Page') : '&nbsp;').'</td></tr>
		</table>
		<div class="paginationline">'.$paginationline_html.'</div>
';
		return $html;
	}
}


?>

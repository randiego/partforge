<?php
/**
 *
 * PartForge Enterprise Groupware for recording parts and assemblies by serial number and version along with associated test data and comments.
 *
 * Copyright (C) 2013-2023 Randall C. Black <randy@blacksdesign.com>
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

class PaginatedReportPageSimple extends PaginatedReportPage {

    public $subtitle_text = '';

    public function __construct($queryvars, ReportDataWithCategory $table_obj, $navigator)
    {
        parent::__construct($queryvars, $table_obj, $navigator);
    }

    protected function title_html($numrows)
    {
        $html = !empty($this->_override_title_html) ? $this->_override_title_html : $this->_report_data_obj->title;
        $text = array();
        if ($this->subtitle_text) {
            $text[] = $this->subtitle_text;
        }
        if (!empty($this->queryvars['search_string'])) {
            $hold_prop_params = $this->_navigator->getPropagatingParamNames();
            $unsearchlink_html = linkify($this->_navigator->unsetPropagatingParam(array('search_string'))->getCurrentViewUrl(), 'clear search', 'go back to viewing all '.$this->_report_data_obj->title.' entries');
            $this->_navigator->setPropagatingParamNames($hold_prop_params); // put it back for the rest of the links on this page
            $matching = $numrows.' records';
            if (!empty($this->queryvars['search_string'])) {
                $matching .= ' matching "'.TextToHtml($this->queryvars['search_string']).'"';
            }
            $html .= ' - Search Results<br><span class="undertitleparen">'.$matching.' ('.$unsearchlink_html.')</span>';
        } elseif ($this->_report_data_obj->use_override_subtitle) {
            $html .= '<br><span class="smallundertitleparen">'.$this->_report_data_obj->override_subtitle_html($numrows).'</span>';
        } else {
            if ($this->_report_data_obj->show_row_enumeration) {
                $text[] = 'Total records: '.$numrows;
            }

            $html .= '<br><span class="smallundertitleparen">'.implode('.  ', $text).'</span>';
        }
        return '<h3 style="clear: right;">'.$html.'</h3>';
    }

    public function fetch_form_body_html_dashboard($overtable_html = '', $paginatioline_html_addon = '', $sort_link_command_key = 'btnChangeSortKey', $include_fields = null, $rowLimit = "", $nosortlinks = false)
    {
        $html = '';

        $this->set_selection_defaults();
        $search_string = !empty($this->queryvars['search_string']) ? $this->queryvars['search_string'] : '';
        $numrows = $this->_report_data_obj->get_records_count($this->queryvars, $search_string);
        $more_records_notice_html = "";
        if (is_numeric($rowLimit)) {
            $this->build_limit_and_pagination_params('', $rowLimit, $numrows, $top_record_number, $limit, $paginationline_html, $right_pagination_url, $this->_navigator);
            $more_records_notice_html = $numrows > $rowLimit ? "There are ".($numrows - $rowLimit)." more records" : "";
        } else {
            $limit = "";
        }
        $top_record_number = 0;
        $paginationline_html = "";
        $right_pagination_url = "";

        $records = $this->_report_data_obj->get_records($this->queryvars, $search_string, $limit);

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
        if ($paginationline_html) {
            $page_line_array[] = $paginationline_html;
        }
        if ($paginatioline_html_addon) {
            $page_line_array[] = $paginatioline_html_addon;
        }

        $html .= '<div class="paginationline">'.implode('<span class="separator">&bull;</span>', $page_line_array).'</div>';
        $header_fields_array = $this->_report_data_obj->display_fields($this->_navigator, $this->queryvars, false, $sort_link_command_key, $nosortlinks);

        // handle case where we are showing a subset
        if (is_array($include_fields)) {
            $new_headers = array();
            foreach ($include_fields as $fn) {
                if (isset($header_fields_array[$fn])) {
                    $new_headers[$fn] = $header_fields_array[$fn];
                }
            }
            $header_fields_array = $new_headers;
        }

        if (count($records)>0) {
            // count the columns in case we will be doing row grouping
            $column_count = 0;
            if ($this->_report_data_obj->show_row_enumeration) {
                $column_count++;
            }
            $column_count += count($header_fields_array);
            $table_html = '';
            $table_html .= '<TABLE class="'.($this->_report_data_obj->group_rows ? 'listtablegrouped' : 'listtable').'">
					<colgroup>'.str_repeat('<COL>', $column_count).'</colgroup>
					<TR>'.($this->_report_data_obj->show_row_enumeration ? '<TH>&nbsp;</TH>' : '').'
					<TH>'.implode('</TH><TH>', $header_fields_array).'</TH>
					</TR>';


            $detail_out = array();
            $detail_out['line_number'] = 0;
            $detail_out['record_number'] = $top_record_number;
            foreach ($records as $record) {
                ++$detail_out['line_number'];
                ++$detail_out['record_number'];
                $detail_out['tr_class'] = ($detail_out['line_number'] % 2 == 0) ? 'even' : 'odd';
                $detail_out['td_class'] = array();
                $buttons = array();
                $display = array();
                $this->_report_data_obj->make_directory_detail($this->queryvars, $record, $buttons, $detail_out, $this->_navigator);

                // add extra row before this record.
                if (!empty($detail_out['fmt_row_break'])) {
                    $table_html .= '<TR class="row_break"><TD COLSPAN="'.$column_count.'">&nbsp;</TD></TR>';
                }

                $table_html .= '<TR class="'.$detail_out['tr_class'].'">'.($this->_report_data_obj->show_row_enumeration ? '<TD class="enumeration">'.nbsp_ifblank($detail_out['record_number']).'</TD>' : '');
                foreach (array_keys($header_fields_array) as $fieldname) {
                    if ($this->_report_data_obj->group_rows && !$detail_out['fmt_row_break'] && in_array($fieldname, $this->_report_data_obj->row_break_group_fields)) {
                        $table_html .= '<TD>&nbsp;</TD>';
                    } else {
                        $table_html .= '<TD'.(isset($detail_out['td_class'][$fieldname]) ? ' class="'.$detail_out['td_class'][$fieldname].'"' : '').'>'.nbsp_ifblank($detail_out[$fieldname]).'</TD>';
                    }
                }
                $table_html .= '</TR>';
            }
            $table_html .= $this->_report_data_obj->group_rows ? '<TR class="row_bottom"><TD COLSPAN="'.$column_count.'">&nbsp;</TD></TR>' : '';
            $table_html .= '</TABLE>';
        } else {
            $table_html = '<div class="noentriesfound">No Entries Found</div>';
        }

        $html .= '
		<table border="0" cellspacing="5" cellpadding="0" style="margin-left: -4px;">
		<tr>
			<td colspan=2>
				'.$table_html.'
			</td>
		</tr>
		</table>
		<div class="paginationline">'.$more_records_notice_html.'</div>
';
        return $html;
    }

}

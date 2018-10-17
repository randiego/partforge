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

class CsvGenerator {
    private $_params;
    private $_report_data;
    private $_passthru_fields;
    
    public function __construct($params,ReportData $report_data,$passthru_fields=array()) {
        $this->_params = $params;
        $this->_report_data = $report_data;
        $this->_passthru_fields = $passthru_fields; // these fields should be passed directly to output without quotes or quoting
    }
    
    static public function arrayToCsv(&$records,$fieldcaptions,$passthrough_fields=array()) {
        $out = '';
        $first = true;
        foreach($records as $record) {
                if ($first) {
                        $first = false;
                        $arr = array();
                        foreach($fieldcaptions as $field => $caption) {
                                $arr[] = '"'.$caption.'"';
                        }
                        $out .= implode(',',$arr)."\r\n";
                }
                $arr = array();
                foreach($fieldcaptions as $field => $caption) {
                    if (in_array($field,$passthrough_fields)) {
                        $arr[] = $record[$field];
                    } else {
                        $arr[] = '"'.str_replace('"',"'",$record[$field]).'"';
                    }
                }
                $out .= implode(',',$arr)."\r\n";
        }
        return $out;
    }
    
    
    public function outputToBrowser($filename='') {
        if ($filename=='') $filename = $this->_params['filename'];
        $records = $this->_report_data->get_records($this->_params, $this->_params['search_string'],'');
        $records_out = array();
        foreach($records as $index => $record) {
            $out_rec = array();
            if ($this->_report_data->make_export_detail($this->_params, $record, $out_rec)) {
                $records_out[$index] = $out_rec;
            }
        }
        send_download_headers('text/csv', $filename);
        echo self::arrayToCsv($records_out,$this->_report_data->csvfields,$this->_passthru_fields);
        exit;
    }
    
}

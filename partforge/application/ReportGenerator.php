<?php
/**
 *
 * PartForge Enterprise Groupware for recording parts and assemblies by serial number and version along with associated test data and comments.
 *
 * Copyright (C) 2013-2021 Randall C. Black <randy@blacksdesign.com>
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

class ReportGenerator {

    public $_rows = array();
    public $_headers = array();
    public $_out_rows = array();
    public $_out_headers = array();
    private $_sortkeys = array();

    public function __construct()
    {
        require_once("../library/phplot/phplot.php");
        DbSchema::getInstance()->connectReadOnly();  // we don't want someone writing a report to accidentally update.  So connect read only.
    }

    /**
     * This scans the /reports directory for report plugins (class files inherited from ReportGenerator)
     * and returns an array of arrays. Each subarray is a dictionary  with class_path, class_name
     * and title.
     *
     * @return array of array
     */
    static function getReportList()
    {
        $OutputUrl = Zend_Controller_Front::getInstance()->getRequest()->getBaseUrl().'/reports';
        $cachelist = DBSchema::getInstance()->getRecords('class_name', "SELECT * FROM reportcache");
        $path = Zend_Registry::get('config')->reports_classes_path;
        $files = scandir($path);
        $out = array();
        foreach ($files as $file) {
            $pathinfo = pathinfo($file);
            if ($pathinfo['extension']=='php') {
                $row = array();
                $row['class_path'] = $path.'/'.$file;
                $row['class_name'] = basename($file, '.php');
                $class_name = $row['class_name'];
                try {
                    require_once($path.'/'.$file);
                    $Report = new $class_name();
                    $row['title'] = $Report->getTitle();
                    $row['description'] = $Report->getDescription();
                    $row['update_interval'] = $Report->getUpdateIntervalInDays();
                    $graphfile = $Report->outputFileName('.png');
                    if (file_exists($graphfile)) {
                        $filename = pathinfo($graphfile, PATHINFO_BASENAME);
                        $row['graph_file_url'] = $OutputUrl.'/'.$filename;
                    }
                } catch (Exception $e) {
                    $row['title'] = 'Error Reading Report Class ['.$class_name.']: '.$e->getMessage();
                }
                if (isset($cachelist[$row['class_name']])) {
                    $cache = $cachelist[$row['class_name']];
                    $row['last_run'] = $cache['last_run'];
                    $row['reportcache_id'] = $cache['reportcache_id'];
                }
                $out[] = $row;
            }
        }
        return $out;
    }

    public function getRecords($idfieldname, $query)
    {
        return DbSchema::getInstance()->getRecords($idfieldname, $query);
    }

    static function getReportObject($classname)
    {
        require_once(Zend_Registry::get('config')->reports_classes_path.'/'.$classname.'.php');
        $Report = new $classname();
        return $Report;
    }

    public function getTitle()
    {
        return 'Override me to put in my own title';
    }

    public function getDescription()
    {
        return 'Override me to put in my own description';
    }

    /*
     * This is how often this report should be updated
     */
    public function getUpdateIntervalInDays()
    {
        return 1.0;
    }

    public function sort_compare($a, $b)
    {
        foreach ($this->_sortkeys as $sort_key) {
            // note $sort_key = array('type' => 'time', 'fieldname' => 'name')
            $a_val = $a[$sort_key['fieldname']];
            $b_val = $b[$sort_key['fieldname']];
            if (isset($sort_key['type']) && ($sort_key['type']=='time')) {
                $a_val = strtotime($a_val);
                $b_val = strtotime($b_val);
            }
            if ($a_val < $b_val) {
                return -1;
            } elseif ($a_val > $b_val) {
                return 1;
            }
        }
        return 0;
    }

    /**
     * Sort the array $this->_out_rows using the keys and types defined in the $keys structure.
     * @param unknown_type $keys
     */
    public function sortoutby($keys)
    {
        $this->_sortkeys = $keys;
        uasort($this->_out_rows, array($this, 'sort_compare'));
    }

    /**
     * Removes duplicates (based on field listed in $match_fields array) from the array.
     * @param unknown_type $match_fields
     */
    public function keep_first_unique($match_fields)
    {
        $hold = array_combine($match_fields, array_fill(0, count($match_fields), null));
        foreach ($this->_out_rows as $i => $row) {
            $diff = false;
            foreach ($match_fields as $match_field) {
                if ($row[$match_field] != $hold[$match_field]) {
                    $diff = true;
                    break;
                }
            }
            foreach ($match_fields as $match_field) {
                $hold[$match_field] = $row[$match_field];
            }
            if (!$diff) {
                unset($this->_out_rows[$i]);
            }
        }
    }


    public function process()
    {
        /*
         * Override this method in the child class something like this:

        $this->extractJoinedWorkingSet(9,11,'xxxx_probe','xxxx_probe');
        $this->_headers['daysaftercooldown'] = 'Days After Cooldown';
        $this->_out_rows = array();
        foreach($this->_rows as $i => $row) {
            $row['daysaftercooldown'] = (strtotime($row['B.iv__effective_date']) - strtotime($row['A.iv__effective_date']))/3600.0/24.0;
            if (($row['daysaftercooldown'] >= 0.0) && ($row['daysaftercooldown'] <= 7.0)) {
                $this->_out_rows[] = $row;
            }
        }

        $this->sortoutby(array(
                            array('fieldname' => 'A.xxxx_probe'),
                            array('type' => 'time', 'fieldname' => 'A.iv__effective_date'),
                            array('type' => 'time', 'fieldname' => 'B.iv__effective_date')
                        ));


        $this->keep_first_unique(array('A.xxxx_probe','A.iv__effective_date'));


        $this->_out_headers = $this->_headers;

         *
         */



    }

    public function extractJoinedWorkingSet($A_typeobject_id, $B_typeobject_id, $A_joincolumn, $B_joincolumn)
    {

        $procedure_options = DBTableRowTypeVersion::getPartNumbersWAliasesAllowedToUser($_SESSION['account'], true);

        $ReportDataA = new ReportDataItemListView(true, true, isset($procedure_options[$A_typeobject_id]), false, $A_typeobject_id);
        $ReportDataB = new ReportDataItemListView(true, true, isset($procedure_options[$B_typeobject_id]), false, $B_typeobject_id);

        $dummyparms = array();
        // process records to fill out extra fields and do normal format conversion
        $records_outA = $ReportDataA->get_export_detail_records($dummyparms, '', '');
        $records_outB = $ReportDataB->get_export_detail_records($dummyparms, '', '');

        // perform an inner join at this point
        $header_recs = array('A' => $ReportDataA->csvfields, 'B' => $ReportDataB->csvfields);
        $data_recs = array('A' => $records_outA, 'B' => $records_outB);
        $join_columns = array('A' => $A_joincolumn, 'B' => $B_joincolumn);
        list($this->_headers, $this->_rows) = joinItemVersionArraysOnField($header_recs, $data_recs, $join_columns);

    }

    public function extractWorkingSet($typeobject_id)
    {
        $procedure_options = DBTableRowTypeVersion::getPartNumbersWAliasesAllowedToUser($_SESSION['account'], true);
        $ReportData = new ReportDataItemListView(true, true, isset($procedure_options[$typeobject_id]), false, $typeobject_id);
        $dummyparms = array();
        $this->_headers = $ReportData->csvfields;
        $this->_rows = $ReportData->get_export_detail_records($dummyparms, '', '');
    }

    public function cacheCSV()
    {
        $myname = get_class($this);
        file_put_contents($this->outputFileName(), CsvGenerator::arrayToCsv($this->_out_rows, $this->_out_headers));

        // make a note in the database
        DbSchema::getInstance()->connectFullAccess();
        $CacheRecord = new DBTableRowReportCache();
        $CacheRecord->getRecordByClassName($myname); // if we dont get anything, who cares.  We will create it when we save it.
        $CacheRecord->last_run = time_to_mysqldatetime(script_time());
        $CacheRecord->class_name = $myname;
        $CacheRecord->save();
        DbSchema::getInstance()->connectReadOnly();
    }

    /**
     * This should be inherited.  It take the data array and generates a graph and saves it to the location
     */
    public function generateAndSaveGraph($outputfilename)
    {

    }

    public function buildGraphFromSavedCSV()
    {
        $f = $this->outputFileName('.png');
        if (file_exists($f)) {
            unlink($f);
        }
        $this->generateAndSaveGraph($f);
    }


    public function outputFileName($suffix = '.csv')
    {
        $myname = get_class($this);
        return Zend_Registry::get('config')->document_path_base.Zend_Registry::get('config')->reports_output_directory.'/'.$myname.$suffix;
    }

    public function outputCachedCSVToBrowser()
    {
        $myname = get_class($this);
        send_download_headers('text/csv', "{$myname}.csv");
        echo file_get_contents($this->outputFileName());
        die();
    }
}

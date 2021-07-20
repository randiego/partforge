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

class ImportCSV {

    protected $_fields = array();
    public $import_all_columns = true;      // if true all the fields are read into the array.  Otherwise, only those specified in the addImportField() call will be read
    public $found_import_fields = array();  // these are the imported fields that we were looking for
    public $input_column_names  = array();  // these are all the import fields that are actually present in the file
    var $update_datetime;
    var $records_updated;
    var $records_inserted;
    var $records_skipped;
    var $import_table;
    protected $_log = array();
    protected $_col_num = array();
    private $_records = array();
    private $_fatal_errors;


    public function __construct()
    {
        $this->_fatal_errors = 0;
    }

    public function addImportField($fieldname, $column_name = null, $optional = false)
    {
        if ($column_name==null) {
            // check for a double underscore.  We want to only use the part after the __ for the column_name
            $arr = explode('__', $fieldname);
            $column_name = strtoupper(str_replace('_', ' ', (count($arr) > 1 ? $arr[1] : $fieldname)  ));
        }
        $this->_fields[$fieldname] = array('column' => $column_name, 'optional' => $optional);
    }

    public function getImportFields()
    {
        $out = array();
        foreach ($this->_fields as $fieldname => $fielddesc) {
            $out[$fieldname] = $fielddesc['column'];
        }
        return $out;
    }

    public function getImportedRecords()
    {
        return $this->_records;
    }


    public function importCSVFile($pcfile, $delimiter = ',', $maxlinelen = 4096)
    {

        if ($fp = fopen($pcfile, "r")) {
            if (!feof($fp)) {
                $this->input_column_names = fgetcsv($fp, $maxlinelen, $delimiter);
                if (is_array($this->input_column_names)) {
                    foreach ($this->input_column_names as $idx => $dn) {
                            $this->input_column_names[$idx] = trim(strtoupper($dn));
                    }

                    $expected_import_fields_by_fieldname = array();
                    foreach ($this->getImportFields() as $fieldname => $columnname) {
                        $expected_import_fields_by_fieldname[strtoupper($columnname)] = $fieldname;
                    }
                    $this->_col_num = array();
                    $expected_col_num = array();  // these are the droids we're looking for
                    foreach ($this->input_column_names as $colinputidx => $colinputname) {
                        if (isset($expected_import_fields_by_fieldname[strtoupper($colinputname)])) {
                            $expected_col_num[$expected_import_fields_by_fieldname[strtoupper($colinputname)]] = $colinputidx;
                        }

                        if ($this->import_all_columns || isset($expected_import_fields_by_fieldname[strtoupper($colinputname)])) {
                            // we want to call these by their defined fieldname if possible, but for those not expected, we can only use the literal column label
                            if (isset($expected_import_fields_by_fieldname[strtoupper($colinputname)])) {
                                $this->_col_num[$expected_import_fields_by_fieldname[strtoupper($colinputname)]] = $colinputidx;
                            } else {
                                $this->_col_num[strtoupper($colinputname)] = $colinputidx;
                            }
                        }
                    }

                    if (!$this->setFoundImportFields(array_keys($expected_col_num))) {
                            $this->_fatal_errors++;
                    }

                    if (!$this->_fatal_errors) {
                        $this->importInitialize();
                        while (!feof($fp)) {
                            $data = fgetcsv($fp, $maxlinelen, $delimiter);
                            if (is_array($data)) {
                                $record = array();
                                foreach ($this->_col_num as $fieldname => $columnnum) {
                                    $record[$fieldname] = $data[$columnnum];
                                }

                                $this->processImportRecord($data, $record);
                            }
                        }
                        $this->importFinalize();
                    }
                }
            }
        } else {
            $this->_log[] = 'Failed to open csv file '.$pcfile;
            $this->_fatal_errors++;
        }

        if ($fp) {
            fclose($fp);
        }
        return $this->_log;
    }

    /**
     * true if there were any fatal errors (e.g., missing required import columns) that occured since creation.
     * @return boolean
     */
    public function importFailed()
    {
        return ($this->_fatal_errors > 0);
    }

    public function headingToColumnNum($heading)
    {
        return $this->_col_num[$heading];
    }

    public function importInitialize()
    {
            $this->update_datetime = time_to_mysqldatetime(script_time());
            $this->records_updated = 0;
            $this->records_skipped = 0;
            $this->records_inserted = 0;
    }

    /*
      Core of import action...
      $rawdata is an array of input row columns.  log is a test list of messages to be present at the end.
      $records contains the associative-indexed values that can then be ->assign()ed to $this->_dbtable.
    */
    protected function processImportRecord(&$rawdata, &$record)
    {
        $this->_records[] = $record;
    }

    public function importFinalize()
    {
            $this->_log[] = '---------------------------------------------------------';
            $this->_log[] = 'Records Inserted = '.$this->records_inserted;
            $this->_log[] = 'Records Updated = '.$this->records_updated;
            $this->_log[] = 'Records Skipped = '.$this->records_skipped;
    }

    public function getRequiredImportFields()
    {
        $out = array();
        foreach ($this->_fields as $fieldname => $fielddesc) {
            if (!$fielddesc['optional']) {
                $out[$fieldname] = $fielddesc['column'];
            }
        }
        return $out;
    }

    public function setFoundImportFields($found_import_fields)
    {
            $errors = 0;
            $this->found_import_fields = $found_import_fields;

        foreach ($this->getRequiredImportFields() as $required_import_fieldname => $columnname) {
            if (!in_array($required_import_fieldname, $found_import_fields)) {
                    $this->_log[] = "Missing required column '$columnname' in import file.";
                    $errors++;
            }
        }
            return ($errors==0);
    }

    public function getPageTitle()
    {
        return 'File Import';
    }

    public function totalAffectRecords()
    {
            return $this->records_updated + $this->records_inserted;
    }

    public function getLog()
    {
        return $this->_log;
    }


}

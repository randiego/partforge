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

class ImportStrategyObjects {

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


    public function __construct()
    {
        $this->addImportField('typeversion_id', null, true);
        $this->addImportField('itemversion_id', null, true);
        $this->addImportField('itemobject_id', null, true);
        $this->addImportField('item_serial_number', null, true);
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

    function getImportFields()
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

        $serious_errors = 0;

        if ($fp = fopen($pcfile, "r")) {
            if (!feof($fp)) {
                $this->input_column_names = fgetcsv($fp, $maxlinelen, $delimiter);
                if (is_array($this->input_column_names)) {
                    foreach ($this->input_column_names as $idx => $dn) {
                            $this->input_column_names[$idx] = trim(strtoupper($dn));
                    }

                    $expected_import_fields_by_fieldname = array();
                    foreach ($this->getImportFields() as $fieldname => $columnname) {
                        $expected_import_fields_by_fieldname[$columnname] = $fieldname;
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
                            $serious_errors++;
                    }

                    if (!$serious_errors) {
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
        }

        if ($fp) {
            fclose($fp);
        }
        return $this->_log;
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

    /**
     * This is a singulation of the storeObjectsFromArray() function.  It uses the same machinery
     * as that method in order to save or update a single object.  We use this rather than going
     * directly to the DBTableRow class because this one has various friendly defaults.
     *
     * @param string $action = NewObject, ReplaceFields, or NewVersion depending on what we want to do with the input
     * @param array $record each field has a key equal to the column name.  Needs to be translated using $curr_field_to_columns to get corresponding field in DB
     * @param array $curr_field_to_columns lists all the columns that are to written.  Also translates fieldnames to column names in the record
     * @param int $typeversion_id
     * @param int $itemversion_id
     * @param int $itemobject_id
     * @param string $user_id if numeric uses the user_id code.  Otherwise uses username (login)
     * @param string $effective_date
     * @param boolean $simulate_only if set will not alter the database
     * @param array $errormsg = list of error message.  Empty means no errors
     */
    public static function storeObjectPerImportRules($action, $record, $curr_field_to_columns, $typeversion_id, $itemversion_id, $itemobject_id, $user_id, $effective_date, $simulate_only, &$errormsg, &$outitemversion_id)
    {
        $ItemVersion = new DBTableRowItemVersion();

        /*
         * action for creating a new itemobject and itemversion
        */
        if ($action=='NewObject') {
            if ($typeversion_id) {
                $ItemVersion->typeversion_id = $typeversion_id;
                if ($user_id) {
                    $ItemVersion->user_id = $user_id;
                }
                if ($effective_date) {
                    $ItemVersion->effective_date = $effective_date;
                }

                // start by initializing the components so that we can pull in any intial values for subcomponent fields like we were editing manually
                $init_components = array();
                $init_components['typeversion_id'] = $typeversion_id;
                foreach ($curr_field_to_columns as $fieldname => $columnname) {
                    $type = $ItemVersion->getFieldType($fieldname);
                    if (!is_null($type)) {
                        if ($type['type']=='component') {
                            $allowedvalues = $ItemVersion->getComponentSelectOptions($fieldname, $ItemVersion->effective_date, '');
                            $allowedvalues[''] = '';
                            $keyval = array_search($record[$columnname], $allowedvalues);
                            if ($keyval!==false) {
                                $init_components[$fieldname] = $keyval;
                            } else {
                                $errormsg[] = "Value {$record[$columnname]} in column {$columnname} not a valid component value.";
                            }
                        }
                    }
                }

                // this can handle pulling in subcomponent values and also diving deep to pull in other related component values
                $ItemVersion->processPostedInitializeVars($init_components);

                // Now that the inherited fields have all been mapped in, time to overwrite the fields
                foreach ($curr_field_to_columns as $fieldname => $columnname) {
                    $type = $ItemVersion->getFieldType($fieldname);
                    if (!is_null($type)) {
                        if ($type['type']!='component') {
                            $ItemVersion->{$fieldname} = $record[$columnname];
                        } else if ($fieldname=='partnumber_alias') {
                            if ($ItemVersion->hasAliases()) {
                                $allowedvalues = extract_column($ItemVersion->getAliases(), 'part_number');
                                $keyval = array_search($record[$columnname], $allowedvalues);
                                if ($keyval!==false) {
                                    $ItemVersion->partnumber_alias = $keyval;
                                } else {
                                    $errormsg[] = "Value {$record[$columnname]} in column {$columnname} not a valid partnumber alias.";
                                }
                            } else {
                                $ItemVersion->partnumber_alias = 0;
                            }
                        }
                    }
                }

                $ItemVersion->validateForFatalFields($ItemVersion->getSaveFieldNames(), $errormsg);
            } else {
                $errormsg[] = 'TypeVersion ID missing.  You must select what type of record you are importing.';
            }

            if ((count($errormsg) == 0) && !$simulate_only) {
                $ItemVersion->saveVersioned($ItemVersion->user_id);
            }
            /*
             * this is for the case where we want to change the value of specific fields in an existing version
            * without creating a new version.  We can even change the field
            */
        } else if ($action=='ReplaceFields') {
            if ($itemversion_id) {
                if ($ItemVersion->getRecordById($itemversion_id)) {
                    if ($user_id) {
                        $ItemVersion->user_id = $user_id;
                    }
                    /*
                     This following assignment is pretty important.  You definitely don't want it to be set to
                     anything if you want to preserve the same version.
                    */
                    if ($typeversion_id) {
                        $ItemVersion->typeversion_id = $typeversion_id;
                    }
                    if ($effective_date) {
                        $ItemVersion->effective_date = $effective_date;
                    }
                    foreach ($curr_field_to_columns as $fieldname => $columnname) {
                        $type = $ItemVersion->getFieldType($fieldname);
                        if (isset($type['type']) && ($type['type']=='component')) {
                            $allowedvalues = $ItemVersion->getComponentSelectOptions($fieldname, $ItemVersion->effective_date, '');
                            $allowedvalues[''] = '';
                            $keyval =  array_search($record[$columnname], $allowedvalues);
                            if ($keyval!==false) {
                                $ItemVersion->{$fieldname} = $keyval;
                            } else {
                                $errormsg[] = "Value {$record[$columnname]} in column {$columnname} not a valid component value.";
                            }
                        } else if ($fieldname=='partnumber_alias') {
                            if ($ItemVersion->hasAliases()) {
                                $allowedvalues = extract_column($ItemVersion->getAliases(), 'part_number');
                                $keyval = array_search($record[$columnname], $allowedvalues);
                                if ($keyval!==false) {
                                    $ItemVersion->partnumber_alias = $keyval;
                                } else {
                                    $errormsg[] = "Value {$record[$columnname]} in column {$columnname} not a valid partnumber alias.";
                                }
                            } else {
                                $ItemVersion->partnumber_alias = 0;
                            }
                        } else {
                            $ItemVersion->{$fieldname} = $record[$columnname];
                        }
                    }
                    $fields_to_check = $ItemVersion->getSaveFieldNames();
                    $fields_to_check = array_diff($fields_to_check, array('effective_date'));
                    $ItemVersion->validateForFatalFields($fields_to_check, $errormsg);
                    if ((count($errormsg) == 0) && !$simulate_only) {
                        $ItemVersion->save(array(), true, $ItemVersion->user_id);
                    }
                } else {
                    $errormsg[] = "Item version does not exist.";
                }
            } else {
                $errormsg[] = 'ItemVersion ID missing.  You must specify this for the ReplaceFields action.';
            }
            /*
             * This is the case where we specify a serial number and some other fields.
            * The SN is used to lookup the corresponding
            * itemversion_id and itemobject_id.  If it cannot be found, then a new object is created with that
            * serial number.  In this case, user_id and effective date are required.  If the itemobject_id
            * is found then we obtain the version that is current as of the input effective_date.  A check is
            * done to see if adding the record will result in any new field values.  Note that
            * we also have to make sure the serial number does not change in mid stream too.  If any new fields
            * create a new version, then a new version
            * of the record is added with the new fields.  If not, then the record is skipped since it will not
            * produce a legit new version.  If the only version found is in the future, then that version is
            * loaded and a comparison is done on the field to be updated.  If there are no changes other than
            * then effective date, then the effective date of the existing record is adjusted but no addition
            * version is creeated.
            */
        } else if ($action=='NewVersion') {
            /*
             * First we need to get the base version that we will derive the new version from.
            */
            if ($itemversion_id) {
                if (!$ItemVersion->getRecordById($itemversion_id)) {
                    $errormsg[] = 'ItemVersion specifed but record not found.';
                }
            } else if ($itemobject_id) {
                if (!$ItemVersion->getCurrentRecordByObjectId($itemobject_id, $effective_date)) {
                    $errormsg[] = 'ItemObject specifed but record not found with given effective_date.';
                }
            } else if (isset($curr_field_to_columns['item_serial_number'])) {
                $sn = $record[$curr_field_to_columns['item_serial_number']];
                $records = DBTableRowItemVersion::getRecordsBySerialNumbers($sn, $effective_date);
                if (count($records)==0 && $effective_date) { // try again without the effective_date constraint: maybe the date is too early
                    $records = DBTableRowItemVersion::getRecordsBySerialNumbers($sn);
                }
                if (count($records)==1) {
                    $rec = reset($records);
                    $ItemVersion->getRecordById($rec['itemversion_id']);
                } else if (count($records)>1) {
                    $errormsg[] = 'There are more than one itemversions matching the serial number.';
                } else { // none found
                    if (!$typeversion_id) {
                        $errormsg[] = 'typeversion_id not specified.';
                    } else {
                        $ItemVersion->typeversion_id = $typeversion_id;
                    }
                    // we will be creating a new entry since it doesn't seem to exist.
                    // are we sure about this?
                }
            } else {
                $errormsg[] = 'No itemversion_id, itemobject_id or serial number.';
            }
            $base_version_array = $ItemVersion->getArray();

            if (count($errormsg) == 0) {
                if ($user_id) {
                    $ItemVersion->user_id = $user_id;
                }
                if ($effective_date) {
                    $ItemVersion->effective_date = $effective_date;
                }
                foreach ($curr_field_to_columns as $fieldname => $columnname) {
                    $type = $ItemVersion->getFieldType($fieldname);
                    if ($type['type']=='component') {
                        $allowedvalues = $ItemVersion->getComponentSelectOptions($fieldname, $ItemVersion->effective_date, '');
                        $allowedvalues[''] = '';
                        $keyval = array_search($record[$columnname], $allowedvalues);
                        if ($keyval!==false) {
                            $ItemVersion->{$fieldname} = $keyval;
                        } else {
                            $errormsg[] = "Value {$record[$columnname]} in column {$columnname} not a valid component value.";
                        }
                    } else if ($fieldname=='partnumber_alias') {
                        if ($ItemVersion->hasAliases()) {
                            $allowedvalues = extract_column($ItemVersion->getAliases(), 'part_number');
                            $keyval = array_search($record[$columnname], $allowedvalues);
                            if ($keyval!==false) {
                                $ItemVersion->partnumber_alias = $keyval;
                            } else {
                                $errormsg[] = "Value {$record[$columnname]} in column {$columnname} not a valid partnumber alias.";
                            }
                        } else {
                            $ItemVersion->partnumber_alias = 0;
                        }
                    } else {
                        $ItemVersion->{$fieldname} = $record[$columnname];
                    }
                }
                $ItemVersion->validateForFatalFields($ItemVersion->getSaveFieldNames(), $errormsg);
                $PreviousItemVersion = new DBTableRowItemVersion();
                $PreviousItemVersion->assign($base_version_array);

                $hold_effective_date = $PreviousItemVersion->effective_date;
                $PreviousItemVersion->effective_date = $ItemVersion->effective_date;
                $has_changes = $ItemVersion->checkDifferencesFrom($PreviousItemVersion);
                $PreviousItemVersion->effective_date = $hold_effective_date;
                if ((count($errormsg) == 0) && !$simulate_only) {
                    if (!$has_changes && strtotime($ItemVersion->effective_date)<strtotime($PreviousItemVersion->effective_date)) {
                        $ItemVersion->save(array(), true, $ItemVersion->user_id);  // save the same version only with the earlier date.
                    } else if ($has_changes) {
                        $ItemVersion->saveVersioned($ItemVersion->user_id);
                    }
                }
            } else {
                $errormsg[] = 'Something missing for the NewVersion action.';
            }
        }
        $outitemversion_id = $ItemVersion->itemversion_id;
    }


    /**
     * This will perform an import using parameters in the $_SESSION['importobjectsconfirm']
     * structure (passed as argument).
     * For each input record, the fields are identified by column name (not fieldname).  The
     * translation from between incoming column name and the internal field name is is done
     * using the column_defs array.
     * It will return an array (indexed by record index in $importParams['records'].
     * $simulate_only will generate messages but not perform any updating of the database.
     *
     * @param unknown_type $importParams['records'], ['column_defs']=, ['typeversion_id']
     * @param unknown_type $simulate_only
     */
    public static function storeObjectsFromArray($importParams, $simulate_only = false)
    {
        if (count($importParams['records'])==0) {
            return array();
        }
        $first_row = reset($importParams['records']);

        $field_to_columns = array();
        foreach ($importParams['column_defs'] as $columnname => $fieldname) {
            // make sure we only consider existing columns.
            if (isset($first_row[$columnname]) && $fieldname) {
                $field_to_columns[$fieldname] = $columnname;
            }
        }

        $user_records_by_id = DbSchema::getInstance()->getRecords('user_id', "SELECT * FROM user");
        $user_records_by_loginid = array();
        foreach ($user_records_by_id as $user_id => $user_record) {
            $user_records_by_loginid[$user_record['login_id']] = $user_record;
        }

        $outmessages = array();
        foreach ($importParams['records'] as $idx => $record) {
            $curr_field_to_columns = $field_to_columns;
            $errormsg = array();

            /*
             * Initialize special fields using appropriate defaults
            */
            $action = 'NewObject';
            if (isset($curr_field_to_columns['IMPORT_ACTION']) && isset($record[$curr_field_to_columns['IMPORT_ACTION']])) {
                $action = $record[$curr_field_to_columns['IMPORT_ACTION']];
            }
            unset($curr_field_to_columns['IMPORT_ACTION']);

            $typeversion_id = null;
            // don't use the selection box input if this is a 'ReplaceFields' action.
            if (($action!='ReplaceFields') && isset($importParams['typeversion_id'])) {
                $typeversion_id = $importParams['typeversion_id'];
            }
            if (isset($curr_field_to_columns['typeversion_id']) && is_numeric($record[$curr_field_to_columns['typeversion_id']])) {
                $typeversion_id = $record[$curr_field_to_columns['typeversion_id']];
            }
            unset($curr_field_to_columns['typeversion_id']);

            $itemversion_id = null;
            if (isset($curr_field_to_columns['itemversion_id']) && is_numeric($record[$curr_field_to_columns['itemversion_id']])) {
                $itemversion_id = $record[$curr_field_to_columns['itemversion_id']];
            }
            unset($curr_field_to_columns['itemversion_id']);

            $itemobject_id = null;
            if (isset($curr_field_to_columns['itemobject_id']) && is_numeric($record[$curr_field_to_columns['itemobject_id']])) {
                $itemobject_id = $record[$curr_field_to_columns['itemobject_id']];
            }
            unset($curr_field_to_columns['itemobject_id']);

            $effective_date = null;
            if (isset($curr_field_to_columns['effective_date']) && is_valid_datetime($record[$curr_field_to_columns['effective_date']])) {
                $effective_date = $record[$curr_field_to_columns['effective_date']];
            }
            unset($curr_field_to_columns['effective_date']);

            $user_id = null;
            if (isset($curr_field_to_columns['user_id'])) {
                if (is_numeric($record[$curr_field_to_columns['user_id']])) {
                    $user_id = $record[$curr_field_to_columns['user_id']];
                    // if not numeric, but is a valid login_id, then user that user instead
                } else if (isset($user_records_by_loginid[$record[$curr_field_to_columns['user_id']]])) {
                    $user_id = $user_records_by_loginid[$record[$curr_field_to_columns['user_id']]]['user_id'];
                } else {
                    $errormsg[] = 'User ID not found in user table: '.$record[$curr_field_to_columns['user_id']];
                }
                unset($curr_field_to_columns['user_id']);
            }

            $outitemversion_id = null;
            self::storeObjectPerImportRules($action, $record, $curr_field_to_columns, $typeversion_id, $itemversion_id, $itemobject_id, $user_id, $effective_date, $simulate_only, $errormsg, $outitemversion_id);

            $errortext = count($errormsg) > 0 ? ', ERR:'.implode(', ', $errormsg) : '';
            $outmessages[$idx] = $errortext;
        }
        return $outmessages;
    }

}

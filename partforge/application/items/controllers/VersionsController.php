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

class Items_VersionsController extends RestControllerActionAbstract
{

    /*
     * GET /items/versions
    *
    * Return a list of all versions of all items, subject to query variables.  It will return itemversion_ids or corresponding expanded nested lists.
    *
    * if typeversion_id is specified, all itemversion_id are returned with that specific typeversion_id.  If typeobject_id is specified
    * then those are returned.  If itemobject_id is specified, then all the versions for that specific itemobject_id is returned.
    *
    * Adding get_objects=1 will return the current version data of each of the objects in the return set as nested lists of fields
    *   just like the corresponding /items/objects/nnn get action instead of the itemobject_ids.
    * When get_objects=1, max_depth can also be specified as with the corresponding /items/objects/nnn get action.
    */
    public function indexAction()
    {
        $typeobject_id = isset($this->params['typeobject_id']) ? (is_numeric($this->params['typeobject_id']) ? addslashes($this->params['typeobject_id']) : null) : null;
        $typeversion_id = isset($this->params['typeversion_id']) ? (is_numeric($this->params['typeversion_id']) ? addslashes($this->params['typeversion_id']) : null) : null;
        $itemobject_id = isset($this->params['itemobject_id']) ? (is_numeric($this->params['itemobject_id']) ? addslashes($this->params['itemobject_id']) : null) : null;
        $has_an_itemobject_id = isset($this->params['has_an_itemobject_id']) ? (is_numeric($this->params['has_an_itemobject_id']) ? addslashes($this->params['has_an_itemobject_id']) : null) : null;
        $records = null;
        if (!is_null($itemobject_id)) {
            $and_where = !is_null($has_an_itemobject_id) ? " AND (itemcomponent.has_an_itemobject_id='{$has_an_itemobject_id}')" : '';
            $records = DbSchema::getInstance()->getRecords('', "SELECT DISTINCT itemversion.itemversion_id FROM itemversion
					LEFT JOIN typeversion ON typeversion.typeversion_id=itemversion.typeversion_id
					LEFT JOIN itemcomponent ON itemcomponent.belongs_to_itemversion_id=itemversion.itemversion_id
					WHERE itemversion.itemobject_id='{$itemobject_id}' {$and_where}
					ORDER BY itemversion.effective_date,itemversion.itemversion_id");
        } elseif (!is_null($typeversion_id)) {
            $and_where = !is_null($has_an_itemobject_id) ? " AND (itemcomponent.has_an_itemobject_id='{$has_an_itemobject_id}')" : '';
            $records = DbSchema::getInstance()->getRecords('', "SELECT DISTINCT itemversion.itemversion_id FROM itemversion
					LEFT JOIN typeversion ON typeversion.typeversion_id=itemversion.typeversion_id
					LEFT JOIN itemcomponent ON itemcomponent.belongs_to_itemversion_id=itemversion.itemversion_id
					WHERE typeversion.typeversion_id='{$typeversion_id}' {$and_where}
					ORDER BY itemversion.effective_date,itemversion.itemversion_id");
        } elseif (!is_null($typeobject_id)) {
            $and_where = !is_null($has_an_itemobject_id) ? " AND (itemcomponent.has_an_itemobject_id='{$has_an_itemobject_id}')" : '';
            $records = DbSchema::getInstance()->getRecords('', "SELECT DISTINCT itemversion.itemversion_id FROM itemversion
					LEFT JOIN typeversion ON typeversion.typeversion_id=itemversion.typeversion_id
					LEFT JOIN itemcomponent ON itemcomponent.belongs_to_itemversion_id=itemversion.itemversion_id
					WHERE typeversion.typeobject_id='{$typeobject_id}' {$and_where}
					ORDER BY itemversion.effective_date,itemversion.itemversion_id");
        }

        if (!is_null($records)) {
            $get_objects = isset($this->params['get_objects']) ? $this->params['get_objects']==1 : false;
            if ($get_objects) {
                $max_depth = isset($this->params['max_depth']) ? (is_numeric($this->params['max_depth']) ? $this->params['max_depth'] : null) : null;
                $objects = array();
                foreach ($records as $record) {
                    $objects[] = DBTableRowItemObject::getItemVersionFullNestedArray($record['itemversion_id'], $max_depth, 0);
                }
                $this->view->itemversions = $objects;
            } else {
                $this->view->itemversion_ids = extract_column($records, 'itemversion_id');
            }
        }
    }

    /*
     * GET /items/versions/12
    *
    * Return the specified version of the itemobject_id specified by the parameter.  The form that is returned is by default
    * a nested list of all fields, components drilled all the way down.  Unlike /items/objects/ api call, this returns the
    * version of the component objects that were active at the time of the effective date of this version.
    * main parameter is itemobject_id
    *
    * Input:
    *   id = the itemversion_id of the object being retrieved. (required)
    *   fmt=nested (default) shows a JSON array in fully nested dictionary format
    *   fmt=sheet gives output in JSON format using spreadsheet names
    *   fmt=pdf generates and outputs a pdf file
    *   use_internal_names=1 means that each field will  be indexed by the internal object name when sheet fmt is used, otherwise we use the captions shown in the Save To Spreadsheet function
    *   max_depth=n is the maximum recursion level for building return query.  default is blank (infinite), 0 stops after first level.
    *   Instead of returning an object for a component, it will return the object's serial number string.
    *
    */

    public function getAction()
    {
        $use_internal_names = isset($this->params['use_internal_names']) && ($this->params['use_internal_names']=='1');
        $fmt = isset($this->params['fmt']) ? $this->params['fmt'] : 'nested';
        $max_depth = isset($this->params['max_depth']) ? (is_numeric($this->params['max_depth']) ? $this->params['max_depth'] : null) : null;
        $ItemVersion = new DBTableRowItemVersion(false, null);
        if ($ItemVersion->getRecordById($this->params['id'])) {
            switch ($fmt) {
                case 'pdf':
                    $Pdf = new ItemViewPDF();
                    $Pdf->dbtable = $ItemVersion;
                    $Pdf->buildDocument($this->params);
                    $Pdf->Output(make_filename_safe('ItemView_'.$ItemVersion->part_number.'_'.$ItemVersion->item_serial_number).'.pdf', 'D');
                    exit;
                case 'nested':
                    foreach (DBTableRowItemObject::getItemVersionFullNestedArray($ItemVersion->itemversion_id, $max_depth, 0) as $fieldname => $value) {
                        $this->view->{$fieldname} = $value;
                    }
                    break;
                case 'sheet':
                    $ReportData = new ReportDataItemListView(true, true, $ItemVersion->is_user_procedure, false, $ItemVersion->tv__typeobject_id, $ItemVersion->itemversion_id);
                    $records = $ReportData->getCSVRecordsAsArray($use_internal_names);
                    if (count($records)==1) {
                        $record = reset($records);
                        foreach ($record as $fieldname => $value) {
                            $this->view->{$fieldname} = $value;
                        }
                    }
            }
        }
    }

    /*
     * POST /items/versions?format=json
    *
    * Create a new version of an item.  This is based on the import verb NewVersion
    * as defined in ImportStrategyObjects::storeObjectPerImportRules().
    * The idea is that you need to give it enough information to locate the existing
    * part record (itemversion_id, itemobject_id, or item_serial_number).  It will attempt
    * retrieve the record using this and then alter the fields that we are specifying.
    * only a serial number is give, then we must specify the typeversion_id or typeobject_id.
    *
    * Input (at minimum):
    *   user_id login_id of the user
    *   itemversion_id or itemobject_id  need to specify one of these.
    *   effective_date (this needs to be filled out.)
    *   item_serial_number
    *   typeversion_id or typeobject_id
    *
    * Output (json):
    *   errormessages = []
    *   itemversion_id
    *   itemobject_id
    *  itemview_url
    *
    */

    public function postAction()
    {
        $record = $this->params;
        $errormsg = array();

        try {
            if (isset($record['id'])) {
                $errormsg[] = 'The parameters for this call cannot contain an ID parameter.';
            }

            $typeversion_id = null;
            if (isset($record['typeversion_id'])) {
                $typeversion_id = $record['typeversion_id'];
                unset($record['typeversion_id']);
                unset($record['typeobject_id']);
            } else if (isset($record['typeobject_id'])) {
                $TypeObject = new DBTableRowTypeObject();
                if ($TypeObject->getRecordById($record['typeobject_id'])) {
                    $typeversion_id = $TypeObject->cached_current_typeversion_id;
                }
                unset($record['typeobject_id']);
            }
            if (!$typeversion_id) {
                $errormsg[] = 'You must enter either a valid typeversion_id or typeobject_id to create a new record.';
            }


            if (empty($errormsg)) {
                // contruct a list of columns we will import
                $EditRow = new DBTableRowItemVersion(false, null);
                // this needs to be e
                $EditRow->typeversion_id = $typeversion_id;
                $curr_field_to_columns = array();
                foreach ($EditRow->getSaveFieldNames() as $fieldname) {
                    if (isset($record[$fieldname])) {
                        $curr_field_to_columns[$fieldname] = $fieldname;
                    }
                }

                // establish defaults here

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
                    $user_records_by_id = DbSchema::getInstance()->getRecords('user_id', "SELECT * FROM user");
                    $user_records_by_loginid = array();
                    foreach ($user_records_by_id as $user_id => $user_record) {
                        $user_records_by_loginid[$user_record['login_id']] = $user_record;
                    }


                    if (is_numeric($record[$curr_field_to_columns['user_id']])) {
                        $user_id = $record[$curr_field_to_columns['user_id']];
                        // if not numeric, but is a valid login_id, then use that user instead
                    } else if (isset($user_records_by_loginid[$record[$curr_field_to_columns['user_id']]])) {
                        $user_id = $user_records_by_loginid[$record[$curr_field_to_columns['user_id']]]['user_id'];
                    } else {
                        $errormsg[] = 'User ID not found in user table: '.$record[$curr_field_to_columns['user_id']];
                    }
                    unset($curr_field_to_columns['user_id']);
                }

                if (empty($errormsg)) {
                    $outitemversion_id = null;
                    ImportStrategyObjects::storeObjectPerImportRules('NewVersion', $record, $curr_field_to_columns, $typeversion_id, $itemversion_id, $itemobject_id, $user_id, $effective_date, false, $errormsg, $outitemversion_id);

                    $this->view->itemversion_id = $outitemversion_id;
                    if (is_numeric($outitemversion_id)) {
                        $ItemVersion = new DBTableRowItemVersion();
                        if ($ItemVersion->getRecordById($outitemversion_id)) {
                            $this->view->itemview_url = $ItemVersion->absoluteUrl();
                            $this->view->itemobject_id = $ItemVersion->itemobject_id;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $errormsg[] = $e->getMessage();
        }

        $this->view->errormessages = $errormsg;
    }

    public function putAction()
    {
        $this->noOp();
    }

    public function deleteAction()
    {
        $this->noOp();
    }
}

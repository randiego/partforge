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

class Types_ObjectsController extends RestControllerActionAbstract
{

    /*
     * GET /types/objects
     *
     * Return a list of current versions of all objects, subject to query variables
     */
    public function indexAction()
    {
        $records = DbSchema::getInstance()->getRecords('', "SELECT DISTINCT typeobject.typeobject_id FROM typeobject
				LEFT JOIN typeversion ON typeversion.typeversion_id=typeobject.cached_current_typeversion_id
				ORDER BY typeversion.effective_date");
        $this->view->typeobject_ids = extract_column($records, 'typeobject_id');
    }

   /*
    * GET /types/objects/1
    *
    * Return the specified typeversion for current version of the itemobject_id.  This can return both PDF and json dictionary versions.
    *
    * Input:
    *   id = the typeobject_id of the definition being retrieved. (required)
    *   fmt=nested (default) shows a JSON array in fully nested dictionary format
    *   fmt=pdf generates and outputs a pdf file
    *   show_linked_procedures=1 (true): for pdfs this will append the definition pages from associated procedures too.  For nested (json) output,
    *    we get an array 'linked_procedures' that contains one level of each linked procedure.
    *   max_depth=n is the maximum recursion level into component definitions for building return dictionary. default is 0 which stops after first level
    *
    */

    public function getAction()
    {
        $fmt = isset($this->params['fmt']) ? $this->params['fmt'] : 'nested';
        $max_depth = isset($this->params['max_depth']) ? (is_numeric($this->params['max_depth']) ? $this->params['max_depth'] : 0) : 0;
        $show_linked_procedures = isset($this->params['show_linked_procedures']) ? $this->params['show_linked_procedures'] : 0;
        $TypeVersion = new DBTableRowTypeVersion(false, null);
        if ($TypeVersion->getCurrentRecordByObjectId($this->params['id'])) {
            switch ($fmt) {
                case 'pdf':
                    $Pdf = new ItemDefinitionViewPDF();
                    $Pdf->buildTypeDocument($TypeVersion->typeversion_id, $this->params);
                    $Pdf->Output(make_filename_safe('Definition_'.DBTableRowTypeVersion::formatPartNumberDescription($TypeVersion->type_part_number, $TypeVersion->type_description)).'.pdf', 'D');
                    exit;
                case 'nested':
                    $errors = array();
                    foreach (DBTableRowTypeObject::getTypeObjectFullNestedArray($TypeVersion->typeobject_id, $errors, null, $max_depth, 0) as $fieldname => $value) {
                        $this->view->{$fieldname} = $value;
                    }
                    if (count($errors)>0) {
                        $this->view->errormessages = $errors;
                    }
                    if ($show_linked_procedures) {
                        $this->view->linked_procedures = DBTableRowTypeObject::getTypeObjectArrayOfLinkedProcedures($TypeVersion->typeobject_id, null);
                    }
                    break;
            }
        }
    }

    /*
     * POST /types/objects?format=json
     *
     * Create a new item object and version from posted data.
     *
     * Input (at minimum):
     *  user_id
     *  typeversion_id
     *  effective_date
     *  item_serial_number
     *
     * Output (json):
     *  errormessages = []
     *  itemversion_id
     *  itemobject_id
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
                        // if not numeric, but is a valid login_id, then user that user instead
                    } else if (isset($user_records_by_loginid[$record[$curr_field_to_columns['user_id']]])) {
                        $user_id = $user_records_by_loginid[$record[$curr_field_to_columns['user_id']]]['user_id'];
                    } else {
                        $errormsg[] = 'User ID not found in user table: '.$record[$curr_field_to_columns['user_id']];
                    }
                    unset($curr_field_to_columns['user_id']);
                }

                $outitemversion_id = null;
                ImportStrategyObjects::storeObjectPerImportRules('NewObject', $record, $curr_field_to_columns, $typeversion_id, $itemversion_id, $itemobject_id, $user_id, $effective_date, false, $errormsg, $outitemversion_id);

                $this->view->itemversion_id = $outitemversion_id;
            }



            /*
            if (empty($errormsg)) {
                $EditRow = new DBTableRowItemVersion(false,null);
                // note that setting typeversion_id will automatically generate the correct type information
                $EditRow->typeversion_id = $this->params['typeversion_id'];
                $EditRow->assignFromAjaxPost($this->params);
                $EditRow->validateFields($EditRow->getSaveFieldNames(),$errormsg);
            }

            if (count($errormsg)==0) {
                $EditRow->save($EditRow->getSaveFieldNames());
                $this->view->itemversion_id = $EditRow->itemversion_id;
                $this->view->itemobject_id = $EditRow->itemobject_id;
            }
            */
        } catch (Exception $e) {
            $errormsg[] = $e->getMessage();
        }

        $this->view->errormessages = $errormsg;
    }

    /*
     * PUT /types/objects/:id?format=json
     *
     * Create a new version of an item specified by the itemobject_id = id
     *
     * Input (at minimum):
     *  user_id
     *  itemobject_id
     *  effective_date
     *  item_serial_number
     *
     * Output (json):
     *  errormessages = []
     *  itemversion_id
     *
     */
    public function putAction()
    {
        $this->noOp();
    }

    public function deleteAction()
    {
        $this->noOp();
    }
}

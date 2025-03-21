<?php
/**
 *
 * PartForge Enterprise Groupware for recording parts and assemblies by serial number and version along with associated test data and comments.
 *
 * Copyright (C) 2013-2025 Randall C. Black <randy@blacksdesign.com>
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

class Types_VersionsController extends RestControllerActionAbstract
{

    /*
     * GET /types/versions/
     * GET /types/versions?typeobject_id=123
     * GET /types/versions?part_number=MYPARTN%
     *
     * Return a list of all versions of all types, subject to query variables
     */
    public function indexAction()
    {
        $and_where = '';
        if (isset($this->params['typeobject_id']) && is_numeric($this->params['typeobject_id'])) {
            $and_where .= " AND (typeversion.typeobject_id='".addslashes($this->params['typeobject_id'])."')";
        }
        if (isset($this->params['part_number'])) {
            $and_where .= " and (partnumbercache.part_number like '".addslashes($this->params['part_number'])."')";
        }

        $records = DbSchema::getInstance()->getRecords('', "SELECT DISTINCT typeversion.typeversion_id FROM typeversion
                LEFT JOIN partnumbercache on partnumbercache.typeversion_id = typeversion.typeversion_id
				WHERE 1=1 {$and_where}
				ORDER BY typeversion.effective_date");
        $this->view->typeversion_ids = extract_column($records, 'typeversion_id');
    }


   /*
    * GET /types/versions/12
    *
    * Return the specified version of the typeversion.  This can return both PDF and json dictionary versions.
    *
    * Unlike /types/objects/ api call, this returns the
    * version of the component objects that were active at the time of the effective date of this version.
    * main parameter is typeobject_id
    *
    * Input:
    *   id = the typeversion_id of the definition being retrieved. (required)
    *   fmt=nested (default) shows a JSON array in fully nested dictionary format
    *   fmt=pdf generates and outputs a pdf file
    *   show_linked_procedures=1 (true): for pdfs this will append the definition pages from associated procedures too.  For nested (json) output,
    *    we get an array 'linked_procedures' that contains one level of each linked procedure.
    *   max_depth=n is the maximum recursion level into component definitions for building return dictionary.  default is 0 which stops after first level.
    *
    */
    public function getAction()
    {
        $fmt = isset($this->params['fmt']) ? $this->params['fmt'] : 'nested';
        $max_depth = isset($this->params['max_depth']) ? (is_numeric($this->params['max_depth']) ? $this->params['max_depth'] : 0) : 0;
        $show_linked_procedures = isset($this->params['show_linked_procedures']) ? $this->params['show_linked_procedures'] : 0;
        $TypeVersion = new DBTableRowTypeVersion(false, null);
        if ($TypeVersion->getRecordById($this->params['id'])) {
            switch ($fmt) {
                case 'pdf':
                    $Pdf = new ItemDefinitionViewPDF();
                    $Pdf->buildTypeDocument($this->params['id'], $this->params);
                    $Pdf->Output(make_filename_safe('Definition_'.DBTableRowTypeVersion::formatPartNumberDescription($TypeVersion->type_part_number, $TypeVersion->type_description)).'.pdf', 'D');
                    exit;
                case 'nested':
                    $errors = array();
                    foreach (DBTableRowTypeObject::getTypeVersionFullNestedArray($TypeVersion->typeversion_id, $errors, $max_depth, 0) as $fieldname => $value) {
                        $this->view->{$fieldname} = $value;
                    }
                    if (count($errors)>0) {
                        $this->view->errormessages = $errors;
                    }
                    if ($show_linked_procedures) {
                        $this->view->linked_procedures = DBTableRowTypeObject::getTypeVersionArrayOfLinkedProcedures($TypeVersion->typeversion_id);
                    }
                    break;
            }
        }
    }

    public function postAction()
    {
        $this->noOp();
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

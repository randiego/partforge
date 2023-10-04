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

class DBTableRowDashboardTable extends DBTableRow {

    public $_data_items_names = array('chkShowProcMatrix','chkShowAllFields', 'lastChangedDays', 'color', 'sort_key', 'rowLimit');

    public function __construct($ignore_joins = false, $parent_index = null)
    {
        parent::__construct('dashboardtable', $ignore_joins, $parent_index);
    }

    public function initFromTypeObjectId($typeobject_id)
    {
        // init
        $this->chkShowProcMatrix = "1";
        $this->chkShowAllFields = "allnew";
        $this->lastChangedDays = "14";
        $this->color = "AAAAAA";
        $this->rowLimit = "5";
        $this->user_id = $_SESSION['account']->user_id;
        $this->typeobject_id = $typeobject_id;
        $report_params = $this->getArray();
        $report_params['view_category'] = $this->typeobject_id;
        $ReportData = new ReportDataItemListView(false, false, false, false, $report_params, true);
        $this->include_fields = implode(',', array_keys($ReportData->fields));
    }

    protected function onAfterGetRecord(&$record_vars)
    {
        // extract the property values from the just processed record.
        $item_data = json_decode($record_vars['data_items'], true);
        foreach ($this->_data_items_names as $field_name) {
            if (isset($item_data[$field_name])) {
                $record_vars[$field_name] = $item_data[$field_name];
            }
        }
        return true;
    }

    public function save($fieldnames = array(), $handle_err_dups_too = true)
    {
        $item_data = array();
        foreach ($this->_data_items_names as $field_name) {
            $item_data[$field_name] = $this->{$field_name};
            //$item_data[$field_name] = self::varToStandardForm(isset($this->_fields[$field_name]) ? $this->_fields[$field_name] : null, $this->getFieldType($dictionary_field_name));
        }
        $this->data_items = !empty($item_data) ?  json_encode($item_data) : '';
        parent::save($fieldnames, $handle_err_dups_too);
    }

    /**
     * Sort input array such that any keys it shares with $this->include_fields are presented in the
     * same order as in $this->include_fields.
     *
     * @param array $in_arr
     *
     * @return array
     */
    public function sortByIncludedFields($in_arr)
    {
        $sortguide = explode(',', $this->include_fields);
        $targkeys = array_keys($in_arr);
        $targvals = array_values($in_arr);
        do {
            $didswap = false;
            for ($ig=0; $ig < count($sortguide) - 1; $ig++) {
                $makeFirst = $sortguide[$ig];
                $makeSecond = $sortguide[$ig + 1];
                $idxSecond = -1;
                foreach ($targkeys as $i_targ => $targkey) {
                    if ($targkey==$makeSecond) {
                        $idxSecond = $i_targ;
                    } elseif (($targkey==$makeFirst) && ($idxSecond > -1)) {
                        // We're here because the target is out of order. So we swap...
                        $holdk = $targkeys[$i_targ];
                        $holdv = $targvals[$i_targ];
                        $targkeys[$i_targ] = $targkeys[$idxSecond];
                        $targvals[$i_targ] = $targvals[$idxSecond];
                        $targkeys[$idxSecond] = $holdk;
                        $targvals[$idxSecond] = $holdv;
                        $didswap = true;
                    }
                }
            }
        } while ($didswap);
        return array_combine($targkeys, $targvals);
    }

}

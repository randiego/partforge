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

class DBTableRowDashboardColumnNote extends DBTableRow {

    public function __construct($ignore_joins = false, $parent_index = null)
    {
        parent::__construct('dashboardcolumnnote', $ignore_joins, $parent_index);
    }

    public function getRecordByTableAndItemObject($dashboardtable_id, $itemobject_id)
    {
        $gotit = $this->getRecordWhere("dashboardtable_id='".addslashes($dashboardtable_id)."' and itemobject_id='".addslashes($itemobject_id)."'");
        $this->dashboardtable_id = addslashes($dashboardtable_id);
        $this->itemobject_id = addslashes($itemobject_id);
        return $gotit;
    }

    public static function storeColumnNote($dashboardTableId, $itemobjectId, $noteValue)
    {
        $Note = new self();
        if ($Note->getRecordByTableAndItemObject($dashboardTableId, $itemobjectId)) {
            if ($noteValue==="") {
                $Note->delete();
            } else {
                $Note->record_modified = time_to_mysqldatetime(script_time());
                $Note->value = $noteValue;
                $Note->save();
            }
        } else {
            if ($noteValue!=="") {
                // This  is a little redundant but we also want to store the typeobject_id in case the dashboardtable gets deleted.
                $DashBoardTable = new DBTableRowDashboardTable();
                if ($DashBoardTable->getRecordById($dashboardTableId)) {
                    $Note->typeobject_id = $DashBoardTable->typeobject_id;
                }
                $Note->dashboardtable_id = $dashboardTableId;
                $Note->user_id = $_SESSION['account']->user_id;
                $Note->itemobject_id = $itemobjectId;
                $Note->value = $noteValue;
                $Note->record_modified = time_to_mysqldatetime(script_time());
                $Note->save();
            }
        }
        return $Note;
    }

    /**
     * get a list of dashboardtable_ids and supplementary information for locating column notes from other tables.
     * We don't want to include the current table, so that's what the $excludetable_id param is about.
     *
     * The returned list includes:
     *   any note created by us (dashboardcolumnnote.user_id == $_SESSION['account']->user_id) regardless of deleted or not.
     *   any note created by someone else if that note is public (dashboard.is_public == 1)
     *
     * @param [type] $typeobject_id
     * @param integer $excludetable_id
     *
     * @return array of dashboard tables with notes of the same type. The array key is the dashboardtable_id.
     */
    public static function getDashboardTableIdsOfTypeObjectId($typeobject_id, $excludetable_id = -1)
    {
        $our_user_id = $_SESSION['account']->user_id;
        $records = DbSchema::getInstance()->getRecords('', "SELECT GROUP_CONCAT(list_of_table_ids) pub_table_ids FROM dashboard WHERE is_public = 1");
        $record = reset($records);
        $pub_table_ids_array = explode(',', $record['pub_table_ids']);
        $records = DbSchema::getInstance()->getRecords('dashboardtable_id', "SELECT DISTINCT dashboardcolumnnote.dashboardtable_id,
                    dashboardcolumnnote.user_id, dashboardtable.title, user.first_name, user.last_name, dashboardtable.user_id as table_user_id,
                    typeversion.type_description
                    FROM dashboardcolumnnote
                    LEFT JOIN dashboardtable ON dashboardtable.dashboardtable_id = dashboardcolumnnote.dashboardtable_id
                    LEFT JOIN user ON user.user_id = dashboardcolumnnote.user_id
                    LEFT JOIN typeobject ON typeobject.typeobject_id = dashboardcolumnnote.typeobject_id
                    LEFT JOIN typeversion ON typeversion.typeversion_id = typeobject.cached_current_typeversion_id
                    WHERE (dashboardcolumnnote.typeobject_id='".$typeobject_id."') and (dashboardcolumnnote.dashboardtable_id != '".$excludetable_id."')
                    and ((dashboardcolumnnote.user_id = '{$our_user_id}') or ('1' = '1'))
                    ORDER BY dashboardcolumnnote.dashboardtable_id");
        $out = array();
        foreach ($records as $dashboardtable_id => $record) {
            if (($record['user_id'] == $our_user_id) || (($record['user_id'] != $our_user_id) && in_array($dashboardtable_id, $pub_table_ids_array))) {
                $person = $record['user_id'] == $_SESSION['account']->user_id ? 'My Notes' : DBTableRowUser::concatNames($record).' Notes';
                $table = is_numeric($record['table_user_id']) ? self::shortTableName($record) : 'Deleted Dashboard Table '.$dashboardtable_id;
                $out[$dashboardtable_id] = $record;
                $out[$dashboardtable_id]['display'] = $person.' from Table '.$table;
            }
        }
        return $out;
    }

    public static function shortTableName($record)
    {
        return trunc_text(isset($record['title']) && $record['title'] ? $record['title'] : $record['type_description'], 50);
    }

/**
 * This shows my notes that were in a previously deleted dashboard table.
 *
 * @param boolean $get_orphans if true, only return ophans, if false only return NOT orphans.
 *
 * @return array
 */
    public static function getMyOrphanColumnNotesGroupedByType()
    {
        $our_user_id = $_SESSION['account']->user_id;
        $and_where = " and dashboardtable.dashboardtable_id IS NULL";
        $records = DbSchema::getInstance()->getRecords('', "SELECT dashboardcolumnnote.dashboardtable_id,
                    dashboardcolumnnote.user_id, dashboardcolumnnote.typeobject_id, user.first_name, user.last_name,
                    typeversion.type_description,
                    itemversion.item_serial_number,
                    itemversion.itemobject_id,
                    dashboardcolumnnote.value,
                    dashboardtable.title
                    FROM dashboardcolumnnote
                    LEFT JOIN dashboardtable ON dashboardtable.dashboardtable_id = dashboardcolumnnote.dashboardtable_id
                    LEFT JOIN user ON user.user_id = dashboardcolumnnote.user_id
                    LEFT JOIN typeobject ON typeobject.typeobject_id = dashboardcolumnnote.typeobject_id
                    LEFT JOIN typeversion ON typeversion.typeversion_id = typeobject.cached_current_typeversion_id
                    LEFT JOIN itemobject ON itemobject.itemobject_id = dashboardcolumnnote.itemobject_id
                    LEFT JOIN itemversion ON itemversion.itemversion_id = itemobject.cached_current_itemversion_id
                    WHERE ((dashboardcolumnnote.user_id = '{$our_user_id}') {$and_where})
                    ORDER BY dashboardcolumnnote.dashboardtable_id, itemversion.item_serial_number");
        $out = array();
        foreach ($records as $record) {
            $dashboardtable_id = $record['dashboardtable_id'];
            if (!isset($out[$dashboardtable_id])) {
                $out[$dashboardtable_id] = array('first_name' => $record['first_name'],
                'last_name' => $record['last_name'], 'type_description' => $record['type_description'],
                'typeobject_id' => $record['typeobject_id'], 'notes' => array());
                if (isset($record['title'])) {
                    $out[$dashboardtable_id]['title'] = $record['title'];
                }
            }
            $out[$dashboardtable_id]['notes'][] = array('itemobject_id' => $record['itemobject_id'], 'item_serial_number' => $record['item_serial_number'], 'value' => $record['value']);
        }
        return $out;
    }

    public static function deleteOrphans($dashboardtable_id)
    {
        $orph_recs = self::getMyOrphanColumnNotesGroupedByType();
        if (isset($orph_recs[$dashboardtable_id])) {
            DbSchema::getInstance()->mysqlQuery("DELETE FROM dashboardcolumnnote WHERE dashboardtable_id = '{$dashboardtable_id}'");
        }
    }

    public static function mergeOrphanColumnNotes($orph_dashboardtable_id, $targ_dashboardtable_id)
    {
        $orph_recs = self::getMyOrphanColumnNotesGroupedByType();
        foreach ($orph_recs[$orph_dashboardtable_id]['notes'] as $note) {
            $ColumnNote = new self();
            if ($ColumnNote->getRecordByTableAndItemObject($targ_dashboardtable_id, $note['itemobject_id'])) {
                $ColumnNote->value .= "\r\n\r\n".$note['value'];
            } else {
                $ColumnNote->user_id = $_SESSION['account']->user_id;
                $ColumnNote->typeobject_id = $orph_recs[$orph_dashboardtable_id]['typeobject_id'];
                $ColumnNote->value .= $note['value'];
            }
            $ColumnNote->record_modified = time_to_mysqldatetime(script_time());
            $ColumnNote->save();
        }
        self::deleteOrphans($orph_dashboardtable_id);
    }

    public static function fetchOrphanEditBlockHtml($navigator, $dashboard)
    {
        $html = '';
        $orph_block = self::getMyOrphanColumnNotesGroupedByType();
        if (count($orph_block) > 0) {
            //
            $non_orph_block = DBTableRowDashboardTable::getMyTablesByType();
            $html .= '<div class="edittablewrapper"><div class="orphan_notes_notice">';
            $html .= 'Notes from Dashboard Tables you deleted:';
            foreach ($orph_block as $dashboardtable_id => $orph_notes) {
                $html .= '<div class="orphnotes_div">';
                $html .= '<h2>'.'from Table ('.$dashboardtable_id.') of Type '.$orph_notes['type_description'].'</h2>';
                $delete_orphan_url = $navigator->getCurrentHandlerUrl('btnDeleteOrphan', null, null, array('dashboardtable_id' => $dashboardtable_id));
                $html .= '<div class="ophan_ctl_div">';
                $html .= '<span>'.linkify($delete_orphan_url, 'Delete', "Permanently delete these orphaned notes.", 'bd-linkbtn dashsernumeditbtn', 'return confirm(\'Permanently delete '.count($orph_notes['notes']).' orphaned note(s)?\');', '', '').'</span>';

                $targettables = array();
                foreach ($non_orph_block as $non_orph_dashboardtable_id => $non_orph_notes) {
                    if ($orph_notes['typeobject_id'] == $non_orph_notes['typeobject_id']) {
                        $dashboard_text = in_array($non_orph_dashboardtable_id, explode(',', $dashboard->list_of_table_ids)) ? ' on this dashboard' : ' on another dashboard';
                        $targettables[$non_orph_dashboardtable_id] = ' into My Notes in existing table "'.self::shortTableName($non_orph_notes).'"'.$dashboard_text;
                    }
                }
                if (count($targettables)>0) {
                    $html .= 'or '.format_select_tag($targettables, "dashboardtable_id[$dashboardtable_id]", array("dashboardtable_id" => ''), "document.theform.btnOnChange.value='import_column_notes:".$dashboardtable_id."';document.theform.submit();return false;", false, 'Import...', '', 'bd-button-colors').' ';
                } else {
                    $html .= 'or if you create a Dashboard Table of type '.$orph_notes['type_description'].', you will have the option to import these notes.';
                }
                $html .= '</div>';
                $html .= '<ul>';
                foreach ($orph_notes['notes'] as $orph_note ) {
                    $html .= '<li><span class="sernumcls">'.$orph_note['item_serial_number'].':</span> '.text_to_unwrappedhtml($orph_note['value']).'</li>';
                }
                $html .= '</ul></div>';
            }
            $html .= '</div></div>';
        }
        return $html;
    }

}

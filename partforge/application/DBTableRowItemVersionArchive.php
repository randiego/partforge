<?php
/**
 *
 * PartForge Enterprise Groupware for recording parts and assemblies by serial number and version along with associated test data and comments.
 *
 * Copyright (C) 2013-2022 Randall C. Black <randy@blacksdesign.com>
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

class DBTableRowItemVersionArchive extends DBTableRow {

    public function __construct()
    {
        parent::__construct('itemversionarchive');
        $this->record_created = time_to_mysqldatetime(script_time());
    }

    /**
     * This is used for populating the field changes_html $count at a time to avoid overdoing it.
     *
     * @param integer $count how many itemversion_ids to work on. If null, it does them all.
     *
     * @return void
     */
    public static function buildSomeArchiveChangesHtml($count = null)
    {
        $limit = is_null($count) ? '' : " LIMIT {$count}";
        $virgin_records = DbSchema::getInstance()->getRecords('itemversion_id', "SELECT DISTINCT itemversion_id FROM itemversionarchive WHERE changes_html IS NULL{$limit}");
        foreach ($virgin_records as $virgin_record) {
            DBTableRowItemVersionArchive::buildArchiveChangesHtml($virgin_record['itemversion_id']);
        }
    }

    public static function buildArchiveChangesHtml($itemversion_id, $force = false)
    {
        $ItemVersionCurr = new DBTableRowItemVersion();
        $ItemVersionCurr->getRecordById($itemversion_id);

        // this is need to create descriptions of what changed
        $arc_records = DbSchema::getInstance()->getRecords('itemversionarchive_id', "SELECT * FROM itemversionarchive WHERE itemversion_id='{$itemversion_id}' ORDER BY record_created desc");
        $out = array();
        $first_entry = null;
        foreach ($arc_records as $arc_record) {
            $arc_fields = json_decode($arc_record['item_data'], true);

            // assign the fields from the archive to the $ItemVersionArc object
            $ItemVersionArc = new DBTableRowItemVersion();
            $ItemVersionArc->typeversion_id = $arc_fields['typeversion_id'];
            // just in case the typeversion is no longer valid, lets just use the current one
            if (!$ItemVersionArc->hasAValidTypeVersionId()) {
                $ItemVersionArc->typeversion_id = $ItemVersionCurr->typeversion_id;
            }
            $fieldtypes = $ItemVersionArc->getFieldTypes();
            $fieldnames_to_assign = array_diff(array_keys($arc_fields), array('typeversion_id'));
            foreach ($fieldnames_to_assign as $fieldname) {
                if (isset($fieldtypes[$fieldname])) {
                    if ($fieldtypes[$fieldname]['type']=='component') {
                        if (isset($arc_fields[$fieldname]['itemobject_id'])) {
                            $ItemVersionArc->{$fieldname} = $arc_fields[$fieldname]['itemobject_id'];
                        } else if (is_numeric($arc_fields[$fieldname])) {
                            $ItemVersionArc->{$fieldname} = $arc_fields[$fieldname];
                        }
                    } else if ($fieldtypes[$fieldname]['type']=='attachment') {
                        $ItemVersionArc->{$fieldname} = $arc_fields[$fieldname];
                    } else {
                        $ItemVersionArc->{$fieldname} = $arc_fields[$fieldname];
                    }
                } else {
                    $ItemVersionArc->{$fieldname} = $arc_fields[$fieldname];
                }
            }

            if (!isset($arc_record['changes_html']) || $force) {
                $event_description_array = array();
                $description_html = EventStream::itemversionMarkupToHtmlTags($ItemVersionCurr->itemDifferencesFrom($ItemVersionArc, true), null, 'ET_CHG', $event_description_array, false);

                $desc_arr = array();
                if (trim($description_html)!='') {
                    $desc_arr[] = $description_html;
                }
                if (strtotime($ItemVersionCurr->effective_date)!=strtotime($ItemVersionArc->effective_date)) {
                    $desc_arr[] = 'Effective Date changed from '.$ItemVersionArc->formatPrintField('effective_date').' to '.$ItemVersionCurr->formatPrintField('effective_date');
                }
                DbSchema::getInstance()->mysqlQuery("UPDATE itemversionarchive SET changes_html='".addslashes(implode(', ', $desc_arr))."' WHERE itemversionarchive_id='".$arc_record['itemversionarchive_id']."'");
            }

            $ItemVersionCurr = $ItemVersionArc;
        }
    }
}

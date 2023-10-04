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

class DBTableRowDashboard extends DBTableRow {

    public function __construct($ignore_joins = false, $parent_index = null)
    {
        parent::__construct('dashboard', $ignore_joins, $parent_index);
    }

    /**
     * This gets a dashboard_id for the user by either returning their one of their existing dashboard_ids if one exists
     * or making a new empty one and returning it's id
     *
     * @param integer $user_id
     *
     * @return void
     */
    public static function getAValidDashboardIdForUser($user_id)
    {
        $records = DbSchema::getInstance()->getRecords('', "select * from dashboard where user_id='".$user_id."' order by record_created desc");
        if (count($records)>0) {
            $record = reset($records);
            $Dashboard = new DBTableRowDashboard();
            $Dashboard->getRecordById($record['dashboard_id']);
        } else {
            $Dashboard = self::makeNewDashboard($user_id);
        }
        return $Dashboard->dashboard_id;
    }

    public static function makeNewDashboard($user_id)
    {
        $records = DbSchema::getInstance()->getRecords('', "select * from dashboard where user_id='".$user_id."' order by record_created desc");
        $users_first_dashboard = count($records) == 0;
        $Dashboard = new DBTableRowDashboard();
        $Dashboard->user_id = $user_id;
        $Dashboard->list_of_tables = "";
        if (!$users_first_dashboard) {
            $Dashboard->title = "My Dashboard ".(count($records)+1);
        } else {
            $tableids = array();
            foreach ($_SESSION['account']->getNumericFavorites('pref_part_view_category_fav') as $typeobject_id) {
                $tableids[] = 'new|'.$typeobject_id;
            }
            $Dashboard->title = "Favorites Dashboard";
            $Dashboard->rearrangeTables(implode(',', $tableids));
        }

        // $Dashboard->list_of_tables = "";
        $Dashboard->save();
        return $Dashboard;
    }

    /**
     * Takes a string of dashboardtable_ids and typeobject_ids and assigns them to the field list_of_table_ids.
     * It also creates new tables if there are duplicates or table ids = "new".
     *
     * @param string $tableids of the form "tbid1|to1,tbid2|to2,..."
     *
     * @return void
     */
    public function rearrangeTables($tableids)
    {
        $table_ids = array();
        if ($tableids !== "") {
            foreach (explode(',', $tableids) as $pair) {
                list($table_id, $typeobject_id) = explode('|', $pair);
                if (in_array($table_id, $table_ids)) {
                    // we should create a duplicate table like the existing one
                    $DashboardTable = new DBTableRowDashboardTable();
                    if ($DashboardTable->getRecordById($table_id)) {
                        $DashboardTable->dashboardtable_id = "new"; // will force the creation of a new table
                        $DashboardTable->typeobject_id = $typeobject_id; // this should not be necessary since we only duplicate the same type
                        $DashboardTable->save();
                        $table_ids[] = $DashboardTable->dashboardtable_id;
                    }
                } elseif ($table_id == 'new') {
                    $DashboardTable = new DBTableRowDashboardTable();
                    $DashboardTable->initFromTypeObjectId($typeobject_id);
                    $DashboardTable->save();
                    $table_ids[] = $DashboardTable->dashboardtable_id;
                } else {
                    $table_ids[] = $table_id;
                }
            }
        }
        $item_to_delete = array_diff(explode(',', $this->list_of_table_ids), $table_ids);
        foreach ($item_to_delete as $dashboardtable_id) {
            $DashboardTable = new DBTableRowDashboardTable();
            if ($DashboardTable->getRecordById($dashboardtable_id)) {
                $DashboardTable->delete();
            }
        }
        $this->list_of_table_ids = implode(',', $table_ids);
    }

    public static function getDashboardRecords()
    {
        $myuser_id = $_SESSION['account']->user_id;
        $records = DbSchema::getInstance()->getRecords('dashboard_id', "select *, IF(dashboard.user_id='{$myuser_id}',1,0) as is_mine from dashboard left join user on user.user_id=dashboard.user_id
                                            having ((dashboard.is_public=1) or is_mine=1) order by IF(dashboard.user_id='{$myuser_id}',1,0) desc, user.last_name asc, user.first_name asc, dashboard.title asc");
        return $records;
    }

    public static function indexOfAllDashboards()
    {
        $out = array();
        $prevwasmine = true;
        foreach (self::getDashboardRecords() as $dashboard_id => $record) {
            // if we are about to output a dashboard entry that is not one of mine, then add separator.
            if (!$record['is_mine'] && $prevwasmine) {
                $out['new'] = '-- Create New Dashboard --';
                $out[''] = '';
            }
            $username = $record['is_mine'] ? '' : DBTableRowUser::concatNames($record, true).': ';
            $out[$dashboard_id] = $username.$record['title'];
            $prevwasmine = $record['is_mine'];
        }
        return $out;
    }

    public function isThisMyOnlyDashboard()
    {
        $is_my_only = true;
        foreach (self::getDashboardRecords() as $dashboard_id => $record) {
            if ($record['is_mine'] && ($dashboard_id != $this->dashboard_id)) {
                $is_my_only = false;
                break;
            }
        }
        return $is_my_only;
    }

    /**
     * Save a complete duplcate copy of this dashboard.
     *
     * @return void
     */
    public function saveCopy()
    {
        $DashboardCopy = new self();
        $tables_to_duplicate = explode(',', $this->list_of_table_ids);
        $new_tables_written = array();

        if ($tables_to_duplicate !== "") {
            foreach ($tables_to_duplicate as $dashboardtable_id) {
                $DashboardTable = new DBTableRowDashboardTable();
                if ($DashboardTable->getRecordById($dashboardtable_id)) {
                    $DashboardTable->dashboardtable_id = 'new';
                    $DashboardTable->user_id = $_SESSION['account']->user_id;
                    $DashboardTable->save();
                    $new_tables_written[] = $DashboardTable->dashboardtable_id;
                }
            }
        }
        $DashboardCopy->list_of_table_ids = implode(',', $new_tables_written);
        $DashboardCopy->title = $this->title.' Copy';
        $DashboardCopy->user_id = $_SESSION['account']->user_id;
        $DashboardCopy->save();
        return $DashboardCopy->dashboard_id;
    }

    public function delete()
    {
        // Delete all the subtables and the dashboard itself.
        foreach (explode(',', $this->list_of_table_ids) as $dashboardtable_id) {
            $DashboardTable = new DBTableRowDashboardTable();
            if ($DashboardTable->getRecordById($dashboardtable_id)) {
                $DashboardTable->delete();
            }
        }
        parent::delete();
    }

    static function getAbsoluteUrl($dashboard_id)
    {
        $locator = '/dash/panel/'.$dashboard_id;
        return getAbsoluteBaseUrl().$locator;
    }

}

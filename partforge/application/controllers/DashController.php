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

class DashController extends DBControllerActionAbstract
{

    public function init()
    {
        parent::init();
        $this->navigator->addPropagatingParamName('dashboard_id');
    }

    public function indexAction()
    {
        $this->navigator->jumpToView('panel');
    }

    public function panelAction()
    {
        $Dashboard = new DBTableRowDashboard();
        if (isset($this->params['dashboard_id']) && $Dashboard->getRecordById($this->params['dashboard_id'])) {
            // continue
        } elseif ($Dashboard->getRecordById($_SESSION['account']->getPreference('current_dashboard_id'))) {
            $this->navigator->jumpToView(null, null, array('dashboard_id' => $Dashboard->dashboard_id));
        } else {
            $this->navigator->jumpToView(null, null, array('dashboard_id' => DBTableRowDashboard::getAValidDashboardIdForUser($_SESSION['account']->user_id)));
        }

        $_SESSION['account']->setPreference('current_dashboard_id', $Dashboard->dashboard_id);
        $this->view->readonly = ($Dashboard->user_id != $_SESSION['account']->user_id);
        if (isset($this->params['form'])) {
            switch (true) {
                case isset($this->params['btnOnChangeTableFilter']) && (is_numeric($this->params['btnOnChangeTableFilter'])):
                    $idx = $this->params['btnOnChangeTableFilter'];
                    $prefs = array('chkShowProcMatrix', 'chkShowAllFields', 'lastChangedDays', 'rowLimit');
                    $DashBoardTable = new DBTableRowDashboardTable();
                    if ($DashBoardTable->getRecordById($idx)) {
                        foreach ($prefs as $pref) {
                            $DashBoardTable->{$pref} = $this->params[$pref][$idx];
                        }
                        $DashBoardTable->save();
                    }
                    $this->navigator->jumpToView();

                case isset($this->params['btnChangeSortKey']):
                    if (is_array($this->params['btnChangeSortKey'])) {
                        foreach ($this->params['btnChangeSortKey'] as $idx => $nothing) {
                            $DashBoardTable = new DBTableRowDashboardTable();
                            if ($DashBoardTable->getRecordById($idx)) {
                                $DashBoardTable->sort_key = $this->params['sort_key'];
                                $DashBoardTable->save();
                            }
                        }
                    }
                    $this->navigator->jumpToView();
                case isset($this->params['btnEditTable']):
                    $DashBoardTable = new DBTableRowDashboardTable();
                    if ($DashBoardTable->getRecordById($this->params['dashboardTableId'])) {
                        $DashBoardTable->title = $this->params['tabletitle'];
                        $DashBoardTable->color = $this->params['tablecolor'];
                        $DashBoardTable->include_fields = $this->params['tablefields'];
                        $DashBoardTable->save();
                    }
                    $this->navigator->jumpToView();
                case isset($this->params['btnEditSerNums']):
                    $DashBoardTable = new DBTableRowDashboardTable();
                    if ($DashBoardTable->getRecordById($this->params['dashboardTableId'])) {
                        $DashBoardTable->include_only_itemobject_ids = $this->params['include_only_itemobject_ids'];
                        $DashBoardTable->autoadd_new_items = $this->params['autoadd_new_items'];
                        $DashBoardTable->save();
                    }
                    $this->navigator->jumpToView();
                case isset($this->params['btnEditDashboard']):
                    $Dashboard->title = $this->params['title'];
                    $Dashboard->is_public = $this->params['is_public'];
                    $Dashboard->rearrangeTables($this->params['tableids']);
                    $Dashboard->save();
                    $this->navigator->jumpToView();
                case isset($this->params['btnOnChange']) && $this->params['btnOnChange']=='changedashboard':
                    if ($this->params['selectdashboard_id']=='new') {
                        // $Dashboard = new DBTableRowDashboard();
                        $Dashboard = DBTableRowDashboard::makeNewDashboard($_SESSION['account']->user_id);
                        $this->navigator->jumpToView(null, null, array('dashboard_id' => $Dashboard->dashboard_id));
                    } else {
                        $this->navigator->jumpToView(null, null, array('dashboard_id' => $this->params['selectdashboard_id']));
                    }
                case isset($this->params['btnCopyDashboard']):
                    $new_dashboard_id = $Dashboard->saveCopy();
                    $this->navigator->jumpToView(null, null, array('dashboard_id' => $new_dashboard_id));
                case isset($this->params['btnDeleteDashboard']):
                    $Dashboard->delete();
                    $fallback_dashboard_id = DBTableRowDashboard::getAValidDashboardIdForUser($_SESSION['account']->user_id);
                    $this->navigator->jumpToView(null, null, array('dashboard_id' => $fallback_dashboard_id));
            }
        }

        // The searching on the dashboard is the part search. So we just do there.
        if (isset($this->params['search_string']) && $this->params['search_string']) {
            $this->navigator->jumpToView('itemlistview', 'struct', array('search_string' => $this->params['search_string'],'resetview' => 1));
        }

        $this->view->dashboard = $Dashboard;
        $this->view->queryvars = $this->params;
    }

}

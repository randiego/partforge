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

class ReportDataUser extends ReportDataWithCategory {

    private $can_edit = false;
    private $can_delete = false;

    public function __construct()
    {
        parent::__construct('user');
        $this->category_array = array();
        $this->show_button_column = false;

        $this->title = 'Manage Users';
        $this->fields['full_name']      = array('display'=>'Name',          'key_asc'=>'user.last_name,user.first_name', 'key_desc'=>'user.last_name desc,user.first_name', 'sort_key_fixed' => true);
        $this->fields['login_id']       = array('display'=>'Login ID',      'key_asc'=>'user.login_id', 'key_desc'=>'user.login_id desc');
        $this->fields['email']      = array('display'=>'Email',     'key_asc'=>'user.email', 'key_desc'=>'user.email desc');
        $this->fields['user_type']      = array('display'=>'User Type',     'key_asc'=>'user.user_type', 'key_desc'=>'user.user_type desc');
        $this->fields['last_visit']         = array('display'=>'Last Visit',        'key_asc'=>'user.last_visit', 'key_desc'=>'user.last_visit desc', 'start_key' => 'key_desc');
        $this->fields['login_count']        = array('display'=>'Login Count',       'key_asc'=>'user.login_count', 'key_desc'=>'user.login_count desc', 'start_key' => 'key_desc');
        $this->fields['cached_items_created_count']         = array('display'=>'Items Created',     'key_asc'=>'cached_items_created_count', 'key_desc'=>'cached_items_created_count desc', 'start_key' => 'key_desc');
        $this->search_box_label = 'first, last name, login id, locator';
        $this->can_edit = Zend_Registry::get('customAcl')->isAllowed($_SESSION['account']->getRole(), 'table:user', 'edit');
        $this->can_delete = Zend_Registry::get('customAcl')->isAllowed($_SESSION['account']->getRole(), 'table:user', 'delete');
    }

    public function getSearchAndWhere($search_string)
    {
        $and_where = '';
        if ($search_string) {
            $or_arr = array();
            $like_value = fetch_like_query($search_string, '', '%');
            $or_arr[] = "user.last_name {$like_value}";
            $or_arr[] = "user.first_name {$like_value}";
            $or_arr[] = "user.login_id {$like_value}";
            $or = implode(' or ', $or_arr);
            $and_where .= " and ($or)";
        }
        return $and_where;
    }

    public function getCategoryAndWhere(DBTableRowQuery $Query, $category, $search_string)
    {
        if (empty($search_string)) { // restrict to category only if we are not searching
            switch ($category) {
                case 'all':
                default:
                    return '';
            }
        } else {
            return '';
        }
    }

    public function get_records($queryvars, $searchstr, $limitstr)
    {
        $DBTableRowQuery = new DBTableRowQuery($this->dbtable);

        $DBTableRowQuery->setOrderByClause("ORDER BY {$this->get_sort_key($queryvars,true)}")
                        ->setLimitClause($limitstr)
                        ->addAndWhere($this->getSearchAndWhere($searchstr))
                        ->addAndWhere($this->getCategoryAndWhere($DBTableRowQuery, 'all', $searchstr))
                        ->addSelectFields('cached_items_created_count');

        return DbSchema::getInstance()->getRecords('', $DBTableRowQuery->getQuery());
    }

    public function get_records_count(&$queryvars, $searchstr)
    {
        $DBTableRowQuery = new DBTableRowQuery($this->dbtable);

        $DBTableRowQuery->addAndWhere( $this->getSearchAndWhere($searchstr))
                        ->setSelectFields('count(*)')
                        ->addAndWhere($this->getCategoryAndWhere($DBTableRowQuery, 'all', $searchstr));
        $records = DbSchema::getInstance()->getRecords('', $DBTableRowQuery->getQuery());
        $record = reset($records);
        return $record['count(*)'];
    }

    /*
     * (select count(*) from itemversion where (user_id=) or (proxy_user_id=) ) as
     */

    public function make_directory_detail($queryvars, &$record, &$buttons_arr, &$detail_out, UrlCallRegistry $navigator)
    {
        parent::make_directory_detail($queryvars, $record, $buttons_arr, $detail_out, $navigator);

        foreach (array_keys($this->display_fields($navigator, $queryvars)) as $fieldname) {
            $detail_out[$fieldname] = isset($record[$fieldname]) ? TextToHtml($record[$fieldname]) : null;
        }
        $detail_out['full_name'] = linkify( UrlCallRegistry::formatViewUrl('id/'.$record['user_id'], 'user'), TextToHtml(DBTableRowUser::concatNames($record, true)), 'View user details');
        $detail_out['cached_items_created_count'] = linkify( UrlCallRegistry::formatViewUrl('changelistview', 'struct', array('list_type' => 'USER'.strval($record['user_id']))), $record['cached_items_created_count'], 'Show all activity of this user');

        if (!$record['user_cryptpassword']) {
            $detail_out['login_id'] .= '<br /><span class="errorred">Password Not Set</span>';
        }
        if (!$record['user_enabled']) {
            $detail_out['login_id'] .= '<br /><span class="errorred">Login Not Enabled</span>';
        }
        if ($record['waiting_approval']) {
            $detail_out['login_id'] .= '<br /><span class="errorred">Waiting Approval</span>';
        }
    }

}

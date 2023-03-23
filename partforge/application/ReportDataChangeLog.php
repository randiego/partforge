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

class ReportDataChangeLog extends ReportDataWithCategory {

    private $_user_id;
    private $_activity_list_type = '';


    static public function activityTypeOptions()
    {
        if (Zend_Registry::get('customAcl')->isAllowed($_SESSION['account']->getRole(), 'user', 'managemylists')) {
            $out = array('WATCHING' => array('name' => 'My Watchlist'),'WATCHING7D' => array('name' => 'My Watchlist (past 7 days)'),
                'MINE' => array('name' => 'My Changes Only'), 'MINE7D' => array('name' => 'My Changes Only (past 7 days)'),
                'ALL' => array('name' => 'All Changes'), 'ALL7D' => array('name' => 'All Changes (past 7 days)'));
            $records = DBSchema::getInstance()->getRecords('user_id', "SELECT user_id, concat(last_name,', ',first_name) as full_name FROM user ORDER BY last_name, first_name");
            $users = array();
            foreach ($records as $user_id => $record)
            {
                $users['USER'.strval($user_id)] = array('name' => $record['full_name']);
            }
            $out = array_merge($out, $users);
            return $out;
        } else {
            return array('ALL' => array('name' => 'All Changes'), 'ALL7D' => array('name' => 'All Changes (past 7 days)'));
        }
    }

    static public function activityTypeDefault()
    {
        return Zend_Registry::get('customAcl')->isAllowed($_SESSION['account']->getRole(), 'user', 'managemylists') ? 'WATCHING7D' : 'ALL';
    }

    /**
     *
     * @param string $activity_list_type // ALL, WATCHING, MINE
     */
    public function __construct($activity_list_type = 'WATCHING')
    {
        parent::__construct('changelog');
        $this->_user_id = $_SESSION['account']->user_id;
        $this->_activity_list_type = $activity_list_type;

        $this->show_button_column = false;
        $this->default_sort_key = 'changed_on desc';

        $activity_list_type_options = self::activityTypeOptions();
        $this->title = 'Activity: '.$activity_list_type_options[$activity_list_type]['name'];

        $this->fields['changed_on']     = array('display'=>'Changed On',        'key_asc'=>'changed_on', 'key_desc'=>'changed_on desc', 'start_key' => 'key_desc');
        $this->fields['changed_by_name']    = array('display'=>'User',      'key_asc'=>'changed_by_name', 'key_desc'=>'changed_by_name desc');

        $this->fields['change_code_name']   = array('display'=>'Change Description',        'key_asc'=>'changecode.change_code_name', 'key_desc'=>'changecode.change_code_name desc');

        $this->fields['typecategory_name']  = array('display'=>'Type',      'key_asc'=>'changelog.desc_typecategory_id', 'key_desc'=>'changelog.desc_typecategory_id desc');
        $this->fields['part_number']    = array('display'=> 'Number',       'key_asc'=>'partnumbercache.part_number', 'key_desc'=>'partnumbercache.part_number desc');
        $this->fields['part_description']   = array('display'=> 'Name',     'key_asc'=>'partnumbercache.part_description', 'key_desc'=>'partnumbercache.part_description desc');

        $this->fields['procedure_date']     = array('display'=> 'Procedure',        'key_asc'=>'procedure_date', 'key_desc'=>'procedure_date desc', 'start_key' => 'key_desc');
        $this->fields['item_serial_number']     = array('display'=> 'Part',     'key_asc'=>'item_serial_number', 'key_desc'=>'item_serial_number desc');

        $this->search_box_label = 'number,SN,user,change,locator';

    }

    public function getSearchAndWhere($search_string, $DBTableRowQuery)
    {
        $and_where = '';
        if ($search_string) {
            $or_arr = array();
            $like_value = fetch_like_query($search_string, '%', '%');
            $start_like_value = fetch_like_query($search_string, '', '%');
            $or_arr[] = "partnumbercache.part_number {$start_like_value}";
            $or_arr[] = "changecode.change_code_name {$start_like_value}";
            $or_arr[] = "itemversion.item_serial_number {$like_value}";
            $or_arr[] = "TRIM(CONCAT(user.first_name,' ',user.last_name)) {$like_value}";
            $or = implode(' or ', $or_arr);
            $and_where .= " and ($or)";
        }
        return $and_where;
    }

    protected function addExtraJoins(&$DBTableRowQuery)
    {
        $DBTableRowQuery->addJoinClause("LEFT JOIN user on user.user_id = changelog.user_id")
                        ->addSelectFields("TRIM(CONCAT(user.first_name,' ',user.last_name)) as changed_by_name");

        $DBTableRowQuery->addJoinClause("LEFT JOIN partnumbercache ON partnumbercache.typeversion_id=changelog.desc_typeversion_id AND partnumbercache.partnumber_alias=IFNULL(changelog.desc_partnumber_alias,0)")
                        ->addSelectFields('partnumbercache.part_number, partnumbercache.part_description');

        $DBTableRowQuery->addSelectFields('IF(changelog.desc_typecategory_id=1,1,0) as is_user_procedure, IF(changelog.desc_typecategory_id=1,"Procedure","Part") as typecategory_name');

        $DBTableRowQuery->addJoinClause("LEFT JOIN itemversion on itemversion.itemversion_id = changelog.desc_itemversion_id")
                        ->addSelectFields('IF(changelog.desc_typecategory_id=1,null,itemversion.item_serial_number) as item_serial_number');

        $DBTableRowQuery->addJoinClause("LEFT JOIN itemobject on itemobject.itemobject_id = itemversion.itemobject_id")
                        ->addSelectFields('itemobject.cached_current_itemversion_id, IF(changelog.desc_typecategory_id=1, itemobject.cached_first_ver_date, null) as procedure_date');

        $DBTableRowQuery->addJoinClause("LEFT JOIN changecode on changecode.change_code = changelog.change_code")
                        ->addSelectFields('changecode.change_code_name');

        $timecutoff_mysql = time_to_mysqldatetime(script_time()-24*3600*7);
        if (preg_match('/^USER(.*)$/', $this->_activity_list_type, $match)) {
            $user_id = $match[1];
            $DBTableRowQuery->addAndWhere(" and (changelog.user_id='{$user_id}')");
        } else {
            switch ($this->_activity_list_type) {
                case 'WATCHING7D':
                    $DBTableRowQuery->addAndWhere(" and (changelog.changed_on>'{$timecutoff_mysql}')");
                case 'WATCHING':
                    $DBTableRowQuery->addAndWhere(" and Exists (select 1 from changesubscription where (
                            (
                                (changelog.trigger_itemobject_id IS NULL) and
                                (
                                    (changesubscription.typeobject_id = changelog.trigger_typeobject_id) or
                                    ((changesubscription.typeobject_id IS NULL) and (changesubscription.itemobject_id IS NULL))
                                )
                            ) or
                            (
                                (changelog.trigger_itemobject_id IS NULL) and
                                (changesubscription.typeobject_id = changelog.trigger_typeobject_id)
                            ) or
                            (
                                (changelog.trigger_itemobject_id IS NOT NULL) and
                                (
                                (changesubscription.itemobject_id = changelog.trigger_itemobject_id) or
                                ( (changesubscription.typeobject_id = changelog.trigger_typeobject_id) and (changesubscription.follow_items_too=1) )
                                )
                            )
                        ) and (changesubscription.user_id='{$this->_user_id}')
                        and (IFNULL(changesubscription.exclude_change_codes, '') not like CONCAT('%', changelog.change_code, '%')))");
                    break;
                case 'MINE7D':
                    $DBTableRowQuery->addAndWhere(" and (changelog.changed_on>'{$timecutoff_mysql}')");
                case 'MINE':
                    $DBTableRowQuery->addAndWhere(" and (changelog.user_id='{$this->_user_id}')");
                    break;
                case 'ALL7D':
                    $DBTableRowQuery->addAndWhere(" and (changelog.changed_on>'{$timecutoff_mysql}')");
                case 'ALL': // ALL, no filters
            }
        }

    }

    public function get_records($queryvars, $searchstr, $limitstr)
    {
        $DBTableRowQuery = new DBTableRowQuery($this->dbtable);
        $DBTableRowQuery->setOrderByClause("ORDER BY {$this->get_sort_key($queryvars,true)}")
                        ->setLimitClause($limitstr)
                        ->addAndWhere($this->getSearchAndWhere($searchstr, $DBTableRowQuery));
        $this->addExtraJoins($DBTableRowQuery);

        return DbSchema::getInstance()->getRecords('', $DBTableRowQuery->getQuery());
    }

    public function get_records_count(&$queryvars, $searchstr)
    {
        $DBTableRowQuery = new DBTableRowQuery($this->dbtable);
        $DBTableRowQuery->addAndWhere( $this->getSearchAndWhere($searchstr, $DBTableRowQuery) );
        $this->addExtraJoins($DBTableRowQuery);
        $DBTableRowQuery->setSelectFields('count(*)');
        $records = DbSchema::getInstance()->getRecords('', $DBTableRowQuery->getQuery());
        $record = reset($records);
        return $record['count(*)'];
    }

    public function make_directory_detail($queryvars, &$record, &$buttons_arr, &$detail_out, UrlCallRegistry $navigator)
    {
        parent::make_directory_detail($queryvars, $record, $buttons_arr, $detail_out, $navigator);

        switch ($record['locator_prefix']) {
            case 'iv' :
                $edit_url = UrlCallRegistry::formatViewUrl('iv/'.$record['desc_itemversion_id'], 'struct');
                break;
            case 'tv' :
                $edit_url = UrlCallRegistry::formatViewUrl('tv/'.$record['desc_typeversion_id'], 'struct');
                break;
        }

        foreach (array_keys($this->display_fields($navigator, $queryvars)) as $fieldname) {
            $detail_out[$fieldname] = TextToHtml($record[$fieldname]);
        }

        if (in_array($record['change_code'], array('AIR','AIP')) ) {
            $detail_out['change_code_name'] .= ' '.$record['desc_text'];
        }
        $detail_out['change_code_name'] = '<div style="display: block; width:400px; max-width:400px;">'.$detail_out['change_code_name'].'</div>';

        $detail_out['changed_on'] = empty($record['changed_on']) ? '' : date('M j, Y G:i', strtotime($record['changed_on']));


        $detail_out['procedure_date'] = (empty($record['procedure_date']) || !$record['is_user_procedure']) ? '' : linkify( $edit_url, date('M j, Y G:i', strtotime($record['procedure_date'])), 'View');
        $detail_out['item_serial_number'] = (empty($record['item_serial_number']) || $record['is_user_procedure']) ? '' : linkify( $edit_url, $record['item_serial_number'], 'View');

        if ($record['locator_prefix']=='tv') {
            $detail_out['part_number'] = linkify( $edit_url, $record['part_number'], 'View');
            $detail_out['part_description'] = linkify( $edit_url, $record['part_description'], 'View');
        }

    }

}

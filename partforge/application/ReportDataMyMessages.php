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

class ReportDataMyMessages extends ReportDataWithCategory {

    private $_user_id;
    private $_message_list_type = '';

    /**
     *
     * @param string $message_list_type // BOTH, RECEIVED, SENT
     */
    public function __construct($message_list_type = 'BOTH')
    {
        parent::__construct('sendmessage');
        $this->_user_id = $_SESSION['account']->user_id;
        $this->_message_list_type = $message_list_type;


        $this->show_button_column = false;
        $this->default_sort_key = 'sent_on desc';

        $this->fields['sent_on']        = array('display'=>'Sent On',   'key_asc'=>'sent_on', 'key_desc'=>'sent_on desc', 'start_key' => 'key_desc');
        $this->fields['from_user_name'] = array('display'=>'Sent From',      'key_asc'=>'from_user_name', 'key_desc'=>'from_user_name desc');
        $this->fields['to_user_names'] = array('display'=>'Sent To');
        $this->fields['object_name']    = array('display'=>'Subject',      'key_asc'=>'object_name', 'key_desc'=>'object_name desc');
        $this->fields['sent_text'] = array('display'=>'Message / Comment');

        $this->search_box_label = 'Name, subject, or message';

    }

    public static function viewSelectOptions()
    {
        return array('BOTH' => 'Sent and Received', 'RECEIVED' => 'Received', 'SENT' => 'Sent');
    }

    public function getSearchAndWhere($search_string, $DBTableRowQuery)
    {
        $and_where = '';
        if ($search_string) {
            $or_arr = array();
            $like_value = fetch_like_query($search_string, '%', '%');
            $start_like_value = fetch_like_query($search_string, '', '%');
            $or_arr[] = "TRIM(CONCAT(from_user.first_name,' ',from_user.last_name)) {$like_value}";
            $or_arr[] = "(SELECT GROUP_CONCAT(CONCAT(to_user.first_name,' ',to_user.last_name) ORDER BY to_user.last_name SEPARATOR ', ')
                            FROM messagerecipient
                            LEFT JOIN user to_user on to_user.user_id = messagerecipient.to_user_id
                            WHERE messagerecipient.sendmessage_id=sendmessage.sendmessage_id) {$like_value}";
            $or_arr[] = "IF(comment.comment_id IS NULL, sendmessage.message_text, comment.comment_text) {$like_value}";
            $or_arr[] = "object_name {$like_value}";
            $or = implode(' or ', $or_arr);
            $and_where .= " and ($or)";
        }
        return $and_where;
    }

    protected function addExtraJoins(&$DBTableRowQuery)
    {
        $DBTableRowQuery->addJoinClause("LEFT JOIN user from_user on from_user.user_id = sendmessage.from_user_id")
                        ->addSelectFields("TRIM(CONCAT(from_user.first_name,' ',from_user.last_name)) as from_user_name");
        $DBTableRowQuery->addSelectFields("(SELECT GROUP_CONCAT(CONCAT(to_user.first_name,' ',to_user.last_name) ORDER BY to_user.last_name SEPARATOR ', ')
                            FROM messagerecipient
                            LEFT JOIN user to_user on to_user.user_id = messagerecipient.to_user_id
                            WHERE messagerecipient.sendmessage_id=sendmessage.sendmessage_id) as to_user_names");
        $DBTableRowQuery->addJoinClause("LEFT JOIN comment on comment.comment_id = sendmessage.comment_id")
                        ->addSelectFields("IF(comment.comment_id IS NULL, sendmessage.message_text, comment.comment_text) as sent_text");

        $or_arr = array();
        if (in_array( $this->_message_list_type, array('BOTH', 'SENT'))) {
            $or_arr[] = "sendmessage.from_user_id='{$this->_user_id}'";
        }
        if (in_array( $this->_message_list_type, array('BOTH', 'RECEIVED'))) {
            $or_arr[] = "EXISTS(SELECT * FROM messagerecipient WHERE messagerecipient.sendmessage_id=sendmessage.sendmessage_id
                        AND messagerecipient.to_user_id='{$this->_user_id}')";
        }
        $or = implode(' or ', $or_arr);
        $DBTableRowQuery->addAndWhere(" and ($or)");
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

        foreach (array_keys($this->display_fields($navigator, $queryvars)) as $fieldname) {
            $detail_out[$fieldname] = TextToHtml($record[$fieldname]);
        }

        $detail_out['sent_on'] = empty($record['sent_on']) ? '' : date('M j, Y G:i', strtotime($record['sent_on']));
        $detail_out['to_user_names'] = '<div class="excerpt" style="display: block; max-width:150px;">'.$record['to_user_names'].'</div>';

        $view_url = Zend_Controller_Front::getInstance()->getRequest()->getBaseUrl().$record['url'];
        if ($record['comment_id']==-1) {
            $type = 'Link to: ';
        } else {
            $type = 'Comment on: ';
            $view_url .= '?highlight=comment,'.$record['comment_id'];
        }
        $detail_out['object_name'] = '<div class="excerpt" style="display: block; max-width:400px;">'.$type.linkify($view_url, $record['object_name'], 'View').'</div>';


        list($comment_html,$dummy) = EventStream::textToHtmlWithEmbeddedCodes($record['sent_text'], $navigator, 'ET_COM');
        $detail_out['sent_text'] = '<div class="excerpt" style="display: block; max-width:400px;">'.$comment_html.'</div>';
    }

}

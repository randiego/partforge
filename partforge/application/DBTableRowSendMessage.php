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

class DBTableRowSendMessage extends DBTableRow {

    public function __construct()
    {
        parent::__construct('sendmessage');
    }

    public function saveRecipientList($recipient_login_ids)
    {
        if (is_array($recipient_login_ids)) {
            $login_ids = DBTableRowUser::parseAndCheckSendToAddresses(implode(',', $recipient_login_ids), $errormsg);
            foreach ($login_ids as $login_id => $record) {
                $SendTo = new DBTableRow('messagerecipient');
                $SendTo->sendmessage_id = $this->sendmessage_id;
                $SendTo->to_user_id = $record['user_id'];
                $SendTo->save();
            }
        }
    }

    public static function queueUpCommentForSending($comment_id, $itemobject_id, $send_to_login_ids)
    {
        $errormsg = array();
        $send_error_messages = array();
        $login_ids = DBTableRowUser::parseAndCheckSendToAddresses($send_to_login_ids, $errormsg);
        if (count($errormsg) == 0) {
            $SendMessage = new self();
            $SendMessage->comment_id = $comment_id;
            $SendMessage->url = '/struct/io/'.$itemobject_id;
            $SendMessage->from_user_id = $_SESSION['account']->user_id;
            $ItemVersion = new DBTableRowItemVersion();
            // now build the description of the Part or Procedure
            if ($ItemVersion->getCurrentRecordByObjectId($itemobject_id)) {
                $title_short = $ItemVersion->getPageTypeTitleHtml(true).($ItemVersion->item_serial_number ? ' - '.TextToHtml($ItemVersion->item_serial_number) : '');
                $item_disposition = $ItemVersion->hasADisposition() ? ' ('.DBTableRowItemVersion::renderDisposition($ItemVersion->getFieldType('disposition'), $ItemVersion->disposition, false, '').')' : '';
                $SendMessage->object_name = $title_short.$item_disposition;
            }
            $SendMessage->save();
            $SendMessage->saveRecipientList(array_keys($login_ids));
            if (!Zend_Registry::get('config')->use_send_message_queue) {
                // we try to send this message now.
                $send_error_messages = self::processUnsentMessages($SendMessage->sendmessage_id);
            }
        }
        return $send_error_messages;
    }

    public static function queueUpLinkForSending($abs_url, $send_to_login_ids, $message, $page_title)
    {
        $errormsg = array();
        $send_error_messages = array();
        $login_ids = DBTableRowUser::parseAndCheckSendToAddresses($send_to_login_ids, $errormsg);
        if (count($errormsg) == 0) {
            $SendMessage = new self();
            $SendMessage->comment_id = -1;
            // try to strip the absolute part. If we can't leave it blank because something is wrong.
            $pos = strpos($abs_url, getAbsoluteBaseUrl());
            if ($pos !== false) {
                $SendMessage->url = substr_replace($abs_url, '', $pos, strlen(getAbsoluteBaseUrl()));  // will be something like  '/struct/io/'.$itemobject_id
            }
            $SendMessage->from_user_id = $_SESSION['account']->user_id;
            $SendMessage->object_name = $page_title;
            $SendMessage->message_text = $message;
            $SendMessage->save();
            $SendMessage->saveRecipientList(array_keys($login_ids));
            if (!Zend_Registry::get('config')->use_send_message_queue) {
                // we try to send this message now.
                $send_error_messages = self::processUnsentMessages($SendMessage->sendmessage_id);
            }
        }
        return $send_error_messages;
    }

    public static function deleteItemsForCommentId($comment_id) {
        if (is_numeric($comment_id) && $comment_id != -1) {
            $records = DbSchema::getInstance()->getRecords('sendmessage_id', "SELECT DISTINCT sendmessage_id FROM sendmessage where comment_id = '{$comment_id}'");
            foreach ($records as $sendmessage_id => $record) {
                $SendMessage = new self();
                $SendMessage->getRecordById($sendmessage_id);
                DbSchema::getInstance()->deleteRecord('messagerecipient', $sendmessage_id, 'sendmessage_id', "");
                $SendMessage->delete();
            }
        }
    }

    public static function formatDocumentsBlock($document_ids, $Emailer)
    {
        $thumbs = $files = array();
        $icon_content_ids = array(); // an array of
        foreach ($document_ids as $document_id) {
            $Doc = new DBTableRowDocument();
            $Doc->getRecordById($document_id);
            if ($Doc->document_thumb_exists) {
                $cid = $Doc->document_id;
                $Emailer->PHPMailer->addEmbeddedImage($Doc->fullStoredFileName('/thumbnail'), $cid);
                $thumbs[] = '<span style="font-weight: bold;margin-bottom: 2px;margin-right: 2px;"><img style="border:0;" src="cid:'.$cid.'"></span>';
            } else {
                $icon_filename = DBTableRowDocument::findIconFileName($Doc->document_file_type, $Doc->document_displayed_filename);
                if (!isset($icon_content_ids[$icon_filename])) {
                    $icon_content_ids[$icon_filename] = count($icon_content_ids) + 1;
                    $cid = $icon_content_ids[$icon_filename];
                    $Emailer->PHPMailer->addEmbeddedImage(dirname(__FILE__) . '/../public/images/'.$icon_filename, $cid);
                } else {
                    $cid = $icon_content_ids[$icon_filename];
                }
                $icon_img = '<IMG style="vertical-align:middle;" src="cid:'.$cid.'" width="16" height="16" border="0">';
                $size = number_format(ceil($Doc->document_filesize / 1024)).' KB';
                $fname = $Doc->document_displayed_filename.' <span class="size">'.$size.'</span>';
                $files[] = '<div style="font-size:11px; font-weight: bold; margin-bottom: 2px;">'.$icon_img.' '.$fname.'</div>';
            }
        }
        return implode('', $files).implode('', $thumbs);
    }

    public function formatHtmlEmailAndSend($recipient_user_id)
    {
        require_once('functions.email.php');
        $User = new DBTableRowUser();
        $User->getRecordById($recipient_user_id);
        $toemail = $User->email;
        $toname = $User->fullName();
        $fromemail = Zend_Registry::get('config')->notices_from_email;
        $subject = '';
        $Sender = new DBTableRowUser();
        $Sender->getRecordById($this->from_user_id);
        $sender_name =  $Sender->fullName(false);
        $sender_email = $Sender->email;

        if (is_numeric($this->comment_id) && $this->comment_id!=-1) {
            // we prepare a comment-based message
            $subject = $sender_name." Sent You a Comment About ".$this->object_name;
            $linkifiedsubject = $sender_name." Sent You a Comment About ".linkify(getAbsoluteBaseUrl().$this->url, $this->object_name);
            $Emailer = new Email($toemail, $toname, $fromemail, Zend_Registry::get('config')->application_title, '', '', $subject, '', false);
            $Emailer->setContentType('text/html; charset=utf8');
            $Comment = new DBTableRowComment();
            if ($Comment->getRecordById($this->comment_id)) {
                list($event_description,$event_description_array) = EventStream::textToHtmlWithEmbeddedCodes($Comment->comment_text, null, 'ET_COM');
                $documents_block_html = self::formatDocumentsBlock($Comment->document_ids, $Emailer);
                $html = '';
                $html .= "<p>{$linkifiedsubject}</p>";

                // the following is painstakingly formatted to handle MS Outlook's own private circle of formatting hell. Don't judge me.
                $html .= '
                    <table style="background-color: rgb(255, 255, 255); border:3px solid #559; margin: 0; padding: 5px; max-width: 690px; min-width: 400px;"><tr><td>
                        <table cellspacing="0" cellpadding="0">
                            <tr>
                                <td width="34" style="padding-top:5px;"><img src="cid:chatballoon"/ style="margin: 0px;"></td>
                                <td style="font-family: Arial, Helvetica, sans-serif;">
                                    <p style="font-weight: bold; font-size: 15px; color: #01317E; margin: 0px; padding: 0px;">
                                    '.TextToHtml(strtoupper(DBTableRowUser::getFullName($Comment->user_id))).'</p>
                                    <p style="margin: 0px; padding: 0px; color: #777; font-weight: bold; font-size: 11px;">'.time_to_bulletdate(strtotime($Comment->comment_added)).'</p>
                                </td>
                            </tr>
                        </table>
                        <div style="margin: 10px 0 0 0; padding: 0; font-size: 12px; font-family: Arial, Helvetica, sans-serif;">
                            <div>'.$documents_block_html.'</div>
                            '.$event_description.'
                        </div>
                    </td></tr></table>
				';
                $Emailer->PHPMailer->addEmbeddedImage(dirname(__FILE__) . '/../public/images/'."chat_baloon.png", 'chatballoon');

                //file_put_contents('C:\wamp64\www\qdforms2\htmlemail.html', $html."\r\n\r\n", FILE_APPEND);
            }
        } else {
            // we are sending just a message without an associated comment
            $subject = $sender_name." Sent a Link to ".$this->object_name;
            $linkifiedsubject = $sender_name." Sent a Link to ".linkify(getAbsoluteBaseUrl().$this->url, $this->object_name);
            $Emailer = new Email($toemail, $toname, $fromemail, Zend_Registry::get('config')->application_title, '', '', $subject, '', false);
            $Emailer->setContentType('text/html; charset=utf8');
            $html = '';
            $html .= '<p style="max-width: 600px;">'.$linkifiedsubject.'</p>';
            $html .= '<p style="max-width: 600px;">'.TextToHtml($this->message_text).'</p>';
            //file_put_contents('C:\wamp64\www\qdforms2\htmlemail.html', $html."\r\n\r\n", FILE_APPEND);
        }
        $Emailer->PHPMailer->Body = $html;
        if ($sender_email) {
            $Emailer->PHPMailer->addReplyTo($sender_email, $sender_name);
        }
        if (!$Emailer->Send()) {
            logerror("Email($toemail, $toname, $fromemail, Zend_Registry::get('config')->application_title, '', '', $subject, $html)->Send() in formatHtmlEmailAndSend()");
            return $Emailer->ErrorInfo();
        } else {
            return '';
        }
    }

    public static function processUnsentMessages($sendmessage_id = null)
    {
        if (!is_null($sendmessage_id) && is_numeric($sendmessage_id)) {
            $records = array($sendmessage_id => $sendmessage_id);
        } else {
            // select all records from sendmessage where sent_on is null.
            $records = DbSchema::getInstance()->getRecords('sendmessage_id', "SELECT DISTINCT sendmessage_id FROM sendmessage where sent_on IS NULL");
        }
        foreach ($records as $sendmessage_id => $record) {
            $send_error_messages = array();
            $SendMessage = new self();
            $SendMessage->getRecordById($sendmessage_id);
            $recipients = DbSchema::getInstance()->getRecords('to_user_id', "SELECT DISTINCT to_user_id FROM messagerecipient where sendmessage_id='{$sendmessage_id}'");
            foreach ($recipients as $recipient_user_id => $record) {
                $send_error_message = $SendMessage->formatHtmlEmailAndSend($recipient_user_id);
                if ($send_error_message) {
                    $send_error_messages[] = $send_error_message;
                }
            }
            $SendMessage->sent_on = time_to_mysqldatetime(script_time());
            $SendMessage->save(array('sent_on'));
        }
        return $send_error_messages;
    }

    public static function getMessageForComment($comment_id)
    {
        $out = array();
        $records = DbSchema::getInstance()->getRecords('sendmessage_id', "SELECT sendmessage.*, user.*
                FROM sendmessage
                LEFT JOIN user on user.user_id=sendmessage.from_user_id
                WHERE comment_id='{$comment_id}' ORDER BY sent_on");
        foreach ($records as $sendmessage_id => $record) {
            $recipient_names = array();
            $recipients = DbSchema::getInstance()->getRecords('', "SELECT user.*
                    FROM messagerecipient
                    LEFT JOIN user on user.user_id=to_user_id
                    WHERE sendmessage_id='{$sendmessage_id}'
                    ORDER BY user.last_name, user.first_name");
            foreach ($recipients as $recipient) {
                $recipient_names[] = TextToHtml(DBTableRowUser::concatNames($recipient).' ('.$recipient['login_id'].')');
            }
            $row = array('sent_on' => time_to_bulletdate(strtotime($record['sent_on']), false), 'from' => TextToHtml(strtoupper(DBTableRowUser::concatNames($record))), 'to' => $recipient_names);

            $out[] = $row;
        }
        return $out;
    }

}

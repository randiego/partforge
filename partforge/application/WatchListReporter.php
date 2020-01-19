<?php
/**
 *
 * PartForge Enterprise Groupware for recording parts and assemblies by serial number and version along with associated test data and comments.
 *
 * Copyright (C) 2013-2020 Randall C. Black <randy@blacksdesign.com>
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

class WatchListReporter {
	
	public function __construct() {
		
	}
	

	/**
	 * This checks for any users who are subscribed for daily notifications (in changesubscription table)
	 * and then look in their preferences file at the followNotifyTimeHHMM and lastFollowNotifyTimeDateAndHHMM
	 * settings to see if it's time to send an email.  If so, all the items in the change log since the last notify time
	 * are gathered and included in the email.  An "empty" email is sent if they have at least one daily notify set
	 * but there are no actual changes.
	 */
	static public function processCurrentDailyWatchNotifications() {
		$current_time = time_to_mysqldatetime(script_time());           // 2016-05-10 21:25:00
		$current_date = time_to_mysqldate(script_time()).' 00:00:00';   // 2016-05-10 00:00:00
		$user_ids_records = DbSchema::getInstance()->getRecords('',"SELECT DISTINCT cs.user_id FROM changesubscription cs
				LEFT JOIN userpreferences ntpref ON ntpref.user_id=cs.user_id AND ntpref.pref_key='followNotifyTimeHHMM'
				LEFT JOIN userpreferences lntpref ON lntpref.user_id=cs.user_id AND lntpref.pref_key='lastFollowNotifyTimeDateAndHHMM'
				WHERE (ntpref.pref_value IS NOT NULL) and (TIMESTAMPDIFF(SECOND, ADDTIME('{$current_date}', STR_TO_DATE(ntpref.pref_value,'%H:%i')   ), '{$current_time}') >= 0)
				and (IFNULL(TIMESTAMPDIFF(SECOND, ADDTIME('{$current_date}', STR_TO_DATE(ntpref.pref_value,'%H:%i')   ), STR_TO_DATE(lntpref.pref_value,'%Y-%m-%d %H:%i')),-1) < 0)
				and (cs.notify_daily=1)");

		foreach($user_ids_records as $user_ids_record) {
			$user_id = $user_ids_record['user_id'];
			
			
			// select change dates >= getPreference('lastFollowNotifyTimeDateAndHHMM') or if not exists, then for the last 24 hours.
			$last_time_str = DBTableRowUser::getUserPreference($user_id, 'lastFollowNotifyTimeDateAndHHMM');
			// in case there was no last time, or there is something wrong with that time or too long ago, fake one...
			$start_time = is_null($last_time_str) || !strtotime($last_time_str) || (strtotime($last_time_str) < script_time() - 48*3600 ) ? script_time() - 24*3600 : strtotime($last_time_str);
			$end_time = script_time();
			
			// select all from changelog where in list.  (this query almost identical to just going to the Watching tab.)
			$DBTableRowQuery = DBTableRowChangeLog::getNewQueryForApiOutput();
			$DBTableRowQuery->setOrderByClause("ORDER BY changelog.changed_on asc");
			$DBTableRowQuery->addAndWhere(" and (changelog.changed_on>='".time_to_mysqldatetime($start_time)."')");
			$DBTableRowQuery->addAndWhere(" and (changelog.changed_on<='".time_to_mysqldatetime($end_time)."')");
			$DBTableRowQuery->addAndWhere(" and Exists (select 1 from changesubscription where (
						  (
						    (changelog.trigger_itemobject_id IS NULL) and
						    (changesubscription.typeobject_id = changelog.trigger_typeobject_id)
						  ) or
						  (
						    (changelog.trigger_itemobject_id IS NOT NULL) and 
						    ( 
						       (changesubscription.itemobject_id = changelog.trigger_itemobject_id) or 
						       ( (changesubscription.typeobject_id = changelog.trigger_typeobject_id) and  (changesubscription.follow_items_too=1) )
						     )
						  )					
					) and (changesubscription.user_id='{$user_id}')  and (changesubscription.notify_daily=1))");
			$records = DbSchema::getInstance()->getRecords('',$DBTableRowQuery->getQuery());
			
			DBTableRowUser::setUserPreference($user_id, 'lastFollowNotifyTimeDateAndHHMM', date('Y-m-d H:i', $end_time));			
			
			$User = new DBTableRowUser();
			if ($User->getRecordById($user_id)) {
				$user_blocks_notifications = $User->getPreference('turnOffWatchListNotifications');
				if (is_null($user_blocks_notifications)) $user_blocks_notifications = false;
				if ($User->user_enabled && !$user_blocks_notifications) {			
					$html_email_body = self::formatDailyWatchHtmlReport($records, $start_time, $end_time);
					// " [".Zend_Registry::get('config')->application_title."]"
					$items = count($records)==0 ? 'no items' : (count($records)==1 ? '1 item' : count($records).' items');
					$subject = "Your Daily Watchlist Report for ".date('D, M j, Y',$end_time)." ({$items})";
					self::sendWatchListEmail($User,$html_email_body, $subject);
					//file_put_contents('C:\wamp\www\qdforms2\watch_daily_dump.txt', $html_email_body."\r\n\r\n", FILE_APPEND);
				}
			}
		}
	}
	
	static public function processCurrentInstantNotificationsNow($user_id, $changelog_id) {
		$DBTableRowQuery = DBTableRowChangeLog::getNewQueryForApiOutput();
		$DBTableRowQuery->setOrderByClause("ORDER BY changelog.changed_on asc");  // redundent really
		$DBTableRowQuery->addAndWhere(" and (changelog.changelog_id='{$changelog_id}')");
		$records = DbSchema::getInstance()->getRecords('',$DBTableRowQuery->getQuery());
		$User = new DBTableRowUser();
		if ($User->getRecordById($user_id)) {
			$user_blocks_notifications = $User->getPreference('turnOffWatchListNotifications');
			if (is_null($user_blocks_notifications)) $user_blocks_notifications = false;
			if ($User->user_enabled && !$user_blocks_notifications) {
				$html_email_body = self::formatInstantWatchHtmlReport($records);
				$subject = "Something has Changed on Your Instant Watchlist";
				self::sendWatchListEmail($User,$html_email_body, $subject);
			}
		}
	}
	
	
	static public function processCurrentInstantNotifications() {
		$changenotifyqueue_ids = DbSchema::getInstance()->getRecords('changenotifyqueue_id',"select changenotifyqueue_id from changenotifyqueue");
		$changenotifyqueue_ids = array_keys($changenotifyqueue_ids);
		
		if (count($changenotifyqueue_ids)>0) {
		
			// now that we have all the record ids from the queue, we first get them grouped by user_id
			$user_ids_records = DbSchema::getInstance()->getRecords('user_id',"SELECT DISTINCT user_id, GROUP_CONCAT(changelog_id) as changelog_ids 
					FROM changenotifyqueue 
					WHERE changenotifyqueue_id in (".implode(',',$changenotifyqueue_ids).")
					GROUP BY user_id ORDER BY user_id");
	
			foreach($user_ids_records as $user_id => $user_ids_record) {
				$changelog_ids = $user_ids_record['changelog_ids'];
				$changelog_ids = explode(',',$changelog_ids);
	
				if (count($changelog_ids)>0) {
					$DBTableRowQuery = DBTableRowChangeLog::getNewQueryForApiOutput();
					$DBTableRowQuery->setOrderByClause("ORDER BY changelog.changed_on asc");
					$DBTableRowQuery->addAndWhere(" and (changelog.changelog_id in ('".implode("','",$changelog_ids)."'))");
					$records = DbSchema::getInstance()->getRecords('',$DBTableRowQuery->getQuery());
					$User = new DBTableRowUser();
					if ($User->getRecordById($user_id)) {
						$user_blocks_notifications = $User->getPreference('turnOffWatchListNotifications');
						if (is_null($user_blocks_notifications)) $user_blocks_notifications = false;
						if ($User->user_enabled && !$user_blocks_notifications) {
							$html_email_body = self::formatInstantWatchHtmlReport($records);
							$subject = "Something has Changed on Your Instant Watchlist";
							self::sendWatchListEmail($User,$html_email_body, $subject);
						}
					}
				}
			}
		
			$query = "delete from changenotifyqueue where changenotifyqueue_id in (".implode(',',$changenotifyqueue_ids).")";
			DbSchema::getInstance()->mysqlQuery($query);
		}
		
	}
	
	
	static public function sendWatchListEmail(DBTableRowUser $User,$html_body, $subject,$tmp = array()) {
		$toemail = $User->email;
		$toname = $User->fullName();
		$fromemail = Zend_Registry::get('config')->notices_from_email;
		return send_template_email($html_body,$toemail,$toname,$fromemail,Zend_Registry::get('config')->application_title,$tmp,$subject,'','','text/html');
	}	

	
	static public function formatDailyWatchHtmlReport($records, $start_time, $end_time) {
		$html = '';
		$duration_hours = number_format(($end_time - $start_time)/3600,0);
		$url = Zend_Controller_Front::getInstance()->getRequest()->getScheme().'://'.Zend_Controller_Front::getInstance()->getRequest()->getHttpHost().Zend_Controller_Front::getInstance()->getRequest()->getBaseUrl()."/struct/changelistview";
		$html .= "<p>This is ".linkify($url, "Your Daily Watchlist Report")." for ".date('D, M j, Y @ G:i',$end_time).".</p>";
		
		if (count($records)>0) {
			$html .= "<p>Activity for the past {$duration_hours} hours:</p>";
			$html .= self::formatWatchHtmlTable($records);
		} else {
			$html .= "<p>There has been no activity on Your Watchlist for the past {$duration_hours} hours:</p>";
		}
		$urlmylist = Zend_Controller_Front::getInstance()->getRequest()->getScheme().'://'.Zend_Controller_Front::getInstance()->getRequest()->getHttpHost().Zend_Controller_Front::getInstance()->getRequest()->getBaseUrl().'/struct/watchlistview?resetview=1';
		$html .= '<p>Click '.linkify($urlmylist,'here').' to manage your Watchlist.</p>';
		return $html;
	}	

	
	static public function formatInstantWatchHtmlReport($records) {
		$html = '';
		$html .= "<p>The following has changed on your Instant Watchlist:</p>";
		$html .= self::formatWatchHtmlTable($records);
		$url = Zend_Controller_Front::getInstance()->getRequest()->getScheme().'://'.Zend_Controller_Front::getInstance()->getRequest()->getHttpHost().Zend_Controller_Front::getInstance()->getRequest()->getBaseUrl().'/struct/watchlistview?resetview=1';
		$html .= '<p>Click '.linkify($url,'here').' to manage your Watchlist.</p>';
		return $html;
	}	
	
	static public function formatWatchHtmlTable($records) {
		$html = '';
		$html .= '<table style="border-color: #CCC;border-collapse: collapse; font-size: 12px;" cellpadding="5">
					<tr><th style="background-color: #e8f2fc; border: 1px solid #ccc; padding: 5px; text-align: left; vertical-align: bottom;">Change Time</th>
					<th style="background-color: #e8f2fc; border: 1px solid #ccc; padding: 5px; text-align: left; vertical-align: bottom;">User</th>
					<th style="background-color: #e8f2fc; border: 1px solid #ccc; padding: 5px; text-align: left; vertical-align: bottom;">Change Description</th>
					<th style="background-color: #e8f2fc; border: 1px solid #ccc; padding: 5px; text-align: left; vertical-align: bottom;">Item Name</th>
					<th style="background-color: #e8f2fc; border: 1px solid #ccc; padding: 5px; text-align: left; vertical-align: bottom;">Procedure</th>
					<th style="background-color: #e8f2fc; border: 1px solid #ccc; padding: 5px; text-align: left; vertical-align: bottom;">Part</th>
					</tr>';
			
		foreach($records as $record) {
			$loc_parts = explode('/',$record['locator']);
			
			$item_name = TextToHtml($record['item_name']);
			if ($loc_parts[0]=='tv') $item_name = linkify(formatAbsoluteLocatorUrl($loc_parts[0],$loc_parts[1]), $item_name);
			
			$procedure_date = '';
			if (!is_null($record['procedure_date'])) $procedure_date = linkify(formatAbsoluteLocatorUrl($loc_parts[0],$loc_parts[1]),date('M j, Y G:i',strtotime($record['procedure_date'])));
			
			$item_serial_number = '';
			if (!is_null($record['item_serial_number'])) $item_serial_number = linkify(formatAbsoluteLocatorUrl($loc_parts[0],$loc_parts[1]),TextToHtml($record['item_serial_number']));
			
			$html .= '
				<tr>
				<td style="border: 1px solid #ccc;">'.date('D G:i',strtotime($record['changed_on'])).'</td>
				<td style="border: 1px solid #ccc;">'.TextToHtml($record['full_name']).'</td>
				<td style="border: 1px solid #ccc;"><div style="display: block; width:400px; max-width:400px;">'.TextToHtml($record['change_description']).'</div></td>
				<td style="border: 1px solid #ccc;">'.$item_name.'</td>
				<td style="border: 1px solid #ccc;">'.$procedure_date.'</td>
				<td style="border: 1px solid #ccc;">'.$item_serial_number.'</td>
				</tr>
				';
		}
		$html .= '</table>';
		return $html;
	}
	
	
}

<?php
/**
 *
 * PartForge Enterprise Groupware for recording parts and assemblies by serial number and version along with associated test data and comments.
 *
 * Copyright (C) 2013-2016 Randall C. Black <randy@blacksdesign.com>
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

class DBTableRowChangeSubscription extends DBTableRow {
	
	public function __construct() {
		parent::__construct('changesubscription');
		$this->added_on = time_to_mysqldatetime(script_time());
	}
		
	public function getRecordByIds($user_id, $itemobject_id, $typeobject_id) {
		$DBTableRowQuery = new DBTableRowQuery($this);
		$DBTableRowQuery->setLimitClause('LIMIT 1')->addSelectors(array('user_id' => $user_id));
		if (!is_null($itemobject_id)) $DBTableRowQuery->addSelectors(array('itemobject_id' => $itemobject_id));
		if (!is_null($typeobject_id)) $DBTableRowQuery->addSelectors(array('typeobject_id' => $typeobject_id));
		return $this->getRecord($DBTableRowQuery->getQuery());
	}
	
	public function getDescription() {
		$action = array();
		$time = $_SESSION['account']->getPreference('followNotifyTimeHHMM');
		if ($this->notify_instantly) $action[] = 'instantly';
		if ($this->notify_daily) $action[] = 'daily at '.$time;
		return count($action)>0 ? "You are currently emailed ".implode(' and ',$action)." if this item changes." : "";
	}

	static function setFollowing($user_id, $itemobject_id, $typeobject_id, $notify_instantly, $notify_daily) {
		$S = new self();
		if ($S->getRecordByIds($user_id, $itemobject_id, $typeobject_id)) {
			$S->delete();
		}
		$S = new self();
		$S->user_id = $user_id;
		$S->itemobject_id = $itemobject_id;
		$S->typeobject_id = $typeobject_id;
		$S->notify_instantly = $notify_instantly;
		$S->notify_daily = $notify_daily;
		$S->save();
	}

	static public function clearFollowing($user_id, $itemobject_id, $typeobject_id) {
		$S = new self();
		$S->getRecordByIds($user_id, $itemobject_id, $typeobject_id);
		$S->delete();
	}
	
	/**
	 * This is called immediately after a change has occured so we can prepare to send an instant notification. 
	 * @param DBTableRowChangeLog $Rec The changelog record that was just added and that we now take action on
	 */
	static public function triggerChangeNotice(DBTableRowChangeLog $Rec) {
		$changelog_id = $Rec->changelog_id;
		$trigger_itemobject_id = $Rec->trigger_itemobject_id;
		$trigger_typeobject_id = $Rec->trigger_typeobject_id;
		
		// get all records from subscriptions that are watching either of these then add to the changenotifyqueue.

		$io_where = !is_null($Rec->trigger_itemobject_id) ? "(itemobject_id='{$Rec->trigger_itemobject_id}')" : '';
		$to_where = !is_null($Rec->trigger_typeobject_id) ? "(typeobject_id='{$Rec->trigger_typeobject_id}')" : '';
		
		$cond = array();
		if (!is_null($Rec->trigger_itemobject_id)) $cond[] = "((itemobject_id IS NOT NULL) and (itemobject_id='{$Rec->trigger_itemobject_id}'))";
		if (!is_null($Rec->trigger_typeobject_id)) $cond[] = "((typeobject_id IS NOT NULL) and (typeobject_id='{$Rec->trigger_typeobject_id}'))";
		if (count($cond)>0) {
			$and_where = " and (".implode(' or ',$cond).")";
			$records = DbSchema::getInstance()->getRecords('',"select distinct user_id from changesubscription where notify_instantly=1 {$and_where}");
			foreach($records as $record) {
				if (Zend_Registry::get('config')->use_instant_watch_queue) {
					$Notify = new DBTableRow('changenotifyqueue');
					$Notify->added_on = time_to_mysqldatetime(script_time());
					$Notify->user_id = $record['user_id'];
					$Notify->changelog_id =  $Rec->changelog_id;
					$Notify->save();
				} else {
					WatchListReporter::processCurrentInstantNotificationsNow($record['user_id'], $Rec->changelog_id);
				}
			}
		}
	}
	
}

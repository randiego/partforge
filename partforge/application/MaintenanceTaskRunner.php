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

/**
 * Instance this and call it to run the processes that are in need for background processing.
 */
class MaintenanceTaskRunner {
	
	public $params = array();
	protected $scheduled_tasks = array();
	public $messages = array();
	

	public function __construct($params) {
		$this->params = $params;
		trim_recursive($this->params);
		
		/*
		 * method names of the various tasks we will be performing.  Each method
		 * public function_name(&$messages) where messages is an array of entries array('message' => 'bla bla bla', 'notify' => false or true)
		 */
		// method names of the various tasks we will be performing.  Each method
		$this->scheduled_tasks = array(
			array('name' => 'update_definition_stats', 'interval' => 5*60),
			array('name' => 'update_cached_fields', 'interval' => 8*3600),
			array('name' => 'generate_user_reports', 'interval' => 5*60),   // reports have their own intervals, so this is not too frequent
			array('name' => 'update_user_stats', 'interval' => 5*60),
			array('name' => 'service_inprocess_workflows', 'interval' => 30), // this should really be as responsive as possible
		);
		
	}
	
	public function run() {
		/*
		 * get all the task log.  For each $this->scheduled_tasks see if it needs to be run, run it if needed then record the run date.
		 */
		$this->messages = array();
		$task_log = DbSchema::getInstance()->getRecords('tl_key',"SELECT * FROM taskslog");
		foreach($this->scheduled_tasks as $scheduled_task) {
			$name = $scheduled_task['name'];
			$interval = $scheduled_task['interval'];
			$run = false;
			if (isset($task_log[$name])) {
				$last_run = strtotime($task_log[$name]['tl_last_run']);
				if (script_time() - $last_run > $interval) $run = true;
			} else {
				$run = true;
			}
			
			if ($run) {
				if (method_exists($this, $name)) {
					/*
					 * reset the log
					 */
					$TaskLog = new DBTableRow('taskslog');
					if ($TaskLog->getRecordWhere("tl_key='{$name}'")) {
						//
					} else {
						$TaskLog->tl_key = $name;
					}
					$TaskLog->tl_last_run =time_to_mysqldatetime(script_time());						
					$TaskLog->save();					

					// we run after resetting the task table.  This makes sure that long infrequent tasks don't overrun themselves with repeated calls.
					$this->$name($this->messages);
						
				} else {
					$this->messages[] = array('message' => "The tasks '{$name}' does not exist", 'notify' => true);
				}
			}
		}
	}
	
	/**
	 * returns array of message, notify pairs for all the messages.
	 * @return array:
	 */
	public function getMessages() {
		return $this->messages;
	}
	
	/**
	 * Update the cached fields in typeobject and  in the Definition view (partlistview).
	 */
	private function update_definition_stats(&$messages) {
		// update the statistics field
		$query = "UPDATE typeobject SET cached_item_count=(SELECT COUNT(*) FROM itemobject LEFT JOIN itemversion on itemversion.itemversion_id=itemobject.cached_current_itemversion_id
											LEFT JOIN typeversion on typeversion.typeversion_id=itemversion.typeversion_id
											WHERE typeversion.typeobject_id=typeobject.typeobject_id)";
		DbSchema::getInstance()->mysqlQuery($query);
		DBTableRowTypeObject::updateCachedNextSerialNumberFields();
		DBTableRowTypeObject::updateCachedHiddenFieldCount();
	}
	
	/**
	 * This updates other cached_ fields in itemobject table.  Under normal circumstances these cache fields are
	 * maintained on the fly by various save and delete functions and dont need to be explicitely updated like this.
	 * @param unknown_type $messages
	 */
	private function update_cached_fields(&$messages) {
		DBTableRowItemVersion::updateCurrentItemVersionIds();
		DBTableRowItemObject::updateCachedLastCommentFields();
		DBTableRowItemObject::updateCachedLastReferenceFields();
		DBTableRowItemObject::updateCachedCreatedOnFields();		
	}
	
	private function generate_user_reports(&$messages) {
		// the report stuff...
		if (Zend_Registry::get('config')->show_analyze_page) {
			$reports = ReportGenerator::getReportList();
			foreach($reports as $report) {
				set_time_limit(300);
				$need_to_run = true;
				// if we ran recently, then don't do it yet.
				if (isset($report['last_run']) && ((script_time() - strtotime($report['last_run']))/24.0/3600. < $report['update_interval'])) {
					$need_to_run = false;
				}
		
				if ($need_to_run) {
					$Report = ReportGenerator::getReportObject($report['class_name']);
					$Report->process();
					$Report->cacheCSV();
					$Report->buildGraphFromSavedCSV();
					$this->messages[] = array('message' => 'Ran report '.$report['class_name'], 'notify' => false);
				}
			}
			// reconnect full access since we may have connected in read-only mode.
			DbSchema::getInstance()->connectFullAccess();
		}		
	}
	
	/**
	 * update the statistics field cached_items_created_count
	 * @param array $messages
	 */
	private function update_user_stats(&$messages) {
		$query = "UPDATE user as u SET cached_items_created_count=((select count(*) from document where (user_id=u.user_id))
								+ (select count(*) from comment where (user_id=u.user_id) or (proxy_user_id=u.user_id) )
								+ (select count(*) from itemversion where (user_id=u.user_id) or (proxy_user_id=u.user_id) ))";
		DbSchema::getInstance()->mysqlQuery($query);		
	}
	
	private function service_inprocess_workflows(&$messages) {
		$group_task_ids = GroupTask::getActiveWorkFlowIds();
		foreach($group_task_ids as $group_task_id) {
			$Workflow = GroupTask::getInstance($group_task_id);
			if (!is_null($Workflow)) {
				$Workflow->evolve();
			}
		}
	}
}

<?php
/**
 *
 * PartForge Enterprise Groupware for recording parts and assemblies by serial number and version along with associated test data and comments.
 *
 * Copyright (C) 2013-2015 Randall C. Black <randy@blacksdesign.com>
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
class GroupTask {

	protected $record = null;
	public $wait_for_each_person_sec = 600;
	public $wait_before_reminder_sec = 86400; // 1 day
	private $timestamp_security_offset = '2004-03-01 18:00:00';
	
	public function __construct() {
		
	}
	
	/**
	 * create an instance of the class or a subclass
	 * @param string or int $group_task_or_classname_id  for a new class the argument is the class name, otherwise it is the group_task_id to be read from the DB.
	 * @return GroupTask instance or null
	 */
	static public function getInstance($group_task_or_classname_id) {
				
		$GroupTaskRec = new DBTableRow('group_task');
		if (is_numeric($group_task_or_classname_id)) {
			if ($GroupTaskRec->getRecordById($group_task_or_classname_id)) {
				$class = $GroupTaskRec->class_name;
				if (class_exists($class)) {
					$TheClass = new $class();
					if ($TheClass instanceOf GroupTask) {
						$TheClass->record = $GroupTaskRec;
						return $TheClass;
					}
				}				
			}
			return null;
		} else {
			$class = $group_task_or_classname_id;
			if (class_exists($class)) {
				$TheClass = new $class();
				if ($TheClass instanceOf GroupTask) {
					$TheClass->record = $GroupTaskRec;
					return $TheClass;
				}
			}			
		}
		return null;
		
	}
	
	/**
	 * get list of the group_task_ids for group_tasks that are still not closed.
	 * @param int $for_user_id  if specified, the list of tasks will be limited to those with the specified user as someone assigned.
	 * @return array of ids
	 */
	static public function getActiveWorkFlowIds($for_user_id=null) {
		$where = is_null($for_user_id) ? '' : "assigned_to_task.user_id='{$for_user_id}' and";
		$records = DbSchema::getInstance()->getRecords('group_task_id',"SELECT assigned_to_task.assigned_to_task_id, group_task.group_task_id FROM assigned_to_task
				LEFT JOIN group_task ON group_task.group_task_id=assigned_to_task.group_task_id
				WHERE {$where} group_task.closed_on IS NULL
				ORDER BY group_task.created_on");
		return array_keys($records);
	}
	
	
	/**
	 * This starts a new workflow by adding it to the tables.
	 * @param array $user_ids
	 * @param string $text this is a 1 liner describing the workflow: "New account approval request for Terry Johnson" 
	 * @param string $redirect_url is the base part url to jump to after someone clicks the link in the workflow notification. Does not include host.
	 */
	public function start($user_ids, $text, $redirect_url=null) {
		$records = DbSchema::getInstance()->getRecords('user_id',"SELECT * FROM user WHERE user_id in (".implode(',',$user_ids).")");
		if (count($records)>0) {
			$this->record = new DBTableRow('group_task');
			$this->record->class_name = get_class($this);
			$this->record->created_on = time_to_mysqldatetime(script_time());
			$this->record->title = $text;
			$this->record->redirect_url = $redirect_url;
			$this->record->save();
			
			foreach($records as $user_id => $record) {
				$Assigned = new DBTableRow('assigned_to_task');
				$Assigned->group_task_id = $this->record->group_task_id;
				$Assigned->user_id = $user_id;
				$Assigned->link_password = self::generatePassword($user_id);
				$Assigned->save();
			}
		}
		$this->evolve();
	}
	
	/**
	 * return a password for the purpose of link recognition
	 * @param int $user_id
	 * @return string the password string
	 */
	static public function generatePassword($user_id) {
		return substr(md5("{$user_id}".(string)script_time()),0,8);
	}
	
	public function getAssignedToTasks($user_id=null) {
		$and_where = is_null($user_id) ? '' : " and (user_id='{$user_id}') ";
		return DbSchema::getInstance()->getRecords('assigned_to_task_id',"SELECT * FROM assigned_to_task WHERE (group_task_id='{$this->record->group_task_id}') {$and_where}");
	}
	
	public function responded($user_id) {
		// get the user record.
		$records = $this->getAssignedToTasks();
		foreach($records as $assigned_to_task_id => $record) {
			$Assigned = new DBTableRow('assigned_to_task');
			$Assigned->assign($record);			
			if ($record['user_id']==$user_id) {
				$Assigned->responded_on = time_to_mysqldatetime(script_time());
				$Assigned->save(array('responded_on'));
			} else {
				if ($Assigned->notified_on) {
					$Assigned->nevermind_on = time_to_mysqldatetime(script_time());
					$Assigned->save(array('nevermind_on'));
					$tmp = array();
					$tmp['RESPONDER_NAME'] = DBTableRowUser::getFullName($user_id);
					$this->sendEmail($record['user_id'],'WorkflowNevermindEmail.txt',"Cancel: ".$this->record->title,$tmp);
				}
			}
		}
		$this->record->closed_on = time_to_mysqldatetime(script_time());
		$this->record->save(array('closed_on'));
	}

	
	public function getRedirectUrl() {
		return $this->record->redirect_url;
	}
	
	protected function sendEmail($user_id,$email_file, $subject=null,$tmp = array()) {
		$User = new DBTableRowUser();
		if ($User->getRecordById($user_id)) {
			// send a nevermind email
			$tmp['WORKFLOW_TITLE'] = $this->record->title;
			$toemail = $User->email;
			$toname = $User->fullName();
			$fromemail = Zend_Registry::get('config')->notices_from_email;
			if (is_null($subject)) $subject = $this->record->title;
			if (!send_template_email(implode("",(@file(APPLICATION_PATH . '/views/'.$email_file))),$toemail,$toname,$fromemail,Zend_Registry::get('config')->application_title,$tmp,$subject)) {
				// oh well.  Nothing to do here if it fails
			}
		}
	}
	
	
	/**
	 * reads the state of the workflow and takes any actions based on elapsed time
	 */
	public function evolve() {
		
		// get the notified user that was notified most recently.  If it has been more than wait_for_each_person_sec then
		$waited_long_enough = false;
		$notified_users = DbSchema::getInstance()->getRecords('assigned_to_task_id',"SELECT * FROM assigned_to_task WHERE (group_task_id='{$this->record->group_task_id}') and (notified_on IS NOT NULL) ORDER BY notified_on desc");
		if (count($notified_users) > 0) {
			$notified_user = reset($notified_users);
			if (strtotime($notified_user['notified_on']) < script_time() - $this->wait_for_each_person_sec) {
				$waited_long_enough = true;
			}
		} else {
			$waited_long_enough = true;
		}
		
		if ($waited_long_enough) {
			// get the list of unnotified users giving preferece to those that have responded to workflows the most in the past year
			// or those that have logged into the system most recently.  Get the next one and send
			$one_year_ago = time_to_mysqldatetime(script_time()-365*24*3600);
			$unnotified_users = DbSchema::getInstance()->getRecords('assigned_to_task_id',"
					SELECT thistask.*, 
					   (SELECT COUNT(*) FROM assigned_to_task as thattask WHERE thistask.user_id=thattask.user_id and (thattask.responded_on IS NOT NULL) and (thattask.responded_on > '{$one_year_ago}')) as responded_count,
					   user.last_visit 
					FROM assigned_to_task as thistask
					LEFT JOIN user ON user.user_id=thistask.user_id 
			        WHERE (thistask.group_task_id='{$this->record->group_task_id}') and (thistask.notified_on IS NULL) order by responded_count desc, user.last_visit desc");
			if (count($unnotified_users)>0) {
				$unnotified_user = reset($unnotified_users);
				// notify this user
				$Assigned = new DBTableRow('assigned_to_task');
				$Assigned->assign($unnotified_user);			
				$Assigned->notified_on = time_to_mysqldatetime(script_time());
				$Assigned->save(array('notified_on'));
				$tmp = array();
				$tmp['URL'] = self::getLinkUrlForMember($Assigned->assigned_to_task_id);
				$this->sendEmail($Assigned->user_id,'WorkflowNotifyEmail.txt',null,$tmp);
					
			} else {
				// everyone must be notified already, so now we need to pester everyone at least once a day.
				foreach($notified_users as $notified_user) {
					$Assigned = new DBTableRow('assigned_to_task');
					$Assigned->assign($notified_user);
					$last_notice = $Assigned->reminded_on ? $Assigned->reminded_on : $Assigned->notified_on;
					if (script_time() > strtotime($last_notice) + $this->wait_before_reminder_sec) {
						$Assigned->reminded_on = time_to_mysqldatetime(script_time());
						$Assigned->save(array('reminded_on'));
						$tmp = array();
						$tmp['URL'] = self::getLinkUrlForMember($Assigned->assigned_to_task_id);
						$this->sendEmail($Assigned->user_id, 'WorkflowReminderEmail.txt', "Reminder: ".$this->record->title, $tmp);
					}
				}
			}
		}		
	}
	
	/**
	 * Returns the full URL of the link that a user would click (in an email normally) to participate and a workflow action.
	 * @param int $assigned_to_task_id
	 * @return string|boolean  if string this is a url.
	 */
	static function getLinkUrlForMember($assigned_to_task_id) {
		// get the link_password in order to form the link
		$Assigned = new DBTableRow('assigned_to_task');
		if ($Assigned->getRecordById($assigned_to_task_id)) {
			$locator = '/dotask/'.$assigned_to_task_id.'/'.$Assigned->link_password;
			return Zend_Controller_Front::getInstance()->getRequest()->getScheme().'://'.Zend_Controller_Front::getInstance()->getRequest()->getHttpHost().Zend_Controller_Front::getInstance()->getRequest()->getBaseUrl().$locator;
		} else {
			return false;
		}
	}
	
	function getTitle() {
		if ($this->record->isSaved()) {
			return $this->record->title.' ('.format_date_MjY($this->record->created_on).')';
		}
	}
	
}

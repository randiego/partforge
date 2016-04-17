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

class CronController extends Zend_Controller_Action
{
	private $_message_log = array();
	private $_message_notify = false;
	public $params;

	public function init()
	{
		$this->params = $this->getRequest()->getParams();
		trim_recursive($this->params);
	}

	private function printMessage($message,$notify=false) {
		$this->_message_log[] = $message;
		if ($notify) $this->_message_notify = true;
		echo $message."\r\n";
	}

	private function saveMessages() {
		$Log = new DBTableRowEventLog();
		$Log->event_log_date_added = time_to_mysqldatetime(script_time());
		$Log->event_log_text = implode("\r\n",$this->_message_log);
		$Log->event_log_notify = $this->_message_notify;
		if ($Log->event_log_text) $Log->save();  // only save this if there is something to say.
	}
	
	/**
	 * This services a bunch of background stuff.  It should be run approximately every minute.  
	 * 
	 * Crontab example:
	 * * * * * * /usr/bin/wget -q -O /var/www/html/partforge/cron/cronout_servicetasks.txt "http://www.yourserver.com/partforge/cron/servicetasks"
	 * 
	 * Individual tasks have their own interval determined by the MaintenanceTaskRunner.
	 */
	public function servicetasksAction() {
		setGlobal('last_task_run', time_to_mysqldatetime(script_time()));
		$TaskRunner = new MaintenanceTaskRunner($this->getRequest()->getParams());
		$TaskRunner->run();
		foreach($TaskRunner->getMessages() as $message) {
			$this->printMessage($message['message'],$message['notify']);
		}
		$this->saveMessages();
		exit;
	}

}

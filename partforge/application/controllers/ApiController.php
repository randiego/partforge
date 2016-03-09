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
class ApiController extends Zend_Controller_Action
{
	public $params;

	public function init()
	{
		$this->params = $this->getRequest()->getParams();
		trim_recursive($this->params);
	}
	
	public function returnOutput($data=array()) {
		echo json_encode($data);
		die();
	}
	
	public function helpAction() {
		$actions = array();
		$h = array();
		$h['getserialnumbers'] = 'returns a list of serial numbers for the specified typeobject_id number.  The second parameter should be something like typeobject_id=35.';
		$h['getusers'] = 'returns a list of login IDs for non-guest and non-data-terminal type users.';
		
		foreach (get_class_methods('ApiController') as $actionmeth) {
		
			if (strstr($actionmeth, "Action") !== false) {
				$action = substr($actionmeth,0,strlen($actionmeth)-strlen("Action"));
				if (isset($h[$action])) $action .= ': '.$h[$action];
				$actions[] = $action;
			}
		}	
		$this->returnOutput($actions);	
	}
	
	/**
	 * Gets a list of all the current serial numbers for the specified typeobject_id.  For parts, it returns
	 * a serial number with itemobject_id as the key.  If procedure, it returns an object of associated serial numbers.
	 * identified by name.
	 */
	public function getserialnumbersAction() {
		
		$json_array = isset($this->params['json_array']) && ($this->params['json_array']);

		$errormsg = array();
		if (!isset($this->params['typeobject_id']) || !is_numeric($this->params['typeobject_id'])) {
			$errormsg[] = 'You must include a typeobject_id parameter with your request.';
		} else {
			$TypeVersion = DbSchema::getInstance()->dbTableRowObjectFactory('typeversion');
			if (!$TypeVersion->getCurrentRecordByObjectId($this->params['typeobject_id'])) {	
				$errormsg[] = 'typeobject_id not found.';
			} else {
				if (DBTableRowTypeVersion::isTypeCategoryAProcedure($TypeVersion->typecategory_id)) {
					$errormsg[] = 'This is a procedure.  It has no serial numbers.';
				}
			}
		}


		$output = array('data' => array(), 'errormessages' => $errormsg);
		$simple_out = array();
		if (count($errormsg)==0) {
			$ReportData = new ReportDataItemListView(true,false,false,false,$this->params['typeobject_id']);
			 
			$dummyparms = array();
			// process records to fill out extra fields and do normal format conversion
			$records_out = $ReportData->get_export_detail_records($dummyparms, '','');
			$data = array();
			foreach($records_out as $record) {
				$data[$record['itemobject_id']] = $record['item_serial_number'];
				$simple_out[] = $record['item_serial_number'];
			}
			$output['data'] = $data;
		}
		 
		if ($json_array) {
			$this->returnOutput($simple_out);
		} else {
			$this->returnOutput($output);
		}
	}
	
	/**
	 * Gets a list of all the current users as an array login IDs.
	 */
	public function getusersAction() {	
		$records = $login_id_by_user_id = DbSchema::getInstance()->getRecords('user_id',"SELECT user_id,login_id FROM user where (user_type not in ('DataTerminal','Guest')) and (user_enabled=1) order by login_id");
		$simple_out = array();
		foreach($records as $record) {
			$simple_out[] = $record['login_id'];
		}
		$this->returnOutput($simple_out);
	}	


}

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

class TableRowApproveAccount extends TableRow {

	public function __construct() {
		parent::__construct($fieldtypes);
		$User = new DBTableRowUser();
		$this->setFieldTypeParams('user_type','enum','',true,'User Type');
		$this->setFieldAttribute('user_type', 'options', $User->getFieldAttribute('user_type', 'options'));
		$this->setFieldTypeParams('send_welcome_email','boolean','',false,'Send Welcome Email');		
		$this->setFieldTypeParams('user_id','varchar','',false,'User ID');
		$this->setFieldTypeParams('email','varchar','',false,'Send Email To Address');
		$this->setFieldAttribute('email', 'input_cols', '80');
		$this->setFieldTypeParams('message','text','',false,'Message');
	}
	
	public function getMessageText($message) {
		$User = new DBTableRowUser();
		$User->getRecordById($this->user_id);
		$url = 	Zend_Controller_Front::getInstance()->getRequest()->getScheme().'://'.Zend_Controller_Front::getInstance()->getRequest()->getHttpHost().Zend_Controller_Front::getInstance()->getRequest()->getBaseUrl().'/user/login';
		return "{$message}\n\nYour account has been set up and is ready for you to login.\n\nUsername: {$User->login_id}\n\nYou can login here:\n".$url;
	}

	public function formatInputTag($fieldname, $display_options=array()) {
		switch($fieldname) {
			case 'message' :
				return parent::formatInputTag($fieldname, $display_options).'<br />'.text_to_unwrappedhtml($this->getMessageText(''));
				break;
		}
		return parent::formatInputTag($fieldname, $display_options);
	}
	
	
}
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

class TableRowSettings extends TableRow {

	public function __construct() {
		parent::__construct();
		$this->setFieldTypeParams('delete_override','boolean','',false,'Delete Override','Temporarily re-enables the delete button for older records.  The delete button is usually disabled after '.(integer)(Zend_Registry::get('config')->delete_grace_in_sec/3600).' hours for archival records.');
		$this->setFieldTypeParams('edit_help','boolean','',false,'Edit Help Pages','Temporarily enables creation and editing of help pages.');
		$this->setFieldTypeParams('edit_comment_data','boolean','',false,'Edit Comment Data','Temporarily allows editing of itemobject_id in the edit comment view.');
		$this->setFieldTypeParams('use_any_typeversion_id','boolean','',false,'Change Type Version Id to Anything','Temporarily allows editing of typeversion_id to any possible value instead of the usual selection of different versions of the same typeobject.');
		$this->setFieldTypeParams('reoganize_data','boolean','',false,'Reorganize Data','Temporarily enable controls on the definitions page that lets you do bulk operations like combine components into a single field.  This also allows direct editing of current definitions.');
		$this->setFieldTypeParams('banner_text','varchar','',false,'Banner Text','Text to show at top of pages during the show period.');
		$this->setFieldAttribute('banner_text', 'input_cols', '100%');
		$this->setFieldTypeParams('banner_show_time','datetime','',true,'Show Time','When the banner should start showing.');
		$this->setFieldTypeParams('banner_hide_time','datetime','',true,'Hide Time','When it should go away.');
	}

	/**
	 * These are the booleans that simply set the admin::settings() temporary variables.
	 * @return multitype:string
	 */
	public function getSessionBooleanFieldNames() {
		$out = array('delete_override','edit_help','edit_comment_data','use_any_typeversion_id','reoganize_data');
		return $out;
	}

	/**
	 * These are fields that get stored in the globals table
	 * @return multitype:string
	 */
	public function getGlobalsFieldNames() {
		return array('banner_text','banner_show_time','banner_hide_time');
	}

	public function loadGlobals() {
		$current = getAllGlobals();
		foreach($this->getGlobalsFieldNames() as $fieldname) {
			$this->{$fieldname} = isset($current[$fieldname]) ? $current[$fieldname] : null;
		}
	}

	static function getBannerText() {
		$Obj = new self();
		$Obj->loadGlobals();
		if ($Obj->banner_text && $Obj->banner_show_time && $Obj->banner_hide_time && (strtotime($Obj->banner_show_time) <= script_time()) && (strtotime($Obj->banner_hide_time) >= script_time())) {
			return $Obj->banner_text;
		}
		return '';
	}

	public function getBannerError() {
		$errormsg = array();
		if (trim($this->banner_text)) {
			$this->validateFields(array('banner_text','banner_show_time','banner_hide_time'), $errormsg);
			if (count($errormsg)==0) {
				if (strtotime($this->banner_show_time) > strtotime($this->banner_hide_time)) $errormsg[] = 'Show time cannot be later than hide time.';
			}
		}
		return implode(' ',$errormsg);
	}
	
	public function getBannerStatus() {
		$msg = array();
		if (trim($this->banner_text)) {
			if (strtotime($this->banner_hide_time) < script_time()) $msg[] = 'Banner no longer showing.';
			if (strtotime($this->banner_show_time) > script_time()) $msg[] = 'Banner will be shown in '.time_difference_str(strtotime($this->banner_show_time) - script_time()).'.';
			if ($this->getBannerText()) $msg[] = 'Banner is showing for the next '.time_difference_str(strtotime($this->banner_hide_time) - script_time()).'.';
		}
		return implode(' ',$msg);
	}

}

?>

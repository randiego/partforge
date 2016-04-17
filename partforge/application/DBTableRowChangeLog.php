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

class DBTableRowChangeLog extends DBTableRow {
	
	public $change_code_defs = array(
			'DIO' => 'Deleted Item', 'DIV' => 'Deleted Item Version', 'AIO' => 'Added Item', 'CIV' => 'Changed Item Version', 'AIV' => 'Added Item Version',
			'ATO' => 'Added Definition', 'RTV' => 'Released Definition Version', 'OTO' => 'Obsoleted Definition', 'CTV' => 'Changed Definition Version', 'ATV' => 'Added Definition Version',
			'DTV' => 'Deleted Definition Version', 'DTO' => 'Deleted Definition',
			'AIC' => 'Added Item Comment', 'CIC' => 'Changed Item Comment', 'DIC' => 'Deleted Item Comment',
			'ATC' => 'Added Definition Comment', 'CTC' => 'Changed Definition Comment', 'DTC' => 'Deleted Definition Comment'
			);

	public function __construct() {
		parent::__construct('changelog');
		$this->user_id = $_SESSION['account']->user_id;
		$this->changed_on = time_to_mysqldatetime(script_time());
		$this->locator_prefix = '';
	}
	
	static private function saveItemEntry($itemobject_id, $itemversion_id, $locator_prefix, $change_code) {
		$Rec = new self();
		$Rec->itemobject_id = $itemobject_id;
		$Rec->itemversion_id = $itemversion_id;
		$Rec->locator_prefix = $locator_prefix;
		$Rec->change_code = $change_code;
		$Rec->save();		
	}
	
	static public function deletedItemObject($itemobject_id, $itemversion_id) {
		self::saveItemEntry($itemobject_id, $itemversion_id, '', 'DIO');
	}

	static public function deletedItemVersion($itemobject_id, $itemversion_id) {
		self::saveItemEntry($itemobject_id, $itemversion_id, 'io', 'DIV');
	}

	static public function addedItemObject($itemobject_id, $itemversion_id) {
		self::saveItemEntry($itemobject_id, $itemversion_id, 'io', 'AIO');
	}

	static public function changedItemVersion($itemobject_id, $itemversion_id) {
		self::saveItemEntry($itemobject_id, $itemversion_id, 'iv', 'CIV');
	}
	
	static public function addedItemVersion($itemobject_id, $itemversion_id) {
		self::saveItemEntry($itemobject_id, $itemversion_id, 'iv', 'AIV');
	}
	
	static public function addedItemComment($itemobject_id) {
		self::saveItemEntry($itemobject_id, null, 'io', 'AIC');
	}
	
	static public function changedItemComment($itemobject_id) {
		self::saveItemEntry($itemobject_id, null, 'io', 'CIC');
	}
	
	static public function deletedItemComment($itemobject_id) {
		self::saveItemEntry($itemobject_id, null, 'io', 'DIC');
	}
	
	static private function saveTypeEntry($typeobject_id, $typeversion_id, $locator_prefix, $change_code) {
		$Rec = new self();
		$Rec->typeobject_id = $typeobject_id;
		$Rec->typeversion_id = $typeversion_id;
		$Rec->locator_prefix = $locator_prefix;
		$Rec->change_code = $change_code;
		$Rec->save();
	}
	
	static public function addedTypeObject($typeobject_id, $typeversion_id) {
		self::saveTypeEntry($typeobject_id, $typeversion_id, 'to', 'ATO');
	}
	
	static public function releasedTypeVersion($typeobject_id, $typeversion_id) {
		self::saveTypeEntry($typeobject_id, $typeversion_id, 'tv', 'RTV');
	}
	
	static public function obsoletedTypeObject($typeobject_id, $typeversion_id) {
		self::saveTypeEntry($typeobject_id, $typeversion_id, 'to', 'OTO');
	}

	static public function changedTypeVersion($typeobject_id, $typeversion_id) {
		self::saveTypeEntry($typeobject_id, $typeversion_id, 'tv', 'CTV');
	}
	
	static public function addedTypeVersion($typeobject_id, $typeversion_id) {
		self::saveTypeEntry($typeobject_id, $typeversion_id, 'tv', 'ATV');
	}
	
	static public function deletedTypeVersion($typeobject_id, $typeversion_id) {
		self::saveTypeEntry($typeobject_id, $typeversion_id, 'to', 'DTV');
	}

	static public function deletedTypeObject($typeobject_id, $typeversion_id) {
		self::saveTypeEntry($typeobject_id, $typeversion_id, '', 'DTO');
	}
	
	static public function addedTypeComment($typeobject_id) {
		self::saveTypeEntry($typeobject_id, null, 'to', 'ATC');
	}
	
	static public function changedTypeComment($typeobject_id) {
		self::saveTypeEntry($typeobject_id, null, 'to', 'CTC');
	}
	
	static public function deletedTypeComment($typeobject_id) {
		self::saveTypeEntry($typeobject_id, null, 'to', 'DTC');
	}
	
}

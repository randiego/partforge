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

class DBTableRowChangeLog extends DBTableRow {
	
	public function __construct() {
		parent::__construct('changelog');
		$this->user_id = $_SESSION['account']->user_id && is_numeric($_SESSION['account']->user_id) ? $_SESSION['account']->user_id : null;
		$this->changed_on = time_to_mysqldatetime(script_time());
		$this->locator_prefix = '';
	}
	
	
	
	/**
	 * This is meant to be used as follows
	 * 
	 * 	$DBTableRowQuery = DBTableRowChangeLog::getNewQueryForApiOutput();
	 *  $DBTableRowQuery->setOrderByClause("ORDER BY changelog.changed_on desc");
	 *  $records = DbSchema::getInstance()->getRecords('',$DBTableRowQuery->getQuery()));
	 * 
	 * @return DBTableRowQuery
	 */
	static public function getNewQueryForApiOutput() {
		$DBTableRowQuery = new DBTableRowQuery(new self());
		
		$DBTableRowQuery->addJoinClause("LEFT JOIN user on user.user_id = changelog.user_id");
		$DBTableRowQuery->addJoinClause("LEFT JOIN partnumbercache ON partnumbercache.typeversion_id=changelog.desc_typeversion_id AND partnumbercache.partnumber_alias=IFNULL(changelog.desc_partnumber_alias,0)");
		$DBTableRowQuery->addJoinClause("LEFT JOIN typecategory on typecategory.typecategory_id = changelog.desc_typecategory_id");
		$DBTableRowQuery->addJoinClause("LEFT JOIN itemversion on itemversion.itemversion_id = changelog.desc_itemversion_id");
		$DBTableRowQuery->addJoinClause("LEFT JOIN itemobject on itemobject.itemobject_id = itemversion.itemobject_id");
		$DBTableRowQuery->addJoinClause("LEFT JOIN changecode on changecode.change_code = changelog.change_code");
		
		$DBTableRowQuery->setSelectFields("changelog.changelog_id, changelog.changed_on, changelog.user_id, user.login_id, TRIM(CONCAT(user.first_name,' ',user.last_name)) as full_name,
						changelog.change_code, (IF(changelog.change_code in ('AIR','AIP'),CONCAT(changecode.change_code_name,' ',changelog.desc_text) , changecode.change_code_name)) as change_description,
						typecategory.typecategory_name as type, partnumbercache.part_number as item_number, partnumbercache.part_description as item_name,
						IF(typecategory.is_user_procedure=1,null,itemversion.item_serial_number) as item_serial_number,
						IF(typecategory.is_user_procedure=1, itemobject.cached_first_ver_date, null) as procedure_date,
						changelog.desc_comment_id as comment_id,
						(IF(changelog.locator_prefix='iv', CONCAT('iv/',changelog.desc_itemversion_id), IF(changelog.locator_prefix='tv', CONCAT('tv/',changelog.desc_typeversion_id),''))) as locator,
						changelog.trigger_itemobject_id, changelog.trigger_typeobject_id");	
		return $DBTableRowQuery;	
	}
	
	
	static private function saveItemEntry($desc_itemobject_id, $desc_itemversion_id, $locator_prefix, $change_code, $comment_id = null, $comment_text = null, $user_id = null) {
		$Rec = new self();
		$Rec->locator_prefix = $locator_prefix;
		$Rec->change_code = $change_code;
		if (!is_null($user_id)) $Rec->user_id = $user_id;

		$IV = new DBTableRowItemVersion();
		$got_record = false;
		if (!is_null($desc_itemversion_id)) {
			$got_record = $IV->getRecordById($desc_itemversion_id);
			$Rec->desc_itemversion_id = $desc_itemversion_id;
		} else {
			$got_record = $IV->getCurrentRecordByObjectId($desc_itemobject_id);
			$Rec->desc_itemversion_id = $IV->itemversion_id;
		}
		
		if ($got_record) {
			if (!$Rec->user_id) $Rec->user_id = $IV->user_id;
			$Rec->desc_typeversion_id = $IV->typeversion_id;
			$Rec->desc_partnumber_alias = $IV->partnumber_alias;
			$Rec->desc_typecategory_id = $IV->typecategory_id;
			$Rec->desc_comment_id = $comment_id;
			$Rec->desc_text = strlen($comment_text) > 255 ? substr($comment_text,0,255) : $comment_text;
			$Rec->trigger_itemobject_id = $IV->itemobject_id;
			$Rec->trigger_typeobject_id = $IV->tv__typeobject_id;
			$Rec->save();
			DBTableRowChangeSubscription::triggerChangeNotice($Rec);
		}	
	}
	
	static public function deletedItemObject($itemobject_id, $typeversion_id, $partnumber_alias) {
		$Rec = new self();
		$Rec->locator_prefix = 'tv';
		$Rec->change_code = 'DIO';
		$Rec->desc_typeversion_id = $typeversion_id;
		$Rec->desc_partnumber_alias = $partnumber_alias;
		$Rec->trigger_itemobject_id = $itemobject_id;
		
		$TV = new DBTableRowTypeVersion();
		if ($TV->getRecordById($typeversion_id)) {
			$Rec->trigger_typeobject_id = $TV->typeobject_id;
			$Rec->desc_typecategory_id = $TV->typecategory_id;
			if (!$Rec->user_id) $Rec->user_id = $TV->user_id;
		}
		$Rec->save();
		DBTableRowChangeSubscription::triggerChangeNotice($Rec);
	}

	static public function deletedItemVersion($itemobject_id, $typeversion_id, $partnumber_alias, $new_itemversion_id) {
		$Rec = new self();
		$Rec->locator_prefix = 'iv';
		$Rec->change_code = 'DIV';
		$Rec->desc_typeversion_id = $typeversion_id;
		$Rec->desc_partnumber_alias = $partnumber_alias;
		$Rec->trigger_itemobject_id = $itemobject_id;
		$TV = new DBTableRowTypeVersion();
		if ($TV->getRecordById($typeversion_id)) {
			$Rec->trigger_typeobject_id = $TV->typeobject_id;
		}
		$IV = new DBTableRowItemVersion();
		if ($IV->getRecordById($new_itemversion_id)) {
			$Rec->desc_itemversion_id = $new_itemversion_id;
			$Rec->desc_typecategory_id = $IV->typecategory_id;
			if (!$Rec->user_id) $Rec->user_id = $IV->user_id;
		}
		
		$Rec->save();
		DBTableRowChangeSubscription::triggerChangeNotice($Rec);
	}

	static public function addedItemObject($itemobject_id, $itemversion_id) {
		self::saveItemEntry($itemobject_id, $itemversion_id, 'iv', 'AIO');
	}

	static public function changedItemVersion($itemobject_id, $itemversion_id) {
		self::saveItemEntry($itemobject_id, $itemversion_id, 'iv', 'CIV');
	}
	
	static public function addedItemVersion($itemobject_id, $itemversion_id) {
		self::saveItemEntry($itemobject_id, $itemversion_id, 'iv', 'AIV');
	}
	
	static public function addedItemComment($itemobject_id, $comment_id, $user_id = null) {
		self::saveItemEntry($itemobject_id, null, 'iv', 'AIC', $comment_id, null, $user_id);
	}
	
	static public function changedItemComment($itemobject_id, $comment_id, $user_id = null) {
		self::saveItemEntry($itemobject_id, null, 'iv', 'CIC', $comment_id, null, $user_id);
	}
	
	static public function deletedItemComment($itemobject_id, $comment_text) {
		self::saveItemEntry($itemobject_id, null, 'iv', 'DIC', null, $comment_text);
	}

	static public function addedItemReference($has_an_itemobject_id, $belongs_to_itemversion_id) {
		$text = '';
		$code = 'AIR';
		$user_id = null;
		$IV = new DBTableRowItemVersion();
		if ($IV->getRecordById($belongs_to_itemversion_id)) {
			$serial_identifier = empty($IV->item_serial_number) ? '' : ' ('.$IV->item_serial_number.')';
			$text = $IV->tv__type_description.$serial_identifier;
			$code = $IV->is_user_procedure ? 'AIP' : 'AIR';
			$user_id = $IV->user_id;
		}	
		self::saveItemEntry($has_an_itemobject_id, null, 'iv', $code, null, $text, $user_id);
	}
	
	static private function saveTypeEntry($desc_typeobject_id, $desc_typeversion_id, $locator_prefix, $change_code, $comment_id = null, $comment_text = null) {
		$Rec = new self();
		$Rec->locator_prefix = $locator_prefix;
		$Rec->change_code = $change_code;
	
		$TV = new DBTableRowTypeVersion();
		$got_record = false;
		if (!is_null($desc_typeversion_id)) {
			$got_record = $TV->getRecordById($desc_typeversion_id);
		} else {
			$got_record = $TV->getCurrentRecordByObjectId($desc_typeobject_id);
		}
	
		if ($got_record) {
			if (!$Rec->user_id) $Rec->user_id = $TV->user_id;
			$Rec->desc_typeversion_id = $TV->typeversion_id;
			$Rec->desc_typecategory_id = $TV->typecategory_id;
			$Rec->desc_comment_id = $comment_id;
			$Rec->desc_text = strlen($comment_text) > 255 ? substr($comment_text,0,255) : $comment_text;
			$Rec->trigger_typeobject_id = $TV->typeobject_id;
			$Rec->save();
			DBTableRowChangeSubscription::triggerChangeNotice($Rec);
		}
	}	
	
	static public function addedTypeObject($typeobject_id, $typeversion_id) {
		self::saveTypeEntry($typeobject_id, $typeversion_id, 'tv', 'ATO');
	}
	
	static public function releasedTypeVersion($typeobject_id, $typeversion_id) {
		self::saveTypeEntry($typeobject_id, $typeversion_id, 'tv', 'RTV');
	}
	
	static public function obsoletedTypeObject($typeobject_id, $typeversion_id) {
		self::saveTypeEntry($typeobject_id, $typeversion_id, 'tv', 'OTO');
	}

	static public function changedTypeVersion($typeobject_id, $typeversion_id) {
		self::saveTypeEntry($typeobject_id, $typeversion_id, 'tv', 'CTV');
	}
	
	static public function addedTypeVersion($typeobject_id, $typeversion_id) {
		self::saveTypeEntry($typeobject_id, $typeversion_id, 'tv', 'ATV');
	}
	
	static public function deletedTypeVersion($typeobject_id) {
		$Rec = new self();
		$Rec->locator_prefix = 'tv';
		$Rec->change_code = 'DTV';
		$TV = new DBTableRowTypeVersion();
		if ($TV->getCurrentRecordByObjectId($typeobject_id)) {
			$Rec->desc_typeversion_id = $TV->typeversion_id;
			$Rec->desc_typecategory_id = $TV->typecategory_id;
			if (!$Rec->user_id) $Rec->user_id = $TV->user_id;
		}
		$Rec->trigger_typeobject_id = $typeobject_id;
		$Rec->save();
		DBTableRowChangeSubscription::triggerChangeNotice($Rec);
	}

	static public function deletedTypeObject($typeobject_id, $text) {
		$Rec = new self();
		$Rec->locator_prefix = '';
		$Rec->change_code = 'DTO';
		$Rec->trigger_typeobject_id = $typeobject_id;
		$Rec->desc_text = strlen($text) > 255 ? substr($text,0,255) : $text;
		$Rec->save();
		DBTableRowChangeSubscription::triggerChangeNotice($Rec);
	}
	
	static public function addedTypeComment($typeobject_id, $comment_id) {
		self::saveTypeEntry($typeobject_id, null, 'tv', 'ATC', $comment_id);
	}
	
	static public function changedTypeComment($typeobject_id, $comment_id) {
		self::saveTypeEntry($typeobject_id, null, 'tv', 'CTC', $comment_id);
	}
	
	static public function deletedTypeComment($typeobject_id, $comment_text) {
		self::saveTypeEntry($typeobject_id, null, 'tv', 'DTC', null, $comment_text);
	}
	
}

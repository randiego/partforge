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

class TableRowReorgMoveComponent extends TableRow {

	public function __construct() {
		parent::__construct();
		$this->setFieldTypeParams('component_typeobject_id','enum','',true,'Component Type','The type of the component that will be moved.');
		$this->setFieldAttribute('component_typeobject_id', 'options', array());
		$this->setFieldTypeParams('typeversion_id','int','',true,'TypeVersion','');
		$this->setFieldTypeParams('sourcename','enum','',true,'Source Component Name','The name of the component to move FROM.');
		$this->setFieldAttribute('sourcename', 'options', array());
		$this->setFieldTypeParams('destname','enum','',true,'Destination Component Name','The name of the component to move TO.');
		$this->setFieldAttribute('destname', 'options', array());
	}
	
	public function getMoveableComponentTypes() {
		$records = self::getMoveableComponentRecords($this->typeversion_id);
		$bytypeobject = array();
		foreach($records as $record) {
			$bytypeobject[$record['can_have_typeobject_id']] = DBTableRowTypeVersion::formatPartNumberDescription($record['type_part_number'],$record['type_description']); 
		}
		return $bytypeobject;
	}
	
	public function getMoveableComponentNames($typeobject_id) {
		$records = self::getMoveableComponentRecords($this->typeversion_id);
		$component_names = array();
		foreach($records as $record) {
			if ($record['can_have_typeobject_id']==$typeobject_id) {
				$component_names[$record['component_name']] = $record['component_name'].' ('.$record['caption'].')';
			}
		}
		return $component_names;
	}	
	
	/**
	 * returns a list of component names and type part numbers from this typeversion that are candidates from moving components between.
	 * @param unknown_type $typeversion_id
	 */
	static function getMoveableComponentRecords($typeversion_id) {
		return DbSchema::getInstance()->getRecords('',"SELECT multicomponent.can_have_typeobject_id, typeversion.type_part_number, typeversion.type_description, typecomponent.* FROM (
				SELECT typecomponent_typeobject.can_have_typeobject_id, count(*) c
				FROM typecomponent
				LEFT JOIN typecomponent_typeobject ON typecomponent.typecomponent_id=typecomponent_typeobject.typecomponent_id
				WHERE typecomponent.belongs_to_typeversion_id='{$typeversion_id}'
				GROUP BY typecomponent_typeobject.can_have_typeobject_id HAVING c>1
		) as multicomponent
		LEFT JOIN typecomponent_typeobject ON typecomponent_typeobject.can_have_typeobject_id=multicomponent.can_have_typeobject_id
		LEFT JOIN typecomponent ON typecomponent.typecomponent_id=typecomponent_typeobject.typecomponent_id
		LEFT JOIN typeobject on typeobject.typeobject_id=typecomponent_typeobject.can_have_typeobject_id
		LEFT JOIN typeversion on typeversion.typeversion_id=typeobject.cached_current_typeversion_id
		WHERE typecomponent.belongs_to_typeversion_id='{$typeversion_id}'
		ORDER BY multicomponent.can_have_typeobject_id, typecomponent.component_name");
	}
	
	/**
	 * now if we want to move between one component to another we need to make sure the itemcomponents can handle it.
	 * return rows if the $sourcename of the source type ($sourcetypeobject_id) exists and also a component exists in the new name ($destname) slot.  
	 * This is an error and needs to be fixed manually before proceeding.
	 * @param unknown_type $typeversion_id
	 * @param unknown_type $sourcetypeobject_id
	 * @param unknown_type $sourcename
	 * @param unknown_type $destname
	 */
	public function getItemComponentsThatCannotBeMoved() {
		return DbSchema::getInstance()->getRecords('',"SELECT * FROM (
				SELECT *, (
				SELECT count(*)
				FROM itemcomponent
				INNER JOIN itemobject ON itemobject.itemobject_id=itemcomponent.has_an_itemobject_id
				INNER JOIN itemversion civ ON civ.itemversion_id=itemobject.cached_current_itemversion_id
				INNER JOIN typeversion ctv ON ctv.typeversion_id=civ.typeversion_id
				WHERE (itemcomponent.belongs_to_itemversion_id=itemversion.itemversion_id)
				AND (( (itemcomponent.component_name='{$this->sourcename}') AND (ctv.typeobject_id='{$this->component_typeobject_id}')) OR (itemcomponent.component_name='{$this->destname}') )
		) as cnt
				FROM itemversion
				WHERE itemversion.typeversion_id='{$this->typeversion_id}'
		) as movecandidates
				WHERE cnt>1");
	}

	/**
	 * this returns a list that contains a 1 in the sourcecount column if something will be moved. and 1 in the destcount 
	 * column is something is already in the destination slot.
	 */
	public function getPreviewOfMove() {
		return DbSchema::getInstance()->getRecords('',"
			SELECT *,
				(SELECT COUNT(*) FROM itemcomponent WHERE itemcomponent.belongs_to_itemversion_id=movecandidates.itemversion_id AND itemcomponent.component_name='{$this->sourcename}' ) as
				  sourcecount,
				(SELECT COUNT(*) FROM itemcomponent WHERE itemcomponent.belongs_to_itemversion_id=movecandidates.itemversion_id AND itemcomponent.component_name='{$this->destname}' ) as
				  destcount 
			FROM (
				SELECT *, (
				  SELECT count(*)
				  FROM itemcomponent
				  INNER JOIN itemobject ON itemobject.itemobject_id=itemcomponent.has_an_itemobject_id
				  INNER JOIN itemversion civ ON civ.itemversion_id=itemobject.cached_current_itemversion_id
				  INNER JOIN typeversion ctv ON ctv.typeversion_id=civ.typeversion_id
				  WHERE (itemcomponent.belongs_to_itemversion_id=itemversion.itemversion_id)
				  AND (( (itemcomponent.component_name='{$this->sourcename}') AND (ctv.typeobject_id='{$this->component_typeobject_id}')) OR (itemcomponent.component_name='{$this->destname}') )
		        ) as cnt
			    FROM itemversion
			    WHERE itemversion.typeversion_id='{$this->typeversion_id}'
		        ) as movecandidates
			WHERE cnt=1");
	}
	
	public function moveComponents() {
		
		// this type version
		$TV = new DBTableRowTypeVersion();
		$TV->getRecordById($this->typeversion_id);
		
		/*
		 * what io and ivs are we preparing to change?  We keep track of them separately so as not to repeat ourselves
		 */
		
		$previewrecords = $this->getPreviewOfMove();
		$itemobject_ids = array();
		$itemversion_ids = array();
		foreach($previewrecords as $previewrecord) {
			if ($previewrecord['sourcecount']==1) {
				if (!in_array($previewrecord['itemobject_id'],$itemobject_ids)) $itemobject_ids[] = $previewrecord['itemobject_id'];
				$itemversion_ids[] = $previewrecord['itemversion_id'];
			}
		}	

		/*
		 * Do the actual move
		 */
		
		$count_affected =  DbSchema::getInstance()->mysqlQuery("UPDATE itemcomponent INNER JOIN (
				SELECT *, 
				(SELECT COUNT(*) FROM itemcomponent WHERE itemcomponent.belongs_to_itemversion_id=movecandidates.itemversion_id AND itemcomponent.component_name='{$this->sourcename}' ) as 
				sourcecount,
				(SELECT COUNT(*) FROM itemcomponent WHERE itemcomponent.belongs_to_itemversion_id=movecandidates.itemversion_id AND itemcomponent.component_name='{$this->destname}' ) as 
				destcount FROM (
				  SELECT *, (
				    SELECT count(*) 
				    FROM itemcomponent 
				    INNER JOIN itemobject ON itemobject.itemobject_id=itemcomponent.has_an_itemobject_id
				    INNER JOIN itemversion civ ON civ.itemversion_id=itemobject.cached_current_itemversion_id
				    INNER JOIN typeversion ctv ON ctv.typeversion_id=civ.typeversion_id
				    WHERE (itemcomponent.belongs_to_itemversion_id=itemversion.itemversion_id) 
				         AND (( (itemcomponent.component_name='{$this->sourcename}') AND (ctv.typeobject_id='{$this->component_typeobject_id}')) OR (itemcomponent.component_name='{$this->destname}') )
				  ) as cnt
				  FROM itemversion
				  WHERE itemversion.typeversion_id='{$this->typeversion_id}'
				) as movecandidates 
				WHERE cnt=1
				) as trg ON trg.itemversion_id=itemcomponent.belongs_to_itemversion_id
				SET itemcomponent.component_name='{$this->destname}'
				WHERE trg.sourcecount=1 AND trg.destcount=0 AND itemcomponent.component_name='{$this->sourcename}'");

		/*
		 * Add a comment to each of the IO pages saying what happened.  We are changing multiple versions for each item
		 * so we do a little tricky stuff to only have one comment per itemobject_id.
		 */

		// first group up the component changes so we don't mention the same io/n over and over again.
		$fromiocode = array();
		foreach($itemversion_ids as $itemversion_id) {
			$IV = new DBTableRowItemVersion();
			$IV->getRecordById($itemversion_id);
			$source_caption = $IV->getFieldAttribute($this->sourcename, 'caption');
			$dest_caption = $IV->getFieldAttribute($this->destname, 'caption');
			$moved_io = $IV->{$this->destname};
			if (!isset($fromiocode[$IV->itemobject_id])) $fromiocode[$IV->itemobject_id] = array();
			$code = "[io/{$moved_io}]";
			$fromiocode[$IV->itemobject_id][$code] = $code;  // this makes sure we don't repeat any io codes
		}
		
		// need the field captions
		$digest = $TV->getLoadedTypeVersionDigest(false);
		$fieldtypes = $digest['fieldtypes'];
		$source_caption = $digest['fieldtypes'][$this->sourcename]['caption'];
		$dest_caption = $digest['fieldtypes'][$this->destname]['caption'];
		
		// post one comment per itemobject affected
		foreach($itemobject_ids as $itemobject_id) {
			$IV = new DBTableRowItemVersion();
			$IV->getCurrentRecordByObjectId($itemobject_id);
			$Comment = new DBTableRowComment();
			$Comment->itemobject_id = $itemobject_id;
			$Comment->comment_text = "[AUTOPOSTED]: A bulk move operation was performed that moved the component ".implode(', ',$fromiocode[$itemobject_id])." from the '{$this->sourcename} ({$source_caption})' field to the '{$this->destname} ({$dest_caption})' field.";
			$Comment->save();			
		}
		
		/*
		 * add a single comment to the definition comments that summarizes all of the changes
		 */
		$io_links = array();
		foreach($itemobject_ids as $itemobject_id) {
			$io_links[] = "[io/{$itemobject_id}]";
		}
		
		$TypeComment = new DBTableRowComment();
		$TypeComment->typeobject_id = $TV->typeobject_id;
		$TypeComment->comment_text = "[AUTOPOSTED]: A bulk component move operation was performed on version '{$TV->getCoreDescription()}' that moved components from the '{$this->sourcename}' field to the '{$this->destname}' field on the following items: ".implode(', ',$io_links);
		$TypeComment->save();
		
		return $count_affected;
	}	

}

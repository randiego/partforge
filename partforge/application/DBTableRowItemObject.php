<?php
/**
 *
 * PartForge Enterprise Groupware for recording parts and assemblies by serial number and version along with associated test data and comments.
 *
 * Copyright (C) 2013-2018 Randall C. Black <randy@blacksdesign.com>
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

class DBTableRowItemObject extends DBTableRow {

	public function __construct($ignore_joins=false,$parent_index=null) {
		parent::__construct('itemobject',$ignore_joins,$parent_index);
		//      $this->user_id = $_SESSION['account']->user_id;
	}

	/**
	 * This updates the fields itemobject.cached_last_comment_date and itemobject.cached_last_comment_person
	 * It is used strictly for performance.   It should be called when a comment is added, edited, deleted.
	 * If $itemobject_id is set then is only updates the cached fields for that specific itemobject_id.
	 */
	static public function updateCachedLastCommentFields($itemobject_id=null) {
		
		// if the itemobject is specified then we only create entries for this specific itemobject_id for the last_comment_tbl.
		$inner_group_by = is_null($itemobject_id) ? "GROUP BY bb_c.itemobject_id" : "and bb_c.itemobject_id='{$itemobject_id}'";
		
		// if the itemobject is specified then we have to be careful to only overwrite for the specified itemobject_id rather than all of them.
		$final_where = is_null($itemobject_id) ? '' : "WHERE cc_io.itemobject_id='{$itemobject_id}'";
		
		DbSchema::getInstance()->mysqlQuery("
			UPDATE itemobject as cc_io
				# Table of all the last comment for each itemobject_id
				LEFT JOIN (
					SELECT bb_c.itemobject_id as bb_io, bb_c.comment_id as bb_comment_id, bb_c.comment_added as bb_comment_added, bb_user.first_name as bb_user_first_name, bb_user.last_name as bb_user_last_name
					FROM comment as bb_c
					LEFT JOIN user as bb_user on bb_user.user_id=bb_c.user_id
					LEFT JOIN (
					  SELECT aa_c.itemobject_id as aa_io, max(aa_c.comment_added) as max_comment_added FROM comment as aa_c GROUP BY aa_c.itemobject_id
					  ) as max_comment_dates on max_comment_dates.aa_io=bb_c.itemobject_id
				    WHERE max_comment_dates.max_comment_added=bb_c.comment_added
					{$inner_group_by}
						) as last_comment_tbl ON cc_io.itemobject_id=last_comment_tbl.bb_io
		
			SET cc_io.cached_last_comment_date=last_comment_tbl.bb_comment_added,
				cc_io.cached_last_comment_person=TRIM(CONCAT(last_comment_tbl.bb_user_first_name,' ',last_comment_tbl.bb_user_last_name))
			{$final_where}
		");
	}
	
	/**
	 * Updates the fields itemobject.cached_last_ref_date and itemobject.cached_last_ref_person.
	 * It should be called when a component is added or edited.  If $itemobject_ids are specified
	 * then it updates the cache fields for only the specified $itemobject_ids.  It is the responsibility
	 * of the caller to construct a list of all the itemobject_ids that might need updating.
	 * If called without any itemobjects than it updates globally.
	 * 
	 * Generally when you call this, you would build a list of itemobjects that are components of item
	 * you are editing.  You do this for both before and after cases and merge together.
	 * 
	 */
	static public function updateCachedLastReferenceFields($itemobject_ids=null) {
		if (is_array($itemobject_ids)) {
			// We don't do anything if we have an empty array.  that means there were no refereces up to update.
			if (count($itemobject_ids)==0) return;
			$itemobject_ids = array_unique($itemobject_ids); // don't repeat ourselves
			$where1 = " WHERE cc_io.itemobject_id IN (".implode(',',$itemobject_ids).")";
			$where2 = " WHERE aa_ic.has_an_itemobject_id IN (".implode(',',$itemobject_ids).")";
		} else {
			$where1 = '';
			$where2 = '';
		}

		DbSchema::getInstance()->mysqlQuery("
			UPDATE itemobject as cc_io
				# Table of all the last referenced itemversion info for each itemobject_id
				LEFT JOIN (
					SELECT bb_iv.itemobject_id as bb_iv_io,  bb_ic.has_an_itemobject_id as bb_io, bb_iv.effective_date as bb_iv_effective_date, bb_user.first_name as bb_iv_user_first_name, bb_user.last_name as bb_iv_user_last_name
					  FROM itemcomponent as bb_ic
					  LEFT JOIN itemversion as bb_iv ON bb_iv.itemversion_id=bb_ic.belongs_to_itemversion_id
					  LEFT JOIN user as bb_user on bb_user.user_id=bb_iv.user_id
					  LEFT JOIN (
						  SELECT aa_ic.has_an_itemobject_id as aa_io, max(aa_iv.effective_date) as max_effective_date
						  FROM itemcomponent as aa_ic
						  LEFT JOIN itemversion as aa_iv ON aa_iv.itemversion_id=aa_ic.belongs_to_itemversion_id
						  {$where2}
						  GROUP BY aa_ic.has_an_itemobject_id
						  ) as max_ref_dates on max_ref_dates.aa_io=bb_ic.has_an_itemobject_id
					  WHERE max_ref_dates.max_effective_date=bb_iv.effective_date
					  GROUP BY bb_ic.has_an_itemobject_id
						) as last_ref_tbl ON cc_io.itemobject_id=last_ref_tbl.bb_io
			
			SET cc_io.cached_last_ref_date=last_ref_tbl.bb_iv_effective_date,
				cc_io.cached_last_ref_person=TRIM(CONCAT(last_ref_tbl.bb_iv_user_first_name,' ',last_ref_tbl.bb_iv_user_last_name))
			{$where1}	
		");
	}	

	/**
	 * Updates the fields itemobject.cached_first_ver_date and itemobject.cached_created_by
	 * it should be called when a new version is saved.  This query is structured such that
	 * it assumes there exists a value for each itemobject_id.  None are null.  This lets us use
	 * the inner join, meaning we will never assign a null value.  If $itemobject_id is specified
	 * it will only update the cache value for the specific io.  Otherwise, all are updated.
	 */
	static public function updateCachedCreatedOnFields($itemobject_id=null) {
		$where1 = is_null($itemobject_id) ? '' : " WHERE aaa_iv.itemobject_id='{$itemobject_id}'";
		DbSchema::getInstance()->mysqlQuery("
			UPDATE itemobject as cc_io,
				# Table of all the first version itemversion info for each itemobject_id
				 (
						SELECT bb_iv.itemobject_id as bb_io, bb_iv.effective_date as bb_iv_effective_date, bb_user.first_name as bb_iv_user_first_name, bb_user.last_name as bb_iv_user_last_name
						  FROM itemversion as bb_iv
						  LEFT JOIN user as bb_user on bb_user.user_id=bb_iv.user_id
						  LEFT JOIN (
							  SELECT aaa_iv.itemobject_id as aa_io, min(aaa_iv.effective_date) as min_effective_date
							  FROM itemversion as aaa_iv
							  {$where1}
							  GROUP BY aaa_iv.itemobject_id
							  ) as min_ref_dates on min_ref_dates.aa_io=bb_iv.itemobject_id
						  WHERE min_ref_dates.min_effective_date=bb_iv.effective_date
						  GROUP BY bb_iv.itemobject_id
						) as first_ver_tbl
			
			SET cc_io.cached_first_ver_date=first_ver_tbl.bb_iv_effective_date,
				cc_io.cached_created_by=TRIM(CONCAT(first_ver_tbl.bb_iv_user_first_name,' ',first_ver_tbl.bb_iv_user_last_name))
			WHERE cc_io.itemobject_id=first_ver_tbl.bb_io
		");
	}
	
	/**
	 * returns a nested dictionary of fields given a specific itemobject_id and optionally a date (to know which version should be retrieved)
	 * 
	 * @param int $itemobject_id
	 * @param date string $effective_date if specified, locate the version that was active at or before this date
	 * @param int $max_depth number of levels to descend
	 * @param int $level the current level of recursion
	 * @param array of int $parents breadcrumbs
	 * @return dictionary of fields values, with subfields expacted as their own dictionaries.
	 */
	static function getItemObjectFullNestedArray($itemobject_id,$effective_date=null, $max_depth=null,$level=0,$parents = array()) {
		$out = array();
		$ItemVersion = new DBTableRowItemVersion();
		if ($ItemVersion->getCurrentRecordByObjectId($itemobject_id,$effective_date)) {
				
			foreach($ItemVersion->getExportFieldTypes() as $fieldname => $fieldtype) {
				if (isset($ItemVersion->{$fieldname})) {
					if ($fieldtype['type']=='component') {
						if ((is_null($max_depth) || ($max_depth >$level)) && !in_array($ItemVersion->{$fieldname},$parents)) {
							$new_parents = array_merge($parents, array($itemobject_id));
							$out[$fieldname] = self::getItemObjectFullNestedArray($ItemVersion->{$fieldname},$effective_date,$max_depth,$level+1,$new_parents);
						} else {
							$value_array = $ItemVersion->getComponentValueAsArray($fieldname);
							$out[$fieldname] = $value_array[$ItemVersion->{$fieldname}];							
						}
					} else {
						$out[$fieldname] = $ItemVersion->{$fieldname};
					}
				}
			}
		}
		return $out;
	}	

	/**
	 * returns a nested dictionary of fields given a specific itemversion_id.  Unlike getItemObjectFullNestedArray this starts with the
	 * specified itemVERSION_id instead of inferring the version from the itemobject_id and the effective_date.  For sub components, though,
	 * it will recurse into the getItemObjectFullNestedArray function and use effective dates for determining versions of the components.
	 * 
	 * @param int $itemversion_id
	 * @param int $max_depth number of levels to descend
	 * @param int $level the current level of recursion
	 * @param array of int $parents breadcrumbs
	 * @return dictionary of fields values, with subfields expacted as their own dictionaries.
	 */

	static function getItemVersionFullNestedArray($itemversion_id,$max_depth=null,$level=0,$parents = array()) {
		$out = array();
		$ItemVersion = new DBTableRowItemVersion();
		if ($ItemVersion->getRecordById($itemversion_id)) {
	
			foreach($ItemVersion->getExportFieldTypes() as $fieldname => $fieldtype) {
				if (isset($ItemVersion->{$fieldname})) {
					if ($fieldtype['type']=='component') {
						if ((is_null($max_depth) || ($max_depth >$level)) && !in_array($ItemVersion->{$fieldname},$parents)) {
							$new_parents = array_merge($parents, array($ItemVersion->itemobject_id));
							// we then recurse into getItemObjectFullNestedArray to fetch all the components using effective_dates to determine the version
							$out[$fieldname] = self::getItemObjectFullNestedArray($ItemVersion->{$fieldname},$ItemVersion->effective_date,$max_depth,$level+1,$new_parents);
						} else {
							$value_array = $ItemVersion->getComponentValueAsArray($fieldname);
							$out[$fieldname] = $value_array[$ItemVersion->{$fieldname}];
						}
					} else {
						$out[$fieldname] = $ItemVersion->{$fieldname};
					}
				}
			}
		}
		return $out;
	}
	
	
}

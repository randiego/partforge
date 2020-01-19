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

abstract class ReportDataWithCategory extends ReportData {

	public $category_array = array();
	public $pref_view_category_name = 'pref_view_category';
	protected $dbtable;
	protected $last_select_class = 'last_select';
	
	public function __construct($tablename) {
		$this->dbtable = DbSchema::getInstance()->dbTableRowObjectFactory($tablename,false);
	}
	
	/**
	 * process records to fill out extra fields and do normal format conversion for export
	 * @param array $queryvars
	 * @param string $searchstr
	 * @param string $limitstr
	 * @return array of records:
	 */
	public function get_export_detail_records(&$queryvars, $searchstr,$limitstr) {
		$records = array();
		foreach($this->get_records($queryvars, $searchstr,$limitstr) as $index => $record) {
			$out_rec = array();
			if ($this->make_export_detail($queryvars, $record, $out_rec)) {
				$records[$index] = $out_rec;
			}
		}		
		return $records;
	}

	// ensures returned category is reasonable and if not sets it to a good default
	public function ensure_category($category) {
		if (count($this->category_array)>0) {
			if (!array_key_exists($category,$this->category_array)) {
				$categories = array_keys($this->category_array);
				$category = reset($categories); // first category
			}
		}
		return $category;
	}

	protected function category_choices_array($role) {
	}
	
	/**
	 * Return an array that is basically equivalent to the doing a CSV export. 
	 * @param boolean $use_internal_names true if we use internal index names to identify the fields, otherwise we use the CSV captions.
	 * @return array of records
	 */
	public function getCSVRecordsAsArray($use_internal_names=false) {

		// use the appropriate keys (fieldname or caption)
		$csv_fields = $this->csvfields;
		if ($use_internal_names) $csv_fields = array_combine(array_keys($csv_fields), array_keys($csv_fields));
		
		$records = $this->get_records(array(), '','');
		$records_out = array();
		foreach($records as $index => $record) {
			$out_rec = array();
			if ($this->make_export_detail(array(), $record, $out_rec)) {
				$records_out[$index] = array();
				foreach($csv_fields as $field => $caption) {
					$records_out[$index][$caption] = $out_rec[$field];
				}
			}
		}	
		
		return $records_out;
	}
	
}
?>

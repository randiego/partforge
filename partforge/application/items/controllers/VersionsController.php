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

class Items_VersionsController extends RestControllerActionAbstract
{

	/*
	 * GET /items/versions
	*
	* Return a list of all versions of all items, subject to query variables.  It will return itemversion_id.  Additional information
	* will need to be queried by calls to /items/versions/n.
	*
	* if typeversion_id is specified, all itemversion_id are returned with that specific typeversion_id.  If typeobject_id is specified
	* then those are returned.  If itemobject_id is specified, then all the versions for that specific itemobject_id is returned.
	* if the parameter fields=field1,field2,etc. are specified, them we return an array with the itemversion_id as the key and
	* a sub array with each of the fields given.
	*/
	public function indexAction() 
	{
		$this->noOp();
	}

	/*
	 * GET /items/versions/12
	*
	* Return the specified version of the itemobject_id specified by the parameter.  The form that is returned is by default
	* a nested list of all fields, components drilled all the way down.  Unlike /items/objects/ api call, this returns the
	* version of the component objects that were active at the time of the effective date of this version.
	* main parameter is itemobject_id
	*
	* Input:
	*   id = the itemversion_id of the object being retrieved. (required)
	*   fmt=nested (default) shows a JSON array in fully nested dictionary format
	*   fmt=sheet gives output in JSON format using spreadsheet names
	*   fmt=pdf generates and outputs a pdf file
	* 	use_internal_names=1 means that each field will  be indexed by the internal object name when sheet fmt is used, otherwise we use the captions shown in the Save To Spreadsheet function
	*   max_depth=n is the maximum recursion level for building return query.  default is blank (infinite), 0 stops after first level.
	*   Instead of returning an object for a component, it will return the object's serial number string.
	*
	*/

	public function getAction()

	{
		$use_internal_names = isset($this->params['use_internal_names']) && ($this->params['use_internal_names']=='1');
		$fmt = isset($this->params['fmt']) ? $this->params['fmt'] : 'nested';
		$max_depth = isset($this->params['max_depth']) ? (is_numeric($this->params['max_depth']) ? $this->params['max_depth'] : null) : null;
		$ItemVersion = DbSchema::getInstance()->dbTableRowObjectFactory('itemversion');
		if ($ItemVersion->getRecordById($this->params['id'])) {
			switch ($fmt) {
				case 'pdf':
					$Pdf = new ItemViewPDF();
					$Pdf->dbtable = $ItemVersion;
					$Pdf->buildDocument($this->params);
					$Pdf->Output(make_filename_safe('ItemView_'.$ItemVersion->part_number.'_'.$ItemVersion->item_serial_number.'.pdf'),'D');
					exit;
				case 'nested':
					foreach(DBTableRowItemObject::getItemObjectFullNestedArray($ItemVersion->itemobject_id,$ItemVersion->effective_date,$max_depth,0) as $fieldname => $value) {
						$this->view->{$fieldname} = $value;
					}
					break;
				case 'sheet':
					$ReportData = new ReportDataItemListView(true,true,$ItemVersion->is_user_procedure, false, $ItemVersion->tv__typeobject_id, $ItemVersion->itemversion_id);
					$records = $ReportData->getCSVRecordsAsArray($use_internal_names);
					if (count($records)==1) {
						$record = reset($records);
						foreach($record as $fieldname => $value) {
							$this->view->{$fieldname} = $value;
						}
					}
			}
		}
	}

	/*
	 * POST /items/versions?format=json
	*
	* Create a new version of an item.  This is based on the import verb NewVersion
	* as defined in ImportStrategyObjects::storeObjectPerImportRules().
	* The idea is that you need to give it enough information to locate the existing
	* part record (itemversion_id, itemobject_id, or item_serial_number).  It will attempt
	* retrieve the record using this and then alter the fields that we are specifying.
	* only a serial number is give, then we must specify the typeversion_id or typeobject_id.
	*
	* Input (at minimum):
	* 	user_id login_id of the user
	* 	itemversion_id or itemobject_id  need to specify one of these.
	* 	effective_date (this needs to be filled out.)
	* 	item_serial_number
	*   typeversion_id or typeobject_id
	*
	* Output (json):
	* 	errormessages = []
	* 	itemversion_id
	* 	itemobject_id
	*  itemview_url
	*
	*/

	public function postAction()
	{
		$record = $this->params;
		$errormsg = array();

		try {

			if (isset($record['id'])) {
				$errormsg[] = 'The parameters for this call cannot contain an ID parameter.';
			}
				
			$typeversion_id = null;
			if (isset($record['typeversion_id'])) {
				$typeversion_id = $record['typeversion_id'];
				unset($record['typeversion_id']);
				unset($record['typeobject_id']);
			} else if (isset($record['typeobject_id'])) {
				$TypeObject = new DBTableRowTypeObject();
				if ($TypeObject->getRecordById($record['typeobject_id'])) {
					$typeversion_id = $TypeObject->cached_current_typeversion_id;
				}
				unset($record['typeobject_id']);
			}
			if (!$typeversion_id) {
				$errormsg[] = 'You must enter either a valid typeversion_id or typeobject_id to create a new record.';
			}
				

			if (empty($errormsg)) {
				// contruct a list of columns we will import
				$EditRow = DbSchema::getInstance()->dbTableRowObjectFactory('itemversion',false);
				// this needs to be e
				$EditRow->typeversion_id = $typeversion_id;
				$curr_field_to_columns = array();
				foreach($EditRow->getSaveFieldNames() as $fieldname) {
					if (isset($record[$fieldname])) $curr_field_to_columns[$fieldname] = $fieldname;
				}

				// establish defaults here
					
				$itemversion_id = null;
				if (isset($curr_field_to_columns['itemversion_id']) && is_numeric($record[$curr_field_to_columns['itemversion_id']])) $itemversion_id = $record[$curr_field_to_columns['itemversion_id']];
				unset($curr_field_to_columns['itemversion_id']);
					
				$itemobject_id = null;
				if (isset($curr_field_to_columns['itemobject_id']) && is_numeric($record[$curr_field_to_columns['itemobject_id']])) $itemobject_id = $record[$curr_field_to_columns['itemobject_id']];
				unset($curr_field_to_columns['itemobject_id']);
					
				$effective_date = null;
				if (isset($curr_field_to_columns['effective_date']) && is_valid_datetime($record[$curr_field_to_columns['effective_date']])) $effective_date = $record[$curr_field_to_columns['effective_date']];
				unset($curr_field_to_columns['effective_date']);
					
				$user_id = null;
				if (isset($curr_field_to_columns['user_id'])) {
						
					$user_records_by_id = DbSchema::getInstance()->getRecords('user_id',"SELECT * FROM user");
					$user_records_by_loginid = array();
					foreach($user_records_by_id as $user_id => $user_record) {
						$user_records_by_loginid[$user_record['login_id']] = $user_record;
					}

						
					if (is_numeric($record[$curr_field_to_columns['user_id']])) {
						$user_id = $record[$curr_field_to_columns['user_id']];
						// if not numeric, but is a valid login_id, then use that user instead
					} else if (isset($user_records_by_loginid[$record[$curr_field_to_columns['user_id']]])) {
						$user_id = $user_records_by_loginid[$record[$curr_field_to_columns['user_id']]]['user_id'];
					} else {
						$errormsg[] = 'User ID not found in user table: '.$record[$curr_field_to_columns['user_id']];
					}
					unset($curr_field_to_columns['user_id']);
				}

				if (empty($errormsg)) {
					$outitemversion_id = null;
					ImportStrategyObjects::storeObjectPerImportRules('NewVersion', $record, $curr_field_to_columns, $typeversion_id, $itemversion_id, $itemobject_id, $user_id, $effective_date, $simlulate_only, $errormsg, $outitemversion_id);

					$this->view->itemversion_id = $outitemversion_id;
					if (is_numeric($outitemversion_id)) {
						$ItemVersion = new DBTableRowItemVersion();
						if ($ItemVersion->getRecordById($outitemversion_id)) {
							$this->view->itemview_url = $ItemVersion->absoluteUrl();
							$this->view->itemobject_id = $ItemVersion->itemobject_id;
						}
					}
				}

			}
				
		} catch (Exception $e) {
			$errormsg[] = $e->getMessage();
		}

		$this->view->errormessages = $errormsg;
	}

	public function putAction()
	{
		$this->noOp();
	}

	public function deleteAction()
	{
		$this->noOp();
	}
}
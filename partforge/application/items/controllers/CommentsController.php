<?php 
/**
 *
 * PartForge Enterprise Groupware for recording parts and assemblies by serial number and version along with associated test data and comments.
 *
 * Copyright (C) 2013-2015 Randall C. Black <randy@blacksdesign.com>
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

class Items_CommentsController extends RestControllerActionAbstract
{
	
	
	/**
	 * GET /items/comments
	 * 
	 * Return a list of comments, subject to query variables.  For example,
	 * /items/comments?itemobject_id=12 will return a list of comment_ids belonging to the indicated itemobject.
	 * /items/comments?typeobject_id=53 will return a list of comment_ids belonging to the indicated typeobject.
	 * Adding &get_info=1 will return an array of json objects with detailed comment text.
	 */
	public function indexAction() {
		
		$select = "SELECT comment.comment_id
				FROM comment
				LEFT JOIN user ON user.user_id=comment.user_id
				LEFT JOIN user as proxy_user ON proxy_user.user_id=comment.proxy_user_id
				LEFT JOIN itemobject ON itemobject.itemobject_id=comment.itemobject_id
				LEFT JOIN itemversion ON itemversion.itemversion_id=itemobject.cached_current_itemversion_id
				LEFT JOIN typeversion ON typeversion.typeversion_id=itemversion.typeversion_id";
		
		$itemobject_id = isset($this->params['itemobject_id']) ? (is_numeric($this->params['itemobject_id']) ? addslashes($this->params['itemobject_id']) : null) : null;
		$typeobject_id = isset($this->params['typeobject_id']) ? (is_numeric($this->params['typeobject_id']) ? addslashes($this->params['typeobject_id']) : null) : null;
		$get_info = isset($this->params['get_info']) && $this->params['get_info']  ? true : false;
		if (!is_null($itemobject_id) || !is_null($typeobject_id)) {
			$where = array();
			if (!is_null($itemobject_id)) $where[] = "(comment.itemobject_id='{$itemobject_id}')";
			if (!is_null($typeobject_id)) $where[] = "(typeversion.typeobject_id='{$typeobject_id}')";
			$records = DbSchema::getInstance()->getRecords('',"{$select} WHERE ".implode(' AND ',$where)." ORDER BY comment.comment_added");
			if ($get_info) {
				$this->view->comments = array();
				$Comment = new DBTableRowComment();
				foreach($records as $record) {
					if ($Comment->getRecordById($record['comment_id'])) $this->view->comments[] = $Comment->getArray();
				}
			} else {
				$this->view->comment_ids = extract_column($records, 'comment_id');
			}
		}
	}
	
	/**
	 * GET /items/comments/N  where N is comment_id
	 * 
	 * @see Zend_Rest_Controller::getAction()
	 */
	public function getAction()
	
	{
		$Comment = new DBTableRowComment();
		if ($Comment->getRecordById($this->params['id'])) {
			$this->view->comment = $Comment->getArray();
		} else {
			$this->view->errormessages = 'comment not found.';
		}
	}
	
	/*
	 * POST /items/comments?format=json
	 * 
	 * Create a new comment from posted data.
	 * 
	 * Input (at minimum):
	 * 	user_id
	 * 	itemobject_id or itemversion_id
	 * 	comment_added (optional)
	 * 	comment_text  (this is optional because we want to be able to put in empty comments to store attachments)
	 * 
	 * Output (json):
	 * 	errormessages = []
	 * 	comment_id
	 * 
	 */
	public function postAction()
	{
		$errormsg = array();
		$record = $this->params;
		
		try {
		
			if (isset($record['id'])) {
				$errormsg[] = 'The parameters for this call cannot contain an ID parameter.';
			}
			
			$itemobject_id = null;
			if (isset($record['itemobject_id'])) {
				$ItemObject = new DBTableRowItemObject();
				if ($ItemObject->getRecordById($record['itemobject_id'])) {
					$itemobject_id = $record['itemobject_id'];
				} else {
					$errormsg[] = 'itemobject_id ('.$record['itemobject_id'].') cannot be found.';
				}
				unset($record['itemversion_id']);
				unset($record['itemobject_id']);
			} else if (isset($record['itemversion_id'])) {
				$ItemVersion = new DBTableRowItemVersion();
				if ($ItemVersion->getRecordById($record['itemversion_id'])) {
					$itemobject_id = $ItemVersion->itemobject_id;
				}
				unset($record['itemversion_id']);
			}
			if (!$itemobject_id) {
				$errormsg[] = 'You must enter either a valid itemversion_id or itemobject_id to create a new comment.';
			}
			// put it back into the record
			$record['itemobject_id'] = $itemobject_id;

			
			$UserObject = new DBTableRowUser();
			if (!isset($record['user_id'])) {
				$errormsg[] = 'You must provide a user_id.';
			} else if (is_numeric($record['user_id'])) {	
				if (!$UserObject->getRecordById($record['user_id'])) {
					$errormsg[] = 'user_id ('.$record['user_id'].') cannot be found.';
				}
			} else if ($UserObject->getRecordByLoginID($record['user_id'])) {
				$record['user_id'] = $UserObject->user_id;
			} else {
				$errormsg[] = 'User ID ('.$record['user_id'].') not found.';
			}
				
			if (empty($errormsg)) {
				$EditRow = DbSchema::getInstance()->dbTableRowObjectFactory('comment');
//				$EditRow->itemobject_id = $record['itemobject_id'];
//				$EditRow->comment_text = $record['comment_text'];
				$EditRow->assignFromAjaxPost($record);
				$EditRow->validateFields($EditRow->getSaveFieldNames(),$errormsg);
			}
			
			if (count($errormsg)==0) {
				$EditRow->save($EditRow->getSaveFieldNames());
				$this->view->comment_id = $EditRow->comment_id;
			}	
			
		} catch (Exception $e) {
			$errormsg[] = $e->getMessage();
		}	
		
		$this->view->errormessages = $errormsg;		
	}
	
	/*
	 * PUT /items/objects/:id?format=json
	 * 
	 * Create a new version of an item specified by the itemobject_id = id
	 * 
	 * Input (at minimum):
	 * 	user_id
	 * 	itemobject_id
	 * 	effective_date
	 * 	item_serial_number
	 * 
	 * Output (json):
	 * 	errormessages = []
	 * 	itemversion_id
	 * 
	 */

	public function putAction()
	{
		$this->noOp();
	}
	
	public function deleteAction()
	{
		$this->noOp();
	}	
}
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

    class DBTableRowComment extends DBTableRow {
        
        public function __construct() {
            parent::__construct('comment');
            $this->comment_added = time_to_mysqldatetime(script_time());
            $this->user_id = $_SESSION['account']->user_id;
            $this->proxy_user_id = ($_SESSION['account']->getRole()=='DataTerminal') ? $_SESSION['account']->user_id : LOGGED_IN_USER_IS_CREATOR;
            $this->document_ids = array();
        }
        
        /*
         * This kinda sorta works but was mostly pasted.
         */
		static public function getListOfCommentActions($comment_added,$user_id,$proxy_user_id) {
            $config = Zend_Registry::get('config');
            $actions = array();
            
            $can_edit = false;
            $can_deleteblocked = false;
            $can_delete = false;
            $time = strtotime($comment_added);
            $inside_grace_period = strtotime($comment_added) + $config->delete_grace_in_sec > script_time();
            if (($_SESSION['account']->getRole() == 'Admin')) {
            	$can_edit = true;
            	
            	if ($inside_grace_period) {
            		$can_delete = true;
            		$can_deleteblocked = false;
            	} else { // outside of grace zone
            		if (AdminSettings::getInstance()->delete_override) {
            			$can_delete = true;
            			$can_deleteblocked = false;
            		} else {
            			$can_delete = false;
            			$can_deleteblocked = true;
            		}
            	}
            
            	
            } else { // not an admin
            	if ((Zend_Registry::get('customAcl')->isAllowed($_SESSION['account']->getRole(),'table:comment','edit')
            		&& ($_SESSION['account']->user_id == $user_id) && $inside_grace_period)) {
            		$can_edit = true;
            		$can_delete = true;
            	} else if (Zend_Registry::get('customAcl')->isAllowed($_SESSION['account']->getRole(),'table:comment','edit')
            		&& ($_SESSION['account']->user_id == $proxy_user_id) && ($_SESSION['account']->getRole() == 'DataTerminal') && $inside_grace_period) {
            		$can_edit = true;
            		$can_delete = true;
            	}
            }
            
            if ($can_edit) $actions['commenteditview'] = array('buttonname' => 'Edit', 'privilege' => 'view');
            if ($can_delete) $actions['delete'] = array('buttonname' => 'Delete', 'privilege' => 'delete', 'confirm' => 'Are you sure you want to delete this?');
            if ($can_deleteblocked) $actions['delete'] = array('buttonname' => 'Delete (Blocked)', 'privilege' => 'delete', 'alert' => 'This record is older than '.(integer)($config->delete_grace_in_sec/3600).' hours.  If you want to delete it, you must go to the Settings menu and enable Delete Override.');

            return $actions;
        }        
        
        /** return the editing links for a dependent row
         * 
         * @param object $navigator
         * @param string $return_url
         * @param integer $itemobject_id
         * @param integer $comment_id
         * @param unknown_type $comments_added
         * @param integer $user_id
         * @return array of link structures
         */
		static public function commentEditLinks($navigator, $return_url, $itemobject_id, $comment_id, $comments_added,$user_id,$proxy_user_id) {
			$can_edit_self = true;
			$links = array();
			foreach(self::getListOfCommentActions($comments_added,$user_id,$proxy_user_id) as $action_name => $detail_action) {
				if (Zend_Registry::get('customAcl')->isAllowed($_SESSION['account']->getRole(),'table:comment',$detail_action['privilege'])
					&& ($can_edit_self || ($detail_action['privilege']=='view'))) {
					$icon_html = detailActionToHtml($action_name,$detail_action);
					$title = isset($detail_action['title']) ? $detail_action['title'] : $detail_action['buttonname'];
					$confirm_js = isset($detail_action['confirm']) 	? "return confirm('".$detail_action['confirm']."');"
																	: (isset($detail_action['alert']) 	? "alert('".$detail_action['alert']."'); return false;"
																										: "return true;");
					$url = $navigator->getCurrentViewUrl($action_name,'struct',array('table' => 'comment', 'comment_id' => $comment_id, 'return_url' => $return_url));
					$target = isset($detail_action['target']) ? $detail_action['target'] : "";
					$links[] = linkify($url,empty($icon_html) ? $detail_action['buttonname'] : $icon_html,$title,empty($icon_html) ? 'minibutton2' : '',
						"{$confirm_js}",$target,'btn-'.$action_name.'-'.$comment_id);
				}
			}
			return $links;
		}    

		/**
		 * Overridden to make sure some cached comment fields get updated
		 * @see DBTableRow::delete()
		 */
		public function delete() {
			$itemobject_id = $this->itemobject_id;
			$text = $this->comment_text;
			parent::delete();
			DBTableRowChangeLog::deletedItemComment($itemobject_id, $text);
			DBTableRowItemObject::updateCachedLastCommentFields($itemobject_id);
		}	

		/**
		 * Overridden to make sure some cached comment fields get updated
		 * @see DBTableRow::save()
		 */
		public function save($fieldnames=array(),$handle_err_dups_too=true) { // function will raise an exception if an error occurs
			$itemobject_id = $this->itemobject_id;
			$new_comment = !$this->isSaved();
			parent::save($fieldnames,$handle_err_dups_too);
			if ($new_comment) {
				DBTableRowChangeLog::addedItemComment($itemobject_id, $this->comment_id, $this->user_id);
			} else {
				DBTableRowChangeLog::changedItemComment($itemobject_id, $this->comment_id, $this->user_id);
			}
			DBTableRowItemObject::updateCachedLastCommentFields($itemobject_id);
		}		
		
		// do any preprocessing after reading table row.  For example, unserialize objects
		protected function onAfterGetRecord(&$record_vars) {
			$config = Zend_Registry::get('config');
			if ('new'!=$record_vars['comment_id']) {
				$record_vars['document_ids'] = array_keys(DbSchema::getInstance()->getRecords('document_id',"SELECT * FROM document WHERE (comment_id='{$record_vars['comment_id']}') and (document_path_db_key='{$config->document_path_db_key}')"));
			}
			return true;
		}		
		
		protected function onAfterSaveRecord() {
			// get all the document records that have not been attached to this comment yet
			if (count($this->document_ids)>0) {
				$records = DbSchema::getInstance()->getRecords('document_id',"SELECT * FROM document WHERE (document_id IN (".implode(',',$this->document_ids).")) and comment_id=-1");
			} else {
				$records = array();
			}
			foreach($records as $document_id => $record) {
				$Doc = new DBTableRowDocument();
				$Doc->assign($record);
				$Doc->comment_id = $this->comment_id;
				$Doc->save();
			}
			return true;
		}		
		
		public function getRecordById($id) {
			$DBTableRowQuery = new DBTableRowQuery($this);
			$DBTableRowQuery->addSelectFields("(SELECT login_id FROM user WHERE user.user_id=comment.user_id) as login_id");
			$DBTableRowQuery->addSelectFields("(SELECT login_id FROM user WHERE user.user_id=comment.proxy_user_id) as proxy_login_id");
			$DBTableRowQuery->setLimitClause('LIMIT 1')->addSelectors(array($this->_idField => $id));
			return $this->getRecord($DBTableRowQuery->getQuery());
		}
		
		public function getEditFieldNames($join_names=null) {
			$fieldnames = parent::getEditFieldNames($join_names);
			if ($_SESSION['account']->getRole()=='DataTerminal') $fieldnames[] = 'user_id';
			return $fieldnames;
		}		
		
		public function formatInputTag($fieldname, $display_options=array()) {
			$fieldtype = $this->getFieldType($fieldname);
			$attributes = isset($fieldtype['disabled']) && $fieldtype['disabled'] ? ' disabled' : '';
			$value = $this->$fieldname;
				

			switch($fieldname) {
				case 'user_id' :
					if ($_SESSION['account']->getRole()=='DataTerminal') {
						$this->setFieldCaption('user_id','Login ID');
						$login_id_by_user_id = DbSchema::getInstance()->getRecords('user_id',"SELECT user_id,login_id FROM user where (user_type not in ('DataTerminal','Guest')) and (user_enabled=1) order by login_id");
						$login_id_by_user_id = extract_column($login_id_by_user_id,'login_id');
						return format_select_tag($login_id_by_user_id,$fieldname,$this->getArray());
					}
					break;
			}
			return parent::formatInputTag($fieldname, $display_options);
		}
		
		public function formatPrintField($fieldname, $is_html=true, $nowrap=true) {
			$fieldtype = $this->getFieldType($fieldname);
			$value = $this->$fieldname;
			switch($fieldname) {
				case 'user_id':
					if ($_SESSION['account']->getRole()=='DataTerminal') {
						$this->setFieldCaption('user_id','Login ID');
						$login_id_by_user_id = DbSchema::getInstance()->getRecords('user_id',"SELECT user_id,login_id FROM user where (user_type not in ('DataTerminal','Guest')) and (user_enabled=1) order by login_id");
						$login_id_by_user_id = extract_column($login_id_by_user_id,'login_id');
						return $is_html ? TextToHtml($login_id_by_user_id[$this->user_id]) : $login_id_by_user_id[$this->user_id];
					}
				default:
					return parent::formatPrintField($fieldname, $is_html, $nowrap);
			}
		}

		static public function commentTipsHtml() {
			return 'Special markup that can appear in your comment text:
					<dl class="comment_tips">
					<dt>'.TextToHtml('<html>...</html>').'</dt><dd>Renders the enclosed string as HTML.</dd>
		    		<dt>'.TextToHtml('[io/nnnn]').'</dt><dd>Embeds a link to the object with the specified io (item object) number.</dd>
		    		<dt>'.TextToHtml('[iv/nnnn]').'</dt><dd>Embeds a link to the object with the specified iv (item version) number.</dd>
				    </dl>';
		}


    }

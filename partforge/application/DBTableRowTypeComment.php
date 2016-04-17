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

    class DBTableRowTypeComment extends DBTableRow {
        
        public function __construct() {
            parent::__construct('typecomment');
            $this->comment_added = time_to_mysqldatetime(script_time());
            $this->user_id = $_SESSION['account']->user_id;
        }
        
        /**
         * Overridden to make sure changelog is updated
         * @see DBTableRow::delete()
         */
        public function delete() {
        	$typeobject_id = $this->typeobject_id;
        	parent::delete();
        	DBTableRowChangeLog::deletedTypeComment($typeobject_id);
        }
        
        /**
         * Overridden to make sure changelog is updated
         * @see DBTableRow::save()
         */
        public function save($fieldnames=array(),$handle_err_dups_too=true) { // function will raise an exception if an error occurs
        	$typeobject_id = $this->typeobject_id;
        	$new_comment = !$this->isSaved();
        	parent::save($fieldnames,$handle_err_dups_too);
        	if ($new_comment) {
        		DBTableRowChangeLog::addedTypeComment($typeobject_id);
        	} else {
        		DBTableRowChangeLog::changedTypeComment($typeobject_id);
        	}
        }        
        
        /*
         * This kinda sorta works but was mostly pasted.
         */
		static public function getListOfCommentActions($comment_added,$user_id) {
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
            	if ((Zend_Registry::get('customAcl')->isAllowed($_SESSION['account']->getRole(),'table:typecomment','edit')
            		&& ($_SESSION['account']->user_id == $user_id) && $inside_grace_period)) {
            		$can_edit = true;
            		$can_delete = true;
            	} 
            }
            
            if ($can_edit) $actions['editview'] = array('buttonname' => 'Edit', 'privilege' => 'view');
            if ($can_delete) $actions['delete'] = array('buttonname' => 'Delete', 'privilege' => 'delete', 'confirm' => 'Are you sure you want to delete this?');
            if ($can_deleteblocked) $actions['delete'] = array('buttonname' => 'Delete (Blocked)', 'privilege' => 'delete', 'alert' => 'This record is older than '.(integer)($config->delete_grace_in_sec/3600).' hours.  If you want to delete it, you must go to the Settings menu and enable Delete Override.');

            return $actions;
        }        
        
    	/*
			return the editing links for a dependent row
		*/
		static public function commentEditLinks($navigator, $return_url, $typeobject_id, $comment_id, $comments_added,$user_id) {
			$can_edit_self = true;
			$links = array();
			foreach(self::getListOfCommentActions($comments_added,$user_id) as $action_name => $detail_action) {
				if (Zend_Registry::get('customAcl')->isAllowed($_SESSION['account']->getRole(),'table:typecomment',$detail_action['privilege'])
					&& ($can_edit_self || ($detail_action['privilege']=='view'))) {
					$icon_html = detailActionToHtml($action_name,$detail_action);
					$title = isset($detail_action['title']) ? $detail_action['title'] : $detail_action['buttonname'];
					$confirm_js = isset($detail_action['confirm']) 	? "return confirm('".$detail_action['confirm']."');"
																	: (isset($detail_action['alert']) 	? "alert('".$detail_action['alert']."'); return false;"
																										: "return true;");
					$url = $navigator->getCurrentViewUrl($action_name,'db',array('table' => 'typecomment', 'comment_id' => $comment_id, 'return_url' => $return_url));
					$target = isset($detail_action['target']) ? $detail_action['target'] : "";
					$links[] = linkify($url,empty($icon_html) ? $detail_action['buttonname'] : $icon_html,$title,empty($icon_html) ? 'minibutton2' : '',
						"{$confirm_js}",$target,'btn-comment-'.$action_name.'-'.$comment_id);
				}
			}
			return $links;
		}        

    }

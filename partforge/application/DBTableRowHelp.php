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

    class DBTableRowHelp extends DBTableRow {
                
        public function __construct($ignore_joins=false,$parent_index=null) {
            parent::__construct('help',$ignore_joins,$parent_index);
        }        
        
        public function getRecordForActionController($action,$controller,$table) {
        	$DBQuery = new DBTableRowQuery($this);
        	// The parameters here are legacy.  Prior to DB version 6, help was context sensitive.  Now it is the same for every page.
        	$DBQuery->addAndWhere(" and action_name='' and controller_name='' and table_name=''");
        	return $this->getRecord($DBQuery->getQuery());
        }
        
    
        /** This is used as a class function.
         * 
         * @param UrlCallRegistry $Navigator
         * @return string
         */
		public static function helpLinkIfPresent($Navigator) {
        	$request = Zend_Controller_Front::getInstance()->getRequest();
        	$params = $request->getParams();
        	$tablename = !empty($params['table']) ? $params['table'] : '';

        	$Help = DBSchema::getInstance()->DBTableRowObjectFactory('help');
			
    		$links = array();
			if ($Help->getRecordForActionController($request->getActionName(),$request->getControllerName(),$tablename)) {
				$tip = TextToHtml($Help->help_tip);
				if (empty($tip)) {
					$tip = 'View help for this page';
				}
				$links[] = popup_linkify(UrlCallRegistry::formatViewUrl('page','help',array('help_action' => $Help->action_name, 'help_controller' => $Help->controller_name, 'help_table' => $Help->table_name)),"Help",$tip,'','','PopupWin',700,600);
			}
    		if ((AdminSettings::getInstance()->edit_help) && !(($request->getControllerName()=='db') && ($tablename=='help'))) {
    			$initialize = array();
    			$initialize['action_name'] = '';
    			$initialize['controller_name'] = '';
    			$initialize['table_name'] = '';
    			$linkparams = array('help_id' => $Help->help_id, 'table' => 'help', 'initialize' => $initialize);
    			if (!is_null($Navigator)) {
    				$linkparams['return_url'] = self_url().'?'.$_SERVER['QUERY_STRING'];
    			}
    			$links[] = linkify(UrlCallRegistry::formatViewUrl('editview','help',$linkparams),"Edit Help",'Edit the help page','minibutton2');
    			if (is_numeric($Help->help_id)) {
    				$links[] = linkify(UrlCallRegistry::formatViewUrl('delete','help',$linkparams),"Delete",'Delete the help page','minibutton2','return confirm(\'Are you sure you want to delete the global help page?\');');
    			}
    		}
			return implode(' ',$links);

    	}        
    }

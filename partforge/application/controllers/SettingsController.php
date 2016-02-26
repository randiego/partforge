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

class SettingsController extends DBControllerActionAbstract
{
    
    public function listviewAction() {
        $action_taken = array();
        $Obj = new TableRowSettings();
        foreach($Obj->getSessionBooleanFieldNames() as $fieldname) {
        	if (!AdminSettings::getInstance()->{$fieldname}) AdminSettings::getInstance()->{$fieldname} = false;
        }
        $Obj->loadGlobals();
        
        switch (true) {
            case isset($this->params['btnSave']):
            	// store temporary boolean
            	foreach($Obj->getSessionBooleanFieldNames() as $fieldname) {
            		if (AdminSettings::getInstance()->{$fieldname} != $this->params[$fieldname]) {
            			$action_taken[] = $Obj->getFieldAttribute($fieldname, 'caption').' is now temporarily '.($this->params[$fieldname] ? 'Enabled' : 'Disabled').'.';
            		}
            	}
            	foreach($Obj->getSessionBooleanFieldNames() as $fieldname) {
            		AdminSettings::getInstance()->{$fieldname} = $this->params[$fieldname];
            	}
                AdminSettings::getInstance()->setExpirationTimeMinutes('edit_help',4*60);
                
                // store permanent globals
                foreach($Obj->getGlobalsFieldNames() as $fieldname) {
                	setGlobal($fieldname, DBTableRowItemVersion::varToStandardForm($this->params[$fieldname], $Obj->getFieldType($fieldname)));
                }                
                
                break;
        }
        
        $this->view->navigator = $this->navigator;
        $this->view->action_taken = implode('  ',$action_taken);
    }
    
    public function treeviewAction()
    {
    }
    
    public function deleteAction()
    {
    }
    
    public function editviewAction()
    {
    }
    
 }

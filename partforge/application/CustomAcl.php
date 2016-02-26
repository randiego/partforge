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
    class CustomAcl extends Zend_Acl
    {
        private $_defaultRole;
        
        public function __construct($in_defaultRole)
        {
        	$global_readonly = Zend_Registry::get('config')->global_readonly;
        	$this->_defaultRole = $in_defaultRole;

        // roles
        	$this->addRole(new Zend_Acl_Role($this->_defaultRole)); // this role is for someone clicking a link and then needing to login and be redirected to that link.
            $this->addRole(new Zend_Acl_Role('Guest'));
        	$this->addRole(new Zend_Acl_Role('Tech'));
        	$this->addRole(new Zend_Acl_Role('DataTerminal'));
            $this->addRole(new Zend_Acl_Role('Eng'));
            $this->addRole(new Zend_Acl_Role('Supervisor'));
            $this->addRole(new Zend_Acl_Role('Admin'));
            
        // resources

            // table resources
            $table_list = DbSchema::getInstance()->getTableNames();
            foreach($table_list as $tablename) {
                $this->add(new Zend_Acl_Resource('table:'.$tablename));
            }

            
            // controller resources
            
            $controller_resources = array('db','error','help','index','cron','api','output','settings','joinedexport','struct','user','types_versions','types_objects','items_versions','items_objects','items_comments', 'items_documents', 'types_documents');
            foreach($controller_resources as $controller_resource) {
            	$this->add(new Zend_Acl_Resource($controller_resource));
            }

            // user interface resources
            $this->add(new Zend_Acl_Resource('ui:itemlistview'));
            $this->add(new Zend_Acl_Resource('ui:itemedit'));
            $this->add(new Zend_Acl_Resource('ui:logout'));
            $this->add(new Zend_Acl_Resource('ui:nonterminalbling'));  // this is the extra fluff like spreadsheet export that are not interesting on a dumb terminal
            $this->add(new Zend_Acl_Resource('ui:caneditdefinitions')); // controls that allow editing of definitions
            
            
            
            
        // access
        
        /*
          privileges for table:* resources are
            view = access the controller and view the records
            add  = have add buttons available to add records
            delete = delete a record
            edit = save changes to a record
            
            For this system, the strategy will be to allow all tables by default for logged-in users of any type, and allow all controllers.
            Then for the controllers, deny specific controllers based on user type.
            Also, always make an exception for the login prompt and utility controllers for default user.  
            
            For user interface elements that fall through the cracks, we use the ui:xxxx resources.
            For example
         
        */

            $this->deny();
            
            // allow all tables except no editing to guests and nothing for unlogged-in users (we will allow bits down below)
            foreach($table_list as $tablename) {
                $this->allow(null,'table:'.$tablename);
                
                if ($global_readonly) {
	                $this->deny($this->_defaultRole,'table:'.$tablename,array('add','delete','edit'));
                } else {
		            $this->deny($this->_defaultRole,'table:'.$tablename);
                } 
                
                
                $this->deny('Guest','table:'.$tablename,array('add','delete','edit'));
            }
            
            
            
            // allow anyone 
            $this->allow(null,'items_versions');
            $this->allow(null,'items_objects');
            $this->allow(null,'items_comments');
            $this->allow(null,'items_documents');
            $this->allow(null,'types_versions');
            $this->allow(null,'types_objects');   
            $this->allow(null,'types_documents');   
            $this->allow(null,'ui:nonterminalbling');
            $this->allow(null,'cron');         
            $this->allow(null,'api');
            
            
            
            // allow all controllers for admin
            foreach($controller_resources as $controller_resource) {
            	$this->allow('Admin',$controller_resource);
            	$this->allow('Eng',$controller_resource);
            	$this->allow('Tech',$controller_resource);
            	$this->allow('DataTerminal',$controller_resource);
            	if ($global_readonly) $this->allow($this->_defaultRole,$controller_resource);
            	$this->allow('Guest',$controller_resource);
            }
            
            // the settings tab
            $this->deny('DataTerminal','struct',array('partlistview'));
            $this->deny('Tech','settings');
            $this->deny('Eng','settings');
            $this->deny('DataTerminal','settings');
            
            // analysis tab
            $this->deny('DataTerminal','struct',array('joinedexport'));            
            
            $this->deny('Guest','settings');
            $this->deny($this->_defaultRole,'settings');
            $this->deny('Guest','user',array('manageaccount','changeprofile','changepassword'));
            $this->deny('DataTerminal','user',array('manageaccount','changeprofile','changepassword','listview'));
            if ($global_readonly) {
	            $this->deny($this->_defaultRole,'user',array('manageaccount','changeprofile','changepassword'));
            }
            // comments tab
            $this->deny('DataTerminal','struct',array('commentlistview'));
            
  			// allow the utility and login controller for default
  			
            if (!$global_readonly) {
	  			$this->allow($this->_defaultRole,'user',array('login','register','searchloginids','jsonsearchloginids','findloginbyemail','resetmypassword','resetmypassword2'));
	  			$this->allow($this->_defaultRole,'error');
	  			$this->allow($this->_defaultRole,'help');
	  			$this->allow($this->_defaultRole,'output');
            }

			// deny user editing for Techs and Eng
  			$this->deny('Tech','table:user',array('add','delete','edit'));
  			$this->deny('Eng','table:user',array('add','delete','edit'));
  			$this->deny('DataTerminal','table:user',array('add','delete','edit','view'));
  			
  				
  			// allow ui: resources
  			$this->allow('Admin','ui:itemlistview');
  			$this->allow('Eng','ui:itemlistview');
  			$this->allow('Tech','ui:itemlistview');
  			$this->allow('DataTerminal','ui:itemlistview');
  			$this->allow('Guest','ui:itemlistview');
  			if ($global_readonly) $this->allow($this->_defaultRole,'ui:itemlistview');
  			
  			$this->allow('DataTerminal','ui:logout');
  			$this->deny('DataTerminal','ui:nonterminalbling');
  			
  			$this->allow('Admin','ui:caneditdefinitions');
  			$this->allow('Eng','ui:caneditdefinitions');
  			
  			// the item edit buttons
  			$this->deny(null,'ui:itemedit');
  			$this->allow('Admin','ui:itemedit',array('new_part_version','edit_part_version','edit_proc_version','new_proc_version','can_edit_old_versions'));
  			$this->allow(array('Eng','Tech'),'ui:itemedit',array('can_edit_old_versions','new_part_version','edit_part_version','new_proc_version'));
  			$this->allow(array('DataTerminal'),'ui:itemedit',array('new_part_version','new_proc_version'));
        }
        
        public function defaultRole()
        {
            return $this->_defaultRole;
        }
        
        public function CntlAndActToResAndPriv($tableparam,$controller,$action) {
            $resource = $controller;
            $privilege = $action;
            if ($controller=='db') {
                if (!empty($tableparam) && in_array($tableparam,DbSchema::getInstance()->getTableNames())) {
                    $resource = 'table:'.$tableparam;
                    $privilege = 'view'; // most basic priviledge
                }
            } elseif (in_array($resource,DbSchema::getInstance()->getTableNames())) {
                $resource = 'table:'.$resource;
                $privilege = 'view';   // This is sort of annoying since it will always set the privilege to view.  I'm not sure what to doo differently though.
            }
            return array($resource,$privilege);
        }
    }
?>

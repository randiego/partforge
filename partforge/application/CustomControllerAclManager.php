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
    class CustomControllerAclManager extends Zend_Controller_Plugin_Abstract
    {
        
        private $_defaultContoller = 'user';
        private $_defaultAction = 'login';
        
        public function __construct(Zend_Acl $acl_in)
        {
            $this->acl = $acl_in;
        }
        
        private function redirectLoginRequestIfBadDomain($request) {
        	$config = Zend_Registry::get('config');
        	$url = $request->getRequestUri();
        	if ($config->force_login_domain) {
        		// check the current domain and redirect if needed
        		if ($request->getHttpHost()!=$config->force_login_domain) {
        			$url = $request->getScheme().'://'.$config->force_login_domain.$url;
        			spawnurl($url);  
        		}
        	}
        }
        
        public function preDispatch(Zend_Controller_Request_Abstract $request)
        {
            $timed_out = logout_if_session_timed_out();
            
            $login_status = LoginStatus::getInstance();
            if ($login_status->isValidUser()) {
            	$role = $_SESSION['account']->getRole();
            } else {
            	$role = $this->acl->defaultRole();
            }
            
            if (!$this->acl->hasRole($role)) {
            	$role = $this->acl->defaultRole();
            }            
            
            /*
              resources are either table name or Zend controller.   table:committeemeeting
              permissions are either table permissions (if resource is tablename) or Zend action
              Always checks if 
            */
            
            $is_allowed = false;
            // To do : sometimes we have module=items, controller=objects, action=index  for example.
            if (isset($request->module) && $this->acl->has($request->module.'_'.$request->controller)  && $this->acl->isAllowed($role,$request->module.'_'.$request->controller,$request->action)) {
            	$is_allowed = true;
            	$resource = $request->module.'_'.$request->controller;
            	$privilege = $request->action;
            } else if ($this->acl->has($request->controller) && $this->acl->isAllowed($role,$request->controller,$request->action)) {
                $is_allowed = true;
                $resource = $request->controller;
                $privilege = $request->action;
            } else {
                list($resource,$privilege) = $this->acl->CntlAndActToResAndPriv($request->getParam('table'),$request->controller,$request->action);
                if ($this->acl->has($resource) && $this->acl->isAllowed($role,$resource,$privilege)) {
                    $is_allowed = true;
                }
            }
            
            if (!$is_allowed) {
            	if ($login_status->isValidUser()) {
                    // this is where we should silently declaire an error since we are trying to go someplace invalid
                    $msg = 'User: '.$_SESSION['account']->login_id."\r\n".
	                       'Request URI: '.$_SERVER['REQUEST_URI']."\r\n";
                    logerror("Invalid Page Fetch Attempt:\r\n".$msg);
                    $_SESSION['msg'] = 'Invalid Page Request';
            		spawnurl($_SESSION['account']->defaultLoginUrl(array('msge' => 1)));
            	} else {
            		// Here we take note of our ultimate destination (login_url) and jump now to the login page.
            		$this->redirectLoginRequestIfBadDomain($request);
            		// using sessions here is pretty lame.  It would be better to send query params using setParam()
            		$_SESSION['login_url'] = $request->getRequestUri(); 
            		$request->setControllerName($this->_defaultContoller);
            		$request->setActionName($this->_defaultAction);
            		if ($timed_out) {
            			$request->setParam('msge', '');
            		}
            	}
            }
        }
    }


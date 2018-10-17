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

abstract class RestControllerActionAbstract extends Zend_Rest_Controller
{
    public $params;
//	protected $_redirector = null;
    
    public function init()
    {
//    	$this->_redirector = $this->_helper->getHelper('Redirector');  
        $this->params = $this->getRequest()->getParams();
//        $this->navigator = new UrlCallRegistry($this,$this->getRequest()->getBaseUrl().'/user/login');
//        $this->navigator->setPropagatingParamNames(explode(',',AUTOPROPAGATING_QUERY_PARAMS));
        trim_recursive($this->params);
//        $this->getResponse()->setHeader('Content-Type', 'text/html; charset=UTF-8', true);
        $this->_helper->viewRenderer->setNoRender(true);

        $contextSwitch = $this->_helper->getHelper('contextSwitch')
        					->addActionContext('index', 'json')
        					->addActionContext('get', 'json')
        					->addActionContext('post', 'json')
        					->addActionContext('put', 'json')
        					->addActionContext('delete', 'json')
        					->initContext();
    }
    
    protected function noOp() {
    	// action body
    	echo "<p>'".$this->params['action']."' action not implemented!</p>";
    	die();
    }
    

    
}

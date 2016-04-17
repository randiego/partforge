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

class UtilsController extends DBControllerActionAbstract
{
	public $params;

	public function init()
	{
		$this->params = $this->getRequest()->getParams();
		trim_recursive($this->params);
		$this->navigator = new UrlCallRegistry($this,$this->getRequest()->getBaseUrl().'/user/login');
		$this->navigator->setPropagatingParamNames(explode(',',AUTOPROPAGATING_QUERY_PARAMS));
	}
	
	public function upgradeAction() {
		$msgs = array();
		if (isset($this->params['form'])) {
			switch (true)
			{
				case isset($this->params['btnUpgrade']):
					$databaseversion = getGlobal('databaseversion');
					if (!$databaseversion) $databaseversion = '1 or older';
					
					if ($databaseversion=='1 or older') {
						$msgs[] = 'Upgrading to version 2: background changelog table';
						DbSchema::getInstance()->mysqlQuery("DROP TABLE IF EXISTS changelog");
						DbSchema::getInstance()->mysqlQuery("CREATE TABLE IF NOT EXISTS `changelog` (
							  `changelog_id` int(11) NOT NULL AUTO_INCREMENT,
							  `user_id` int(11) NOT NULL,
							  `changed_on` datetime NOT NULL,
							  `itemobject_id` int(11) DEFAULT NULL,
							  `itemversion_id` int(11) DEFAULT NULL,
							  `typeobject_id` int(11) DEFAULT NULL,
							  `typeversion_id` int(11) DEFAULT NULL,
							  `locator_prefix` VARCHAR(2) DEFAULT NULL,
							  `change_code` VARCHAR(4) NOT NULL,
							  PRIMARY KEY (`changelog_id`),
							  KEY `user_id` (`user_id`),
							  KEY `itemobject_id` (`itemobject_id`),
							  KEY `itemversion_id` (`itemversion_id`),
							  KEY `typeobject_id` (`typeobject_id`),
							  KEY `typeversion_id` (`typeversion_id`),
							  KEY `change_code` (`change_code`)
							) ENGINE=InnoDB  DEFAULT CHARSET=utf8");
						$databaseversion = '2';
						setGlobal('databaseversion', $databaseversion);
					}
					
			}
		}
		$this->view->currentversion = getGlobal('databaseversion');
		if (!$this->view->currentversion) $this->view->currentversion = 'old version';	
		$this->view->targetversion = Zend_Registry::get('config')->databaseversion;	
		$this->view->msgs = $msgs;
		$this->view->params = $this->params;
		$this->view->navigator = $this->navigator;
	}

}

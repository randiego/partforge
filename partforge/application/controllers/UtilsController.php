<?php
/**
 *
 * PartForge Enterprise Groupware for recording parts and assemblies by serial number and version along with associated test data and comments.
 *
 * Copyright (C) 2013-2021 Randall C. Black <randy@blacksdesign.com>
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
        $this->navigator = new UrlCallRegistry($this, $this->getRequest()->getBaseUrl().'/user/login');
        $this->navigator->setPropagatingParamNames(explode(',', AUTOPROPAGATING_QUERY_PARAMS));
        $this->view->navigator = $this->navigator;
    }

    public function upgradeAction()
    {
        $msgs = array();
        if (isset($this->params['form'])) {
            switch (true) {
                case isset($this->params['btnUpgrade']):
                    $databaseversion = getGlobal('databaseversion');
                    if (!$databaseversion) {
                        $databaseversion = '1 or older';
                    }


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

                    if ($databaseversion=='2') {
                        $msgs[] = 'Upgrading to version 3: new format for changelog table.';
                        DbSchema::getInstance()->mysqlQuery("DROP TABLE IF EXISTS changelog");
                        DbSchema::getInstance()->mysqlQuery("CREATE TABLE IF NOT EXISTS `changelog` (
							  `changelog_id` int(11) NOT NULL AUTO_INCREMENT,
							  `user_id` int(11) NOT NULL,
							  `changed_on` datetime NOT NULL,
							  `desc_typeversion_id` int(11) DEFAULT NULL,
							  `desc_partnumber_alias` int(11) DEFAULT NULL,
							  `desc_itemversion_id` int(11) DEFAULT NULL,
							  `desc_typecategory_id` int(11) DEFAULT NULL,
							  `desc_comment_id` int(11) DEFAULT NULL,
							  `desc_text` varchar(255) DEFAULT NULL,
							  `locator_prefix` VARCHAR(2) DEFAULT NULL,
							  `trigger_itemobject_id` int(11) DEFAULT NULL,
							  `trigger_typeobject_id` int(11) DEFAULT NULL,
							  `change_code` VARCHAR(4) NOT NULL,
							  PRIMARY KEY (`changelog_id`),
							  KEY `user_id` (`user_id`),
							  KEY `trigger_itemobject_id` (`trigger_itemobject_id`),
							  KEY `trigger_typeobject_id` (`trigger_typeobject_id`),
							  KEY `change_code` (`change_code`)
							) ENGINE=InnoDB  DEFAULT CHARSET=utf8");

                        DbSchema::getInstance()->mysqlQuery("DROP TABLE IF EXISTS changecode");
                        DbSchema::getInstance()->mysqlQuery("CREATE TABLE `changecode` (
						`change_code_id` int(11) NOT NULL AUTO_INCREMENT,
						`change_code` VARCHAR(4) NOT NULL,
						`change_code_name` varchar(128) DEFAULT NULL,
						PRIMARY KEY (`change_code_id`),
						KEY `change_code` (`change_code`)
						) ENGINE=InnoDB  DEFAULT CHARSET=utf8;");

                        DbSchema::getInstance()->mysqlQuery("INSERT INTO `changecode` (`change_code_id`, `change_code`, `change_code_name`) VALUES
						(1, 'DIO', 'Deleted an Item'),
						(2, 'DIV', 'Deleted Item Version'),
						(3, 'AIO', 'Added New Item'),
						(4, 'CIV', 'Changed Item Version'),
						(5, 'AIV', 'Added Item Version'),
						(6, 'ATO', 'Added New Definition'),
						(7, 'RTV', 'Released Definition Version'),
						(8, 'OTO', 'Obsoleted Definition'),
						(9, 'CTV', 'Changed Definition Version'),
						(10, 'ATV', 'Added Definition Version'),
						(11, 'DTV', 'Deleted Definition Version'),
						(12, 'DTO', 'Deleted a Definition'),
						(13, 'AIC', 'Added Item Comment'),
						(14, 'CIC', 'Changed Item Comment'),
						(15, 'DIC', 'Deleted Item Comment'),
						(16, 'AIR', 'Became Used On'),
						(17, 'AIP', 'Added Procedure'),
						(18, 'ATC', 'Added Definition Comment'),
						(19, 'CTC', 'Changed Definition Comment'),
						(20, 'DTC', 'Deleted Definition Comment');");

                        $databaseversion = '3';
                        setGlobal('databaseversion', $databaseversion);
                    }

                    if ($databaseversion=='3') {
                        $msgs[] = 'Upgrading to version 4: changesubscription table';
                        DbSchema::getInstance()->mysqlQuery("DROP TABLE IF EXISTS changesubscription");
                        DbSchema::getInstance()->mysqlQuery("CREATE TABLE IF NOT EXISTS `changesubscription` (
							  `changesubscription_id` int(11) NOT NULL AUTO_INCREMENT,
							  `user_id` int(11) NOT NULL,
							  `added_on` datetime NOT NULL,
							  `itemobject_id` int(11) DEFAULT NULL,
							  `typeobject_id` int(11) DEFAULT NULL,
							  `notify_instantly` INT(1) DEFAULT 0,
							  `notify_daily` INT(1) DEFAULT 0,
							  PRIMARY KEY (`changesubscription_id`),
							  KEY `user_id` (`user_id`),
							  KEY `itemobject_id` (`itemobject_id`),
							  KEY `typeobject_id` (`typeobject_id`)
							) ENGINE=InnoDB  DEFAULT CHARSET=utf8");
                        DbSchema::getInstance()->mysqlQuery("DROP TABLE IF EXISTS changenotifyqueue");
                        DbSchema::getInstance()->mysqlQuery("CREATE TABLE IF NOT EXISTS `changenotifyqueue` (
							  `changenotifyqueue_id` int(11) NOT NULL AUTO_INCREMENT,
							  `user_id` int(11) NOT NULL,
							  `changelog_id` int(11) NOT NULL,
							  `added_on` datetime NOT NULL,
							  PRIMARY KEY (`changenotifyqueue_id`),
							  KEY `user_id` (`user_id`),
							  KEY `changelog_id` (`changelog_id`)
							) ENGINE=InnoDB  DEFAULT CHARSET=utf8");
                        $databaseversion = '4';
                        setGlobal('databaseversion', $databaseversion);
                    }

                    if ($databaseversion=='4') {
                        $msgs[] = 'Upgrading to version 5: changesubscription table additions - Jan 2019';
                        DbSchema::getInstance()->mysqlQuery("ALTER TABLE `changesubscription` ADD COLUMN `follow_items_too` INT(1) DEFAULT 1 AFTER `typeobject_id`");
                        $databaseversion = '5';
                        setGlobal('databaseversion', $databaseversion);
                    }

                    if ($databaseversion=='5') {
                        $msgs[] = 'Upgrading to version 6: merge help topics into single one - Oct 2020';
                        $recs = DbSchema::getInstance()->getRecords('help_id', "SELECT * FROM help");
                        if (count($recs)>0) {
                            $group_records = DbSchema::getInstance()->getRecords('', "SELECT GROUP_CONCAT(DISTINCT `help_tip` SEPARATOR ' ') as group_tip,  GROUP_CONCAT(DISTINCT `help_markup` SEPARATOR ' ') as group_markup FROM `help` WHERE 1");
                            $group_record = reset($group_records);
                            $Help = new DBTableRowHelp();
                            $Help->action_name = '';
                            $Help->controller_name = '';
                            $Help->table_name = '';
                            $Help->help_tip = $group_record['group_tip'];
                            $Help->help_markup = $group_record['group_markup'];
                            DbSchema::getInstance()->mysqlQuery("DELETE FROM help");
                            $Help->save();
                        }
                        $databaseversion = '6';
                        setGlobal('databaseversion', $databaseversion);
                    }

                    if ($databaseversion=='6') {
                        $msgs[] = 'Upgrading to version 7: proper handling of Unversions change tracking - March 2021';
                        DbSchema::getInstance()->mysqlQuery("ALTER TABLE `itemversionarchive` ADD COLUMN `original_record_created` datetime NULL AFTER `record_created`");
                        DbSchema::getInstance()->mysqlQuery("UPDATE itemversionarchive
							SET original_record_created=REPLACE(SUBSTRING_INDEX(SUBSTR(item_data, LOCATE('record_created\":\"',item_data,1)), '\"', 3),'record_created\":\"','')");
                        $databaseversion = '7';
                        setGlobal('databaseversion', $databaseversion);
                    }

            }
        }
        $this->view->currentversion = getGlobal('databaseversion');
        if (!$this->view->currentversion) {
            $this->view->currentversion = 'old version';
        }
        $this->view->targetversion = Zend_Registry::get('config')->databaseversion;
        $this->view->msgs = $msgs;
        $this->view->params = $this->params;
    }

}

<?php
/**
 *
 * PartForge Enterprise Groupware for recording parts and assemblies by serial number and version along with associated test data and comments.
 *
 * Copyright (C) 2013-2022 Randall C. Black <randy@blacksdesign.com>
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
							SET original_record_created=REPLACE(SUBSTRING_INDEX(SUBSTR(item_data, LOCATE('record_created\":\"',item_data,1)), '\"', 3),'record_created\":\"','')
                            WHERE REPLACE(SUBSTRING_INDEX(SUBSTR(item_data, LOCATE('record_created\":\"',item_data,1)), '\"', 3),'record_created\":\"','') != ''");
                        $databaseversion = '7';
                        setGlobal('databaseversion', $databaseversion);
                    }

                    if ($databaseversion=='7') {
                        $msgs[] = 'Upgrading to version 8: QR Code Uploading from comments page - Oct 2021';
                        DbSchema::getInstance()->mysqlQuery("CREATE TABLE IF NOT EXISTS `qruploadkey` (
							  `qruploadkey_id` int(11) NOT NULL AUTO_INCREMENT,
							  `user_id` int(11) NOT NULL DEFAULT -1,
							  `created_on` datetime NOT NULL,
							  `is_validated` INT(1) NOT NULL DEFAULT 0,
							  `is_closed` INT(1) NOT NULL DEFAULT 0,
							  `qruploadkey_value` varchar(32) DEFAULT NULL,
							  UNIQUE KEY `qruploadkey_value` (`qruploadkey_value`),
							  PRIMARY KEY (`qruploadkey_id`),
							  KEY `user_id` (`user_id`)
							) ENGINE=InnoDB  DEFAULT CHARSET=utf8");
                        DbSchema::getInstance()->mysqlQuery("CREATE TABLE IF NOT EXISTS `qruploaddocument` (
							  `qruploaddocument_id` int(11) NOT NULL AUTO_INCREMENT,
							  `qruploadkey_id` int(11) NOT NULL,
							  `document_id` int(11) NOT NULL,
							  PRIMARY KEY (`qruploaddocument_id`)
							) ENGINE=InnoDB  DEFAULT CHARSET=utf8");
                        $databaseversion = '8';
                        setGlobal('databaseversion', $databaseversion);
                    }

                    if ($databaseversion=='8') {
                        $msgs[] = 'Upgrading to version 9: Adding DB indexes and 7 days watches';
                        DbSchema::getInstance()->mysqlQuery("ALTER TABLE changelog ADD INDEX (changed_on)");
                        $databaseversion = '9';
                        setGlobal('databaseversion', $databaseversion);
                    }
                    if ($databaseversion=='9') {
                        $msgs[] = 'Upgrading to version 10: Use each components only once with override';
                        DbSchema::getInstance()->mysqlQuery("ALTER TABLE typecomponent ADD COLUMN max_uses INT DEFAULT 1 AFTER required");
                        $databaseversion = '10';
                        setGlobal('databaseversion', $databaseversion);
                    }
                    if ($databaseversion=='10') {
                        $msgs[] = 'Upgrading to version 11: Recursive validation error reporting.';
                        DbSchema::getInstance()->mysqlQuery("ALTER TABLE itemobject ADD COLUMN validation_cache_is_valid INT DEFAULT 0");
                        DbSchema::getInstance()->mysqlQuery("ALTER TABLE itemobject ADD COLUMN cached_has_validation_errors INT DEFAULT 0");
                        $databaseversion = '11';
                        setGlobal('databaseversion', $databaseversion);
                    }
                    if ($databaseversion=='11') {
                        $msgs[] = 'Upgrading to version 12: This update adds in-form file attachments.';
                        DbSchema::getInstance()->mysqlQuery("CREATE TABLE IF NOT EXISTS `itemcomment` (
							  `itemcomment_id` int(11) NOT NULL AUTO_INCREMENT,
							  `belongs_to_itemversion_id` int(11) NOT NULL,
                              `field_name` varchar(80) DEFAULT NULL COMMENT 'field name of this itemcomment in the dictionary',
							  `has_a_comment_id` int(11) NOT NULL,
							  PRIMARY KEY (`itemcomment_id`),
                              KEY `belongs_to_itemversion_id` (`belongs_to_itemversion_id`),
                              KEY `has_a_comment_id` (`has_a_comment_id`)
							) ENGINE=InnoDB  DEFAULT CHARSET=utf8");
                        DbSchema::getInstance()->mysqlQuery("ALTER TABLE comment ADD COLUMN is_fieldcomment INT(1) DEFAULT 0");
                        $databaseversion = '12';
                        setGlobal('databaseversion', $databaseversion);
                    }
                    if ($databaseversion=='12') {
                        $msgs[] = 'Upgrading to version 13: This adds better tracking of unversioned changes.';
                        DbSchema::getInstance()->mysqlQuery("ALTER TABLE itemversionarchive ADD COLUMN changes_html text AFTER item_data");
                        DBTableRowItemVersionArchive::buildSomeArchiveChangesHtml();
                        $databaseversion = '13';
                        setGlobal('databaseversion', $databaseversion);
                    }
                    if ($databaseversion=='13') {
                        $msgs[] = 'Upgrading to version 14: Improvements to recursive validation caching.';
                        DbSchema::getInstance()->mysqlQuery("ALTER TABLE itemobject ADD COLUMN validated_on datetime AFTER validation_cache_is_valid");
                        DbSchema::getInstance()->mysqlQuery("ALTER TABLE itemobject ADD INDEX (validated_on)");
                        $script_time = time_to_mysqldatetime(script_time());
                        DbSchema::getInstance()->mysqlQuery("UPDATE itemobject SET validated_on='{$script_time}' WHERE validation_cache_is_valid=1");
                        $databaseversion = '14';
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

    public function qruploadAction()
    {
        $QRUploadKey = new DBTableRowQRUploadKey();
        if (!$QRUploadKey->getRecordByUploadKey($this->params['qrkey'])) {
            $QRUploadKey->qruploadkey_value = $this->params['qrkey'];
            $QRUploadKey->created_on = time_to_mysqldatetime(script_time());
            $QRUploadKey->save();

            // we only just created the record, now we need to wait for the commenteditview process
            // to validate it, so re return qruploadwaiting
            $this->view->qruploadkey_value = $QRUploadKey->qruploadkey_value;
            $this->view->time_elapsed = $QRUploadKey->timeElapsed();
            $this->render('qruploadwaiting');
        } elseif (!$QRUploadKey->is_validated || $QRUploadKey->is_closed) {
            // we are stll not ready to open the doors
            $this->view->qruploadkey_value = $QRUploadKey->qruploadkey_value;
            $this->view->time_elapsed = $QRUploadKey->timeElapsed();
            $this->view->is_closed = $QRUploadKey->is_closed;
            $this->render('qruploadwaiting');
        }
        if (!isset($this->params['initialized']) || !isset($_SESSION['qrupload'])) {
            // we come here if we really haven't gotten started yet
            $_SESSION['qrupload'] = array();
            $_SESSION['qrupload']['document_ids'] = $QRUploadKey->syncAndGetDocumentIds();
            $_SESSION['qrupload']['user_id'] = $QRUploadKey->user_id;
            spawnurl($this->getRequest()->getBaseUrl().'/utils/qrupload/'.$this->params['qrkey'].'?initialized=');
        }

        // this is the background loop and upload handler for the fileuploader stuff.
        if (isset($this->params['ajaxaction'])) {
            if (isset($this->params['close_connection'])) {
                $QRUploadKey->is_closed = true;
                $QRUploadKey->save(array('is_closed'));
                echo json_encode(array('status' => 'closed'));
            } else {
                $upload_handler = new QRUploadHandler($_SESSION['qrupload']['document_ids'], $_SESSION['qrupload']['user_id'], $QRUploadKey->qruploadkey_value);
                $_SESSION['qrupload']['document_ids'] = $upload_handler->document_ids;
                $QRUploadKey->saveDocumentIds($_SESSION['qrupload']['document_ids']);
            }
            die();
        }

        // Handle the display of documents being uploaded. We need this here because we are not logged-in.
        if (isset($this->params['showdoc'])) {
            $Document = new DBTableRowDocument();
            if ($Document->getRecordById($this->params['document_id'])) {
                if ($Document->document_thumb_exists) { // this  is one way we decide if this is an image vs some other document type.
                    $fmt = isset($this->params['fmt']) ? $this->params['fmt'] : 'medium';
                    if ($fmt=='thumbnail') {
                        $Document->outputThumbnailImageToBrowser(true, false);
                    } else if ($fmt=='medium') {
                        $Document->outputMediumImageToBrowser(true, false);
                    }
                } else {
                    $Document->outputIconToBrowser();
                }
            } else {
                $this->view->errormessages = 'document not found.';
            }
            die();
        }

        // at this point 'initialized' is set, so we know the session vars are set.
        $this->view->document_ids = $_SESSION['qrupload']['document_ids'];
        $this->view->user_id = $QRUploadKey->user_id;
        $this->view->qruploadkey_value = $QRUploadKey->qruploadkey_value;

    }
}

<?php
/**
 *
 * PartForge Enterprise Groupware for recording parts and assemblies by serial number and version along with associated test data and comments.
 *
 * Copyright (C) 2013-2023 Randall C. Black <randy@blacksdesign.com>
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

    private function shouldUpgradeFrom($testversion)
    {
        return (getGlobal('databaseversion')==$testversion) && (intval(Zend_Registry::get('config')->databaseversion) > intval($testversion));
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
							) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4");
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
                    if ($databaseversion=='14') {
                        $msgs[] = 'Upgrading to version 15: New ability to email someoone a comment.';
                        DbSchema::getInstance()->mysqlQuery("CREATE TABLE IF NOT EXISTS `sendmessage` (
							  `sendmessage_id` int(11) NOT NULL AUTO_INCREMENT,
							  `comment_id` int(11) NOT NULL COMMENT 'This can be -1 if not associated with a comment',
                              `url` varchar(128) COMMENT 'This is the part of the target url that looks like /struct/io/12345',
                              `object_name` varchar(128) COMMENT 'This is something like Demo Part - DEM011',
                              `message_text` text DEFAULT NULL COMMENT 'contains a message to send. Normally this is set if comment_id is -1',
                              `from_user_id` int(11) NOT NULL,
                              `sent_on` datetime COMMENT 'null if this message has not been send yet',
							  PRIMARY KEY (`sendmessage_id`),
                              KEY `from_user_id` (`from_user_id`),
                              KEY `url` (`url`),
                              KEY `comment_id` (`comment_id`)
							) ENGINE=InnoDB  DEFAULT CHARSET=utf8");
                        DbSchema::getInstance()->mysqlQuery("CREATE TABLE IF NOT EXISTS `messagerecipient` (
							  `messagerecipient_id` int(11) NOT NULL AUTO_INCREMENT,
							  `sendmessage_id` int(11) NOT NULL,
                              `to_user_id` int(11) NOT NULL,
							  PRIMARY KEY (`messagerecipient_id`),
                              KEY `sendmessage_id` (`sendmessage_id`),
                              KEY `to_user_id` (`to_user_id`)
							) ENGINE=InnoDB  DEFAULT CHARSET=utf8");
                        $databaseversion = '15';
                        setGlobal('databaseversion', $databaseversion);
                    }
                    if ($databaseversion=='15') {
                        $msgs[] = 'Upgrading to version 16: New ability to sort the Procedure order.';
                        DbSchema::getInstance()->mysqlQuery("CREATE TABLE IF NOT EXISTS `proceduresortorder` (
							  `proceduresortorder_id` int(11) NOT NULL AUTO_INCREMENT,
							  `sort_order` int(11) NOT NULL COMMENT 'an integer to be sorted on',
                              `of_typeobject_id` int(11) NOT NULL,
                              `when_viewed_by_typeobject_id` int(11) NOT NULL,
                              `section_break` int(1) DEFAULT 0 COMMENT 'if 1, then this is the start of section so maybe add hr displays',
							  PRIMARY KEY (`proceduresortorder_id`),
                              KEY `of_typeobject_id` (`of_typeobject_id`),
                              KEY `when_viewed_by_typeobject_id` (`when_viewed_by_typeobject_id`)
							) ENGINE=InnoDB  DEFAULT CHARSET=utf8");
                        DbSchema::getInstance()->mysqlQuery("CREATE TABLE IF NOT EXISTS `proceduresorthistory` (
							  `proceduresorthistory_id` int(11) NOT NULL AUTO_INCREMENT,
							  `when_viewed_by_typeobject_id` int(11) NOT NULL,
                              `to_user_id` int(11) NOT NULL,
                              `record_created` datetime NOT NULL,
                              `sort_order_typeobject_ids` text DEFAULT NULL COMMENT 'archived comma sep list of typeobject_ids--not live.',
							  PRIMARY KEY (`proceduresorthistory_id`),
                              KEY `when_viewed_by_typeobject_id` (`when_viewed_by_typeobject_id`),
                              KEY `to_user_id` (`to_user_id`)
							) ENGINE=InnoDB  DEFAULT CHARSET=utf8");
                        $databaseversion = '16';
                        setGlobal('databaseversion', $databaseversion);
                    }
                    if ($databaseversion=='16') {
                        $msgs[] = 'Upgrading to version 17: cache_depth added for supporting tree view.';
                        DbSchema::getInstance()->mysqlQuery("ALTER TABLE itemobject ADD COLUMN cached_depth INT DEFAULT 0");
                        $databaseversion = '17';
                        setGlobal('databaseversion', $databaseversion);
                    }
                    if ($databaseversion=='17') {
                        // Problem: until now, database tables have been a mix of latin1 and utf8 but we've
                        // always been connected to the DB as latin1, but consistently send and expecting utf8
                        // data. This update attempts to unwind this without loosing anything.
                        $msgs[] = 'Upgrading to version 18: Converting all tables to self-consistent utf8mb4.';
                        $finished_work_col_key = 'dbver17_upgrade_finished_columns';
                        $finished_columns = getGlobal($finished_work_col_key);
                        $finished_columns = $finished_columns ? explode(',', $finished_columns) : array();
                        $finished_work_tbl_key = 'dbver17_upgrade_finished_tables';
                        $finished_tables = getGlobal($finished_work_tbl_key);
                        $finished_tables = $finished_tables ? explode(',', $finished_tables) : array();
                        $upgrade_success = true;

                        // get all the character fields and their character sets
                        $query = "SELECT TABLE_SCHEMA,
									TABLE_NAME,
									CCSA.CHARACTER_SET_NAME AS DEFAULT_CHAR_SET,
									COLUMN_NAME,
                                    COLUMN_KEY,
									COLUMN_TYPE,
                                    COLUMN_COMMENT,
                                    IS_NULLABLE,
                                    COLUMN_DEFAULT,
									C.CHARACTER_SET_NAME
								FROM information_schema.TABLES AS T
								JOIN information_schema.COLUMNS AS C USING (TABLE_SCHEMA, TABLE_NAME)
								JOIN information_schema.COLLATION_CHARACTER_SET_APPLICABILITY AS CCSA
									ON (T.TABLE_COLLATION = CCSA.COLLATION_NAME)
								WHERE TABLE_SCHEMA=SCHEMA()
								AND C.DATA_TYPE IN ('enum', 'varchar', 'char', 'text', 'mediumtext', 'longtext')
								ORDER BY TABLE_SCHEMA,
										TABLE_NAME,
										COLUMN_NAME";
                        $recs = DbSchema::getInstance()->getRecords('', $query);
                        // now each record contains a text or character fields that needs to be converted to utf8 after misusing it with a latin1 connection
                        $tables_to_convert = array();
                        foreach ($recs as $rec) {
                            $table = $rec['TABLE_NAME'];
                            $col = $rec['COLUMN_NAME'];
                            $coltype = $rec['COLUMN_TYPE'];
                            $colkey = $rec['COLUMN_KEY'];
                            $colcomment = $rec['COLUMN_COMMENT'];
                            $nullable = ($rec['IS_NULLABLE']=='YES');
                            $default = $rec['COLUMN_DEFAULT'];
                            $charset = $rec['CHARACTER_SET_NAME'];
                            if ($charset=='utf8mb3') {
                                $charset = 'utf8';
                            }
                            if (!is_null($default)) {
                                $default = trim($default, "'");
                            }
                            if ($default == 'NULL') {
                                $default = null;
                            }
                            if ($nullable) {
                                if (is_null($default)) {
                                    $valtype = " DEFAULT NULL";
                                } else {
                                    $valtype = " DEFAULT '{$default}'";
                                }
                            } else {
                                if (is_null($default)) {
                                    $valtype = " NOT NULL";
                                } else {
                                    $valtype = " NOT NULL DEFAULT '{$default}'";
                                }
                            }
                            $colcomment = $colcomment ? " COMMENT '{$colcomment}'" : '';
                            if (empty($colkey)) { // we only do this for non-key columns
                                if (in_array($table.'_'.$col, $finished_columns)) {
                                    $msgs[] = "The table {$table} and column {$col} were already processed. It will be skipped this time.";
                                } else {
                                    if ($charset=='utf8') {
                                        // we convert the column in the way we will do it down below and look for problems.
                                        $predicted_fail_recs = DbSchema::getInstance()->getRecords('',
                                                    "SELECT * FROM {$table} where (convert(binary convert({$col} using latin1) using utf8) IS NULL)
                                                    AND NOT ({$col} IS NULL)");
                                    } else { // it's latin1 so let's just see what happens if we cast it as utf8.
                                        $predicted_fail_recs = DbSchema::getInstance()->getRecords('',
                                                    "SELECT * FROM {$table} where (convert(binary {$col} using utf8) IS NULL)
                                                    AND NOT ({$col} IS NULL)");
                                    }
                                    $failed_rec_numbers = array();
                                    foreach ($predicted_fail_recs as $failrec) {
                                        $failrec = array_reverse($failrec);
                                        $failed_rec_numbers[] = array_pop($failrec); // should return the id column so we can say what record the error happened on
                                    }
                                    $warning_recs = DbSchema::getInstance()->getRecords('', "SHOW WARNINGS");
                                    if (count($failed_rec_numbers)>0) {
                                        $msgs[] = "Error: The column {$col} in table {$table} has illegal values that cannot be converted. The bad record numbers are ".implode(', ', $failed_rec_numbers).". Please correct these fields and rerun this Upgrade Procedure.";
                                        $upgrade_success = false;
                                    } elseif (count($warning_recs)>0) {
                                        foreach ($warning_recs as $warning_rec) {
                                            $msgs[] = "Error: A conversion error has been detected at column {$col} in table {$table}.".implode(', ', $warning_rec).".  Please correct this and rerun this Upgrade Procedure.";
                                            $upgrade_success = false;
                                        }
                                    } else {
                                        if ($charset=='utf8') {
                                            DbSchema::getInstance()->mysqlQuery("ALTER TABLE {$table} MODIFY {$col} {$coltype} CHARACTER SET latin1 {$valtype}{$colcomment}");
                                        }
                                        // ok so now the table looks like how we've been treating it in the past (as latin1) even though actual encoding is utf8
                                        // we do the following little dance to recast (without converting) to utf8
                                        DbSchema::getInstance()->mysqlQuery("ALTER TABLE {$table} CHANGE {$col} {$col} LONGBLOB");  // LONGBLOB should cover anything sizewise
                                        DbSchema::getInstance()->mysqlQuery("ALTER TABLE {$table} CHANGE {$col} {$col} {$coltype} CHARACTER SET utf8 {$valtype}{$colcomment}");
                                        if (!in_array($table, $tables_to_convert)) {
                                            $tables_to_convert[] = $table;
                                        }
                                        $finished_columns[] = $table.'_'.$col;
                                        setGlobal($finished_work_col_key, implode(',', $finished_columns));
                                    }
                                }
                            }
                        }
                        // finally we will convert the tables that's we've touched so far
                        foreach ($tables_to_convert as $table) {
                            if (in_array($table, $finished_tables)) {
                                $msgs[] = "The table {$table} was already processed. It will be skipped this time.";
                            } else {
                                try {
                                    DbSchema::getInstance()->mysqlQuery("ALTER TABLE {$table} CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci");
                                    $finished_tables[] = $table;
                                    setGlobal($finished_work_tbl_key, implode(',', $finished_tables));
                                } catch (Exception $e) {
                                    $msgs[] = "Error: An error was encountered with table {$table}: ".$e->getMessage().". Fix the problem and run upgrade process again.";
                                    $upgrade_success = false;
                                }
                            }
                        }

                        // one final thing is to convert everything to utf8mb4 which should go smoothly since we've already made sure it's in utf8 (a subset)
                        if ($upgrade_success) {
                            try {
                                foreach (DbSchema::getInstance()->getTableNames() as $table) {
                                    DbSchema::getInstance()->mysqlQuery("ALTER TABLE {$table} CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
                                }
                                DbSchema::getInstance()->mysqlQuery("ALTER DATABASE CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
                            } catch (Exception $e) {
                                $msgs[] = "An error was encountered cleaning up after conversion: ".$e->getMessage().". However, the upgrade is complete.";
                                $upgrade_success = false;
                            }
                        }

                        if ($upgrade_success) {
                            $databaseversion = '18';
                            setGlobal('databaseversion', $databaseversion);
                        }
                    }

                    if ($databaseversion=='18') {
                        $msgs[] = 'Upgrading to version 19: Increase width of serial number caption.';
                        DbSchema::getInstance()->mysqlQuery("ALTER TABLE typeversion CHANGE COLUMN serial_number_caption serial_number_caption varchar(255) DEFAULT NULL");
                        $databaseversion = '19';
                        setGlobal('databaseversion', $databaseversion);
                    }

                    if ($this->shouldUpgradeFrom('19')) {
                        $msgs[] = 'Upgrading to version 20: Add new change codes.';
                        DbSchema::getInstance()->mysqlQuery("DROP TABLE IF EXISTS changecode");
                        DbSchema::getInstance()->mysqlQuery("CREATE TABLE `changecode` (
						`change_code_id` int(11) NOT NULL AUTO_INCREMENT,
						`change_code` VARCHAR(4) NOT NULL,
                        `is_for_definitions` INT(1) NOT NULL,
                        `affects_released_definitions` INT(1) NOT NULL,
						`change_code_name` varchar(128) DEFAULT NULL,
						PRIMARY KEY (`change_code_id`),
						KEY `change_code` (`change_code`)
						) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;");
                        DbSchema::getInstance()->mysqlQuery("INSERT INTO `changecode` (`change_code_id`, `change_code`, `is_for_definitions`, `affects_released_definitions`, `change_code_name`) VALUES
						(1, 'DIO', 0, 0, 'Deleted an Item'),
						(2, 'DIV', 0, 0, 'Deleted Item Version'),
						(3, 'AIO', 0, 0, 'Added New Item'),
						(4, 'CIV', 0, 0, 'Changed Item Version'),
						(5, 'AIV', 0, 0, 'Added Item Version'),
						(6, 'ATO', 1, 0, 'Added New Definition'),
						(7, 'RTV', 1, 1, 'Released Definition Version'),
                        (8, 'VTV', 1, 1, 'Reverted Definition to Draft'),
						(9, 'OTO', 1, 1, 'Obsoleted Definition'),
                        (10, 'UTV', 1, 1, 'Un-Obsoleted Definition'),
						(11, 'CTV', 1, 0, 'Changed Definition Version'),
						(12, 'ATV', 1, 0, 'Added Definition Version'),
						(13, 'DTV', 1, 1, 'Deleted Released Definition Version'),
						(14, 'DTO', 1, 1, 'Deleted Released Definition'),
						(15, 'DDT', 1, 0, 'Deleted Draft Definition'),
						(16, 'AIC', 0, 0, 'Added Item Comment'),
						(17, 'CIC', 0, 0, 'Changed Item Comment'),
						(18, 'DIC', 0, 0, 'Deleted Item Comment'),
						(19, 'AIR', 0, 0, 'Became Used On'),
						(20, 'AIP', 0, 0, 'Added Procedure'),
						(21, 'ATC', 1, 0, 'Added Definition Comment'),
						(22, 'CTC', 1, 0, 'Changed Definition Comment'),
						(23, 'DTC', 1, 0, 'Deleted Definition Comment');");
                        DbSchema::getInstance()->mysqlQuery("ALTER TABLE changesubscription ADD COLUMN `exclude_change_codes` VARCHAR(255) DEFAULT NULL AFTER follow_items_too");
                        $databaseversion = '20';
                        setGlobal('databaseversion', $databaseversion);
                    }

                    if ($this->shouldUpgradeFrom('20')) {
                        $msgs[] = 'Upgrading to version 21: Add Dashboard.';
                        DbSchema::getInstance()->mysqlQuery("CREATE TABLE IF NOT EXISTS dashboardtable (
                            dashboardtable_id int(11) NOT NULL AUTO_INCREMENT,
                            user_id int(11) NOT NULL,
                            typeobject_id int(11) NOT NULL,
                            title VARCHAR(128) DEFAULT NULL,
                            include_fields longtext,
                            data_items longtext,
                            PRIMARY KEY (dashboardtable_id),
                            KEY user_id (user_id),
                            KEY typeobject_id (typeobject_id)
                        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;");
                        DbSchema::getInstance()->mysqlQuery("CREATE TABLE IF NOT EXISTS dashboard (
                            dashboard_id int(11) NOT NULL AUTO_INCREMENT,
                            user_id int(11) NOT NULL,
                            title VARCHAR(128) DEFAULT NULL,
                            is_public int(1) NOT NULL DEFAULT 0,
                            list_of_table_ids longtext,
                            list_of_closed_tables longtext,
                            record_created datetime,
                            PRIMARY KEY (dashboard_id),
                            KEY user_id (user_id)
                        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;");
                        $databaseversion = '21';
                        setGlobal('databaseversion', $databaseversion);
                    }

                    if ($this->shouldUpgradeFrom('21')) {
                        $msgs[] = 'Upgrading to version 22: Add Dashboard Ser Num Selector.';
                        DbSchema::getInstance()->mysqlQuery("ALTER TABLE dashboardtable ADD COLUMN include_only_itemobject_ids longtext AFTER typeobject_id");
                        DbSchema::getInstance()->mysqlQuery("ALTER TABLE dashboardtable ADD COLUMN autoadd_new_items INT DEFAULT 0 AFTER include_only_itemobject_ids");
                        DbSchema::getInstance()->mysqlQuery("ALTER TABLE dashboardtable ADD INDEX(autoadd_new_items)");
                        $databaseversion = '22';
                        setGlobal('databaseversion', $databaseversion);
                    }

                    if ($this->shouldUpgradeFrom('22')) {
                        $msgs[] = 'Upgrading to version 23: Add Dashboard My Notes column.';
                        DbSchema::getInstance()->mysqlQuery("CREATE TABLE IF NOT EXISTS dashboardcolumnnote (
                            dashboardcolumnnote_id int(11) NOT NULL AUTO_INCREMENT,
                            dashboardtable_id int(11) NOT NULL,
                            user_id int(11) NOT NULL,
                            typeobject_id int(11) NOT NULL,
                            itemobject_id int(11) NOT NULL,
                            value longtext,
                            record_modified datetime,
                            PRIMARY KEY (dashboardcolumnnote_id),
                            KEY dashboardtable_id (dashboardtable_id),
                            KEY user_id (user_id),
                            KEY typeobject_id (typeobject_id),
                            KEY itemobject_id (itemobject_id)
                        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8mb4;");
                        $databaseversion = '23';
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

##
## PartForge Enterprise Groupware for recording parts and assemblies by serial number and version along with associated test data and comments.
##
## Copyright (C) 2013-2016 Randall C. Black <randy@blacksdesign.com>
##
## This file is part of PartForge
##
## PartForge is free software: you can redistribute it and/or modify
## it under the terms of the GNU General Public License as published by
## the Free Software Foundation, either version 3 of the License, or
## any later version.
##
## PartForge is distributed in the hope that it will be useful,
## but WITHOUT ANY WARRANTY; without even the implied warranty of
## MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
## GNU General Public License for more details.
##
## You should have received a copy of the GNU General Public License
## along with PartForge.  If not, see <http://www.gnu.org/licenses/>.
##
## @license GPL-3.0+ <http://spdx.org/licenses/GPL-3.0+>
##
##
##	Every table has a primary index as the first entry.
##	Cross-referencing indexes between pages always have the same name and type INT.
##	Field names should look descriptive when underscores are removed and each first letter capitalized

--
-- Table structure for table `assigned_to_task`
--

CREATE TABLE `assigned_to_task` (
  `assigned_to_task_id` int(11) NOT NULL auto_increment,
  `group_task_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `link_password` varchar(8) default NULL,
  `notified_on` datetime default NULL,
  `reminded_on` datetime default NULL,
  `nevermind_on` datetime default NULL,
  `responded_on` datetime default NULL,
  PRIMARY KEY  (`assigned_to_task_id`),
  KEY `user_id` (`user_id`),
  KEY `group_task_id` (`group_task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

--
-- Dumping data for table `assigned_to_task`
--


-- --------------------------------------------------------

--
-- Table structure for table `changecode`
--

CREATE TABLE `changecode` (
  `change_code_id` int(11) NOT NULL auto_increment,
  `change_code` varchar(4) NOT NULL,
  `change_code_name` varchar(128) default NULL,
  PRIMARY KEY  (`change_code_id`),
  KEY `change_code` (`change_code`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=21 ;

--
-- Dumping data for table `changecode`
--

INSERT INTO `changecode` VALUES(1, 'DIO', 'Deleted an Item');
INSERT INTO `changecode` VALUES(2, 'DIV', 'Deleted Item Version');
INSERT INTO `changecode` VALUES(3, 'AIO', 'Added New Item');
INSERT INTO `changecode` VALUES(4, 'CIV', 'Changed Item Version');
INSERT INTO `changecode` VALUES(5, 'AIV', 'Added Item Version');
INSERT INTO `changecode` VALUES(6, 'ATO', 'Added New Definition');
INSERT INTO `changecode` VALUES(7, 'RTV', 'Released Definition Version');
INSERT INTO `changecode` VALUES(8, 'OTO', 'Obsoleted Definition');
INSERT INTO `changecode` VALUES(9, 'CTV', 'Changed Definition Version');
INSERT INTO `changecode` VALUES(10, 'ATV', 'Added Definition Version');
INSERT INTO `changecode` VALUES(11, 'DTV', 'Deleted Definition Version');
INSERT INTO `changecode` VALUES(12, 'DTO', 'Deleted a Definition');
INSERT INTO `changecode` VALUES(13, 'AIC', 'Added Item Comment');
INSERT INTO `changecode` VALUES(14, 'CIC', 'Changed Item Comment');
INSERT INTO `changecode` VALUES(15, 'DIC', 'Deleted Item Comment');
INSERT INTO `changecode` VALUES(16, 'AIR', 'Became Used On');
INSERT INTO `changecode` VALUES(17, 'AIP', 'Added Procedure');
INSERT INTO `changecode` VALUES(18, 'ATC', 'Added Definition Comment');
INSERT INTO `changecode` VALUES(19, 'CTC', 'Changed Definition Comment');
INSERT INTO `changecode` VALUES(20, 'DTC', 'Deleted Definition Comment');

-- --------------------------------------------------------

--
-- Table structure for table `changelog`
--

CREATE TABLE `changelog` (
  `changelog_id` int(11) NOT NULL auto_increment,
  `user_id` int(11) NOT NULL,
  `changed_on` datetime NOT NULL,
  `desc_typeversion_id` int(11) default NULL,
  `desc_partnumber_alias` int(11) default NULL,
  `desc_itemversion_id` int(11) default NULL,
  `desc_typecategory_id` int(11) default NULL,
  `desc_comment_id` int(11) default NULL,
  `desc_text` varchar(255) default NULL,
  `locator_prefix` varchar(2) default NULL,
  `trigger_itemobject_id` int(11) default NULL,
  `trigger_typeobject_id` int(11) default NULL,
  `change_code` varchar(4) NOT NULL,
  PRIMARY KEY  (`changelog_id`),
  KEY `user_id` (`user_id`),
  KEY `trigger_itemobject_id` (`trigger_itemobject_id`),
  KEY `trigger_typeobject_id` (`trigger_typeobject_id`),
  KEY `change_code` (`change_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

--
-- Dumping data for table `changelog`
--


-- --------------------------------------------------------

--
-- Table structure for table `changenotifyqueue`
--

CREATE TABLE `changenotifyqueue` (
  `changenotifyqueue_id` int(11) NOT NULL auto_increment,
  `user_id` int(11) NOT NULL,
  `changelog_id` int(11) NOT NULL,
  `added_on` datetime NOT NULL,
  PRIMARY KEY  (`changenotifyqueue_id`),
  KEY `user_id` (`user_id`),
  KEY `changelog_id` (`changelog_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

--
-- Dumping data for table `changenotifyqueue`
--


-- --------------------------------------------------------

--
-- Table structure for table `changesubscription`
--

CREATE TABLE `changesubscription` (
  `changesubscription_id` int(11) NOT NULL auto_increment,
  `user_id` int(11) NOT NULL,
  `added_on` datetime NOT NULL,
  `itemobject_id` int(11) default NULL,
  `typeobject_id` int(11) default NULL,
  `notify_instantly` int(1) default '0',
  `notify_daily` int(1) default '0',
  PRIMARY KEY  (`changesubscription_id`),
  KEY `user_id` (`user_id`),
  KEY `itemobject_id` (`itemobject_id`),
  KEY `typeobject_id` (`typeobject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

--
-- Dumping data for table `changesubscription`
--


-- --------------------------------------------------------

--
-- Table structure for table `comment`
--

CREATE TABLE `comment` (
  `comment_id` int(11) NOT NULL auto_increment,
  `user_id` int(11) NOT NULL COMMENT 'who created this version',
  `proxy_user_id` int(11) NOT NULL default '-1',
  `itemobject_id` int(11) NOT NULL,
  `record_created` datetime default NULL,
  `comment_text` longtext,
  `comment_added` datetime default NULL,
  PRIMARY KEY  (`comment_id`),
  KEY `user_id` (`user_id`),
  KEY `itemobject_id` (`itemobject_id`),
  KEY `proxy_user_id` (`proxy_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

--
-- Dumping data for table `comment`
--


-- --------------------------------------------------------

--
-- Table structure for table `document`
--

CREATE TABLE `document` (
  `document_id` int(11) NOT NULL auto_increment,
  `comment_id` int(11) NOT NULL,
  `document_displayed_filename` varchar(64) default NULL,
  `document_stored_filename` varchar(255) default NULL,
  `document_stored_path` varchar(64) default '',
  `document_thumb_exists` int(1) default NULL,
  `optional_description` varchar(32) default NULL,
  `document_filesize` int(11) default NULL,
  `document_file_type` varchar(255) default NULL,
  `document_date_added` datetime NOT NULL,
  `user_id` int(11) default NULL,
  `document_path_db_key` int(2) default NULL,
  PRIMARY KEY  (`document_id`),
  KEY `comment_id` (`comment_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

--
-- Dumping data for table `document`
--


-- --------------------------------------------------------

--
-- Table structure for table `eventlog`
--

CREATE TABLE `eventlog` (
  `event_log_id` int(11) NOT NULL auto_increment,
  `event_log_date_added` datetime NOT NULL,
  `event_log_notify` int(1) default '0',
  `event_log_text` text,
  PRIMARY KEY  (`event_log_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

--
-- Dumping data for table `eventlog`
--


-- --------------------------------------------------------

--
-- Table structure for table `globals`
--

CREATE TABLE `globals` (
  `globals_id` int(11) NOT NULL auto_increment,
  `gl_key` varchar(64) default NULL,
  `gl_value` text,
  PRIMARY KEY  (`globals_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=3 ;

--
-- Dumping data for table `globals`
--

INSERT INTO `globals` VALUES(1, 'last_task_run', '2017-02-08 21:39:17');
INSERT INTO `globals` VALUES(2, 'databaseversion', '4');

-- --------------------------------------------------------

--
-- Table structure for table `group_task`
--

CREATE TABLE `group_task` (
  `group_task_id` int(11) NOT NULL auto_increment,
  `class_name` varchar(64) NOT NULL,
  `created_on` datetime NOT NULL,
  `closed_on` datetime default NULL,
  `title` text,
  `redirect_url` varchar(255) default NULL,
  PRIMARY KEY  (`group_task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

--
-- Dumping data for table `group_task`
--


-- --------------------------------------------------------

--
-- Table structure for table `help`
--

CREATE TABLE `help` (
  `help_id` int(11) NOT NULL auto_increment,
  `controller_name` varchar(255) default NULL,
  `action_name` varchar(255) default NULL,
  `table_name` varchar(255) default NULL,
  `help_tip` varchar(255) default NULL,
  `help_markup` text,
  PRIMARY KEY  (`help_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

--
-- Dumping data for table `help`
--


-- --------------------------------------------------------

--
-- Table structure for table `itemcomponent`
--

CREATE TABLE `itemcomponent` (
  `itemcomponent_id` int(11) NOT NULL auto_increment,
  `belongs_to_itemversion_id` int(11) NOT NULL,
  `has_an_itemobject_id` int(11) NOT NULL,
  `component_name` varchar(80) default NULL COMMENT 'field name of this component in the dictionary',
  PRIMARY KEY  (`itemcomponent_id`),
  KEY `belongs_to_itemversion_id` (`belongs_to_itemversion_id`),
  KEY `has_an_itemobject_id` (`has_an_itemobject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

--
-- Dumping data for table `itemcomponent`
--


-- --------------------------------------------------------

--
-- Table structure for table `itemobject`
--

CREATE TABLE `itemobject` (
  `itemobject_id` int(11) NOT NULL auto_increment,
  `cached_current_itemversion_id` int(11) default NULL COMMENT 'cached pointer to entry in itemversion table that has latest effective date',
  `cached_first_ver_date` datetime default NULL,
  `cached_created_by` varchar(128) default NULL,
  `cached_last_ref_date` datetime default NULL,
  `cached_last_ref_person` varchar(128) default NULL,
  `cached_last_comment_date` datetime default NULL,
  `cached_last_comment_person` varchar(128) default NULL,
  PRIMARY KEY  (`itemobject_id`),
  KEY `cached_current_itemversion_id` (`cached_current_itemversion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

--
-- Dumping data for table `itemobject`
--


-- --------------------------------------------------------

--
-- Table structure for table `itemversion`
--

CREATE TABLE `itemversion` (
  `itemversion_id` int(11) NOT NULL auto_increment,
  `itemobject_id` int(11) NOT NULL,
  `item_serial_number` varchar(64) default NULL,
  `disposition` varchar(12) default '',
  `cached_serial_number_value` int(11) default NULL,
  `typeversion_id` int(11) NOT NULL,
  `partnumber_alias` int(11) NOT NULL default '0',
  `effective_date` datetime default NULL COMMENT 'at what time did this item configuration become effective',
  `user_id` int(11) NOT NULL COMMENT 'who created this version',
  `proxy_user_id` int(11) NOT NULL default '-1',
  `record_created` datetime default NULL,
  `dictionary_overrides` longtext,
  `item_data` longtext,
  PRIMARY KEY  (`itemversion_id`),
  KEY `itemobject_id` (`itemobject_id`),
  KEY `typeversion_id` (`typeversion_id`),
  KEY `user_id` (`user_id`),
  KEY `item_serial_number` (`item_serial_number`),
  KEY `cached_serial_number_value` (`cached_serial_number_value`),
  KEY `proxy_user_id` (`proxy_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

--
-- Dumping data for table `itemversion`
--


-- --------------------------------------------------------

--
-- Table structure for table `itemversionarchive`
--

CREATE TABLE `itemversionarchive` (
  `itemversionarchive_id` int(11) NOT NULL auto_increment,
  `itemversion_id` int(11) NOT NULL,
  `cached_user_id` int(11) NOT NULL COMMENT 'who created this version',
  `record_created` datetime default NULL,
  `item_data` longtext COMMENT 'json representation of item fields',
  PRIMARY KEY  (`itemversionarchive_id`),
  KEY `itemversion_id` (`itemversion_id`),
  KEY `cached_user_id` (`cached_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

--
-- Dumping data for table `itemversionarchive`
--


-- --------------------------------------------------------

--
-- Table structure for table `partnumbercache`
--

CREATE TABLE `partnumbercache` (
  `partnumber_id` int(11) NOT NULL auto_increment,
  `part_number` varchar(64) default NULL,
  `part_description` varchar(255) default NULL,
  `typeversion_id` int(11) NOT NULL,
  `partnumber_alias` int(11) NOT NULL default '0',
  PRIMARY KEY  (`partnumber_id`),
  KEY `typeversion_id` (`typeversion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

--
-- Dumping data for table `partnumbercache`
--


-- --------------------------------------------------------

--
-- Table structure for table `reportcache`
--

CREATE TABLE `reportcache` (
  `reportcache_id` int(11) NOT NULL auto_increment,
  `class_name` varchar(255) default NULL,
  `last_run` datetime default NULL,
  PRIMARY KEY  (`reportcache_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

--
-- Dumping data for table `reportcache`
--


-- --------------------------------------------------------

--
-- Table structure for table `reportsubscription`
--

CREATE TABLE `reportsubscription` (
  `reportsubscription_id` int(11) NOT NULL auto_increment,
  `reportcache_id` int(11) default NULL,
  `user_id` int(11) default NULL,
  `subscription_interval_days` float default NULL,
  `last_sent` datetime default NULL,
  PRIMARY KEY  (`reportsubscription_id`),
  KEY `reportcache_id` (`reportcache_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

--
-- Dumping data for table `reportsubscription`
--


-- --------------------------------------------------------

--
-- Table structure for table `terminaltypeobject`
--

CREATE TABLE `terminaltypeobject` (
  `terminaltypeobject_id` int(11) NOT NULL auto_increment,
  `user_id` int(11) default NULL,
  `allowed_typeobject_id` int(11) default NULL,
  PRIMARY KEY  (`terminaltypeobject_id`),
  KEY `allowed_typeobject_id` (`allowed_typeobject_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

--
-- Dumping data for table `terminaltypeobject`
--


-- --------------------------------------------------------

--
-- Table structure for table `typecategory`
--

CREATE TABLE `typecategory` (
  `typecategory_id` int(11) NOT NULL auto_increment,
  `typecategory_name` varchar(64) default NULL,
  `event_stream_reference_prefix` varchar(64) default NULL,
  `is_user_procedure` int(1) default NULL,
  `has_a_serial_number` int(1) default NULL,
  `has_a_disposition` int(1) default NULL,
  PRIMARY KEY  (`typecategory_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=3 ;

--
-- Dumping data for table `typecategory`
--

INSERT INTO `typecategory` VALUES(1, 'Procedure', '', 1, 0, 1);
INSERT INTO `typecategory` VALUES(2, 'Part', 'Became part of', 0, 1, 0);

-- --------------------------------------------------------

--
-- Table structure for table `typecomment`
--

CREATE TABLE `typecomment` (
  `comment_id` int(11) NOT NULL auto_increment,
  `user_id` int(11) NOT NULL COMMENT 'who created this version',
  `typeobject_id` int(11) NOT NULL,
  `record_created` datetime default NULL,
  `comment_text` longtext,
  `comment_added` datetime default NULL,
  PRIMARY KEY  (`comment_id`),
  KEY `user_id` (`user_id`),
  KEY `typeobject_id` (`typeobject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

--
-- Dumping data for table `typecomment`
--


-- --------------------------------------------------------

--
-- Table structure for table `typecomponent`
--

CREATE TABLE `typecomponent` (
  `typecomponent_id` int(11) NOT NULL auto_increment,
  `belongs_to_typeversion_id` int(11) NOT NULL,
  `component_name` varchar(64) default NULL COMMENT 'field name of this component in the dictionary',
  `caption` varchar(255) default NULL,
  `subcaption` varchar(255) default NULL,
  `featured` int(1) default NULL,
  `required` int(1) default NULL,
  PRIMARY KEY  (`typecomponent_id`),
  KEY `belongs_to_typeversion_id` (`belongs_to_typeversion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

--
-- Dumping data for table `typecomponent`
--


-- --------------------------------------------------------

--
-- Table structure for table `typecomponent_typeobject`
--

CREATE TABLE `typecomponent_typeobject` (
  `typecomponent_id` int(11) NOT NULL,
  `can_have_typeobject_id` int(11) NOT NULL,
  KEY `typecomponent_id` (`typecomponent_id`),
  KEY `can_have_typeobject_id` (`can_have_typeobject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `typecomponent_typeobject`
--


-- --------------------------------------------------------

--
-- Table structure for table `typedocument`
--

CREATE TABLE `typedocument` (
  `document_id` int(11) NOT NULL auto_increment,
  `typeobject_id` int(11) NOT NULL,
  `document_displayed_filename` varchar(64) default NULL,
  `document_stored_filename` varchar(255) default NULL,
  `document_stored_path` varchar(64) default '',
  `document_thumb_exists` int(1) default NULL,
  `optional_description` varchar(32) default NULL,
  `document_filesize` int(11) default NULL,
  `document_file_type` varchar(255) default NULL,
  `document_date_added` datetime NOT NULL,
  `user_id` int(11) default NULL,
  `document_path_db_key` int(2) default NULL,
  PRIMARY KEY  (`document_id`),
  KEY `typeobject_id` (`typeobject_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

--
-- Dumping data for table `typedocument`
--


-- --------------------------------------------------------

--
-- Table structure for table `typeobject`
--

CREATE TABLE `typeobject` (
  `typeobject_id` int(11) NOT NULL auto_increment,
  `cached_current_typeversion_id` int(11) default NULL COMMENT 'cached pointer to entry in itemversion table that has latest effective date',
  `cached_item_count` int(11) default NULL,
  `cached_next_serial_number` varchar(64) default NULL,
  `cached_hidden_fields` int(11) default '0',
  `typedisposition` varchar(1) NOT NULL default 'A' COMMENT 'A=Active, B=oBsolete',
  PRIMARY KEY  (`typeobject_id`),
  KEY `cached_current_typeversion_id` (`cached_current_typeversion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

--
-- Dumping data for table `typeobject`
--


-- --------------------------------------------------------

--
-- Table structure for table `typeversion`
--

CREATE TABLE `typeversion` (
  `typeversion_id` int(11) NOT NULL auto_increment,
  `typeobject_id` int(11) NOT NULL,
  `type_part_number` longtext,
  `type_description` longtext,
  `serial_number_format` varchar(64) default NULL,
  `serial_number_check_regex` varchar(64) default NULL,
  `serial_number_parse_regex` varchar(64) default NULL,
  `serial_number_caption` varchar(64) default NULL,
  `serial_number_type` int(11) default NULL,
  `typecategory_id` int(11) NOT NULL COMMENT 'is this type a 1=form or procedure or 2=part or assembly',
  `versionstatus` varchar(1) NOT NULL default 'A' COMMENT 'A=Active, D=Draft, R=Review',
  `effective_date` datetime default NULL COMMENT 'at what time did this type definition become effective',
  `user_id` int(11) NOT NULL COMMENT 'who created this version',
  `record_created` datetime default NULL,
  `modified_by_user_id` int(11) default NULL,
  `record_modified` datetime default NULL,
  `type_data_dictionary` longtext,
  `type_form_layout` longtext,
  PRIMARY KEY  (`typeversion_id`),
  KEY `typeobject_id` (`typeobject_id`),
  KEY `user_id` (`user_id`),
  KEY `typecategory_id` (`typecategory_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

--
-- Dumping data for table `typeversion`
--


-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `user_id` int(11) NOT NULL auto_increment,
  `user_enabled` int(1) NOT NULL default '1',
  `login_id` varchar(64) NOT NULL,
  `user_cryptpassword` varchar(64) default NULL,
  `login_count` int(11) NOT NULL default '0',
  `last_visit` datetime default NULL,
  `account_created` datetime NOT NULL,
  `user_type` varchar(16) default NULL,
  `pref_rows_per_page` int(11) default '30',
  `pref_view_category` varchar(64) default NULL,
  `first_name` varchar(64) default NULL,
  `last_name` varchar(64) default NULL,
  `email` varchar(64) default NULL,
  `comments` text,
  `cached_items_created_count` int(11) NOT NULL default '0',
  `has_temporary_password` int(1) NOT NULL default '0',
  `waiting_approval` int(1) NOT NULL default '0',
  PRIMARY KEY  (`user_id`),
  UNIQUE KEY `login_id` (`login_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=2 ;

--
-- Dumping data for table `user`
--

INSERT INTO `user` VALUES(1, 1, 'admin', '$1$As0.JB5.$yRHmc8nQxVcKM9QVhRV530', 1, '2015-05-18 20:50:59', '2015-05-18 20:48:42', 'Admin', 30, '', 'Administrative', 'User', '', '', 0, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `userpreferences`
--

CREATE TABLE `userpreferences` (
  `userpreference_id` int(11) NOT NULL auto_increment,
  `user_id` int(11) NOT NULL,
  `pref_key` varchar(63) NOT NULL,
  `pref_value` varchar(255) default NULL,
  PRIMARY KEY  (`userpreference_id`),
  KEY `pref_key` (`pref_key`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=2 ;

--
-- Dumping data for table `userpreferences`
--

INSERT INTO `userpreferences` VALUES(1, 1, 'pref_part_view_category', '*');

-- --------------------------------------------------------

--
-- Table structure for table `whats_new_user`
--

CREATE TABLE `whats_new_user` (
  `whats_new_user_id` int(11) NOT NULL auto_increment,
  `message_key` varchar(33) default NULL,
  `user_id` int(11) NOT NULL,
  `view_count` int(11) default NULL,
  `hide` int(1) default NULL,
  PRIMARY KEY  (`whats_new_user_id`),
  KEY `message_key` (`message_key`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

--
-- Dumping data for table `whats_new_user`
--

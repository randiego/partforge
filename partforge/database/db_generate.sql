##
## PartForge Enterprise Groupware for recording parts and assemblies by serial number and version along with associated test data and comments.
##
## Copyright (C) 2013-2023 Randall C. Black <randy@blacksdesign.com>
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
-- --------------------------------------------------------

--
-- Table structure for table `assigned_to_task`
--

DROP TABLE IF EXISTS `assigned_to_task`;
CREATE TABLE IF NOT EXISTS `assigned_to_task` (
  `assigned_to_task_id` int(11) NOT NULL AUTO_INCREMENT,
  `group_task_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `link_password` varchar(8) DEFAULT NULL,
  `notified_on` datetime DEFAULT NULL,
  `reminded_on` datetime DEFAULT NULL,
  `nevermind_on` datetime DEFAULT NULL,
  `responded_on` datetime DEFAULT NULL,
  PRIMARY KEY (`assigned_to_task_id`),
  KEY `user_id` (`user_id`),
  KEY `group_task_id` (`group_task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `changecode`
--

DROP TABLE IF EXISTS `changecode`;
CREATE TABLE IF NOT EXISTS `changecode` (
  `change_code_id` int(11) NOT NULL AUTO_INCREMENT,
  `change_code` varchar(4) NOT NULL,
  `change_code_name` varchar(128) DEFAULT NULL,
  PRIMARY KEY (`change_code_id`),
  KEY `change_code` (`change_code`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `changecode`
--

INSERT INTO `changecode` (`change_code_id`, `change_code`, `change_code_name`) VALUES
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
(20, 'DTC', 'Deleted Definition Comment');

-- --------------------------------------------------------

--
-- Table structure for table `changelog`
--

DROP TABLE IF EXISTS `changelog`;
CREATE TABLE IF NOT EXISTS `changelog` (
  `changelog_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `changed_on` datetime NOT NULL,
  `desc_typeversion_id` int(11) DEFAULT NULL,
  `desc_partnumber_alias` int(11) DEFAULT NULL,
  `desc_itemversion_id` int(11) DEFAULT NULL,
  `desc_typecategory_id` int(11) DEFAULT NULL,
  `desc_comment_id` int(11) DEFAULT NULL,
  `desc_text` varchar(255) DEFAULT NULL,
  `locator_prefix` varchar(2) DEFAULT NULL,
  `trigger_itemobject_id` int(11) DEFAULT NULL,
  `trigger_typeobject_id` int(11) DEFAULT NULL,
  `change_code` varchar(4) NOT NULL,
  PRIMARY KEY (`changelog_id`),
  KEY `user_id` (`user_id`),
  KEY `trigger_itemobject_id` (`trigger_itemobject_id`),
  KEY `trigger_typeobject_id` (`trigger_typeobject_id`),
  KEY `change_code` (`change_code`),
  KEY `changed_on` (`changed_on`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `changenotifyqueue`
--

DROP TABLE IF EXISTS `changenotifyqueue`;
CREATE TABLE IF NOT EXISTS `changenotifyqueue` (
  `changenotifyqueue_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `changelog_id` int(11) NOT NULL,
  `added_on` datetime NOT NULL,
  PRIMARY KEY (`changenotifyqueue_id`),
  KEY `user_id` (`user_id`),
  KEY `changelog_id` (`changelog_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `changesubscription`
--

DROP TABLE IF EXISTS `changesubscription`;
CREATE TABLE IF NOT EXISTS `changesubscription` (
  `changesubscription_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `added_on` datetime NOT NULL,
  `itemobject_id` int(11) DEFAULT NULL,
  `typeobject_id` int(11) DEFAULT NULL,
  `follow_items_too` int(1) DEFAULT '1',
  `notify_instantly` int(1) DEFAULT '0',
  `notify_daily` int(1) DEFAULT '0',
  PRIMARY KEY (`changesubscription_id`),
  KEY `user_id` (`user_id`),
  KEY `itemobject_id` (`itemobject_id`),
  KEY `typeobject_id` (`typeobject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `comment`
--

DROP TABLE IF EXISTS `comment`;
CREATE TABLE IF NOT EXISTS `comment` (
  `comment_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT 'who created this version',
  `proxy_user_id` int(11) NOT NULL DEFAULT '-1',
  `itemobject_id` int(11) NOT NULL,
  `record_created` datetime DEFAULT NULL,
  `comment_text` longtext,
  `comment_added` datetime DEFAULT NULL,
  `is_fieldcomment` int(1) DEFAULT '0',
  PRIMARY KEY (`comment_id`),
  KEY `user_id` (`user_id`),
  KEY `itemobject_id` (`itemobject_id`),
  KEY `proxy_user_id` (`proxy_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `document`
--

DROP TABLE IF EXISTS `document`;
CREATE TABLE IF NOT EXISTS `document` (
  `document_id` int(11) NOT NULL AUTO_INCREMENT,
  `comment_id` int(11) NOT NULL,
  `document_displayed_filename` varchar(255) DEFAULT NULL,
  `document_stored_filename` varchar(255) DEFAULT NULL,
  `document_stored_path` varchar(64) DEFAULT '',
  `document_thumb_exists` int(1) DEFAULT NULL,
  `optional_description` varchar(32) DEFAULT NULL,
  `document_filesize` int(11) DEFAULT NULL,
  `document_file_type` varchar(255) DEFAULT NULL,
  `document_date_added` datetime NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `document_path_db_key` int(2) DEFAULT NULL,
  PRIMARY KEY (`document_id`),
  KEY `comment_id` (`comment_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `eventlog`
--

DROP TABLE IF EXISTS `eventlog`;
CREATE TABLE IF NOT EXISTS `eventlog` (
  `event_log_id` int(11) NOT NULL AUTO_INCREMENT,
  `event_log_date_added` datetime NOT NULL,
  `event_log_notify` int(1) DEFAULT '0',
  `event_log_text` mediumtext,
  PRIMARY KEY (`event_log_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `globals`
--

DROP TABLE IF EXISTS `globals`;
CREATE TABLE IF NOT EXISTS `globals` (
  `globals_id` int(11) NOT NULL AUTO_INCREMENT,
  `gl_key` varchar(64) DEFAULT NULL,
  `gl_value` mediumtext,
  PRIMARY KEY (`globals_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `globals`
--

INSERT INTO `globals` (`globals_id`, `gl_key`, `gl_value`) VALUES
(1, 'databaseversion', '18'),
(2, 'dbver17_upgrade_finished_columns', 'assigned_to_task_link_password,changecode_change_code_name,changelog_desc_text,changelog_locator_prefix,comment_comment_text,document_document_displayed_filename,document_document_file_type,document_document_stored_filename,document_document_stored_path,document_optional_description,eventlog_event_log_text,globals_gl_key,globals_gl_value,group_task_class_name,group_task_redirect_url,group_task_title,help_action_name,help_controller_name,help_help_markup,help_help_tip,help_table_name,itemcomment_field_name,itemcomponent_component_name,itemobject_cached_created_by,itemobject_cached_last_comment_person,itemobject_cached_last_ref_person,itemversion_dictionary_overrides,itemversion_disposition,itemversion_item_data,itemversionarchive_changes_html,itemversionarchive_item_data,partnumbercache_part_description,partnumbercache_part_number,proceduresorthistory_sort_order_typeobject_ids,reportcache_class_name,sendmessage_message_text,sendmessage_object_name,typecategory_event_stream_reference_prefix,typecategory_typecategory_name,typecomment_comment_text,typecomponent_caption,typecomponent_component_name,typecomponent_subcaption,typedocument_document_displayed_filename,typedocument_document_file_type,typedocument_document_stored_filename,typedocument_document_stored_path,typedocument_optional_description,typeobject_cached_next_serial_number,typeobject_typedisposition,typeversion_serial_number_caption,typeversion_serial_number_check_regex,typeversion_serial_number_format,typeversion_serial_number_parse_regex,typeversion_type_data_dictionary,typeversion_type_description,typeversion_type_form_layout,typeversion_type_part_number,typeversion_versionstatus,user_comments,user_email,user_first_name,user_last_name,user_pref_view_category,user_user_cryptpassword,user_user_type,userpreferences_pref_value'),
(3, 'dbver17_upgrade_finished_tables', 'assigned_to_task,changecode,changelog,comment,document,eventlog,globals,group_task,help,itemcomment,itemcomponent,itemobject,itemversion,itemversionarchive,partnumbercache,proceduresorthistory,reportcache,sendmessage,typecategory,typecomment,typecomponent,typedocument,typeobject,typeversion,user,userpreferences');

-- --------------------------------------------------------

--
-- Table structure for table `group_task`
--

DROP TABLE IF EXISTS `group_task`;
CREATE TABLE IF NOT EXISTS `group_task` (
  `group_task_id` int(11) NOT NULL AUTO_INCREMENT,
  `class_name` varchar(64) NOT NULL,
  `created_on` datetime NOT NULL,
  `closed_on` datetime DEFAULT NULL,
  `title` mediumtext,
  `redirect_url` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`group_task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `help`
--

DROP TABLE IF EXISTS `help`;
CREATE TABLE IF NOT EXISTS `help` (
  `help_id` int(11) NOT NULL AUTO_INCREMENT,
  `controller_name` varchar(255) DEFAULT NULL,
  `action_name` varchar(255) DEFAULT NULL,
  `table_name` varchar(255) DEFAULT NULL,
  `help_tip` varchar(255) DEFAULT NULL,
  `help_markup` mediumtext,
  PRIMARY KEY (`help_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `itemcomment`
--

DROP TABLE IF EXISTS `itemcomment`;
CREATE TABLE IF NOT EXISTS `itemcomment` (
  `itemcomment_id` int(11) NOT NULL AUTO_INCREMENT,
  `belongs_to_itemversion_id` int(11) NOT NULL,
  `field_name` varchar(80) DEFAULT NULL COMMENT 'field name of this itemcomment in the dictionary',
  `has_a_comment_id` int(11) NOT NULL,
  PRIMARY KEY (`itemcomment_id`),
  KEY `belongs_to_itemversion_id` (`belongs_to_itemversion_id`),
  KEY `has_a_comment_id` (`has_a_comment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `itemcomponent`
--

DROP TABLE IF EXISTS `itemcomponent`;
CREATE TABLE IF NOT EXISTS `itemcomponent` (
  `itemcomponent_id` int(11) NOT NULL AUTO_INCREMENT,
  `belongs_to_itemversion_id` int(11) NOT NULL,
  `has_an_itemobject_id` int(11) NOT NULL,
  `component_name` varchar(80) DEFAULT NULL COMMENT 'field name of this component in the dictionary',
  PRIMARY KEY (`itemcomponent_id`),
  KEY `belongs_to_itemversion_id` (`belongs_to_itemversion_id`),
  KEY `has_an_itemobject_id` (`has_an_itemobject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `itemobject`
--

DROP TABLE IF EXISTS `itemobject`;
CREATE TABLE IF NOT EXISTS `itemobject` (
  `itemobject_id` int(11) NOT NULL AUTO_INCREMENT,
  `cached_current_itemversion_id` int(11) DEFAULT NULL COMMENT 'cached pointer to entry in itemversion table that has latest effective date',
  `cached_first_ver_date` datetime DEFAULT NULL,
  `cached_created_by` varchar(128) DEFAULT NULL,
  `cached_last_ref_date` datetime DEFAULT NULL,
  `cached_last_ref_person` varchar(128) DEFAULT NULL,
  `cached_last_comment_date` datetime DEFAULT NULL,
  `cached_last_comment_person` varchar(128) DEFAULT NULL,
  `validation_cache_is_valid` int(11) DEFAULT '0',
  `validated_on` datetime DEFAULT NULL,
  `cached_has_validation_errors` int(11) DEFAULT '0',
  `cached_depth` int(11) DEFAULT '0',
  PRIMARY KEY (`itemobject_id`),
  KEY `cached_current_itemversion_id` (`cached_current_itemversion_id`),
  KEY `validated_on` (`validated_on`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `itemversion`
--

DROP TABLE IF EXISTS `itemversion`;
CREATE TABLE IF NOT EXISTS `itemversion` (
  `itemversion_id` int(11) NOT NULL AUTO_INCREMENT,
  `itemobject_id` int(11) NOT NULL,
  `item_serial_number` varchar(64) DEFAULT NULL,
  `disposition` varchar(12) DEFAULT '',
  `cached_serial_number_value` int(11) DEFAULT NULL,
  `typeversion_id` int(11) NOT NULL,
  `partnumber_alias` int(11) NOT NULL DEFAULT '0',
  `effective_date` datetime DEFAULT NULL COMMENT 'at what time did this item configuration become effective',
  `user_id` int(11) NOT NULL COMMENT 'who created this version',
  `proxy_user_id` int(11) NOT NULL DEFAULT '-1',
  `record_created` datetime DEFAULT NULL,
  `dictionary_overrides` longtext,
  `item_data` longtext,
  PRIMARY KEY (`itemversion_id`),
  KEY `itemobject_id` (`itemobject_id`),
  KEY `typeversion_id` (`typeversion_id`),
  KEY `user_id` (`user_id`),
  KEY `item_serial_number` (`item_serial_number`),
  KEY `cached_serial_number_value` (`cached_serial_number_value`),
  KEY `proxy_user_id` (`proxy_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `itemversionarchive`
--

DROP TABLE IF EXISTS `itemversionarchive`;
CREATE TABLE IF NOT EXISTS `itemversionarchive` (
  `itemversionarchive_id` int(11) NOT NULL AUTO_INCREMENT,
  `itemversion_id` int(11) NOT NULL,
  `cached_user_id` int(11) NOT NULL COMMENT 'who created this version',
  `record_created` datetime DEFAULT NULL,
  `original_record_created` datetime DEFAULT NULL,
  `item_data` longtext COMMENT 'json representation of item fields',
  `changes_html` mediumtext,
  PRIMARY KEY (`itemversionarchive_id`),
  KEY `itemversion_id` (`itemversion_id`),
  KEY `cached_user_id` (`cached_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `messagerecipient`
--

DROP TABLE IF EXISTS `messagerecipient`;
CREATE TABLE IF NOT EXISTS `messagerecipient` (
  `messagerecipient_id` int(11) NOT NULL AUTO_INCREMENT,
  `sendmessage_id` int(11) NOT NULL,
  `to_user_id` int(11) NOT NULL,
  PRIMARY KEY (`messagerecipient_id`),
  KEY `sendmessage_id` (`sendmessage_id`),
  KEY `to_user_id` (`to_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `partnumbercache`
--

DROP TABLE IF EXISTS `partnumbercache`;
CREATE TABLE IF NOT EXISTS `partnumbercache` (
  `partnumber_id` int(11) NOT NULL AUTO_INCREMENT,
  `part_number` varchar(64) DEFAULT NULL,
  `part_description` varchar(255) DEFAULT NULL,
  `typeversion_id` int(11) NOT NULL,
  `partnumber_alias` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`partnumber_id`),
  KEY `typeversion_id` (`typeversion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `proceduresorthistory`
--

DROP TABLE IF EXISTS `proceduresorthistory`;
CREATE TABLE IF NOT EXISTS `proceduresorthistory` (
  `proceduresorthistory_id` int(11) NOT NULL AUTO_INCREMENT,
  `when_viewed_by_typeobject_id` int(11) NOT NULL,
  `to_user_id` int(11) NOT NULL,
  `record_created` datetime NOT NULL,
  `sort_order_typeobject_ids` text COMMENT 'archived comma sep list of typeobject_ids--not live.',
  PRIMARY KEY (`proceduresorthistory_id`),
  KEY `when_viewed_by_typeobject_id` (`when_viewed_by_typeobject_id`),
  KEY `to_user_id` (`to_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `proceduresortorder`
--

DROP TABLE IF EXISTS `proceduresortorder`;
CREATE TABLE IF NOT EXISTS `proceduresortorder` (
  `proceduresortorder_id` int(11) NOT NULL AUTO_INCREMENT,
  `sort_order` int(11) NOT NULL COMMENT 'an integer to be sorted on',
  `of_typeobject_id` int(11) NOT NULL,
  `when_viewed_by_typeobject_id` int(11) NOT NULL,
  `section_break` int(1) DEFAULT '0' COMMENT 'if 1, then this is the start of section so maybe add hr displays',
  PRIMARY KEY (`proceduresortorder_id`),
  KEY `of_typeobject_id` (`of_typeobject_id`),
  KEY `when_viewed_by_typeobject_id` (`when_viewed_by_typeobject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `qruploaddocument`
--

DROP TABLE IF EXISTS `qruploaddocument`;
CREATE TABLE IF NOT EXISTS `qruploaddocument` (
  `qruploaddocument_id` int(11) NOT NULL AUTO_INCREMENT,
  `qruploadkey_id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  PRIMARY KEY (`qruploaddocument_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `qruploadkey`
--

DROP TABLE IF EXISTS `qruploadkey`;
CREATE TABLE IF NOT EXISTS `qruploadkey` (
  `qruploadkey_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL DEFAULT '-1',
  `created_on` datetime NOT NULL,
  `is_validated` int(1) NOT NULL DEFAULT '0',
  `is_closed` int(1) NOT NULL DEFAULT '0',
  `qruploadkey_value` varchar(32) DEFAULT NULL,
  PRIMARY KEY (`qruploadkey_id`),
  UNIQUE KEY `qruploadkey_value` (`qruploadkey_value`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `reportcache`
--

DROP TABLE IF EXISTS `reportcache`;
CREATE TABLE IF NOT EXISTS `reportcache` (
  `reportcache_id` int(11) NOT NULL AUTO_INCREMENT,
  `class_name` varchar(255) DEFAULT NULL,
  `last_run` datetime DEFAULT NULL,
  PRIMARY KEY (`reportcache_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `reportsubscription`
--

DROP TABLE IF EXISTS `reportsubscription`;
CREATE TABLE IF NOT EXISTS `reportsubscription` (
  `reportsubscription_id` int(11) NOT NULL AUTO_INCREMENT,
  `reportcache_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `subscription_interval_days` float DEFAULT NULL,
  `last_sent` datetime DEFAULT NULL,
  PRIMARY KEY (`reportsubscription_id`),
  KEY `reportcache_id` (`reportcache_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `sendmessage`
--

DROP TABLE IF EXISTS `sendmessage`;
CREATE TABLE IF NOT EXISTS `sendmessage` (
  `sendmessage_id` int(11) NOT NULL AUTO_INCREMENT,
  `comment_id` int(11) NOT NULL COMMENT 'This can be -1 if not associated with a comment',
  `url` varchar(128) DEFAULT NULL COMMENT 'This is the part of the target url that looks like /struct/io/12345',
  `object_name` varchar(128) DEFAULT NULL COMMENT 'This is something like Demo Part - DEM011',
  `message_text` text COMMENT 'contains a message to send. Normally this is set if comment_id is -1',
  `from_user_id` int(11) NOT NULL,
  `sent_on` datetime DEFAULT NULL COMMENT 'null if this message has not been send yet',
  PRIMARY KEY (`sendmessage_id`),
  KEY `from_user_id` (`from_user_id`),
  KEY `url` (`url`),
  KEY `comment_id` (`comment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `taskslog`
--

DROP TABLE IF EXISTS `taskslog`;
CREATE TABLE IF NOT EXISTS `taskslog` (
  `tasklog_id` int(11) NOT NULL AUTO_INCREMENT,
  `tl_key` varchar(64) DEFAULT NULL,
  `tl_last_run` datetime DEFAULT NULL,
  `tl_run_duration` float DEFAULT NULL,
  `tl_run_peak_memory` float DEFAULT NULL,
  PRIMARY KEY (`tasklog_id`),
  KEY `tl_key` (`tl_key`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `terminaltypeobject`
--

DROP TABLE IF EXISTS `terminaltypeobject`;
CREATE TABLE IF NOT EXISTS `terminaltypeobject` (
  `terminaltypeobject_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `allowed_typeobject_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`terminaltypeobject_id`),
  KEY `allowed_typeobject_id` (`allowed_typeobject_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `typecategory`
--

DROP TABLE IF EXISTS `typecategory`;
CREATE TABLE IF NOT EXISTS `typecategory` (
  `typecategory_id` int(11) NOT NULL AUTO_INCREMENT,
  `typecategory_name` varchar(64) DEFAULT NULL,
  `event_stream_reference_prefix` varchar(64) DEFAULT NULL,
  `is_user_procedure` int(1) DEFAULT NULL,
  `has_a_serial_number` int(1) DEFAULT NULL,
  `has_a_disposition` int(1) DEFAULT NULL,
  PRIMARY KEY (`typecategory_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `typecategory`
--

INSERT INTO `typecategory` (`typecategory_id`, `typecategory_name`, `event_stream_reference_prefix`, `is_user_procedure`, `has_a_serial_number`, `has_a_disposition`) VALUES
(1, 'Procedure', '', 1, 0, 1),
(2, 'Part', 'Became part of', 0, 1, 0);

-- --------------------------------------------------------

--
-- Table structure for table `typecomment`
--

DROP TABLE IF EXISTS `typecomment`;
CREATE TABLE IF NOT EXISTS `typecomment` (
  `comment_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT 'who created this version',
  `typeobject_id` int(11) NOT NULL,
  `record_created` datetime DEFAULT NULL,
  `comment_text` longtext,
  `comment_added` datetime DEFAULT NULL,
  PRIMARY KEY (`comment_id`),
  KEY `user_id` (`user_id`),
  KEY `typeobject_id` (`typeobject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `typecomponent`
--

DROP TABLE IF EXISTS `typecomponent`;
CREATE TABLE IF NOT EXISTS `typecomponent` (
  `typecomponent_id` int(11) NOT NULL AUTO_INCREMENT,
  `belongs_to_typeversion_id` int(11) NOT NULL,
  `component_name` varchar(64) DEFAULT NULL COMMENT 'field name of this component in the dictionary',
  `caption` varchar(255) DEFAULT NULL,
  `subcaption` varchar(255) DEFAULT NULL,
  `featured` int(1) DEFAULT NULL,
  `required` int(1) DEFAULT NULL,
  `max_uses` int(11) DEFAULT '1',
  PRIMARY KEY (`typecomponent_id`),
  KEY `belongs_to_typeversion_id` (`belongs_to_typeversion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `typecomponent_typeobject`
--

DROP TABLE IF EXISTS `typecomponent_typeobject`;
CREATE TABLE IF NOT EXISTS `typecomponent_typeobject` (
  `typecomponent_id` int(11) NOT NULL,
  `can_have_typeobject_id` int(11) NOT NULL,
  KEY `typecomponent_id` (`typecomponent_id`),
  KEY `can_have_typeobject_id` (`can_have_typeobject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `typedocument`
--

DROP TABLE IF EXISTS `typedocument`;
CREATE TABLE IF NOT EXISTS `typedocument` (
  `document_id` int(11) NOT NULL AUTO_INCREMENT,
  `typeobject_id` int(11) NOT NULL,
  `document_displayed_filename` varchar(255) DEFAULT NULL,
  `document_stored_filename` varchar(255) DEFAULT NULL,
  `document_stored_path` varchar(64) DEFAULT '',
  `document_thumb_exists` int(1) DEFAULT NULL,
  `optional_description` varchar(32) DEFAULT NULL,
  `document_filesize` int(11) DEFAULT NULL,
  `document_file_type` varchar(255) DEFAULT NULL,
  `document_date_added` datetime NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `document_path_db_key` int(2) DEFAULT NULL,
  PRIMARY KEY (`document_id`),
  KEY `typeobject_id` (`typeobject_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `typeobject`
--

DROP TABLE IF EXISTS `typeobject`;
CREATE TABLE IF NOT EXISTS `typeobject` (
  `typeobject_id` int(11) NOT NULL AUTO_INCREMENT,
  `cached_current_typeversion_id` int(11) DEFAULT NULL COMMENT 'cached pointer to entry in itemversion table that has latest effective date',
  `cached_item_count` int(11) DEFAULT NULL,
  `cached_next_serial_number` varchar(64) DEFAULT NULL,
  `cached_hidden_fields` int(11) DEFAULT '0',
  `typedisposition` varchar(1) NOT NULL DEFAULT 'A' COMMENT 'A=Active, B=oBsolete',
  PRIMARY KEY (`typeobject_id`),
  KEY `cached_current_typeversion_id` (`cached_current_typeversion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `typeversion`
--

DROP TABLE IF EXISTS `typeversion`;
CREATE TABLE IF NOT EXISTS `typeversion` (
  `typeversion_id` int(11) NOT NULL AUTO_INCREMENT,
  `typeobject_id` int(11) NOT NULL,
  `type_part_number` longtext,
  `type_description` longtext,
  `serial_number_format` varchar(64) DEFAULT NULL,
  `serial_number_check_regex` varchar(64) DEFAULT NULL,
  `serial_number_parse_regex` varchar(64) DEFAULT NULL,
  `serial_number_caption` varchar(64) DEFAULT NULL,
  `serial_number_type` int(11) DEFAULT NULL,
  `typecategory_id` int(11) NOT NULL COMMENT 'is this type a 1=form or procedure or 2=part or assembly',
  `versionstatus` varchar(1) NOT NULL DEFAULT 'A' COMMENT 'A=Active, D=Draft, R=Review',
  `effective_date` datetime DEFAULT NULL COMMENT 'at what time did this type definition become effective',
  `user_id` int(11) NOT NULL COMMENT 'who created this version',
  `record_created` datetime DEFAULT NULL,
  `modified_by_user_id` int(11) DEFAULT NULL,
  `record_modified` datetime DEFAULT NULL,
  `type_data_dictionary` longtext,
  `type_form_layout` longtext,
  PRIMARY KEY (`typeversion_id`),
  KEY `typeobject_id` (`typeobject_id`),
  KEY `user_id` (`user_id`),
  KEY `typecategory_id` (`typecategory_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

DROP TABLE IF EXISTS `user`;
CREATE TABLE IF NOT EXISTS `user` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_enabled` int(1) NOT NULL DEFAULT '1',
  `login_id` varchar(64) NOT NULL,
  `user_cryptpassword` varchar(64) DEFAULT NULL,
  `login_count` int(11) NOT NULL DEFAULT '0',
  `last_visit` datetime DEFAULT NULL,
  `account_created` datetime NOT NULL,
  `user_type` varchar(16) DEFAULT NULL,
  `pref_rows_per_page` int(11) DEFAULT '30',
  `pref_view_category` varchar(64) DEFAULT NULL,
  `first_name` varchar(64) DEFAULT NULL,
  `last_name` varchar(64) DEFAULT NULL,
  `email` varchar(64) DEFAULT NULL,
  `comments` mediumtext,
  `cached_items_created_count` int(11) NOT NULL DEFAULT '0',
  `has_temporary_password` int(1) NOT NULL DEFAULT '0',
  `waiting_approval` int(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `login_id` (`login_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`user_id`, `user_enabled`, `login_id`, `user_cryptpassword`, `login_count`, `last_visit`, `account_created`, `user_type`, `pref_rows_per_page`, `pref_view_category`, `first_name`, `last_name`, `email`, `comments`, `cached_items_created_count`, `has_temporary_password`, `waiting_approval`) VALUES
(1, 1, 'admin', '$1$As0.JB5.$yRHmc8nQxVcKM9QVhRV530', 1, '2015-05-18 20:50:59', '2015-05-18 20:48:42', 'Admin', 30, '', 'Administrative', 'User', '', '', 0, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `userpreferences`
--

DROP TABLE IF EXISTS `userpreferences`;
CREATE TABLE IF NOT EXISTS `userpreferences` (
  `userpreference_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `pref_key` varchar(63) NOT NULL,
  `pref_value` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`userpreference_id`),
  KEY `pref_key` (`pref_key`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `whats_new_user`
--

DROP TABLE IF EXISTS `whats_new_user`;
CREATE TABLE IF NOT EXISTS `whats_new_user` (
  `whats_new_user_id` int(11) NOT NULL AUTO_INCREMENT,
  `message_key` varchar(33) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `view_count` int(11) DEFAULT NULL,
  `hide` int(1) DEFAULT NULL,
  PRIMARY KEY (`whats_new_user_id`),
  KEY `message_key` (`message_key`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

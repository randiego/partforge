-- phpMyAdmin SQL Dump
-- version 3.5.1
-- http://www.phpmyadmin.net
--
-- Host: 127.0.0.1
-- Generation Time: Feb 25, 2016 at 07:49 AM
-- Server version: 5.5.24-log
-- PHP Version: 5.2.9-2

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

--
-- Database: `partforgedb`
--

-- --------------------------------------------------------

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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=5 ;

--
-- Dumping data for table `comment`
--

INSERT INTO `comment` VALUES(1, 3, -1, 5, '2015-05-19 07:36:33', 'One small mark on the left stick.  Replaced with spare--like new now.', '2015-05-19 07:36:33');
INSERT INTO `comment` VALUES(2, 3, -1, 9, '2015-05-19 07:39:39', 'Slight puffing.  Jenny says it''s fine.', '2015-05-19 07:39:39');
INSERT INTO `comment` VALUES(3, 1, -1, 26, '2015-05-19 07:58:10', 'QC Inspected.', '2015-05-19 07:58:10');
INSERT INTO `comment` VALUES(4, 1, -1, 25, '2015-05-19 07:58:36', 'QC Inspected.', '2015-05-19 07:58:36');

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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=2 ;

--
-- Dumping data for table `eventlog`
--

INSERT INTO `eventlog` VALUES(1, '2015-05-21 22:54:14', 0, '');

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

INSERT INTO `globals` VALUES(1, 'last_task_run', '2017-02-08 21:52:35');
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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=17 ;

--
-- Dumping data for table `itemcomponent`
--

INSERT INTO `itemcomponent` VALUES(1, 21, 1, 'camera');
INSERT INTO `itemcomponent` VALUES(2, 21, 12, 'camera_mount');
INSERT INTO `itemcomponent` VALUES(3, 21, 15, 'fuselage');
INSERT INTO `itemcomponent` VALUES(4, 21, 19, 'main_board');
INSERT INTO `itemcomponent` VALUES(5, 22, 2, 'camera');
INSERT INTO `itemcomponent` VALUES(6, 22, 13, 'camera_mount');
INSERT INTO `itemcomponent` VALUES(7, 22, 16, 'fuselage');
INSERT INTO `itemcomponent` VALUES(8, 22, 17, 'main_board');
INSERT INTO `itemcomponent` VALUES(9, 23, 21, 'drone');
INSERT INTO `itemcomponent` VALUES(10, 24, 22, 'drone');
INSERT INTO `itemcomponent` VALUES(11, 25, 7, 'battery');
INSERT INTO `itemcomponent` VALUES(12, 25, 22, 'drone');
INSERT INTO `itemcomponent` VALUES(13, 25, 5, 'transmitter');
INSERT INTO `itemcomponent` VALUES(14, 26, 8, 'battery');
INSERT INTO `itemcomponent` VALUES(15, 26, 21, 'drone');
INSERT INTO `itemcomponent` VALUES(16, 26, 4, 'transmitter');

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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=27 ;

--
-- Dumping data for table `itemobject`
--

INSERT INTO `itemobject` VALUES(1, 1, '2015-05-19 07:30:00', 'Sarah Greene', '2015-05-19 07:46:00', 'Justin Brown', NULL, NULL);
INSERT INTO `itemobject` VALUES(2, 2, '2015-05-19 07:31:00', 'Sarah Greene', '2015-05-19 07:47:00', 'Justin Brown', NULL, NULL);
INSERT INTO `itemobject` VALUES(3, 3, '2015-05-19 07:31:00', 'Sarah Greene', NULL, NULL, NULL, NULL);
INSERT INTO `itemobject` VALUES(4, 4, '2015-05-19 07:32:00', 'Sarah Greene', '2015-05-19 07:57:00', 'Justin Brown', NULL, NULL);
INSERT INTO `itemobject` VALUES(5, 5, '2015-05-19 07:33:00', 'Sarah Greene', '2015-05-19 07:56:00', 'Justin Brown', '2015-05-19 07:36:33', 'Sarah Greene');
INSERT INTO `itemobject` VALUES(6, 6, '2015-05-19 07:33:00', 'Sarah Greene', NULL, NULL, NULL, NULL);
INSERT INTO `itemobject` VALUES(7, 7, '2015-05-19 07:37:00', 'Sarah Greene', '2015-05-19 07:56:00', 'Justin Brown', NULL, NULL);
INSERT INTO `itemobject` VALUES(8, 8, '2015-05-19 07:39:00', 'Sarah Greene', '2015-05-19 07:57:00', 'Justin Brown', NULL, NULL);
INSERT INTO `itemobject` VALUES(9, 9, '2015-05-19 07:39:00', 'Sarah Greene', NULL, NULL, '2015-05-19 07:39:39', 'Sarah Greene');
INSERT INTO `itemobject` VALUES(10, 10, '2015-05-19 07:39:00', 'Sarah Greene', NULL, NULL, NULL, NULL);
INSERT INTO `itemobject` VALUES(11, 11, '2015-05-19 07:41:00', 'Justin Brown', NULL, NULL, NULL, NULL);
INSERT INTO `itemobject` VALUES(12, 12, '2015-05-19 07:41:00', 'Justin Brown', '2015-05-19 07:46:00', 'Justin Brown', NULL, NULL);
INSERT INTO `itemobject` VALUES(13, 13, '2015-05-19 07:41:00', 'Justin Brown', '2015-05-19 07:47:00', 'Justin Brown', NULL, NULL);
INSERT INTO `itemobject` VALUES(14, 14, '2015-05-19 07:43:00', 'Justin Brown', NULL, NULL, NULL, NULL);
INSERT INTO `itemobject` VALUES(15, 15, '2015-05-19 07:43:00', 'Justin Brown', '2015-05-19 07:46:00', 'Justin Brown', NULL, NULL);
INSERT INTO `itemobject` VALUES(16, 16, '2015-05-19 07:44:00', 'Justin Brown', '2015-05-19 07:47:00', 'Justin Brown', NULL, NULL);
INSERT INTO `itemobject` VALUES(17, 17, '2015-05-19 07:45:00', 'Justin Brown', '2015-05-19 07:47:00', 'Justin Brown', NULL, NULL);
INSERT INTO `itemobject` VALUES(18, 18, '2015-05-19 07:45:00', 'Justin Brown', NULL, NULL, NULL, NULL);
INSERT INTO `itemobject` VALUES(19, 19, '2015-05-19 07:46:00', 'Justin Brown', '2015-05-19 07:46:00', 'Justin Brown', NULL, NULL);
INSERT INTO `itemobject` VALUES(20, 20, '2015-05-19 07:46:00', 'Justin Brown', NULL, NULL, NULL, NULL);
INSERT INTO `itemobject` VALUES(21, 21, '2015-05-19 07:46:00', 'Justin Brown', '2015-05-19 07:57:00', 'Justin Brown', NULL, NULL);
INSERT INTO `itemobject` VALUES(22, 22, '2015-05-19 07:47:00', 'Justin Brown', '2015-05-19 07:56:00', 'Justin Brown', NULL, NULL);
INSERT INTO `itemobject` VALUES(23, 23, '2015-05-19 07:55:00', 'Justin Brown', NULL, NULL, NULL, NULL);
INSERT INTO `itemobject` VALUES(24, 24, '2015-05-19 07:56:00', 'Justin Brown', NULL, NULL, NULL, NULL);
INSERT INTO `itemobject` VALUES(25, 25, '2015-05-19 07:56:00', 'Justin Brown', NULL, NULL, '2015-05-19 07:58:36', 'Administrative User');
INSERT INTO `itemobject` VALUES(26, 26, '2015-05-19 07:57:00', 'Justin Brown', NULL, NULL, '2015-05-19 07:58:10', 'Administrative User');

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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=27 ;

--
-- Dumping data for table `itemversion`
--

INSERT INTO `itemversion` VALUES(1, 1, 'CAM001', '', 1, 1, 0, '2015-05-19 07:30:00', 3, -1, '2015-05-19 07:31:31', '', '{"manufacturer_serial_number":"X123456","revision":"B0"}');
INSERT INTO `itemversion` VALUES(2, 2, 'CAM002', '', 2, 1, 0, '2015-05-19 07:31:00', 3, -1, '2015-05-19 07:31:49', '', '{"manufacturer_serial_number":"X123445","revision":"B0"}');
INSERT INTO `itemversion` VALUES(3, 3, 'CAM003', '', 3, 1, 0, '2015-05-19 07:31:00', 3, -1, '2015-05-19 07:32:05', '', '{"manufacturer_serial_number":"X143456","revision":"B1"}');
INSERT INTO `itemversion` VALUES(4, 4, 'YY12945-2014-02', '', NULL, 6, 0, '2015-05-19 07:32:00', 3, -1, '2015-05-19 07:33:07', '', '{"revision":"A1"}');
INSERT INTO `itemversion` VALUES(5, 5, 'YY12045-2014-02', '', NULL, 6, 0, '2015-05-19 07:33:00', 3, -1, '2015-05-19 07:33:21', '', '{"revision":"A1"}');
INSERT INTO `itemversion` VALUES(6, 6, 'YY12946-2014-02', '', NULL, 6, 0, '2015-05-19 07:33:00', 3, -1, '2015-05-19 07:33:42', '', '{"revision":"A1"}');
INSERT INTO `itemversion` VALUES(7, 7, 'LP1K001', '', 1, 7, 0, '2015-05-19 07:37:00', 3, -1, '2015-05-19 07:38:58', '', '{"manufacturer":"Snake Bite"}');
INSERT INTO `itemversion` VALUES(8, 8, 'LP1K002', '', 2, 7, 0, '2015-05-19 07:39:00', 3, -1, '2015-05-19 07:39:06', '', '{"manufacturer":"Snake Bite"}');
INSERT INTO `itemversion` VALUES(9, 9, 'LP1K003', '', 3, 7, 0, '2015-05-19 07:39:00', 3, -1, '2015-05-19 07:39:18', '', '{"manufacturer":"Snake Bite"}');
INSERT INTO `itemversion` VALUES(10, 10, 'LP1K004', '', 4, 7, 0, '2015-05-19 07:39:00', 3, -1, '2015-05-19 07:39:32', '', '{"manufacturer":"Snake Bite"}');
INSERT INTO `itemversion` VALUES(11, 11, 'GIM001', '', 1, 2, 0, '2015-05-19 07:41:00', 2, -1, '2015-05-19 07:41:31', '', '{"revision":"B0"}');
INSERT INTO `itemversion` VALUES(12, 12, 'GIM002', '', 2, 2, 0, '2015-05-19 07:41:00', 2, -1, '2015-05-19 07:41:42', '', '{"revision":"B0"}');
INSERT INTO `itemversion` VALUES(13, 13, 'GIM003', '', 3, 2, 0, '2015-05-19 07:41:00', 2, -1, '2015-05-19 07:41:52', '', '{"revision":"B0"}');
INSERT INTO `itemversion` VALUES(14, 14, 'XCH001', '', 1, 4, 0, '2015-05-19 07:43:00', 2, -1, '2015-05-19 07:43:30', '', '{"body_color":"Orange","motors":"TraxxasQR1","prop_color":"Red+Black"}');
INSERT INTO `itemversion` VALUES(15, 15, 'XCH002', '', 2, 4, 0, '2015-05-19 07:43:00', 2, -1, '2015-05-19 07:43:56', '', '{"body_color":"Black","motors":"TraxxasQR1","prop_color":"Red+Black"}');
INSERT INTO `itemversion` VALUES(16, 16, 'XCH003', '', 3, 4, 0, '2015-05-19 07:44:00', 2, -1, '2015-05-19 07:44:11', '', '{"body_color":"Black","motors":"EstesDart7mm","prop_color":"Black"}');
INSERT INTO `itemversion` VALUES(17, 17, 'MCB001', '', 1, 3, 0, '2015-05-19 07:45:00', 2, -1, '2015-05-19 07:45:49', '', '{"firmware_version":"01.01.23","pcb_revision":"C0"}');
INSERT INTO `itemversion` VALUES(18, 18, 'MCB002', '', 2, 3, 0, '2015-05-19 07:45:00', 2, -1, '2015-05-19 07:46:03', '', '{"firmware_version":"01.01.23","pcb_revision":"C1"}');
INSERT INTO `itemversion` VALUES(19, 19, 'MCB003', '', 3, 3, 0, '2015-05-19 07:46:00', 2, -1, '2015-05-19 07:46:15', '', '{"firmware_version":"01.01.23","pcb_revision":"C1"}');
INSERT INTO `itemversion` VALUES(20, 20, 'MCB004', '', 4, 3, 0, '2015-05-19 07:46:00', 2, -1, '2015-05-19 07:46:25', '', '{"firmware_version":"01.01.23","pcb_revision":"C1"}');
INSERT INTO `itemversion` VALUES(21, 21, 'XTD001', '', 1, 5, 0, '2015-05-19 07:46:00', 2, -1, '2015-05-19 07:47:11', '', '');
INSERT INTO `itemversion` VALUES(22, 22, 'XTD002', '', 2, 5, 0, '2015-05-19 07:47:00', 2, -1, '2015-05-19 07:47:31', '', '');
INSERT INTO `itemversion` VALUES(23, 23, '', 'Pass', NULL, 9, 0, '2015-05-19 07:55:00', 2, -1, '2015-05-19 07:55:48', '', '{"hover_test":true,"low_battery_test":true}');
INSERT INTO `itemversion` VALUES(24, 24, '', 'Pass', NULL, 9, 0, '2015-05-19 07:56:00', 2, -1, '2015-05-19 07:56:27', '', '{"hover_test":true,"low_battery_test":true}');
INSERT INTO `itemversion` VALUES(25, 25, 'XRS001', '', 1, 8, 0, '2015-05-19 07:56:00', 2, -1, '2015-05-19 07:57:18', '', '');
INSERT INTO `itemversion` VALUES(26, 26, 'XRS002', '', 2, 8, 0, '2015-05-19 07:57:00', 2, -1, '2015-05-19 07:57:33', '', '');

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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=16 ;

--
-- Dumping data for table `partnumbercache`
--

INSERT INTO `partnumbercache` VALUES(1, '140-234', 'HD Camera', 1, 0);
INSERT INTO `partnumbercache` VALUES(2, '120-200', 'Gimbal Assembly (3cm)', 2, 0);
INSERT INTO `partnumbercache` VALUES(3, '110-100', 'Sym 5x Main Controller Board', 3, 0);
INSERT INTO `partnumbercache` VALUES(4, '100-200', 'Xtreme III Fuselage w Motors', 4, 0);
INSERT INTO `partnumbercache` VALUES(5, '090-120', 'Xtreme III Drone', 5, 0);
INSERT INTO `partnumbercache` VALUES(6, '050-100', 'Broadmaster 6 Channel Transmitter', 6, 0);
INSERT INTO `partnumbercache` VALUES(7, '040-100', '1000 mAh LiPo Pack', 7, 0);
INSERT INTO `partnumbercache` VALUES(8, '999-120', 'Xtreme III Ready To Ship', 8, 0);
INSERT INTO `partnumbercache` VALUES(9, 'TP-FLIGHT', 'Flight Test', 9, 0);

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
-- Table structure for table `taskslog`
--

CREATE TABLE `taskslog` (
  `tasklog_id` int(11) NOT NULL auto_increment,
  `tl_key` varchar(64) default NULL,
  `tl_last_run` datetime default NULL,
  `tl_run_duration` float default NULL,
  `tl_run_peak_memory` float default NULL,
  PRIMARY KEY  (`tasklog_id`),
  KEY `tl_key` (`tl_key`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=3 ;

--
-- Dumping data for table `taskslog`
--

INSERT INTO `taskslog` VALUES(1, 'service_inprocess_workflows', '2017-02-08 21:52:35', 0.00182104, 5.11239e+06);
INSERT INTO `taskslog` VALUES(2, 'process_watch_notifications', '2017-02-08 21:52:35', NULL, NULL);

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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=9 ;

--
-- Dumping data for table `typecomponent`
--

INSERT INTO `typecomponent` VALUES(1, 5, 'camera', '', '', 0, 0);
INSERT INTO `typecomponent` VALUES(2, 5, 'camera_mount', '', '', 0, 0);
INSERT INTO `typecomponent` VALUES(3, 5, 'fuselage', '', '', 0, 0);
INSERT INTO `typecomponent` VALUES(4, 5, 'main_board', '', '', 0, 0);
INSERT INTO `typecomponent` VALUES(5, 8, 'battery', '', '', 0, 0);
INSERT INTO `typecomponent` VALUES(6, 8, 'drone', '', '', 0, 0);
INSERT INTO `typecomponent` VALUES(7, 8, 'transmitter', '', '', 0, 0);
INSERT INTO `typecomponent` VALUES(8, 9, 'drone', '', '', 0, 0);

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

INSERT INTO `typecomponent_typeobject` VALUES(1, 1);
INSERT INTO `typecomponent_typeobject` VALUES(2, 2);
INSERT INTO `typecomponent_typeobject` VALUES(4, 3);
INSERT INTO `typecomponent_typeobject` VALUES(3, 4);
INSERT INTO `typecomponent_typeobject` VALUES(6, 5);
INSERT INTO `typecomponent_typeobject` VALUES(8, 5);
INSERT INTO `typecomponent_typeobject` VALUES(7, 6);
INSERT INTO `typecomponent_typeobject` VALUES(5, 7);

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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=10 ;

--
-- Dumping data for table `typeobject`
--

INSERT INTO `typeobject` VALUES(1, 1, 3, 'CAM004', 0, 'A');
INSERT INTO `typeobject` VALUES(2, 2, 3, 'GIM004', 0, 'A');
INSERT INTO `typeobject` VALUES(3, 3, 4, 'MCB005', 0, 'A');
INSERT INTO `typeobject` VALUES(4, 4, 3, 'XCH004', 0, 'A');
INSERT INTO `typeobject` VALUES(5, 5, 2, 'XTD003', 0, 'A');
INSERT INTO `typeobject` VALUES(6, 6, 3, '', 0, 'A');
INSERT INTO `typeobject` VALUES(7, 7, 4, 'LP1K005', 0, 'A');
INSERT INTO `typeobject` VALUES(8, 8, 2, 'XRS003', 0, 'A');
INSERT INTO `typeobject` VALUES(9, 9, 2, NULL, 0, 'A');

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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=10 ;

--
-- Dumping data for table `typeversion`
--

INSERT INTO `typeversion` VALUES(1, 1, '140-234', 'HD Camera', 'CAM###', '', '', '', 3, 2, 'A', '2015-05-18 21:49:00', 1, '2015-05-18 21:57:54', 1, '2015-05-18 21:57:54', '{"manufacturer_serial_number":{"type":"varchar","featured":"0","len":"32","required":"0","unique":"0"},"revision":{"type":"varchar","subcaption":"Revision letter and number (e.g., B45) from back","featured":"0","len":"32","required":"0","unique":"0"}}', '[{"type":"columns","columns":[{"name":"manufacturer_serial_number"},{"name":"revision"}]}]');
INSERT INTO `typeversion` VALUES(2, 2, '120-200', 'Gimbal Assembly (3cm)', 'GIM###', '', '', '', 3, 2, 'A', '2015-05-18 21:58:00', 1, '2015-05-18 22:00:20', 1, '2015-05-18 22:00:20', '{"revision":{"type":"varchar","featured":"0","len":"32","required":"0","unique":"0"}}', '[{"type":"columns","columns":[{"name":"revision"}]}]');
INSERT INTO `typeversion` VALUES(3, 3, '110-100', 'Sym 5x Main Controller Board', 'MCB###', '', '', '', 3, 2, 'A', '2015-05-18 22:03:00', 1, '2015-05-18 22:08:09', 1, '2015-05-18 22:08:09', '{"firmware_version":{"type":"varchar","subcaption":"from sticker on board (e.g., 01.02.35)","featured":"0","len":"32","required":"0","unique":"0"},"pcb_revision":{"type":"varchar","caption":"PCB Revision","subcaption":"board revisions","featured":"0","len":"32","required":"0","unique":"0"}}', '[{"type":"columns","columns":[{"name":"firmware_version"},{"name":"pcb_revision"}]}]');
INSERT INTO `typeversion` VALUES(4, 4, '100-200', 'Xtreme III Fuselage w Motors', 'XCH###', '', '', '', 3, 2, 'A', '2015-05-18 22:08:00', 1, '2015-05-18 22:17:54', 1, '2015-05-18 22:22:41', '{"body_color":{"type":"enum","featured":"0","options":{"Orange":"Orange","Black":"Black"},"required":"0"},"motors":{"type":"enum","featured":"0","options":{"TraxxasQR1":"Traxxas QR1","EstesDart7mm":"Estes Dart 3.7v 7mm"},"required":"0"},"prop_color":{"type":"enum","featured":"0","options":{"Red":"Red","Black":"Black","Red+Black":"Red+Black"},"required":"0"}}', '[{"type":"columns","columns":[{"name":"body_color"},{"name":"motors"}]},{"type":"columns","columns":[{"name":"prop_color"}]}]');
INSERT INTO `typeversion` VALUES(5, 5, '090-120', 'Xtreme III Drone', 'XTD###', '', '', '', 3, 2, 'A', '2015-05-18 22:18:00', 1, '2015-05-18 22:22:05', 1, '2015-05-18 22:22:05', '{}', '[{"type":"columns","columns":[{"name":"camera"},{"name":"camera_mount"}]},{"type":"columns","columns":[{"name":"fuselage"},{"name":"main_board"}]}]');
INSERT INTO `typeversion` VALUES(6, 6, '050-100', 'Broadmaster 6 Channel Transmitter', '', '', '', 'manufacturers SN on back', 0, 2, 'A', '2015-05-18 22:22:00', 1, '2015-05-18 22:25:02', 1, '2015-05-18 22:25:02', '{"revision":{"type":"varchar","subcaption":"on back of transmitter","featured":"0","len":"32","required":"0","unique":"0"}}', '[{"type":"columns","columns":[{"name":"revision"}]}]');
INSERT INTO `typeversion` VALUES(7, 7, '040-100', '1000 mAh LiPo Pack', 'LP1K###', '', '', '', 3, 2, 'A', '2015-05-18 22:26:00', 1, '2015-05-18 22:30:01', 1, '2015-05-18 22:30:01', '{"manufacturer":{"type":"varchar","featured":"0","len":"32","required":"0","unique":"0"}}', '[{"type":"columns","columns":[{"name":"manufacturer"}]}]');
INSERT INTO `typeversion` VALUES(8, 8, '999-120', 'Xtreme III Ready To Ship', 'XRS###', '', '', '', 3, 2, 'A', '2015-05-18 22:30:00', 1, '2015-05-18 22:32:42', 1, '2015-05-18 22:32:42', '{}', '[{"type":"columns","columns":[{"name":"battery"},{"name":"drone"}]},{"type":"columns","columns":[{"name":"transmitter"}]}]');
INSERT INTO `typeversion` VALUES(9, 9, 'TP-FLIGHT', 'Flight Test', '', '', '', '', NULL, 1, 'A', '2015-05-19 07:48:00', 1, '2015-05-19 07:51:29', 1, '2015-05-19 07:55:10', '{"hover_test":{"type":"boolean","subcaption":"orange battery.  hold in box for 2 minutes.","featured":"0","required":"0"},"low_battery_test":{"type":"boolean","subcaption":"red battery","featured":"0","required":"0"}}', '[{"type":"columns","columns":[{"name":"drone"}]},{"type":"html","html":"<p><strong>Note: </strong>For the following tests, the test batteries must have green lights on prep fixtures.</p>"},{"type":"columns","columns":[{"name":"hover_test"},{"name":"low_battery_test"}]}]');

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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=4 ;

--
-- Dumping data for table `user`
--

INSERT INTO `user` VALUES(1, 1, 'admin', '$1$As0.JB5.$yRHmc8nQxVcKM9QVhRV530', 3, '2015-05-19 07:23:21', '2015-05-18 20:48:42', 'Admin', 30, '', 'Administrative', 'User', '', '', 2, 0, 0);
INSERT INTO `user` VALUES(2, 1, 'justin', '$1$Bq3.0h5.$rYEq/atj3kiH0LdDjZWHB0', 0, NULL, '2015-05-19 07:24:33', 'Tech', 30, '', 'Justin', 'Brown', '', '', 16, 0, 0);
INSERT INTO `user` VALUES(3, 1, 'sarah', '$1$qS0.rm..$VVLumaEEgN91uBawFW4OC/', 0, NULL, '2015-05-19 07:27:57', 'Tech', 30, '', 'Sarah', 'Greene', '', '', 12, 0, 0);

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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=8 ;

--
-- Dumping data for table `userpreferences`
--


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
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=2 ;

--
-- Dumping data for table `whats_new_user`
--
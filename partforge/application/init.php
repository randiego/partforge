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

/* INIT.PHP - this should be called after all class definitions, function definitions, and after session_start */
$config = array();
$config['banner_array'] = array();   // an array of html banners to show at the top of the application.
$config['application_title'] = 'PartForge'; // appears in title tag and other places
$config['cached_code_version'] = '57';  // for css and js files, this appended as ?v=N to the end to force reload to browse.  Increment when css or js files changes.
$config['databaseversion'] = '4';
$config['config_for_testing'] = false; // Makes a few minor changes to improve testability when we are running as a test instance during automated testing.

$config['global_readonly'] = false;  // if false, then all users must log in to be able to view content.  If true, then it is only necessary to login to write.
$config['force_login_domain'] = false; // set to false if we don't want to insist on any particular domain name.  Set to, say www.mydomain.com if you want to redirect the login page to this domain instead
$config['db_query_dump_file'] = false; // set this to the absolute pathname of a file to force a save of every mysql query.  Normally used for testing.
$config['reports_output_directory'] = '/reports';  // this is under the document_path_base.  This is were the graph files and report and report data files are automatically saved.
$config['document_directory'] = '/documents'; // this is under the document_path_base.  This is where all documents and thumbs are stored
$config['document_path_db_key'] = '1';  // this is a code that helps with migration of databases when the /documents folder is not also migrated.  This key must match the field in the documents table or else the document is considered missing.
$config['local_testing'] = false;   // indicates that we are running in a debug environment and want to avoid timeouts as we step through or run long tests.

$config['logo_file'] = '/images/logo.png'; // graphic at top of all webpages.  It is relative to /public

$config['button_new_version_save'] = 'Save New Version';
$config['button_edit_version_save'] = 'Save'; 
$config['button_new_object_save'] = 'Save New'; 

$config['login_html_message'] = '';

$config['max_allowed_field_length'] = 80;

$config['use_instant_watch_queue'] = true;  // If true then queue up the "instant" watch notification for sending on the cron  (every minute?).  Otherwise they are send immediately.
$config['fake_cron_service'] = true; // If true, then if the cron task servicer has not been run sufficiently recently, then process the tasks on the next page fetch
$config['max_file_upload_size'] = 40*1024*1024;  // this is a browser defined maximum bytes one can upload. This should be smaller than the php limit.
$config['default_new_password'] = 'partforgepw'; // this is the password that is automatically assigneed to accounts which have had their password reset.
$config['allowed_to_keep_temp_pw'] = true;     // when user's password is reset and emailed to them, they are allowed to keep it (instead of being forced to do a new one.)
$config['lockout_all_users'] = false; 
$config['activity_timeout'] = 4*60*60; // if you haven't clicked around in this long, you will be logged out
$config['activity_timeout_terminal_user'] = 30*24*60*60; // This is for terminal type users.
$config['edit_form_keep_alive_interval'] = 20*60; // If an edit form is not saved in this amount of time, a refresh or ping to the server will be done by the browser
$config['delete_grace_in_sec'] = 3600*24;  // how many seconds after a record is created can we delete it?
$config['edit_grace_in_sec'] = 7*3600*24;  // how many seconds after a record is created can it be edited?
$config['recent_row_age'] = 7*3600*24; // add a light background to any rows in the parts, procedures, comments, or definitions pages that have changed or been created less than this time ago.
$config['admin_can_toggle_roles'] = false;  // Add the capability for an admin user to cycle between the user interfaces that are available to all the diffrent user types.
$config['show_old_proc_version_in_eventstream'] = false;
$config['show_old_part_version_in_eventstream'] = true;
$config['show_analyze_page'] = false;   // show the analyze tab (contains the reports and the Exporting and Table Joining Functions)
$config['warn_of_hidden_fields'] = true;   // when true, this shows red warnings when a component or fieldname is present in the def but not in the layout
$config['allow_self_register'] = true;     // show the "register for account" link on the login page.
$config['self_register_user_type'] = 'Guest'; // The user type 'Guest, Admin, Tech, Eng'
$config['self_register_require_approval'] = true;  // if false, the registree can log in right away. If true, they have to wait for the approver.
$config['allow_username_search'] = true; // if true allows a user who has forgotten their login ID to view all login IDs.

define( 'LOGGED_IN_USER_IS_CREATOR', '-1'); // used for db field user.proxy_user_id when there is no proxy user

$config['scheme'] = $config['local_testing'] ? 'http' : 'http';

error_reporting (E_ALL ^ E_NOTICE ^ E_USER_NOTICE);
ini_set('display_errors',1);

// get local configuration
require_once("../config.php");

// the read-only passwords default to the rw ones 
if (!$config['db_params']['ro_username']) {
	$config['db_params']['ro_username'] = $config['db_params']['username'];
	$config['db_params']['ro_password'] = $config['db_params']['password'];
}

$script_time = (isset($config['fake_date']) && $config['fake_date']) ? strtotime($config['fake_date']) : time();
Zend_Registry::set('script_time',$script_time);

if ($config['lockout_all_users']) {
	echo 'The System is Temporarily Unavailable.  To retry, click refresh in your browser.';
	die();
}

Zend_Registry::set('config', new Zend_Config($config));
// This helps with really long execution times.
if ($config['local_testing']) {
	set_time_limit(1200);
}

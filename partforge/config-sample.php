<?php
/**
 *
 * PartForge Enterprise Groupware for recording parts and assemblies by serial number and version along with associated test data and comments.
 *
 * Copyright (C) 2013-2015 Randall C. Black <randy@blacksdesign.com>
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

// email address of person an administrated person who can help a user who encounters an error.
$config['support_email'] = '***somebody@example.com***';

// email address of person silent error messages are sent to.
$config['webmaster_email'] = '***somebody@example.com***';

// system notices are send from this address.
$config['notices_from_email'] = '***posiblynoone@example.com***';

// this is a browser defined maximum bytes one can upload. This should be smaller than the php limit.
// $config['max_file_upload_size'] = 40*1024*1024;

// this is the password that is automatically assigneed to accounts which have had their password reset.
//$config['default_new_password'] = 'partforgepw';

// dead stop and show terse message that system is unavailable
//$config['lockout_all_users'] = true;

// graphic at top of all webpages.  It is relative to /public
//$config['logo_file'] = '/images/logo.png';

// special instructions to appear at the bottom of the login page
//$config['login_html_message'] = '<p>To browse with read-only access, use login=guest, password=guest</p>';

/*
 * NEEDS TO BE MADE INTO SOMETHING GENERIC WITH INSTRUCTIONS
 */

// this where email notifications are sent when someone registers.
$config['register_notification_email'] = '***somebody@example.com***';

// this is the absolute file path of the /public folder.  For example: '/var/www/html/partforge/public' or 'c:/wamp/www/partforge/public'
$config['document_path_base'] = '***c:/wamp/www/partforge/public***';

// the absolute file path of the reports folder.  For example: '/var/www/html/partforge/reports' or 'c:/wamp/www/partforge/reports'
$config['reports_classes_path'] = '***c:/wamp/www/partforge/reports***';

$config['db_params'] = array(
		'host' => 'localhost',  // host name
		'username' => '***partforgeuser***',     // database username
		'password' => '***partforgepw***',       // database password
		'ro_username' => '',  // database username readonly access within the report generator.  Use the same user and password if you do not want the added protection of running read-only when processing a report
		'ro_password' => '',    // database password readonly access
		'dbname' => '***partforgedb***',       // database name
);

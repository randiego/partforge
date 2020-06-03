<?php

/*
 * This installation file should be run from within the "partforge/public" drectory.  After you have run this installation script
 * to setup the database, you should delete it or move it to a directory that is not accessible by the webserver.
 */

/**
 *
 * PartForge Enterprise Groupware for recording parts and assemblies by serial number and version along with associated test data and comments.
 *
 * Copyright (C) 2013-2020 Randall C. Black <randy@blacksdesign.com>
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

session_start(); 
ob_start();
?>

<!DOCTYPE html>
<html>
<head>
<link type="text/css" rel="stylesheet" href="commonLayout.css" />
<title>PartForge Installer</title>
</head>
<body>
<div id="logobar" style="max-width:600px;">
<img class="logoheadimage" src="images/logo.png" title="PartForge">
</div>
<div class="textArea" style="max-width:600px;">

<?php

/*
 * The structure of the installation script was inspired by the example here:
 * http://tutsforweb.blogspot.com/2012/02/php-installation-script.html
 */


$step = (isset($_GET['step']) && $_GET['step'] != '') ? $_GET['step'] : '';
switch($step){
	case '1':
		step_1();
		break;
	case '2':
		step_2();
		break;
	case '3':
		step_3();
		break;
	case '4':
		step_4();
		break;
	case '5':
		step_5();
		break;
	default:
		$_SESSION['globals']=array();
		step_1();
}

function error_message($msg) {
	echo '<p class="event_error" style="font-weight: bold; padding: 10px;">'.$msg.'</p>';
}

function is_config_default($value) {
	return is_null($value) || !$value || (substr($value,0,3)=='***');   // strings are going to look like *** my value *** if the config.php file hasn't been initialized yet.
}

function step_1(){
 if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['agree'])){
	prep_step_2();
    header('Location: install.php?step=2');
    exit;
 }
 if($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['agree'])){
    error_message("You must agree to the license.");
 }
 ?>
	<h1>Step 1: Welcome to PartForge Installation</h1>
	<p>This wizard will walk you through some of the steps required to setup and configure PartForge.</p>  

	<p>PartForge is free software: you can redistribute it and/or modify
	 it under the terms of the GNU General Public License as published by
	 the Free Software Foundation, either version 3 of the License, or
	 any later version.
	 
	 PartForge is distributed in the hope that it will be useful,
	 but WITHOUT ANY WARRANTY; without even the implied warranty of
	 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	 GNU General Public License for more details.</p>
	 
	 <h2>GNU GENERAL PUBLIC LICENSE Version 3</h2>
	 <div style="max-height: 200px; width: auto; overflow: scroll; overflow-x: auto; border: 5px solid black; margin: 10px 0px 10px 0px;"><pre><?php echo file_get_contents('../LICENSE.txt');?></pre></div>
 
	 <p>The text of this license can also be found here: <a href="http://www.gnu.org/licenses" target="_blank">http://www.gnu.org/licenses</a>.</p>
	
	<form action="install.php?step=1" method="post">
		<p>
			I agree to the license <input type="checkbox" name="agree" />
		</p>
		<input type="submit" value="Continue" />
	</form>
	<?php 
}

function show_message($msg,$severity='E') {
	//$severity = E, W, I
	echo '<p style="'.($severity=='I' ? 'color: #080;' : ($severity=='E' ? 'color: #F00; font-weight:bold;' : 'color: #EE9E00; font-weight:bold;')).'">'.$msg.'</p>';
}

function php_ini_error($ini_param, $msg) {
	$value = ini_get($ini_param);
	show_message("PHP Configuration Setting '{$ini_param}' = '{$value}' is incorrect.  {$msg}");
}

function php_ini_ok($ini_param) {
	$value = ini_get($ini_param);
	show_message("PHP Configuration Setting '{$ini_param}' = '{$value}': OK",'I');
}

function check_extension($ext) {
	if (!extension_loaded($ext)) {
		show_message("The PHP extension '{$ext}' is required, but not loaded.");
		return false;
	} else {
		show_message("The PHP extension '{$ext}' is loaded.",'I');
		return true;
	}
}

/**
 * Check PHP configuration
 */
function prep_step_2() {
	$configtext = file_get_contents($_SESSION['globals']['configfilename']);
	$_SESSION['globals']['document_path_base'] = getInstallParamFromConfig($configtext,'DOCUMENT_PATH_BASE');
	$_SESSION['globals']['reports_classes_path'] = getInstallParamFromConfig($configtext,'REPORTS_CLASSES_PATH');
}

function step_2() {
	if($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST['pre_error'] =='') {
		prep_step_3();
		header('Location: install.php?step=3');
		exit;
	}
	if($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST['pre_error'] != '') {
		error_message($_POST['pre_error']);
	}
	
	echo '<h1>Step 2: Your Server Environment</h1>';

	$error = false;

	if (!ini_get('date.timezone')) {
		php_ini_error('date.timezone','It must be set to something.');
		$error = true;
	} else {
		php_ini_ok('date.timezone');
	}
	if ((intval(ini_get('memory_limit'))!=-1) && (intval(ini_get('memory_limit'))<256)) {
		show_message('PHP Configuration setting memory_limit (currently '.ini_get('memory_limit').') is recommended to be at least 256M.  (Complex operations and views may fail mysteriously.)','W');
	} else {
		php_ini_ok('memory_limit');
	}
	if (intval(ini_get('post_max_size'))<40) {
		show_message('PHP Configuration setting post_max_size (currently '.ini_get('post_max_size').') is recommended to be at least 40M.','W');
	} else {
		php_ini_ok('post_max_size');
	}
	if (ini_get('session.auto_start')) {
		php_ini_error('session.auto_start','This must be turned off.');
		$error = true;
	} else {
		php_ini_ok('session.auto_start');
	}
	
	if (ini_get('magic_quotes_gpc')) {
		php_ini_error('magic_quotes_gpc','This must be turned off.');
		$error = true;
	} else {
		php_ini_ok('magic_quotes_gpc');
	}
	
	
	$minversion = '5.2.9';
	$toolargeversion = '8.0.0';
	if (version_compare(PHP_VERSION, $minversion) < 0) {
		show_message("The PHP version (".PHP_VERSION.") is too low.  PartForge has only been tested to versions less than ".$toolargeversion." but at least version ".$minversion."." );
		$error = true;
	} else if (version_compare(PHP_VERSION, $toolargeversion) >= 0) {
		show_message("The PHP version (".PHP_VERSION.") is too high.  PartForge has only been tested to versions less than ".$toolargeversion." but at least version ".$minversion."." );
		$error = true;
	} else {
		show_message("The PHP version (".PHP_VERSION."): OK",'I');
	}

	// check extensions.

	if (!check_extension('curl')) $error = true;
	if (!check_extension('gd')) $error = true;   // supposedly this means gd2
	if (!check_extension('mysqli')) $error = true;
	if (!check_extension('json')) $error = true;
	if (!check_extension('mbstring')) $error = true;
	
	// prepare config file
	
	$configsamplefilename = realpath(dirname(__FILE__) . '/..').'/config-sample.php';
	$configfilename = realpath(dirname(__FILE__) . '/..').'/config.php';

	If (!file_exists($configfilename) && file_exists($configsamplefilename)) {
		copy($configsamplefilename,$configfilename);
	}
	
	if (!file_exists($configfilename) || !is_writable($configfilename)) {
		$error = true;
		show_message('The file "'.$configfilename.'" needs to exist and be writable for setup to continue!');
	} else {
		show_message('"'.$configfilename.'" is writeable.','I');
		$_SESSION['globals']['configfilename'] = $configfilename;
	}
	
	// test the document directory

	// only initialize this if we sense a default value present
	if (is_config_default($_SESSION['globals']['document_path_base'])) {
		$document_path_base = dirname(__FILE__);
		$document_directory = $document_path_base.'/documents';	
		$_SESSION['globals']['document_path_base'] = $document_path_base;
	}
	
	$document_directory = $_SESSION['globals']['document_path_base'].'/documents';
	$testfile = $document_directory.'/installtester'.time().'.txt';
	file_put_contents($testfile, 'test contents');
	$doctestpassed = false;
	if (file_exists($testfile)) {
		$contents = trim(file_get_contents($testfile));
		if ($contents=='test contents') {
			unlink($testfile);
			if (!file_exists($testfile)) {
				$doctestpassed = true;
				show_message('The document directory "'.$document_directory.'" is writeable.','I');
			}
		}
	}
	if (!$doctestpassed) {
		show_message('The document directory "'.$document_directory.'" is NOT writeable.');
		$error = true;
	}
	
	if (is_config_default($_SESSION['globals']['reports_classes_path'])) {
		$_SESSION['globals']['reports_classes_path'] = realpath(dirname(__FILE__) . '/../reports');
	}
	
	
	$pre_error = $error ? 'Your system is not configured properly for this software.' : '';
	?>
		<form action="install.php?step=2" method="post">
			<input type="hidden" name="pre_error" id="pre_error"
				value="<?php echo $pre_error;?>" /> <input type="submit" name="continue" value="Continue" />
		</form>
	<?php
}

function getInstallParamFromConfig($configtext, $paramname) {
	// $out[0] has the whole opening and closing tag. $out[1] has only the enclosed part
	$match = preg_match_all("|'([^']*?)'[ ,;]*?//INSTALL:PARAM:{$paramname}:|i",$configtext,$out);
	return count($out[1])==1 ? $out[1][0] : '';
} 

function putInstallParamToConfigStr($configtext, $paramname, $paramvalue) {
	return preg_replace("|'([^']*?)'([ ,;]*?//INSTALL:PARAM:{$paramname}:)|i", '\''.$paramvalue.'\'$2', $configtext);
}

function prep_step_3() {
	$configtext = file_get_contents($_SESSION['globals']['configfilename']);
	$_SESSION['globals']['host'] = getInstallParamFromConfig($configtext,'HOST');
	$_SESSION['globals']['dbname'] = getInstallParamFromConfig($configtext,'DBNAME');
	$_SESSION['globals']['username'] = getInstallParamFromConfig($configtext,'USERNAME');
	$_SESSION['globals']['password'] = getInstallParamFromConfig($configtext,'PASSWORD'); 
	$_SESSION['globals']['initial_db'] = 'db_quadcopter_example.sql';
}

function getSubmittedAndCheckRequired($paramnames) {
	$success = true;
	foreach($paramnames as $paramname) {
		$_SESSION['globals'][$paramname]=isset($_POST[$paramname])?$_POST[$paramname]:"";
		if (empty($_SESSION['globals'][$paramname])) $success = false;
	}
	return $success;
}

function getSubmittedEmailsAndCheckRequired($paramnames) {
	$EMAIL_REGEX = '^[a-zA-Z0-9_.-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9.-]+$';
	$success = true;
	foreach($paramnames as $paramname) {
		$_SESSION['globals'][$paramname]=isset($_POST[$paramname])?$_POST[$paramname]:"";
		if (empty($_SESSION['globals'][$paramname]) || !preg_match('"'.$EMAIL_REGEX.'"i',trim($_SESSION['globals'][$paramname]))) {
			$success = false;
		}
	}
	return $success;
}

function get_records($connection, $idfieldname,$query) {
	$out = array();
	$result = @mysqli_query($connection, $query);
	if ($result) {
		$num_results = mysqli_num_rows($result);
		for ($i=0; $i < $num_results; $i++) {
			$row = mysqli_fetch_assoc($result);
			if ($idfieldname=='') {
				$out[] = $row;
			} else {
				$out[$row[$idfieldname]] = $row;
			}
		}
	} else {
		// error
	}
	return $out;
}

function format_radio_tags($values,$field_name,$in_value) {
	$tags = array();
	$ii = 0;
	foreach($values as $value => $text) {
		// must make sure integer string is compared properly to integer index.
		$in_value = is_numeric($in_value) && (intval($in_value)==$in_value ) ? (int)$in_value : $in_value;
		$value = is_numeric($value) && (intval($value)==$value ) ? (int)$value : $value;
		if ($in_value===$value) {
			$selected = ' checked="checked"';
		} else {
			$selected = '';
		}
		$ii++;
		$idname = $field_name.'_'.$ii;
		$tags[] = '<input class="radioclass" type="radio" name="'.$field_name.'" value="'.$value.'" id="'.$idname.'"'.$selected.' />&nbsp;<label for="'.$idname.'">'.$text.'</label>';
	}
	return implode('<br />',$tags);
}

function step_3(){
	$allow_skip = false;
	if (isset($_POST['submit']) && $_POST['submit']=="Skip") {
		prep_step_4();
		header("Location: install.php?step=4");
	} else if (isset($_POST['submit']) && $_POST['submit']=="Continue") {
		$_SESSION['globals']['initial_db'] = $_POST['initial_db'];
		if (!getSubmittedAndCheckRequired(array('host','dbname','username','password'))) {
			error_message("All fields are required!");
		} else {
			$dbok = true;
			$connection = mysqli_connect($_SESSION['globals']['host'], $_SESSION['globals']['username'], $_SESSION['globals']['password']);
			if (!$connection) {
				$dbok = false;
				error_message("Cannot connect to host using the specified username and password.");
			} else {
				$db_selected = mysqli_select_db($connection, $_SESSION['globals']['dbname']);
				if (!$db_selected) {
					$dbok = false;
					error_message("Cannot select the specified database.");
				} else {
					$recs = get_records($connection, 'Tables_in_'.$_SESSION['globals']['dbname'],"SHOW TABLES");
					$tablenames = array_keys($recs);
					if (in_array('typeversion',$tablenames) && count($tablenames) > 5) {
						$dbok = false;
						$allow_skip = true;
						error_message("There are already tables in the database.  You must start with an empty database before continuing.");						
					} else {
						$sqlfile = realpath(dirname(__FILE__) . '/../database/'.$_SESSION['globals']['initial_db']);
						$sql = file($sqlfile);
						if (!$sql) {
							$dbok = false;
							error_message("Cannot locate the database source file '{$sqlfile}' or it is empty.");
						} else {
							// load the database source
							$query = '';
							foreach($sql as $line) {
								$tsl = trim($line);
								if (($sql != '') && (substr($tsl, 0, 2) != "--") && (substr($tsl, 0, 1) != '#')) {
									$query .= $line;
							
									if (preg_match('/;\s*$/', $line)) {							
										mysqli_query($connection, $query);
										$err = mysqli_error($connection);
										if (!empty($err))
											break;
										$query = '';
									}
								}
							}
						}
					}				




				}
			}
			if ($dbok) {
				prep_step_4();
				header("Location: install.php?step=4");
			}
		}
	}
		$db_options = array('db_quadcopter_example.sql' => 'Quadcopter Demonstration Database', 'db_generate.sql' => 'Empty Database');
		?>
		<h1>Step 3: Database Configuration</h1>
		<form method="post" action="install.php?step=3">
			<table class="edittable">
			<tr><th>Initialization Database:</th><td><?php echo format_radio_tags($db_options,'initial_db',$_SESSION['globals']['initial_db']);?></td></tr>
			<tr><th>Database Host:</th><td><input type="text" name="host" value='<?php echo $_SESSION['globals']['host']; ?>' size="30"></td></tr>
			<tr><th>Database Name:</th><td><input type="text" name="dbname" size="30" value="<?php echo $_SESSION['globals']['dbname']; ?>"></td></tr>
			<tr><th>Database Username:</th><td><input type="text" name="username" size="30" value="<?php echo $_SESSION['globals']['username']; ?>"></td></tr>
			<tr><th>Database Password:</th><td><input type="text" name="password" size="30" value="<?php echo $_SESSION['globals']['password']; ?>"></td></tr>
			</table>
			<p style="margin-top: 10px;">
				<input type="submit" name="submit" value="Continue">  <?php if ($allow_skip) { ?><input type="submit" name="submit" value="Skip"> <?php } ?>
			</p>
		</form>
		<?php
}

function prep_step_4() {
	$configtext = file_get_contents($_SESSION['globals']['configfilename']);
	$_SESSION['globals']['support_email'] = getInstallParamFromConfig($configtext,'SUPPORT_EMAIL');
	if (is_config_default($_SESSION['globals']['support_email'])) $_SESSION['globals']['support_email'] = 'administrator@example.com';
		
	$_SESSION['globals']['webmaster_email'] = getInstallParamFromConfig($configtext,'WEBMASTER_EMAIL');
	if (is_config_default($_SESSION['globals']['webmaster_email'])) $_SESSION['globals']['webmaster_email'] = 'administrator@example.com';
	
	$_SESSION['globals']['notices_from_email'] = getInstallParamFromConfig($configtext,'NOTICES_FROM_EMAIL');
	if (is_config_default($_SESSION['globals']['notices_from_email'])) $_SESSION['globals']['notices_from_email'] = 'administrator@example.com';
}

function step_4(){
	if (isset($_POST['submit']) && $_POST['submit']=="Continue") {
		if (!getSubmittedEmailsAndCheckRequired(array('support_email','webmaster_email','notices_from_email'))) {
			error_message("All fields are required and must be valid email addresses!");
		} else {
			header("Location: install.php?step=5");
		}
	}

	?>
		<h1>Step 4: Email Addresses</h1>
		<form method="post" action="install.php?step=4">
			<table class="edittable">
			<tr><th>Support Email Address:<br /><span class="paren">administrator who<br />manages user accounts</span></th><td><input type="text" name="support_email" value='<?php echo $_SESSION['globals']['support_email']; ?>' size="30"></td></tr>
			<tr><th>Webmaster Email:<br /><span class="paren">person to whom system<br />error messages are sent</span></th><td><input type="text" name="webmaster_email" size="30" value="<?php echo $_SESSION['globals']['webmaster_email']; ?>"></td></tr>
			<tr><th>Workflow Notices Email:<br /><span class="paren">address from which<br />workflow notifications are sent</span></th><td><input type="text" name="notices_from_email" size="30" value="<?php echo $_SESSION['globals']['notices_from_email']; ?>"></td></tr>
			</table>
			<p style="margin-top: 10px;">
				<input type="submit" name="submit" value="Continue">
			</p>
		</form>
		<?php
}

function self($scheme='',$host='') {
	if (!$scheme) {
		$scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']=='on' ? 'https' : 'http';
	}
	if (!$host) {
		$host = $_SERVER['HTTP_HOST'];
	}
	return $scheme.'://'.$host.$_SERVER['PHP_SELF'];
}

function step_5() {
	// up to this point, nothing is saved in the config file yet.  So now we do that.
	$configtext = file_get_contents($_SESSION['globals']['configfilename']);
	$configtext = putInstallParamToConfigStr($configtext, 'HOST', $_SESSION['globals']['host']);
	$configtext = putInstallParamToConfigStr($configtext, 'DBNAME', $_SESSION['globals']['dbname']);
	$configtext = putInstallParamToConfigStr($configtext, 'USERNAME', $_SESSION['globals']['username']);
	$configtext = putInstallParamToConfigStr($configtext, 'PASSWORD', $_SESSION['globals']['password']);
	$configtext = putInstallParamToConfigStr($configtext, 'DOCUMENT_PATH_BASE', $_SESSION['globals']['document_path_base']);
	$configtext = putInstallParamToConfigStr($configtext, 'REPORTS_CLASSES_PATH', $_SESSION['globals']['reports_classes_path']);
	$configtext = putInstallParamToConfigStr($configtext, 'SUPPORT_EMAIL', $_SESSION['globals']['support_email']);
	$configtext = putInstallParamToConfigStr($configtext, 'WEBMASTER_EMAIL', $_SESSION['globals']['webmaster_email']);
	$configtext = putInstallParamToConfigStr($configtext, 'NOTICES_FROM_EMAIL', $_SESSION['globals']['notices_from_email']);
	file_put_contents($_SESSION['globals']['configfilename'],$configtext);
	
	$login_url = str_replace(basename(__FILE__), '', self());
	$cron_url = str_replace(basename(__FILE__), 'cron/servicetasks', self());
	
	?>
	<h1>Congratulations!  Your Installation is Mostly Complete.</h1>
	<p>The database has been initialized and some basic parameters have been added to the configuration file (<?php echo $_SESSION['globals']['configfilename'];?>).  
	To modify these parameters and others, please edit that file directly.
	</p>
	<p>You should now be able to log into your PartForge installation at a location like <a href="<?php echo $login_url;?>"><?php echo $login_url;?></a> using Login ID <i>admin</i> and Password <i>admin</i>.</p>
	<p>For a complete installation, you need to setup a cron job to service maintenance tasks.  You should have the cron load the page "<?php echo $cron_url;?>" once a minute.  The crontab entry will look something like:<br />
	<pre>* * * * * /usr/bin/wget -q "<?php echo $cron_url;?>"</pre>
	The reason this is possibly optional is that if the cron is not running, accessing the site will service the tasks.  But this is not great for responsiveness and if you don't use the site
	nothing happens.</p>
	<p>Finally, you should delete this installation script (<?php echo __FILE__;?>) or move it to a location like <?php echo realpath(dirname(__FILE__) . '/..');?> that is not accessible from the webserver.  Keeping this script where it is is obviously a security hole.</p>
	<p>Please visit the project page at <a href="https://github.com/randiego/partforge" target="_blank">GitHub/PartForge</a> for the latest version and other resources.</p>
	<p style="margin-top:30px;"><a class="bd-linkbtn" style="padding:10px;"  href="<?php echo $login_url;?>">Login at <?php echo $login_url;?></a></p>
	<?php 
}

?>

</div>
</body>
<?php ob_end_flush();
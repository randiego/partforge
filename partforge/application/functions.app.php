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

require_once('functions.common.php');


define('AUTOPROPAGATING_QUERY_PARAMS', 'pageno,search_string,sort_key,table,edit_buffer_key,list_type,months');

require_once('functions.common.php');

/**
 * This is a simple substitution engine grabbed from
 * http://stackoverflow.com/questions/5815028/creating-a-simple-but-flexible-templating-engine
 * @param string $template
 * @param array $replacements
 * @return string|mixed
 */
function email_template_to_text($template, $replacements = array())
{
    /*
     $template = "foo={_FOO_},bar={_BAR_},title={_TITLE_}\n";

    $replacements = array(
            'title' => 'This is the title',
            'foo' => 'Footastic!',
            'bar' => 'Barbaric!'
    );
    */
    $keys = array_map("templatevarmap", array_keys($replacements));
    $rendered = str_replace($keys, array_values($replacements), $template);
    return $rendered;
}

function templatevarmap($a)
{
    return '{_'. strtoupper($a) .'_}';
}

function send_template_email($templatestring, $to, $toname, $from, $fromname, $assignarray, $subject, $cc = '', $bcc = '', $content_type = 'text/plain')
{
    $message = email_template_to_text($templatestring, $assignarray);
    require_once('functions.email.php');
    $themail = new Email($to, $toname, $from, $fromname, $cc, $bcc, $subject, $message);
    $themail->setContentType($content_type);
    if (!$themail->Send()) {
        logerror("Email($to, $toname, $from, $fromname, $cc, $bcc, $subject, $message)->Send() in send_template_email()");
        return false;
    }
    return true;
}

class AdminSettings {
    protected static $_instance = null;

    const SESSIONVAR = 'admin_settings';
    private $_var_expiration_sec = 1800;

    protected function __construct()
    {

    }

    public static function getInstance()
    {
        if (null === self::$_instance) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    public function __set($key, $value)
    {
        $_SESSION[self::SESSIONVAR][$key] = array('value' => $value, 'expires' => script_time() + $this->_var_expiration_sec);
    }

    public function __get($key)
    {
        if (isset($_SESSION[self::SESSIONVAR][$key]) && ($_SESSION[self::SESSIONVAR][$key]['expires'] > script_time())) {
            return $_SESSION[self::SESSIONVAR][$key]['value'];
        }

        return null;
    }

    public function setExpirationTimeMinutes($key, $minutes)
    {
        $new_expiration = script_time() + $minutes * 60;
        if (isset($_SESSION[self::SESSIONVAR][$key])) {
            $_SESSION[self::SESSIONVAR][$key]['expires'] = $new_expiration;
        }
    }

    public function getExpirationTimeMinutes($key)
    {
        if (isset($_SESSION[self::SESSIONVAR][$key])) {
            return round(($_SESSION[self::SESSIONVAR][$key]['expires'] - script_time())/60);
        }
        return 0;
    }

}

// singleton class wrapper for session variable
class LoginStatus {

    const LOGINCOOKIE     = 'DATAFLOWLOGIN';
    const COOKIELASTDAYS = 365;
    const SESSIONVAR = 'validlogin';
    const SESSIONVARRETURNLOGIN = 'returntologinid';

    protected static $_instance = null;

    protected $_logincookie = null;

    protected function __construct()
    {
        $error_reporting = error_reporting(error_reporting() ^ E_NOTICE);  // we don't want to see the inevitable notice here
        $this->_logincookie = array();
        if (!empty($_COOKIE[self::LOGINCOOKIE])) {
            $unser = unserialize($_COOKIE[self::LOGINCOOKIE]);
            if ($unser !== false) {
                $this->_logincookie = $unser;
            }
        }
        error_reporting($error_reporting);
    }

    public static function getInstance()
    {
        if (null === self::$_instance) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    public function isValidUser()
    {
        return isset($_SESSION[self::SESSIONVAR]) && $_SESSION[self::SESSIONVAR];
    }

    public function unsetReturnLogin()
    {
        unset($_SESSION[self::SESSIONVARRETURNLOGIN]);
    }

    public function setReturnLogin($login)
    {
        $_SESSION[self::SESSIONVARRETURNLOGIN] = $login;
    }

    public function getReturnLogin()
    {
        return $this->returnLoginExists() ? $_SESSION[self::SESSIONVARRETURNLOGIN] :  null;
    }

    public function returnLoginExists()
    {
        return isset($_SESSION[self::SESSIONVARRETURNLOGIN]);
    }

    public function setValidUser($val)
    {
        if ($val) {
            $this->unsetReturnLogin();
        }
        $_SESSION[self::SESSIONVAR] = $val;
    }

    public function rememberThisUser($id, $pw = '')
    {
        if ($pw) {
            setcookie(self::LOGINCOOKIE, serialize(array('login' => $id, 'cryptpw' => $pw)), time() + self::COOKIELASTDAYS*3600*24, '/');
        } else {
            setcookie(self::LOGINCOOKIE, serialize(array('login' => $id)), time() + self::COOKIELASTDAYS*3600*24, '/');
        }
    }

    public function unRememberThisUser()
    {
        setcookie(self::LOGINCOOKIE, '', script_time() - 3600000, '/');
    }

    public function cookieLogin()
    {
        return isset($this->_logincookie['login']) ? $this->_logincookie['login'] : '';
    }

    public function cookieCryptPassword()
    {
        return isset($this->_logincookie['cryptpw']) ? $this->_logincookie['cryptpw'] : '';
    }

}

function date_compare($a, $b)
{
    global $DATEFIELD;
    if ($a->$DATEFIELD == $b->$DATEFIELD) {
        return 0;
    }
    return (strtotime($a->$DATEFIELD) < strtotime($b->$DATEFIELD)) ? -1 : 1;
}

function sort_by_date_property(&$array, $fieldname)
{
    global $DATEFIELD;
    $DATEFIELD = $fieldname;
    uasort($array, "date_compare");
}

function make_unknown_a_number_between($var, $low, $hi = '')
{
    if (!is_numeric($var)) {
        return $low;
    } elseif ($var < $low) {
        return $low;
    } elseif ($hi != '') {
        if ($var > $hi) {
            return $hi;
        }
    }
    return $var;
}

function fetch_date_text($date_in, $useparens = true, $showtime = false)
{
    if ($showtime) {
        $fmt = "D, M j, Y, g:i a";
    } else {
        $fmt = "D, M j, Y";
    }
    $today = date($fmt, script_time());
    $yesterday = date($fmt, script_time()-24*3600);
    $date_text = date($fmt, strtotime($date_in));
    if ($useparens) {
        $date_text .= ($date_text==$today) ? ' (today)' : '';
        $date_text .= ($date_text==$yesterday) ? ' (yesterday)' : '';
    }
    return $date_text;
}

function logout_if_session_timed_out()
{
    $config = Zend_Registry::get('config');
    $last_page_fetch = isset($_SESSION['last_page_fetch_time']) ? $_SESSION['last_page_fetch_time'] : 0;
    $_SESSION['last_page_fetch_time'] = script_time();
    $ignore_timeout = isset($_REQUEST['no_timeout']) && ($_REQUEST['no_timeout']==1);
    $activity_timeout = ($_SESSION['account']->getRole()=='DataTerminal') ? $config->activity_timeout_terminal_user : $config->activity_timeout;
    if (LoginStatus::getInstance()->isValidUser() && (script_time() > $activity_timeout + $last_page_fetch) && !$ignore_timeout) {
        LoginStatus::getInstance()->setValidUser(false);
        $_SESSION['account'] = new DBTableRowUser();
        $_SESSION['msg'] = 'Your session was inactive for more than '.number_format($activity_timeout/60, 0).' minutes.';
        return true;
    }
    return false;
}

/*
 * From http://php.net/manual/en/function.hex2bin.php
*/
function hextobin($hexstr)
{
    $n = strlen($hexstr);
    $sbin="";
    $i=0;
    while ($i<$n) {
        $a =substr($hexstr, $i, 2);
        $c = pack("H*", $a);
        if ($i==0) {
            $sbin=$c;
        } else {
            $sbin.=$c;
        }
        $i+=2;
    }
    return $sbin;
}

/**
 * need to get a list of all the procedure types that references this type.
 * select all the types with components that reference our type.
 * @param integer $typeversion_id
 * @param integer $is_user_procedure set to 1 if you only want procedures, 0 of only want parts, and null if you want them all.
 * @return unknown
 */
function getTypesThatReferenceThisType($typeversion_id, $is_user_procedure = 1)
{
    $is_user_procedure_and = ($is_user_procedure===1) ? 'AND (typecategory.is_user_procedure=1)' : (($is_user_procedure===0) ? 'AND (typecategory.is_user_procedure=0)' : '');

    $procedure_records = DbSchema::getInstance()->getRecords('', "
			SELECT DISTINCT typeversiontarg.*, typecomponent.component_name, typeobjecttarg.typedisposition
			FROM typeobject as typeobjecttarg, typeversion as typeversiontarg, typecomponent, typecomponent_typeobject, typecategory, typeversion as typeversionself
			WHERE (typeversionself.typeversion_id='{$typeversion_id}')
			AND (typeversionself.typeobject_id=typecomponent_typeobject.can_have_typeobject_id)
			AND (typecomponent_typeobject.typecomponent_id=typecomponent.typecomponent_id)
			AND (typecomponent.belongs_to_typeversion_id=typeversiontarg.typeversion_id)
			AND (typeversiontarg.typeversion_id=typeobjecttarg.cached_current_typeversion_id)
			AND (typeversiontarg.typecategory_id=typecategory.typecategory_id)
			{$is_user_procedure_and}
			ORDER BY typeversiontarg.type_description
			");
    return $procedure_records;
}

/**
 * Perform a join operation on on two export tables from ReportDataItemListView such that the resulting
 * table output contains all possible combinations where the values in the join columns match.
 * This is to do correlation studies between two different types of procedure items.
 * @param array $header_recs array('A' => array_of_headersA, 'B' => array_of_headersB), each array indexed by fieldname
 * @param array $data_recs array('A' => array_of_datarowsA, 'B' => array_of_datarowsB), each array is numeric indexed rows
 * @param array $join_columns array('A' => 'name_of_join_column_in_A', 'B' => name_of_join_column_in_B)
 */
function joinItemVersionArraysOnField($header_recs, $data_recs, $join_columns)
{
    $out_headers = array();
    $out_rows = array();

    foreach ($header_recs['A'] as $fieldname => $fieldcaption) {
        $out_headers['A.'.$fieldname] = 'A->'.$fieldcaption;
    }
    foreach ($header_recs['B'] as $fieldname => $fieldcaption) {
        $out_headers['B.'.$fieldname] = 'B->'.$fieldcaption;
    }

    // now need to group the records according to the join fields.  This is basically a sort operation
    $grouped_recs = array('A' => array(), 'B' => array());
    foreach ($grouped_recs as $tableid => $grouped_tree) {
        foreach ($data_recs[$tableid] as $idx => $record) {
            // create cluster stub
            if (!empty($record[$join_columns[$tableid]])) {
                if (!isset($grouped_recs[$tableid][$record[$join_columns[$tableid]]])) {
                    $grouped_recs[$tableid][$record[$join_columns[$tableid]]] = array();
                }
                $grouped_recs[$tableid][$record[$join_columns[$tableid]]][$idx] = $record;
            }
        }
    }

    // now perform the actual joining.  For each matching cluster, generate all permutations
    $out_rows = array();
    foreach ($grouped_recs['A'] as $JoinValue => $cluster_records) {
        if (isset($grouped_recs['B'][$JoinValue])) {
            // a matching cluster exists in B, so we should combine the records in that cluster with the ones in this cluster
            foreach ($grouped_recs['A'][$JoinValue] as $idxA => $recordA) {
                foreach ($grouped_recs['B'][$JoinValue] as $idxB => $recordB) {
                    // fill up the final record buffer
                    $out_rec = array();
                    foreach ($header_recs['A'] as $fieldname => $fieldcaption) {
                        $out_rec['A.'.$fieldname] = $recordA[$fieldname];
                    }
                    foreach ($header_recs['B'] as $fieldname => $fieldcaption) {
                        $out_rec['B.'.$fieldname] = $recordB[$fieldname];
                    }
                    $out_rows[] = $out_rec;
                }
            }
            // now we remove the B side cluster of records (eventually we will need to join from the remainer on the B side)
            unset($grouped_recs['B'][$JoinValue]);
            unset($grouped_recs['A'][$JoinValue]);
        }
    }

    // now do the right join using the B side as the starter
    foreach ($grouped_recs['B'] as $JoinValue => $cluster_records) {
        if (isset($grouped_recs['A'][$JoinValue])) {
            // a matching cluster exists in A, so we should combine the records in that cluster with the ones in this cluster
            foreach ($grouped_recs['B'][$JoinValue] as $idxB => $recordB) {
                foreach ($grouped_recs['A'][$JoinValue] as $idxA => $recordA) {
                    // fill up the final record buffer
                    $out_rec = array();
                    foreach ($header_recs['A'] as $fieldname => $fieldcaption) {
                        $out_rec['A.'.$fieldname] = $recordA[$fieldname];
                    }
                    foreach ($header_recs['B'] as $fieldname => $fieldcaption) {
                        $out_rec['B.'.$fieldname] = $recordB[$fieldname];
                    }
                    $out_rows[] = $out_rec;
                }
            }
            // now we remove the A side cluster of records (eventually we will need to join from the remainer on the B side)
            unset($grouped_recs['A'][$JoinValue]);
        }
    }

    return array($out_headers, $out_rows);
}

/**
 * Perform two exports and then join the tables (inner join) on the specifed column
 * @param string $A_category typeobject_id of the first record type for the join
 * @param string $B_category typeobject_id of the second record type for the join
 * @param unknown_type $A_joincolumn columnname to join on in the first table
 * @param unknown_type $B_joincolumn columnname to join on in the second table
 */
function outputJoinedCSVToBrowser($A_category, $B_category, $A_joincolumn, $B_joincolumn)
{
    $procedure_options = DBTableRowTypeVersion::getPartNumbersWAliasesAllowedToUser($_SESSION['account'], true);

    $ReportDataA = new ReportDataItemListView(true, true, isset($procedure_options[$A_category]), false, $A_category);
    $ReportDataB = new ReportDataItemListView(true, true, isset($procedure_options[$B_category]), false, $B_category);

    $dummyparms = array();
    // process records to fill out extra fields and do normal format conversion
    $records_outA = $ReportDataA->get_export_detail_records($dummyparms, '', '');
    $records_outB = $ReportDataB->get_export_detail_records($dummyparms, '', '');

    // perform an inner join at this point
    $header_recs = array('A' => $ReportDataA->csvfields, 'B' => $ReportDataB->csvfields);
    $data_recs = array('A' => $records_outA, 'B' => $records_outB);
    $join_columns = array('A' => $A_joincolumn, 'B' => $B_joincolumn);
    list($out_headers, $out_rows) = joinItemVersionArraysOnField($header_recs, $data_recs, $join_columns);

    send_download_headers('text/csv', "Joined_{$A_category}_{$B_category}.csv");
    echo CsvGenerator::arrayToCsv($out_rows, $out_headers);
    exit;
}

/**
 *
 * @param string $category
 */
function outputCSVToBrowser($typeobject_id)
{
    $procedure_options = DBTableRowTypeVersion::getPartNumbersWAliasesAllowedToUser($_SESSION['account'], true);
    $ReportData = new ReportDataItemListView(true, true, isset($procedure_options[$typeobject_id]), false, $typeobject_id);
    $dummyparms = array();
    $records_out = $ReportData->get_export_detail_records($dummyparms, '', '');
    send_download_headers('text/csv', "Export_TypeObjectId{$typeobject_id}.csv");
    echo CsvGenerator::arrayToCsv($records_out, $ReportData->csvfields);
    exit;
}

define('ROWS_PER_PAGE', 5);
define('MAX_INPUT_TAG_WIDTH', 50);
define('DEFAULT_FLOAT_WIDTH', 20);
define('DEFAULT_FIELD_PRINT_WIDTH', 50);
define('PATH_TO_SCRIPTS', '/scripts/');
define('EMAILSPLITTER', '<!-- #EndEditable -->' );
define('REQUIRED_SYM', '<span class="req_field">&nbsp;*</span>');
define('LOCKED_FIELD_SYM', '<span class="locked_field"><span class="field_locked_sym"></span></span>');

function popup_linkify($url, $linktext, $title, $class, $onclick, $win_name, $xsize = '500', $ysize = '600')
{
    $script = "open_win_named('$url','$win_name',$xsize,$ysize); return false";
    return linkify('#', $linktext, $title, $class, $onclick.$script);
}

function popup_button_input_tag($url, $linktext, $value, $onclick, $win_name, $xsize = '500', $ysize = '600', $class = 'bd-button')
{
    $script = "open_win_named('$url','$win_name',$xsize,$ysize); return false";
    return '<input class="'.$class.'" type="submit" value="'.$linktext.'" name="'.$value.'" onclick="'.$onclick.$script.'">';
}

function fetch_form_page($title, $html, $buttons, $bottom_html = '', $on_click_array = array(), $attributes = '')
{
    if (!is_array($on_click_array)) {
        $on_click_array=array();
    }
    $btns = '';
    foreach ($buttons as $text => $value) {
        $onclick = isset($on_click_array[$text]) ? ' onclick="'.$on_click_array[$text].'"' : '';
        $btns .= '<span class="dialogbutton"><input class="bd-button" type="submit" value="'.$text.'" name="'.$value.'"'.$onclick.'></span>';
    }

    return '<h1 class="dialogtitle">'.$title.'</h1>
			<div class="dialogbody">
			'.fetch_form_tag('
					<div class="dialogcontent">'.$html.'
					</div>
					<div class="dialogbuttons">'.$btns.'
					</div>
					<div class="dialogsubtext">'.$bottom_html.'
					</div>
					', $attributes).'
							</div>';
}

function showdialog_html($errheader, $msg, $buttonlist)
{

    $buttons = '';
    foreach ($buttonlist as $btntext => $urltext) {
        $buttons .= '<span class="dialogbutton"><form method=post action="'.$urltext.'"><input class="bd-button" TYPE="submit" name="submit" value="'.$btntext.'"></form></span>';
    }

    return '<h1 class="dialogtitle">'.$errheader.'</h1>
			<div class="dialogbody">
			<div class="dialogcontent">'.$msg.'
					</div>
					<div class="dialogbuttons">'.$buttons.'
							</div>
							</div>';
}

function showdialog($errheader, $errormsg, $buttonlist)
{
   // display the message, then proceed


    // make a standalone layout for this since I don't know what the controller action is
    $layout = new Zend_Layout();

    // snag the path from the non-standalonemain layout
    $layout->setLayoutPath(APPLICATION_PATH . '/layouts/scripts');
    $layout->setLayout('layoutdialog');

    // here's what we're displaying
    $msg = '';
    if (ob_get_length()) { // then output buffering is turned on and we should make sure nothing has been written
        if (ob_get_length() > 0) {
            $msg = "[".ob_get_contents()."]<br>";
            ob_end_clean();
        }
    }
    $msg .= $errormsg;
    $layout->content = showdialog_html($errheader, $msg, $buttonlist);
    $layout->title = $errheader;
    $layout->show_in_any_window = true;
    Zend_Controller_Front::getInstance()->getResponse()->sendHeaders(); // sends UTF-8 content type as defined in CustomControllerAction

    echo $layout->render();

    die();
}

function showinputdialog($title, $message, $label, $text)
{
   // display the message, the proceed

    // make a standalone layout for this since I don't know what the controller action is
    $layout = new Zend_Layout();

    // snag the path from the non-standalonemain layout
    $layout->setLayoutPath(APPLICATION_PATH . '/layouts/scripts');
    $layout->setLayout('layoutdialog');
    $layout->content = fetch_edit_page($title,
            '<p>'.$message.'</p>
			<p><b>'.$label.':</b>&nbsp;<input class="inputboxclass" type="TEXT" name="text" size="50" value="'.$text.'"></p>');

    $layout->title = $title;
    $layout->show_in_any_window = true;
    Zend_Controller_Front::getInstance()->getResponse()->sendHeaders(); // sends UTF-8 content type as defined in CustomControllerAction

    echo $layout->render();

    die();
}


function spawnshowdialog($title, $message, $buttonlist)
{
   // looks just like above, except spawned as new url
    $_SESSION['showdialog']['buttons'] = $buttonlist;
    $_SESSION['showdialog']['title'] = $title;
    $_SESSION['showdialog']['message'] = $message;
    spawnurl(UrlCallRegistry::formatViewUrl('showdialog', 'output'));
}

class BreadCrumbsManager {

    private $_current_url = '';

    public function __construct()
    {
        if (!isset($_SESSION['breadcrumbs'])) {
            $_SESSION['breadcrumbs'] = array();
        }
    }

    /**
     * Push the specified url and caption onto the breadcrumb stack.  The url has to be an exact match of previously
     * added ones in order to properly prune the history list.
     * @param unknown_type $inurl
     * @param unknown_type $intitle
     */
    public function addCurrentUrl($inurl, $intitle, $match_on_title_only = false)
    {
        $this->_current_url = $inurl;
        $_SESSION['breadcrumbs'][$inurl] = $intitle;
        // now prune any urls after this one.  Basically we reset the breadcrumbs back to the current url when we
        // encounter a duplicate url.
        $new = array();
        $bc_links = array();
        foreach ($_SESSION['breadcrumbs'] as $url => $url_name) {
            $new[$url] = $url_name;
            if ($url==$inurl) {
                break;
            }
            if ($match_on_title_only && ($url_name==$intitle)) {
                array_pop($new); // remove the last entry we just put on
                $new[$inurl] = $intitle;
                break;
            }
            $bc_links[] = linkify($url, $url_name);
        }
        $_SESSION['breadcrumbs'] = $new;
    }

    /**
     * Return the rendered html of the bread crumb links
     * @param string $current_url
     * @param string $override_title
     * @param integer $max_entries only show this many of the recent locations (0=show all)
     * @return string
     */
    public function render($current_url = null, $override_title = null, $max_entries = 0, $divclass = 'breadcrumbdiv')
    {
        if (!is_null($current_url)) {
            $this->_current_url = $current_url;
        }
        $bc_links = array();
        foreach ($_SESSION['breadcrumbs'] as $url => $url_name) {
            if ($url==$this->_current_url) {
                $bc_links[] = !is_null($override_title) ? $override_title : $url_name;
                break;
            }
            $bc_links[] = linkify($url, $url_name);
        }
        if ($max_entries>0) {
            $bc_links = array_slice($bc_links, -$max_entries);
        }
        return '<div class="'.$divclass.'">'.implode(' &raquo; ', $bc_links).'</div>';
    }

    /**
     * look to previously select URL and returns just the URL for that.  This can be used for constructing a return url button
     */
    public function getPreviousUrl()
    {
        if (count($_SESSION['breadcrumbs'])>1) {
            // go back one URL from the end
            $records = array_slice(  array_keys($_SESSION['breadcrumbs']), -2, 1);
            $url = reset($records);
            return $url;
        } else {
            return '';
        }
    }

    /**
     * Erase the history and start over again.
     * @param unknown_type $inurl
     * @param unknown_type $intitle
     */
    public function newAnchor($inurl, $intitle)
    {
        $_SESSION['breadcrumbs'] = array();
        $_SESSION['breadcrumbs'][$inurl] = $intitle;
    }
}

class EditLinks {
    var $items = array();

    public $classname = 'bd-button';

    public function __construct()
    {
    }

    function buttons_html()
    {
        $html = '';
        $entry = array();
        if (count($this->items) > 0) {
            foreach ($this->items as $item) {
                if (isset($item['link'])) {
                    $entry[] = $item['link'];
                } else {
                    $entry[] = linkify($item['url'], $item['text'], $item['title'], $this->classname, $item['onclick']);
                }
            }
            $html .= implode(' ', $entry);
        } else {
            $html .= '&nbsp;';
        }
        return $html;
    }

    function td_html($colspan = 1)
    {
        return '<td class="editlink_td" colspan="'.$colspan.'">'.$this->buttons_html().'</td>';
    }

    function add_item($url, $text, $title = '', $onclick = '')
    {
        $this->items[] = array('url' => $url, 'text' => $text, 'title' => $title, 'onclick' => $onclick);
    }

    function add_link($link)
    {
        $this->items[] = array('link' => $link);
    }

    public function count()
    {
        return count($this->items);
    }
}

function fetch_edit_page($title, $html)
{
    return fetch_form_page($title, $html, array('OK' => 'btnOK', 'Cancel' => 'btnCancel'));
}

function format_date_MjY($str, $empty_text = '')
{
    return $str ? date("M j, Y", strtotime($str)) : $empty_text;
}

function format_application_id($application_id)
{
    return str_pad((int) $application_id, 4, "0", STR_PAD_LEFT);
}

function fetchPageBannerDiv()
{
    $html = '';
    $html .= isset($_GET['msgi']) ? '<div class="pageBannerDiv yellow">'.$_SESSION['msg'].'</div>' : (isset($_GET['msge']) ? '<div class="pageBannerDiv">'.$_SESSION['msg'].'</div>' : '');
    $banner_array = Zend_Registry::get('config')->banner_array->toArray();
    if (is_array($banner_array)) {
        foreach ($banner_array as $banner_html) {
            $html .= '<div class="pageBannerDiv">'.$banner_html.'</div>';
        }
    }
    $server_message = TableRowSettings::getBannerText();
    if ($server_message) {
        $html .= '<div class="pageBannerDiv">'.$server_message.'</div>';
    }

    $group_task_ids = GroupTask::getActiveWorkFlowIds();
    foreach ($group_task_ids as $group_task_id) {
        $Workflow = GroupTask::getInstance($group_task_id);
        if (!is_null($Workflow)) {
            foreach ($Workflow->getAssignedToTasks($_SESSION['account']->user_id) as $assigned_to_task_id => $assigned_to_task) {
                $out = "Task Assigned to You: ".linkify(GroupTask::getLinkUrlForMember($assigned_to_task_id), $Workflow->getTitle(), "Clear this workflow task from the system.");
                $html .= '<div class="pageBannerDiv yellow">'.$out.'</div>';
            }
        }
    }

    if (($_SESSION['account']->user_type=='Admin') && !Zend_Registry::get('config')->config_for_testing) { // we don't show if its a test
        $installfile = htmlentities(Zend_Registry::get('config')->public_base_path.'/install.php');
        if (file_exists($installfile)) {
            $html .= '<div class="pageBannerDiv">Warning: The installation file "'.htmlentities($installfile).'" should be removed to prevent accidental or malicious reconfiguration.</div>';
        }
    }

    return $html;
}


function itemTableRowExists($table, $id)
{
    if (is_numeric($id)) {
        $records = DbSchema::getInstance()->getRecords('', "SELECT {$table}_id FROM {$table} where {$table}_id='{$id}'");
        return count($records) > 0;
    }
    return false;
}

/**
 *
 * @param str $prefix 'io' or 'iv' or 'tv' or 'to'
 * @param integer $id the itemobject or itemversion
 * @return string
 */
function formatAbsoluteLocatorUrl($prefix, $id)
{
    $locator = '/struct/'.$prefix.'/'.$id;
    return Zend_Controller_Front::getInstance()->getRequest()->getScheme().'://'.Zend_Controller_Front::getInstance()->getRequest()->getHttpHost().Zend_Controller_Front::getInstance()->getRequest()->getBaseUrl().$locator;
}

function specialSearchKeyToUrl($search_string, $strict = true)
{
    if (!$strict && ctype_digit($search_string)) {
        $search_string = 'io/'.$search_string;
    }
    $map = array('io' => 'itemobject', 'iv' => 'itemversion','to' => 'typeobject','tv' => 'typeversion');
    preg_match('/^(io|iv|tv|to)\/([0-9]+)$/', strtolower($search_string), $out);
    if (isset($out[1]) && isset($out[2])) {
        $table = $map[$out[1]];
        $id = $out[2];
        return itemTableRowExists($table, $id) ? formatAbsoluteLocatorUrl($out[1], $id) : '';
    }
    return '';
}

function fetchHtmlHeaderIncludes()
{
    $baseurl = Zend_Controller_Front::getInstance()->getRequest()->getBaseUrl();
    $ver = Zend_Registry::get('config')->cached_code_version;
    return <<<EOD
<link href="{$baseurl}/commonLayout.css?v={$ver}" rel="stylesheet" type="text/css" />
<link type="text/css" href="{$baseurl}/jqueryui11/jquery-ui.min.css" rel="Stylesheet" />
<link rel="stylesheet" href="{$baseurl}/jqueryextras/gallery-2.15.2/css/blueimp-gallery.min.css">
<link rel="stylesheet" href="{$baseurl}/jqueryextras/jquery-file-upload/css/jquery.fileupload-ui.css">
<link rel="stylesheet" href="{$baseurl}/jqueryextras/jquery-custom-combobox/jquery-custom-combobox.css">
<script type="text/javascript" src="{$baseurl}/jqueryui11/external/jquery/jquery.js"></script>
<script type="text/javascript" src="{$baseurl}/jqueryui11/jquery-ui.min.js"></script>
<script type="text/javascript" src="{$baseurl}/jqueryextras/js/jquery-ui-timepicker-addon.js"></script>
<script type="text/javascript" src="{$baseurl}/jqueryextras/js/jquery-ui-sliderAccess.js"></script>
<script type="text/javascript" src="{$baseurl}/jqueryextras/js/jquery.watermark.min.js"></script>
<script language="JavaScript" src="{$baseurl}/scripts/common.js?v={$ver}" type="TEXT/JAVASCRIPT"></script>
<script language="JavaScript" src="{$baseurl}/scripts/app.js?v={$ver}" type="TEXT/JAVASCRIPT"></script>
<script language="JavaScript" src="{$baseurl}/jqueryextras/jquery.cookie.js" type="TEXT/JAVASCRIPT"></script>
<script language="JavaScript" src="{$baseurl}/jqueryextras/jquery.json-2.3.js" type="TEXT/JAVASCRIPT"></script>
<script language="JavaScript" src="{$baseurl}/scripts/tiny_mce/jquery.tinymce.js" type="TEXT/JAVASCRIPT"></script>
<script language="JavaScript" src="{$baseurl}/jqueryextras/jquery-custom-combobox/jquery-custom-combobox.js" type="TEXT/JAVASCRIPT"></script>
<script language="JavaScript" src="{$baseurl}/jqueryextras/jquery.ui.touch-punch.min.js" type="TEXT/JAVASCRIPT"></script>
<script language="JavaScript" src="{$baseurl}/jqueryextras/jquery-qrcode/jquery.qrcode.min.js" type="TEXT/JAVASCRIPT"></script>
EOD;
}

function time_to_bulletdate($timestamp, $show_day = true)
{
    return ($show_day ? date('D', $timestamp).' &bull; ' : '').date('M j, Y', $timestamp).' &bull; '.date('G:i', $timestamp);
}

function getGlobal($key)
{
    $records = DbSchema::getInstance()->getRecords('globals_id', "SELECT * FROM globals WHERE gl_key='{$key}'");
    if (count($records)==1) {
        $record = reset($records);
        return $record['gl_value'];
    } else {
        return null;
    }
}

function getAllGlobals()
{
    $records = DbSchema::getInstance()->getRecords('globals_id', "SELECT * FROM globals");
    $out = array();
    foreach ($records as $record) {
        $out[$record['gl_key']] = $record['gl_value'];
    }
    return $out;
}

function setGlobal($key, $value)
{
    $Pref = new DBTableRow('globals');
    if ($Pref->getRecordWhere("gl_key='{$key}'")) {
        $Pref->gl_value = $value;
    } else {
        $Pref->gl_key = $key;
        $Pref->gl_value = $value;
    }
    $Pref->save();
}

function time_difference_str($diff)
{
    if ($diff > 3600*48) {
        return number_format($diff/3600/24, 1).' days';
    } else if ($diff > 3600*2) {
        return number_format($diff/3600, 1).' hours';
    } else {
        return number_format($diff/60, 1).' minutes';
    }
}

function fetch_event_log_header_html($clear_url)
{
    $html_msg = '';
    $records = DbSchema::getInstance()->getRecords('event_log_id', "SELECT * FROM eventlog WHERE event_log_notify='1' ORDER BY event_log_date_added desc");
    if (count($records) > 0) {
        $html_msg .= '<div id="eventlog"><h1>Notifications from Background Processing</h1>
            <p><ul>';
        foreach ($records as $record) {
            $html_msg .= '<li><span>'.date('M j, Y', strtotime($record['event_log_date_added'])).':</span>'.text_to_unwrappedhtml($record['event_log_text']).'</li>';
        }
        $html_msg .= '</ul></p><div>'.linkify($clear_url, 'Clear Messages', 'Clear this list of log messages').'</div></div>';
    }
    return $html_msg;
}


function event_log_notify_clear()
{
    $Messages = new DBRecords(new DBTableRowEventLog(), '', '');
    $Query = $Messages->getQueryObject('');
    $Query->addSelectors(array('event_log_notify' => '1'));
    $Messages->getRecords($Query->getQuery());
    foreach ($Messages->keys() as $key) {
        $Message = $Messages->getRowObject($key);
        $Message->event_log_notify = 0;
        $Message->save();
    }
}

/**
 * Use <del> and <ins> tags to annotate the difference between two blocks of text.
 * @param text $was
 * @param text $is
 * @return string the $was text with the markup tags
 */
function markupDiffBetweenTextBlocks($was, $is)
{
    require_once("PHPFineDiff/finediff.php");
    $opcodes = FineDiff::getDiffOpcodes($was, $is, FineDiff::wordDelimiters /* , default granularity is set to character */);
    return FineDiff::renderDiffToHTMLFromOpcodes($was, $opcodes);
}

function checkWasChangedField(&$changelist, $name, $was = null, $is = null)
{
    $was_value_empty = is_null($was) || ($was==='');
    $is_value_empty = is_null($is) || ($is==='');
    if (strcmp($was, $is) !== 0) {
        if (($was_value_empty && !$is_value_empty)) {
            $changelist[] = "<b>{$name}</b> set: <ins>'{$is}'</ins>.";
        } else if ((!$was_value_empty && $is_value_empty)) {
            $changelist[] = "<b>{$name}</b> unset: <del>'{$was}'</del>.";
        } else {
            $to_text = markupDiffBetweenTextBlocks($was, $is);
            $changelist[] = "<b>{$name}</b> changed: {$to_text}.";
        }
    }
}

function checkWasChangedDefinition(&$changelist, $name, $was = null, $is = null)
{
    $was_value_empty = is_null($was) || ($was==='');
    $is_value_empty = is_null($is) || ($is==='');
    if (strcmp($was, $is) !== 0) {
        if (($was_value_empty && !$is_value_empty)) {
            $changelist[] = "<b>{$name}</b> added: <ins>'{$is}'</ins>.";
        } else if ((!$was_value_empty && $is_value_empty)) {
            $changelist[] = "<b>{$name}</b> removed: <del>'{$was}</del>'.";
        } else {
            $to_text = markupDiffBetweenTextBlocks($was, $is);
            $changelist[] = "<b>{$name}</b> changed: {$to_text}.";
        }
    }
}

function checkWasChangedItemField(&$changelist, $name, $was = null, $is = null)
{
    $was_value_empty = is_null($was) || ($was==='');
    $is_value_empty = is_null($is) || ($is==='');
    if (strcmp($was, $is) !== 0) {
        if (($was_value_empty && !$is_value_empty)) {
            $changelist[] = "<b>{$name}</b> set to '{$is}'.";
        } else if ((!$was_value_empty && $is_value_empty)) {
            $changelist[] = "<b>{$name}</b> no longer set to '{$was}'.";
        } else {
            $to_text = markupDiffBetweenTextBlocks($was, $is);
            $changelist[] = "<b>{$name}</b> changed from '{$was}' to '{$is}'.";
        }
    }
}

function checkWasChangedItemFieldByFieldname(&$changelist, $fieldname, $was = null, $is = null)
{
    $was_value_empty = is_null($was) || ($was==='');
    $is_value_empty = is_null($is) || ($is==='');
    if (strcmp($was, $is) !== 0) {
        if (($was_value_empty && !$is_value_empty)) {
            $changelist[$fieldname] = "set to '{$is}'";
        } else if ((!$was_value_empty && $is_value_empty)) {
            $changelist[$fieldname] = "deleted";
        } else {
            $to_text = markupDiffBetweenTextBlocks($was, $is);
            $changelist[$fieldname] = "set to '{$is}'";
        }
    }
}

function fetch_like_query($str, $opening = '%', $closing = '%')
{
    $e = '=';
    $str = str_replace(array($e, '_', '%'), array($e.$e, $e.'_', $e.'%'), $str);
    $escaped = mysqli_real_escape_string(DbSchema::getInstance()->getConnectionLink(), $str);
    return "LIKE '{$opening}{$escaped}{$closing}' ESCAPE '='";
}

function make_subcaption_if_defined($fieldkey)
{
    $subcaptions = Zend_Registry::get('config')->subcaptions;
    return isset($subcaptions->{$fieldkey}) ? '<br><span class="paren">'.$subcaptions->{$fieldkey}.'</span>' : '';
}

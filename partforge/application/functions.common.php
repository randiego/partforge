<?php
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

// note: REQUIRED_SYM must be defined someplace.  E.g., define('REQUIRED_SYM','<span class="req_field">*</span>');

// for popup calendar: define('PATH_TO_SCRIPTS', 'stdlib/');
// for required fields: define('REQUIRED_SYM','<span class="req_field">*</span>');

function trim_recursive(&$var)
{
    if (is_string($var)) {
        $var = trim($var);
    } elseif (is_array($var)) {
        foreach ($var as $key => $value) {
            trim_recursive($var[$key]);
        }
    } elseif (is_object($var)) {
        foreach (get_object_vars($var) as $key => $value) {
            trim_recursive($var->$key);
        }
    }
}

function script_time()
{
    $SCRIPT_TIME = Zend_Registry::get('script_time');
    if (!isset($SCRIPT_TIME)) {
        return time();
    } else {
        return $SCRIPT_TIME;
    }
}

function time_to_mysqldatetime($tm)
{
    return date('Y-m-d H:i:s', $tm);
}

function time_to_mysqldate($tm)
{
    return date('Y-m-d', $tm);
}

function str_contains($haystack, $needle, $offset = 0)
{
    return (strpos($haystack, $needle, $offset) !==false);
}

function extract_column($records, $fieldname)
{
    $out = array();
    foreach ($records as $index => $record) {
        $out[$index] = $record[$fieldname];
    }
    return $out;
}

function format_field_generic($fieldname)
{
    $fieldname = str_replace('_', ' ', $fieldname);
    $fieldname = ucwords($fieldname);
    return $fieldname;
}

function generate_password()
{
    $letters = array("a", "b", "c", "d", "e", "f", "g", "h" ,"i", "j", "k", "m", "n", "p", "q", "r", "s", "t", "u", "v", "w", "x", "y", "z");
    $numbers = array("2", "3", "4", "5", "6", "7", "8", "9");
    $pw = '';
    $pw .= $letters[rand(0, 23)];
    $pw .= $letters[rand(0, 23)];
    $pw .= $letters[rand(0, 23)];
    $pw .= $numbers[rand(0, 7)];
    $pw .= $numbers[rand(0, 7)];
    $pw .= $numbers[rand(0, 7)];
    return $pw;
}

function generateRandomString($length = 32)
{
    if (Zend_Registry::get('config')->config_for_testing) {
        // if we are doing approval testing, this needs to be the same each time.
        return md5(time_to_mysqldatetime(script_time()));
    }
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

function is_self_reference($url)
{
    return ($url == self_url().'?'.$_SERVER['QUERY_STRING']);
}

/*
 Call Classes for remembering were to return to from a given form
*/

class UrlCallRegistry {
    protected $_target_url_array = array();
    protected $_return = null;
    protected $_return_conditions = null;
    protected $_sessionvar = 'url_call_registry';
    protected $_last_chance_return_url; // if there is no matching return path for some reason
    protected $_controller_action;
    protected $_propagating_param_names = array();

    public function __construct(DBControllerActionAbstract $controller_action, $last_change_url)
    {
        if (!isset($_SESSION[$this->_sessionvar]) || !is_array($_SESSION[$this->_sessionvar])) {
            $_SESSION[$this->_sessionvar] = array();
        }
        $this->_controller_action = $controller_action;
        $this->_last_chance_return_url = $last_change_url;
    }

    public function setPropagatingParamNames($param_names)
    {
        $this->_propagating_param_names = $param_names;
        return $this;
    }

    public function getPropagatingParamNames()
    {
        return $this->_propagating_param_names;
    }

    public function unsetPropagatingParam($param_names)
    {
        if (!is_array($param_names)) {
            $param_names = array($param_names);
        }
        $this->_propagating_param_names = array_diff($this->_propagating_param_names, $param_names);
        return $this;
    }

    public function addPropagatingParamName($name)
    {
        if (!in_array($name, $this->_propagating_param_names)) {
            $this->_propagating_param_names[] = $name;
        }
    }

    public function getPropagatingParamValues()
    {
        $params = $this->_controller_action->params;
        $out = array();
        foreach ($this->_propagating_param_names as $name) {
            if (isset($params[$name])) {
                $out[$name]=$params[$name];
            }
        }
        return $out;
    }

    public function setTarget($viewname = null, $filename = null, $params = null)
    {
        if (!empty($viewname)) {
            $this->_target_url_array['view'] = $viewname;
        }
        if (!empty($filename)) {
            $this->_target_url_array['file'] = $filename;
        }
        if (!empty($params)) {
            $this->_target_url_array['params'] = $params;
        }
        return $this;
    }

    public function setReturn($url = '')
    {
        $this->_return = $url;
        return $this;
    }

    public function setReturnConditions($params = array())
    {
        $this->_return_conditions = $params;
        return $this;
    }

    public function addPath($return_conditions, $return_url)
    {
        $arr = array('conditions' => $return_conditions, 'return_url' => $return_url);
        // this is a weird way to add an item with no duplicates
        $_SESSION[$this->_sessionvar][md5(serialize($arr['conditions']))] = $arr;
        return $this;
    }

    public function formatHandlerUrl($buttonname, $viewname, $filename, $params = array())
    {
        if (!isset($params['form'])) {
            $params['form'] = '';
        }
        if (!isset($params[$buttonname])) {
            $params[$buttonname] = '';
        }
        return Zend_Controller_Front::getInstance()->getRequest()->getBaseUrl().'/'.$filename.'/'.$viewname.(!empty($params) ? '?'.http_build_query($params) : '');
    }

    public static function formatViewUrl($viewname, $filename, $params = array())
    {
        return Zend_Controller_Front::getInstance()->getRequest()->getBaseUrl().'/'.$filename.'/'.$viewname.(!empty($params) ? '?'.http_build_query($params) : '');
    }

    protected function getFilledUrlArrayWithDefaults($url_array)
    {
        $out = $url_array;
        if (!isset($out['view'])) {
            $out['view'] = $this->_controller_action->params['action'];
        }
        if (!isset($out['file'])) {
            $out['file'] = $this->_controller_action->params['controller'];
        }
        if (!isset($out['params'])) {
            $out['params'] = array();
        }
        // if a variable from getPropagatingParamValues() is not in $out['params'] then put it there
        foreach ($this->getPropagatingParamValues() as $fieldname => $value) {
            if (!isset($out['params'][$fieldname])) {
                $out['params'][$fieldname] = $value;
            }
        }
        return $out;
    }

    // with no params, returns the last set current location settings as formatted url.  This params it is initialized
    public function getCurrentViewUrl($viewname = null, $filename = null, $params = null)
    {
        $arr = array();
        if (($viewname!=null) || ($filename!=null) || ($params!=null)) {
            if (!empty($viewname)) {
                $arr['view'] = $viewname;
            }
            if (!empty($filename)) {
                $arr['file'] = $filename;
            }
            if (!empty($params)) {
                $arr['params'] = $params;
            }
        }
        $a = $this->getFilledUrlArrayWithDefaults($arr);
        return self::formatViewUrl($a['view'], $a['file'], $a['params']);
    }

    public function getTargetViewUrl($viewname = null, $filename = null, $params = null)
    {
        if (($viewname!=null) || ($filename!=null) || ($params!=null)) {
            $this->setTarget($viewname, $filename, $params);
        }
        $a = $this->getFilledUrlArrayWithDefaults($this->_target_url_array);
        return self::formatViewUrl($a['view'], $a['file'], $a['params']);
    }

    public function getCurrentHandlerUrl($buttonname, $viewname = null, $filename = null, $params = null)
    {
        $arr = array();
        if (($viewname!=null) || ($filename!=null) || ($params!=null)) {
            if (!empty($viewname)) {
                $arr['view'] = $viewname;
            }
            if (!empty($filename)) {
                $arr['file'] = $filename;
            }
            if (!empty($params)) {
                $arr['params'] = $params;
            }
        }
        $a = $this->getFilledUrlArrayWithDefaults($arr);
        return $this->formatHandlerUrl($buttonname, $a['view'], $a['file'], $a['params']);
    }

    public function getTargetHandlerUrl($buttonname, $viewname = null, $filename = null, $params = null)
    {
        if (($viewname!=null) || ($filename!=null) || ($params!=null)) {
            $this->setTarget($viewname, $filename, $params);
        }
        $a = $this->getFilledUrlArrayWithDefaults($this->_target_url_array);
        return $this->formatHandlerUrl($buttonname, $a['view'], $a['file'], $a['params']);
    }

    public function getReturnCondition()
    {
        if (empty($this->_return_conditions)) {
            $a = $this->getFilledUrlArrayWithDefaults($this->_target_url_array);
            return array('action' => $a['view'], 'controller' => $a['file']);
        } else {
            return $this->_return_conditions;
        }
    }

    public function getReturnUrl()
    {
        return empty($this->_return) ? $this->getCurrentViewUrl() : $this->_return;
    }

    public function callView($viewname = null, $filename = null, $params = null)
    {
        // set Target first since the return conditions could depend on it
        if (($viewname!=null) || ($filename!=null) || ($params!=null)) {
            $this->setTarget($viewname, $filename, $params);
        }

        $this->addPath($this->getReturnCondition(), $this->getReturnUrl());

        spawnurl($this->getTargetViewUrl());
    }

    public function jumpToView($viewname = null, $filename = null, $params = null)
    {
        if (($viewname!=null) || ($filename!=null) || ($params!=null)) {
            $this->setTarget($viewname, $filename, $params);
        }
        spawnurl($this->getTargetViewUrl());
    }

    public function jumpToHandler($buttonname, $viewname = null, $filename = null, $params = null)
    {
        if (($viewname!=null) || ($filename!=null) || ($params!=null)) {
            $this->setTarget($viewname, $filename, $params);
        }
        spawnurl($this->getTargetHandlerUrl($buttonname));
    }

    /*
     the location that would be called if returnFromCall() is called
    */
    public function getCallingUrl()
    {
        $arr = array_reverse($_SESSION[$this->_sessionvar]);
        foreach ($arr as $key => $path) {
            $match = true;
            foreach ($path['conditions'] as $field => $value) {
                if ($this->_controller_action->params[$field]!=$value) {
                    $match = false;
                    break;
                }
            }

            /*
             need to test if we are going to loop back on ourselves and prevent this.
            Need to use the router to get the comparison right.
            */
            if ($match) {
                $request = new Zend_Controller_Request_Http();

                // make well formed uri to give to setRequestUri()
                if (preg_match('"^(http://|https://)"i', trim($path['return_url']))) {
                    $return_uri = Zend_Uri::factory($path['return_url']);
                    if (!$return_uri->valid()) {
                        return $this->_last_chance_return_url;
                    }
                    $return_path  = $return_uri->getPath();
                    $return_query = $return_uri->getQuery();
                    if (!empty($return_query)) {
                        $return_path .= '?' . $return_query;
                    }
                    $request->setRequestUri($return_path);   // instead of current uri
                } else {
                    $request->setRequestUri($path['return_url']);
                }

                $request->setParamSources(array());             // instead of current getvars
                Zend_Controller_Front::getInstance()->getRouter()->route($request);
                $jump_to_params = array();
                parse_str(parse_url($path['return_url'], PHP_URL_QUERY), $jump_to_params);
                $jump_to_params = array_merge($jump_to_params, $request->getParams());
                $matchagain = true;
                foreach ($path['conditions'] as $field => $value) {
                    if ($jump_to_params[$field]!=$value) {
                        $matchagain = false;
                        break;
                    }
                }

                if ($matchagain) {  // we are going to loop.  So go ahead and jump, but first delete this entry in the stack for next time
                    unset($_SESSION[$this->_sessionvar][$key]);
                }
                return $path['return_url'];
            }
        }
        return $this->_last_chance_return_url;
    }

    public function returnFromCall()
    {
        spawnurl($this->getCallingUrl());
    }
}

function self_url($scheme = '', $host = '')
{
    if (!$scheme) {
        $scheme = isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS']=='on') ? 'https' : 'http';
    }
    if (!$host) {
        $host = $_SERVER['HTTP_HOST'];
    }

    $request = Zend_Controller_Front::getInstance()->getRequest();
    return $scheme.'://'.$host.$request->getBaseUrl().'/'.$request->getControllerName().'/'.$request->getActionName();
}

/* for logging any problems that the user wouldn't know what to do with, but I should know about */

function logerror($msg)
{
    $webmaster_email = Zend_Registry::get('config')->webmaster_email;
    $headers = "From: Error Logger <".$webmaster_email.">\r\n";
    $headers .= "X-Mailer: PHP\r\n"; //mailer
    $headers .= "X-Priority: 3\r\n"; //1 UrgentMessage, 3 Normal
    $headers .= "X-MSMail-Priority: Normal\r\n";
    @error_log($_SESSION['account']->login_id.' '.date("M d Y H:i:s", script_time()).' : '.$msg, 1, $webmaster_email, $headers);
}

/*
 this is a safe spawn to another url.  It checks to see if headers have been sent already
if something has been written, say so and give a "continue" button.  Better: if all output
has been buffered using ob_start() up until this point, then quietly email to WEBMASTER and
continue on.  For HTTP 1.1, $url must be absolute.

*/

function spawnurl($url)
{
    if (ob_get_length()) { // then output buffering is turned on and we should make sure nothing has been written
        if (ob_get_length() > 0) {
            $msg = "[".ob_get_contents()."]\n\r";
            $msg .= "Unexpected output generated while jumping to $url";
            logerror($msg);  // quietly email what went wrong
            ob_end_clean();
        }
        //      header("Location: ".$url);    I removed this because I had done it when I got qdregister to work in a subdirectory.  But I don't know why I needed this gone.
        exit;
    } elseif (headers_sent()) { // then we've really goofed and now we can't hide it anymore. User will press "continue"
        $msg = "Unexpected output occured while trying to jump to $url";
        logerror($msg." webmaster should have protected code with ob_start()");
        showdialog('Error', $msg, array('Continue' => $url));
    } else {
        header("Location: ".$url);
        exit;
    }
}

function is_valid_datetime($str)
{
    return !((strtotime($str)==-1) || (strtotime($str)===false));
}

function check_valid_nonnegative_number_params($fieldname, $param, &$errormsg)
{
    if (!is_numeric($param)) {
        $errormsg[$fieldname] = $fieldname.' must be a number.';
    } elseif ($param < 0) {
        $errormsg[$fieldname] = $fieldname.' cannot be a negative number.';
    }
}

function js_wrapper($js_code)
{
    return '
			<script type="text/javascript">
			<!--
			'.$js_code.'
					//-->
					</script>
					';
}

function checkbox_html($fieldname, $value, $attributes = '')
{
    $checked = $value ? ' checked' : '';
    $idname = $fieldname.'_1';
    return '<INPUT TYPE="hidden" NAME="'.$fieldname.'" VALUE="0"'.$attributes.'>
									<INPUT class="checkboxclass" TYPE="checkbox" NAME="'.$fieldname.'" VALUE="1" ID="'.$idname.'"'.$checked.$attributes.'>';
}

function merge_in_post_vars($fields, $default_fields = array())
{
    // for example, $default_fields = array('rollaway_checkbox' => 0,'crib_checkbox' => 0)
    // looks for fields $default_zero_fields in $fields.  If one exists and it does not in $_POST then set one in $_POST equal to zero so that the merge operation will overwrite zero.
    // the problem is that checkboxes do not return anything if uncheck.  This ensures that a user unchecking a form checkbox will really make in unset in the merged array.
    foreach ($default_fields as $name => $default_value) {
        if (!isset($_POST[$name])) {
            $_POST[$name] = $default_value;
        }
    }
    $fields = array_merge($fields, $_POST);
    return $fields;
}

/**
 * Pass this a file base string (no extention) to remove undesirable characters and replace with underscores
 *
 * @param string $filename, just the part without the extension
 *
 * @return string sanitized file name string
 */
function make_filename_safe($filename)
{
    // returns a version of filename with no spaces or weird characters at all
    $temp = $filename;

    // Replace spaces with a '_'
    $temp = str_replace(" ", "_", $temp);

    // Loop through string
    $result = '';
    for ($i=0; $i<strlen($temp); $i++) {
        if (preg_match('([0-9]|[a-z]|_|[A-Z])', $temp[$i])) {
            $result = $result . $temp[$i];
        }
    }

    // truncate $result if needed
    $maxbasename = 64;
    if (strlen($result) > $maxbasename) {
        $result = substr($result, 0, $maxbasename);
    }

    if (!$result) {
        $result = 'file.';
    }

    // Return filename
    return $result;
}

/**
 * Take any string and sanitize and truncate to a nice variable name
 * @param string $str
 * @param int $maxlen
 * @return string
 */
function make_good_var_name($str, $maxlen = 40)
{
    $stra = strtolower( (make_filename_safe($str)) );
    if (strlen($stra)<=$maxlen) {
        return $stra;
    } else {
        $start = strlen($stra) - $maxlen + 1;
        $goodending = strrpos($stra, '_', -$start);
        if (($goodending==0) || ($goodending===false)) {
            $goodending = $maxlen;
        }
        return substr($stra, 0, $goodending);
    }
}

function prefix_array_keys($in, $key_prefix)
{
    $out = array();
    foreach ($in as $key => $value) {
        $out[$key_prefix.$key] = $value;
    }
    return $out;
}


function extract_prefixed_keys($sourcearray, $prefix_str, $remove_prefix = false)
{
    $out = array();
    foreach ($sourcearray as $key => $value) {
        if (preg_match('/^'.$prefix_str.'(.*)$/', $key, $match)) {
            $keyout = $remove_prefix ? $match[1] : $key;
            $out[$keyout] = $value;
        }
    }
    return $out;
}

function nbsp_ifblank($inhtml)
{
    return ($inhtml!=='') ? $inhtml : '&nbsp;';
}

function mailto_link_href($emailaddr, $subject = '', $body = '')
{
    $params = array();
    if ($subject) {
        $params[] = 'subject='.rawurlencode($subject);
    }
    if ($body) {
        $params[] = 'body='.rawurlencode($body);
    }
    $params_str = (count($params)>0) ? '?'.implode('&', $params) : '';
    return 'mailto:'.$emailaddr.$params_str;
}

function mailto_link($emailaddr, $text = '', $subject = '', $body = '')
{
    if (!$text) {
        $text = $emailaddr;
    }
    return '<A HREF="'.mailto_link_href($emailaddr, $subject, $body).'" title="Send email to '.$emailaddr.'">'.TextToHtml($text).'</A>';
}

function texttolines($text)
{
   // def = use all lines
    $stripreturns = str_replace("\r", '', $text);
    return explode("\n", $stripreturns);
}

function wrapemailtext($text, $width, $linebreak)
{
    $arr = texttolines($text);
    foreach ($arr as $key => $val) {
        $arr[$key] = wordwrap($val, $width, $linebreak, 1);
    }
    return implode($linebreak, $arr);
}

function text_to_wrappedhtml($text, $width)
{
    return nl2br(TextToHtml(wrapemailtext($text, $width, "\r\n")));
}

function text_to_unwrappedhtml($text)
{
    return nl2br(TextToHtml($text));
}

function trunc_text($text, $width)
{
    if (strlen($text) > $width) {
        return substr($text, 0, $width-3).'...';
    } else {
        return $text;
    }
}

function text_to_trunc_html($text, $width)
{
    return TextToHtml(trunc_text($text, $width));
}

function linkify($url, $text, $title = '', $class = '', $onclick = '', $target = '', $id = '')
{
    $title_attr = $title ? ' title="'.$title.'"' : '';
    $class_attr = $class ? ' class="'.$class.'"' : '';
    $onclick_attr = $onclick ? ' onclick="'.$onclick.'"' : '';
    $target_attr = $target ? ' target="'.$target.'"' : '';
    $id_attr = $id ? ' id="'.$id.'"' : '';
    return "<a href=\"{$url}\"$id_attr$title_attr$class_attr$onclick_attr$target_attr>{$text}</a>";
}

function pre_text_to_popup_html($text)
{
    $html = '<table border="0" cellspacing="0" cellpadding="0"><tr><td><PRE>';
    $html .= TextToHtml( wrapemailtext( $text, 60, "\r\n") );
    $html .= '</PRE></td></tr></table>';
    return $html;
}

function text_to_readmore_template($text, $width, $viewlines, $maxlines, $readmorelink, $usetables, $force_readmorelink)
{
    $lines = texttolines(wrapemailtext(trim($text), $width, "\r\n"));
    if (count($lines) <= $maxlines) {
        $html = $usetables ? '<table width="100%" border="0" cellspacing="0" cellpadding="0"><tr><td nowrap>' : '<div>';
        $html .= text_to_wrappedhtml($text, $width);
        $html .= $usetables ? '</td></tr>' : '</div>';
        if ($force_readmorelink) {
            $html .= $usetables ? '<tr><td align="left">'.$readmorelink.'</td></tr></table>' : '<div>'.$readmorelink.'</div>';
        } else {
            $html .= $usetables ? '</table>' : '';
        }
        return $html;
    } else {
        $out = array();
        for ($i=0; $i<$viewlines; $i++) {
            $out[] = $lines[$i];
        }
        $html = $usetables ? '<table width="100%" border="0" cellspacing="0" cellpadding="0"><tr><td nowrap>' : '<div>';
        $html .= nl2br(TextToHtml(implode("\r\n", $out)));
        $html .= $usetables ? '</td></tr>' : '</div>';
        $html .= $usetables ? '<tr><td align="left">'.$readmorelink.'</td></tr></table>' : '<div>'.$readmorelink.'</div>';
        return $html;
    }
}

function text_to_readmore_html($text, $width, $viewlines, $maxlines, $readmorelink)
{
    return text_to_readmore_template($text, $width, $viewlines, $maxlines, $readmorelink, true, false);
}

function text_to_readmore_html2($text, $width, $viewlines, $maxlines, $readmorelink, $force_readmorelink)
{
    return text_to_readmore_template($text, $width, $viewlines, $maxlines, $readmorelink, false, $force_readmorelink);
}
function description_text_to_popup_html($desctext)
{
    $html = '<table border="0" cellspacing="0" cellpadding="0"><tr><td><p>';
    $html .= nl2br(TextToHtml($desctext));
    $html .= '</p></td></tr></table>';
    return $html;
}

/* date functions */


function block_text_html($text)
{
 // used for some showdialog messages
    return '<table class="blocktext" WIDTH="400" BORDER="0" CELLPADDING="0" CELLSPACING="0"><tr><td>'.$text.'</td></tr></table>';
}

function format_select_tag($cc_type_array, $field_name, $params, $onchange = '', $disabled = false, $nothingselectedtext = '-- select one --', $attributes = '', $class = 'inputboxclass')
{
    $html = '';
    $is_anything_selected = false;
    $in_value = isset($params[$field_name]) ? $params[$field_name] : null;
    // must make sure integer string is compared properly to integer index.  Also make a null on the input equivalent to '' on index
    $in_value = (is_numeric($in_value) && (intval($in_value)==$in_value )) ? (int)$in_value : ((null==$in_value) ? '' : $in_value);
    foreach ($cc_type_array as $value => $text) {
        $value = is_numeric($value) && (intval($value)==$value ) ? (int)$value : $value;
        if ($in_value===$value) {
            $selected = ' selected';
            $is_anything_selected = true;
        } else {
            $selected = '';
        }
        $html .= '
				<option value="'.$value.'"'.$selected.'>'.TextToHtml($text).'</option>';
    }

    if (!$is_anything_selected) {
        $html = '<option value="" selected>'.$nothingselectedtext.'</option>'.$html;
    }

    $onchange_html = $onchange ? ' onChange="'.$onchange.'"' : '';
    return '
			<select class="'.$class.'" name="'.$field_name.'"'.$onchange_html.$attributes.($disabled ? ' disabled' : '').'>
					'.$html.'
							</select>
							';
}

function format_radio_tags($values, $field_name, $params, $onclick = '', $attributes = '')
{
    $tags = array();
    $onclick_html = $onclick ? ' onClick="'.$onclick.'"' : '';
    $ii = 0;
    foreach ($values as $value => $text) {
        $in_value = $params[$field_name];
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
        $tags[] = '<input class="radioclass" type="radio" name="'.$field_name.'" value="'.$value.'" id="'.$idname.'"'.$attributes.$selected.$onclick_html.' />&nbsp;<label for="'.$idname.'">'.TextToHtml($text).'</label>';
    }
    return implode('<br />', $tags);
}

/*
 when a method is specified as parameter for a display field or select list, this is where we part it.
for example callMethodLiteral($PersonRowObj, 'getFullName','name not found') would try to call method
$PersonRowObj->getFullName().
*/
function callMethodLiteral(TableRow $dbtable, $methodname, $default)
{
    $arr = explode('.', $methodname);
    if (count($arr)==1) {
        $return_value = method_exists($dbtable, $methodname) ? $dbtable->$methodname() : $default;
    } elseif ($dbtable instanceof DbTableRow) {
        $joins = $dbtable->getJoinFieldsAndTables();
        $join_obj = $joins[$arr[0]]['rhs_dbtableobj'];
        $return_value = method_exists($join_obj, $arr[1]) ? $join_obj->{$arr[1]}() : $default;
    } else {
        $return_value = $default;
    }
    return $return_value;
}

function parseSelectValues($fieldname, TableRow $dbtable)
{
    $fieldtype = $dbtable->getFieldType($fieldname);
    if (isset($fieldtype['options'])) {
        $select_name = $fieldtype['options'];
        if (is_array($select_name)) {
            $select_values = $select_name;
        } else {
            $default = array($dbtable->{$fieldname} => $dbtable->{$fieldname});
            $select_values = callMethodLiteral($dbtable, $select_name, $default);
        }
        return $select_values;
    } else {
        return array();
    }
}

function parseJoinValues($fieldname, TableRow $dbtable)
{
    return ($dbtable instanceof DbTableRow) ? $dbtable->getJoinSelectOptions($fieldname) : array($dbtable->{$fieldname} => $dbtable->{$fieldname});
}

function getSortOrderArray($sort_order_field, DbTableRow $dbtable)
{
    $out = array();

    $PeerRecords = new DBRecords(DbSchema::getInstance()->dbTableRowObjectFactory($dbtable->getTableName(), false, $dbtable->getParentPointerIndexName()), $dbtable->getParentPointerIndexName(), '');
    $PeerRecords->getRecordsById($dbtable->{$dbtable->getParentPointerIndexName()}, $sort_order_field);

    $curr_sort_pos = $dbtable->{$sort_order_field};

    if ((count($PeerRecords->keys()) == 0) && !empty($curr_sort_pos)) {
        $out[$curr_sort_pos] = "(end)";
    } else if (empty($curr_sort_pos)) {
        $out[10*count($PeerRecords->keys()) + 10] = "(end)";
    } else {
        $min = $PeerRecords->getMinOfField($sort_order_field);
        if ($curr_sort_pos < $min) {
            $out[$curr_sort_pos] = '(beginning)';
        }

        foreach ($PeerRecords->keys() as $key) {
            $RowObj = $PeerRecords->getRowObject($key);

            $pos = $RowObj->{$sort_order_field};
            if ($pos < $curr_sort_pos) {
                $out[$pos-1] = "before: ".$RowObj->getCoreDescription();
            } elseif ($pos == $curr_sort_pos) {
                $out[$pos] = "(current position)";
            } else {
                $out[$pos+1] = "after: ".$RowObj->getCoreDescription();
            }
        }

        $max = $PeerRecords->getMaxOfField($sort_order_field);
        if ($curr_sort_pos > $max) {
            $out[$curr_sort_pos] = '(end)';
        }
    }
    return $out;
}

/*
 This converts action names (from DBTableRow:: getListOfDetailActions()) into an icon or name
*/
function detailActionToHtml($action_name, $detail_action)
{
    $baseurl = Zend_Controller_Front::getInstance()->getRequest()->getBaseUrl();
    $name_html = '';
    if ($action_name=='delete') {
        $name_html = ('Delete'==$detail_action['buttonname'])
        ? '<IMG style="vertical-align:middle;" src="'.$baseurl.'/images/deleteicon.png" width="16" height="16" border="0" alt="delete">'
                : '<IMG style="vertical-align:middle; background-color:#DDD;" src="'.$baseurl.'/images/deleteicon.png" width="16" height="16" border="0" alt="delete">';
    }
    //    if ($action_name=='editview') $name_html = '<IMG style="vertical-align:middle;" src="'.$baseurl.'/images/editicon.gif" width="16" height="16" border="0" alt="edit">';
    if ($action_name=='finish') {
        $name_html = '<IMG style="vertical-align:middle;" src="'.$baseurl.'/images/flag_blue.png" width="16" height="16" border="0" alt="finish">';
    }
    return $name_html;
}

function extractFieldOptions($optionss, $fieldname)
{
    // this gets the option codes for just one field from a list describing many fields
    if (!is_array($optionss)) {
        $optionss = array('ALL' => $optionss);
    }
    $globaloptions = isset($optionss['ALL']) ? (!is_array($optionss['ALL']) ? array($optionss['ALL']) : $optionss['ALL']) : array();

    $options = $globaloptions;
    $isset = isset($optionss[$fieldname]);
    $issetnot = isset($optionss['!'.$fieldname]);
    if ($isset) {
        $options = array_merge($options, (!is_array($optionss[$fieldname]) ? array($optionss[$fieldname]) : $optionss[$fieldname]));
    }
    if ($issetnot) {
        $options = array_diff($options, (!is_array($optionss['!'.$fieldname]) ? array($optionss['!'.$fieldname]) : $optionss['!'.$fieldname]));
    }
    return $options;
}

// example, options = array('ALL' => array('Required'), 'track' => array('UseRadiosForMultiSelect'))
function fetchEditTableTR($fieldlayout, TableRow $dbtable, $optionss = '', $editable = true, $callBackFunction = null)
{
    $html = '';
    $editfields = $dbtable->getEditFieldNames();
    foreach ($fieldlayout as $row) {
        if (!is_array($row)) {
            throw new Exception('fetchEditTableTR(): layout row is not an array.');
        }

        if (isset($row['class'])) {
            $row_class = $row['class'];
            unset($row['class']);
        } else {
            $row_class = '';
        }

        $html .= '<TR'.($row_class ? ' class="'.$row_class.'"' : '').'>';
        $is_single_column = (count($row)==1);
        foreach ($row as $fielddef) {
            // abreviated entry??
            if (!is_array($fielddef)) {
                $fielddef = array('dbfield' => $fielddef);
            }


            if (isset($fielddef['dbfield'])) {
                $fieldname = $fielddef['dbfield'];
                // set any add-on attributes
                if (isset($fielddef['field_attributes'])) {
                    foreach ($fielddef['field_attributes'] as $key => $attribute) {
                        $dbtable->setFieldAttribute($fieldname, $key, $attribute);
                    }
                }


                // set any add-on display options, e.g. UseRadiosForMultiSelect
                $options = extractFieldOptions($optionss, $fieldname);
                if (isset($fielddef['display_options'])) {
                    $options = array_unique(array_merge($options, $fielddef['display_options']));
                }

                $can_edit = in_array($fieldname, $editfields) && $editable;
                $marker = $can_edit && (($dbtable->isRequired($fieldname) && !in_array('NotRequired', $options)) || in_array('Required', $options)) ? REQUIRED_SYM.' ' : '';
                if ($callBackFunction!==null) {
                    $rhs_html = $callBackFunction($fieldname, $dbtable);
                } else if ($can_edit) {
                    $rhs_html = $dbtable->formatInputTag($fieldname, $options);
                    if ($dbtable instanceof DBTableRow) {
                        $dbtable->overrideWithUserSubcaption($fieldname, true);
                    }
                } else {
                    $rhs_html = $dbtable->formatPrintField($fieldname);
                }

                $html .= '<TH>'.$dbtable->formatFieldname($fieldname, $marker).'</TH>
						<TD'.($is_single_column ? ' colspan="3"' : '').'>'.$rhs_html.'</TD>
								';
            } elseif (isset($fielddef['calcfield']) && isset($fielddef['method'])) {
                $fieldname = $fielddef['calcfield'];
                // set any add-on attributes
                if (isset($fielddef['field_attributes'])) {
                    foreach ($fielddef['field_attributes'] as $key => $attribute) {
                        $dbtable->setFieldAttribute($fieldname, $key, $attribute);
                    }
                }
                $method = $fielddef['method'];
                $html .= '<TH>'.$dbtable->formatFieldname($fieldname).'</TH>
						<TD'.($is_single_column ? ' colspan="3"' : '').'>'.TextToHtml(callMethodLiteral($dbtable, $method, '')).'</TD>
								';
            } elseif (isset($fielddef['caption'])) {
                $html .= '<td colspan="4">'.$fielddef['caption'].'</td>';
            }
        }
        $html .= '</tr>';
    }
    return $html;
}


function fetchPrintTableTR($fieldnames, TableRow $dbtable)
{
    $html = '';
    foreach ($fieldnames as $fieldname) {
        $html .= '<TR>
				<TH>'.$dbtable->formatFieldname($fieldname).'</TH>
				<TD>'.nbsp_ifblank($dbtable->formatPrintField($fieldname)).'</TD>
				</TR>
			';
    }
    return $html;
}

function fetch_form_tag($html, $attributes = '', $form_id = 'theform')
{
    return '
			<form accept-charset="utf-8" id="'.$form_id.'" method="POST" action="'.self_url().'" name="'.$form_id.'" '.$attributes.'>
					<input type="hidden" name="form" value="">
					'.$html.'
							</form>
							';
}

// this one was a godsend and works on IE and FF, http and https...
function send_download_headers($file_type, $file_name, $attachment = 'attachment; ', $cachecontrol = 'max-age=0')
{
    header( "Pragma: ");
    header( "Cache-Control: {$cachecontrol}");
    header( "Content-Type: $file_type" );
    header( "Content-Disposition: {$attachment}filename=".rawurlencode($file_name) );
}

function TextToHtml($text)
{
    $conv = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    if ($conv=='' && $text!='') {
        return $text;
    } else {
        return $conv;
    }
}

function outputPdfToBrowser($full_filename)
{
    send_download_headers('application/pdf', $full_filename, '');
    header( 'Content-Length: '.filesize($full_filename) );
    header( 'Content-Description: Download Data' );
    readfile($full_filename);
    exit;
}

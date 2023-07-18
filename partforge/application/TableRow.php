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

class TableRow {
    /*
         fieldtype values := {
            caption*
            subcaption
            type*         =  {text, varchar, boolean, datetime, date, enum, multiple, sort_order,
                                print_function, left_join, id, ft_other}  ft_other means there is custom code
            options       // for enum type, is an array of key value pairs, or the name of a method
            len           used in combinatino with varchar and others for length
            input_rows
            required        means this field is required.
            input_cols
            print_function_name    // these function have parameter ($value, is_html)
            disabled
            onchange_js          // js to be put in OnChange handler
            onclick_js          // js to be put in OnClick handler
            join_name           // same as key in dictionary join array
            exclude_on_layout     // don't show on the default layout.
            mode      R means that it is not saved or editable.  RW (default) means it is editable and saveable
         }
         * = required

         for example:
         $tr = new TableRow();
         $tr->setFieldType('item_color', array('type' => 'enum', 'options' => array('Red' => 'Red','Green' => 'Green','Blue' => 'Blue') ));
    */
    protected $_fieldtypes = array();
    protected $_fields = array();


    public function __construct($fieldtypes = null)
    {
        $this->_fieldtypes = array();
        $this->_fields = array();
        if ($fieldtypes!=null) {
            $this->setFieldTypes($fieldtypes);
        }
    }

    public function verifyFieldsExist($list_of_fields)
    {
        if (!is_array($list_of_fields)) {
            $list_of_fields = array($list_of_fields);
        }
        foreach ($list_of_fields as $field) {
            if (!isset($this->_fieldtypes[$field])) {
                throw new Exception("Error.  Field name '{$field}' is not in table '{$this->_table}'");
            }
        }
    }

    public function __set($key, $value)
    {
        $this->_fields[$key] = $value;
    }

    public function __get($key)
    {
        if (isset($this->_fields[$key])) {
            return $this->_fields[$key];
        }

        return null;
    }

    public function getArray()
    {
        return $this->_fields;
    }

    public function assign($spec, $value = null)
    {
        if (is_array($spec)) {
//                $this->_fields = array_merge($this->_fields,$spec);
            foreach ($spec as $fld => $val) {
                $this->{$fld} = $val;
            }
        }

        if (is_string($spec)) {
            $this->{$spec} = $value;
        }
    }

    /*
          like assign() but $in_params is supposed to be the array from a form submission and
          $merge_params supposed to be a persistant session array.  Odd form types will be
          converted to db-friendly formats.  Also note that only fields that have defined
          types in getFieldTypes() are merged in.
    */
    public function assignFromFormSubmission($in_params, &$merge_params)
    {
        foreach ($this->getFieldTypes() as $fieldname => $fieldtype) {
            if (isset($in_params[$fieldname])) {
                if (isset($fieldtype['type']) && ($fieldtype['type']=='multiple') && is_array($in_params[$fieldname])) {
                    $out = array();
                    foreach ($in_params[$fieldname] as $select_value => $is_set) {
                        if ($is_set) {
                            $out[] = $select_value;
                        }
                    }
                    $merge_params[$fieldname] = implode('|', $out);
                } else {
                    $merge_params[$fieldname] = $in_params[$fieldname];
                }
            }
        }

        $this->assign($merge_params);

        // now that all the variables have been assigned, we evaluate and assign the calculated ones.
        foreach ($this->processCalculatedFields() as $fieldname => $assigned_value) {
            $merge_params[$fieldname] = $assigned_value;
        }

        return true;
    }

    /*
            Like assignFromFormSubmission() except it doesn't merge parameters into anything, it
            just assigns only those variables that are defined by the existing types.
     */
    public function assignFromAjaxPost($in_params)
    {
        $merge_params = array();
        return self::assignFromFormSubmission($in_params, $merge_params);
    }

    public function __isset($key)
    {
        return (isset($this->_fields[$key]));
    }

    public function __unset($key)
    {
        if (isset($this->_fields[$key])) {
            unset($this->_fields[$key]);
        }
    }

    public function getFieldTypes()
    {
        return $this->_fieldtypes;
    }

    public function setFieldTypes($fieldtypes)
    {
        $this->_fieldtypes = array_merge($this->_fieldtypes, $fieldtypes);
    }

    public function setFieldType($fieldname, $fieldtype)
    {
        $this->_fieldtypes[$fieldname] = $fieldtype;
    }

    // e.g., setFieldAttribute('comments','input_rows',18)
    public function setFieldAttribute($fieldname, $attribute, $value)
    {
        $this->_fieldtypes[$fieldname][$attribute] = $value;
    }

    public function getFieldAttribute($fieldname, $attribute)
    {
        return isset($this->_fieldtypes[$fieldname][$attribute]) ? $this->_fieldtypes[$fieldname][$attribute] : null;
    }

    public function setIsRequired($fields, $is_required)
    {
        if (!is_array($fields)) {
            $fields = array($fields);
        }
        foreach ($fields as $field) {
            $this->_fieldtypes[$field]['required'] = $is_required;
        }
    }

    public function isRequired($field)
    {
        return isset($this->_fieldtypes[$field]['required']) ? $this->_fieldtypes[$field]['required'] : false;
    }

    public function getFieldNames()
    {
        return array_keys($this->_fieldtypes);
    }

    // default fields for saving the whole record
    public function getSaveFieldNames()
    {
        return array_keys($this->_fieldtypes);
    }

    // default fields for editing (usually excludes the primary index)
    public function getEditFieldNames()
    {
        return array_keys($this->_fieldtypes);
    }

    public function getFieldType($fieldname)
    {
        return isset($this->_fieldtypes[$fieldname]) ? $this->_fieldtypes[$fieldname] : null;
    }

    public function getFieldTypeSize($fieldname)
    {
        return $this->_fieldtypes[$fieldname]['len'];
    }

    public function setFieldTypeParams($fieldname, $datatype, $length = '', $is_required = false, $caption = '', $subcaption = '')
    {
        // $datatype = varchar, or whatever
        $this->_fieldtypes[$fieldname] = array('type' => $datatype);
        $this->setIsRequired($fieldname, $is_required);
        if ($length != '') {
            $this->_fieldtypes[$fieldname]['len'] = $length;
        }
        if ($caption != '') {
            $this->setFieldCaption($fieldname, $caption, $subcaption);
        }
    }

    public function getAddOnFieldNames()
    {
        return array();
    }

    public function validateFields($fieldnames, &$errormsg)
    {
        $this->verifyFieldsExist($fieldnames);
        $EMAIL_REGEX = '^[a-zA-Z0-9_.-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9.-]+$';
        foreach ($fieldnames as $fieldname) {
            if (str_contains($fieldname, 'email')) {
                if ($this->isRequired($fieldname) || (isset($this->_fields[$fieldname]) && (trim($this->_fields[$fieldname])!=''))) {
                    if (!isset($this->_fields[$fieldname]) || !preg_match('"'.$EMAIL_REGEX.'"i', trim($this->_fields[$fieldname]))) {
                        $errormsg[$fieldname] = 'Please make sure '.$this->formatFieldnameNoColon($fieldname).' is entered correctly.';
                    }
                }
            } elseif (preg_match('/_url$/i', $fieldname)) {
                if ($this->isRequired($fieldname) || (isset($this->_fields[$fieldname]) && (trim($this->_fields[$fieldname])!=''))) {
                    if (!isset($this->_fields[$fieldname]) || !preg_match('"^(http://|https://)"i', trim($this->_fields[$fieldname]))) {
                        $errormsg[$fieldname] = 'Please make sure '.$this->formatFieldnameNoColon($fieldname).' starts with http:// or https://.';
                    }
                }
            } elseif (isset($this->_fieldtypes[$fieldname]['type']) && (($this->_fieldtypes[$fieldname]['type'] == 'datetime') || ($this->_fieldtypes[$fieldname]['type'] == 'date') && str_contains($fieldname, 'date'))) {
                if ($this->isRequired($fieldname) || (isset($this->_fields[$fieldname]) && (trim($this->_fields[$fieldname])!=''))) {
                    if (!isset($this->_fields[$fieldname]) || !is_valid_datetime($this->_fields[$fieldname])) {
                        $errormsg[$fieldname] = 'Please make sure '.$this->formatFieldnameNoColon($fieldname).' is a valid date.';
                    }
                }
            } else {
                $regularized_value = DBTableRowItemVersion::varToStandardForm(isset($this->_fields[$fieldname]) ? $this->_fields[$fieldname] : null, $this->_fieldtypes[$fieldname]);
                if ($this->isRequired($fieldname) &&  (is_null($regularized_value) || ($regularized_value===''))) {
                    $errormsg[$fieldname] = 'Please enter '.$this->formatFieldnameNoColon($fieldname).'.';
                } elseif (isset($this->_fields[$fieldname]) && (trim($this->_fields[$fieldname])!='') && str_contains($this->_fieldtypes[$fieldname]['type'], 'float') && !is_numeric($this->_fields[$fieldname])) {
                    $errormsg[$fieldname] = $this->formatFieldnameNoColon($fieldname).' must be numeric.';
                }
            }
        }

        // we do this after all the others to make sure error handling has been first done on the possible parameters that are used in the following expressions.
        foreach ($fieldnames as $fieldname) {
            if (isset($this->_fieldtypes[$fieldname]['type']) && ($this->_fieldtypes[$fieldname]['type'] == 'calculated')) {
                $this->evaluateCalulatedField($fieldname, $errormsg);
            }
        }
    }

    /**
     */
    public static function composeSubcaptionWithValidation($typeinfo, $html = true)
    {
        $subcaption = isset($typeinfo['subcaption']) && is_string($typeinfo['subcaption']) ? trim($typeinfo['subcaption']) : '';
        $min = isset($typeinfo['minimum']) ? trim($typeinfo['minimum']) : '';
        $max = isset($typeinfo['maximum']) ? trim($typeinfo['maximum']) : '';
        $units = isset($typeinfo['units']) ? trim($typeinfo['units']) : '';
        $type = isset($typeinfo['type']) ? trim($typeinfo['type']) : '';
        $out = array();
        if (is_numeric($min) && is_numeric($max)) {
            if ($type=='boolean') {
                if ($min==1) {
                    $out[] = 'Yes';
                } else if ($max==0) {
                    $out[] = 'No';
                }
            } else {
                if ($min==$max) {
                    $out[] = 'exactly '.$min;
                } else {
                    $out[] = $min.' to '.$max;
                }
            }
        } else if (is_numeric($min)) {
            $out[] = ($html ? '&ge;' : '>=').' '.$min;
        } else if (is_numeric($max)) {
            $out[] = ($html ? '&le;' : '<=').' '.$max;
        }
        if ($units) {
            $out[] = $units;
        }
        $synthcap = count($out) > 0 ? implode(' ', $out) : '';
        return strlen($synthcap)==0 ? $subcaption : ((strlen($subcaption)==0) ? $synthcap : '['.$synthcap.']'.($html ? '<br />' : "\n").$subcaption);
    }

    public function formatFieldname($string, $marker = '')
    {
        if (isset($this->_fieldtypes[$string])) {
            $out = $this->_fieldtypes[$string]['caption'].$marker.':';

            $subcaption = '';

            if (isset($this->_fieldtypes[$string]['type']) && in_array($this->_fieldtypes[$string]['type'], array('float','boolean','calculated','attachment'))) {
                $subcaption = self::composeSubcaptionWithValidation($this->_fieldtypes[$string], true);
            } elseif (isset($this->_fieldtypes[$string]['subcaption']) && is_string($this->_fieldtypes[$string]['subcaption'])) {
                $subcaption = $this->_fieldtypes[$string]['subcaption'];
            }

            if (strlen($subcaption)>0) {
                $out .= '<br><span class="paren">'.$subcaption.'</span>';
            }
            return $out;
        } else {
            return '';
        }
    }

    public function formatFieldSimple($fieldname)
    {
            $fieldname = str_replace('_', ' ', $fieldname);
            $fieldname = ucwords(trim($fieldname));
            return $fieldname;
    }

    public function formatFieldnameNoColon($string)
    {
        return $this->_fieldtypes[$string]['caption'];
    }


    public function setFieldCaption($fieldname, $caption, $subcaption = '')
    {
        $this->_fieldtypes[$fieldname]['caption'] = $caption;
        if ($subcaption) {
            $this->_fieldtypes[$fieldname]['subcaption'] = $subcaption;
        }
    }

    public function setFieldTagDisabled($fields)
    {
        if (!is_array($fields)) {
            $fields = array($fields);
        }
        $this->verifyFieldsExist($fields);
        foreach ($fields as $field) {
            $this->setFieldAttribute($field, 'disabled', true);
        }
    }

    public function delimitedFieldDump($fields = array())
    {
        $delimiter = ',';
        $replace_delim = '[COMMA]';
        if (count($fields)==0) {
            $fields=$this->getFieldNames();
        }
        $line = array();
        foreach ($fields as $field) {
                $line[] = str_replace($delimiter, $replace_delim, $this->formatPrintField($field, false));
        }
        return implode($delimiter, $line);
    }

    /*
         * TODO: some of these types should be moved into DBTableRow (left_join ?)
     */
    public function formatInputTag($fieldname, $display_options = array())
    {
        $fieldtype = $this->getFieldType($fieldname);
        $value = $this->$fieldname;
        $type = isset($fieldtype['type']) ? $fieldtype['type'] : '';
        $attributes = isset($fieldtype['disabled']) && $fieldtype['disabled'] ? ' disabled' : '';
        if (in_array($type, array('enum','left_join','sort_order'))) { // a dropdown box
            switch ($type) {
                case 'enum':
                    $select_values = parseSelectValues($fieldname, $this);
                    break;
                case 'left_join':
                    $select_values = parseJoinValues($fieldname, $this);
                    break;
                case 'sort_order':
                    $select_values = getSortOrderArray($fieldname, $this);
                    break;
            }

            if (in_array('UseRadiosForMultiSelect', $display_options)) {
                return format_radio_tags($select_values, $fieldname, $this->getArray(), isset($fieldtype['onclick_js']) ? $fieldtype['onclick_js'] : '', $attributes);
            } else {
                return format_select_tag($select_values, $fieldname, $this->getArray(), isset($fieldtype['onchange_js']) ? $fieldtype['onchange_js'] : '');
            }
        } elseif (in_array($type, array('multiple'))) { // a multiple check box thing
            $select_values = parseSelectValues($fieldname, $this);
            $html = '';
            $value_arr = explode('|', $value);
            foreach ($select_values as $key => $select_value) {
                $checked = in_array($key, $value_arr) ? ' checked' : '';
                $html .= '<INPUT TYPE="hidden" NAME="'.$fieldname.'['.$key.']" VALUE="0"'.$attributes.'>
								  <INPUT class="checkboxclass" TYPE="checkbox" NAME="'.$fieldname.'['.$key.']" VALUE="1"'.$checked.$attributes.'>&nbsp;'.$select_value.'<br>
								  ';
            }
            return $html;
        } elseif ($type == 'boolean') { // a boolean, so lets use a checkbox
            if (in_array('UseCheckForBoolean', $display_options)) {
                return checkbox_html($fieldname, $value, $attributes);
            } else {
                $select_values = array("1" => 'Yes', "0" => 'No');
                $select_value_arr = array($fieldname => (is_null($value) || ($value==='') ? $value : ($value ? "1" : "0")));
                return '<INPUT TYPE="hidden" NAME="'.$fieldname.'" VALUE="">'.format_radio_tags($select_values, $fieldname, $select_value_arr, isset($fieldtype['onclick_js']) ? $fieldtype['onclick_js'] : '', $attributes);
            }
        } else if (in_array($type, array('text'))) { // a text field
            $rows = isset($fieldtype['input_rows']) ? $fieldtype['input_rows'] : '3';
            $cols = isset($fieldtype['input_cols']) ? $fieldtype['input_cols'] : '40';
            return '<TEXTAREA class="inputboxclass" NAME="'.$fieldname.'" ROWS="'.$rows.'" COLS="'.$cols.'"'.$attributes.'>'.TextToHtml($value).'</TEXTAREA>';
        } else if ($type == 'date') {
            if ($value == '0000-00-00') {  // equivalent of null in mysql
                $value = '';
            }
                return '<INPUT class="inputboxclass jq_datepicker" TYPE="text" NAME="'.$fieldname.'" VALUE="'.(($value && (strtotime($value) != -1)) ? date('m/d/Y', strtotime($value)) : $value).'" SIZE="12" MAXLENGTH="20"'.$attributes.'>';
        } else if ($type == 'datetime') {
            if ($value == '0000-00-00 00:00:00') {  // equivalent of null in mysql
                $value = '';
            }
                return '<INPUT class="inputboxclass jq_datetimepicker" TYPE="text" NAME="'.$fieldname.'" VALUE="'.(($value && (strtotime($value) != -1)) ? date('m/d/Y H:i', strtotime($value)) : $value).'" SIZE="20" MAXLENGTH="24"'.$attributes.'>';
        } else {
            if (in_array($type, array('float','calculated'))) {
                $length = DEFAULT_FLOAT_WIDTH;
            } else {
                $length = isset($fieldtype['len']) ? $fieldtype['len'] : '';
            }
            $classes = array('inputboxclass');
            if ($type=='calculated') {
                $classes[] = 'calculated';
            }
            $readonly = ($type=='calculated') ? ' readonly' : '';
            $maxsize = MAX_INPUT_TAG_WIDTH;
            $size = ($length > $maxsize) ? $maxsize : $length;
            $override_size = isset($fieldtype['input_cols']) ? $fieldtype['input_cols'] : $size;
            $on_change = isset($fieldtype['onchange_js']) ? ' OnChange="'.$fieldtype['onchange_js'].'"' : '';
            $on_click = isset($fieldtype['onclick_js']) ? ' OnClick="'.$fieldtype['onclick_js'].'"' : '';
            return '<INPUT ID="'.$fieldname.'" class="'.implode(' ', $classes).'" TYPE="text" NAME="'.$fieldname.'" VALUE="'.TextToHtml($value).'" SIZE="'.$override_size.'" MAXLENGTH="'.$length.'"'.$on_change.$on_click.$attributes.$readonly.'>';
        }
    }

    /*
         * TODO: some of these types should be moved into DBTableRow (left_join ?)
     */
    public function formatPrintField($fieldname, $is_html = true)
    {
        $fieldtype = $this->getFieldType($fieldname);
        $value = $this->$fieldname;
        $type = isset($fieldtype['type']) ? $fieldtype['type'] : '';
        if ($type == 'print_function') {
            $print_function_name = $fieldtype['print_function_name'];
            return $print_function_name($value, $is_html);
        } elseif (in_array($type, array('enum','left_join','sort_order'))) { // this is a multiple choice thing
            switch ($type) {
                case 'enum':
                    $value_array = parseSelectValues($fieldname, $this);
                    break;
                case 'left_join':
                    $value_array = parseJoinValues($fieldname, $this);
                    break;
                case 'sort_order':
                    $value_array = getSortOrderArray($fieldname, $this);
                    break;
            }
            $outval = isset($value_array[$value]) ? $value_array[$value] : null;
            return $is_html ? TextToHtml($outval) : $outval;
        } elseif ($type=='multiple') { // this is a multiple select (more than one at a time)
            $select_values = parseSelectValues($fieldname, $this);
            $value_arr = explode('|', $value);
            $out = array();
            foreach ($value_arr as $key) {
                $out[] = $is_html ? TextToHtml($select_values[$key]) : $select_values[$key];
            }
            return $is_html ? implode('<br>', $out) : implode("\r\n", $out);
        } else if ($type == 'boolean') { // a boolean
            return is_null($value) || ($value==='') ? '' : ($value ? 'Yes' : 'No');
        } else if ($type == 'datetime') {
            return ($value && (strtotime($value) != -1)) ? date('m/d/Y G:i', strtotime($value)) : $value;
        } else if ($type == 'date') {
            return ($value && (strtotime($value) != -1)) ? date('m/d/Y', strtotime($value)) : $value;
        } else {
            return $is_html ? EventStream::embeddedLinksToHtmlTags(nl2br(TextToHtml($value))) : $value;
        }
    }

    /**
     * Get all the fieldnames used as parameters in calculated fields without dups.
     *
     * @return array of fieldnames
     */
    public function getCalculatedParamFieldNames()
    {
        $merge_params = array();
        foreach ($this->getCalculatedFieldTypes() as $fieldname => $fieldtype) {
            $merge_params = array_merge($merge_params, $this->extractParamsFromExpression($fieldtype['expression']));
        }
        return array_keys($merge_params);
    }

    public function getCalculatedFieldTypes()
    {
        $out = array();
        foreach ($this->getFieldTypes() as $fieldname => $fieldtype) {
            if (isset($fieldtype['type']) && ($fieldtype['type'] == 'calculated')) {
                $out[$fieldname] = $fieldtype;
            }
        }
        return $out;
    }

    /**
     * Calculate the values of all the calculated fields.  Store them in $this but also return an array
     *
     * @return array of calculated field values by fieldname.
     */
    public function processCalculatedFields()
    {
        $assigned_values = array();  // in case the caller wants to know what values were assigned to what
        $ignored_errors = array();
        foreach ($this->getCalculatedFieldTypes() as $fieldname => $fieldtype) {
            $assigned_values[$fieldname] = $this->evaluateCalulatedField($fieldname, $ignored_errors);
            $this->{$fieldname} = $assigned_values[$fieldname];
        }
        return $assigned_values;
    }

    /**
     * Try to return the value of this fieldname as a number.  Works for boolean and enums too.
     *
     * @param string $fieldname
     *
     * @return mixed numerical value of the field or null if it doesn't make sense as a number
     */
    public function getFieldValueAsNumber($fieldname)
    {
        $value = $this->{$fieldname};
        if (($value!=='') && !is_null($value) && in_array($this->_fieldtypes[$fieldname]['type'], array('date','datetime'))) {
            $value = strtotime($value);
        }
        return is_numeric($value) ? (float)$value : null;
    }

    /**
     * search the given math expression for instances of "[fieldname]" and return
     * a list of array('fieldname' => '[fieldname]')
     *
     * @param string $expression
     *
     * @return array of bracketed names as a function of fieldnames.
     */
    public static function extractParamsFromExpression($expression)
    {
        $return = array();
        $match = preg_match_all('/\[([a-z_0-9]+)]/i', $expression, $out);
        for ($i=0; $i < count($out[0]); $i++) {
            $return[$out[1][$i]] = $out[0][$i];
        }
        return $return;
    }

    /**
     * evaluates and returns the calculated expression.  It also adds to the errormsg list if there is a problem.
     * Use this to both validate and to get the value for assignment.
     */
    public function evaluateCalulatedField($fieldname, &$errormsg)
    {
        require_once(dirname(__FILE__).'/../library/EvalMath/EvalMath.php');
        require_once(dirname(__FILE__).'/../library/EvalMath/Stack.php');
        $param_fields = array(); // these are field that appear in the expression.
        $result = null;
        $m = new EvalMath();
        $m->suppress_errors = true;
        $fieldtype = $this->_fieldtypes[$fieldname];
        $expression = $this->_fieldtypes[$fieldname]['expression'];
        $match = preg_match_all('/\[([a-z_0-9]+)]/i', $expression, $out);
        $good = true;
        foreach (self::extractParamsFromExpression($expression) as $param_fieldname => $param_inc_bracket) {
            $param_varname = 'x'.$param_fieldname;
            $param_value = $this->getFieldValueAsNumber($param_fieldname);
            if (!is_null($param_value)) {
                $m->evaluate("{$param_varname} = {$param_value}");
            } else {
                $good = false;
                if ($this->isRequired($fieldname)) {
                    $errormsg[$fieldname] = 'The expression for '.$this->formatFieldnameNoColon($fieldname).' cannot be evaluated because the parameter "'.$param_fieldname.'" is not numeric.';
                }
            }
            $expression = str_ireplace($param_inc_bracket, $param_varname, $expression);
        }
        if ($good) {
            $eval = $m->evaluate($expression);
            if ($eval===false) {
                $errormsg[$fieldname] = 'There is something wrong with the expression ('.$this->_fieldtypes[$fieldname]['expression'].') for '.$this->formatFieldnameNoColon($fieldname).': '.$m->last_error;
            } elseif (is_nan($eval) || !is_numeric($eval)) {
                $errormsg[$fieldname] = 'The expression ('.$this->_fieldtypes[$fieldname]['expression'].') for '.$this->formatFieldnameNoColon($fieldname).' does not evaluate to a number.';
            } else {
                $result = $eval;
            }
        }
        return $result;
    }


}


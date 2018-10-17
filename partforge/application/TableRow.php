<?php
/**
 *
 * PartForge Enterprise Groupware for recording parts and assemblies by serial number and version along with associated test data and comments.
 *
 * Copyright (C) 2013-2018 Randall C. Black <randy@blacksdesign.com>
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
            len			  used in combinatino with varchar and others for length
            input_rows
            required		means this field is required.
            input_cols
            print_width
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
        
        
        public function __construct($fieldtypes=null)
        {
            $this->_fieldtypes = array();
            $this->_fields = array();
            if ($fieldtypes!=null) {
                $this->setFieldTypes($fieldtypes);
            }
        }
        
        public function verifyFieldsExist($list_of_fields) {
            if (!is_array($list_of_fields)) $list_of_fields = array($list_of_fields);
            foreach($list_of_fields as $field) {
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
                foreach($spec as $fld => $val) {
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
        public function assignFromFormSubmission($in_params,&$merge_params) {
            foreach($this->getFieldTypes() as $fieldname => $fieldtype) {
                if (isset($in_params[$fieldname])) {
                    if (($fieldtype['type']=='multiple') && is_array($in_params[$fieldname])) {
                        $out = array();
                        foreach($in_params[$fieldname] as $select_value => $is_set) {
                            if ($is_set) $out[] = $select_value;
                        }
                        $merge_params[$fieldname] = implode('|',$out);
                    } else {
                        $merge_params[$fieldname] = $in_params[$fieldname];
                    }
                }
            }
            $this->assign($merge_params);
            return true;
        }
        
        /*
         	Like assignFromFormSubmission() except it doesn't merge parameters into anything, it
         	just assigns only those variables that are defined by the existing types.
         */
        public function assignFromAjaxPost($in_params) {
        	$merge_params = array();
        	return self::assignFromFormSubmission($in_params,$merge_params);
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
        
        public function setFieldTypes($fieldtypes) {
            $this->_fieldtypes = array_merge($this->_fieldtypes,$fieldtypes);
        }
        
        public function setFieldType($fieldname,$fieldtype) {
            $this->_fieldtypes[$fieldname] = $fieldtype;
        }
        
        // e.g., setFieldAttribute('comments','input_rows',18)
        public function setFieldAttribute($fieldname,$attribute,$value) {
            $this->_fieldtypes[$fieldname][$attribute] = $value;
        }
        
        public function getFieldAttribute($fieldname,$attribute) {
            return $this->_fieldtypes[$fieldname][$attribute];
        }
        
        public function setIsRequired($fields,$is_required) {
            if (!is_array($fields)) $fields = array($fields);
            foreach($fields as $field) {
                $this->_fieldtypes[$field]['required'] = $is_required;
            }
        }
        
        public function isRequired($field) {
            return $this->_fieldtypes[$field]['required'];
        }
        
        public function getFieldNames()
        {
            return array_keys($this->_fieldtypes);
        }
        
        // default fields for saving the whole record
        public function getSaveFieldNames() {
            return array_keys($this->_fieldtypes);
        }
        
        // default fields for editing (usually excludes the primary index)
        public function getEditFieldNames() {
            return array_keys($this->_fieldtypes);
        }
        
        public function getFieldType($fieldname) {
            return $this->_fieldtypes[$fieldname];
        }
        
        public function getFieldTypeSize($fieldname) {
            return $this->_fieldtypes[$fieldname]['len'];
        }
        
        public function setFieldTypeParams($fieldname,$datatype,$length='',$is_required,$caption='',$subcaption='') {
            // $datatype = varchar, or whatever
            $this->_fieldtypes[$fieldname] = array('type' => $datatype);
            $this->setIsRequired($fieldname,$is_required);
            if ($length != '') {
                $this->_fieldtypes[$field]['len'] = $length;
            }
            if ($caption != '') {
                $this->setFieldCaption($fieldname,$caption,$subcaption);
            }
        }
        
        public function getFieldNamesWithoutStrictValidation() {
        	return array();
        }

        public function validateFields($fieldnames,&$errormsg)
        {
        	$this->verifyFieldsExist($fieldnames);
        	$EMAIL_REGEX = '^[a-zA-Z0-9_.-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9.-]+$';
        	$DATE_REGEX = '^[0-9]{1,2}/[0-9]{1,2}/[0-9]{4}$';
        	foreach ($fieldnames as $fieldname) {
        		if (str_contains($fieldname,'email')) {
        			if ($this->isRequired($fieldname) || trim($this->_fields[$fieldname])!='') {
        				if (!preg_match('"'.$EMAIL_REGEX.'"i',trim($this->_fields[$fieldname]))) {
        					$errormsg[$fieldname] = 'Please make sure '.$this->formatFieldnameNoColon($fieldname).' is entered correctly.';
        				}
        			}
        		} elseif (preg_match('/_url$/i',$fieldname)) {
        			if ($this->isRequired($fieldname) || trim($this->_fields[$fieldname])!='') {
        				if (!preg_match('"^(http://|https://)"i',trim($this->_fields[$fieldname]))) {
        					$errormsg[$fieldname] = 'Please make sure '.$this->formatFieldnameNoColon($fieldname).' starts with http:// or https://.';
        				}
        			}
        		} elseif (($this->_fieldtypes[$fieldname]['type'] == 'datetime') || ($this->_fieldtypes[$fieldname]['type'] == 'date') && str_contains($fieldname,'date')) {
        			if ($this->isRequired($fieldname) || trim($this->_fields[$fieldname])!='') {
        				if (!is_valid_datetime($this->_fields[$fieldname])) {
        					$errormsg[$fieldname] = 'Please make sure '.$this->formatFieldnameNoColon($fieldname).' is a valid date.';
        				}
        			}
        		} else {
        			$regularized_value = DBTableRowItemVersion::varToStandardForm($this->_fields[$fieldname], $this->_fieldtypes[$fieldname]);
        			if ($this->isRequired($fieldname) &&  (is_null($regularized_value) || ($regularized_value===''))) {
        				$errormsg[$fieldname] = 'Please enter '.$this->formatFieldnameNoColon($fieldname).'.';
        			} elseif (trim($this->_fields[$fieldname])!='' && str_contains($this->_fieldtypes[$fieldname]['type'],'float') && !is_numeric($this->_fields[$fieldname])) {
        				$errormsg[$fieldname] = $this->formatFieldnameNoColon($fieldname).' must be numeric.';
        			}
        		}
        	}
        }

        public static function composeSubcaptionWithValidation($subcaption, $min, $max, $units, $html=true) {
        	$min = trim($min);
        	$max = trim($max);
        	$units = trim($units);
        	$out = array();
        	if (is_numeric($min) && is_numeric($max)) {
        		if ($min==$max) {
        			$out[] = 'exactly '.$min;
        		} else {
	        		$out[] = $min.' to '.$max;
        		}
        	} else if (is_numeric($min)) {
        		$out[] = ($html ? '&gt;' : '>').' '.$min;
        	} else if (is_numeric($max)) {
        		$out[] = ($html ? '&lt;' : '<').' '.$max;
        	}
        	if ($units) $out[] = $units;
        	$synthcap = count($out) > 0 ? implode(' ',$out) : '';
        	return strlen($synthcap)==0 ? $subcaption : (($subcaption=='') ? $synthcap : '['.$synthcap.']'.($html ? '<br />' : "\n").$subcaption);
        }

        public function formatFieldname($string,$marker='') {
            $out = $this->_fieldtypes[$string]['caption'].$marker.':';
            $subcaption = '';
            if (isset($this->_fieldtypes[$string]['subcaption']) && ($this->_fieldtypes[$string]['subcaption']!='')) {
            	$subcaption = $this->_fieldtypes[$string]['subcaption'];
            }
            if ($this->_fieldtypes[$string]['type']=='float') {
	            $subcaption = self::composeSubcaptionWithValidation($subcaption, $this->_fieldtypes[$string]['minimum'], $this->_fieldtypes[$string]['maximum'], $this->_fieldtypes[$string]['units']);
            }
            if ($subcaption) {
                $out .= '<br><span class="paren">'.$subcaption.'</span>';
            }
            return $out;
        }
        
        public function formatFieldSimple($fieldname) {
                $fieldname = str_replace('_', ' ', $fieldname);
                $fieldname = ucwords(trim($fieldname));
                return $fieldname;
        }
        
        public function formatFieldnameNoColon($string) {
            return $this->_fieldtypes[$string]['caption'];
        }
        

        public function setFieldCaption($fieldname,$caption,$subcaption='') {
            $this->_fieldtypes[$fieldname]['caption'] = $caption;
            if ($subcaption) {
                $this->_fieldtypes[$fieldname]['subcaption'] = $subcaption;
            }
        }
        
        public function setFieldTagDisabled($fields) {
            if (!is_array($fields)) {
                $fields = array($fields);
            }
            $this->verifyFieldsExist($fields);
            foreach($fields as $field) {
                $this->setFieldAttribute($field,'disabled',true);
            }
        }
        
        public function delimitedFieldDump($fields=array()) {
            $delimiter = ',';
            $replace_delim = '[COMMA]';
            if (count($fields)==0) {
                $fields=$this->getFieldNames();
            }
            $line = array();
            foreach($fields as $field) {
                    $line[] = str_replace($delimiter,$replace_delim,$this->formatPrintField($field, false, true));
            }
            return implode($delimiter,$line);
        }
        
		/*
		 * TODO: some of these types should be moved into DBTableRow (left_join ?)
		 */
        public function formatInputTag($fieldname, $display_options=array()) {
			$fieldtype = $this->getFieldType($fieldname);
			$value = $this->$fieldname;
			$attributes = isset($fieldtype['disabled']) && $fieldtype['disabled'] ? ' disabled' : '';
			if (in_array($fieldtype['type'],array('enum','left_join','sort_order'))) { // a dropdown box
		
				switch($fieldtype['type']) {
					case 'enum':        $select_values = parseSelectValues($fieldname, $this); break;
					case 'left_join':   $select_values = parseJoinValues($fieldname, $this); break;
					case 'sort_order':  $select_values = getSortOrderArray($fieldname, $this); break;
				}
		//            $select_values = 'enum'==$fieldtype['type'] ? parseSelectValues($fieldname, $this) : parseJoinValues($fieldname, $this);
		
				if (in_array('UseRadiosForMultiSelect',$display_options)) {
					return format_radio_tags($select_values,$fieldname,$this->getArray(),$fieldtype['onclick_js'],$attributes);
				} else {
					return format_select_tag($select_values,$fieldname,$this->getArray(),$fieldtype['onchange_js']);
				}
		    } elseif (in_array($fieldtype['type'],array('multiple'))) { // a multiple check box thing
				$select_values = parseSelectValues($fieldname, $this);
				$html = '';
				$value_arr = explode('|',$value);
				foreach($select_values as $key => $select_value) {
					$checked = in_array($key,$value_arr) ? ' checked' : '';
					$html .= '<INPUT TYPE="hidden" NAME="'.$fieldname.'['.$key.']" VALUE="0"'.$attributes.'>
								  <INPUT class="checkboxclass" TYPE="checkbox" NAME="'.$fieldname.'['.$key.']" VALUE="1" ID="'.$idname.'"'.$checked.$attributes.'>&nbsp;'.$select_value.'<br>
								  ';
				}
				return $html;
			} elseif ($fieldtype['type'] == 'boolean') { // a boolean, so lets use a checkbox
				
			    if (in_array('UseCheckForBoolean',$display_options)) {
			    	return checkbox_html($fieldname,$value,$attributes);
			    } else {
					$select_values = array("1" => 'Yes', "0" => 'No');
					$select_value_arr = array($fieldname => (is_null($value) || ($value==='') ? $value : ($value ? "1" : "0")));
					return '<INPUT TYPE="hidden" NAME="'.$fieldname.'" VALUE="">'.format_radio_tags($select_values,$fieldname,$select_value_arr,$fieldtype['onclick_js'],$attributes);
			    }
			} else if (in_array($fieldtype['type'],array('text'))) { // a text field
				$rows = isset($fieldtype['input_rows']) ? $fieldtype['input_rows'] : '3';
				$cols = isset($fieldtype['input_cols']) ? $fieldtype['input_cols'] : '40';
				return '<TEXTAREA class="inputboxclass" NAME="'.$fieldname.'" ROWS="'.$rows.'" COLS="'.$cols.'"'.$attributes.'>'.TextToHtml($value).'</TEXTAREA>';
			} else if ($fieldtype['type'] == 'date') {
		            if ($value == '0000-00-00') {  // equivalent of null in mysql
		                $value = '';
		            }
					return '<INPUT class="inputboxclass jq_datepicker" TYPE="text" NAME="'.$fieldname.'" VALUE="'.(($value && (strtotime($value) != -1)) ? date('m/d/Y',strtotime($value)) : $value).'" SIZE="12" MAXLENGTH="20"'.$attributes.'>';
		
			} else if ($fieldtype['type'] == 'datetime') {
		            if ($value == '0000-00-00 00:00:00') {  // equivalent of null in mysql
		                $value = '';
		            }
					return '<INPUT class="inputboxclass jq_datetimepicker" TYPE="text" NAME="'.$fieldname.'" VALUE="'.(($value && (strtotime($value) != -1)) ? date('m/d/Y H:i',strtotime($value)) : $value).'" SIZE="20" MAXLENGTH="24"'.$attributes.'>';
		
			} else {
				if ($fieldtype['type']=='float') {
					$length = DEFAULT_FLOAT_WIDTH;
				} else {
					$length = $fieldtype['len'];
				}
				$maxsize = MAX_INPUT_TAG_WIDTH;
				$size = ($length > $maxsize) ? $maxsize : $length;
				$override_size = isset($fieldtype['input_cols']) ? $fieldtype['input_cols'] : $size;
				$on_change = isset($fieldtype['onchange_js']) ? ' OnChange="'.$fieldtype['onchange_js'].'"' : '';
				$on_click = isset($fieldtype['onclick_js']) ? ' OnClick="'.$fieldtype['onclick_js'].'"' : '';
				return '<INPUT ID="'.$fieldname.'" class="inputboxclass" TYPE="text" NAME="'.$fieldname.'" VALUE="'.TextToHtml($value).'" SIZE="'.$override_size.'" MAXLENGTH="'.$length.'"'.$on_change.$on_click.$attributes.'>';
			}
		}
        
		/*
		 * TODO: some of these types should be moved into DBTableRow (left_join ?)
		 */
		public function formatPrintField($fieldname, $is_html=true,$nowrap=false) {
			$fieldtype = $this->getFieldType($fieldname);
			$value = $this->$fieldname;
			if ($fieldtype['type'] == 'print_function') {
				$print_function_name = $fieldtype['print_function_name'];
				return $print_function_name($value,$is_html);
			} elseif (in_array($fieldtype['type'],array('enum','left_join','sort_order'))) { // this is a multiple choice thing
				switch($fieldtype['type']) {
					case 'enum':        $value_array = parseSelectValues($fieldname, $this); break;
					case 'left_join':   $value_array = parseJoinValues($fieldname, $this); break;
					case 'sort_order':  $value_array = getSortOrderArray($fieldname, $this); break;
				}
				return $is_html ? TextToHtml($value_array[$value]) : $value_array[$value];
			} elseif ($fieldtype['type']=='multiple') { // this is a multiple select (more than one at a time)
				$select_values = parseSelectValues($fieldname, $this);
				$value_arr = explode('|',$value);
				$out = array();
				foreach($value_arr as $key) {
					$out[] = $is_html ? TextToHtml($select_values[$key]) : $select_values[$key];
				}
				return $is_html ? implode('<br>',$out) : implode("\r\n",$out);
			} else if ($fieldtype['type'] == 'boolean') { // a boolean
				return is_null($value) ? '' : ($value ? 'Yes' : 'No');
			} else if ($fieldtype['type'] == 'datetime') {
				return ($value && (strtotime($value) != -1)) ? date('m/d/Y G:i',strtotime($value)) : $value;
			} else if ($fieldtype['type'] == 'date') {
				return ($value && (strtotime($value) != -1)) ? date('m/d/Y',strtotime($value)) : $value;
			} else {
				$width = isset($fieldtype['print_width']) ? $fieldtype['print_width'] : DEFAULT_FIELD_PRINT_WIDTH;
				if ($is_html) {
					return $nowrap ? nbsp_ifblank(TextToHtml($value)) : text_to_wrappedhtml($value,$width);
				} else {
					return $nowrap ? $value : wrapemailtext( $value, $width, "\r\n");
				}
			}
		}


    }


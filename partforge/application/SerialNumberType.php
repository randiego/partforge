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

/**
 * This class and its subclasses manage the way different types of serial numbers are processed and displayed.
 * For example, if serial numbers look like ABC123, these classes take care of things like figuring out the
 * number part and what the next serial number should be if we request that.  It also handles edit checking.
 * Instantial using the factory typeFactory() method with the array of serial number fields stored in the
 * typeversion record. 
 * @author randy
 *
 */
abstract class SerialNumberType {
	 
	protected $_format_array = array();
	
	public function __construct($sn_format_array)
	{
		$this->_format_array = $sn_format_array;
	}
	
	static function typeFactory($sn_format_array) {
		$test = $sn_format_array['serial_number_type'];
		switch (true) {
			case $test===null:
				return new NoSerialNumber($sn_format_array);
			case $test==0:
				return new SerialNumberWithSimpleFormatChecking($sn_format_array);
			case $test==1:
				return new SerialNumberWithRegexPrePostFix($sn_format_array);
			case $test==2:
				return new SerialNumberWithDatePrefixA($sn_format_array);
			case $test==3:
				return new SerialNumberWithSimplePrefix($sn_format_array);
		}	
	}
	
	/**
	 * Does this serial number support the "Get Next" type operation.
	 * @return boolean
	 */
	abstract public function supportsGetNextNumber();
	
	/**
	 * Searches for the largest serial number value in the database for this typeversion_id and then adds 1 and formats it
	 * according to the rules.
	 * @param integer $typeversion_id
	 * @return string formatted serial number
	 */
	abstract public function getNextSerialNumber($typeversion_id);
	 
	/**
	 * Takes the serial number and sees if it it formatted correctly and generates an appropriate error message if there is a problem.
	 * @param string $item_serial_number
	 * @param array $errormsg
	 */
	abstract public function validateEnteredSerialNumber($item_serial_number, &$errormsg);
	
	/**
	 * Checks for self-consistency among the parameters in $this->_format_array.  Does the part definition have valid parameters.
	 * @param array $errormsg
	 */
	public function validateSerialNumberType(&$errormsg) {}	
	
	/**
	 * Takes the serial number and extract the number part out and returns it.  If it cannot be extracted out for some reason, it returns null
	 * @param string $item_serial_number
	 * @return Ambigous <NULL, unknown>|NULL
	 */
	abstract public function convertSerialNumberToOrdinal($item_serial_number);
	
	/**
	 * This returns the the captions and subcaptions that are appropriate for the fields that describe the serial numbers.
	 * This is used for the purpose of displaying prompts for entering the values. By default they are all hidden used==false
	 */
	public function getParamCaptions() {
		$out = array();
		$out['serial_number_format'] = array(
				'used' => false, 
				'caption' => 'Serial Number Print Format', 
				'subcaption' => linkify('http://us2.php.net/sprintf','sprintf','PHP manual','','','_blank').' format for taking the raw number part of a serial number and formatting it into a serial number string. Example: ABC%03d');
		$out['serial_number_check_regex'] = array(
				'used' => false,
				'caption' => 'Serial Number Check RegEx',
				'subcaption' => 'If you only want the user to be able to enter a serial number in a specific format, enter a '.linkify('http://us2.php.net/manual/en/function.preg-match.php','regular expression','PHP manual for preg_match()','','','_blank').' like /^ABC([0-9]{3,5})$/ (only accept serial numbers of the form ABC###, ABC####, or ABC####).  If you leave this blank, any serial number is allowed as long as it is unique and not blank.');
		$out['serial_number_parse_regex'] = array(
				'used' => false,
				'caption' => 'Serial Number Parse RegEx',
				'subcaption' => linkify('http://us2.php.net/manual/en/function.preg-match.php','preg_match','PHP manual','','','_blank').' format for extracting the number part of a serial number.  The expression should have a single parenthesized subpattern.  Example: /^ABC([0-9]{3,5})$/');
		$out['serial_number_caption'] = array(
				'used' => false,
				'caption' => 'Serial Number Caption',
				'subcaption' => 'helpful short description of the serial number format which appears in small print near the input box.');
		return $out;
	}
	
	public function getHelperCaption() {
		return $this->_format_array['serial_number_caption'];
	}
}


// type === null
class NoSerialNumber extends SerialNumberType {
	public function supportsGetNextNumber() {
		return false;
	}
	
	public function validateEnteredSerialNumber($item_serial_number, &$errormsg) { }
	
	public function convertSerialNumberToOrdinal($item_serial_number) {
		return null;
	}	
	
	public function getNextSerialNumber($typeversion_id) {
		return '';
	}	
	
}

// type = 0
class SerialNumberWithSimpleFormatChecking extends SerialNumberType {
	public function supportsGetNextNumber() {
		return false;
	}
	
	public function validateEnteredSerialNumber($item_serial_number, &$errormsg) {
		if (!empty($this->_format_array['serial_number_check_regex'])) {
			$matchreturn = preg_match($this->_format_array['serial_number_check_regex'],$item_serial_number);
			if ($matchreturn===false) {
				$errormsg['item_serial_number'] = 'Something wrong with the serial number checker.';
			} else if ($matchreturn===0) {
				$errormsg['item_serial_number'] = 'The serial number is not in the right format.';
			}
		}
	}
	
	public function validateSerialNumberType(&$errormsg) {
		if (!empty($this->_format_array['serial_number_check_regex'])) {
			$captions = $this->getParamCaptions();
			$matchreturn = @preg_match($this->_format_array['serial_number_check_regex'],'Some random text used to exercise the regex');
			if ($matchreturn===false) {
				$errormsg[] = $captions['serial_number_check_regex']['caption'].' it not a valid RegEx expression.';
			}
		}
	}	
	
	public function convertSerialNumberToOrdinal($item_serial_number) {
		return null;
	}	
	
	public function getNextSerialNumber($typeversion_id) {
		return '';
	}	

	public function getParamCaptions() {
		$out = parent::getParamCaptions();
		$out['serial_number_check_regex']['used'] = true;
		$out['serial_number_caption']['used'] = true;
		return $out;
	}	
	
}


// type = 1
class SerialNumberWithRegexPrePostFix extends SerialNumberType {
	public function supportsGetNextNumber() {
		return !empty($this->_format_array['serial_number_parse_regex']) 
				&& !empty($this->_format_array['serial_number_format']);
	}
	
	public function validateEnteredSerialNumber($item_serial_number, &$errormsg) {
		if (!empty($this->_format_array['serial_number_check_regex'])) {
			$matchreturn = preg_match($this->_format_array['serial_number_check_regex'],$item_serial_number);
			if ($matchreturn===false) {
				$errormsg['item_serial_number'] = 'Something wrong with the serial number checker.';
			} else if ($matchreturn===0) {
				$errormsg['item_serial_number'] = 'The serial number is not in the right format.';
			}
		}
	}	
	
	public function validateSerialNumberType(&$errormsg) {
		$captions = $this->getParamCaptions();

		$matchreturn = @preg_match($this->_format_array['serial_number_check_regex'],'Some random text used to exercise the regex');
		if ($matchreturn===false) {
			$errormsg[] = $captions['serial_number_check_regex']['caption'].' it not a valid RegEx expression.';
		}

		$matchreturn = @preg_match($this->_format_array['serial_number_parse_regex'],'Some random text used to exercise the regex');
		if ($matchreturn===false) {
			$errormsg[] = $captions['serial_number_parse_regex']['caption'].' it not a valid RegEx expression.';
		}
		
		// I try to use the format specifier to output serial number of 314.  I test to make sure that the result has the string "314" in it.
		$testprint = @sprintf($this->_format_array['serial_number_format'],314);
		if (!str_contains($testprint, '314')) {
			$errormsg[] = $captions['serial_number_format']['caption'].' it not a valid format specifier.';
		}
		
	}	
	
	public function convertSerialNumberToOrdinal($item_serial_number) {
		if (!empty($this->_format_array['serial_number_parse_regex'])) {
			preg_match($this->_format_array['serial_number_parse_regex'],$item_serial_number,$out);
			return isset($out[1]) ? $out[1] : null;
		}
		return null;
	}	
	
	public function getNextSerialNumber($typeversion_id) {
		$records = DbSchema::getInstance()->getRecords('',"
				SELECT max(other_iv.cached_serial_number_value) as max_serial_number
				FROM itemversion as other_iv
				INNER JOIN typeversion as other_tv ON other_iv.typeversion_id = other_tv.typeversion_id
				INNER JOIN itemobject as other_io ON other_io.cached_current_itemversion_id=other_iv.itemversion_id
				WHERE (other_tv.typeobject_id=(SELECT tv.typeobject_id FROM typeversion AS tv WHERE tv.typeversion_id='{$typeversion_id}' LIMIT 1) )");
		$record = reset($records);
		$next_value = empty($record['max_serial_number']) ? 1 : $record['max_serial_number'] + 1;
		if (!empty($this->_format_array['serial_number_format'])) {
			$out = sprintf($this->_format_array['serial_number_format'],$next_value);
		} else {
			$out = sprintf("%05d",$next_value);
		}
		return $out;
	}
	
	public function getParamCaptions() {
		$out = parent::getParamCaptions();
		$out['serial_number_format']['used'] = true;
		$out['serial_number_check_regex']['used'] = true;
		$out['serial_number_parse_regex']['used'] = true;
		$out['serial_number_caption']['used'] = true;
		return $out;
	}	
	
}

// type = 2
class SerialNumberWithDatePrefixA extends SerialNumberType {
	public function supportsGetNextNumber() {
		return true;
	}
	
	public function validateEnteredSerialNumber($item_serial_number, &$errormsg) {
		$matchreturn = preg_match('/^[0-9]{2}-[0-9]{2}-[0-9]{2}-([0-9]{1,5})$/',$item_serial_number);
		if ($matchreturn===false) {
			$errormsg['item_serial_number'] = 'Something wrong with the serial number checker.';
		} else if ($matchreturn===0) {
			$errormsg['item_serial_number'] = 'The serial number is not in the format MM-DD-YY-##.';
		}
	}	
	
	public function convertSerialNumberToOrdinal($item_serial_number) {
		preg_match('/^[0-9]{2}-[0-9]{2}-[0-9]{2}-([0-9]{1,5})$/',$item_serial_number,$out);
		return isset($out[1]) ? $out[1] : null;
	}	
	
	public function getNextSerialNumber($typeversion_id) {
		$date_prefix = date('m-d-y-',script_time());
		// select highest cached number where the date part of the serial number is the same
		$records = DbSchema::getInstance()->getRecords('',"
				SELECT max(other_iv.cached_serial_number_value) as max_serial_number
				FROM itemversion as other_iv
				INNER JOIN typeversion as other_tv ON other_iv.typeversion_id = other_tv.typeversion_id
				INNER JOIN itemobject as other_io ON other_io.cached_current_itemversion_id=other_iv.itemversion_id
				WHERE (other_tv.typeobject_id=(SELECT tv.typeobject_id FROM typeversion AS tv WHERE tv.typeversion_id='{$typeversion_id}' LIMIT 1) ) and (other_iv.item_serial_number LIKE '{$date_prefix}%')");
		$record = reset($records);
		$next_value = empty($record['max_serial_number']) ? 1 : $record['max_serial_number'] + 1;
		return $date_prefix.sprintf('%02d',$next_value);
	}
	
	public function getParamCaptions() {
		$out = parent::getParamCaptions();
		$out['serial_number_caption']['used'] = true;
		$out['serial_number_caption']['subcaption'] = 'Optional: any extra explanation for the user about the serial number, other than the formatting (which is already shown to them).';
		return $out;
	}	
	
	public function getHelperCaption() {
		$entered_caption = parent::getHelperCaption();
		return !empty($entered_caption) ? '[MM-DD-YY-##]<br />'.$entered_caption : 'MM-DD-YY-##';
	}
	
	
}

// type = 3
class SerialNumberWithSimplePrefix extends SerialNumberType {
	public function supportsGetNextNumber() {
		return true;
	}
	
	protected function convertToExpressions() {
		preg_match('/^([^\#]*)(\#+)$/',$this->_format_array['serial_number_format'],$out);
		$prefix = $out[1];
		$digits = strlen($out[2]);
		$format = $prefix."%0{$digits}d";
		$check = '/^'.preg_quote($prefix).'([0-9]{'.$digits.',10})$/';
		$parse = '/^'.preg_quote($prefix).'([0-9]+)$/';
		return array($format, $check, $parse);
	}
	
	public function validateEnteredSerialNumber($item_serial_number, &$errormsg) {
		list($format, $check, $parse) = $this->convertToExpressions();
		if (!empty($check)) {
			$matchreturn = preg_match($check,$item_serial_number);
			if ($matchreturn===false) {
				$errormsg['item_serial_number'] = 'Something wrong with the serial number checker.';
			} else if ($matchreturn===0) {
				$errormsg['item_serial_number'] = 'The serial number is not in the format '.$this->_format_array['serial_number_format'].'.';
			}
		}
	}
	

	public function validateSerialNumberType(&$errormsg) {
		$matchreturn = preg_match('/^([^\#]*)(\#+)$/',$this->_format_array['serial_number_format']);
		if ($matchreturn===false) {
			$errormsg[] = 'Something wrong with the format checker.';
		} else if ($matchreturn===0) {
			$errormsg[] = 'The entered serial number format must be in the form ABC###.';
		}
	}

	public function convertSerialNumberToOrdinal($item_serial_number) {
		list($format, $check, $parse) = $this->convertToExpressions();
		if (!empty($parse)) {
			preg_match($parse,$item_serial_number,$out);
			return isset($out[1]) ? $out[1] : null;
		}
		return null;
	}

	public function getNextSerialNumber($typeversion_id) {
		list($format, $check, $parse) = $this->convertToExpressions();
		$records = DbSchema::getInstance()->getRecords('',"
				SELECT max(other_iv.cached_serial_number_value) as max_serial_number
				FROM itemversion as other_iv
				INNER JOIN typeversion as other_tv ON other_iv.typeversion_id = other_tv.typeversion_id
				INNER JOIN itemobject as other_io ON other_io.cached_current_itemversion_id=other_iv.itemversion_id
				WHERE (other_tv.typeobject_id=(SELECT tv.typeobject_id FROM typeversion AS tv WHERE tv.typeversion_id='{$typeversion_id}' LIMIT 1) )");
		$record = reset($records);
		$next_value = empty($record['max_serial_number']) ? 1 : $record['max_serial_number'] + 1;
		if (!empty($format)) {
			$out = sprintf($format,$next_value);
		} else {
			$out = sprintf("%05d",$next_value);
		}
		return $out;
	}
	
	public function getParamCaptions() {
		$out = parent::getParamCaptions();
		$out['serial_number_format']['used'] = true;
		$out['serial_number_format']['caption'] = 'Format';
		$out['serial_number_format']['subcaption'] = 'for example, ABC### means the serial numbers will be of the form ABC012.';
		$out['serial_number_caption']['used'] = true;
		$out['serial_number_caption']['subcaption'] = 'Optional: any extra explanation for the user about the serial number, other than the formatting (which is already shown to them).';
		return $out;
	}	

	public function getHelperCaption() {
		$entered_caption = parent::getHelperCaption();
		return !empty($entered_caption) ? '['.$this->_format_array['serial_number_format'].']<br />'.$entered_caption : $this->_format_array['serial_number_format'];
	}	

}

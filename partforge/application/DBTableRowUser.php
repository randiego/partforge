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

    class DBTableRowUser extends DBTableRow {
        
        private $_lastErrorMessage;
		private $_crypt_password='';
		private $_role_override = null;
        
        public function __construct($ignore_joins=false,$parent_index=null) {
            parent::__construct('user',$ignore_joins,$parent_index);
            $this->account_created = time_to_mysqldatetime(script_time());
            $this->user_cryptpassword = crypt(Zend_Registry::get('config')->default_new_password);
            $this->_lastErrorMessage = '';
        }
		
		public function getLastCryptPassword() {
			return $this->_crypt_password;
		}
		
		public function getRecordByLoginID($loginid) {
			$DBTableRowQuery = new DBTableRowQuery($this);
			$DBTableRowQuery->setLimitClause('LIMIT 1');
			$DBTableRowQuery->addSelectors(array('login_id' => $loginid));
			return $this->getRecord($DBTableRowQuery->getQuery());
		}

		public function getRecordByEmail($email) {
			$DBTableRowQuery = new DBTableRowQuery($this);
			$DBTableRowQuery->setLimitClause('LIMIT 1');
			$DBTableRowQuery->addSelectors(array('email' => $email));
			return $this->getRecord($DBTableRowQuery->getQuery());
		}
		
		/**
		 * Overridden to handle cleanup after deleting user records
		 * @see DBTableRow::delete()
		 */
		public function delete() {
			$user_id = $this->user_id;
			parent::delete();
			DbSchema::getInstance()->mysqlQuery("delete from userpreferences where user_id='{$user_id}'");
		}
		
		public function ldapAuth($user) {
			return false;
		}
        
        public function login($loginid,$plainpw,$ignore_pw=false,$update_fields=true) { 
            $DBTableRowQuery = new DBTableRowQuery($this);
            $DBTableRowQuery->setLimitClause('LIMIT 1');
			$this->_lastErrorMessage = '';
			$DBTableRowQuery->addSelectors(array('login_id' => $loginid));
			
			
			if ($this->getRecord($DBTableRowQuery->getQuery())) {
				$this->setRoleOverride(null);
				
				// authenticate
				/*
				if (!$ignore_pw) {
					$stored_pw = !empty($this->user_cryptpassword) ? $this->user_cryptpassword :  '';  
					$compare_cryptpw = !empty($plainpw) ? crypt($plainpw,$stored_pw) : $cryptpw;
					if (empty($stored_pw)) {
						$this->_lastErrorMessage = 'Sorry: Invalid Login.';
					} else {
						if (trim($compare_cryptpw) == trim($stored_pw)) { // authenticated
							// not really used, delete _crypt_password some day.
							$this->_crypt_password = $compare_cryptpw;
						} else {
							$this->_lastErrorMessage = 'Sorry: Password was typed incorrectly.';
						}
					}
				}
				*/
				
				if (!$ignore_pw) {
					if (!empty($this->user_cryptpassword)) {
						// there is crypted password in the user table that we will use instead of LDAP/AD servers
						// If the $cryptpw is present we will use if otherwise we use the plainpw field.
						// Eventually, we would like to get rid of the parameter $cryptpw whose only purpose is to allow legacy remembered passwords
						$compare_cryptpw = crypt($plainpw,$this->user_cryptpassword);
						if (trim($compare_cryptpw) != trim($this->user_cryptpassword)) { // authenticated
							$this->_lastErrorMessage = 'Sorry: Password was typed incorrectly.';
						}
					} else {
						
						 //  Try to use an alternate authentication scheme
						 
						if (!$this->ldapAuth(array('username' => $loginid, 'password' => $plainpw))) {
							$this->_lastErrorMessage = 'Sorry: Invalid Login.';
						}
					}
				}
				
				
				// login
				
				if ('' == $this->_lastErrorMessage) {
					// check more stuff...
					if (!$this->user_enabled) {
						$this->_lastErrorMessage = "This account has been deactivated.<br>If you have questions, please contact ".mailto_link(Zend_Registry::get('config')->support_email);
					} else if ($this->waiting_approval) {
						$this->_lastErrorMessage = "This account has not been approved yet.<br>If you have questions, please contact ".mailto_link(Zend_Registry::get('config')->support_email);
						
					} else {
						// update account fields
						if ($update_fields) {
							$this->login_count++;
							$this->last_visit = time_to_mysqldatetime(script_time());
							$this->save(array('login_count','last_visit'));
						}   
					}
				}
			} else {
				$this->_lastErrorMessage = 'Sorry: This login ID not was found.';
			}

			return ('' == $this->_lastErrorMessage);
		}
		
		public function reasonsWhyCantRecieveWatchNotices() {
			$m = array();
			if (!$this->user_enabled) $m[] = 'Account is not enabled.';
			if ($this->waiting_approval) $m[] = 'Account has not been approved yet.';
			if (in_array($this->getRole(),array('Guest','DataTerminal'))) $m[] = 'This user type is not allowed to receive watch notices.';
			if (!$this->email) {
				$m[] = 'Your email address is missing and should be set from the My Account tab.';
			} else {
				$this->validateFields(array('email'), $m);
			}
			return $m;
		}

        public function getLastErrorMessage()
        {
            return $this->_lastErrorMessage;
        }
        
        public function validateFields($fieldnames,&$errormsg)
        {
        	if (in_array('login_id',$fieldnames)) {
        		$LOGINID_REGEX = "/^[a-zA-Z0-9_.@-]{2,64}$/i";
        		if (!preg_match($LOGINID_REGEX,trim($this->_fields['login_id']))) {
        			$errormsg['login_id'] = 'Please make sure the login ID contains between 2 and 64 characters.';
        		}
        		unset($fieldnames[array_search('login_id',$fieldnames)]);
        	}

        	if (in_array('password',$fieldnames)) {
        		$PASSWORD_REGEX = '/^[[:graph:]]{4,32}$/i'; // all printable characters except space
        		if (!preg_match($PASSWORD_REGEX,trim($this->_fields['password']))) {
        			$errormsg['password'] = 'Please make sure the password is between 4 and 32 characters with no spaces.';
        		}
        		unset($fieldnames[array_search('password',$fieldnames)]);
        	}

        	if (in_array('password2',$fieldnames)) { // assumes that password is there too
        		if ($this->_fields['password'] != $this->_fields['password2']) {
        			$errormsg['password2'] = 'Please make sure the two passwords are the same.';
        		}
        		unset($fieldnames[array_search('password2',$fieldnames)]);
        	}

        	if (in_array('email2',$fieldnames)) { // assumes that email is there too
        		if ($this->_fields['email'] != $this->_fields['email2']) {
        			$errormsg['email2'] = 'Please make sure the two email addresses are the same.';
        		}
        		unset($fieldnames[array_search('email2',$fieldnames)]);
        	}

        	if (in_array('currentpassword',$fieldnames)) {
        		if ($this->_fields['user_cryptpassword'] != crypt($this->_fields['currentpassword'],$this->_fields['user_cryptpassword'])) {
        			$errormsg['currentpassword'] = 'Your current password is incorrect.';
        		}
        		unset($fieldnames[array_search('currentpassword',$fieldnames)]);
        	}
        	parent::validateFields($fieldnames,$errormsg);
        }

        public function testPasswordCorrect($pw) {
        	$this->currentpassword = $pw;
        	$errormsg = array();
        	$this->validateFields(array('currentpassword'), $errormsg);
	        return count($errormsg)==0;
        }
        
        public function getEditFieldNames($join_names=null) {
        	$out = parent::getEditFieldNames($join_names);
        	$out[] = 'email2';
        	return $out;
        }
        
        public function save($fieldnames=array(),$handle_err_dups_too=true)
        {
			
			if (in_array('password',$fieldnames)) {
				$this->_fields['user_cryptpassword'] = crypt($this->_fields['password']);  // no salt for creating
				unset($fieldnames[array_search('password',$fieldnames)]);
				if (!in_array('user_cryptpassword',$fieldnames)) $fieldnames[] = 'user_cryptpassword';
			}	

        	if (!$this->isSaved() && !in_array('account_created',$fieldnames)) {  // normally we don't save this.  Exception is when new.
				$fieldnames[] = 'account_created';
			}
			
			
			
			parent::save($fieldnames,$handle_err_dups_too);
        }
        
        static public function getUserPreference($user_id, $key) {
        	$records = DbSchema::getInstance()->getRecords('userpreference_id',"SELECT * FROM userpreferences WHERE user_id='{$user_id}' and pref_key='{$key}'");
        	if (count($records)==1) {
        		$record = reset($records);
        		return $record['pref_value'];
        	} else {
        		return null;
        	}        	
        }
        
        public function getPreference($key) {
            if (($this->getRole()=='nobody') || !is_numeric($this->user_id)) {
                if (isset($_SESSION['nobody_userpreferences'][$key])) {
                    return $_SESSION['nobody_userpreferences'][$key];
                } else {
                    return null;
                }
            } else {
            	return self::getUserPreference($this->user_id, $key);
            }
        }
        
        static public function setUserPreference($user_id,$key,$value) {
        	$Pref = DbSchema::getInstance()->dbTableRowObjectFactory('userpreferences',true);
        	if ($Pref->getRecord("SELECT * FROM userpreferences WHERE user_id='{$user_id}' and pref_key='{$key}'")) {
        		$Pref->pref_value = $value;
        	} else {
        		$Pref->user_id = $user_id;
        		$Pref->pref_key = $key;
        		$Pref->pref_value = $value;
        	}
        	$Pref->save();
        }
        
        public function setPreference($key,$value) {
            if (($this->getRole()=='nobody') || !is_numeric($this->user_id)) {
                $_SESSION['nobody_userpreferences'][$key] = $value;
            } else {
            	self::setUserPreference($this->user_id, $key, $value);
            }
        }
        
        public function keepMyTemporaryPassword() {
        	$this->has_temporary_password = false;
        	$this->save(array('has_temporary_password'));
        }
        
        public function setNumericFavoriteToRollingList($keyname, $value, $favorites_max_length=7) {
        	if (is_numeric($value)) {
        		$favorites = explode('|',$this->getPreference($keyname));
        		if (in_array($value,$favorites)) $favorites = array_diff($favorites,array($value));
        		$newfavorites = array_merge(array($value),$favorites);
        		$newfavorites = array_slice($newfavorites, 0, $favorites_max_length);
        		$this->setPreference($keyname,implode('|',$newfavorites));
        	}      	 
        }
        
        public function getNumericFavorites($keyname) {
        	return explode('|',$this->getPreference($keyname));
        }

        public function fullName($is_html=false)
        {
			$arr = array('first_name' => $this->first_name, 'last_name' => $this->last_name);
            $out = self::concatNames($arr);
            return $is_html ? TextToHtml($out) : $out;
        }
        
        static public function getFullName($user_id, $is_html=false) {
        	$User = new DBTableRowUser();
        	$User->getRecordById($user_id);
        	return $User->fullName($is_html);
        }
        
        public function userTypeText() {
        	$fieldtype = $this->getFieldType('user_type');
        	return $fieldtype['options'][$this->getRole()];
        }
        
        public function defaultLoginUrl($params = array()) {
            switch($this->getRole()) {
                case 'Admin' : return UrlCallRegistry::formatViewUrl('itemlistview','struct',$params);
                case 'Tech' : return UrlCallRegistry::formatViewUrl('itemlistview','struct',$params);
                case 'DataTerminal' : return UrlCallRegistry::formatViewUrl('procedurelistview','struct',$params);
                case 'Guest' : return UrlCallRegistry::formatViewUrl('itemlistview','struct',$params);
                case 'nobody' : return UrlCallRegistry::formatViewUrl('itemlistview','struct',$params);
                default :  return UrlCallRegistry::formatViewUrl('itemlistview','struct',$params);              
            }
            return UrlCallRegistry::formatViewUrl('login','user',$params);
        }
        
        /**
         * returns true if it makes sense for an admin to manage a list of objects for this particular user.
         */
        public function usesObjectsList() {
        	switch($this->getRole()) {
        		case 'DataTerminal' : return true;
        		default :  return false;
        	}        	
        }
        
        public function whoAmIHtml() {
        	if (LoginStatus::getInstance()->isValidUser()) {
        		$html = 'Current User: '.$this->login_id.' ('.TextToHtml($this->fullName(true)).($this->userTypeText() ? ', '.$this->userTypeText() : '').')';
        		if (LoginStatus::getInstance()->returnLoginExists()) $html .= '<p>'.linkify(UrlCallRegistry::formatViewUrl('returnlogin','user'), 'Switch back to '.LoginStatus::getInstance()->getReturnLogin(),'','minibutton2 switch_user_button').'</p>';
        		$fieldtype = $this->getFieldType('user_type');
                $next_role = $this->getNextRole();
        		$next_role_name = $fieldtype['options'][$next_role];        		
        		if ($this->canRoleSwitch()) $html .= '<p>'.linkify(UrlCallRegistry::formatViewUrl('switchrole','user'), 'Switch to '.$next_role_name.' View','','minibutton2 switch_user_button').'</p>';
        	} else {
        		$html = '';
        	}
        	return $html;
        }        
        
        
        public function broadestAllowedUserCategory() {
			return 'all';
        }		
        
	    static public function concatNames($names_array,$lname_first=false) {
	        if ($lname_first) {
	            return trim($names_array['last_name']).', '.trim($names_array['first_name']);
	        } else {
	            return trim($names_array['first_name']).' '.trim($names_array['last_name']);
	        }
	    }
	    
	    /**
	     * This returns an array of typeobject_id numbers that are allowed to be manipulated by the current
	     * user which is assumed to be of type DataTerminal.
	     */
	    public function getDataTerminalObjectIds() {
	    	if ($this->hasLimitedVisibilityOfTypes()) {
	    		$records = DbSchema::getInstance()->getRecords('allowed_typeobject_id',"SELECT allowed_typeobject_id FROM terminaltypeobject where user_id='{$this->user_id}'");
	    		return array_keys($records);
	    	} else {
	    		return array();
	    	}
	    }
	    
	    public function setDataTerminalObjectIds($typeobject_ids) {
	    	$current = $this->getDataTerminalObjectIds();
	    	$ones_to_add = array_diff($typeobject_ids,$current);
	    	$ones_to_delete = array_diff($current,$typeobject_ids);
	    	if (count($ones_to_delete)>0) DbSchema::getInstance()->mysqlQuery("delete from terminaltypeobject where (user_id='{$this->user_id}') and (allowed_typeobject_id IN (".implode(',',$ones_to_delete)."))");
	    	if (count($ones_to_add)>0) {
	    		foreach($ones_to_add as $typeobject_id) {
	    			$Obj = new DBTableRow('terminaltypeobject');
	    			$Obj->user_id = $this->user_id;
	    			$Obj->allowed_typeobject_id = $typeobject_id;
	    			$Obj->save();
	    		}
	    	}
	    }

	    public function hasLimitedVisibilityOfTypes() {
	    	return ($this->getRole()=='DataTerminal');
	    }
	    
	    public function canIEditMyOwnVersion($user_id,$proxy_user_id,$record_created_str) {
	    	$config = Zend_Registry::get('config');
	    	$inside_grace_period = strtotime($record_created_str) + $config->delete_grace_in_sec > script_time();
	    	$can_edit = false;
	    	if ((Zend_Registry::get('customAcl')->isAllowed($this->getRole(),'table:itemversion','edit')
	    			&& ($this->user_id == $user_id) && $inside_grace_period)) {
	    		$can_edit = true;
	    	} else if (Zend_Registry::get('customAcl')->isAllowed($this->getRole(),'table:itemversion','edit')
	    			&& ($this->user_id == $proxy_user_id) && ($_SESSION['account']->getRole() == 'DataTerminal') && $inside_grace_period) {
	    		$can_edit = true;
	    	}
	    	return $can_edit;
	    }	    

	    /**
	     * Used for special login privileges
	     * @param string $role
	     */
	    public function setRoleOverride($role) {
	    	$this->_role_override = $role;
	    }
	    
	    public function isRoleOverride() {
	    	return !is_null($this->_role_override);
	    }
	    
	    public function getRole() {
	    	return !is_null($this->_role_override) ? $this->_role_override : $this->user_type;
	    }
	    
	    public function canRoleSwitch() {
	    	return Zend_Registry::get('config')->admin_can_toggle_roles && $this->user_type=='Admin';
	    }
	    
	    public function getNextRole() {
	    	$fieldtype = $this->getFieldType('user_type');
	    	$roles = array_keys($fieldtype['options']);
    		$idx = array_search($this->getRole(),$roles);
    		if ($idx < count($roles) - 1) {
    			return $roles[$idx + 1];
    		} else {
    			return $roles[0];
    		}
	    }
	    
	    public function switchRoles() {
	    	if ($this->canRoleSwitch()) {
	    		if ($this->getNextRole()=='Admin') {
	    			$this->setRoleOverride(null);
	    		} else {
		    		$this->setRoleOverride($this->getNextRole());
	    		}
	    	}
	    }

	    public function getDependentRecordsBeforeDelete(DbTableRow $DbTableRowContext) {	   
	    	
	    	$relationships = array();
	    	$relationships[] = array('table' => 'user', 'index' => 'user_id','dep_table' => 'reportsubscription', 'dep_index' => 'user_id');
	    	$relationships[] = array('table' => 'user', 'index' => 'user_id','dep_table' => 'document', 'dep_index' => 'user_id');
	    	$relationships[] = array('table' => 'user', 'index' => 'user_id','dep_table' => 'typecomment', 'dep_index' => 'user_id');
	    	$relationships[] = array('table' => 'user', 'index' => 'user_id','dep_table' => 'comment', 'dep_index' => 'user_id');
	    	$relationships[] = array('table' => 'user', 'index' => 'user_id','dep_table' => 'comment', 'dep_index' => 'proxy_user_id');
	    	$relationships[] = array('table' => 'user', 'index' => 'user_id','dep_table' => 'typeversion', 'dep_index' => 'user_id');
	    	$relationships[] = array('table' => 'user', 'index' => 'user_id','dep_table' => 'typeversion', 'dep_index' => 'modified_by_user_id');
	    	$relationships[] = array('table' => 'user', 'index' => 'user_id','dep_table' => 'itemversion', 'dep_index' => 'user_id');
	    	$relationships[] = array('table' => 'user', 'index' => 'user_id','dep_table' => 'itemversion', 'dep_index' => 'proxy_user_id');	    	
	    	
	    	$dependents = parent::getDependentRecordsBeforeDelete($DbTableRowContext); // in case the framework knows about records that are dependent
	    	foreach($relationships as $relationship) {
	    		$Records = new DBRecords($this->_dbschema->dbTableRowObjectFactory($relationship['dep_table'],false,$relationship['dep_index']), $relationship['dep_index'], '');
	    		$Records->getRecordsById($DbTableRowContext->{$relationship['index']});
	    		if (($relationship['dep_table']==$DbTableRowContext->getTableName())) {
	    			foreach($Records->keys() as $key) {
	    				// exclude the self record.
	    				if (($Records->getRowObject($key)->getIndexValue()==$DbTableRowContext->getIndexValue())) {
	    					$Records->unsetItem($key);
	    				}
	    			}
	    		}
	    		if (count($Records->keys())>0) {
	    			$dependents[] = array('relationship' => $relationship, 'DBRecords' => $Records, 'parent_index_value' => $this->{$relationship['index']});
	    		}
	    	}
	    	return $this->removeDuplicateDependents($dependents);
	    }	
	    
	    public function getOtherUsersLikeThisOne() {
	    	$name_search = addslashes($this->last_name);
	    	$or_email_search = $this->email ?  "or email='".addslashes($this->email)."'" : '';
	    	$records = DbSchema::getInstance()->getRecords('user_id',"SELECT * FROM user WHERE (last_name='{$name_search}' $or_email_search) and (user_id != '{$this->user_id}') order by last_name");
	    	return $records;
	    }

	    public function formatPrintField($fieldname, $is_html=true, $nowrap=false, $show_float_units=false) {
	    	$fieldtype = $this->getFieldType($fieldname);
	    	$value = $this->$fieldname;
	    	switch($fieldname) {
	    		case 'email':
	    			return mailto_link($value,$value,'','','');
	    		default:
	    			return parent::formatPrintField($fieldname, $is_html, $nowrap);
	    				
	    	}
	    }
	     
	    
    }


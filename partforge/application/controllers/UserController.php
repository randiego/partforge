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

class UserController extends DBCustomControllerAction
{

    public function indexAction() 
    {
       $this->jumpToUsersLandingPage();
    }
	
    public function loginAction()
    {

    	if (LoginStatus::getInstance()->isValidUser()) {
    		$this->jumpToUsersLandingPage();
    	} else if (Zend_Registry::get('config')->global_readonly && isset($this->params['return_url'])) {
    		$_SESSION['login_url'] = $this->params['return_url'];
    	}

    	if (isset($this->params['form'])) {
    		switch (true)
    		{
    			case isset($this->params['btnLogin']):
    					
    				$plainpw = $this->params['password'];

    				if ($_SESSION['account']->login($this->params['loginid'],$plainpw)) {
    					if (!empty($this->params['remember'])) {
    						LoginStatus::getInstance()->rememberThisUser($this->params['loginid']);
    					} else {
    						LoginStatus::getInstance()->unRememberThisUser();
    					}
	    				LoginStatus::getInstance()->setValidUser(true);
    					if ($_SESSION['account']->has_temporary_password) {
    						$buttonlist = array('Change It Now' => $this->navigator->getCurrentHandlerUrl('btnChangePassword','changetemppassword','user'), 'Remind Me Next Time' => $this->navigator->getCurrentHandlerUrl('btnDoNothing','changetemppassword','user'));
    						if (Zend_Registry::get('config')->allowed_to_keep_temp_pw) $buttonlist['Keep My Password'] = $this->navigator->getCurrentHandlerUrl('btnKeepPassword','changetemppassword','user');
    						showdialog('Change Temporary Password', 'You have a temporary password.', $buttonlist);
    					} else {
    						$this->navigator->jumpToHandler('btnDoNothing','changetemppassword','user');
    					}
    				} else {
    					LoginStatus::getInstance()->unRememberThisUser();
    					$_SESSION['msg'] = $_SESSION['account']->getLastErrorMessage();
    					$this->navigator->jumpToView('login','user', array('msge' => '') );
    				}
    				
    			case isset($this->params['btnRegister']):
    				$TempUser = new DBTableRowUser();
    				$_SESSION['register'] = $TempUser->getArray();
    				$_SESSION['register']['done_url'] = $this->navigator->getCurrentViewUrl(null,'user');
    				$this->navigator->jumpToView('register','user');
    				
    			case isset($this->params['btnForgot']):
					showdialog("Select One:", "",
    				 array('I Forgot My Login ID' => $this->navigator->getCurrentHandlerUrl('btnForgotLogin','login','user'), 'I Forgot My Password' => $this->navigator->getCurrentHandlerUrl('btnForgotPassword','login','user')));
					
				case isset($this->params['btnForgotLogin']):
					if (Zend_Registry::get('config')->allow_username_search) {
						$this->navigator->jumpToView('searchloginids','user');
					} else {
						$_SESSION['findloginbyemail'] = array();
						$this->navigator->jumpToView('findloginbyemail','user');
					}
					
				case isset($this->params['btnForgotPassword']):
					$_SESSION['resetmypassword'] = array();
					$this->navigator->jumpToView('resetmypassword','user');
						
    			case isset($this->params['btnCancelRemember']):
    				LoginStatus::getInstance()->unRememberThisUser();
    				$this->navigator->jumpToView('login','user');
    		}
    	}

    	if (LoginStatus::getInstance()->cookieLogin()) {
    		$this->view->remembered_login = LoginStatus::getInstance()->cookieLogin();
    		$this->view->remembered_cryptpw = LoginStatus::getInstance()->cookieCryptPassword();
    	}
    	$this->view->databasecompatible = getGlobal('databaseversion') == Zend_Registry::get('config')->databaseversion;
    	$this->view->params = $this->params;
    }
    
    public function searchloginidsAction() {  	
    	if (isset($this->params['form'])) {
    		switch (true)
    		{
    			case isset($this->params['btnCancel']):
    				$this->navigator->jumpToView('login');
    			case isset($this->params['login_id']):
    				LoginStatus::getInstance()->rememberThisUser($this->params['login_id']);
    				$this->navigator->jumpToView('login');
    		}
    	}
    		 
    	
    	$this->view->params = $this->params;
    }
    
    public function jsonsearchloginidsAction() {
    	$out = array();
    	if (Zend_Registry::get('config')->allow_username_search) {
    		$and_where = isset($this->params['term']) ? " and last_name ".fetch_like_query($this->params['term'],'','%') : '';
    		$records = DBSchema::getInstance()->getRecords('login_id',"SELECT login_id, concat(last_name,', ',first_name) as full_name FROM user WHERE user_enabled=1 {$and_where} ORDER BY last_name, first_name");
    	    foreach($records as $record) {
	    		$out[] = array('label' => $record['full_name'].' ('.$record['login_id'].')', 'value' => $record['login_id']);
	    	}
    	}
    	echo json_encode($out);
    	die();
    }
    
    /**
     * come here if you need to recover you login ID and the system doesn't allow browsing usernames
     */
    public function findloginbyemailAction() {
    	$_SESSION['findloginbyemail'] = array_merge($_SESSION['findloginbyemail'],$this->params);
    	$Fields = new TableRow();
    	$Fields->setFieldTypeParams('email', 'varchar', 64, true,'Your Email Address','');
    	$Fields->assign($_SESSION['findloginbyemail']);
    	if (isset($this->params['form'])) {
    		switch (true)
    		{
    			case isset($this->params['btnOK']):
    				$errormsg = array();
    				$Fields->validateFields(array('email'),$errormsg);
    				if (count($errormsg)==0) {
    					$User = new DBTableRowUser();
    					if ($User->getRecordByEmail($Fields->email)) {
    						
    						$tmp = array();
    						$tmp['FULLNAME'] = $User->fullName();
    						$tmp['LOGINID'] = $User->login_id;
    						$tmp['URL'] = Zend_Controller_Front::getInstance()->getRequest()->getScheme().'://'.Zend_Controller_Front::getInstance()->getRequest()->getHttpHost().Zend_Controller_Front::getInstance()->getRequest()->getBaseUrl().'/';
    						$toemail = $User->email;
    						$toname = $User->fullName();
    						$fromemail = Zend_Registry::get('config')->notices_from_email;
    						if (!send_template_email(implode("",(@file(APPLICATION_PATH . '/views/LoginIDReminder.txt'))),$toemail,$toname,$fromemail,Zend_Registry::get('config')->application_title,$tmp,Zend_Registry::get('config')->application_title.': Your Login ID')) {
    							$errormsg[] = 'Sorry.  Your email address was found but there was a problem sending your Login ID by email.';
    						} else {
    							$_SESSION['msg'] = 'Your Login ID was sent to '.$User->email.'.  Please check your email.';
    							$this->navigator->jumpToView('login','user', array('msgi' => '') );
    						}
    					} else {
    						$errormsg[] = 'Sorry.  Your email could not be found in the system.';
    					}
    				}
    				$this->go_back_if_invalid_input($errormsg);
    				$this->navigator->jumpToView('login');  // shouldn't get here since there should be a message
    			case isset($this->params['btnCancel']):
    				$this->navigator->jumpToView('login');
    		}
    	}
    	
    	$this->view->params = $this->params;
    }
    
    /**
     * come here if you need to recover you Password.  This just asks for a login ID.
     */
    public function resetmypasswordAction() {
    	$User = new DBTableRowUser();
    	$User->assignFromFormSubmission($this->params, $_SESSION['resetmypassword']);
    	if (isset($this->params['form'])) {
    		switch (true)
    		{
    			case isset($this->params['btnOK']):
    				$errormsg = array();
    				$User->validateFields(array('login_id'),$errormsg);
    				if (count($errormsg)==0) {
    					if ($User->getRecordByLoginID($User->login_id)) {
    						// this is a valid user...  so now do we have a reasonable.  Two choices, (1) send me a reset password.
    						$_SESSION['resetmypassword'] = $User->getArray();
    						$this->navigator->jumpToView('resetmypassword2');
    					} else {
    						$errormsg[] = 'Sorry.  Your Login ID could not be found in the system.';
    					}
    				}
    				$this->go_back_if_invalid_input($errormsg);
    				$this->navigator->jumpToView('login');  // shouldn't get here since there should be a message
    			case isset($this->params['btnCancel']):
    				$this->navigator->jumpToView('login');
    		}
    	}
    	 
    	$this->view->params = $this->params;
    }
    
    /**
     * Once you have the login ID, present options for recovering password
     */
    public function resetmypassword2Action() {
    	$User = new DBTableRowUser();
    	$_SESSION['resetmypassword'] = array_merge($_SESSION['resetmypassword'],$this->params);
    	$User->assignFromFormSubmission($this->params, $_SESSION['resetmypassword']);
    	if (isset($this->params['form'])) {
    		switch (true)
    		{
    			case isset($this->params['btnResetByEmail']):
    				$password = generate_password();
    				$message = TableRowPasswordReset::getPasswordResetMessageTextForUserId($User->user_id, false, '', $password);
    				$to = $User->email;
    				$toname = $User->fullName();
    				$from = Zend_Registry::get('config')->notices_from_email;
    				$fromname = Zend_Registry::get('config')->application_title;
    				$subject = Zend_Registry::get('config')->application_title.": Password Reset";
    				if (!send_template_email($message,$to,$toname,$from,$fromname,array(),$subject)) {
    					showdialog('An Error Occured', block_text_html("There was an error sending the email to {$to}; Your password has NOT been change. You will need to try again."),
    					array('Back' => $this->navigator->getCurrentViewUrl()));
    				}
    				$User->has_temporary_password = true;
    				$User->password = $password;
    				$User->save(array('password','has_temporary_password'));
    				$_SESSION['msg'] = 'Your new password was sent to '.$User->email.'.  Please check your email.';
    				$this->navigator->jumpToView('login','user', array('msgi' => '') );
    			case isset($this->params['btnRequestResetFromAdmin']):
    				$errormsg = array();
    				if (!$User->contact_location) $errormsg[] = 'You need to specify contact information.';
    				$this->go_back_if_invalid_input($errormsg);
    				
    				
    				// start password reset workflow
    				$WF = new GroupTask();
    				$admin_users = DbSchema::getInstance()->getRecords('user_id',"SELECT * FROM user WHERE user_type='Admin' and user_enabled=1");
    				$message_to_approver = "Password reset request for ".$User->fullName().'.  ';
    				$message_to_approver .= "Please reset the user's password and contact them with the new password as follows:\n\n".$User->contact_location;  				
    				
    				$WF->start(array_keys($admin_users), $message_to_approver, $this->navigator->getCurrentViewUrl('id/'.$User->user_id,'user'));
    					
    				$_SESSION['msg'] = "Your request to reset your password has been sent.";
    				$this->navigator->jumpToView('login','user', array('msgi' => '') );
    			case isset($this->params['btnCancel']):
    				$this->navigator->jumpToView('login');
    		}
    	}
    
    	$this->view->dbtable = $User;
    	$this->view->params = $this->params;
    }    
    
    public function changetemppasswordAction()
    {
    	if (isset($this->params['form'])) {
    		switch(true)
    		{
    			case isset($this->params['btnKeepPassword']):
    				$_SESSION['account']->keepMyTemporaryPassword();
    				$this->navigator->jumpToHandler('btnDoNothing');
    				
    			case isset($this->params['btnChangePassword']):
    				$_SESSION['changetemppassword'] = $_SESSION['account']->getArray();
    				$this->navigator->jumpToView();
    				
    			case isset($this->params['btnDoNothing']):
    				if (Zend_Registry::get('config')->global_readonly && isset($_SESSION['login_url'])) {
    					$login_url = $_SESSION['login_url'];
    					unset($_SESSION['login_url']);
    					spawnurl($login_url);
    				} else {
    					$this->jumpToUsersLandingPage();
    				}
    				
    			case isset($this->params['btnOK']):
    				$_SESSION['changetemppassword'] = array_merge($_SESSION['changetemppassword'],$this->params);    
    				$errormsg = array();
    				$TempUser = new DBTableRowUser();
    				$TempUser->assign($_SESSION['changetemppassword']);
    				
    				// make sure the password is not the same as the current one
    				if ($TempUser->testPasswordCorrect($TempUser->password) && !Zend_Registry::get('config')->allowed_to_keep_temp_pw) {
    					$errormsg[] = 'Your new password cannot be the same as your old one.';
    				}
    				
    				$TempUser->validateFields(array('password','password2'),$errormsg);
    				$this->go_back_if_invalid_input($errormsg);
    				$TempUser->has_temporary_password = false;
    				$TempUser->save(array('password','has_temporary_password'));
    				$_SESSION['account']->reload();
    				$this->navigator->jumpToHandler('btnDoNothing');
    		}
    	}
    
    	$this->view->fields = $_SESSION['changetemppassword'];
    }    

    public function logoutAction() {
    	LoginStatus::getInstance()->setValidUser(false);
    	unset($_SESSION['account']);
    	if (Zend_Registry::get('config')->global_readonly && isset($this->params['return_url'])) {
    		spawnurl($this->params['return_url']);
    	} else {
    		$this->navigator->jumpToView('login');
    	}
    }   

    /** Required fields: assigned_to_task_id, link_password
     */
    public function workflowtaskresponseAction() {
    	$Assigned = new DBTableRow('assigned_to_task');
    	if ($Assigned->getRecordById($this->params['assigned_to_task_id'])) {
    		if ($Assigned->link_password == $this->params['link_password']) {
    			// this link is good now get the workflow object itself.
    			$Workflow = GroupTask::getInstance($Assigned->group_task_id);
    			if (!is_null($Workflow)) {
    				if (!$Workflow->isTaskClosed()) {
	    				$Workflow->responded($Assigned->user_id);
	    				spawnurl($Workflow->getRedirectUrl());
    				} else {
    					$_SESSION['msg'] = TextToHtml($Workflow->getRespondedUserNames())." already responded to ".linkify($Workflow->getRedirectUrl(),'this request').".";
    					spawnurl($_SESSION['account']->defaultLoginUrl(array('msgi' => 1)));
    				}
    			}
    		}
    	}
    	// something wrong if you got here
    	$_SESSION['msg'] = "There appears to be something wrong with the link you clicked.";
    	spawnurl($_SESSION['account']->defaultLoginUrl(array('msge' => 1)));    	
    }
    
    public function registerAction() {
    	$_SESSION['register'] = array_merge($_SESSION['register'],$this->params);
    	$TempUser = new DBTableRowUser();
    	$TempUser->assign($_SESSION['register']);
    	if (isset($this->params['form'])) {
    		switch (true)
    		{
    			case isset($this->params['btnOK']):
    				$errormsg = array();
    				$TempUser->validateFields(array('first_name','last_name','login_id','email','email2','password','password2'),$errormsg);
    				$this->go_back_if_invalid_input($errormsg);
    
    				$TempUser->account_created = time_to_mysqldatetime(script_time());
    				$TempUser->login_count = 0;
    				$TempUser->waiting_approval = Zend_Registry::get('config')->self_register_require_approval ? '1' : '0';
    				$TempUser->user_type = Zend_Registry::get('config')->self_register_user_type;
    
    				$TempUser->save(array('first_name','last_name','email','login_id','password','login_count','account_created','user_type','waiting_approval'),false);
    				if (!$TempUser->isSaved()) {
    					showdialog("Sorry, an account with this Login ID already exists.", "Please press the Back button and choose a different Login ID.",
    					array('<== Back' => $this->navigator->getCurrentViewUrl()));
    				}
    
					// start approval workflow
					$WF = new GroupTask();
					$admin_users = DbSchema::getInstance()->getRecords('user_id',"SELECT * FROM user WHERE user_type='Admin' and user_enabled=1");	
                    $message_to_approver = "New Account Approval Request for ".$TempUser->fullName().'.';
                    $message_to_approver .= $TempUser->waiting_approval ? '  Please approve the account if not a duplicate and the request is legitimate.  Also, set the User Type and notify the user.' : '  Please set the User Type and notify the user.';                   
					$WF->start(array_keys($admin_users), $message_to_approver, $this->navigator->getCurrentViewUrl('id/'.$TempUser->user_id,'user'));
					
					$message_to_user = "Your account has been created and tentatively set to User Type '{$TempUser->user_type}'.";
					$message_to_user .= $TempUser->waiting_approval ? '  You will be able to log in when your account has been approved.' 
							     : ($TempUser->user_type=='Guest' ? '  You can login now;  however, until your registration is reviewed, you can only view but not edit anything.' : '  You can login now.');
					showdialog('Thank You for Registering','<p>'.$message_to_user.'</p>', array('OK' => $_SESSION['register']['done_url']));
    
    			case isset($this->params['btnCancel']):
    				spawnurl($_SESSION['register']['done_url']);
    		}
    	  
    	}
    	$this->view->dbtable = $TempUser;
        $this->view->params = $this->params;
    }
    
    public function manageaccountAction()
    {
    	if (isset($this->params['form'])) {
    		switch(true)
    		{
    			case isset($this->params['btnEditProfile']):
    				$_SESSION['changeprofile'] = $_SESSION['account']->getArray();
    				$this->navigator->jumpToView('changeprofile');
    			case isset($this->params['btnEditPassword']):
    				$_SESSION['changepassword'] = $_SESSION['account']->getArray();
    				$this->navigator->jumpToView('changepassword');
    		}
    	}
    	$this->view->dbtable = $_SESSION['account'];
    }
    
    public function changeprofileAction()
    {
    	if (isset($this->params['form'])) {
    		switch(true)
    		{
    			case isset($this->params['btnOK']):
    				$_SESSION['changeprofile'] = array_merge($_SESSION['changeprofile'],$this->params);
    
    				$errormsg = array();
    				$TempUser = new DBTableRowUser();
       				$TempUser->assign($_SESSION['changeprofile']);
    				$TempUser->validateFields(array('first_name','last_name','login_id','email'),$errormsg);
    				$this->go_back_if_invalid_input($errormsg);
    				try {
    					$TempUser->save(array('first_name','last_name','login_id','email'),true);
    				} catch (Exception $e) {  // might be other reasons why we have exception too.
    					showdialog("Sorry, an account with this Login ID already exists.", "Please press the Back button and choose a different Login ID",
    					array('<== Back' => $this->navigator->getCurrentViewUrl()));
    				}
    
    				$_SESSION['account']->reload();
    
    				$this->navigator->jumpToView('manageaccount');
    			case isset($this->params['btnCancel']):
    				$this->navigator->jumpToView('manageaccount');
    		}
    	}
    
    	$this->view->dbtable = new DBTableRowUser();
    	$this->view->dbtable->assign($_SESSION['changeprofile']);
    }
    
    public function changepasswordAction()
    {
    	if (isset($this->params['form'])) {
    		switch(true)
    		{
    			case isset($this->params['btnOK']):
    				$_SESSION['changepassword'] = array_merge($_SESSION['changepassword'],$this->params);
    
    				$errormsg = array();
    				$TempUser = new DBTableRowUser();
    				$TempUser->assign($_SESSION['changepassword']);
					if ($_SESSION['user_cryptpassword']) {
						$TempUser->validateFields(array('currentpassword','password','password2'),$errormsg);
					} else {
						$TempUser->validateFields(array('password','password2'),$errormsg);
					}
    				$this->go_back_if_invalid_input($errormsg);
    				$TempUser->save(array('password'));
    				$_SESSION['account']->reload();
    				$this->navigator->jumpToView('manageaccount');
    			case isset($this->params['btnCancel']):
    				$this->navigator->jumpToView('manageaccount');
        		}
    	}
    
    	$this->view->fields = $_SESSION['changepassword'];
    }

    
    public function listviewAction()
    {
        $ReportData = new ReportDataUser();
        $PaginatedReportPage = new PaginatedReportPage($this->params,$ReportData,$this->navigator);
        if (isset($this->params['form'])) {
            switch (true)
            {
                
                case isset($this->params['btnNewUser']):
                    $initialize = array();
                    $_SESSION['editing_user']['user_id'] = 'new';
                    $this->navigator->jumpToView('editview','user',array('user_id' => 'new', 'return_url' => $this->navigator->getCurrentHandlerUrl('btnSetPassword'), 'initialize' => $initialize, 'resetview' => 1));
                case isset($this->params['btnSetPassword']): 
                	if (is_numeric($_SESSION['editing_user']['user_id'])) {
                		$User = new DBTableRowUser();
                		if ($User->getRecordById($_SESSION['editing_user']['user_id'])) {
		                    $Form = new TableRowPasswordReset();
		                    $Form->user_id = $User->user_id;
		                    $Form->is_new_user = true;
		                    $Form->password = Zend_Registry::get('config')->default_new_password;
		                    $Form->email = $User->email;
		                    $Form->show_password = true;
		                    $Form->has_temporary_password = true;
		                    $Form->email_password = true;
		                    $Form->message = '';
		                    $_SESSION['resetpassword'] = $Form->getArray();
		                    $this->navigator->jumpToView('resetpassword');         
                		}
                	}   
                	$this->navigator->jumpToView();
                case isset($this->params['btnClearMessages']):
                	event_log_notify_clear();                   
                    $this->navigator->jumpToView();
    			case isset($this->params['btnShowEmails']):
    				$this->navigator->CallView('listofemails','',array_merge($this->params,array('resetview' => 1)));            
            }
            $PaginatedReportPage->sort_and_search_handler();
            
        }
        
        /*
         * If we have a special search format then jump to it now.  We also reset the breadcrumbs
        */
        if (isset($this->params['search_string']) && $this->params['search_string']) {
        	// first see if we have a search string link like io/1123
        	$altSearchTargetUrl = specialSearchKeyToUrl($this->params['search_string'],false);
        	if ($altSearchTargetUrl) {
        		$BreadCrumbs = new BreadCrumbsManager();
        		$BreadCrumbs->newAnchor($this->navigator->getCurrentViewUrl('listview','user',array('resetview' => 1,'search_string' => '')),'List of Users');
        		spawnurl($altSearchTargetUrl);
        	}
        }        
        
        $this->view->queryvars = $this->params;
        $this->view->paginated_report_page = $PaginatedReportPage;
    }
    

    
    public function itemviewAction() {
    	$User = new DBTableRowUser();
    	if (isset($this->params['login_id']) && $User->getRecordByLoginID($this->params['login_id'])) {
    	} else if (isset($this->params['user_id']) && is_numeric($this->params['user_id']) && $User->getRecordByID($this->params['user_id'])) {
    	} else {
    		showdialog('Invalid User ID', 'User ID not valid.  Please double check the link format.', array('OK' => $this->navigator->getCurrentViewUrl('listview')));
    	}
    	$this->view->return_url = UrlCallRegistry::formatViewUrl('id/'.$User->user_id,'user');

    	if (isset($this->params['form'])) {
    		switch(true)
    		{
    			case isset($this->params['btnLoginAs']):
    				$account_hold = $_SESSION['account']->getArray();
    				if ($_SESSION['account']->login($User->login_id,'',true,false)) {
    					LoginStatus::getInstance()->setValidUser(true);
    					LoginStatus::getInstance()->setReturnLogin($account_hold['login_id']);
    					$this->jumpToUsersLandingPage();
    				} else { // in the off chance, we didn't login
    					$Msg = $_SESSION['account']->getLastErrorMessage();
    					$_SESSION['account']->assign($account_hold);
    					showdialog('Error Logging In', $Msg,array('OK' => $this->navigator->getCurrentViewUrl('id/'.$this->params['user_id'], 'user')));
    				}
    					
    			case isset($this->params['btnEditAllowedObjects']):
    				$_SESSION['editallowedobjects'] = $User->getDataTerminalObjectIds();
    				$_SESSION['editallowedobjects_user_id'] = $User->user_id;
    				$this->navigator->setReturn($this->view->return_url)->CallView('editallowedobjects');

    			case isset($this->params['btnResetPassword']):
    				$Form = new TableRowPasswordReset();
    				$Form->user_id = $User->user_id;
    				$Form->is_new_user = false;
    				$Form->password = Zend_Registry::get('config')->default_new_password;
    				$Form->email = $User->email;
    				$Form->show_password = true;
    				$Form->has_temporary_password = true;
    				$Form->email_password = true;
    				$Form->message = '';
    				$_SESSION['resetpassword'] = $Form->getArray();
    				$this->navigator->jumpToView('resetpassword');

    			case isset($this->params['btnApproveAccount']):
    				$Form = new TableRowApproveAccount();
    				$Form->user_id = $User->user_id;
    				$Form->user_type = $User->user_type;
    				$Form->send_welcome_email = true;
    				$Form->email = $User->email;
    				$Form->message = '';
    				$_SESSION['approveaccount'] = $Form->getArray();
    				$this->navigator->jumpToView('approveaccount');
    		}
    	}
    	 
    	$this->view->can_edit = Zend_Registry::get('customAcl')->isAllowed($_SESSION['account']->getRole(),'table:user','edit');
    	$this->view->can_delete = Zend_Registry::get('customAcl')->isAllowed($_SESSION['account']->getRole(),'table:user','delete');
    	 
    	$this->view->dbtable = $User;

    }
    
    /**
     * call with $_SESSION['resetpassword'] filled out;
     */
    public function resetpasswordAction() {
    	$Form = new TableRowPasswordReset();
    	if (!$Form->assignFromFormSubmission($this->params,$_SESSION['resetpassword'])) { 
    		$this->showBrowsingError();
    	}
    	
    	$User = new DBTableRowUser();
    	if (!$User->getRecordById($Form->user_id)) {
    		throw new Exception('missing user_id parameter in resetpasswordAction().');
    	}
    	if ($this->getRequest()->isPost() || isset($this->params['chkSendNotificationEmail'])) {
    		switch (true)
    		{
    
    			case isset($this->params['btnOK']):
    
    				// validate the entered parameters using errormessage on the following parameters:
    				
    				$errormsg = array();
    				if ($Form->email_password) {
    					$Form->validateFields(array('email'), $errormsg);
    				}
    				    				
    				if (!$Form->show_password) {
    					if ($Form->password != $Form->password2) {
    						$errormsg['password2'] = 'Please make sure the two passwords are the same.';
    					}
    				}
    				if (count($errormsg)==0) {
    					$User->password = $Form->password;
    					$User->validateFields(array('password'),$errormsg);
    				}
    				$this->go_back_if_invalid_input($errormsg);
    				
    				// save
    				
    				$User->has_temporary_password = $Form->has_temporary_password;
    				$User->save(array('password','has_temporary_password'));
    				
					$this->navigator->jumpToHandler('chkSendNotificationEmail');
					
    			case isset($this->params['chkSendNotificationEmail']):
    				
    				// construct message and send
    				if ($Form->email_password)  {
    					$message = $Form->getPasswordResetMessageText($Form->message,$Form->password);
    					$to = $Form->email;
    					$toname = $User->fullName();
    					$from = $_SESSION['account']->email;
    					$fromname = $_SESSION['account']->fullName();
    					$subject = Zend_Registry::get('config')->application_title.": Password Reset";
    					if (!send_template_email($message,$to,$toname,$from,$fromname,array(),$subject)) {
		    				$return_url = UrlCallRegistry::formatViewUrl('id/'.$User->user_id,'user');
    						showdialog('An Error Occured', block_text_html("There was an error sending the email to {$to}; however, the password has been change. You can Try Again, or send it yourself ".mailto_link($to,'using your email program',$subject,$message).'.'),
    						array('Try Again' => $this->navigator->getCurrentHandlerUrl('chkSendNotificationEmail'), 'Nevermind' => $return_url));
    					}    					
    				}
    						
    				if ($Form->is_new_user) {
    					$_SESSION['msg'] = 'Password set.'.($Form->email_password ? '  Email with username and password was sent to '.$Form->email : '');
    				} else {
	    				$_SESSION['msg'] = 'Password reset.'.($Form->email_password ? '  Email with password was sent to '.$Form->email : '');
    				}
    				$this->navigator->jumpToView('id/'.$User->user_id,'user',array('msgi' => 1));

    			case isset($this->params['btnCancel']):
    				$this->navigator->jumpToView('id/'.$User->user_id,'user');
    		}
    
    	}
    	$this->view->usertable = $User;
    	$this->view->formtable = $Form;
    }    
    
    public function approveaccountAction() {
    	$Form = new TableRowApproveAccount();
    	if (!$Form->assignFromFormSubmission($this->params,$_SESSION['approveaccount'])) {
    		$this->showBrowsingError();
    	}
    	 
    	$User = new DBTableRowUser();
    	if (!$User->getRecordById($Form->user_id)) {
    		throw new Exception('missing user_id parameter in approveaccountAction().');
    	}
    	if ($this->getRequest()->isPost() || isset($this->params['chkSendNotificationEmail'])) {
    		switch (true)
    		{
    
    			case isset($this->params['btnOK']):
    
    				// validate the entered parameters using errormessage on the following parameters:
    
    				$errormsg = array();
    				if ($Form->send_welcome_email) {
    					$Form->validateFields(array('email'), $errormsg);
    				}
    
    				$this->go_back_if_invalid_input($errormsg);
    
    				// save
    				$User->user_type = $Form->user_type;
    				$User->waiting_approval = false;
    				$User->save(array('waiting_approval','user_type'));
    
    				$this->navigator->jumpToHandler('chkSendNotificationEmail');
    					
    			case isset($this->params['chkSendNotificationEmail']):
    
    				// construct message and send
    				if ($Form->send_welcome_email)  {
    					$message = $Form->getMessageText($Form->message);
    					$to = $Form->email;
    					$toname = $User->fullName();
    					$from = $_SESSION['account']->email;
    					$fromname = $_SESSION['account']->fullName();
    					$subject = Zend_Registry::get('config')->application_title.": Account Approved";
    					if (!send_template_email($message,$to,$toname,$from,$fromname,array(),$subject)) {
    						$return_url = UrlCallRegistry::formatViewUrl('id/'.$User->user_id,'user');
    						showdialog('An Error Occured', block_text_html("There was an error sending the email to {$to}; however, the account has been approved. You can Try Again, or send it yourself ".mailto_link($to,'using your email program',$subject,$message).'.'),
    						array('Try Again' => $this->navigator->getCurrentHandlerUrl('chkSendNotificationEmail'), 'Nevermind' => $return_url));
    					}
    				}
    
    				$_SESSION['msg'] = 'Account Approved.'.($Form->send_welcome_email ? '  Approval email was sent to '.$Form->email : '');
    				$this->navigator->jumpToView('id/'.$User->user_id,'user',array('msgi' => 1));
    
    			case isset($this->params['btnCancel']):
    				$this->navigator->jumpToView('id/'.$User->user_id,'user');
    		}
    
    	}
    	$this->view->usertable = $User;
    	$this->view->formtable = $Form;
    }
    
    public function listofemailsAction() {
    	if (isset($this->params['form'])) {
    		switch (true)
    		{
    			case isset($this->params['btnDone']):
    				$this->navigator->returnFromCall();
    		}
    	}
    	$ReportData = new ReportDataUser();
		$this->view->records = $ReportData->get_records($this->params, isset($this->params['search_string']) ? $this->params['search_string'] : '','');
    }    
    
    public function editallowedobjectsAction() {
    	    	
    	if (isset($this->params['form'])) {
    		switch(true)
    		{
    			case isset($this->params['btnOK']):

    				$errormsg = array();
    				if (!isset($this->params['typeobject_id']) || !is_array($this->params['typeobject_id'])) {
    					$errormsg[] = 'Did session timeout?  No form variables were returned.';
    				}
    				
    				$this->go_back_if_invalid_input($errormsg);
    				try {
    					
    					// pack selected typeobject_ids into a simple array
    					$selected_typeobject_ids = array();
    					foreach($this->params['typeobject_id'] as $typeobject_id => $selected) {
    						if ($selected) $selected_typeobject_ids[] = $typeobject_id;
    					}
    					
    					// save the new configuration
    					$TempUser = new DBTableRowUser();
    					$TempUser->getRecordById($_SESSION['editallowedobjects_user_id']);
    					$TempUser->setDataTerminalObjectIds($selected_typeobject_ids);
    				} catch (Exception $e) {  // might be other reasons why we have exception too.
    					showdialog("Sorry, there was an error saving.", "Please press the Back button and try your luck again.",
    					array('<== Back' => $this->navigator->getCurrentViewUrl()));
    				}
    				 
    				$this->navigator->returnFromCall();
    				
    			case isset($this->params['btnCancel']):
    				$this->navigator->returnFromCall();
    		}
    	}
    	 
    	$this->view->editallowedobjects_user_id = $_SESSION['editallowedobjects_user_id'];
    	$this->view->editallowedobjects = $_SESSION['editallowedobjects'];
    }
    
    /**
     * come here if we are coming back from a login as jump
     */
    public function returnloginAction() {
    	if (LoginStatus::getInstance()->isValidUser() && LoginStatus::getInstance()->returnLoginExists()) {
    		$account_hold = $_SESSION['account']->getArray();
    		if ($_SESSION['account']->login(LoginStatus::getInstance()->getReturnLogin(),'',true,false)) {
    			LoginStatus::getInstance()->setValidUser(true);
    		} else { // in the off chance, we didn't login
    			$Msg = $_SESSION['account']->getLastErrorMessage();
    			$_SESSION['account']->assign($account_hold);
    			showdialog('Error Logging In', $Msg,array('OK' => UrlCallRegistry::formatViewUrl('login','user')));
    		}
    	}
    	$this->jumpToUsersLandingPage();
    }
    
    public function switchroleAction() {
    	if (LoginStatus::getInstance()->isValidUser() && $_SESSION['account']->canRoleSwitch()) {
    		$_SESSION['account']->switchRoles();
    	}
    	$this->jumpToUsersLandingPage();
    }   
    
    /**
     * Need to add this to make sure we could capture the edit_db_handler method and override it.
     * @see DBCustomControllerAction::editviewAction()
     */
    public function editviewAction() {
    	return DBControllerActionAbstract::editviewAction();
    }    

    /**
     * Needed to override so that we could catch exceptions in the save when a duplicate login ID was attempted, otherwise very messy.
     * @see DBControllerActionAbstract::edit_db_handler()
     */
    protected function edit_db_handler(DBTableRow $dbtable,$save_fieldnames) {
    	$dbschema = DbSchema::getInstance();
    	$edit_buffer = 'editing_'.$this->getBufferKey($dbtable);
    
    	switch (true)
    	{
    		case isset($this->params['btnOK']):
    			$errormsg = array();
    			$dbtable->validateFields($save_fieldnames,$errormsg);
    			$this->show_error_dialog_if_needed($errormsg,$dbtable,isset($this->params['btnOK']) ? 'btnOK' : 'btnSaveBeforeSubEdit');
    			$is_new = !$dbtable->isSaved();
    			
    			try {
    				$dbtable->save($save_fieldnames,true);
    			} catch (Exception $e) {  // might be other reasons why we have exception too.
    				showdialog("Sorry, an account with this Login ID already exists.", "Please press the Back button and choose a different Login ID",
    				array('<== Back' => $this->navigator->getCurrentViewUrl()));
    			}

    			// map fields back into session var for further handling
    			foreach($dbtable->getFieldNames() as $fieldname) {
    				$_SESSION[$edit_buffer][$fieldname] = $dbtable->{$fieldname};
    			}
    			$dbtable->startSelfTouchedTimer();
    			$_SESSION[$edit_buffer]['form_result'] = 'btnOK';
    			$this->navigator->returnFromCall();
    	}
    	parent::edit_db_handler($dbtable,$save_fieldnames);
    }
}

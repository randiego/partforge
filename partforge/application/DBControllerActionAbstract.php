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
abstract class DBControllerActionAbstract extends Zend_Controller_Action
{
    public $params;
	protected $_redirector = null;
    
    public function init()
    {
    	$this->_redirector = $this->_helper->getHelper('Redirector');  
        $this->params = $this->getRequest()->getParams();
        $this->navigator = new UrlCallRegistry($this,$this->getRequest()->getBaseUrl().'/user/login');
        $this->navigator->setPropagatingParamNames(explode(',',AUTOPROPAGATING_QUERY_PARAMS));
        trim_recursive($this->params);
        $config = Zend_Registry::get('config');
        if ($this->getRequest()->getScheme()!=$config->scheme) {
            // this is an absolute redirect so have to explicitly put in the BaseUrl()
			spawnurl($config->scheme.'://'.$this->getRequest()->getHttpHost().$this->getRequest()->getBaseUrl().$this->navigator->getCurrentViewUrl());
        }
        $this->getResponse()->setHeader('Content-Type', 'text/html; charset=UTF-8', true);
        
        // if cron is not running, we try to service it here.  A mutex of somesort here would be ideal.  If unlucky, two processes might run this at the same time.
        $last_task_run = getGlobal('last_task_run');
        $last_chance_task_interval = 600; // seconds be longer than standard cron interval servicing cron/servicetasks
        if (is_null($last_task_run) || script_time() > strtotime($last_task_run) + $last_chance_task_interval) {
        	setGlobal('last_task_run', time_to_mysqldatetime(script_time()));
        	$TaskRunner = new MaintenanceTaskRunner(array());
        	$TaskRunner->run();
        }
        
    }
    
    public function indexAction()
    {
    	$this->jumpToUsersLandingPage();
    }    
    
    protected function jumpToUsersLandingPage($msg = '')
    {
        if (isset($_SESSION['login_url'])) {
        	
            /*
             * Need to allow only certain urls here because some are not reenterable cold and will bomb.
             * for example: http://mylocalhost/sandbox/struct/importobjectsconfirm.  The question is how to
             * do this.  
             */
            $newurl = $_SESSION['login_url'];           
            unset($_SESSION['login_url']);
            $request = new Zend_Controller_Request_Http(); 
            // make well formed uri to give to setRequestUri()
            if (preg_match('"^(http://|https://)"i',trim($newurl))) {
            	$target_uri = Zend_Uri::factory($newurl);
            	if (!$target_uri->valid()) return $this->_last_chance_return_url;
            	$target_path  = $target_uri->getPath();
            	$target_query = $target_uri->getQuery();
            	if (!empty($target_query)) {
            		$target_path .= '?' . $target_query;
            	}
            	$request->setRequestUri($target_path);   // instead of current uri
            } else {
            	$request->setRequestUri($newurl);
            }
            $request->setParamSources(array());             // instead of current getvars
            Zend_Controller_Front::getInstance()->getRouter()->route($request);     
            // ok now $request should be decomposed into something with an action and controller.  
            $allowed_controller_actions = array('/struct/itemview','/struct/iv','/struct/io','/struct/tv','/struct/to','/struct/lv','/struct/partlistview','/struct/itemdefinitionview','/struct/procedurelistview','/struct/commentlistview','/struct/itemlistview','/struct/whoami','/user/listview','/user/itemview','/user/workflowtaskresponse'); 
            $bare_target_controller_action = '/'.$request->getControllerName().'/'.$request->getActionName();
            if (array_search($bare_target_controller_action,$allowed_controller_actions)!==false) {
            	// it's in the list, so we can jump there
            	spawnurl($newurl);
            }  
        }
        $msg_params = array();
        if ($msg) {
        	$_SESSION['msg'] = $msg;
        	$msg_params = array('msge' => 1);
        }
		spawnurl($_SESSION['account']->defaultLoginUrl($msg_params));
    }
    
    protected function go_back_if_invalid_input($errormsg,$post_script="Please press the Back button to re-enter the information.") {
    	if ($errormsg) {
    		$_SESSION['user_tried_to_save_bad_data'] = true;
    		showdialog('Invalid Input', implode('<br>',$errormsg)."<br><br>".$post_script,
    		array('<== Back' => $this->navigator->getCurrentViewUrl()));
    	}
    }
    
    public function listviewAction()
    {
        
        $this->view->queryvars = $this->params;
        $this->view->navigator = $this->navigator;

    }
    
    public function treeviewAction()
    {
        
        $this->view->queryvars = $this->params;
        $this->view->navigator = $this->navigator;

    }
    
    protected function formatHtmlDeleteMessage($dependents) {
        $text = '';
        if (!empty($dependents)) {
            $text .= "Before you can delete this record, you must delete any records that are attached to it.<br>";
            $text .= "The following items are attached to this record:<br>";
            foreach($dependents as $dependent) {
                $Records = $dependent['DBRecords'];
                $text .= "<br>There are ".count($Records->keys())." items in the ".Ucwords(DbSchema::getInstance()->getNiceTableName($dependent['relationship']['dep_table']))." table:<br><br>";
                $text .= "<ul>";
                foreach($Records->keys() as $key) {
                    $text .= '<li>'.$Records->getRowObject($key)->getShortDescriptionHtml($dependent['relationship']['table'],$dependent['relationship']['dep_index']).'</li>';
                }
                $text .= "</ul>";
            }
            $text .= "<br>";
            $text .= "After these item(s) are deleted, then you may delete this record.<br>";
            $text .= "<br>";
            $text .= "Please press the Back button to return.";
        }
        return $text;
    }
	
	
	/*
		check for exemptions before warning about dependents
	*/
	protected function screenDependentsBeforeDelete(&$dependents) {
	}
	
	/*
		come here after a successful call to delete action, but before jumping back to caller
	*/
	protected function postDelete($index_value) {
	}
   
    public function deleteAction() {
        if (!isset($this->params['table'])) {
            throw new Exception('table not specified in DBControllerActionAbstract::deleteAction()');
        }
        $return_to_url = isset($this->params['return_url']) && $this->params['return_url'] ? $this->params['return_url'] : $this->navigator->getCallingUrl();
        $get_vars = $this->getRequest()->getQuery();
        if (isset($this->params['table'])) {
            $Dbschema = DbSchema::getInstance();
            // get the index name for this table
            $EditRow = $Dbschema->dbTableRowObjectFactory($this->params['table']);
            if (isset($get_vars[$EditRow->getIndexName()])) {
                if ($EditRow->getRecordById($get_vars[$EditRow->getIndexName()])) {
                    // make sure there are not other records that need to be deleted first
                    $dependents = $EditRow->getDependentRecordsBeforeDelete($EditRow);
					$this->screenDependentsBeforeDelete($dependents);
                    if (!empty($dependents)) {
						$EditRow->startSelfTouchedTimer();
                        showdialog('Cannot Delete', $this->formatHtmlDeleteMessage($dependents), array('<== Back' => $return_to_url));
                    } else {
						$index_value = $get_vars[$EditRow->getIndexName()];
                        $EditRow->delete();
						$this->postDelete($index_value);
                    }
                }
            }

        }
        // this return logic needs more work: problem is if I want to call editview from another handler and have it come back.
        spawnurl($return_to_url);
    }
	
    // this maps the get vars into a session for an editing session.  Override this if you want
    // to introduce some new editing modes, like for instance, versioned editing.
	public function preEditViewSessionLoad($force_save_new_records = false) {
        if (!isset($this->params['table'])) {
            throw new Exception('table not specified in DBControllerActionAbstract::editviewAction()');
        }
        // get the index name for this table
        $indexname = DbSchema::getInstance()->getPrimaryIndexName($this->params['table']);
        $get_vars = $this->getRequest()->getQuery();
        if (!isset($get_vars[$indexname])) {
            throw new Exception("index '{$indexname}' not specified in DBControllerActionAbstract::editviewAction()");
        }
        $EditRow = DbSchema::getInstance()->dbTableRowObjectFactory($this->params['table']);
        if ($EditRow->hasDedicatedSortOrderField()) { // we need to save the initialized value in case we are copying
            $hold_sort_order = $EditRow->{$EditRow->getSortOrder()};
        }
        if ($get_vars[$indexname]!='new') {
            $EditRow->getRecordById($get_vars[$indexname]);
        }
//        $fields_to_save_now = array($indexname);
        if (isset($get_vars['initialize']) && is_array($get_vars['initialize'])) {
            $EditRow->processPostedInitializeVars($get_vars['initialize']);
        }
        if (isset($get_vars['save_as_new'])) {
            $EditRow->{$indexname} = 'new';
            if ($EditRow->hasDedicatedSortOrderField() && !empty($hold_sort_order)) { // if we are copying, then restore new sort order value so it will appear at end
                $EditRow->{$EditRow->getSortOrder()} = $hold_sort_order;
            }
        }
        if ($force_save_new_records && ($EditRow->{$indexname}=='new')) {
            $EditRow->save();
        }
        $edit_buffer = 'editing_'.$this->getBufferKey($EditRow);
        $_SESSION[$edit_buffer] = $EditRow->getArray();
        
        //  certain fields can be explicitely excluded from editing.  These fields are stashed in a session var
        if (isset($get_vars['hidefields']) && is_array($get_vars['hidefields'])) {
            $_SESSION[$edit_buffer.'_hidefields'] = $get_vars['hidefields'];
        } elseif (isset($_SESSION[$edit_buffer.'_hidefields'])) {
            unset($_SESSION[$edit_buffer.'_hidefields']);
        }
        
        // have we specified an alternate editing mode?
        if (isset($get_vars['version_edit_mode'])) {
        	$_SESSION[$edit_buffer]['version_edit_mode'] = $get_vars['version_edit_mode'];
        }

        if (isset($this->params['parent_index'])) $_SESSION[$edit_buffer]['parent_index'] = $this->params['parent_index'];
        return $this->getBufferKey($EditRow);
	}
	
	public function jumpToEditViewDb($edit_buffer_key) {
        // this return logic needs more work: problem is if I want to call editview from another handler and have it come back.
//        if (!isset($this->params['return_url'])) {
//        	throw new Exception("you must use return_url in DBControllerActionAbstract::jumpToEditViewDb()");
//        }
        $return_to_url = isset($this->params['return_url']) && $this->params['return_url'] ? $this->params['return_url'] : $this->navigator->getCallingUrl();
        $target_action = method_exists($this,'editview'.$this->params['table'].'Action') ? 'editview'.$this->params['table'] : 'editviewdb';
        $params = $this->navigator->getPropagatingParamValues();
        $params['edit_buffer_key'] = $edit_buffer_key;
        if (isset($this->params['resetview'])) $params['resetview'] = 1;
        $this->navigator->setReturn($return_to_url)->setReturnConditions(array('action' => $target_action,'table' => $params['table'],'edit_buffer_key' => $edit_buffer_key))->callView($target_action,'',$params);
	}
    
    /*
      To initialize parameters, need to come here with queryvars initialize set.
    */
    
    public function editviewAction() {

		$edit_buffer_key = $this->preEditViewSessionLoad();
		
		$this->jumpToEditViewDb($edit_buffer_key);
        
    }
    
    protected function showBrowsingError() {
        showdialog('Browsing Error.','<p>It appears that you are trying to edit more than one record at a time.  This may be because you have more than one browser window open.  Please use only a single browser window.</p>',array('OK' => $this->navigator->getCurrentViewUrl()));
    }
    
    /*
     * This returns buffer key name.  This is used for identifying the editing buffer in the $_SESSION
	 * array for persistence during editing operations.
     */
    public function getBufferKey(TableRow $dbtable) {
    	return $dbtable->getTableName();
    }
    
    /*
      this is the action for editing a record in the SESSION variable.
      override this to simply change the view and save handling
    */
    public function editviewdbAction() {
        if (!isset($this->params['table'])) {
            throw new Exception('table not specified in DBControllerActionAbstract::editviewdbAction()');
        }
        if (!isset($this->params['edit_buffer_key'])) {
            throw new Exception('edit_buff not specified in DBControllerActionAbstract::editviewdbAction()');
        }
    	$edit_buffer = 'editing_'.$this->params['edit_buffer_key'];
        $EditRow = DbSchema::getInstance()->dbTableRowObjectFactory($this->params['table'],false,$_SESSION[$edit_buffer]['parent_index']);
        if (!$EditRow->assignFromFormSubmission($this->params,$_SESSION[$edit_buffer])) {  // this updates $_SESSION['editapplication'] too
            $this->showBrowsingError();
        }        

		if (isset($this->params['form'])) {
			$this->edit_db_handler($EditRow,$EditRow->getSaveFieldNames());            
        }
        
        $this->view->dbtable = $EditRow;
        $this->view->navigator = $this->navigator;
        $this->view->edit_buffer_key = $this->params['edit_buffer_key'];
        
        $this->render('editview');        
        
    }
    
    public function show_error_dialog_if_needed($errormsg,$dbtable,$button_value) {
    	$fatal_fields = array_diff(array_keys($errormsg), $dbtable->getFieldNamesWithoutStrictValidation());
    	$buttons = array('<== Back' => $this->navigator->getCurrentViewUrl());
    	$show = false;
    	if (count($fatal_fields)>0) {
    		$show = true;
    	} else if ((count($errormsg)>0) && !isset($this->params['ignore_nonstrict_errors'])) {
    		$show = true;
    		$buttons['Save Anyway'] = $this->navigator->getCurrentHandlerUrl($button_value,null,null,array('ignore_nonstrict_errors' => 1));
    	}
    	if ($show) {
    		$_SESSION['user_tried_to_save_bad_data'] = true;
    		showdialog('Invalid Input', implode('<br>',$errormsg)."<br><br>Please press the Back button to re-enter the information.",$buttons);
    	}    	
    }
    
    protected function edit_db_handler(DBTableRow $dbtable,$save_fieldnames) {
        $dbschema = DbSchema::getInstance();
        $edit_buffer = 'editing_'.$this->getBufferKey($dbtable);
		
        switch (true)
        {
            case isset($this->params['btnOK']):
            case isset($this->params['btnSaveBeforeSubEdit']):
                $errormsg = array();
                $dbtable->validateFields($save_fieldnames,$errormsg);
                $this->show_error_dialog_if_needed($errormsg,$dbtable,isset($this->params['btnOK']) ? 'btnOK' : 'btnSaveBeforeSubEdit');
                $is_new = !$dbtable->isSaved();
                $dbtable->save($save_fieldnames);
                // renumber all the sort keys if there is a dedicate sort key associated with this table
                if ($dbtable->hasDedicatedSortOrderField()) {
                    $dbtable->renumberAndSaveSortOrderFields();
                }
            
                // map fields back into session var for further handling
                foreach($dbtable->getFieldNames() as $fieldname) {
                    $_SESSION[$edit_buffer][$fieldname] = $dbtable->{$fieldname};
                }
                if (isset($this->params['btnOK'])) {
                    $dbtable->startSelfTouchedTimer();
    //                if ($is_new) $_SESSION['embedded_editing_return_url'] = str_replace($indexfieldname.'=new',$indexfieldname.'='.$dbtable->{$indexfieldname},$_SESSION['embedded_editing_return_url']);
					$_SESSION[$edit_buffer]['form_result'] = 'btnOK';
                    $this->navigator->returnFromCall();
                } else { // btnSaveBeforeSubEdit
                    // see if there are any undefined initialization parameters that can be defined now
                    $sub_params = array();
                    parse_str($this->params['sub_edit_params'], $sub_params);
                    if (!empty($sub_params['initialize']) && is_array($sub_params['initialize'])) {
                        foreach($sub_params['initialize'] as $field => $value) {
                            // if the value is a string that starts with $ then initialize it to $dbtable->{$value}
                            if (is_string($value) && (strlen($value) > 1) && ($value[0]=='$')) {
                                $dbtable_fieldname = substr($value,1);
                                $sub_params['initialize'][$field] = $dbtable->$dbtable_fieldname;
                            }
                        }
                    }
                    $jump_params = $this->navigator->getPropagatingParamValues();
                    $jump_params['btnSubEditParams'] = http_build_query($sub_params);
                    $jump_params[$dbtable->getIndexName()] = $dbtable->getIndexValue();
                    $this->navigator->jumpToHandler('btnSubEditParams',null,null,$jump_params);
                }
            case isset($this->params['btnCancel']):
                $dbtable->startSelfTouchedTimer();
                $this->navigator->returnFromCall();
            case !empty($this->params['btnSubEditParams']):
                $sub_params = array();
                parse_str($this->params['btnSubEditParams'], $sub_params); // get params for jumping to the subtable editing
                /*
                  see if there are any undefined initialization parameters.  These indicate that we have an unsaved parent
                  that needs to be saved before proceeding.
                */
                $must_save_first = false;
                if (!empty($sub_params['initialize']) && is_array($sub_params['initialize'])) {
                    foreach($sub_params['initialize'] as $field => $value) {
                        // if the value is a string that starts with $ then initialize it to $dbtable->{$value}
                        if (is_string($value) && (strlen($value) > 1) && ($value[0]=='$')) {
                            $must_save_first = true;
                            break;
                        }
                    }
                }
                if (!is_numeric($dbtable->getIndexValue())) {
                    $must_save_first = true;
                }
                
                if ($must_save_first) {
					$_SESSION[$edit_buffer]['version_edit_mode'] = 'vem_finish_save_record';
                    showdialog('Please Save', "You must save this record before editing related items.  Click to save.", 
                    		array(	'Save' => $this->navigator->getCurrentHandlerUrl('btnSaveBeforeSubEdit',null,null,array('sub_edit_params' => $this->params['btnSubEditParams'])), 
                    				'Cancel' => $this->navigator->getCurrentViewUrl()));
                }
				
				// check changes: Yes, No, Cancel
                if (isset($sub_params['force_save'])) {
                    unset($sub_params['force_save']);
					if ($dbtable->hasChanged($save_fieldnames)
						&& Zend_Registry::get('customAcl')->isAllowed($_SESSION['account']->getRole(),'table:'.$dbtable->getTableName(),'edit')
						&& !$dbtable->isEditOperationBlocked('save',$dbtable->getTableName())) {
						$sub_edit_params = http_build_query($sub_params);
						$jump_params = $this->navigator->getPropagatingParamValues();
						$jump_params['btnSubEditParams'] = $sub_edit_params;
						showdialog('Fields in the record have changed.', "<p>Do you want to save the changes?</p>",
								array('Yes' => $this->navigator->getCurrentHandlerUrl('btnSaveBeforeSubEdit',null,null,array('sub_edit_params' => $sub_edit_params)),
									  'No' => $this->navigator->getCurrentHandlerUrl('btnSubEditParams',null,null,$jump_params),
									  'Cancel' => $this->navigator->getCurrentViewUrl()));
					}
                }
                
                // if we are jumping to another branch of the tree, we should go back to where we would go back from here
                if (isset($sub_params['forward_return'])) {
                    unset($sub_params['forward_return']);
                    $sub_params['return_url'] = $this->navigator->getCallingUrl();
                } else {
 //                   $sub_params['return_url'] = $this->navigator->setCurrent(null,null,array('table' => $this->params['table']))->getCurrentViewUrl();
 					$params_in_return_url = array('table' => $this->params['table']);
 					if (isset($sub_params['subedit_return_value'])) {
 						$params_in_return_url['subedit_return_param'] = $sub_params['subedit_return_value'];
 					}
                    $sub_params['return_url'] = $this->navigator->getCurrentViewUrl(null,null,$params_in_return_url);
                }
                
                $next_action = $sub_params['action'];            unset($sub_params['action']);
                $next_controller = $sub_params['controller'];    unset($sub_params['controller']);
                $sub_params['resetview'] = 1;
                $this->navigator->unsetPropagatingParam('table')->jumpToView($next_action,$next_controller,$sub_params);
            case !empty($this->params['btnAddIncomingJoin']):
                $joins = $dbtable->getJoinFieldsAndTables();
                $join_name = $this->params['btnAddIncomingJoin'];
                $target = $joins[$join_name];
                // if its a RW join but not currently active, then make it so
//                $rhs_index = $target['field_prefix'].'__'.$target['rhs_index'];
//                $rhs_primary_index = $target['field_prefix'].'__'.$target['rhs_dbtableobj']->getIndexName();
                if (('incoming' == $target['type']) && ('RW' == $target['mode']) && !$dbtable->joinFieldsAreActive($join_name)) {
					// map each $fieldname from $target['rhs_dbtableobj'] into $_SESSION
					foreach($target['rhs_dbtableobj']->getFieldNames() as $fieldname) {
						$_SESSION[$edit_buffer][$target['field_prefix'].'__'.$fieldname] = $target['rhs_dbtableobj']->{$fieldname};
					}
					// connect to the current record
                    $_SESSION[$edit_buffer][$target['field_prefix'].'__'.$target['rhs_index']] = $dbtable->{$target['lhs_index']};
//                    $_SESSION[$edit_buffer][$rhs_primary_index] = 'new';
                }
                $this->navigator->jumpToView();
            case !empty($this->params['btnDeleteIncomingJoin']):
                $result = $dbtable->deleteIncomingJoin($this->params['btnDeleteIncomingJoin']);
                foreach($result['update_field_list'] as $fieldname) {
                    $_SESSION[$edit_buffer][$fieldname] = $dbtable->{$fieldname};
                }
                if (!empty($result['blocking_dependents'])) {
                    showdialog('Cannot Delete', $this->formatHtmlDeleteMessage($result['blocking_dependents']), array('<== Back' => $this->navigator->getCurrentViewUrl()));
                }
                $this->navigator->jumpToView();
            case ($this->params['btnOnChange'] == 'sort_order'):
                $sub_params = array();
                parse_str($this->params['onChangeParams'], $sub_params);
                if (!empty($sub_params['tablename'])) {
                    $RowObj = DbSchema::getInstance()->dbTableRowObjectFactory($sub_params['tablename'],false,$sub_params['parentindex']);
                    if ($RowObj->hasDedicatedSortOrderField()) {
                        $i = 0;
                        foreach($sub_params['keys'] as $key) {
                            $i = $i + 10;
                            $RowObj->getRecordById($key);
                            $RowObj->{$RowObj->getSortOrder()} = $i;
                            $RowObj->save(array($RowObj->getSortOrder()));
                        }
                    }
                }
                $this->navigator->jumpToView();
            case ($this->params['btnOnChange'] == 'joinselectchange'):
                // if $dbtable->student_id != $dbtable->s__student_id then reload that part
                /*
                 When a join selection box changes, we put the command word in the
                 $target['lhs_index'] if it is other than just a change of record ID.
                */
                foreach($dbtable->getJoinFieldsAndTables() as $join_name => $target) {
                    if (('outgoing' == $target['type']) && !empty($dbtable->{$target['lhs_index']})) { // there is a non-zero join table, so show the fields
                        $rhs_index = $target['field_prefix'].'__'.$target['rhs_index'];
                        if ($dbtable->{$target['lhs_index']}!=$dbtable->{$rhs_index}) {
                            
                            // need to reload this join table data and leave everything else
                            $TempJoinRow = DbSchema::getInstance()->dbTableRowObjectFactory($target['rhs_table'],true);
                            
                            if (is_numeric($dbtable->{$target['lhs_index']})) {
                                $TempJoinRow->getRecordById($dbtable->{$target['lhs_index']});
                                
                            } elseif ('delete'==$dbtable->{$target['lhs_index']}) {
                                
                                // make sure current record has been saved and that it is a RW join.  Probably some other things to check too
                                if (is_numeric($dbtable->getIndexValue()) && ('RW'==$target['mode'])) {
                                    
                                    // check dependent records.
                                    if ($TempJoinRow->getRecordById($dbtable->{$rhs_index})) { //TODO: Assuming that $rhs_index is primary index which I hope is true
                                        
                                        // make sure there are not other records that need to be deleted first
                                        $dependents = $TempJoinRow->getDependentRecordsBeforeDelete($dbtable);
                                        
                                        if (!empty($dependents)) {
                                            // overlay command with actual index
                                            $_SESSION[$edit_buffer][$target['lhs_index']] = $dbtable->{$rhs_index};
                                            showdialog('Cannot Delete', $this->formatHtmlDeleteMessage($dependents), array('<== Back' => $this->navigator->getCurrentViewUrl()));
                                        } else {
                                            $TempJoinRow->delete();
                                            // now set join link to null and save it
                                            $dbtable->{$target['lhs_index']} = null;
                                            $dbtable->save(array($target['lhs_index']));
                                            $_SESSION[$edit_buffer][$target['lhs_index']] = null;
                                        }
                                    }
                                }
                            } elseif ('detach'==$dbtable->{$target['lhs_index']}) {
                                $_SESSION[$edit_buffer][$target['lhs_index']] = null;
                            } // else new
                            foreach($TempJoinRow->getFieldNames() as $fieldname) {
                                $_SESSION[$edit_buffer][$target['field_prefix'].'__'.$fieldname] = $TempJoinRow->{$fieldname};
                            }
                        }
                    }
                }
                $this->navigator->jumpToView();
		}
    }

    
}

?>

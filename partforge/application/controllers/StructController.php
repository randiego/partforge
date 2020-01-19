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

class StructController extends DBControllerActionAbstract
{

	/**
	 * Looks to see if the search term entered is somehow special and warrents jumping directly to an object. 
	 * @param ReportData $ReportData this is an instantiated instance of the ReportData class that makes up the list view.
	 * @param string $quickjump_prefix this is the locator prefix (iv, or tv) that should be used to construct the locator url.  Leave blank if you don't want the single result search to jump.
	 * @param string $quickjump_indexname if we construct a url, this is the fieldname of the number part of the locator as it would appear in the $ReportData results
	 * @return string which is the URL of the unique object we want to jump to, otherwise return blank if we should just show the search results in a list
	 */
	protected function getAlternateSearchUrl($ReportData, $quickjump_prefix='iv', $quickjump_indexname='iv__itemversion_id') {
		// first see if we have a search string link io/1123
		$altSearchTargetUrl = specialSearchKeyToUrl($this->params['search_string'], true);
		// if not a prefix format, then see if we have a single search result we can jump to
		$searchResultCount = $ReportData->get_records_count($this->params, $this->params['search_string']);
		if (!$altSearchTargetUrl && ($searchResultCount==1) && ($quickjump_prefix!='')) {
			$records = $ReportData->get_records($this->params, $this->params['search_string'],'');
			$record = reset($records);
			$altSearchTargetUrl = formatAbsoluteLocatorUrl($quickjump_prefix,$record[$quickjump_indexname]);
		}
		// finally see if our luck changes if we assume an integer really means io/nnnn but only do it if we have gotten no hits
		if (!$altSearchTargetUrl && ($searchResultCount==0)) {
			$altSearchTargetUrl = specialSearchKeyToUrl($this->params['search_string'], false);
		}
		return $altSearchTargetUrl;
	}
	
	public function partAndProcListViewHandler($is_user_procedure) {
		$this->view->is_user_procedure = $is_user_procedure;
		$ReportData = new ReportDataItemListView(false, false, $is_user_procedure, $this->params['search_string'],null,null,true);
		$PaginatedReportPage = new PaginatedReportPage($this->params,$ReportData,$this->navigator);
		
		if (isset($this->params['form'])) {
			switch (true)
			{
				case isset($this->params['btnNewItem']):
					$return_url = $this->navigator->getCurrentHandlerUrl('btnDoneTryingToCreateNew',null,null,array('typeversion_id' => $this->params['typeversion_id']));
					$_SESSION['most_recent_new_itemversion_id'] = 'new';  // this should get changed by a successfull change
					$initialize = array('typeversion_id' => $this->params['typeversion_id']);
					if (isset($this->params['partnumber_alias'])) $initialize['partnumber_alias'] = $this->params['partnumber_alias'];
					$this->navigator->setReturn($return_url)->CallView('editview','',array('table' => 'itemversion', 'itemversion_id' => 'new', 'resetview' => 1, 'initialize' => $initialize));
				case isset($this->params['btnDoneTryingToCreateNew']):
					if (is_numeric($_SESSION['most_recent_new_itemversion_id'])) {
						if ($this->view->is_user_procedure) {
							$params = array('itemversion_id' => $_SESSION['most_recent_new_itemversion_id']);
							if ($_SESSION['account']->getRole()=='DataTerminal') {
								$params['offer_more_url'] = $this->navigator->getCurrentHandlerUrl('btnNewItem',null,null,array('typeversion_id' => $this->params['typeversion_id']));
							}
							$this->navigator->jumpToView('itemview','',$params);
						} else {
							$this->navigator->jumpToView('itemview','',array('itemversion_id' => $_SESSION['most_recent_new_itemversion_id'], 'offer_more_url' => $this->navigator->getCurrentHandlerUrl('btnNewItem',null,null,array('typeversion_id' => $this->params['typeversion_id']))));
						}
					} else {
						$this->navigator->jumpToView();
					}
				case isset($this->params['btnSetViewPreference']):
					if (isset($this->params['chkShowProcMatrix'])) {
						$_SESSION['account']->setPreference('chkShowProcMatrix', $this->params['chkShowProcMatrix']);
					}
					if (isset($this->params['chkShowAllFields'])) {
						$_SESSION['account']->setPreference('chkShowAllFields', $this->params['chkShowAllFields']);
					}
					$this->navigator->jumpToView();
		
				case isset($this->params['btnSavetoCSV']):
					$params = $this->navigator->getPropagatingParamValues();
					$filename1 = $this->view->is_user_procedure ? 'ExportCurrentProcedureVersions.csv' : 'ExportCurrentItemVersions.csv';
					$filename2 = $this->view->is_user_procedure ? 'ExportAllProcedureVersions.csv' : 'ExportAllItemVersions.csv';
					spawnshowdialog('Save to a comma-separated values (CSV) file','<p>To save the records, click the link below, or right-click the link and choose "Save Target/Link As":</p>
						<p>'.linkify($this->navigator->getCurrentViewUrl('outputcsv','',array_merge($params,array('filename' => 'ExportCurrentItemVersions.csv', 'output_all_versions' => false, 'is_user_procedure' => $this->view->is_user_procedure))),$filename1).'</p>
						<p>'.linkify($this->navigator->getCurrentViewUrl('outputcsv','',array_merge($params,array('filename' => 'ExportAllItemVersions.csv', 'output_all_versions' => true, 'is_user_procedure' => $this->view->is_user_procedure))),$filename2).'</p>',array('<== Back' => $this->navigator->getCurrentViewUrl()));
				case ($this->params['btnOnChange'] == 'catchange'):
					//if $this->params['view_category'] is prefixed with "fav" then strip it before storing.  Only numeric or * are allowed.
					if (is_numeric($this->params['view_category']) || ($this->params['view_category']=='*')) {
						$_SESSION['account']->setPreference($ReportData->pref_view_category_name, $this->params['view_category']);
					} else if (preg_match('/^fav([0-9]+)$/',$this->params['view_category'],$out)===1) {
						$_SESSION['account']->setPreference($ReportData->pref_view_category_name, $out[1]);
					} else if (preg_match('/^([0-9]+)a([0-9]+)$/',$this->params['view_category'],$out)===1) {
						$_SESSION['account']->setPreference($ReportData->pref_view_category_name, $out[1]);
					} 
				
					$currCategory = $_SESSION['account']->getPreference($ReportData->pref_view_category_name);
					$_SESSION['account']->setNumericFavoriteToRollingList($ReportData->pref_view_category_name.'_fav', $currCategory);
					unset($this->params['pageno']); // all handled commands but this one require resetting to the first page which this does
					$this->navigator->jumpToView();					
			}
			$PaginatedReportPage->sort_and_search_handler();
		}
		
		/*
		 * If we have a special search format then jump to it now.  We also reset the breadcrumbs
		*/
		if (isset($this->params['search_string']) && $this->params['search_string']) {
			$altSearchTargetUrl = $this->getAlternateSearchUrl($ReportData,'iv','iv__itemversion_id');
			if ($altSearchTargetUrl) {
				$BreadCrumbs = new BreadCrumbsManager();
				$BreadCrumbs->newAnchor($this->navigator->getCurrentViewUrl($this->view->is_user_procedure ? 'procedurelistview' : 'itemlistview','struct',array('resetview' => 1,'search_string' => '')),$this->view->is_user_procedure ? 'List of Procedures' : 'List of Parts');
				spawnurl($altSearchTargetUrl);
			}
		}
		
		$this->view->queryvars = $this->params;
		$this->view->report_data = $ReportData;
		$this->view->paginated_report_page = $PaginatedReportPage;
		$this->view->navigator = $this->navigator;
		$this->render('itemlistview');
	}
	
	public function itemlistviewAction()
	{
		$this->partAndProcListViewHandler(false);
    }  
	    
    public function procedurelistviewAction()
    {
		$this->partAndProcListViewHandler(true);
   	}
     
    
    /**
     * This is sort of deprecated (2014/10).  New Items created by
     * /sandbox/struct/remoteedit?form=&btnNewItem=&typeobject_id=NNN
     * The new preferred way is 
     * /sandbox/struct/to/239/new
     */
    public function remoteeditAction() {
    	if (isset($this->params['form'])) {
    		switch (true)
    		{
    			case isset($this->params['btnNewItem']):
    				$return_url = $this->navigator->getCurrentHandlerUrl('btnDoneTryingToCreateNew',null,null,array('typeversion_id' => $this->params['typeversion_id']));
    				$_SESSION['most_recent_new_itemversion_id'] = 'new';  // this should get changed by a successfull change
    				$initialize = array();
    				if (isset($this->params['typeversion_id'])) {
    					$initialize['typeversion_id'] = $this->params['typeversion_id'];
    				} elseif (isset($this->params['typeobject_id'])) {
    					$TypeObject = DbSchema::getInstance()->dbTableRowObjectFactory('typeobject');
    					if ($TypeObject->getRecordById($this->params['typeobject_id'])) {
    						$initialize['typeversion_id'] = $TypeObject->cached_current_typeversion_id;
    					}
    				}
    				$params = array('table' => 'itemversion', 'itemversion_id' => 'new');
    				$params['initialize'] = $initialize;
    				if (is_array($this->params['hidefields'])) $params['hidefields'] = $this->params['hidefields'];
    				$params['version_edit_mode'] = 'vem_remoteedit';
    				$params['resetview'] = 1;
    				$this->navigator->setReturn($return_url)->CallView('editview','',$params);
    				
    			case isset($this->params['btnEditItem']):
    				$return_url = $this->navigator->getCurrentHandlerUrl('btnDoneTryingToCreateNew');
    				$_SESSION['most_recent_new_itemversion_id'] = 'new';  // this should get changed by a successfull change
    				$params = array('table' => 'itemversion', 'itemversion_id' => $this->params['itemversion_id']);
    				if (is_array($this->params['hidefields'])) $params['hidefields'] = $this->params['hidefields'];
    				$params['version_edit_mode'] = 'vem_remoteedit';
    				$params['resetview'] = 1;
    				$this->navigator->setReturn($return_url)->CallView('editview','',$params);


    			case isset($this->params['btnDoneTryingToCreateNew']):
    				if (is_numeric($_SESSION['most_recent_new_itemversion_id'])) {
    					// successfully added record
    					echo 'itemversion_id='.$_SESSION['most_recent_new_itemversion_id'];
    					die;
    				} else {
    					// cancelled out.
    					echo 'canceled';
    					die;
    				}
    		}
    	
    	}
    	echo "no handler defined";
    	die;
    	 
    }
    
	/**
	 * The this is a possibly interactive way of getting a login ID and making sure the user was forced to login somehow.
	 * Used by add browsers.
	 */
    public function whoamiAction() {
    	echo "<output><login_id>".$_SESSION['account']->login_id.'</login_id></output>';
    	die; 
    }    
    
    /**
     * used to ping and keep alive
     */
    public function keepaliveAction() {
    	$out = array();
    	$out['is_valid_user'] = LoginStatus::getInstance()->isValidUser();
    	if ($out['is_valid_user']) $out['login_id'] = $_SESSION['account']->login_id;
    	echo json_encode($out);
    	die;
    }    
    
    public function outputcsvAction() {
    	if (!isset($this->params['filename']) || !isset($this->params['output_all_versions']) || !isset($this->params['is_user_procedure'])) {
    		throw new Exception('missing parameter in outputcsvAction().');
    	}
    	$ReportData = new ReportDataItemListView(true,$this->params['output_all_versions'],$this->params['is_user_procedure'], $this->params['search_string']);
    	$CsvGen = new CsvGenerator($this->params,$ReportData);
    	$CsvGen->outputToBrowser();
    }
    
    public function joinedexportAction() {
    	if (isset($this->params['resetview']) || !is_array($_SESSION['joinedexport'])) {
    		$_SESSION['joinedexport'] = array();
    	} else {
    		$_SESSION['joinedexport'] = array_merge($_SESSION['joinedexport'],$this->params);
    	}
    	if (isset($this->params['form'])) {
    		switch (true)
    		{
    			case isset($this->params['btnSaveCSV']):

    				outputJoinedCSVToBrowser($_SESSION['joinedexport']['A_category'],$_SESSION['joinedexport']['B_category'],
    				$_SESSION['joinedexport']['A_joincolumn'],$_SESSION['joinedexport']['B_joincolumn']);

    			case isset($this->params['btnSaveOneToCSV']):

    				if (isset($_SESSION['joinedexport'][$this->params['tableid'].'_category'])) {
    					outputCSVToBrowser($_SESSION['joinedexport'][$this->params['tableid'].'_category']);
    				}

    			case isset($this->params['btnForceRefreshReport']):
    				if (isset($this->params['class_name'])) {
    					$Report = ReportGenerator::getReportObject($this->params['class_name']);
    					$Report->process();
    					$Report->cacheCSV();
    					$Report->buildGraphFromSavedCSV();
    				}
    				$this->navigator->jumpToView();

    		}
    	}
    	$this->view->params = $_SESSION['joinedexport'];
    	$this->view->navigator = $this->navigator;
    }
    
    public function commenteditviewAction() {
    	// calls the normal action two classes up.
    	return DBControllerActionAbstract::editviewAction();
    }
    
    
    public function partlistviewAction()
    {
        $ReportData = new ReportDataPartListView();
        $PaginatedReportPage = new PaginatedReportPage($this->params,$ReportData,$this->navigator);
        if (isset($this->params['form'])) {
        	switch (true)
        	{
        		case isset($this->params['btnType']):
        			$return_url = $this->navigator->getCurrentHandlerUrl('btnDoneTryingToCreateNew','partlistview',null,array('typeversion_id' => $this->params['typeversion_id']));
        			$_SESSION['most_recent_new_typeversion_id'] = 'new';  // this should get changed by a successfull change
        			$this->navigator->setReturn($return_url)->CallView('editview',null,array('table' => 'typeversion','typeversion_id' => 'new', 'initialize' => array('effective_date' => time_to_mysqldatetime(script_time())), 'return_url' => $return_url, 'resetview' => 1));
        		case isset($this->params['btnDoneTryingToCreateNew']):
        			if (is_numeric($_SESSION['most_recent_new_typeversion_id'])) {
        				$this->navigator->jumpToView('itemdefinitionview','',array('typeversion_id' => $_SESSION['most_recent_new_typeversion_id']));
        			} else {
        				$this->navigator->jumpToView();
        			}
        		case isset($this->params['btnCopy']):
        			$TypeVersion = new DBTableRowTypeVersion();
        			if ($TypeVersion->getRecordById($this->params['typeversion_id'])) {
        				$this->navigator->CallView('editview',null,array('table' => 'typeversion','typeversion_id' => $TypeVersion->typeversion_id, 'initialize' => array('type_part_number' => $TypeVersion->type_part_number.'-COPY','typeobject_id' => 'new','effective_date' => time_to_mysqldatetime(script_time())), 'resetview' => 1, 'save_as_new' => 1));
        			}
        	}
            $PaginatedReportPage->sort_and_search_handler();
            
        }
        
        if (isset($this->params['search_string']) && $this->params['search_string']) {
        	$altSearchTargetUrl = $this->getAlternateSearchUrl($ReportData,'tv','tv__typeversion_id');
        	if ($altSearchTargetUrl) {
        		$BreadCrumbs = new BreadCrumbsManager();
        		$BreadCrumbs->newAnchor($this->navigator->getCurrentViewUrl('partlistview','struct',array('resetview' => 1,'search_string' => '')),'List of Definitions');
        		spawnurl($altSearchTargetUrl);
        	}
        }        
        
        
        
        $this->view->queryvars = $this->params;
        $this->view->paginated_report_page = $PaginatedReportPage;
        $this->view->navigator = $this->navigator;
    }  
    
    public function commentlistviewAction()
    {
    	$ReportData = new ReportDataCommentListView();
    	$PaginatedReportPage = new PaginatedReportPage($this->params,$ReportData,$this->navigator);
    	if (isset($this->params['form'])) {
    		switch (true)
    		{
    		}
    		$PaginatedReportPage->sort_and_search_handler();
    
    	}
    	
    	if (isset($this->params['search_string']) && $this->params['search_string']) {
    		$altSearchTargetUrl = $this->getAlternateSearchUrl($ReportData,'iv','cached_current_itemversion_id');
    		if ($altSearchTargetUrl) {
    			$BreadCrumbs = new BreadCrumbsManager();
    			$BreadCrumbs->newAnchor($this->navigator->getCurrentViewUrl('commentlistview','struct',array('resetview' => 1,'search_string' => '')),'List of Comments');
    			spawnurl($altSearchTargetUrl);
    		}
    	}    	
    
    	$this->view->queryvars = $this->params;
    	$this->view->paginated_report_page = $PaginatedReportPage;
    	$this->view->navigator = $this->navigator;
    }    
    
    public function changelistviewAction()
    {
    	$list_type = !isset($this->params['list_type']) || !in_array($this->params['list_type'], array_keys(ReportDataChangeLog::activityTypeOptions())) ? ReportDataChangeLog::activityTypeDefault() : $this->params['list_type'];
    	$ReportData = new ReportDataChangeLog($list_type);
    	$PaginatedReportPage = new PaginatedReportPage($this->params,$ReportData,$this->navigator);
    	if (isset($this->params['form'])) {
    		switch (true)
    		{
    		}
    		$PaginatedReportPage->sort_and_search_handler();
    
    	}
    	 
    	if (isset($this->params['search_string']) && $this->params['search_string']) {
    		$altSearchTargetUrl = $this->getAlternateSearchUrl($ReportData,'','');
    		if ($altSearchTargetUrl) {
    			spawnurl($altSearchTargetUrl);
    		}
    	}
    
    	$this->view->list_type = $list_type;
    	$this->view->queryvars = $this->params;
    	$this->view->paginated_report_page = $PaginatedReportPage;
    	$this->view->navigator = $this->navigator;
    }    
    
    public function watchlistviewAction()
    {
    	$ReportData = new ReportDataChangeSubscription();
    	$PaginatedReportPage = new PaginatedReportPage($this->params,$ReportData,$this->navigator);
    	
    	if (isset($this->params['form'])) {
    		switch (true)
    		{
    			case isset($this->params['btnDelete']):
    				$Rec = new DBTableRowChangeSubscription();
    				if ($Rec->getRecordById($this->params['changesubscription_id'])) {
    					$Rec->delete();
    				}
    				$this->navigator->jumpToView();
    			case isset($this->params['btnSetDailyNotifyTime']):
    				$_SESSION['account']->setPreference('followNotifyTimeHHMM', $this->params['followNotifyTimeHHMM']);
    				echo json_encode(array('sucess' => true));
    				die();
    				
    		}
    		$PaginatedReportPage->sort_and_search_handler();
    	
    	}

    	if (isset($this->params['search_string']) && $this->params['search_string']) {
    		$altSearchTargetUrl = $this->getAlternateSearchUrl($ReportData,'','');
    		if ($altSearchTargetUrl) {
    			spawnurl($altSearchTargetUrl);
    		}
    	}
    
    	$this->view->queryvars = $this->params;
    	$this->view->paginated_report_page = $PaginatedReportPage;
    	$this->view->navigator = $this->navigator;
    }
    
    /*
     * input: changesubscription_id, notify_instantly, notify_daily
    * output: json object['notify_instantly'] or ['notify_daily']
    */
    public function setsubscriptionnotifyAction() {
    	if (isset($this->params['changesubscription_id']) && is_numeric($this->params['changesubscription_id'])) {
    		$CS = new DBTableRowChangeSubscription();
    		if ($CS->getRecordById($this->params['changesubscription_id'])) {
    			if (isset($this->params['notify_instantly'])) {
    				$CS->notify_instantly = $this->params['notify_instantly'];
    				$CS->save(array('notify_instantly'));
    				echo json_encode(array('notify_instantly' => $CS->notify_instantly ? 1 : 0));
    				die();    				
    			} else if (isset($this->params['notify_daily'])) {
    				$CS->notify_daily = $this->params['notify_daily'];
    				$CS->save(array('notify_daily'));
    				echo json_encode(array('notify_daily' => $CS->notify_daily ? 1 : 0));
    				die();
    			}
    		}
    	}
    	echo json_encode(array('field' => 'nothing'));
    	die();
    }
    
    
    protected function edit_db_handler(DBTableRow $dbtable,$save_fieldnames) {
        $dbschema = DbSchema::getInstance();
        $edit_buffer = 'editing_'.$this->getBufferKey($dbtable);
        switch (true)
        {
        	// make a change to a record instead of adding a new version of the same one.
            case isset($this->params['btnChangePart']):
            case isset($this->params['btnSaveBeforeSubEdit']):
            	
                $errormsg = array();
                /* Note that we have to temporarily set itemversion_id = 'new' here to signal that
    			 * we are edit checking something that will create a new item version.  The hack is needed
    			 * because ->saveVersioned() need itemversion_id to be set to the record we are inheriting
    			 * the version from.
                 */
                $itemversion_hold = $dbtable->itemversion_id;
                $dbtable->itemversion_id = 'new';
                $dbtable->validateFields($save_fieldnames,$errormsg);
                $dbtable->itemversion_id = $itemversion_hold;
                $this->show_error_dialog_if_needed($errormsg,$dbtable,isset($this->params['btnChangePart']) ? 'btnChangePart' : 'btnSaveBeforeSubEdit');                
                /*
                   When calling the following, if we are a data terminal type user, we want to pass in the select user_id.  
                   But if we are a normal user, we want it to be the logged-in user.
                 */
                $dbtable->saveVersioned($_SESSION['account']->getRole()=='DataTerminal' ? $dbtable->user_id : $_SESSION['account']->user_id);   // we don't pass in the $save_fieldnames because a new version saves everything.
                // renumber all the sort keys if there is a dedicate sort key associated with this table
                if ($dbtable->hasDedicatedSortOrderField()) {
                    $dbtable->renumberAndSaveSortOrderFields();
                }
            
                // map fields back into session var for further handling
                foreach($dbtable->getFieldNames() as $fieldname) {
                    $_SESSION[$edit_buffer][$fieldname] = $dbtable->{$fieldname};
                }
                
                if (isset($this->params['btnChangePart'])) {
					$dbtable->startSelfTouchedTimer();
					$_SESSION[$edit_buffer]['form_result'] = 'btnChangePart';
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
                    // now that we've saved it, lets go back and try again.
                    $jump_params = $this->navigator->getPropagatingParamValues();
                    $jump_params['btnSubEditParams'] = http_build_query($sub_params);
                    $jump_params[$dbtable->getIndexName()] = $dbtable->getIndexValue();
                    $this->navigator->jumpToHandler('btnSubEditParams',null,null,$jump_params);
                }
            case ($this->params['btnOnChange'] == 'componentselectchange'):
                $sub_params = array();
                parse_str($this->params['onChangeParams'], $sub_params);
                if (!empty($sub_params['component_name'])) {
                	// reload the component identified by $sub_params['component_name']
                	$loaded_values = $dbtable->reloadComponent($sub_params['component_name']);
                	$_SESSION[$edit_buffer] = array_merge($_SESSION[$edit_buffer],$loaded_values);
                }
                $this->navigator->jumpToView();
        }
		
        // now check if a different button was pressed
        parent::edit_db_handler($dbtable,$save_fieldnames);
    }
    
    public function getBufferKey(TableRow $dbtable) {
    	return $dbtable->getTableName().$dbtable->typeversion_id;
    }
    
    
    /*
      this is the action for editing a record in the SESSION variable.
      override this to simply change the view and save handling
    */
    public function editviewdbAction() {
    	if (!isset($this->params['table']) || !isset($this->params['edit_buffer_key'])) {
    		$this->jumpToUsersLandingPage('The requested URL is not correct: Edit buffer not specified.');
    	}
        $edit_buffer = 'editing_'.$this->params['edit_buffer_key'];
        if (!isset($_SESSION[$edit_buffer])) {
        	$this->jumpToUsersLandingPage('Edit buffer timed out or browser in wrong state.');
        }
        $EditRow = DbSchema::getInstance()->dbTableRowObjectFactory($this->params['table'],false,$_SESSION[$edit_buffer]['parent_index']);
        if (!$EditRow->assignFromFormSubmission($this->params,$_SESSION[$edit_buffer])) {  
            $this->showBrowsingError();
        }        
    	$this->view->version_edit_mode = isset($_SESSION[$edit_buffer]['version_edit_mode']) ? $_SESSION[$edit_buffer]['version_edit_mode'] : 'vem_new_version';
        
		if (isset($this->params['form'])) {
			$this->edit_db_handler($EditRow,$EditRow->getSaveFieldNames());    
			if (is_a($EditRow,'DBTableRowTypeVersion')) {  //     $this->params['table']=='typeversion'
				switch (true)
				{
					case isset($this->params['btnOnChange']) && ($this->params['btnOnChange']=='deletealias'):
						$EditRow->deleteAlias();
						$_SESSION[$edit_buffer]['type_part_number'] = $EditRow->type_part_number;
						$_SESSION[$edit_buffer]['type_description'] = $EditRow->type_description;
						$this->navigator->jumpToView();
					case isset($this->params['btnOnChange']) && ($this->params['btnOnChange']=='addalias'):
						$EditRow->addAlias();
						$_SESSION[$edit_buffer]['type_part_number'] = $EditRow->type_part_number;
						$_SESSION[$edit_buffer]['type_description'] = $EditRow->type_description;
						$this->navigator->jumpToView();
				}
			}        
		}
        
        /*
         * If returning from editing a sub component, then there are some actions we need to perform,
         * like making sure the component value is set to the newly created component.
         */
        if (isset($this->params['subedit_return_param'])) {
        	list($previous_action,$edited_field,$edited_buffer) = explode(',',$this->params['subedit_return_param']);
        	$edited_buffer = 'editing_'.$edited_buffer;
        	if ('editsubcomponent'==$previous_action) {
        		if (('btnOK'==$_SESSION[$edited_buffer]['form_result']) || ('btnChangePart'==$_SESSION[$edited_buffer]['form_result'])) {
        			$EditRow->{$edited_field} = $_SESSION[$edited_buffer]['itemobject_id'];
        			$EditRow->ensureEffectiveDateValid();
        		}
        	}
        }
        
        $this->view->edit_action_button = 'btnOK';
		if (in_array($this->view->version_edit_mode,array('vem_finish_save_record','vem_edit_version'))) $this->view->edit_action_button = 'btnOK';
		if ('vem_new_version'==$this->view->version_edit_mode) $this->view->edit_action_button = 'btnChangePart';
        
        $this->view->dbtable = $EditRow;
        $this->view->navigator = $this->navigator;
        $this->view->edit_buffer_key = $this->params['edit_buffer_key'];
        $this->view->params = $this->params;
                
        if ($this->params['table']=='itemversion') {
        	$this->render('editview');
        } else if ($this->params['table']=='typeversion') {
        	$this->render('typeeditview');
        } else if ($this->params['table']=='comment') {
        	$this->render('commenteditview');
        }        
        
    }    

    /**
     * This is a shortcut version to get to itemview page.  (mylocalhost/sandbox/struct/iv/iv/4412)
     * @throws Exception
     */
    public function ivAction() {
    	if (isset($this->params['iv'])) {
    		$this->navigator->jumpToView('itemview','struct',array('itemversion_id' => $this->params['iv'],'resetview' => 1));
    	} else {
    		throw new Exception('parameter iv missing in StructController::ivAction()');
    	}	 
    }
    
    
    /**
     * This is a shortcut version to get to itemview page.  (mylocalhost/sandbox/struct/iv/iv/4412)
     * @throws Exception
     */
    public function ioAction() {
    	if (isset($this->params['io'])) {
    		$this->navigator->jumpToView('itemview','struct',array('itemobject_id' => $this->params['io'],'resetview' => 1));
    	} else {
    		throw new Exception('parameter io missing in StructController::ioAction()');
    	}
    }
    
    /**
     * This is a shortcut version to get to itemlistview and procedurelistview page.  (mylocalhost/sandbox/struct/lv/to/32 
     * optionally /struct/lv/to/32/mat/1 or 0).  We will automatically try to determine if this is a procedure
     * list or a part list and jump to the appropriate page.  Unfortunately, this slams the user preferences for the view.
     * The number is the typeobject_id for the listing you want to see.
     * @throws Exception
     */
    public function lvAction() {
    	if (isset($this->params['to'])) {
    		$TypeVersion = new DBTableRowTypeVersion();
    		if ($TypeVersion->getCurrentRecordByObjectId($this->params['to'])) {
    			if (DBTableRowTypeVersion::isTypeCategoryAProcedure($TypeVersion->typecategory_id)) {
    				$_SESSION['account']->setPreference('pref_proc_view_category', $this->params['to']);
    				$_SESSION['account']->setNumericFavoriteToRollingList('pref_proc_view_category_fav', $this->params['to']);
	    			$this->navigator->jumpToView('procedurelistview',null,array('resetview' => 1));
    			} else { // a part
    				if (isset($this->params['mat'])) {
    					$_SESSION['account']->setPreference('chkShowProcMatrix', $this->params['mat']);
    				}   
    				$_SESSION['account']->setPreference('pref_part_view_category', $this->params['to']);
    				$_SESSION['account']->setNumericFavoriteToRollingList('pref_part_view_category_fav', $this->params['to']);
    				$this->navigator->jumpToView('itemlistview',null,array('resetview' => 1));
    			}
    		} else {
    			throw new Exception('type object (to param) does not exist in StructController::lvAction()');
    		}
    	} else {
    		throw new Exception('parameter io missing in StructController::lvAction()');
    	}
    }
        
      
    public function itemviewAction() {
    	$ItemVersion = new DBTableRowItemVersion();
    	if (isset($this->params['itemversion_id'])) {
    		$ItemVersion->getRecordById($this->params['itemversion_id']);
    	} else if (isset($this->params['itemobject_id'])) {
    		$ItemVersion->getCurrentRecordByObjectId($this->params['itemobject_id']);
    	} else {
    		$this->jumpToUsersLandingPage('The requested URL is not correct: No item ID has been specified.');
    	}
    	
        // if we didn't actually succeed in locating the item, then go to the landing page.
    	if (!$ItemVersion->isSaved()) {
            $this->jumpToUsersLandingPage('Item not found.');
        }

        // handling for long pages
        
        $length_items = EventStream::getNestedEventStreamRecordCount($ItemVersion);
        $is_big_page =  $length_items > EventStream::tooBigRecordCount();
        $this->view->show_big_page_controls = isset($this->params['months']) && $is_big_page;   
        if ($is_big_page && !isset($this->params['months'])) {
        	$params = isset($this->params['itemversion_id']) ? array('itemversion_id' => $this->params['itemversion_id']) : array('itemobject_id' => $this->params['itemobject_id']);
        	$params['months'] = EventStream::getTooBigStartingNumMonths($ItemVersion, $length_items);
        	$this->navigator->jumpToView(null,null,$params);
        }      
    	
        if (isset($this->params['form'])) {
            switch (true)
            {
            	case ($this->params['btnOnChange'] == 'monthschange'):
            		$params = isset($this->params['itemversion_id']) ? array('itemversion_id' => $this->params['itemversion_id']) : array('itemobject_id' => $this->params['itemobject_id']);
            		$params['months'] = $this->params['months'];
            		$this->navigator->jumpToView(null,null,$params);
            	case isset($this->params['btnFollow']):
            		DBTableRowChangeSubscription::setFollowing($_SESSION['account']->user_id, $this->params['itemobject_id'], null, $this->params['notify_instantly'], $this->params['notify_daily']);
            		$_SESSION['account']->setPreference('followNotifyTimeHHMM', $this->params['followNotifyTimeHHMM']);
            		$_SESSION['account']->setPreference('followInstantly', $this->params['notify_instantly']);
            		$_SESSION['account']->setPreference('followDaily', $this->params['notify_daily']);
            		$this->navigator->jumpToView(null,null,array('itemobject_id' => $this->params['itemobject_id']));
            	case isset($this->params['btnUnFollow']):            		
            		DBTableRowChangeSubscription::clearFollowing($_SESSION['account']->user_id, $this->params['itemobject_id'], null);
            		$this->navigator->jumpToView(null,null,array('itemobject_id' => $this->params['itemobject_id']));
            		
            	case isset($this->params['btnSearch']):
            		if (isset($this->params['search_string']) && $this->params['search_string']) {
            			$this->navigator->jumpToView('itemlistview','struct',array('search_string' => $this->params['search_string']));
            		}
            		$this->navigator->jumpToView(null,null,array('itemversion_id' => $this->params['itemversion_id']));
            }
            
        }

        
        $earliest_date = null;
        if ($this->view->show_big_page_controls && is_numeric($this->params['months'])) {  // e.g.  months=ALL doesn't come here
        	$earliest_date = EventStream::getEarliestDateOfHistory($ItemVersion, $this->params['months']);
        } 
        $this->view->earliest_date = $earliest_date;        
        $this->view->streamrecords = EventStream::getNestedEventStreamRecords($ItemVersion,$earliest_date);
        $this->view->fieldhistory = EventStream::changeHistoryforFields($ItemVersion);
        
        $ItemVersion->startSelfTouchedTimer(); // the ideas is that we want to touch anything we view.
        $this->view->queryvars = $this->params;
        $this->view->dbtable = $ItemVersion;
    	$this->view->navigator = $this->navigator;
    }
    
    public static function renderItemViewPdf(DBTableRowItemVersion $dbtable, $queryvars=array()) {
    	$Pdf = new ItemViewPDF();
    	$Pdf->dbtable = $dbtable;
    	$Pdf->buildDocument($queryvars);
    	$Pdf->Output(make_filename_safe('ItemView_'.$dbtable->part_number.'_'.$dbtable->item_serial_number.'.pdf'),'D');
    	exit;
    }
    
    public function itemviewpdfAction() {
    	$ItemVersion = new DBTableRowItemVersion();
    	if (isset($this->params['itemversion_id'])) {
    		$ItemVersion->getRecordById($this->params['itemversion_id']);
    	} else if (isset($this->params['itemobject_id'])) {
    		$ItemVersion->getCurrentRecordByObjectId($this->params['itemobject_id']);
    	} else {
    		throw new Exception('itemversion_id or itemobject_id not specified in StructController::itemviewAction()');
    	}

    	self::renderItemViewPdf($ItemVersion, $this->params);
    }    
    
    /**
     * Directly output a QR code image given itemversion_id (or itemobject_id)
     * @throws Exception
     */
    public function qrcodeAction() {
    	$ItemVersion = new DBTableRowItemVersion();
    	if (isset($this->params['itemversion_id'])) {
    		$ItemVersion->getRecordById($this->params['itemversion_id']);
    	} else if (isset($this->params['itemobject_id'])) {
    		$ItemVersion->getCurrentRecordByObjectId($this->params['itemobject_id']);
    	} else {
    		throw new Exception('itemversion_id or itemobject_id not specified in StructController::itemviewAction()');
    	}
    	require_once('../library/phpqrcode/qrlib.php');
    	QRcode::png($ItemVersion->absoluteUrl());
    	die();
    }

    public function itemdefinitionviewAction() {
    	$TypeVersion = new DBTableRowTypeVersion();
    	if (isset($this->params['typeversion_id'])) {
    		if (!$TypeVersion->getRecordById($this->params['typeversion_id'])) {
    			$this->jumpToUsersLandingPage('The requested URL is not correct: typeversion_id is not known.');
    		}
    	} else if (isset($this->params['typeobject_id'])) {
    		if (!$TypeVersion->getCurrentRecordByObjectId($this->params['typeobject_id'])) {
    			$this->jumpToUsersLandingPage('The requested URL is not correct: typeobject_id is not known.');
    		}
    	} else {
    		$this->jumpToUsersLandingPage('The requested URL is not correct: No definition ID has been specified.');
    	}
    	
    	$ItemCounts = $TypeVersion->getItemInstanceCounts();
    	$TypeRefs = $TypeVersion->getTypeVersionInstancesWhereFieldIsASubField();
    	 
    	if (isset($this->params['form'])) {
    		switch (true)
    		{

    			// Handling for Add New Linked Procedure/Part
    			case isset($this->params['btnNewLinked']):
    				// $initialize and $typeversion_id are set as params.
    				$return_url = $this->navigator->getCurrentHandlerUrl('btnDoneTryingToCreateLinked','itemdefinitionview',null,array('typeversion_id' => $this->params['typeversion_id']));
    				$_SESSION['most_recent_new_typeversion_id'] = 'new';  // this should get changed by a successfull change
    				$this->navigator->setReturn($return_url)->CallView('editview',null,array('table' => 'typeversion','typeversion_id' => 'new', 'initialize' => $this->params['initialize'], 'return_url' => $return_url, 'resetview' => 1));
    			case isset($this->params['btnDoneTryingToCreateLinked']):
    				if (is_numeric($_SESSION['most_recent_new_typeversion_id'])) {
    					$this->navigator->jumpToView('itemdefinitionview','',array('typeversion_id' => $_SESSION['most_recent_new_typeversion_id']));
    				} else {
    					$this->navigator->jumpToView(null,null,array('typeversion_id' => $this->params['typeversion_id']));
    				}
    				
    			// Handling New versions of current type
    			case isset($this->params['btnNewVersion']):
    				$return_url = $this->navigator->getCurrentHandlerUrl('btnDoneTryingToCreateNew','itemdefinitionview',null,array('typeversion_id' => $this->params['typeversion_id']));
    				$_SESSION['most_recent_new_typeversion_id'] = 'new';  // this should get changed by a successfull change
    				$this->navigator->setReturn($return_url)->CallView('editview',null,array('table' => 'typeversion','typeversion_id' => $this->params['typeversion_id'], 'initialize' => array('effective_date' => time_to_mysqldatetime(script_time()), 'versionstatus' => 'D'), 'return_url' => $return_url, 'resetview' => 1));

    			case isset($this->params['btnDoneTryingToCreateNew']):
    				if (is_numeric($_SESSION['most_recent_new_typeversion_id'])) {
    					$this->navigator->jumpToView('itemdefinitionview','',array('typeversion_id' => $_SESSION['most_recent_new_typeversion_id']));
    				} else {
    					$this->navigator->jumpToView(null,null,array('typeversion_id' => $this->params['typeversion_id']));
    				}
    			case isset($this->params['btnRevertToDraft']):
    				$TypeVersion->versionstatus = 'D';
    				$TypeVersion->save(array('versionstatus'));
    				$this->navigator->jumpToView(null,null,array('typeversion_id' => $this->params['typeversion_id']));
    			case isset($this->params['btnReleaseVersion']):
    				$TypeVersion->versionstatus = 'A';
    				$TypeVersion->save(array('versionstatus'));
					DBTableRowChangeLog::releasedTypeVersion($TypeVersion->typeobject_id, $TypeVersion->typeversion_id);
    				$this->navigator->jumpToView(null,null,array('typeversion_id' => $this->params['typeversion_id']));
    			case isset($this->params['btnPreview']):
    				$return_url = $this->navigator->getCurrentHandlerUrl('',null,null,array('typeversion_id' => $this->params['typeversion_id']));
    				$initialize = array('typeversion_id' => $this->params['typeversion_id'], 'preview_definition_flag' => 1, 'effective_date' => time_to_mysqldatetime(script_time()));
    				$this->navigator->setReturn($return_url)->CallView('editview','',array('table' => 'itemversion', 'itemversion_id' => 'new', 'resetview' => 1, 'initialize' => $initialize));
    				 
    			case isset($this->params['btnDeleteVersion']):
    				if ($TypeVersion->allowedToDelete()) {
    					$typeobject_id = $TypeVersion->typeobject_id;
    					$TypeVersion->delete();
    					$TypeVersion = new DBTableRowTypeVersion();
    					if ($TypeVersion->getCurrentRecordByObjectId($typeobject_id)) {
    						$this->navigator->jumpToView(null,null,array('resetview' => 1,'typeobject_id' => $typeobject_id));
    					} else {
    						$this->navigator->jumpToView('partlistview',null,array('resetview' => 1));
    					}
    				}
    			case isset($this->params['btnReorgMoveComponent']):
    				$Form = new TableRowReorgMoveComponent();
    				$Form->typeversion_id = $TypeVersion->typeversion_id;
    				$Form->component_typeobject_id = '';
    				$_SESSION['reorgmovecomponent'] = $Form->getArray();    				
    				$this->navigator->jumpToView('reorgmovecomponent');
    			case isset($this->params['btnObsolete']):
    				$TypeObject = new DBTableRowTypeObject();
    				if ($TypeObject->getRecordById($this->params['typeobject_id'])) {
    					$TypeObject->typedisposition = 'B';
    					$TypeObject->save(array('typedisposition'));
						DBTableRowChangeLog::obsoletedTypeObject($TypeVersion->typeobject_id, $TypeVersion->typeversion_id);
    				}
    				$this->navigator->jumpToView(null,null,array('resetview' => 1,'typeobject_id' => $this->params['typeobject_id']));
    			case isset($this->params['btnMakeActive']):
    				$TypeObject = new DBTableRowTypeObject();
    				if ($TypeObject->getRecordById($this->params['typeobject_id'])) {
    					$TypeObject->typedisposition = 'A';
    					$TypeObject->save(array('typedisposition'));
    				}
    				$this->navigator->jumpToView(null,null,array('resetview' => 1,'typeobject_id' => $this->params['typeobject_id']));
    			case isset($this->params['btnUpgradeVersion']):
    				$count = DBTableRowTypeVersion::upgradeItemVersions($this->params['typeversion_id'], $this->params['to_typeversion_id']);
    				$_SESSION['msg'] = "Version Upgrade Complete.  {$count} items were upgraded from Type Version {$this->params['typeversion_id']} to Type Version {$this->params['to_typeversion_id']}.";
    				$this->navigator->jumpToView('itemdefinitionview',null,array('resetview' => 1,'typeversion_id' => $this->params['to_typeversion_id'],'msgi' => 1));
    				
    			case isset($this->params['btnFollow']):
    				DBTableRowChangeSubscription::setFollowing($_SESSION['account']->user_id, null, $this->params['typeobject_id'], $this->params['notify_instantly'], $this->params['notify_daily'], $this->params['follow_items_too']);
    				$_SESSION['account']->setPreference('followNotifyTimeHHMM', $this->params['followNotifyTimeHHMM']);
    				$_SESSION['account']->setPreference('followInstantly', $this->params['notify_instantly']);
    				$_SESSION['account']->setPreference('followDaily', $this->params['notify_daily']);
    				$_SESSION['account']->setPreference('followItemsToo', $this->params['follow_items_too']);
    				$this->navigator->jumpToView(null,null,array('typeobject_id' => $this->params['typeobject_id']));
    			case isset($this->params['btnUnFollow']):
    				DBTableRowChangeSubscription::clearFollowing($_SESSION['account']->user_id, null, $this->params['typeobject_id']);
    				$this->navigator->jumpToView(null,null,array('typeobject_id' => $this->params['typeobject_id']));

    			case isset($this->params['btnSearch']):
    				if (isset($this->params['search_string']) && $this->params['search_string']) {
    					$this->navigator->jumpToView('itemlistview','struct',array('search_string' => $this->params['search_string']));
    				}
    				$this->navigator->jumpToView(null,null,array('typeversion_id' => $this->params['typeversion_id']));

    		}
    	}

    	$TypeVersion->startSelfTouchedTimer(); // the ideas is that we want to touch anything we view.
    	$this->view->queryvars = $this->params;
    	$this->view->dbtable = $TypeVersion;
    	$this->view->navigator = $this->navigator;
    	$this->view->itemcounts = $ItemCounts; 
    	$this->view->typerefs = $TypeRefs;
    }
    
    public function reorgmovecomponentAction() {
    	$Form = new TableRowReorgMoveComponent();
    	if (!$Form->assignFromFormSubmission($this->params,$_SESSION['reorgmovecomponent'])) {
    		$this->showBrowsingError();
    	}    	

    	switch (true) {
    		case isset($this->params['btnOK']):
    			$count = $Form->moveComponents();
    			$_SESSION['msg'] = 'Move operation complete.  '.$count.' items updated.';
    			$this->navigator->jumpToView('itemdefinitionview',null,array('typeversion_id' => $Form->typeversion_id, 'msgi' => 1));
    		case isset($this->params['btnCancel']):
    			$this->navigator->jumpToView('itemdefinitionview',null,array('typeversion_id' => $Form->typeversion_id));
    	}
    	 
    	$this->view->navigator = $this->navigator;
    	$this->view->formtable = $Form;
    }
    
    
    public static function renderItemDefinitionViewPdf($typeversion_id, $queryvars=array()) {
    	$Pdf = new ItemDefinitionViewPDF();
    	$Pdf->buildTypeDocument($typeversion_id, $queryvars);
    	
    	// probably a more efficient way, but lets get the typeobject_id
    	$TypeVersion = new DBTableRowTypeVersion();
    	$TypeVersion->getRecordById($typeversion_id);
    	$Pdf->Output(make_filename_safe('Definition_'.DBTableRowTypeVersion::formatPartNumberDescription($TypeVersion->type_part_number,$TypeVersion->type_description).'.pdf'),'D');
    	exit;
    }
    
    public function itemdefinitionviewpdfAction() {
    	if (!isset($this->params['typeversion_id'])) {
    		throw new Exception('typeversion_id or typeobject_id not specified in StructController::itemdefinitionviewpdfAction()');
    	}
    	self::renderItemDefinitionViewPdf($this->params['typeversion_id'], $this->params);
    }
    

    /**
     * This is a shortcut version to get to itemdefinitionview page.  (mylocalhost/sandbox/struct/tv/tv/44)
     * @throws Exception
     */
    public function tvAction() {
    	if (isset($this->params['tv'])) {
    		$this->navigator->jumpToView('itemdefinitionview','struct',array('typeversion_id' => $this->params['tv'],'resetview' => 1));
    	} else {
    		throw new Exception('parameter tv missing in StructController::tvAction()');
    	}
    }    
    
    /**
     * This is a shortcut version to get to itemdefinitionview page.  Or alternately a call to create a new itemobject of this type
     * (mylocalhost/sandbox/struct/to/44 or mylocalhost/sandbox/struct/to/44/new)
     * @throws Exception
     */
    public function toAction() {
    	if (isset($this->params['to'])) {
    		$TypeObject = new DBTableRowTypeObject();
    		$TypeObject->getRecordById($this->params['to']);
    		if (isset($this->params['link_action']) && ($this->params['link_action']=='new')) {
    			$return_url = $this->navigator->getCurrentHandlerUrl('btnDoneTryingToCreateNew','itemlistview',null,array('typeversion_id' => $this->params['typeversion_id']));
    			$_SESSION['most_recent_new_itemversion_id'] = 'new';  // this should get changed by a successfull change
    			$this->navigator->setReturn($return_url)->CallView('editview','',array('table' => 'itemversion', 'itemversion_id' => 'new', 'resetview' => 1, 'initialize' => array('typeversion_id' => $TypeObject->cached_current_typeversion_id)));
    		} else {
	    		$this->navigator->jumpToView('itemdefinitionview','struct',array('typeversion_id' => $TypeObject->cached_current_typeversion_id,'resetview' => 1));
    		}
    	} else {
    		throw new Exception('parameter to missing in StructController::toAction()');
    	}
    }      
    
    /*
     * input: typeversion_id
     * output: json object['next_serial_number']
     */
    public function nextserialnumberAction() {
    	if (isset($this->params['typeversion_id']) && is_numeric($this->params['typeversion_id'])) {
    		$TypeVersion = DbSchema::getInstance()->dbTableRowObjectFactory('typeversion');
    		if ($TypeVersion->getRecordById($this->params['typeversion_id'])) {
    			echo json_encode(array('next_serial_number' => $TypeVersion->nextSerialNumber()));
    			die();
    		}
    	}
    	echo json_encode(array('field' => 'nothing'));
    	die();
    }
    
    public function getdatetimenowAction() {
    	echo json_encode(array('datetimenow' => date("m/d/Y H:i",script_time())));
    	die();
    }    
    
    public function tipsokigotitAction() {   	 
    	if (isset($this->params['key'])) {
    		if (DBTableRowWhatsNewUser::clearWhatsNew($this->params['key'])) {
    			echo json_encode(array('ok' => 1));
    			die();
    		}
    	}
    	echo json_encode(array('ok' => 0));
    	die();
    }   
    
    /*
     * Note: This will add a comment to the specified itemobject_id or typeobject_id 
     */
    public function addcommentitemAction() {
    	// how do we know we are really logged in here.
    	if (isset($this->params['itemobject_id']) && is_numeric($this->params['itemobject_id']) && $this->params['comment_text']) {
    		$Comment = DbSchema::getInstance()->dbTableRowObjectFactory('comment');
    		$Comment->itemobject_id = $this->params['itemobject_id'];
    		$Comment->comment_text = $this->params['comment_text'];
    		$Comment->save();
    	} else if (isset($this->params['typeobject_id']) && is_numeric($this->params['typeobject_id']) && $this->params['comment_text']) {
    		$Comment = DbSchema::getInstance()->dbTableRowObjectFactory('typecomment');
    		$Comment->typeobject_id = $this->params['typeobject_id'];
    		$Comment->comment_text = $this->params['comment_text'];
    		$Comment->save();
    	}
    	echo json_encode(array('comment_id' => $Comment->comment_id));
    	die();
    }
    
    public function jsonAction() {
    	echo json_encode(
    		array(
    			array("vacuum_sleeve_serial_number","clt_filter_type"),
    			array("impedance_type"),
    			)
    	);
    	die();
    }

    
    public function json2Action() {
        	$out = array();
        	
        	$out['vacuum_sleeve_serial_number'] = array('type' => 'varchar', 'len' => '32', 'caption' => 'Vacuum Sleeve Ser#');
        	$out['clt_filter_type'] = array('type' => 'enum', 'options' => array('Course' => 'Course (green sticker)', 'Normal' => 'Normal'));
        	$out['impedance_type'] = array('type' => 'enum', 'options' => array('Bent' => 'Bent', 'Normal' => 'Normal'));
      	
    	echo json_encode($out);
    	die();
    }
    
    public function jsonarchivechangesAction() {
    	$ItemVersion = new DBTableRowItemVersion();
    	$ItemVersion->getRecordById($this->params['itemversion_id']);
    	$ItemVersion->_navigator = $this->navigator;
    	$out = $ItemVersion->getArchiveEditChangesArray();
    	echo json_encode($out);
    	die();
    }
    
	/**
	 * Returns a json formated array of type descriptions keyed by typeobject_id.  
	 * It returns the description from the current version of the type.
	 */
    public function jsonlistoftypedescriptionsAction() {
    	$wheresql = (isset($this->params['typecategory_id'])) ? " WHERE typeversion.typecategory_id='".addslashes($this->params['typecategory_id'])."' " : "";
    	$typerecords = DbSchema::getInstance()->getRecords('typeobject_id',"SELECT typeversion.* FROM typeobject LEFT JOIN typeversion ON typeversion.typeversion_id=typeobject.cached_current_typeversion_id {$wheresql} ORDER BY typeversion.type_part_number, typeversion.effective_date");
	    $out = array();
	    foreach($typerecords as $typeobject_id => $typerecord) {
	    	if ($typeobject_id) {
	    		$out[] = array($typeobject_id,DBTableRowTypeVersion::formatPartNumberDescription($typerecord['type_part_number'],$typerecord['type_description']));
	    	}
	    }
	    echo json_encode($out);
	    die();
    }
    
    public function jsonlistofobjectfieldsAction() {
    	$TypeVersion = new DBTableRowTypeVersion();
    	$out = array();
    	if (isset($this->params['typeobject_id'])) {
	    	if (is_numeric($this->params['typeobject_id']) && $TypeVersion->getCurrentRecordByObjectId($this->params['typeobject_id'])) {
	    		$out = $TypeVersion->getFieldsAllowsAsSubFields();
	    	} else if (is_array($this->params['typeobject_id'])) {
	    		foreach($this->params['typeobject_id'] as $typeobject_id) {
	    			if (is_numeric($typeobject_id) && $TypeVersion->getCurrentRecordByObjectId($typeobject_id)) {
	    				$out[$typeobject_id] = $TypeVersion->getFieldsAllowsAsSubFields();
	    			}
	    		}
	    	}
    	}
    	echo json_encode($out);
    	die();
    }    

    public function testeruploadAction() {
    	
    }
    
    public function documentsajaxAction() {
    	$EditRow = DbSchema::getInstance()->dbTableRowObjectFactory('comment',false,'itemobject_id');
    	$EditRow->assign($_SESSION['editing_comment']);
    	$upload_handler = new MyUploadHandler($EditRow);
    	$_SESSION['editing_comment']['document_ids'] = $EditRow->document_ids;
    	die();
    }
    
    public function importobjectsfromcsvAction() {
    	$ImportStrategy = new ImportStrategyObjects();
    	if (isset($this->params['form'])) {
    		switch (true)
    		{
    			case isset($this->params['btnUpload']):
    				$ERRORTEXT = array(UPLOAD_ERR_INI_SIZE => "File is larger than ".ini_get('upload_max_filesize').".",
    				UPLOAD_ERR_FORM_SIZE => "File is larger than ".Zend_Registry::get('config')->max_file_upload_size.".",
    				UPLOAD_ERR_PARTIAL => "File was only partially uploaded.",
    				UPLOAD_ERR_NO_FILE => "No file was uploaded.");
    				if ($_FILES['pcfile']['error']) {
    					showdialog('Error Importing File', "{$ERRORTEXT[$_FILES['pcfile']['error']]}  Please press the Back button.",
    					array('<== Back' => $this->navigator->getCurrentViewUrl()));
    				} else if ($_FILES['pcfile']['size']==0) {
    					showdialog('File is Empty', "Please press the Back button.",
    					array('<== Back' => $this->navigator->getCurrentViewUrl()));
    				}
    	
    				$update_log = $ImportStrategy->importCSVFile($_FILES['pcfile']['tmp_name'],$this->params['use_tabs'] ? "\t" : ",");
    				$_SESSION['importobjectsconfirm'] = array();
    				$_SESSION['importobjectsconfirm']['records'] = $ImportStrategy->getImportedRecords();
    				$this->navigator->jumpToView('importobjectsconfirm');
    	
    			case isset($this->params['btnCancel']):
    			case isset($this->params['btnDone']):
    				$this->navigator->returnFromCall();
    		}
    	}
    	$this->view->import_object = $ImportStrategy;
    }
    
    public function importobjectsconfirmAction() {
    	if (!isset($_SESSION['importobjectsconfirm']['records']) || !is_array($_SESSION['importobjectsconfirm']['records'])) {
    		throw new Exception("Records array not initialized in importobjectsconfirmAction().");
    	}
    	if (!is_array($_SESSION['importobjectsconfirm']['column_defs'])) {
    		$_SESSION['importobjectsconfirm']['column_defs'] = array();
    	}
    	$ImportRecords = $_SESSION['importobjectsconfirm']['records'];
    	$ColumnDefs = $_SESSION['importobjectsconfirm']['column_defs'];
    	if (isset($this->params['form'])) {
    		switch (true)
    		{
    			case isset($this->params['btnOk']):
    				$outmessages = ImportStrategyObjects::storeObjectsFromArray($_SESSION['importobjectsconfirm'], false);
    				$this->navigator->returnFromCall();
    			case isset($this->params['btnChooseTypeVersion']):
    				$_SESSION['importobjectsconfirm']['typeversion_id'] = $this->params['typeversion_id'];
    				$this->navigator->jumpToView();
    			case isset($this->params['btnSelectColumn']):
    				$_SESSION['importobjectsconfirm']['column_defs'][$this->params['col_import_label']] = $this->params['col_fieldname_val'];
    				$this->navigator->jumpToView();
    			case isset($this->params['btnCancel']):
    			case isset($this->params['btnDone']):
    				$this->navigator->returnFromCall();
    		}
    	}
    	 
    	$this->view->import_messages = ImportStrategyObjects::storeObjectsFromArray($_SESSION['importobjectsconfirm'], true);
    	$this->view->import_records = $ImportRecords;
    	$this->view->column_defs = $ColumnDefs;
    	$this->view->navigator = $this->navigator;
    }
    
    public function reportgenerateAction() {
    	if (isset($this->params['class_name'])) {
    		$Report = ReportGenerator::getReportObject($this->params['class_name']);
    		$Report->outputCachedCSVToBrowser();
    	}
     }
     
     /**
      * Standard delete type action that also handles special checks for deleting itemversion records.
      * @see DBControllerActionAbstract::deleteAction()
      */
     public function deleteAction() {
     	
     	// special instructions for deleting itemversion.
     	if ($this->params['table']=='itemversion') {
     		$return_to_url = isset($this->params['return_url']) && $this->params['return_url'] ? $this->params['return_url'] : $this->navigator->getCallingUrl();
     		$get_vars = $this->getRequest()->getQuery();
     		$Dbschema = DbSchema::getInstance();
     		// get the index name for this table
     		$EditRow = $Dbschema->dbTableRowObjectFactory($this->params['table']);
     		if (isset($get_vars[$EditRow->getIndexName()])) {
     			if ($EditRow->getRecordById($get_vars[$EditRow->getIndexName()])) {
     				$msgs = array();
     				$iv_records = DbSchema::getInstance()->getRecords('',"SELECT * FROM itemversion where itemobject_id='{$EditRow->itemobject_id}'");
     				/*
     				 * if there is only 1 itemversion, then we should assume that we are about to completely delete this itemobject
     				 * and so we should do some extra checks.
     				 */
     				if (count($iv_records)==1) {
     					$ref_records = DbSchema::getInstance()->getRecords('',"SELECT * FROM itemcomponent where has_an_itemobject_id='{$EditRow->itemobject_id}'");
     					if (count($ref_records)>0) $msgs[] = 'You cannot delete this if there are other parts or procedures that reference it.  You must delete them first.';
     					$com_records = DbSchema::getInstance()->getRecords('',"SELECT * FROM comment where itemobject_id='{$EditRow->itemobject_id}'");
     					if (count($com_records)>0) $msgs[] = 'You cannot delete this if there are comments.  You must delete them first.';
     				}
     				if (count($msgs)>0) {
     					$EditRow->startSelfTouchedTimer();
     					if (isset($this->params['return_url_failed'])) $return_to_url = $this->params['return_url_failed'];
     					showdialog('Cannot Delete', implode(' ',$msgs), array('<== Back' => $return_to_url));
     				} else {
     					$EditRow->delete();
     				}
     			}
     		}
     		spawnurl($return_to_url);
     	} else {
     		parent::deleteAction();
     	}
    }     
    
   
}

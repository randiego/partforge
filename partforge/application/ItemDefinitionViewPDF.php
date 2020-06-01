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

class ItemDefinitionViewPDF extends ItemViewPDF {
	
	public $TypeVersion;
	protected $_linked_to_part_number_text = '';

	protected function formatDictionaryData($fieldname) {
		return $this->TypeVersion->fetchLayoutFieldParamsHtml($fieldname, true);
	}
	
	protected function getLinkedTypeRecordsByObjectId($is_procedure_flag) {
		$procedure_records = getTypesThatReferenceThisType($this->dbtable->typeversion_id,$is_procedure_flag);
		
		$procedure_records_by_typeobject_id = array();
		foreach($procedure_records as $record) {
			$procedure_records_by_typeobject_id[$record['typeobject_id']] = $record;
		}		
		return $procedure_records_by_typeobject_id;
	}
	
	/**
	 * outputs the list of linked procedure defintions
	 * @param int $is_procedure_flag is 1 show only procedures, or 0 show only Parts
	 * @param string $section_heading
	 */
	protected function outputLinkedDefinitions($is_procedure_flag=1, $section_heading='Linked Procedure Definitions') {
		
		$procedure_records_by_typeobject_id = $this->getLinkedTypeRecordsByObjectId($is_procedure_flag);
		
		if (count($procedure_records_by_typeobject_id)>0) {
			$this->Ln(6);
			$this->WriteHTML('<h2><i>'.$section_heading.'</i></h2>',true,false,true,true);
			$this->Ln(3);
			$items_html = '';
			foreach($procedure_records_by_typeobject_id as $record) {
				$SubTypeVersion = new DBTableRowTypeVersion();
				$SubTypeVersion->getRecordById($record['typeversion_id']);
				$items_html .= '<li>'.TextToHtml(DBTableRowTypeVersion::formatPartNumberDescription($record['type_part_number'],$record['type_description'])).'<br /><i>'.$SubTypeVersion->absoluteUrl().'</i></li>';
		
			}
			$this->WriteHTML('<ul>'.$items_html.'</ul>',true,false,true,true);
		}		
	}
	
	protected function renderTypeCommentsChangesTable($lines) {
		$layout_rows_comments_changes = DefinitionEventStream::renderEventStreamHtmlForPdfFromLines($lines);
		if (count($layout_rows_comments_changes)>0) {
			$this->Ln(10);
			$this->sectionHeader('Comments and Changes');
			$this->SetFont($this->_myfont,'',10);
			$html = '';
			$html .= '<table border="0.5" cellpadding="4" cellspacing="0"><thead><tr style="background-color:#EEE; font-weight:bold;"><td width="113" align="center">User / Date</td><td width="443" align="center" span="2">Comment</td></tr></thead>';
			foreach($layout_rows_comments_changes as $layout_rows_comments_change) {
				foreach($layout_rows_comments_change as $key => $value) {
					$layout_rows_comments_change[$key] = $this->clean_html($value);
				}
				$html .= '<tr><td width="113">'.$layout_rows_comments_change[0].'</td><td width="443" colspan="2">'.$layout_rows_comments_change[1].'</td></tr>';
			}
			$html .= '</table>';
			$this->WriteHTML($html);
		}
	}	
	
	function Footer() {
		// I have to set the font here so that getAliasNbPages() properly knowns what type of font we are using
		$this->SetFont($this->_myfont,'I',10);
		$maxwidth = 90;
		$required_text = 'Definition: '.TextToHtml($this->TypeVersion->type_part_number);
		$optional_text = $this->_linked_to_part_number_text ? ' ('.TextToHtml($this->_linked_to_part_number_text).')' : '';
		$this->outputFooter(strlen($required_text.$optional_text) > $maxwidth ? $required_text : $required_text.$optional_text, 'Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages());
		$this->SetFont($this->_myfont,'',10);
	}	
	
	/**
	 * Outputs all the pages of the definition given by $typeversion_id.  If $linked_to_part_number_text is specified, it will
	 * indicate in the footer, for example, that this is linked to the part number
	 * @param int $typeversion_id
	 * @param plain text $linked_to_part_number_text
	 */
	protected function addDefinitionToDocument($typeversion_id,$linked_to_part_number_text='') {

		/* 
		 * we need to add the page first here in case this is a sub definition and there is already content rendered with footers
		 * approproate to the previous part.  AddPage() only calls the footers if there is stuff generated on the page already.
		 */		
		$this->AddPage();
		

		$this->_linked_to_part_number_text = $linked_to_part_number_text;
		
		/*
		 * yes, it's strange, but because of the reuse of ItemViewPDF we need an instance of DBTableRowItemVersion.
		*/
		$this->dbtable = new DBTableRowItemVersion();
		$this->dbtable->typeversion_id = $typeversion_id;
		$this->dbtable->setFieldTypeForRecordLocator();
		 
		$this->TypeVersion = new DBTableRowTypeVersion();
		$this->TypeVersion->getRecordById($this->dbtable->typeversion_id);
		
		$title = 'Definition: '.DBTableRowTypeVersion::formatPartNumberDescription($this->TypeVersion->type_part_number,$this->TypeVersion->type_description);
		
		// set document information.  only want to do this if we are not a sub definition
		if (!$linked_to_part_number_text) {
			$this->SetAuthor('PartForge / '.DBTableRowUser::getFullName($this->TypeVersion->user_id));
			$this->SetTitle($title);
		}
		
		$this->config = Zend_Registry::get('config');
		
		
		$header_html = $this->TypeVersion->fetchFullDefinitionSheetHeader(true);
		
		$this->SetFont($this->_myfont,'',10);
		
		// using table as it is only way to get the padding right.
		$out = '<table border="0.5" cellpadding="5" cellspacing="0"><tr>
				<td><div><h2>'.TextToHtml($title).'</h2><br />'.$header_html.'</div></td>
						</tr></table>';
		$this->setHtmlVSpace(array('h2' => array(0 => array('h'=>0,'n' => 0), 1 => array('h'=>0,'n' => 0)))); // makes paragraph spacing more sane		
		$this->WriteHTML($out,true,false,false,false);		
		
		
		
		// start the form layout
		
		$fields_to_remove = array();
		$fieldlayout = $this->dbtable->getEditViewFieldLayout($this->dbtable->getEditFieldNames(array('')),$fields_to_remove,'itemview');
		
		$this->WriteHTML('<h2><i>Form Layout</i></h2><br />',true,false,true,true);
		
		if (Zend_Registry::get('config')->warn_of_hidden_fields) {
			$hidden = $this->TypeVersion->getHiddenFieldnames();
			if (count($hidden)>0) {
				$enum = count($hidden)==1 ? 'is one defined field' : 'are '.count($hidden).' defined fields';
				$msg = '<h3 style="color:#F00;">! There '.$enum.' not in the layout (<i>'.implode(', ',$hidden).'</i>).  Such fields should be removed from the definition or added to the layout.<br /></h3>';
				$this->WriteHTML($msg,true,false,false,false,'');
			}		
		}
		
		$this->itemViewFieldOutput($fieldlayout, '', 'formatDictionaryData');
		
		$this->outputLinkedDefinitions(1, 'Linked Procedure Definitions');
		
		$this->outputLinkedDefinitions(0, 'Linked Part Definitions');
		
		$DefinitionEventStream = new DefinitionEventStream($this->TypeVersion->typeobject_id);
		$lines = DefinitionEventStream::eventStreamRecordsToLines($DefinitionEventStream->assembleStreamArray(), $this->TypeVersion);
		
		$this->renderTypeCommentsChangesTable($lines);
		
	}

    public function buildTypeDocument($typeversion_id, $queryvars) {
    	$show_linked_procedures = isset($queryvars['show_linked_procedures']) ? $queryvars['show_linked_procedures'] : false;
    	$this->initializeDocument();
    	$this->setListIndentWidth(4.0);
    	
    	$this->addDefinitionToDocument($typeversion_id);
    	$top_part_number = $this->TypeVersion->type_part_number;
    	if ($show_linked_procedures) {
    		$procedure_records_by_typeobject_id = $this->getLinkedTypeRecordsByObjectId(1);
    		if (count($procedure_records_by_typeobject_id)>0) {
    			foreach($procedure_records_by_typeobject_id as $record) {
    				$this->addDefinitionToDocument($record['typeversion_id'],'procedure of '.$top_part_number);
    			}
    		}    		
    	}
    }

}

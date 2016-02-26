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

// freesans=161K , dejavusans=198K, helvetica=12K (no sym), dejavusanscondensed=149, dejavusansextralight=56K (missing stuff)
define ('PDF_FONT_NAME_MAIN', 'freesans');

require_once('tcpdf/tcpdf.php');

class ItemViewPDF extends TCPDF {

	public $dbtable;

	public $show_form_fields = true;
	public $show_text_fields = true;
	public $show_procedure_tables = false;
	public $show_event_stream = true;
	public $show_procedures_in_event_stream = true;

	public $config;
	protected $_w1; // left column width
	protected $_w2; // right column width
	protected $_h1; // single row table height
	protected $_h2; // text spacing
	protected $_myfont;
	protected $_current_header;
	
	// http://tidy.sourceforge.net/docs/quickref.html
	protected $_tidy_options = array (
				'clean' => 1,
				'drop-empty-paras' => 1,
				'drop-proprietary-attributes' => 1,
				'fix-backslash' => 1,
				'hide-comments' => 1,
				'join-styles' => 1,
				'lower-literals' => 1,
				'merge-divs' => 1,
				'merge-spans' => 1,
				'word-2000' => 1,
				'wrap' => 0,
				'output-bom' => 0,
				'show-body-only' => 1,
				'char-encoding' => 'utf8',
			);

	public function __construct() {
		parent::__construct('P', 'mm', 'LETTER');
		$this->_myfont = PDF_FONT_NAME_MAIN;  
		$this->setFontSubsetting(true);
		$this->SetFont($this->_myfont,'I',10);
	}


	public function Error($msg)
	{
		throw new Exception($msg);
	}

	protected function translateUtf8ToIso($txt) {
		$txt_trans ='';
		$len = mb_strlen($txt,'UTF-8');
		for($i = 0; $i < $len; $i ++) {
			$o = iconv("UTF-8", "ISO-8859-1//TRANSLIT//IGNORE", mb_substr($txt,$i,1,'UTF-8'));
			$txt_trans .= $o=='' ? '['.substr(htmlentities(mb_substr($txt,$i,1,'UTF-8'), ENT_NOQUOTES, "utf-8"),1,-1).']' : $o;
		}
		return $txt_trans;
	}

	public function MyCell($w, $h=0, $txt='', $border=0, $ln=0, $align='', $fill=false, $link='') {
		return parent::Cell($w, $h,  $this->translateUtf8ToIso($txt), $border, $ln, $align, $fill, $link);
	}

	public function MyMultiCell($w, $h, $html, $border=0, $align='L', $fill=false) {
		$html = tidy_repair_string($html,$this->_tidy_options);
		return parent::writeHTMLCell($w, $h, $x, $y, $html, $border, 1, $fill, true, $align, true);
	}

	public function sectionHeader($title) {
		$this->Ln(4);
		$this->appCheckPageBreak(6);
		$this->SetFont($this->_myfont,'B',12);
		$this->MyCell(0,6,$title,0,1,'L');
		$this->Ln(2);
	}

	protected function appCheckPageBreak($h)
	{
		//If the height h would cause an overflow, add a new page immediately
		if($this->GetY()+$h>$this->PageBreakTrigger)
			$this->AddPage($this->CurOrientation);
	}

	protected function tableOutput($header,$data,$w) {
		$html = '';
		$html .= '<table border="0.5" cellpadding="4" cellspacing="0"><thead><tr style="background-color:#EEE; font-weight:bold;">';
		foreach($w as $i => $colwidth) {
			$html .= '<td width="'.$colwidth*2.84.'" align="center">'.$header[$i].'</td>';
		}
		$html .= '</tr></thead>';

		foreach($data as $row) {
			$html .= '<tr nobr="true">';
			foreach($w as $i => $colwidth) {
				$html .= '<td width="'.$colwidth*2.84.'">'.$row[$i].'</td>';
			}
			$html .= '</tr>';
		}
		$html .= '</table>';
		$this->SetFont($this->_myfont,'',10);
		$html = tidy_repair_string($html,$this->_tidy_options);
		$this->writeHTML($html);
	}

	function Header()
	{
		$this->SetXY(3,3);
		$this->SetFont($this->_myfont,'I',10);
		$this->SetTextColor(100);
		$this->MyCell(50,$this->_h2,$this->_current_header);
		$this->SetTextColor(0);
		$this->SetFont($this->_myfont,'',10);
		$this->Ln(10);
	}
	
	protected function outputFooter($left,$right) {
		$this->SetXY(3,-12);
		$this->SetTextColor(100);
		$this->MyCell(0,10,$left);
		
		$this->SetXY(3,-12);
		$this->MyCell(0,10,$this->getAliasRightShift().$right,0,0,'R');
		$this->SetTextColor(0);
	}


	function Footer() {
		// I have to set the font here so that getAliasNbPages() properly knowns what type of font we are using.
		$this->SetFont($this->_myfont,'I',10);
		$this->outputFooter($this->dbtable->getPageTypeTitleHtml().($this->dbtable->item_serial_number ? ' - '.TextToHtml($this->dbtable->item_serial_number) : ''), 'Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages());
		$this->SetFont($this->_myfont,'',10);
	}
	
	/**
	 * Convert to src="../types/documents/nnn"  to src="//mylocalhost/sandbox/types/documents/nnn"
	 * 
	 * @param html string $html
	 * @return html string
	 */
    function convertIMGsToAbsoluteUrls($html) {

    	$match = preg_match_all('#\<IMG(.+?)src=\"\.\./types/documents/([0-9]+?)\"(.+?)\>#ims',$html,$out);
    	// $out[0] has the whole img tag.
    	
    	foreach($out[2] as $sub_index => $rhs) {
			$typedocument_id = $out[2][$sub_index];
			$size_attrib = $out[3][$sub_index];   // contain width="nnn" and possibly height="nnn"
			$match = preg_match_all('#width=\"(.+?)\"#ims',$size_attrib,$width_out);
			// a resolution of 1.5 x requested width turns out to be a good balance.
			$width_params = count($width_out[1])==1 ? '?fmt=customwidth&width='.(1.5*$width_out[1][0]) : '';
			
    		$replace = '<img'.$out[1][$sub_index].'src="//'.Zend_Controller_Front::getInstance()->getRequest()->getHttpHost().Zend_Controller_Front::getInstance()->getRequest()->getBaseUrl().'/types/documents/'.$typedocument_id.$width_params.'"'.$size_attrib.'>';
    		$html = str_ireplace($out[0][$sub_index],$replace,$html);
    	}    	
    	return $html;    	
    }

	protected function itemViewFieldOutput($fieldlayout, $optionss='', $definitionCallBackFunction=null) {
		$errormsg = array();
		$this->dbtable->validateFields($this->dbtable->getSaveFieldNames(),$errormsg);
		$this->dbtable->applyDictionaryOverridesToFieldTypes();
		$this->dbtable->applyCategoryDependentHeaderCaptions(false);
		$html = '';
		$html .= '<table border="0.0" cellpadding="4" cellspacing="0">';
		foreach($fieldlayout as $row) {
			if (!is_array($row)) {
				throw new Exception('itemViewFieldOutput(): layout row is not an array.');
			}

			if (!isset($row['type'])) {
				throw new Exception('itemViewFieldOutput(): row type is not defined.');
			}


			if ($row['type']=='columns') {
				$out = array();
				$html .= '<tr nobr="true">';
				$is_single_column = (count($row['columns'])==1);
				foreach($row['columns'] as $field_index => $field) {

					$fieldname = $field['name'];
					if (isset($field['field_attributes'])) {
						foreach($field['field_attributes'] as $key => $attribute) {
							$this->dbtable->setFieldAttribute($fieldname,$key,$attribute);
						}
					}
					// set any add-on display options, e.g. UseRadiosForMultiSelect
					$options = extractFieldOptions($optionss,$fieldname);
					if (isset($field['display_options'])) {
						$options = array_unique(array_merge($options,$field['display_options']));
					}

					$can_edit = false;
					$validate_msg = array();
					if ($definitionCallBackFunction!==null) {
						$rhs_html = call_user_func_array(array($this, $definitionCallBackFunction),array($fieldname));
					} else {
						if (isset($errormsg[$fieldname])) {
							$validate_msg[] = $errormsg[$fieldname];
						}
							
						$fieldtype = $this->dbtable->getFieldType($fieldname);
						if (isset($fieldtype['error'])) {
							$validate_msg[] = $fieldtype['error'];
						}
						$rhs_html = $this->dbtable->formatPrintField($fieldname);
					}

					$fieldtype = $this->dbtable->getFieldType($fieldname);
					$label = $fieldtype['caption'].":";
					$sublabel = TableRow::composeSubcaptionWithValidation($fieldtype['subcaption'], $fieldtype['minimum'], $fieldtype['maximum'], $fieldtype['units'],true);
					
					$label = tidy_repair_string($label,$this->_tidy_options);
					$sublabel = tidy_repair_string($sublabel,$this->_tidy_options);
					$rhs_html = tidy_repair_string($rhs_html,$this->_tidy_options);
					
					$lw = 130;
					$rw = 148;
					$html .= '<td width="'.$lw.'" style="border-color:#000 #ddd #000 #000;background-color:#EEE;"><b>'.$label.'</b><br /><font size="8"><i>'.$sublabel.'</i></font></td>';
					$redmessage = '';
					if (count($validate_msg)>0) {
						$redmessage = '<br /><i><font size="9" color="red">'.tidy_repair_string(implode('<br />',$validate_msg),$this->_tidy_options).'</font></i>';
					}
					if ($is_single_column) {
						$html .= '<td colspan="3" width="'.(2*$rw+$lw).'" style="border-color:#000 #000 #000 #ddd;">'.$rhs_html.$redmessage.'</td>';
					} else {
						$html .= '<td width="'.$rw.'" style="border-color:#000 #000 #000 #ddd;">'.$rhs_html.$redmessage.'</td>';
					}
				}
				$html .= '</tr>';
				 
			} else if (($row['type']=='html') && ($this->show_text_fields)) {
				$user_html_in = $row['html'];
				$user_html_clean = tidy_repair_string($user_html_in,$this->_tidy_options);
				$user_html_clean = $this->convertIMGsToAbsoluteUrls($user_html_clean);
				$html .= '<tr>';
				$html .= '<td colspan="4" style="border-left:0.5px solid #fff;border-right:0.5px solid #fff;"><div>'.$user_html_clean.'</div></td>';
				$html .= '</tr>';
			}

		}
		$html .= '</table>';
		$this->setHtmlVSpace(array('p' => array(0 => array('h'=>'','n' => 1), 1 => array('h'=>'','n' => 1)))); // makes paragraph spacing more sane
		$this->WriteHTML($html, true, false, false, false, '');
	}

	public function renderDashboardView($procedure_records_by_typeobject_id, $references_by_typeobject_id) {
		foreach($procedure_records_by_typeobject_id as $typeobject_id => $procedure_record) {
			$references = isset($references_by_typeobject_id[$typeobject_id]) ? $references_by_typeobject_id[$typeobject_id] : array();
			$this->sectionHeader($procedure_record['type_description']);

			if (count($references)>0) {
				 
				$w=array(40,136,20);
				$header = array("Date", "Details", "Result");
				$lines = array();

				foreach($references as $reference) {
					$feat = array();
					foreach($reference['event_description_array'] as $iv => $item) {
						$user_time = '<b>'.strtoupper($reference['user_name_html']).'</b><br /><i>'.time_to_bulletdate(strtotime($reference['effective_date']),false).'</i>';
						foreach($item['features'] as $feature) {
							$feat[] = "&bull; ".$feature['name'].': <b>'.$feature['value'].'</b>';
						}

					}
					if (!$reference['is_future_version']) {
						$lines[] = array($user_time,implode("<br />",$feat),'<b>'.DBTableRowItemVersion::renderDisposition($this->dbtable->getFieldType('disposition'),$reference['disposition'],false).'</b>');
					}

				}
				$this->tableOutput($header,$lines,$w);
			} else {
				$this->SetFont($this->_myfont,'I',10);
				$this->SetX($this->GetX() + 10);
				$this->MyCell(0,4,'No Results.',0,1,'L');
			}
		}

	}

	protected function renderCommentsChangesTable($lines) {
		$layout_rows_comments_changes = EventStream::renderEventStreamHtmlForPdfFromLines($this->dbtable,$lines,$this->show_procedures_in_event_stream);
		if (count($layout_rows_comments_changes)>0) {
			$this->Ln(10);
			$this->sectionHeader(($this->show_procedures_in_event_stream ? 'Comments, Changes, and Procedures' : 'Comments and Changes').' (oldest first)');
			$this->SetFont($this->_myfont,'',10);
			$html = '';
			$html .= '<table border="0.5" cellpadding="4" cellspacing="0"><thead><tr style="background-color:#EEE; font-weight:bold;"><td width="113" align="center">User / Date</td><td width="386" align="center">Comment or Description</td><td width="57" align="center">Result</td></tr></thead>';
			foreach($layout_rows_comments_changes as $layout_rows_comments_change) {
				foreach($layout_rows_comments_change as $key => $value) {
					$layout_rows_comments_change[$key] = tidy_repair_string($value,$this->_tidy_options);
				}
				if (isset($layout_rows_comments_change[2]) && $layout_rows_comments_change[2]) {
					$html .= '<tr><td width="113">'.$layout_rows_comments_change[0].'</td><td width="386">'.$layout_rows_comments_change[1].'</td><td width="57"><span style="font-weight:bold;">'.$layout_rows_comments_change[2].'</span></td></tr>';
				} else {
					$html .= '<tr><td width="113">'.$layout_rows_comments_change[0].'</td><td width="443" colspan="2">'.$layout_rows_comments_change[1].'</td></tr>';
				}
			}
			$html .= '</table>';
			$this->WriteHTML($html);
		}
	}

	protected function initializeDocument() {
		$this->_w1 = 40;
		$this->_w2 = 146; // should be page width -2*cmargin
		$this->_h1 = 7;
		$this->_h2 = 4.4;
		$this->cMargin = 2;
		$this->SetCellPaddings(2.0,0.0,2.0);
		$this->_current_header = '';
		$this->SetDisplayMode('default','continuous');  // makes it nicer to scroll through a multipage PDF.
		if (Zend_Registry::get('config')->config_for_testing) {
			$this->setDocCreationTimestamp(script_time());
			$this->setDocModificationTimestamp(script_time());
			$this->file_id = md5('I am testing so I want this to be the same always');
		}
	}


	public function buildDocument($show_params=array()) {

		if (isset($show_params['show_form_fields'])) $this->show_form_fields = $show_params['show_form_fields'];
		if (isset($show_params['show_text_fields'])) $this->show_text_fields = $show_params['show_text_fields'];
		if (isset($show_params['show_procedure_tables'])) $this->show_procedure_tables = $show_params['show_procedure_tables'];
		if (isset($show_params['show_event_stream'])) $this->show_event_stream = $show_params['show_event_stream'];
		if (isset($show_params['show_procedures_in_event_stream'])) $this->show_procedures_in_event_stream = $show_params['show_procedures_in_event_stream'];
		 
		$this->initializeDocument();
		$this->dbtable->setFieldTypeForRecordLocator();
		
		 
		$title = $this->dbtable->getPageTypeTitleHtml().($this->dbtable->item_serial_number ? ' - '.TextToHtml($this->dbtable->item_serial_number) : '');

		// set document information
		$this->SetAuthor('PartForge / '.$this->dbtable->lastChangedBy());
		$this->SetTitle($title);
		$this->setListIndentWidth(4.0);

		$this->config = Zend_Registry::get('config');

		$this->AddPage();

		// QR Code
		require_once('../library/phpqrcode/qrlib.php');
		$temp = tempnam('','');
		QRcode::png($this->dbtable->absoluteUrl(),$temp);
		$this->Image($temp, 176, $this->GetY(), 30,30,'png');
		unlink($temp);

		// box at top
		$this->SetXY(10,10);
		$this->MyCell(0,30,'',1,10,'L');

		// title
		$this->SetXY(10,12);
		$this->SetFont($this->_myfont,'B',14);
		$this->MyMultiCell(130,4.5,TextToHtml($title));

		$this->SetXY(100,12);
		$this->SetFont($this->_myfont,'B',14);
		$this->MyMultiCell(80,4.5,$this->dbtable->locatorTerm(true),0,'R');

		$this->SetXY(10,22);
		$this->Ln(4);

		// name & url
		$this->SetFont($this->_myfont,'',10);
		$this->WriteHTML('<p></p>');
		$html = '<p>Last Changed By: <b>'.$this->dbtable->lastChangedBy().'</b><br />
				'.$this->dbtable->absoluteUrl().'</p>';
		$this->WriteHTML($html,true,false,true,true);
		$this->Ln(2);

		$this->SetXY(10,50);

		if ($this->show_form_fields) {
			// get the layout for the itemview
			$fields_to_remove = array();
			$fieldlayout = $this->dbtable->getEditViewFieldLayout($this->dbtable->getEditFieldNames(array('')),$fields_to_remove,'itemview');
			$this->itemViewFieldOutput($fieldlayout, '');
		} else {
			$this->WriteHTML('<p><i>(form output suppressed)</i></p>');
		}

		$procedure_records = getTypesThatReferenceThisType($this->dbtable->typeversion_id);
		$procedure_records_by_typeobject_id = array();
		foreach($procedure_records as $record) {
			$procedure_records_by_typeobject_id[$record['typeobject_id']] = $record;
		}

		$EventStream = new EventStream($this->dbtable->itemobject_id);
		list($lines,$references_by_typeobject_id) = EventStream::eventStreamRecordsToLines($EventStream->assembleStreamArray(),$this->dbtable, $this->navigator);
		$this->Ln(5);

		if ($this->show_procedure_tables) {
			$this->renderDashboardView($procedure_records_by_typeobject_id, $references_by_typeobject_id);
		}

		// comments and changes
		if ($this->show_event_stream) {
			$this->renderCommentsChangesTable($lines);
		}

	}

}

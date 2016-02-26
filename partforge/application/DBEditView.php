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

class DBEditView {
	protected $dbtable;
	protected $acl;
	protected $can_edit_self;
    protected $anchor_array = array();
    public $show_floating_toc = false;
    public $edit_buffer_key = '';
	
	public function __construct(DBTableRow $dbtable) {
		$this->dbtable = $dbtable;
		$this->acl = Zend_Registry::get('customAcl');
		$this->can_edit_self = !$this->dbtable->isEditOperationBlocked('save',$this->dbtable->getTableName());
		$this->anchor_array["TopOfPageAnchor"] = "Top";
	}
	
	
	/*
		This formats a table suitable for displaying as a sublistview for dependent records.
		It is meant to be used by descendent classes.
	*/
	static public function formatSublistTableHtml($lines,$pre_row_html='',$table_id='') {
		if (count($lines)==0) return '';
		$html = '';
		$has_links = false;
		foreach($lines as $line) {
			if (!empty($line['links'])) {
				$has_links = true;
				break;
			}
		}
		
		// header
		$html .= '<table class="sublisttable"'.(!empty($table_id) ? ' id="'.$table_id.'"' : '').'>'.$pre_row_html.'<tr>';
		$line = reset($lines);
		unset($line['tr_attribute']);
		unset($line['links']);
		foreach($line as $key => $field) {
			$html .= '<th>'.$key.'</th>';
		}
		if ($has_links) $html .= '<th>&nbsp;</th>';
		$html .= '</tr>';
		
		// detail lines
		foreach($lines as $line) {
			$html .= '<tr'.$line['tr_attribute'].'>';
			unset($line['tr_attribute']);
			$links = $line['links'];
			unset($line['links']);
			foreach($line as $key => $field) {
				$html .= '<td>'.$field.'</td>';
			}
			if ($has_links) $html .= '<td>'.implode(' ',$links).'</td>';
			$html .= '</tr>';
		}
		$html .= '</table>';
		return $html;
	}	
		
	public function getNavLinks() {
		$navlinks = array();
		$navlinks[] = linkify('#','Home','go to home page','',
					"if (true) {document.theform.btnSubEditParams.value='force_save=&forward_return=&action=login&controller=user';document.theform.submit();} return false;");
		foreach($this->dbtable->getAddableIncomingJoins() as $join_name => $target) {
			$pretty_join_name = ucwords(str_replace('_',' ',$join_name));
			if ($this->acl->isAllowed($_SESSION['account']->getRole(),'table:'.$target['rhs_table'],'add') && $this->can_edit_self) {
				$navlinks[] = linkify('#','Add '.$pretty_join_name,'Add new '.$pretty_join_name,'',
					"document.theform.btnAddIncomingJoin.value='{$join_name}';document.theform.submit(); return false;");
			}
		}
		
		return $navlinks;
	}
	
    protected function addAnchor($anchor_title) {
    	$anchor_name = 'AnchorTOC'.count($this->anchor_array);
        $this->anchor_array[$anchor_name] = $anchor_title;
        return '<a name="'.$anchor_name.'"></a>';
    }
    
    protected function fetchAnchorLinks() {
        $links = array();
        foreach($this->anchor_array as $anchor_name => $anchor_title) {
            $links[] = '<a href="#'.$anchor_name.'">'.TextToHtml($anchor_title).'</a>';
        }
        return '
        <div id="toc">
        <ul>
        <li>'.implode("</li>\r\n<li>",$links).'</li>  
        </ul>
        </div>';
    }
	
	public function fetchJoinHeaderHtml($join_name, $target) {
		$pretty_join_name = ucwords(str_replace('_',' ',$join_name));
		$link_html = ($this->acl->isAllowed($_SESSION['account']->getRole(),'table:'.$target['rhs_table'],'delete') && $this->can_edit_self)
				?   $link_html = ' ('.linkify('#','delete','Delete '.$pretty_join_name,'',
					"if (confirm('Are you sure you want to delete this?')) {document.theform.btnDeleteIncomingJoin.value='{$join_name}';document.theform.submit();} return false;").')'
				:   '';
		$html .= '<h2 class="editviewsubhead">'.$this->addAnchor($pretty_join_name).$pretty_join_name.$link_html.'</h2>
		';
		return $html;
	}
	
	public function fetchTableHtml($join_name, $target, $fields_to_remove = array()) {
		$html = '';
		if ($join_name=='') $fields_to_remove[] = $this->dbtable->getParentPointerIndexName();
//		$field_to_remove = ($join_name=='') ? $this->dbtable->getParentPointerIndexName() : '';
		$layout_key = $this->dbtable->getTableName().(($join_name!='') ? '.'.$target['rhs_table'] : '');
		$fieldlayout = $this->dbtable->getEditViewFieldLayout($this->dbtable->getEditFieldNames(array($join_name)),$fields_to_remove,$layout_key);
		if (!empty($fieldlayout)) {
			$html .= ($join_name!='') ? $this->fetchJoinHeaderHtml($join_name, $target) : '';
			$html .= '<table class="edittable">
					 <col class="table_label_width">
					 <col class="table_value_width">
					 <col class="table_label_width">
					 <col class="table_value_width">';
					 
			// don't show fields as editable if editing of this record is not allowed
			$editable = Zend_Registry::get('customAcl')->isAllowed($_SESSION['account']->getRole(),'table:'.$this->dbtable->getTableName(),'edit')
                && !$this->dbtable->isEditOperationBlocked('save',$this->dbtable->getTableName());

			$html .= fetchEditTableTR($fieldlayout, $this->dbtable, '', $editable);
			$html .= '</table>
			';
		}
		return $html;
	}
	
	public function fetchJavascriptBlock() {
		return <<<DELIM
	
    function setSortable(id_name) {
        $("#"+id_name).sortable().addClass("sortinglist");
        $("#"+id_name+" > li").prepend('<span class="ui-icon ui-icon-arrowthick-2-n-s ui-state-default ui-corner-all"></span>');
    }
    
    function packSortParams(tablename, parentpointername) {
        var items = $("#ul_" + tablename + " > li");
        var itemkeys = [];
        for(var x=0; x<items.length; x++) {
            var id_str = items[x].id;
            id_split_arr = id_str.split("_");
            itemkeys.push(id_split_arr[2]);
        }
        return $.param({ keys : itemkeys, tablename : tablename, parentindex : parentpointername});
    }
	
DELIM;
	}
	
	public function prepareFieldTypes() {
		// attach change event handlers to the table object before rendering any left_join type edit fields
		foreach($this->dbtable->getFieldTypes() as $fieldname => $fieldtype) {
			if (('left_join'==$fieldtype['type']) && $this->can_edit_self) {
				$join_name = $fieldtype['join_name'];
				$this->dbtable->setFieldAttribute($fieldname,'onchange_js',"document.theform.btnOnChange.value='joinselectchange';document.theform.submit();return false;");
			}
		}
	}
	
	/*
		return a description array for dependent row.  Override if enhancing the description
	*/
	protected function dependentItemDescriptionArray(DBTableRow $TableRow, $relationship) {
		// build description with links (if privileged to view)
		$desc_array = $TableRow->getShortDescriptionAsArray($relationship['table'],$relationship['dep_index']);
		foreach($desc_array as $desc_array_key => $desc_array_item) {
			if (!$this->dbtable->isEditOperationBlocked('descLinkInDependent',$relationship['dep_table']) && ($desc_array_key!='') && ($this->acl->isAllowed($_SESSION['account']->getRole(),'table:'.$desc_array_item['link_params']['table'],'view'))) {
				$desc_array[$desc_array_key]['desc_html'] = linkify('#',$desc_array_item['desc_html'],'save and navigate to '.$desc_array_item['desc_html'],'',
					"document.theform.btnSubEditParams.value='force_save=&forward_return=&action=editview&controller={$desc_array_item['link_params']['table']}&{$desc_array_item['link_params']['index']}={$desc_array_item['link_params']['index_value']}';document.theform.submit(); return false;");
			}
		}
		return $desc_array;
	}
	
	/*
		return a page title both with and without links to a parent record.
	*/
	public function getTitleHtmlArray($edit_action_verb='Edit') {
		$dbschema = DbSchema::getInstance();
		$ParentRecord = $this->dbtable->getParentRecord();
		if ($ParentRecord!=null) { 
			$parent_description = $ParentRecord->getCoreDescription();
			$parent_description_linked = $parent_description;
			if ($this->acl->isAllowed($_SESSION['account']->getRole(),'table:'.$ParentRecord->getTableName(),'view')) {
				$link_params = $ParentRecord->getLinkParamsToSelf();
				$parent_description_linked = linkify('#',$parent_description_linked,'go to parent record','',
					"if (true) {document.theform.btnSubEditParams.value='force_save=&forward_return=&action=editview&controller={$link_params['table']}&{$link_params['index']}={$link_params['index_value']}';document.theform.submit();} return false;");
			}
		} else {
			$parent_description = '';
			$parent_description_linked = '';
		}
		$title = ($this->dbtable->getIndexValue() == 'new' ? 'New' : $edit_action_verb).' '.ucwords($dbschema->getNiceTableName($this->dbtable->getTableName())).(!empty($parent_description) ? ' for '.$parent_description : '');
		$linkified_title = ($this->dbtable->getIndexValue() == 'new' ? 'New' : $edit_action_verb).' '.ucwords($dbschema->getNiceTableName($this->dbtable->getTableName())).(!empty($parent_description_linked) ? ' for '.$parent_description_linked : '');
		return array($title,$linkified_title);
	}
	
	/*
		return the editing links for a dependent row
	*/
	protected function dependentItemLinks(DBTableRow $TableRow, $relationship) {
		$links = array();
		$link_params = $TableRow->getLinkParamsToSelf();
		foreach($TableRow->getListOfDetailActions() as $action_name => $detail_action) {
			if ($this->acl->isAllowed($_SESSION['account']->getRole(),'table:'.$relationship['dep_table'],$detail_action['privilege'])
				&& ($this->can_edit_self || ($detail_action['privilege']=='view'))) {
				$icon_html = detailActionToHtml($action_name,$detail_action);
				$title = isset($detail_action['title']) ? $detail_action['title'] : $detail_action['buttonname'];
				$confirm_js = isset($detail_action['confirm']) 	? "confirm('".$detail_action['confirm']."')"
																: (isset($detail_action['alert']) 	? "alert('".$detail_action['alert']."')"
																									: "true");
				$target = isset($detail_action['target']) ? $detail_action['target'] : "";
				$links[] = linkify('#',empty($icon_html) ? $detail_action['buttonname'] : $icon_html,$title,empty($icon_html) ? 'minibutton2' : '',
					"if ({$confirm_js}) {document.theform.btnSubEditParams.value='action={$action_name}&controller={$link_params['table']}&{$link_params['index']}={$link_params['index_value']}&parent_index={$relationship['dep_index']}';document.theform.submit();} return false;",$target);
			}
		}
		return $links;
	}
	
	protected function rewriteDBRecords(DBRecords &$DBRecords, $relationship) {
	}
	
	/*
		Generate the table or ul html of dependent records without the header.
	*/
	protected function fetchDependentListHtml($can_edit,$dependent,$id_basename) {
		$html = '';
		$Records = $dependent['DBRecords'];
		if ((count($Records->keys())>0)) {	
			
			$html .= '<ul id="ul_'.$id_basename.'">';
			foreach($Records->keys() as $key) {

				$TableRow = $Records->getRowObject($key);
				
				// build description with links (if privileged to view)
				$desc_array = $this->dependentItemDescriptionArray($TableRow, $dependent['relationship']);

				// add edit controls
				$links = $can_edit ? $this->dependentItemLinks($TableRow, $dependent['relationship']) :  array();

				$class_highlight = $TableRow->getTouchedRecently() ? ' class="last_select"' : '';

				$html .= '<li'.$class_highlight.' id="li_'.$id_basename.'_'.$key.'">'.$TableRow->descriptionArrayToHtml($desc_array).' '.implode(' ',$links).'</li>';
			}
			$html .= '</ul>';
		}
		
		return $html;
	}
	
	protected function rewriteDependentListButtons($can_edit,$can_add,$dependent,&$button_arr) {
	}
	
	protected function fetchDependentBlocksHtml($can_edit,$can_add,$dependent) {
		$html = '';
		
		$this->rewriteDBRecords($dependent['DBRecords'],$dependent['relationship']);
							
		$Records = $dependent['DBRecords'];
		$tree_params = $Records->getCurrentRowObject()->getTreeParams();
		$collection_description = (($dependent['relationship']['type']=='parent') || !isset($tree_params['linkto_calls_me'])) ? $tree_params['parent_calls_me'] : $tree_params['linkto_calls_me'];
		
		if ($can_add || (count($Records->keys())>0)) {
			$html .= '<h2 class="editviewsubhead">'.$this->addAnchor($collection_description['plural']).$collection_description['plural'].'</h2>';
		}
		
		$button_arr = array();
		
		if ($can_add) {
			$subtablename = $Records->getCurrentRowObject()->getTableName();
			$subtableindexname = $Records->getCurrentRowObject()->getIndexName();
			$button_arr['add'] = linkify('#','Add '.$collection_description['singular'],'Add '.$collection_description['singular'],'minibutton2',
							   "document.theform.btnSubEditParams.value='action=editview&controller={$subtablename}&parent_index={$dependent['relationship']['dep_index']}&initialize[{$dependent['relationship']['dep_index']}]={$dependent['parent_index_value']}&{$subtableindexname}=new';document.theform.submit(); return false;");
		}
		
		$id_basename = $dependent['relationship']['dep_table'];
		
		if ($Records->getCurrentRowObject()->hasDedicatedSortOrderField() && (count($Records->keys()) > 1)
				&& $this->acl->isAllowed($_SESSION['account']->getRole(),'table:'.$dependent['relationship']['dep_table'],'edit') && $this->can_edit_self) {
			$subtablename = $Records->getCurrentRowObject()->getTableName();
			$subtableindexname = $Records->getCurrentRowObject()->getIndexName();
			$button_arr['arrange'] = '<span id="span_go_sortable_'.$id_basename.'">'.linkify('#','Arrange','Change order of '.$collection_description['plural'],'minibutton2',
							   "$(this).hide(); $('#span_save_sortable_".$id_basename."').show(); setSortable('ul_".$id_basename."'); return false;").'</span>';
			$button_arr['arrange_save'] = '<span style="display:none;" id="span_save_sortable_'.$id_basename.'">Drag and drop items in the list.  When done click: '.linkify('#','Save New Arrangement','Save New Arrangement of '.$collection_description['plural'],'minibutton2',
							   "document.theform.onChangeParams.value=packSortParams('".$id_basename."','".$dependent['relationship']['dep_index']."'); document.theform.btnOnChange.value='sort_order';document.theform.submit();return false;").'&nbsp;'.
								linkify('#','Cancel','Cancel Rearranging '.$collection_description['plural'],'minibutton2',
							   "document.theform.btnOnChange.value='sort_order';document.theform.submit();return false;").'</span>';
		}
		
		$html .= $this->fetchDependentListHtml($can_edit,$dependent,$id_basename);
		$this->rewriteDependentListButtons($can_edit,$can_add,$dependent,$button_arr);
		if (count($button_arr)>0) {
			$html .= '<p>'.implode('&nbsp;',$button_arr).'</p>';
		}
		
		return $html;
	}

	public function fetchHtml($fields_to_remove = array()) {
		$html ='';
		
		$html .= js_wrapper($this->fetchJavascriptBlock());
		
		if ($this->show_floating_toc) {
	    	$html .= js_wrapper("
	        
	        // sliding table of contents (TOC)
	
			$(function(){
				function moveFloatMenu() {
					var menuOffset = menuYloc.top + $(this).scrollTop() + 'px';
					$('#toc').animate({top:menuOffset},{duration:300,queue:false});
				}
			 
				menuYloc = $('#toc').offset();
			 
				$(window).scroll(moveFloatMenu);
			 
				moveFloatMenu();
			});
	        
	
	                        ");
		}		

		$this->prepareFieldTypes();
		
		$html .= $this->dbtable->fetchHiddenTableAndIndexFormTags();
		if ($this->edit_buffer_key) {
			$html .= '<input type="hidden" name="edit_buffer_key" value="'.$this->edit_buffer_key.'">';
		}
		$html .= '<input type="hidden" name="btnOnChange" value="">';
		$html .= '<input type="hidden" name="onChangeParams" value="">';   // if btnOnChange is set, this is sometimes used for params
		$html .= '<input type="hidden" name="btnAddIncomingJoin" value="">'; // set this to join name to add an incoming join record
		$html .= '<input type="hidden" name="btnDeleteIncomingJoin" value="">'; // set this to join name to delete an incoming join record
		$html .= '<input type="hidden" name="btnSubEditParams" value="">'; // when calling a subpage will look like table=grades&grade_id=3 for example
		

		/*
		  Show fields, both main and joined records
		*/
		
		$html .= '<p class="req_field_para">'.REQUIRED_SYM.' = required field(s)</p>';
		$html .= '<p class="locked_field_para">'.LOCKED_FIELD_SYM.' = field(s) locked by import process</p>';
		
		$html .= $this->fetchTableHtml('','', $fields_to_remove);
		foreach($this->dbtable->getActiveJoins() as $join_name => $target) {
			$html .= $this->fetchTableHtml($join_name,$target, $fields_to_remove);
		}
		
		/*
		  Show lists of dependent records based on the dependencies
		*/
		
		foreach($this->dbtable->getDependentRecordsCollection() as $dependent) {
			if ($this->acl->isAllowed($_SESSION['account']->getRole(),'table:'.$dependent['relationship']['dep_table'],'view')) {
				
				
				$can_edit = $dependent['relationship']['type']=='parent';
				$can_add = ($dependent['relationship']['type']=='parent') && $this->can_edit_self
							&& !$this->dbtable->isEditOperationBlocked('addDependent',$dependent['relationship']['dep_table'])
							&& $this->acl->isAllowed($_SESSION['account']->getRole(),'table:'.$dependent['relationship']['dep_table'],'add');
				$html .= $this->fetchDependentBlocksHtml($can_edit,$can_add,$dependent);
							
			}    
		}
		
		$html .= '<p>'.$this->addAnchor('End').'</p>';
		
	    if ($this->show_floating_toc) {
	        $html = $this->fetchAnchorLinks().$html;
	    }
		
		return $html;
		
	}
	
}


?>

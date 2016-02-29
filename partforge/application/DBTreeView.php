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

class DBTreeView {
    private $queryvars;
    private $_dbschema;
    protected $_navigator;

    public function __construct($queryvars,$navigator) {
        $this->queryvars = $queryvars;
        $this->_navigator = $navigator;
        $this->_dbschema = DbSchema::getInstance();
    }
    
    private function bullet_children_ul($children, $parent_table_in_tree, $parent_pointers) {
        /*
          $parent_pointers contains an array of values in the parent table that are of interest for the searching for
          records at this level.  Usually this is just the primary index name and value in the parent record but it may
          have additional entries when there is more than one child table.
        */
		$html = '';
		foreach($children as $child) {
	
			
			if (isset($child['heading'])) {
			
				$buttons = array();
				foreach($child['children']  as $subchild) {
					if (isset($subchild['table'])) {
											$primeindexname = isset($subchild['index']) ? $subchild['index'] : $this->_dbschema->getPrimaryIndexName($subchild['table']);
											$buttons[] = linkify($this->_navigator->getCurrentViewUrl('editview',$subchild['table'],array($primeindexname => 'new', 'return_url' => $this->_navigator->getCurrentViewUrl())),'add '.strtolower($this->_dbschema->getNiceTableName($subchild['table'])),'','minibutton2');
					}
				}
				if ($child['test_url']) {
					$buttons[] = linkify($child['test_url'],'test','','minibutton2','','_blank');
				}
				$html .= '<li>'.$child['heading'].' '.implode('  ',$buttons);
				$html .= $this->bullet_children_ul($child['children'], '', array());
				$html .= '</li><br>';
			
			} else {
				$primeindexname = isset($child['index']) ? $child['index'] : $this->_dbschema->getPrimaryIndexName($child['table']);
				$desc_field = isset($child['desc_field']) ? $child['desc_field'] : $this->_dbschema->getDefaultDescriptionField($child['table']);
							$parent_table = isset($child['parent_table']) ? $child['parent_table'] : $parent_table_in_tree;
				$select = isset($child['select']) ? $child['select'] : '*';
							
							// get the child records according to parent id
							if (empty($parent_pointers)) {
								$parent_index = '';
								$parent_value = '';
							} else {
								// the qualifier for the next search is the default or the first in the list of parent pointers
								$keyss = array_keys($parent_pointers);
								$parent_index_in_parent = isset($child['parent_index_in_parent']) ? $child['parent_index_in_parent'] : reset($keyss);
								$parent_value = $parent_pointers[$parent_index_in_parent];
								$parent_index = isset($child['parent_index']) ? $child['parent_index'] : $parent_index_in_parent;
							}
							$Schema = DbSchema::getInstance();
							$ChildRecords = new DBRecords($Schema->dbTableRowObjectFactory($child['table'],null,$parent_index),$parent_index,'');
							$ChildRecords->getRecordsById($parent_value);
							
							
				if (count($ChildRecords->keys()) > 0) {
					$html .= '<ul>';
					foreach($ChildRecords->keys() as $primeindex) {
											$pointers_for_subchildren = array();
											$pointers_for_subchildren[$primeindexname] = $primeindex;
											$DbRowObj = $ChildRecords->getRowObject($primeindex);
						$params = array($primeindexname => $primeindex, 'return_url' => $this->_navigator->getCurrentViewUrl());
											$highlight = $DbRowObj->getTouchedRecently() ? ' class="last_select"' : '';
						$buttons = array();
											
											// need to check what ids are needed for the subchildren
						foreach($child['children']  as $subchild) {
							$linkname = isset($subchild['linkto_table'])
									? 'link '.strtolower($this->_dbschema->getNiceTableName($subchild['linkto_table']))
									: 'add '.strtolower($this->_dbschema->getNiceTableName($subchild['table']));
							$subchild_index = isset($subchild['index']) ? $subchild['index'] : $this->_dbschema->getPrimaryIndexName($subchild['table']);
							$subchild_parent_index_in_parent = isset($subchild['parent_index_in_parent']) ? $subchild['parent_index_in_parent'] : $primeindexname;
							$subchild_parent_value = $DbRowObj->{$subchild_parent_index_in_parent};
							$subchild_parent_index = isset($subchild['parent_index']) ? $subchild['parent_index'] : $subchild_parent_index_in_parent;
							
							if (($subchild_parent_index_in_parent != $primeindexname) && !empty($subchild_parent_value)) {
								$pointers_for_subchildren[$subchild_parent_index_in_parent] = $subchild_parent_value;
							}
							
							if (!empty($subchild_parent_value)) {
								$get_params = array("initialize[{$subchild_parent_index}]" => $subchild_parent_value, $subchild_index => 'new', 'return_url' => $this->_navigator->getCurrentViewUrl());
								if (!empty($subchild_parent_index)) $get_params['parent_index'] = $subchild_parent_index;
								$buttons[] = linkify($this->_navigator->getCurrentViewUrl('editview',$subchild['table'],$get_params),$linkname,'','minibutton2');
							}
						}
						if (isset($child['linkto_table'])) {
							$buttons[] = linkify($this->_navigator->getCurrentViewUrl('delete',$child['table'],$params),'unlink','','minibutton2','return confirm(\'Are you sure you want to unlink this?\');');
						} else {
							$edit_params = $params;
							if (!empty($parent_index)) $edit_params['parent_index'] = $parent_index;
							foreach($DbRowObj->getListOfDetailActions() as $action_name => $detail_action) {
								$icon_html = detailActionToHtml($action_name,$detail_action);
								$title = isset($detail_action['title']) ? $detail_action['title'] : $detail_action['buttonname'];
								$confirm_js = isset($detail_action['confirm']) 	? "return confirm('".$detail_action['confirm']."');"
																				: (isset($detail_action['alert']) 	? "alert('".$detail_action['alert']."'); return false;"
																													: "return true;");
								$target = isset($detail_action['target']) ? $detail_action['target'] : "";
								$buttons[] = linkify($this->_navigator->getCurrentViewUrl($action_name,$child['table'],$edit_params),(empty($icon_html) ? $detail_action['buttonname'] : $icon_html),$title,(empty($icon_html) ? 'minibutton2' : ''),
													 "{$confirm_js}",$target);
							}
							if ($child['test_url_function']) {
								$buttons[] = linkify($child['test_url_function']($edit_params),'test','','minibutton2');
							}
													//        $buttons[] = linkify($this->_navigator->getCurrentViewUrl('editview',$child['table'],array_merge($edit_params,array("save_as_new" => 1))),'copy');
						}
											$text = $DbRowObj->getShortDescriptionHtml($parent_table,$parent_index);
						$html .= '<li'.$highlight.'><span>'.$text.'</span> '.implode('  ',$buttons);
						$html .= $this->bullet_children_ul($child['children'],$child['table'],$pointers_for_subchildren);
						$html .= "</li>\n";
	
					}
					$html .= "</ul>\n";
				}
			}
			
		}
		return $html;
    }
    
    
    public function fetchHtml($subheading=null,$toplevelclass='ul_treeview') {
		$html = '';
		$html .= '<ul class="'.$toplevelclass.'">';
		$html .= $this->bullet_children_ul($this->_dbschema->getRelationshipTree($subheading), '', array());
		$html .= '</ul>';
		return $html;
    }
    
    
}

?>

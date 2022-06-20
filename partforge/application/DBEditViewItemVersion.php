<?php
/**
 *
 * PartForge Enterprise Groupware for recording parts and assemblies by serial number and version along with associated test data and comments.
 *
 * Copyright (C) 2013-2021 Randall C. Black <randy@blacksdesign.com>
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

class DBEditViewItemVersion extends DBEditView {

    public $error_msg_array = array();

    public function fetchTableHtml($join_name, $target, $fields_to_remove = array())
    {
        if ($join_name!='') {
            return parent::fetchTableHtml($join_name, $target, $fields_to_remove);
        }

        $fieldlayout = $this->dbtable->getEditViewFieldLayout($this->dbtable->getEditFieldNames(array('')), $fields_to_remove, 'editview', true);
        if (!empty($fieldlayout)) {
            $html = ($join_name!='') ? $this->fetchJoinHeaderHtml($join_name, $target) : '';
            $html .= '<table class="edittable"><colgroup>
					 <col class="table_label_width">
					 <col class="table_value_width">
					 <col class="table_label_width">
					 <col class="table_value_width"></colgroup>';

            // don't show fields as editable if editing of this record is not allowed
            $editable = Zend_Registry::get('customAcl')->isAllowed($_SESSION['account']->getRole(), 'table:'.$this->dbtable->getTableName(), 'edit')
                && !$this->dbtable->isEditOperationBlocked('save', $this->dbtable->getTableName());

            $options = 'vem_new_version'==$this->version_edit_mode ? 'AlwaysAllowFieldAttachmentDelete' : '';
            $procedure_blocks_html = $this->dbtable->getProcedureEditBlockHtmlByTypeObjectId(false);
            $html .= DBTableRowItemVersion::fetchItemVersionEditTableTR($fieldlayout, $this->dbtable, $this->error_msg_array, $options, $editable, null, array(), $procedure_blocks_html);
            $html .= '</table>
			';
            return $html;
        }
        return '';
    }

    protected function fetchDependentBlocksHtml($can_edit, $can_add, $dependent)
    {
        return '';
    }

    public function getNavLinks()
    {
        $navlinks = array();
        return $navlinks;
    }

    public function prepareFieldTypes()
    {
        // attach change event handlers to the table object before rendering any left_join type edit fields
        foreach ($this->dbtable->getFieldTypes() as $fieldname => $fieldtype) {
            if (isset($fieldtype['type']) && ('component'==$fieldtype['type']) && $this->can_edit_self) {
                $this->dbtable->setFieldAttribute($fieldname, 'onchange_js', "document.theform.btnOnChange.value='componentselectchange';document.theform.onChangeParams.value='component_name={$fieldname}';document.theform.submit();return false;");
            }
        }
        parent::prepareFieldTypes();
    }

    /*
        return a page title both with and without links to a parent record.
    */
    public function getTitleHtmlArray($edit_action_verb = 'Edit')
    {
        $dbschema = DbSchema::getInstance();
        $ParentRecord = $this->dbtable->getParentRecord();
        if ($ParentRecord!=null) {
            $parent_description = $ParentRecord->getCoreDescription();
            $parent_description_linked = $parent_description;
            if ($this->acl->isAllowed($_SESSION['account']->getRole(), 'table:'.$ParentRecord->getTableName(), 'view')) {
                $link_params = $ParentRecord->getLinkParamsToSelf();
                $parent_description_linked = linkify('#', $parent_description_linked, 'go to parent record', '',
                    "if (true) {document.theform.btnSubEditParams.value='force_save=&forward_return=&action=editview&controller={$link_params['table']}&{$link_params['index']}={$link_params['index_value']}';document.theform.submit();} return false;");
            }
        } else {
            $parent_description = '';
            $parent_description_linked = '';
        }


        /*
         * Not the most efficient way to do this, but...
         */

        $type_name = $this->dbtable->getPageTypeTitleHtml();

        $title = ($this->dbtable->getIndexValue() == 'new' ? 'New' : $edit_action_verb).' '.$type_name.(!empty($parent_description) ? ' for '.$parent_description : '');
        $linkified_title = ($this->dbtable->getIndexValue() == 'new' ? 'New' : $edit_action_verb).' '.$type_name.(!empty($parent_description_linked) ? ' for '.$parent_description_linked : '');
        return array($title,$linkified_title);
    }


}

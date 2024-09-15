<?php
/**
 *
 * PartForge Enterprise Groupware for recording parts and assemblies by serial number and version along with associated test data and comments.
 *
 * Copyright (C) 2013-2024 Randall C. Black <randy@blacksdesign.com>
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

class ReportDataItemListView extends ReportDataWithCategory {

    private $addon_fields_list = array();
    private $export_user_records = array();
    private $is_user_procedure = false;
    private $view_category = '';
    private $output_all_versions = false;
    private $_showing_search_results = false;
    private $_show_proc_matrix = true;
    private $_proc_matrix_column_keys = array();
    private $_override_itemversion_id = null;
    private $_recent_row_age = null;
    private $_has_aliases = false;
    private $_show_used_on = false;
    public $_last_changed_days = '';
    private $_include_only_itemobject_ids = null;
    private $_dashboardtable_id = null;
    private $_extracolumnnotetables = array();
    private $_readonly = false;
    private $_is_public_dashboard = false;
    private $_user_records = array();
    private $_dash_comments_limit;

    /**
     * If $output_all_versions is true, then there is 1 output row per itemversion_id.  If false, then 1 output row per itemobject_id.
     * @param boolean $initialize_for_export
     * @param boolean $output_all_versions
     * @param boolean $is_user_procedure
     */
    public function __construct($initialize_for_export = false, $output_all_versions = false, $is_user_procedure = false, $showing_search_results = false, $overrides = array(), $display_only = false)
    {
        $this->_showing_search_results = $showing_search_results;
        if ($output_all_versions) {
            parent::__construct('itemversion');
        } else {
            parent::__construct('itemobject');
        }
        $this->pref_view_category_name = $is_user_procedure ? 'pref_proc_view_category' : 'pref_part_view_category';
        $this->is_user_procedure = $is_user_procedure;
        $this->output_all_versions = $output_all_versions;
        $this->_override_itemversion_id = isset($overrides['itemversion_id']) ? $overrides['itemversion_id'] : null;
        $this->_is_public_dashboard = isset($overrides['is_public']) ? $overrides['is_public'] : false;
        $this->_readonly = isset($overrides['readonly']) ? $overrides['readonly'] : false;
        $this->_dash_comments_limit = isset($overrides['dash_comments_limit']) ? $overrides['dash_comments_limit'] : 10;
        $this->_recent_row_age = Zend_Registry::get('config')->recent_row_age;
        $this->_show_used_on = !$is_user_procedure && !$this->output_all_versions;

        // this little dance is to make sure that we get a valid view_category.
        $this->view_category = isset($overrides['view_category']) ? $overrides['view_category'] : $_SESSION['account']->getPreference($this->pref_view_category_name);
        if ($this->_showing_search_results) {
            $this->view_category = '*';
        }
        if (is_null($this->_override_itemversion_id)) {
            $this->category_array = $this->category_choices_array($_SESSION['account']->getRole());
            $this->view_category = $this->ensure_category($this->view_category);
            $matrix_selector_visible = ($this->view_category!='*') && !$this->is_user_procedure;
            $chkShowProcMatrix = (isset($overrides['chkShowProcMatrix']) ? $overrides['chkShowProcMatrix'] : $_SESSION['account']->getPreference('chkShowProcMatrix'));
            $this->_show_proc_matrix =  $chkShowProcMatrix && $matrix_selector_visible && !$this->output_all_versions;
        } else {
            // we are in a special mode when overriding itemversion.
            $this->category_array = array();
            $this->_show_proc_matrix = false;
        }

        if (($this->view_category!='*')) {
            // '1' = all fields all version, 'allnew' = only from current version
            $show_all_fields = (isset($overrides['chkShowAllFields']) ? $overrides['chkShowAllFields'] : $_SESSION['account']->getPreference('chkShowAllFields'));
            list($this->addon_fields_list,$this->_has_aliases) = $this->getAddOnFieldsForTypeObjectId($this->view_category, $show_all_fields, false, false, $this->is_user_procedure);
        }

        $show_early_part_numbers_column = ($this->view_category=='*') || ($this->_showing_search_results);
        $show_associated_sn_column = $this->is_user_procedure && ($this->view_category=='*');
        $show_item_sn_column = !$this->is_user_procedure;
        $show_change_date_column = !$initialize_for_export || ($initialize_for_export && !$this->output_all_versions);
        $show_disposition_column = $this->is_user_procedure;
        $show_created_fields = !$initialize_for_export || ($initialize_for_export && !$this->output_all_versions);
        $show_created_field_columns_early = $show_created_fields && $this->is_user_procedure;
        $show_proc_matrix_columns = ($this->view_category!='*') && $this->_show_proc_matrix;
        $show_late_part_numbers_column = !$show_early_part_numbers_column && $this->_has_aliases;

        $this->last_select_class = 'rowlight';


        $this->title = $this->is_user_procedure ? 'List of Procedures' : 'List of Parts';
        if ($show_early_part_numbers_column) {
            $this->fields['part_number']    = array('display'=> ($this->is_user_procedure ? 'Procedure Number' : 'Part Number'),        'key_asc'=>'partnumbercache.part_number,iv__item_serial_number', 'key_desc'=>'partnumbercache.part_number desc,iv__item_serial_number');
            $this->fields['part_description']   = array('display'=> ($this->is_user_procedure ? 'Name' : 'Part Name'),      'key_asc'=>'partnumbercache.part_description', 'key_desc'=>'partnumbercache.part_description desc');
        }

        if ($show_created_fields && $show_created_field_columns_early) {
            if (!$show_early_part_numbers_column) {
                $this->default_sort_key = 'first_ref_date desc';
            }
            $this->fields['first_ref_date']  = array('display' => 'Created On Date','key_asc'=>'first_ref_date', 'key_desc'=>'first_ref_date desc', 'start_key' => 'key_desc');
            $this->fields['created_by']  = array('display' => 'Created By',      'key_asc'=>'created_by', 'key_desc'=>'created_by desc');
        }

        if ($show_associated_sn_column) {
            $this->fields['component_serial_numbers']   = array('display'=> 'Associated Serial Number(s)',      'key_asc'=>'component_serial_numbers', 'key_desc'=>'component_serial_numbers desc');
        }
        if ($show_item_sn_column) {
            if ($display_only && !$show_early_part_numbers_column && !$show_created_field_columns_early) {
                $this->default_sort_key = 'iv__item_serial_number desc';
            }
            $this->fields['iv__item_serial_number'] = array('display'=> 'Item Serial Number', 'key_asc'=>'iv__item_serial_number', 'key_desc'=>'iv__item_serial_number desc');
        }

        if (isset($overrides['dashboardtable_id'])) {
            $this->_dashboardtable_id = $overrides['dashboardtable_id'];
            $person = !isset($overrides['dashboardtableuser_id']) || ($overrides['dashboardtableuser_id'] == $_SESSION['account']->user_id)
                    ? ($this->_is_public_dashboard ? 'My Notes (public)' : 'My Notes (private)')
                    : DBTableRowUser::getFullName($overrides['dashboardtableuser_id']).' Notes';
            $this->fields['__column_notes__'] = array('display' => $person);
            $this->fields['__comments__'] = array('display' => 'Comments');
            $this->_user_records = DbSchema::getInstance()->getRecords('user_id', "SELECT user_id, first_name, last_name FROM user"); // for quick lookups
        }

        if ($this->_show_used_on) {
            $this->fields['used_on']   = array('display'=> 'Used On');
        }

        if ($show_late_part_numbers_column) {
            $this->fields['part_number']    = array('display'=> ($this->is_user_procedure ? 'Procedure Number' : 'Part Number'),        'key_asc'=>'partnumbercache.part_number,iv__item_serial_number', 'key_desc'=>'partnumbercache.part_number desc,iv__item_serial_number');
            $this->fields['part_description']   = array('display'=> ($this->is_user_procedure ? 'Name' : 'Part Name'),      'key_asc'=>'partnumbercache.part_description', 'key_desc'=>'partnumbercache.part_description desc');
        }

        if (($this->view_category!='*')) {
            foreach ($this->addon_fields_list as $fieldname => $fieldtype) {
                $this->fields[$fieldname]    = array('display' => $fieldtype['caption']);
                if (DbSchema::getInstance()->hasJsonSupport()) { // we need this to see if the JSON_EXTRACT function is available in Mysql.
                    if (isset($fieldtype['type']) && in_array($fieldtype['type'], array('component'))) {
                        $this->fields[$fieldname]['key_asc'] = 'compsn:'.$fieldname.'';
                        $this->fields[$fieldname]['key_desc'] = 'compsn:'.$fieldname.' desc';
                    } elseif (isset($fieldtype['component_subfield'])) {
                        $this->fields[$fieldname]['key_asc'] = 'compsf:'.$fieldname.'';
                        $this->fields[$fieldname]['key_desc'] = 'compsf:'.$fieldname.' desc';
                    } elseif (isset($fieldtype['type']) && !in_array($fieldtype['type'], array('attachment'))) {
                        $this->fields[$fieldname]['key_asc'] = 'jsonx:'.$fieldname.'';
                        $this->fields[$fieldname]['key_desc'] = 'jsonx:'.$fieldname.' desc';
                    }
                }
            }
        }

        if ($show_proc_matrix_columns) {
            $type_records_refer_to_us = $this->getProcedureRecordsForTheCategory($this->view_category);
            foreach ($type_records_refer_to_us as $proctyperec) {
                $list_url = UrlCallRegistry::formatViewUrl('lv', 'struct', array('to' => $proctyperec['typeobject_id'], 'resetview' => 1));
                $list_button = linkify($list_url, 'All', "List all Proceedures of this type on the Procedures Tab", 'minibutton2');
                $key = 'ref_procedure_typeobject_id_'.$proctyperec['typeobject_id'];
                $obs = $proctyperec['typedisposition']=='B' ? ' [Obsolete]' : '';
                $this->fields[$key] = array('display' => $proctyperec['type_description'].$obs, 'displaylink' => $list_button);
                $this->_proc_matrix_column_keys[] = $key;
            }
        }

        if ($show_disposition_column) {
            $this->fields['iv__disposition']    = array('display'=> 'Disposition',      'key_asc'=>'iv__disposition', 'key_desc'=>'iv__disposition desc');
        }

        // dates
        if ($initialize_for_export) {
            $this->fields['iv__effective_date']     = array('display'=>($this->is_user_procedure ? 'Completed on Date' : 'Effective Date'),     'key_asc'=>'iv__effective_date', 'key_desc'=>'iv__effective_date desc', 'start_key' => 'key_desc');
            $this->fields['modified_by_name']   = array('display'=>($this->is_user_procedure ? 'User' : 'Modified By'),     'key_asc'=>'modified_by_name', 'key_desc'=>'modified_by_name desc');
            if (!$this->output_all_versions) {
                $this->fields['last_comment_date']  = array('display' => 'Last Comment Date');
                $this->fields['last_ref_date']  = array('display' => 'Last Reference Date');
            }
        }

        if ($show_created_fields && !$show_created_field_columns_early) {
            $this->fields['first_ref_date']  = array('display' => 'Created On Date','key_asc'=>'first_ref_date', 'key_desc'=>'first_ref_date desc', 'start_key' => 'key_desc');
            $this->fields['created_by']  = array('display' => 'Created By',      'key_asc'=>'created_by', 'key_desc'=>'created_by desc');
        }

        if ($show_change_date_column) {
            $this->fields['last_change_date']   = array('display'=>($this->is_user_procedure ? 'Last Change' : 'Last Change'),      'key_asc'=>'last_change_date', 'key_desc'=>'last_change_date desc', 'start_key' => 'key_desc');
            $this->fields['last_changed_by']    = array('display'=>($this->is_user_procedure ? 'Changed By' : 'Changed By'),      'key_asc'=>'last_changed_by', 'key_desc'=>'last_changed_by desc');
        }

        if (isset($overrides['dashboardtable_id'])) {
            $this->_extracolumnnotetables = DBTableRowDashboardColumnNote::getDashboardTableIdsOfTypeObjectId($this->view_category, $this->_dashboardtable_id);
            foreach ($this->_extracolumnnotetables as $dashboardtable_id => $dashboardcol) {
                $this->fields['column_notes_'.$dashboardtable_id] = array('display' => $dashboardcol['display']);
            }
        }

        if ($initialize_for_export) {
            foreach ($this->fields as $field => $params) {
                $this->csvfields[$field] = $params['display'];
            }
            if ($this->view_category!='*') {
                list($this->all_fields,$has_aliases) = $this->getAddOnFieldsForTypeObjectId($this->view_category, '1', true, true);
                foreach ($this->all_fields as $fieldname => $fieldtype) {
                    $comp_suffix = $fieldtype['type']=='component' ? ' (to/'.implode('|', $fieldtype['can_have_typeobject_id']).')' : '';
                    if (str_contains($fieldname, '.')) {
                        $a = explode('.', $fieldname);
                        $this->csvfields[$fieldname] = $a[0].'->'.$fieldtype['caption'].$comp_suffix;
                    } else {
                        $this->csvfields[$fieldname] = $fieldtype['caption'].$comp_suffix;
                    }
                }
            }
            if (isset($this->csvfields['iv__effective_date']) && isset($this->csvfields['effective_date'])) {
                unset($this->csvfields['effective_date']);
            }
            $this->csvfields['itemobject_id'] = 'itemobject_id';
            $this->csvfields['itemversion_id'] = 'itemversion_id';
            $this->csvfields['typeversion_id'] = 'typeversion_id';
            $this->csvfields['user_id'] = 'user_id';

            $this->export_user_records = DbSchema::getInstance()->getRecords('user_id', "SELECT * FROM user");
        }

        if (!$initialize_for_export && !$this->_showing_search_results) {
            $this->_last_changed_days =  (isset($overrides['lastChangedDays']) ? $overrides['lastChangedDays'] : $_SESSION['account']->getPreference('lastChangedDays'));
            $this->_include_only_itemobject_ids = isset($overrides['include_only_itemobject_ids']) ? $overrides['include_only_itemobject_ids'] : null;
        }

        $this->search_box_label = $this->is_user_procedure ? 'proc. number, SN, or locator' : 'part number, SN, or locator';

    }

    public function getViewCategory()
    {
        return $this->view_category;
    }

    protected function getProcedureRecordsForTheCategory($typeobject_id)
    {
        $TypeObject = new DBTableRowTypeObject(false, null);
        $TypeObject->getRecordById($typeobject_id);
        return getTypesThatReferenceThisType($TypeObject->cached_current_typeversion_id);
    }


    /**
     * gets all the fieldnames for this typeobject_id.  If requested (not request), gets only featured fields.
     * If there is more than one typeversion, it will amalgamate all fields from all typeversions for the specified
     * $typeobject_id.
     * @param integer $typeobject_id
     * @param string $show_all_fields '0' =  only featured fields, '1' = all fields all version, 'allnew' = all fields from current version
     * @return multitype:
     */

    protected function getAddOnFieldsForTypeObjectId($typeobject_id, $show_all_fields, $get_subfields, $get_header_fields, $force_include_components = false)
    {

        $TypeObject = new DBTableRowTypeVersion(false, null);
        $DBTableRowQuery = new DBTableRowQuery($TypeObject);
        $DBTableRowQuery->addAndWhere("and typeversion.typeobject_id='".$typeobject_id."' and typeversion.versionstatus='A'");
        $DBTableRowQuery->setOrderByClause("order by typeversion.effective_date desc");
        if ($show_all_fields == 'allnew') { // only use most recent active version.
            $DBTableRowQuery->setLimitClause('LIMIT 1');
        }
        $typerecords = DbSchema::getInstance()->getRecords('', $DBTableRowQuery->getQuery());
        $has_aliases = false;
        $out = array();
        foreach ($typerecords as $typerecord) {
            $TypeVersion = new DBTableRowTypeVersion(false, null);
            if ($TypeVersion->getRecordById($typerecord['typeversion_id'])) {
                $out = array_merge($out, (($show_all_fields != '0') ? $TypeVersion->getItemFieldTypes(true, $get_header_fields, $force_include_components) : $TypeVersion->getItemFieldTypes(false, $get_header_fields, $force_include_components)));
                if ($TypeVersion->partnumber_count > 1) {
                    $has_aliases = true;
                }
            }
            if ($get_subfields) {
                $out = array_merge($out, DBTableRowTypeVersion::getAllPossibleComponentExtendedFieldNames($typerecord['typeobject_id']));
            }
        }
        return array($out,$has_aliases);
    }

    public function getSearchAndWhere($search_string, $DBTableRowQuery)
    {
        $and_where = '';
        if (!is_null($this->_override_itemversion_id) && is_numeric($this->_override_itemversion_id)) {
            $and_where .=  " and (itemversion_id='{$this->_override_itemversion_id}')";
        }
        $and_where .=  $this->is_user_procedure ? " and (typecategory.is_user_procedure='1')" : " and (typecategory.is_user_procedure!='1')";
        if ($search_string) {
            $like_value = fetch_like_query($search_string, '%', '%');
            $or_arr = array();
            $or_arr[] = "currtypeversion.type_part_number {$like_value}";
            if ($this->is_user_procedure) {
                $or_arr[] = "EXISTS (SELECT *
					FROM itemcomponent
					LEFT JOIN itemobject AS io_them ON io_them.itemobject_id=itemcomponent.has_an_itemobject_id
					LEFT JOIN itemversion AS iv_them ON iv_them.itemversion_id=io_them.cached_current_itemversion_id
					WHERE (itemcomponent.belongs_to_itemversion_id=itemobject.cached_current_itemversion_id) and (iv_them.item_serial_number {$like_value}))";
            } else {
                if ($this->output_all_versions) {
                    $or_arr[] = "itemversion.item_serial_number {$like_value}";
                } else {
                    $or_arr[] = "{$DBTableRowQuery->getJoinAlias('itemversion')}.item_serial_number {$like_value}";
                }
            }
            $or = implode(' or ', $or_arr);
            $and_where .= " and ($or)";
        } else {
            if ($this->view_category!='*') {
                $and_where .= " and (currtypeversion.typeobject_id='{$this->view_category}')";
            } elseif ($_SESSION['account']->hasLimitedVisibilityOfTypes()) {
                $ids_codes = $_SESSION['account']->getDataTerminalObjectIds();
                if (count($ids_codes)>0) {
                    $and_where .= " and (currtypeversion.typeobject_id IN (".implode(',', $ids_codes)."))";
                }
            }
        }
        // add date range
        if (is_numeric($this->_last_changed_days)) {
            $iv_alias = $DBTableRowQuery->getJoinAlias('itemversion');
            $from_date = time_to_mysqldatetime(script_time() - $this->_last_changed_days*3600*24);
            $to_date = time_to_mysqldatetime(script_time());
            $and_where .= " and (IF ( itemobject.cached_last_comment_date IS NULL OR  itemobject.cached_last_comment_date <= {$iv_alias}.effective_date,
                                IF (itemobject.cached_last_ref_date IS NULL OR itemobject.cached_last_ref_date <= {$iv_alias}.effective_date, {$iv_alias}.effective_date, itemobject.cached_last_ref_date ),
                                IF (itemobject.cached_last_ref_date IS NULL OR itemobject.cached_last_ref_date <= itemobject.cached_last_comment_date, itemobject.cached_last_comment_date, itemobject.cached_last_ref_date) )
                                    between '{$from_date}' and '{$to_date}')";
        }
        // handle when we show only specific serial numbers.
        if (!is_null($this->_include_only_itemobject_ids) && ($this->_include_only_itemobject_ids !=="")) {
            $and_where .= " and itemobject.itemobject_id in ($this->_include_only_itemobject_ids)";
        }



        return $and_where;
    }

    protected function category_choices_array($role)
    {
        $fulllist = DBTableRowTypeVersion::getPartNumbersWAliasesAllowedToUser($_SESSION['account'], $this->is_user_procedure);
        $options = array();
        $favorites = $_SESSION['account']->getNumericFavorites($this->pref_view_category_name.'_fav');
        $cnt = 0;
        foreach ($favorites as $io_fav) {
            if (isset($fulllist[$io_fav])) {
                $options += array('fav'.$io_fav => $fulllist[$io_fav]);
                $cnt++;
            }
        }
        if ($cnt>0) {
            $options += array('' => '');
        }
        $options += array('*' => ($this->is_user_procedure ? 'All Procedures' : 'All Parts'));
        $options = $options + $fulllist;
        return $options;
    }

    // ensures returned category is reasonable and if not, sets it to a good default
    public function ensure_category($category)
    {
        if (!is_numeric($category) && ($category!='*')) {
            preg_match('/^fav([0-9]+)$/', $category, $out);
            if (isset($out[1])) {
                $category = $out[1];
            }
        }
        return parent::ensure_category($category);
    }

    protected function addExtraJoins(&$DBTableRowQuery, $skipDateProcessing = false)
    {
        if ($this->output_all_versions) {
            // add type version info
            $DBTableRowQuery->addJoinClause("LEFT JOIN typeversion as currtypeversion on currtypeversion.typeversion_id = itemversion.typeversion_id")
                            ->addSelectFields('currtypeversion.typeobject_id,currtypeversion.type_part_number,currtypeversion.type_description, itemversion.item_serial_number as iv__item_serial_number');

            $DBTableRowQuery->addJoinClause("LEFT JOIN partnumbercache ON partnumbercache.typeversion_id=itemversion.typeversion_id AND partnumbercache.partnumber_alias=itemversion.partnumber_alias")
                            ->addSelectFields('partnumbercache.part_number, partnumbercache.part_description');

            // add typecategory info
            $DBTableRowQuery->addJoinClause("LEFT JOIN typecategory on typecategory.typecategory_id = currtypeversion.typecategory_id")
                            ->addSelectFields('typecategory.is_user_procedure');

            // add user's name
            $DBTableRowQuery->addJoinClause("LEFT JOIN user on user.user_id = itemversion.user_id")
                            ->addSelectFields("TRIM(CONCAT(user.first_name,' ',user.last_name)) as modified_by_name");
        } else {
            // add type version info
            $DBTableRowQuery->addJoinClause("LEFT JOIN typeversion as currtypeversion on currtypeversion.typeversion_id = {$DBTableRowQuery->getJoinAlias('itemversion')}.typeversion_id")
                            ->addSelectFields('currtypeversion.typeobject_id,currtypeversion.type_part_number,currtypeversion.type_description');

            $DBTableRowQuery->addJoinClause("LEFT JOIN partnumbercache ON partnumbercache.typeversion_id={$DBTableRowQuery->getJoinAlias('itemversion')}.typeversion_id AND partnumbercache.partnumber_alias={$DBTableRowQuery->getJoinAlias('itemversion')}.partnumber_alias")
                            ->addSelectFields('partnumbercache.part_number, partnumbercache.part_description');

            // add typecategory info
            $DBTableRowQuery->addJoinClause("LEFT JOIN typecategory on typecategory.typecategory_id = currtypeversion.typecategory_id")
            ->addSelectFields('typecategory.is_user_procedure');

            // add user's name
            $DBTableRowQuery->addJoinClause("LEFT JOIN user on user.user_id = {$DBTableRowQuery->getJoinAlias('itemversion')}.user_id")
                            ->addSelectFields("TRIM(CONCAT(user.first_name,' ',user.last_name)) as modified_by_name");

            /*
             * This is to include the latest change date and person in the output.  This is done by chosing the latest of three dates: last_comment_date, last_ref_date, last_effective_date
             * To do the comparison in sql need (among other things) this logic:
             *
             * IF ( AA IS NULL OR  AA <= CC, IF (BB IS NULL OR BB <= CC, CC, BB ), IF (BB IS NULL OR BB <= AA, AA, BB) )
             *
             * This handles the case where AA or BB can be null (i.e., no comments or referenced components)
             *
             * AA = last_comment_tbl.bb_comment_added      =>   TRIM(CONCAT(last_comment_tbl.bb_user_first_name,' ',last_comment_tbl.bb_user_last_name))
             * BB = last_ref_tbl.bb_iv_effective_date     =>    TRIM(CONCAT(last_ref_tbl.bb_iv_user_first_name,' ',last_ref_tbl.bb_iv_user_last_name))
             * CC = {$iv_alias}.effective_date            =>    TRIM(CONCAT(user.first_name,' ',user.last_name))
             */

            if (!$skipDateProcessing) {
                $iv_alias = $DBTableRowQuery->getJoinAlias('itemversion');
                $DBTableRowQuery->addSelectFields("
										IF ( itemobject.cached_last_comment_date IS NULL OR  itemobject.cached_last_comment_date <= {$iv_alias}.effective_date,
											IF (itemobject.cached_last_ref_date IS NULL OR itemobject.cached_last_ref_date <= {$iv_alias}.effective_date, {$iv_alias}.effective_date, itemobject.cached_last_ref_date ),
											IF (itemobject.cached_last_ref_date IS NULL OR itemobject.cached_last_ref_date <= itemobject.cached_last_comment_date, itemobject.cached_last_comment_date, itemobject.cached_last_ref_date) ) as last_change_date,
										IF ( itemobject.cached_last_comment_date IS NULL OR  itemobject.cached_last_comment_date <= {$iv_alias}.effective_date,
											IF (itemobject.cached_last_ref_date IS NULL OR itemobject.cached_last_ref_date <= {$iv_alias}.effective_date, TRIM(CONCAT(user.first_name,' ',user.last_name)), itemobject.cached_last_ref_person ),
											IF (itemobject.cached_last_ref_date IS NULL OR itemobject.cached_last_ref_date <= itemobject.cached_last_comment_date, itemobject.cached_last_comment_person, itemobject.cached_last_ref_person) ) as last_changed_by,
										itemobject.cached_last_comment_date as last_comment_date,
										itemobject.cached_last_ref_date as last_ref_date,
										itemobject.cached_first_ver_date as first_ref_date,
										itemobject.cached_created_by as created_by,
										{$iv_alias}.effective_date as last_effective_date");
            }
        }
        /*
         * might also want to left join any components linked to us
         */


    }

    /**
     * This takes an ORDER BY clause that might have fake names like jsonx:my_data_field and replaces these
     * with valid SQL. The fake values are short-hand for a field that is not a native field of itemversion.
     * jsonx:* = a user field encoded in the json field item_data
     * compsn:* = a component name
     * compsf:* = a component subfield
     *
     * @param string $expression
     *
     * @return string
     */
    public function convertSortExpressionsToJsonExtracts($expression)
    {
        // create SQL to handle standard fields embedded in item_data
        $out = array();
        $match = preg_match_all('/jsonx:([a-z_0-9]+)/i', $expression, $out);
        for ($i=0; $i < count($out[0]); $i++) {
            $inner = $out[1][$i];
            $outer = $out[0][$i];
            $expression = str_ireplace($outer, "JSON_EXTRACT(IF(item_data='','{}',item_data),'$.".$inner."')", $expression);
        }

        // create SQL to handle component fields
        $out = array();
        $match = preg_match_all('/compsn:([a-z_0-9]+)/i', $expression, $out);
        for ($i=0; $i < count($out[0]); $i++) {
            $inner = $out[1][$i];
            $outer = $out[0][$i];
            $expression = str_ireplace($outer,
            "(SELECT ivcomp.item_serial_number
            FROM itemobject as iocomp,itemversion as ivcomp, itemcomponent
            WHERE ivcomp.itemversion_id=iocomp.cached_current_itemversion_id
            AND iocomp.itemobject_id=itemcomponent.has_an_itemobject_id
            AND itemcomponent.belongs_to_itemversion_id=iv__itemversion_id
            AND itemcomponent.component_name='".$inner."' LIMIT 1)", $expression);
        }

        // create SQL to handle component subfields
        $out = array();
        $match = preg_match_all('/compsf:([a-z_0-9]+)/i', $expression, $out);
        for ($i=0; $i < count($out[0]); $i++) {
            $inner = $out[1][$i];
            $outer = $out[0][$i];
            $fieldtype = $this->addon_fields_list[$inner];
            $component = $fieldtype['component_name'];
            $subfield = $fieldtype['component_subfield'];
            $expression = str_ireplace($outer,
            "(SELECT JSON_EXTRACT(IF(ivcomp.item_data='','{}',ivcomp.item_data),'$.".$subfield."')
            FROM itemobject as iocomp,itemversion as ivcomp, itemcomponent
            WHERE ivcomp.itemversion_id=iocomp.cached_current_itemversion_id
            AND iocomp.itemobject_id=itemcomponent.has_an_itemobject_id
            AND itemcomponent.belongs_to_itemversion_id=iv__itemversion_id
            AND itemcomponent.component_name='".$component."' LIMIT 1)", $expression);
        }

        return $expression;
    }

    public function get_records($queryvars, $searchstr, $limitstr)
    {

        if ($this->output_all_versions) {
            $DBTableRowQuery = new DBTableRowQuery($this->dbtable);
            $DBTableRowQuery->addSelectFields('itemversion.effective_date as iv__effective_date, itemversion.itemversion_id as iv__itemversion_id, itemversion.disposition as iv__disposition');
            $DBTableRowQuery->setOrderByClause($this->convertSortExpressionsToJsonExtracts("ORDER BY {$this->get_sort_key($queryvars,true)}"))
                            ->setLimitClause($limitstr);

            $DBTableRowQuery->addAndWhere($this->getSearchAndWhere($searchstr, $DBTableRowQuery));
            /*
             * for the current itemversion, stich together serial numbers.
             */
            $DBTableRowQuery->addSelectFields("
                    (SELECT GROUP_CONCAT(iv_them.item_serial_number)
                    FROM itemcomponent
                    LEFT JOIN itemobject AS io_them ON io_them.itemobject_id=itemcomponent.has_an_itemobject_id
                    LEFT JOIN itemversion AS iv_them ON iv_them.itemversion_id=io_them.cached_current_itemversion_id
                    WHERE itemcomponent.belongs_to_itemversion_id=itemversion.itemversion_id) as component_serial_numbers"
            );
            $this->addExtraJoins($DBTableRowQuery);
        } else {
            $DBTableRowQuery = new DBTableRowQuery($this->dbtable);
            $DBTableRowQuery->setOrderByClause($this->convertSortExpressionsToJsonExtracts("ORDER BY {$this->get_sort_key($queryvars,true)}"))
                            ->setLimitClause($limitstr)
                            ->addAndWhere($this->getSearchAndWhere($searchstr, $DBTableRowQuery));
            /*
             * display the related component serial number(s) for the current itemobject.  I don't think the
             * concats will do anything here.
             */
            $DBTableRowQuery->addSelectFields("
                (SELECT GROUP_CONCAT(iv_them.item_serial_number)
                FROM itemcomponent
                LEFT JOIN itemobject AS io_them ON io_them.itemobject_id=itemcomponent.has_an_itemobject_id
                LEFT JOIN itemversion AS iv_them ON iv_them.itemversion_id=io_them.cached_current_itemversion_id
                WHERE itemcomponent.belongs_to_itemversion_id=itemobject.cached_current_itemversion_id) as component_serial_numbers"
            );

            if (is_numeric($this->_dashboardtable_id)) {
                // group concat in case we ended up with more than one record for some reason.
                $DBTableRowQuery->addSelectFields("
                    (SELECT GROUP_CONCAT(dashboardcolumnnote.value)
                    FROM dashboardcolumnnote
                    WHERE (dashboardcolumnnote.itemobject_id=itemobject.itemobject_id) and (dashboardcolumnnote.dashboardtable_id='{$this->_dashboardtable_id}')) as __column_notes__"
                );
                $DBTableRowQuery->addSelectFields("
                    (
						SELECT
							GROUP_CONCAT(
								CONCAT(themcomment.comment_id,'&',themcomment.user_id,'&',themcomment.comment_added,'&',CONVERT(HEX(themcomment.comment_text),CHAR),'&',IFNULL((SELECT
									GROUP_CONCAT(
										CONCAT(document.document_id,',',document.document_filesize,',',CONVERT(HEX(document.document_displayed_filename),CHAR),',',CONVERT(HEX(document.document_stored_filename),CHAR),',',document.document_stored_path,',',document.document_file_type,',',document.document_thumb_exists)
                                        ORDER BY document.document_date_added
										SEPARATOR ';'
									)
								FROM document WHERE (document.comment_id = themcomment.comment_id) and (document.document_path_db_key='".Zend_Registry::get('config')->document_path_db_key."')),''))
								SEPARATOR '|')
						FROM comment as themcomment WHERE themcomment.itemobject_id=itemobject.itemobject_id and themcomment.is_fieldcomment=0
                        ORDER BY themcomment.comment_added
					) as __comments__"
                );
                foreach ($this->_extracolumnnotetables as $dashboardtable_id => $dashboardcol) {
                    $DBTableRowQuery->addSelectFields("
                    (SELECT GROUP_CONCAT(dashboardcolumnnote.value)
                    FROM dashboardcolumnnote
                    WHERE (dashboardcolumnnote.itemobject_id=itemobject.itemobject_id) and (dashboardcolumnnote.dashboardtable_id='{$dashboardtable_id}')) as column_notes_".$dashboardtable_id
                    );
                }
            }

            if ($this->_show_proc_matrix) {
                // we will only see the latest version of the procedure
                $DBTableRowQuery->addSelectFields("
						(SELECT GROUP_CONCAT(CONCAT(tv_proc.typeobject_id,',',iv_proc.itemobject_id,',',iv_proc.disposition,',',io_proc.cached_first_ver_date) ORDER BY io_proc.cached_first_ver_date SEPARATOR ';')
						FROM itemcomponent
						LEFT JOIN itemversion AS iv_proc ON iv_proc.itemversion_id=itemcomponent.belongs_to_itemversion_id
						LEFT JOIN typeversion AS tv_proc ON tv_proc.typeversion_id=iv_proc.typeversion_id
						LEFT JOIN typecategory as tc_proc ON tc_proc.typecategory_id=tv_proc.typecategory_id
						LEFT JOIN itemobject AS io_proc ON io_proc.itemobject_id=iv_proc.itemobject_id
						WHERE (itemcomponent.has_an_itemobject_id=itemobject.itemobject_id and tc_proc.is_user_procedure='1') && (iv_proc.itemversion_id=io_proc.cached_current_itemversion_id)) as all_procedure_object_ids"
                );
            }

            if ($this->_show_used_on) {
                $DBTableRowQuery->addSelectFields("
						(SELECT GROUP_CONCAT(CONCAT(iv_used_on.itemobject_id,',',CONVERT(HEX(iv_used_on.item_serial_number),CHAR),',',iv_used_on.partnumber_alias,',',CONVERT(HEX(tv_used_on.type_description),CHAR)) ORDER BY iv_used_on.itemobject_id SEPARATOR ';')
						FROM itemcomponent
						LEFT JOIN itemversion AS iv_used_on ON iv_used_on.itemversion_id=itemcomponent.belongs_to_itemversion_id
						LEFT JOIN typeversion AS tv_used_on ON tv_used_on.typeversion_id=iv_used_on.typeversion_id
						LEFT JOIN itemobject AS io_used_on ON io_used_on.itemobject_id=iv_used_on.itemobject_id
						WHERE (itemcomponent.has_an_itemobject_id=itemobject.itemobject_id and tv_used_on.typecategory_id='2') && (iv_used_on.itemversion_id=io_used_on.cached_current_itemversion_id)) as used_on_packed"
                );
            }

            $this->addExtraJoins($DBTableRowQuery);
        }
        return DbSchema::getInstance()->getRecords('', $DBTableRowQuery->getQuery());
    }

    public function get_records_count(&$queryvars, $searchstr)
    {
        $DBTableRowQuery = new DBTableRowQuery($this->dbtable);
        $DBTableRowQuery->addAndWhere( $this->getSearchAndWhere($searchstr, $DBTableRowQuery) );
        $this->addExtraJoins($DBTableRowQuery, true);
        $DBTableRowQuery->setSelectFields('count(*)');
        $records = DbSchema::getInstance()->getRecords('', $DBTableRowQuery->getQuery());
        $record = reset($records);
        return $record['count(*)'];
    }

    public function make_directory_detail($queryvars, &$record, &$buttons_arr, &$detail_out, UrlCallRegistry $navigator)
    {
        parent::make_directory_detail($queryvars, $record, $buttons_arr, $detail_out, $navigator);
        $query_params = array();
        $query_params['itemversion_id'] = $record['iv__itemversion_id'];
        $query_params['return_url'] = $navigator->getCurrentViewUrl();
        $query_params['resetview'] = 1;
        $edit_url = $navigator->getCurrentViewUrl('itemview', 'struct', $query_params);
        // the following links have superfluis table params--oh well.
        $buttons_arr[] = linkify( $edit_url, 'View', 'View', 'listrowlink');

        foreach (array_keys($this->display_fields($navigator, $queryvars)) as $fieldname) {
            $detail_out[$fieldname] = isset($record[$fieldname]) ? TextToHtml($record[$fieldname]) : null;
        }

        $detail_out['iv__item_serial_number'] = linkify( $edit_url, $record['iv__item_serial_number'], 'View');
        $detail_out['type_part_number'] = TextToHtml(DBTableRowTypeVersion::formatPartNumberDescription($record['type_part_number']));

        if ($this->_show_used_on) {
            $wu_links = array();
            foreach (explode(';', $record['used_on_packed']) as $wu) {
                $fields = explode(',', $wu);
                if (count($fields)==4) {
                    list($wu_itemobject_id, $wu_hex_item_serial_number, $wu_partnumber_alias, $wu_hex_type_description) = $fields;
                    $type_desc = explode('|', hextobin($wu_hex_type_description))[$wu_partnumber_alias];
                    $wu_query_params = $query_params;
                    unset($wu_query_params['itemversion_id']);
                    $wu_query_params['itemobject_id'] = $wu_itemobject_id;
                    $wu_url = $navigator->getCurrentViewUrl('itemview', 'struct', $wu_query_params);
                    list($error_counts_array, $depths_array) = DBTableRowItemObject::refreshAndGetValidationErrorCountsAndDepths(array($wu_itemobject_id), false);
                    if ($error_counts_array[$wu_itemobject_id]>0) {
                        $detail_out['td_class']['used_on'] = 'cell_error';
                    }
                    $tree_link = $depths_array[$wu_itemobject_id] > 0 ? popupTreeViewLink($wu_itemobject_id) : '';

                    $wu_links[] = linkify($wu_url, hextobin($wu_hex_item_serial_number), 'View '.$type_desc.': '.hextobin($wu_hex_item_serial_number)).$tree_link;
                }
            }
            $detail_out['used_on'] = implode(', ', $wu_links);
        }

        $last_change_date_str = date('M j, Y G:i', strtotime($record['last_change_date']));
        $first_ref_date_str = date('M j, Y G:i', strtotime($record['first_ref_date']));
        $detail_out['last_change_date'] = empty($record['last_change_date']) ? '' : $last_change_date_str;
        $detail_out['first_ref_date'] = empty($record['first_ref_date']) ? '' : ($this->is_user_procedure ? linkify($edit_url, $first_ref_date_str, 'View') : $first_ref_date_str);

        // used for the csv export of all versions
        $detail_out['iv__effective_date'] = empty($record['iv__effective_date']) ? '' : date('M j, Y G:i', strtotime($record['iv__effective_date']));

        $record_is_not_selected_category = ($this->view_category!='*') && ($this->view_category!=$record['typeobject_id']);

        // It would be a little more efficient if I only instantiated $ItemVersion if I needed it but more fragile.

        $need_to_load_ItemVersion = (count($this->addon_fields_list)>0) || $this->is_user_procedure;

        $errormsg = array();
        if ($need_to_load_ItemVersion) {
            $ItemVersion = new DBTableRowItemVersion(false, null);
            $ItemVersion->_navigator = $navigator;
            $ItemVersion->getRecordById($record['iv__itemversion_id']);
            $ItemVersion->validateFields($ItemVersion->getSaveFieldNames(), $errormsg);
            // the following MUST be called after validateFields because otherwise validate fields will think component errors are real errors
            $component_depths_array = $ItemVersion->getComponentValidationErrorsAndDepths($errormsg, false);
        }

        // if this is a list of parts, we also want to show a red background for the SN field if there are errors in the part.
        if (!$this->is_user_procedure) {
            list($error_counts_array, $depths_array) = DBTableRowItemObject::refreshAndGetValidationErrorCountsAndDepths(array($record['itemobject_id']), false);
            if ($error_counts_array[$record['itemobject_id']] > 0) {
                $detail_out['td_class']['iv__item_serial_number'] = 'cell_error';
            }
            if (isset($depths_array[$record['itemobject_id']]) && ($depths_array[$record['itemobject_id']] > 0)) {
                $detail_out['iv__item_serial_number'] .= popupTreeViewLink($record['itemobject_id']);
            }
        }

        if ($need_to_load_ItemVersion && (count($this->addon_fields_list)>0)) {
            foreach ($this->addon_fields_list as $fieldname => $fieldtype) {
                if (isset($ItemVersion->{$fieldname})) {
                    $editing_msg = '';

                    if (($fieldtype['type']=='component') && isset($component_depths_array[$ItemVersion->{$fieldname}]) && ($component_depths_array[$ItemVersion->{$fieldname}] > 0)) {
                        $editing_msg = popupTreeViewLink($ItemVersion->{$fieldname});
                    }
                    $detail_out[$fieldname] = $ItemVersion->formatPrintField($fieldname, true, true).$editing_msg;
                }
                $fieldtype2 = $ItemVersion->getFieldType($fieldname);
                if (isset($fieldtype2['error']) || isset($errormsg[$fieldname])) {
                    $detail_out['td_class'][$fieldname] = 'cell_error';
                }
                // mark the fields that are really not defined for this type
                if ($record_is_not_selected_category) {
                    $detail_out['td_class'][$fieldname] = 'na';
                }
            }
        }

        if (count($this->_proc_matrix_column_keys)>0) {
            $matrix_query_params = $query_params;
            unset($matrix_query_params['itemversion_id']);
            $out = array();
            $ref_recs = !empty($record['all_procedure_object_ids']) ? explode(';', $record['all_procedure_object_ids']) : array();
            foreach ($ref_recs as $ref_rec) {
                $proc_arr = explode(',', $ref_rec);
                if (count($proc_arr)==4) {
                    list($proc_to,$proc_io,$proc_disposition,$proc_effective_date) = $proc_arr;
                    $key = 'ref_procedure_typeobject_id_'.$proc_to;
                    if (!isset($out[$key])) {
                        $out[$key] = array();
                    }
                    $matrix_query_params['itemobject_id'] = $proc_io;
                    $edit_url = $navigator->getCurrentViewUrl('itemview', 'struct', $matrix_query_params);
                    $title = date('M j, Y G:i', strtotime($proc_effective_date)).' - '.(isset($this->fields[$key]['display']) ? $this->fields[$key]['display'] : '');
                    $out[$key][] = linkify($edit_url, DBTableRowItemVersion::renderDisposition($this->dbtable->getFieldType('iv__disposition'), $proc_disposition, true, '<span class="disposition Black">No Disposition</span>'), $title);
                }
            }
            // this outputs the procedures into each cell, but colors background dark if the wrong category (typeobject_id)
            foreach ($this->_proc_matrix_column_keys as $key) {
                if (isset($out[$key])) {
                    $detail_out[$key] = implode(' ', $out[$key]);
                }
                if ($record_is_not_selected_category) {
                    $detail_out['td_class'][$key] = 'na';
                }
            }
            foreach ($out as $key => $disp_array) {
                $detail_out[$key] = '<div class="cellofprocs">'.implode(' ', $disp_array).'</div>';
            }
        }
        $detail_out['iv__disposition'] = DBTableRowItemVersion::renderDisposition($this->dbtable->getFieldType('iv__disposition'), $record['iv__disposition']);
        if ($need_to_load_ItemVersion && isset($errormsg['disposition'])) {
            $detail_out['td_class']['iv__disposition'] = 'cell_error';
        }

        $detail_out['tr_class'] .= DBTableRow::wasItemTouchedRecently('itemversion'.$record['typeobject_id'], $record['iv__itemversion_id']) ? ' '.$this->last_select_class : '';
        $recently_changed_row = script_time() - strtotime($record['last_change_date']) < $this->_recent_row_age;
        if ($recently_changed_row) {
            $detail_out['tr_class'] .= ' recently_changed_row';
            $detail_out['td_class']['last_change_date'] = 'em';
        }

        if (is_numeric($this->_dashboardtable_id)) {
            $edit_btn = !$this->_readonly ? '<div class="bd-edit"><a href="#" class="columnnoteeditbtn minibutton2">Edit</a></div>' : '';
            $fname = '__column_notes__';
            $detail_out[$fname] = '<div class="dash-column-container" data-itemobject_id="'.$record['itemobject_id'].'" data-dashboard_id="'.$this->_dashboardtable_id.'">'.$edit_btn.'<div class="dash-column-content">'.text_to_unwrappedhtml($record[$fname]).'</div></div>';
            if ($record[$fname]) {
                $detail_out['td_class'][$fname] = 'notebkg';
            }

            foreach ($this->_extracolumnnotetables as $dashboardtable_id => $dashboardcol) {
                $edit_btn = '';
                $fname = 'column_notes_'.$dashboardtable_id;
                $detail_out[$fname] = '<div class="dash-column-container" data-itemobject_id="'.$record['itemobject_id'].'" data-dashboard_id="'.$dashboardtable_id.'">'.$edit_btn.'<div class="dash-column-content">'.text_to_unwrappedhtml($record[$fname]).'</div></div>';
                if ($record[$fname]) {
                    $detail_out['td_class'][$fname] = 'notebkg';
                }
            }

            $subcomments_html = '';
            $comment_add_btn = Zend_Registry::get('customAcl')->isAllowed($_SESSION['account']->getRole(), 'table:comment', 'add')
                    ? linkify($navigator->getCurrentViewUrl('commenteditview', 'struct', array('table' => 'comment', 'comment_id' => 'new', 'return_url' => $navigator->getCurrentViewUrl(), 'initialize' => array('itemobject_id' => $record['itemobject_id']))), 'Add', 'add a new comment, photos, or documents to the event stream', 'minibutton2')
                    : '';
            $subcomments_html .= '<div class="dash-comments-container"><div class="bd-edit">'.$comment_add_btn.'</div><div class="dash-column-content"><ul class="bd-event-subcomments">';
            if ($record['__comments__']) {
                $is_first = true;
                $comments_arr = explode('|', $record['__comments__']);
                if (count($comments_arr) > $this->_dash_comments_limit) {
                     $subcomments_html .= '<li class="bd-event-subcomment dash-no-hrule"><span class="paren">'.(count($comments_arr) - $this->_dash_comments_limit).' older comment(s) not shown</span></li>';
                     $is_first = false;
                     $comments_arr = array_slice($comments_arr, count($comments_arr) - $this->_dash_comments_limit, $this->_dash_comments_limit, true);
                }
                foreach ($comments_arr as $bare_idx => $subcomment) {
                    list($comment_id, $user_id, $comment_added, $comment_text, $subdocuments_packed) = explode('&', $subcomment);
                    $subdatetime = time_to_bulletdate(strtotime($comment_added));
                    $comment_text = hextobin($comment_text); // we had packed this earlier for safety
                    list($comment_html,$comment_text_array) = EventStream::textToHtmlWithEmbeddedCodes($comment_text, $navigator, 'ET_COM', false);
                    if ($is_first) {
                        $hrule_class = 'bd-event-subcomment dash-no-hrule';
                        $is_first = false;
                    } else {
                        $hrule_class = 'bd-event-subcomment';
                    }
                    $subcomments_html .= '<li class="'.$hrule_class.'">
                            <div class="bd-subcomment-who-message">
                            <span class="bd-subcomment-byline">'.DBTableRowUser::concatNames($this->_user_records[$user_id]).':</span>'.$comment_html.'
                                    </div>
                                    ';
                    if ($subdocuments_packed) {
                        $subcomments_html .= '<div class="bd-subcomment-documents">';
                        $subcomments_html .= EventStream::documentsPackedToFileGallery(Zend_Controller_Front::getInstance()->getRequest()->getBaseUrl(), 'id_'.$this->_dashboardtable_id.'_'.$record['itemobject_id'].'_'.$bare_idx, $subdocuments_packed);
                        $subcomments_html .= '</div>';
                    }
                    $subcomments_html .= '<div class="bd-subcomment-when">'.$subdatetime.'</div></li>';
                }
                $subcomments_html .= '</ul></div></div>';
            }
            $detail_out['__comments__'] = $subcomments_html;


        }


    }

    public function make_export_detail($queryvars, &$record, &$detail_out)
    {
        foreach ($this->csvfields as $field => $description) {
            $detail_out[$field] = isset($record[$field]) ? $record[$field] : null;
        }
        $ItemVersion = new DBTableRowItemVersion(false, null);
        $ItemVersion->getRecordById($record['iv__itemversion_id']);
        foreach ($ItemVersion->getFieldTypes() as $fieldname => $fieldtype) {
            if (isset($ItemVersion->{$fieldname}) && (trim($ItemVersion->{$fieldname})!=='')) {
                if ($fieldtype['type']=='component') {
                    $value_array = $ItemVersion->getComponentValueAsArray($fieldname);
                    $detail_out[$fieldname] = $value_array[$ItemVersion->{$fieldname}];
                } else {
                    $detail_out[$fieldname] = $ItemVersion->{$fieldname};
                }

                // if this is a component, then we want to drill deep and get subfields.
                if ($fieldtype['type']=='component') {
                    $SubIV = new DBTableRowItemVersion(false, null);
                    $SubIV->getCurrentRecordByObjectId($ItemVersion->{$fieldname}, $ItemVersion->effective_date);
                    foreach ($SubIV->getFieldTypes() as $subfieldname => $subfieldtype) {
                        if ($subfieldtype['type']=='component') {
                            $value_array = $SubIV->getComponentValueAsArray($subfieldname);
                            $detail_out[DBTableRowTypeVersion::formatSubfieldPrefix($fieldname, $SubIV->tv__typeobject_id).'.'.$subfieldname] = isset($SubIV->{$subfieldname}) ? $value_array[$SubIV->{$subfieldname}] : null;
                        } elseif (($subfieldname == 'user_id') && isset($this->export_user_records[$SubIV->user_id])) {
                            $detail_out[DBTableRowTypeVersion::formatSubfieldPrefix($fieldname, $SubIV->tv__typeobject_id).'.'.$subfieldname] = $this->export_user_records[$SubIV->user_id]['login_id'];
                        } else {
                            $detail_out[DBTableRowTypeVersion::formatSubfieldPrefix($fieldname, $SubIV->tv__typeobject_id).'.'.$subfieldname] = $SubIV->{$subfieldname};
                        }
                    }
                }
            }
        }

        if ($this->_show_used_on) {
            $wu_links = array();
            foreach (explode(';', $record['used_on_packed']) as $wu) {
                $fields = explode(',', $wu);
                if (count($fields)==4) {
                    list($wu_itemobject_id, $wu_hex_item_serial_number, $wu_partnumber_alias, $wu_hex_type_description) = $fields;
                    $wu_links[] = hextobin($wu_hex_item_serial_number);
                }
            }
            $detail_out['used_on'] = implode(', ', $wu_links);
        }

        if (count($this->_proc_matrix_column_keys)>0) {
            $out = array();
            $ref_recs = !empty($record['all_procedure_object_ids']) ? explode(';', $record['all_procedure_object_ids']) : array();
            foreach ($ref_recs as $ref_rec) {
                list($proc_to,$proc_io,$proc_disposition,$proc_effective_date) = explode(',', $ref_rec);
                $key = 'ref_procedure_typeobject_id_'.$proc_to;
                if (!isset($out[$key])) {
                    $out[$key] = array();
                }
                $out[$key][] = DBTableRowItemVersion::renderDisposition($this->dbtable->getFieldType('iv__disposition'), $proc_disposition, false, 'N/A');
            }
            foreach ($out as $key => $disp_array) {
                $detail_out[$key] = implode(';', $disp_array);
            }
        }

        if (isset($this->export_user_records[$ItemVersion->user_id])) {
            $detail_out['user_id'] = $this->export_user_records[$ItemVersion->user_id]['login_id'];
        }

        return true;
    }


}

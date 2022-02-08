<?php
/**
 *
 * PartForge Enterprise Groupware for recording parts and assemblies by serial number and version along with associated test data and comments.
 *
 * Copyright (C) 2013-2022 Randall C. Black <randy@blacksdesign.com>
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
class DBTableRowItemVersion extends DBTableRow {


    /*
         * types added by this class in the fieldtypes array: "component"
         *
         * this holds the most recently loaded list of field names and layout that came from the datadictionary
         * keys are 'typeversion_id', 'addon_property_fields', 'addon_component_fields', 'addon_component_subfields', 'dictionary_field_layout'
     */
    public $_navigator;
    private $_typeversion_digest = array();
    private $_last_loaded_component_objects = array(); // this is a list of recently loaded components, mostly loaded in OnAfterGetRecord() meth.

    public function __construct($ignore_joins = false, $parent_index = null)
    {
        parent::__construct('itemversion', $ignore_joins, $parent_index);
        $this->user_id = $_SESSION['account']->user_id;
        $this->proxy_user_id = ($_SESSION['account']->getRole()=='DataTerminal') ? $_SESSION['account']->user_id : LOGGED_IN_USER_IS_CREATOR;
        if ($_SESSION['account']->getRole()=='DataTerminal') {
            $this->effective_date = time_to_mysqldatetime(script_time());
        }
        $this->itemobject_id = 'new';
    }

    public function assignFromFormSubmission($in_params, &$merge_params)
    {

        // since only fields that have defined types in getFieldTypes() are merged in, we need to make sure they are there first
        $typeversion_id = $this->typeversion_id;
        if ($merge_params['typeversion_id']) {
            $typeversion_id = $merge_params['typeversion_id'];
        }
        if (isset($in_params['typeversion_id'])) {
            $typeversion_id = $in_params['typeversion_id'];
        }

        $this->refreshLoadedTypeVersionFields($typeversion_id);

        return parent::assignFromFormSubmission($in_params, $merge_params);
    }

    /**
     * attempt to perform initialization of any component values that are based on any already initialized components
     * the idea is to locate any initialized components and then query those objects for initialized components
     * Then based on the names and typeobject_ids of these, fill in any components in the current object which happen to match.
     */
    private function tryInitializingComponentsByDrilling()
    {

        /*
             * First, find all the components of the already initialized components and keep an exhaustive list as well as
            * a list by typeobject_id which we will use as a last resort.  Note that this is not very efficient if we have
            * to call this function multiple times since we are calling getCurrentRecordByObjectId() on the same objects
            * over and over.
        */

        $component_values_of_components_by_itemobject_id = array();
        $component_values_of_components = array(); // contains full list of set component values within components with multiples too
        foreach ($this->getCurrentlySetComponentValues() as $component_field_name => $itemobject_id) {
            $ItemVersion = new DBTableRowItemVersion(false, null);
            if ($ItemVersion->getCurrentRecordByObjectId($itemobject_id)) {
                $fieldtypes_of_component = $ItemVersion->getFieldTypes();
                foreach ($ItemVersion->getCurrentlySetComponentValues() as $comp_of_comp_name => $comp_of_comp_itemobject_id) {
                    foreach ($fieldtypes_of_component[$comp_of_comp_name]['can_have_typeobject_id'] as $typeobject_id) {
                        $component_values_of_components[] = array('fieldname' => $comp_of_comp_name, 'typeobject_id' => $typeobject_id, 'itemobject_id' => $comp_of_comp_itemobject_id);
                        $component_values_of_components_by_itemobject_id[$typeobject_id] = $comp_of_comp_itemobject_id;
                    }
                }
            }
        }

        /*
             * Now that we have a list of initialized component values in the already-initialized components, we want to map
            * those into any uninitialized components in the current item.  We first give priority to matches by type and name.
        */
        $fieldtypes_of_self = $this->getFieldTypes();
        foreach ($this->getCurrentlyUnsetComponentNames() as $component_field_name) {
            foreach ($fieldtypes_of_self[$component_field_name]['can_have_typeobject_id'] as $typeobject_id) {
                if (isset($component_values_of_components_by_itemobject_id[$typeobject_id])) {
                    foreach ($component_values_of_components as $idx => $name_type_val) {
                        if (($component_field_name==$name_type_val['fieldname']) && ($typeobject_id==$name_type_val['typeobject_id'])) {
                            $this->{$component_field_name} = $name_type_val['itemobject_id'];
                            // we unset these to possibly avoid reusing the same ones (though we can alway fall back to the $component_values_of_components_by_itemobject_id)
                            unset($component_values_of_components[$idx]);
                            break;
                        }
                    }
                }
            }
        }

        /*
             * Now that we've done any matches possible with name + typeobject_id, we will simply match by type only
            * This time through there should be fewer unsetcomponents
        */
        foreach ($this->getCurrentlyUnsetComponentNames() as $component_field_name) {
            foreach ($fieldtypes_of_self[$component_field_name]['can_have_typeobject_id'] as $typeobject_id) {
                if (isset($component_values_of_components_by_itemobject_id[$typeobject_id])) {
                    $found = false;
                    foreach ($component_values_of_components as $idx => $name_type_val) {
                        if ($typeobject_id==$name_type_val['typeobject_id']) {
                            $found = true;
                            $this->{$component_field_name} = $name_type_val['itemobject_id'];
                            // we unset these to possibly avoid reusing the same ones (though we can alway fall back to the $component_values_of_components_by_itemobject_id)
                            unset($component_values_of_components[$idx]);
                            break;
                        }
                    }
                    // if we got here not found, then we have more items to initialize than present in the source, so just use any one with matching typeobject_id
                    if (!$found) {
                        $this->{$component_field_name} = $component_values_of_components_by_itemobject_id[$typeobject_id];
                    }
                }
            }
        }
    }


    /**
    * The input is assumed to be query variables from an initialize[] array.
    * This would be a typical step when initializing the tablerow object
    * for a new (unsaved) record.  It is overridden here so that we can
    * decide if there are component_subfields that need to be initialized.
    * This would be the case if we are creating a new item (probably procedure)
    * and initializing a component value and there also happens to be component_subfields
    * corresponding to that component.  In that case, the subfield value needs to be
    * initialized with the corresponding value in the component by calling reloadComponent().
    */
    public function processPostedInitializeVars($initialize_array)
    {
        parent::processPostedInitializeVars($initialize_array);

        // only want to do this if
        if (!$this->isSaved()) {
            if (isset($initialize_array['typeversion_id']) && is_numeric($initialize_array['typeversion_id'])) {
                // just to be sure the type info is initialized...
                $this->refreshLoadedTypeVersionFields($initialize_array['typeversion_id']);


                // iteratively attempt to get all the possible components initialized
                $starting_count = count($this->getCurrentlyUnsetComponentNames());
                do {
                    $try_again = false;
                    if ($starting_count>0) {
                        $this->tryInitializingComponentsByDrilling();
                        $ending_count = count($this->getCurrentlyUnsetComponentNames());
                        $changed = $ending_count != $starting_count;
                        if ($changed) {
                            $starting_count = $ending_count;
                            $try_again = true;
                        }
                    }
                } while ($try_again);


                // If any of the initialize vars are components with subfields present, then initialized the subfield values with the component db values.
                foreach ($this->_typeversion_digest['components_in_defined_subfields'] as $component_field_name) {
                    // if we are actually initializing a component, then...
                    if (isset($initialize_array[$component_field_name]) && is_numeric($initialize_array[$component_field_name])) {
                        foreach ($this->reloadComponent($component_field_name) as $field => $value) {
                            $this->{$field} = $value;
                        }
                    }
                }
            }
        }
    }

    public function getCoreDescription()
    {
        return $this->item_serial_number;
    }



    /**
     * This will take an input variable and normalize (convert to standard form) it
     * so that we can, for example, we can compare datetime "01/06/2012 10:33" and "01/06/2012 10:33:00"
     * and know that they are the same. This function is very similar to DbSchema::varToEscapedMysqlLiteral().
     */
    static public function varToStandardForm($var, $fieldtype)
    {
        $is_null_str = (($var==='') || is_null($var));
        $type = isset($fieldtype['type']) ? $fieldtype['type'] : '';
        if ($type == 'datetime') {
            $lit = !$is_null_str ? time_to_mysqldatetime(strtotime($var)) : null;
        } else if ($type == 'date') {
            $lit = !$is_null_str ? time_to_mysqldate(strtotime($var)) : null;
        } else if ($type == 'int') {
            $lit = !$is_null_str ? round($var) : null;
        } else if (in_array($type, array('float','calculated'))) {
            $lit = !$is_null_str ? (float) $var : null;
        } else if ($type == 'boolean') {
            $lit = !$is_null_str ? (boolean) $var : null;
        } else if (is_array($var)) {
            $lit = serialize($var);
        } else {
            $lit = trim($var);
        }
        return $lit;
    }

    /**
     * packs the fields that are properties into a single field.,
     * Returns an array of two values, a json_encoded item_data field that contains packed properties
     * and also an array of fieldnames that were used in this
     * transformation.
     */
    public function propertyFieldsToItemData()
    {

        // this is the last chance to make sure the calculated fields get processed before saving.
        $this->processCalculatedFields();

        // special handling for the field defined in the typeversion record.
        $item_data = array();
        $fieldnames_converted = array();
        // check all dictionary properties
        foreach ($this->_typeversion_digest['addon_property_fields'] as $dictionary_field_name) {
            $item_data[$dictionary_field_name] = self::varToStandardForm(isset($this->_fields[$dictionary_field_name]) ? $this->_fields[$dictionary_field_name] : null, $this->getFieldType($dictionary_field_name));
            if (!in_array($dictionary_field_name, $fieldnames_converted)) {
                $fieldnames_converted[] = $dictionary_field_name;
            }
        }

        $item_data = !empty($item_data) ?  json_encode($item_data) : '';
        return array($item_data,$fieldnames_converted);
    }

    public function getExportFieldTypes()
    {
        $out = array();
        $header_fields = array('typeversion_id','effective_date','record_created','itemobject_id','itemversion_id','user_id','login_id');
        if ($this->hasASerialNumber()) {
            $header_fields[] = 'item_serial_number';
        }
        if ($this->hasADisposition()) {
            $header_fields[] = 'disposition';
        }
        if ($this->hasAliases()) {
            $header_fields[] = 'partnumber_alias';
            $header_fields[] = 'part_number';
        }
        foreach ($header_fields as $header_field) {
            $out[$header_field] = $this->getFieldType($header_field);
        }

        if ($this->hasDictionaryOverrides()) {
            $out['dictionary_overrides'] = $this->getFieldType('dictionary_overrides');
        }

        foreach ($this->getComponentFieldNames() as $fieldname) {
            $out[$fieldname] = $this->getFieldType($fieldname);
        }
        foreach ($this->getComponentSubFieldNames() as $fieldname) {
            $out[$fieldname] = $this->getFieldType($fieldname);
        }
        foreach ($this->_typeversion_digest['addon_property_fields'] as $dictionary_field_name) {
            $out[$dictionary_field_name] = $this->getFieldType($dictionary_field_name);
        }
        foreach ($this->_typeversion_digest['addon_attachment_fields'] as $dictionary_field_name) {
            $out[$dictionary_field_name] = $this->getFieldType($dictionary_field_name);
        }
        return $out;
    }

    /**
     * Returns field type arrays for those fields with the featured property set to 1
     */
    public function getFeaturedFieldTypes()
    {
        return DBTableRowTypeVersion::filterFeaturedFieldTypes($this->getFieldTypes());
    }


    public function getAddOnFieldNames()
    {
        $out = $this->_typeversion_digest['addon_property_fields'];
        $out = array_merge($out, $this->_typeversion_digest['addon_attachment_fields']);
        $out = array_merge($out, $this->_typeversion_digest['addon_component_fields']);
        $out = array_merge($out, $this->_typeversion_digest['addon_component_subfields']);
        if (!Zend_Registry::get('config')->pass_disposition_required_error_free) {
            $out[] = 'disposition';
        }
        return $out;
    }

    public function getComponentFieldNames()
    {
        $this->refreshLoadedTypeVersionFields($this->typeversion_id);
        return $this->_typeversion_digest['addon_component_fields'];
    }

    public function getFieldAttachmentFieldNames()
    {
        $this->refreshLoadedTypeVersionFields($this->typeversion_id);
        return $this->_typeversion_digest['addon_attachment_fields'];
    }

    public function getComponentSubFieldNames()
    {
        $this->refreshLoadedTypeVersionFields($this->typeversion_id);
        return $this->_typeversion_digest['addon_component_subfields'];
    }

    /**
     * Returns component values with name as key and itemobject_id as value.
     */
    public function getCurrentlySetComponentValues()
    {
        $out = array();
        foreach ($this->getComponentFieldNames() as $fieldname) {
            if (isset($this->{$fieldname}) && $this->{$fieldname}) {
                $out[$fieldname] = $this->{$fieldname};
            }
        }
        return $out;
    }

    public function getCurrentlySetFieldAttachmentValues()
    {
        $out = array();
        foreach ($this->getFieldAttachmentFieldNames() as $fieldname) {
            if (isset($this->{$fieldname}) && $this->{$fieldname}) {
                $out[$fieldname] = $this->{$fieldname};
            }
        }
        return $out;
    }


    /**
     * Return a list of component names that are not currently set to any value
     * @return array of component fieldnames
     */
    public function getCurrentlyUnsetComponentNames()
    {
        $out = array();
        foreach ($this->getComponentFieldNames() as $fieldname) {
            if (!isset($this->{$fieldname}) || !$this->{$fieldname}) {
                $out[] = $fieldname;
            }
        }
        return $out;
    }

    /**
     * For each component, find it's created-on date.  Then find the latest of all these.  The returned
     * date is used to find the latest effective date that is allowed for the current itemversion.
     * That is, you can't have an effective date that's before any of the components were created.
     */
    public function getLatestOfComponentCreatedDates()
    {
        $latest_time = null;
        $component_itemobject_ids = $this->getCurrentlySetComponentValues();
        if (!empty($component_itemobject_ids)) {
            $records = DbSchema::getInstance()->getRecords('', "
						SELECT max(tb.min_date) as max_date
						FROM (SELECT itemobject_id, min(effective_date) as min_date FROM itemversion WHERE itemobject_id IN (".implode(',', $component_itemobject_ids).") GROUP BY itemobject_id) as tb
					");
            if (!empty($records)) {
                $time = $records[0]['max_date'];
                return strtotime($time);
            }
        }
        return $latest_time;
    }

    /**
     * make sure the effective_date is recent enough to be consistent with the components.
     * If it is not, then change it.
     */
    public function ensureEffectiveDateValid()
    {
        $latest_component_date = $this->getLatestOfComponentCreatedDates();
        if ($this->effective_date && (strtotime($this->effective_date) != -1) && $latest_component_date && ($latest_component_date > strtotime($this->effective_date))) {
            $this->effective_date = date("m/d/Y H:i", $latest_component_date);
        }
    }

    /**
     * give this method another object of the same type, and it returns true if there are any differences
     * including effective_date and components.
     */
    public function checkDifferencesFrom(self $CompareItem)
    {
        $something_has_changed = false;

        list($this_item_data,$fieldnames_converted)     = $this->propertyFieldsToItemData();
        list($compare_item_data,$fieldnames_converted)  = $CompareItem->propertyFieldsToItemData();

        if ( (strtotime($this->effective_date)!=strtotime($CompareItem->effective_date)) || ($this->partnumber_alias!=$CompareItem->partnumber_alias) || ($this->item_serial_number!=$CompareItem->item_serial_number) || ($this->disposition!=$CompareItem->disposition) || ($this_item_data!=$compare_item_data)
            || ($this->typeversion_id!=$CompareItem->typeversion_id)
            || (count($this->getCurrentlySetComponentValues())!=count($CompareItem->getCurrentlySetComponentValues()))
            || (count($this->getCurrentlySetFieldAttachmentValues())!=count($CompareItem->getCurrentlySetFieldAttachmentValues())) ) {
            $something_has_changed = true;
        } else {
            $saved_components = $CompareItem->getCurrentlySetComponentValues();
            foreach ($this->getCurrentlySetComponentValues() as $fieldname => $itemobject_id) {
                if ($saved_components[$fieldname]!=$itemobject_id) {
                    $something_has_changed = true;
                    break;
                }
            }
            $saved_attachments = $CompareItem->getCurrentlySetFieldAttachmentValues();
            foreach ($this->getCurrentlySetFieldAttachmentValues() as $fieldname => $comment_id) {
                if ($saved_attachments[$fieldname]!=$comment_id) {
                    $something_has_changed = true;
                    break;
                }
            }
        }
        return $something_has_changed;
    }

    public function getArchiveEditChangesArray()
    {
        return self::ArchiveEditChanges($this->itemversion_id, $this->_navigator);
    }

    /**
     * This returns an array contains a description of the changes in the itemversionarchive table.
     * @param unknown_type $itemversion_id
     * @return multitype:multitype:string Ambigous <string, unknown>
     */
    public static function ArchiveEditChanges($itemversion_id, $Navigator = null)
    {
        $ItemVersionCurr = new DBTableRowItemVersion();
        $ItemVersionCurr->getRecordById($itemversion_id);
        $curr_record_created = $ItemVersionCurr->record_created;
        $curr_user_id = $ItemVersionCurr->user_id;

        // this is need to create navigable links in the descriptions of what changed
        $arc_records = DbSchema::getInstance()->getRecords('itemversionarchive_id', "SELECT * FROM itemversionarchive WHERE itemversion_id='{$itemversion_id}' ORDER BY record_created desc");
        $out = array();
        $entry_count = 0;
        $original_entry = "";
        foreach ($arc_records as $arc_record) {
            $entry_count++;
            $is_last_record = $entry_count == count($arc_records);
            $description_html = $arc_record['changes_html'];
            if ($is_last_record) {
                $original_entry = array(
                        'date' => time_to_bulletdate(strtotime($arc_record['original_record_created']), false),
                        'name' => TextToHtml(strtoupper(DBTableRowUser::getFullName($arc_record['cached_user_id']))),
                        'differences' => 'New Version',
                );
            }
            $out[] = array(
                    'date' => time_to_bulletdate(strtotime($curr_record_created), false),
                    'name' => TextToHtml(strtoupper(DBTableRowUser::getFullName($curr_user_id))),
                    'differences' => $description_html,
                    );

            $curr_record_created = $arc_record['original_record_created'];
            $curr_user_id = $arc_record['cached_user_id'];
        }
        if ($original_entry) {
            $out[] = $original_entry;
        }
        return $out;
    }

    /**
     * give this method another object of the same type, and it finds any differences
     * and output them as a textual description of the change.  This is used in generating
     * the difference messages in the EventStream.
     * As an alternate output format (for decorating the table view), the output is generated
     * as an array indexed by field name if $output_by_fieldname is true.
     */
    public function itemDifferencesFrom(self $CompareItem, $say_more_about_starting_state = false, $output_by_fieldname = false)
    {
        $list = array();

        // check the header items

        $this_part_number = $this->hasAliases() ? $this->formatPrintField('part_number', false) : '';
        $compareitem_part_number = $CompareItem->hasAliases() ? $CompareItem->formatPrintField('part_number', false) : '';
        if ($this_part_number!=$compareitem_part_number) {
            if ($output_by_fieldname) {
                $list['part_number'] = "set to '".$this_part_number."'";
            } else {
                $list[] = "<b>Part Number Alias</b> changed from '".$compareitem_part_number."' to '".$this_part_number."'";
            }
        }
        if ($this->disposition!=$CompareItem->disposition) {
            if ($output_by_fieldname) {
                $list['disposition'] = "set to '".$this->formatPrintField('disposition', false)."'";
            } else {
                $list[] = "<b>Disposition</b> changed from '".$CompareItem->formatPrintField('disposition', false)."' to '".$this->formatPrintField('disposition', false)."'";
            }
        }
        if ($this->item_serial_number!=$CompareItem->item_serial_number) {
            if ($output_by_fieldname) {
                $list['item_serial_number'] = "set to '".$this->formatPrintField('item_serial_number', false)."'";
            } else {
                $list[] = "<b>Serial Number</b> changed from '".$CompareItem->formatPrintField('item_serial_number', false)."' to '".$this->formatPrintField('item_serial_number', false)."'";
            }
        }
        if ($this->typeversion_id!=$CompareItem->typeversion_id) {
            if ($output_by_fieldname) {
                $list['typeversion_id'] = "set to '".$this->formatPrintField('typeversion_id', false)."'";
            } else {
                $list[] = "<b>Type Version</b> changed from '".$CompareItem->formatPrintField('typeversion_id', false)."' to '".$this->formatPrintField('typeversion_id', false)."'";
            }
        }

        // get all the fieldnames

        $this_fieldnames = array();
        $compare_fieldnames = array();

        // properties
        list($this_item_data,$this_property_fieldnames)    = $this->propertyFieldsToItemData();
        list($compare_item_data,$compare_property_fieldnames)  = $CompareItem->propertyFieldsToItemData();
        $this_fieldnames = array_unique(array_merge($this_fieldnames, $this_property_fieldnames));
        $compare_fieldnames = array_unique(array_merge($compare_fieldnames, $compare_property_fieldnames));

        // attachments
        $this_attachment_fieldnames = array_keys($this->getCurrentlySetFieldAttachmentValues());
        $compare_attachment_fieldnames = array_keys($CompareItem->getCurrentlySetFieldAttachmentValues());
        $this_fieldnames = array_unique(array_merge($this_fieldnames, $this_attachment_fieldnames));
        $compare_fieldnames = array_unique(array_merge($compare_fieldnames, $compare_attachment_fieldnames));

        // components
        $this_component_fieldnames = array_keys($this->getCurrentlySetComponentValues());
        $compare_component_fieldnames = array_keys($CompareItem->getCurrentlySetComponentValues());
        $this_fieldnames = array_unique(array_merge($this_fieldnames, $this_component_fieldnames));
        $compare_fieldnames = array_unique(array_merge($compare_fieldnames, $compare_component_fieldnames));

        // now lets do messages for fields (of all types) that were DELETED
        $deleted = array_diff($compare_fieldnames, $this_fieldnames);
        foreach ($deleted as $fieldname) {
            if (in_array($fieldname, $compare_property_fieldnames)
                || in_array($fieldname, $compare_attachment_fieldnames)) {
                $compare_value = $CompareItem->formatPrintField($fieldname, false);
                if ($output_by_fieldname) {
                    checkWasChangedItemFieldByFieldname($list, $fieldname, $compare_value, null);
                } else {
                    checkWasChangedItemField($list, $CompareItem->formatFieldnameNoColon($fieldname), $compare_value, null);
                }
            } else if (in_array($fieldname, $compare_component_fieldnames)) {
                if ($say_more_about_starting_state) {
                    $itemversion_id = DBTableRowItemVersion::getItemVersionIdFromByObjectId($CompareItem->{$fieldname}, $CompareItem->effective_date);
                    $compare_value = $CompareItem->formatPrintField($fieldname, false).": <itemversion>{$itemversion_id}</itemversion>";
                } else {
                    $compare_value = $CompareItem->formatPrintField($fieldname, false);
                }
                if ($output_by_fieldname) {
                    checkWasChangedItemFieldByFieldname($list, $fieldname, $compare_value, null);
                } else {
                    checkWasChangedItemField($list, $CompareItem->formatFieldnameNoColon($fieldname), $compare_value, null);
                }
            }
        }

        // now fields that were ADDED
        $added = array_diff($this_fieldnames, $compare_fieldnames);
        foreach ($added as $fieldname) {
            if (in_array($fieldname, $this_property_fieldnames)
                || in_array($fieldname, $this_attachment_fieldnames)) {
                $this_value = $this->formatPrintField($fieldname, false);
                if ($output_by_fieldname) {
                    checkWasChangedItemFieldByFieldname($list, $fieldname, null, $this_value);
                } else {
                    checkWasChangedItemField($list, $this->formatFieldnameNoColon($fieldname), null, $this_value);
                }
            } else if (in_array($fieldname, $this_component_fieldnames)) {
                if ($say_more_about_starting_state) {
                    $this_value = $this->formatPrintField($fieldname, false);
                } else {
                    $itemversion_id = DBTableRowItemVersion::getItemVersionIdFromByObjectId($this->{$fieldname}, $this->effective_date);
                    $this_value = $this->formatPrintField($fieldname, false).": <itemversion>{$itemversion_id}</itemversion>";
                }
                if ($output_by_fieldname) {
                    checkWasChangedItemFieldByFieldname($list, $fieldname, null, $this->formatPrintField($fieldname, false));
                } else {
                    checkWasChangedItemField($list, $this->formatFieldnameNoColon($fieldname), null, $this_value);
                }
            }
        }

        // now fields that possibly CHANGED
        $maybe_changed = array_intersect($this_fieldnames, $compare_fieldnames);
        foreach ($maybe_changed as $fieldname) {
            if (in_array($fieldname, $this_property_fieldnames + $compare_property_fieldnames )
                    || in_array($fieldname, $this_attachment_fieldnames + $compare_attachment_fieldnames )) {
                $this_value = $this->formatPrintField($fieldname, false);
                $compare_value = $CompareItem->formatPrintField($fieldname, false);
                if ($output_by_fieldname) {
                    checkWasChangedItemFieldByFieldname($list, $fieldname, $compare_value, $this_value);
                } else {
                    checkWasChangedItemField($list, $CompareItem->formatFieldnameNoColon($fieldname), $compare_value, $this_value);
                }
            } else if (in_array($fieldname, $this_component_fieldnames + $compare_component_fieldnames )) {
                // I have to directly compare here instead of in checkWasChangedItemField since I'm decorating the start and finish values differently
                if ($this->{$fieldname} !== $CompareItem->{$fieldname}) {
                    if ($say_more_about_starting_state) {
                        $itemversion_id = DBTableRowItemVersion::getItemVersionIdFromByObjectId($CompareItem->{$fieldname}, $CompareItem->effective_date);
                        $compare_value = $CompareItem->formatPrintField($fieldname, false).": <itemversion>{$itemversion_id}</itemversion>";
                        $this_value = $this->formatPrintField($fieldname, false);
                    } else {
                        $itemversion_id = DBTableRowItemVersion::getItemVersionIdFromByObjectId($this->{$fieldname}, $this->effective_date);
                        $compare_value = $CompareItem->formatPrintField($fieldname, false);
                        $this_value = $this->formatPrintField($fieldname, false).": <itemversion>{$itemversion_id}</itemversion>";
                    }
                    if ($output_by_fieldname) {
                        checkWasChangedItemFieldByFieldname($list, $fieldname, $compare_value, $this->formatPrintField($fieldname, false));
                    } else {
                        checkWasChangedItemField($list, $CompareItem->formatFieldnameNoColon($fieldname), $compare_value, $this_value);
                    }
                }
            }
        }

        return $output_by_fieldname ? $list : (count($list)>1 ? '<ul class="changelist"><li>'.implode('</li><li>', $list).'</li></ul>' : implode(',', $list));
    }

    /**
     * if anything has changed, this saves a new version of the record rather than overwriting
     * function will raise an exception if an error occurs
     */
    public function saveVersioned($user_id = null, $handle_err_dups_too = true)
    {

        if ($user_id==null) {
            $user_id = $_SESSION['account']->user_id;
        }

        // start list of affected reference io
        $affected_itemobjects = $this->isSaved() ? self::getHasItemObjectsAsArray($this->itemversion_id) : array();

        $fieldnames = $this->getSaveFieldNames();

        $something_has_changed = false;

        /*
             * This tries to save the component subfields and returns true if any were saved.
             * It also sets the component select field values like $this->{$component_name}
         */
        $components_were_saved = $this->saveComponentSubFieldsVersioned($this->effective_date);
        $something_has_changed = $something_has_changed || $components_were_saved;

        /*
             * if this is a new item instance, then first create the item instance
             * record then save the record as a new record.
         */
        $Temp = null;
        $new_object = !$this->isSaved();
        if (!$this->isSaved()) {
            $ItemObject = new DBTableRow('itemobject');
            $ItemObject->save();
            $this->itemobject_id = $ItemObject->itemobject_id;
            $something_has_changed = true;
        } else {
            $Temp = new DBTableRowItemVersion(false, null);
            $Temp->getRecordById($this->itemversion_id);
            $has_differences = $this->checkDifferencesFrom($Temp);
            $something_has_changed = $something_has_changed || $has_differences;
        }


        /*
             * If the above checks indicate there's a change, then go ahead and save this as a new
             * record and create a new copy of the component pointers.
         */
        if ($something_has_changed) {
            /*
                 * pack property fields for saving
             */
            list($item_data,$fieldnames_converted) = $this->propertyFieldsToItemData();
            $fieldnames = array_diff($fieldnames, $fieldnames_converted);
            if (!in_array('item_data', $fieldnames)) {
                $fieldnames[] = 'item_data';
            }
            $this->_fields['item_data'] = $item_data;

            // create the cached serial number for saving
            if (in_array('item_serial_number', $fieldnames)) {
                if (!in_array('cached_serial_number_value', $fieldnames)) {
                    $fieldnames[] = 'cached_serial_number_value';
                }
                $this->_fields['cached_serial_number_value'] = $this->convertSerialNumberToOrdinal($this->item_serial_number);
            }


            $this->itemversion_id = 'new';
            $this->user_id = $user_id;
            $this->record_created = time_to_mysqldatetime(script_time());

            // don't try to save component fieldnames or fieldattachent fieldname
            $fieldnames = array_diff($fieldnames, $this->getComponentFieldNames(), $this->getComponentSubFieldNames(), $this->getFieldAttachmentFieldNames());

            parent::save($fieldnames, $handle_err_dups_too);

            $previous_rev_components = !is_null($Temp) ? $Temp->getCurrentlySetComponentValues() : array();
            // save all the itemcomponent records
            foreach ($this->getCurrentlySetComponentValues() as $fieldname => $itemobject_id) {
                $Comp = new DBTableRow('itemcomponent');
                $Comp->component_name = $fieldname;
                $Comp->belongs_to_itemversion_id = $this->itemversion_id;
                $Comp->has_an_itemobject_id = $itemobject_id;
                $Comp->save();

                // only report this if it is different than the previous rev
                if (!isset($previous_rev_components[$fieldname]) || ($previous_rev_components[$fieldname]!=$itemobject_id)) {
                    DBTableRowChangeLog::addedItemReference($itemobject_id, $this->itemversion_id);
                }
            }

            // save all the itemcomment records
            foreach ($this->getCurrentlySetFieldAttachmentValues() as $fieldname => $comment_id) {
                $FieldComm = new DBTableRow('itemcomment');
                $FieldComm->field_name = $fieldname;
                $FieldComm->belongs_to_itemversion_id = $this->itemversion_id;
                $FieldComm->has_a_comment_id = $comment_id;
                $FieldComm->save();

                $Comm = new DBTableRowComment();
                if ($Comm->getRecordById($comment_id)) {
                    $Comm->itemobject_id = $this->itemobject_id;
                    $Comm->save(array('itemobject_id'));
                }
            }


            $this->getRecordById($this->itemversion_id);
            self::updateCurrentItemVersionIds($this->itemobject_id);
            $_SESSION['most_recent_new_itemversion_id'] = $this->itemversion_id;

            // add any additional itemobjects that might need their last reference info updated
            $affected_itemobjects = array_merge($affected_itemobjects, self::getHasItemObjectsAsArray($this->itemversion_id));
            $affected_itemobjects[] = $this->itemobject_id;
            DBTableRowItemObject::updateCachedLastReferenceFields($affected_itemobjects);
            DBTableRowItemObject::updateCachedCreatedOnFields($this->itemobject_id);
            if ($this->hasASerialNumber()) {
                DBTableRowItemObject::invalidateValidationCacheOnAllWhereUsed($this->itemobject_id);
            }

            if ($new_object) {
                DBTableRowChangeLog::addedItemObject($this->itemobject_id, $this->itemversion_id);
            } else {
                DBTableRowChangeLog::addedItemVersion($this->itemobject_id, $this->itemversion_id);
            }
        }
    }

    /**
     * turns a formatted serial number into a simple ordinal integer.
     */
    public function convertSerialNumberToOrdinal($item_serial_number)
    {

        // we make sure this is a formatted serial number and that we have the fields to do our job
        $SNFormat = SerialNumberType::typeFactory($this->_typeversion_digest['serial_number_format']);
        $errormsg = array();
        $this->validateFields(array('item_serial_number'), $errormsg); // make sure the serial number is in an acceptible before bothering
        if (empty($errormsg)) {
            return $SNFormat->convertSerialNumberToOrdinal($item_serial_number);
        }
        return null;
    }

    /**
     * Use this instead of isSaved() in certain instances where you want to know if the next
     * call to save this object will result in a new item instance of an existing object, or
     * a completely new itemobject.
     * @return boolean
     */
    public function isExistingObject()
    {
        return is_numeric($this->itemobject_id);
    }


    /**
     * this is a traditional save.  It does not do any versioning.
     * It is called when the "correct this record" is pressed.
     * It deletes then resaves the itemcomponent records when called.  It also will save component itemversion records as
     * part of this.
     *
     * function will raise an exception if an error occurs
     */
    protected function saveUnversioned($fieldnames = array(), $handle_err_dups_too = true)
    {

        // start list of affected reference io
        $affected_itemobjects = $this->isSaved() ? self::getHasItemObjectsAsArray($this->itemversion_id) : array();

        // by default we will include all the fields here.
        if (count($fieldnames)==0) {
            $fieldnames = $this->getSaveFieldNames();
        }

        // pack property fields for saving
        list($item_data,$fieldnames_converted) = $this->propertyFieldsToItemData();
        $fieldnames = array_diff($fieldnames, $fieldnames_converted);
        if (!in_array('item_data', $fieldnames)) {
            $fieldnames[] = 'item_data';
        }
        $this->_fields['item_data'] = $item_data;

        // create the cached serial number for saving
        if (in_array('item_serial_number', $fieldnames)) {
            if (!in_array('cached_serial_number_value', $fieldnames)) {
                $fieldnames[] = 'cached_serial_number_value';
            }
            $this->_fields['cached_serial_number_value'] = $this->convertSerialNumberToOrdinal($this->item_serial_number);
        }


        // if this is a new item instance, then first create the itemobject record then save the record as a new record
        if (!$this->isSaved()) {
            $ItemObject = new DBTableRow('itemobject');
            $ItemObject->save();
            $this->itemobject_id = $ItemObject->itemobject_id;
        }

        // don't try to save component fieldnames or Field Attachments
        $fieldnames = array_diff($fieldnames, $this->getComponentFieldNames(), $this->getComponentSubFieldNames(), $this->getFieldAttachmentFieldNames());
        parent::save($fieldnames, $handle_err_dups_too);

        /*
             * save component subfields.
        */
        $this->saveComponentSubFieldsUnversioned();

        /*
            Save component itemcomponent records as needed.  Don't mindlessly delete and re-add, but
            check first to make sure there are actually changes.
        */

        $comp_records_to_delete = DbSchema::getInstance()->getRecords('itemcomponent_id', "SELECT * FROM itemcomponent WHERE belongs_to_itemversion_id='{$this->itemversion_id}'");
        $components_to_save = $this->getCurrentlySetComponentValues();   // $fieldname => $itemobject_id

        foreach ($comp_records_to_delete as $itemcomponent_id => $itemcomponent) {
            $component_name_on_disk = $itemcomponent['component_name'];
            // if this component is one we are going to turn around and recreated anyway, then remove it from both $comp_records_to_delete and $components_to_save
            if (isset($components_to_save[$component_name_on_disk]) && ($itemcomponent['has_an_itemobject_id']==$components_to_save[$component_name_on_disk])) {
                unset($comp_records_to_delete[$itemcomponent_id]);
                unset($components_to_save[$component_name_on_disk]);
            }
        }

        // delete any from the delete list that are still there
        foreach ($comp_records_to_delete as $itemcomponent_id => $itemcomponent) {
            $Comp = new DBTableRow('itemcomponent');
            $Comp->getRecordById($itemcomponent_id);
            $Comp->delete();
        }

        // save any that are left in the $component_to_save list
        foreach ($components_to_save as $fieldname => $itemobject_id) {
            $Comp = new DBTableRow('itemcomponent');
            $Comp->component_name = $fieldname;
            $Comp->belongs_to_itemversion_id = $this->itemversion_id;
            $Comp->has_an_itemobject_id = $itemobject_id;
            $Comp->save();
            DBTableRowChangeLog::addedItemReference($itemobject_id, $this->itemversion_id);
        }

        /*
            Save attachment types as needed, but don't mindlessly delete and resave
        */
        $records_to_delete = DbSchema::getInstance()->getRecords('itemcomment_id', "SELECT * FROM itemcomment WHERE belongs_to_itemversion_id='{$this->itemversion_id}'");
        $records_to_save = $this->getCurrentlySetFieldAttachmentValues();

        foreach ($records_to_delete as $itemcomment_id => $itemcomment) {
            $comment_name_on_disk = $itemcomment['field_name'];
            // if this is one we are going to turn around and recreated anyway, then remove it from both $records_to_delete and $records_to_save
            if (isset($records_to_save[$comment_name_on_disk]) && ($itemcomment['has_a_comment_id']==$records_to_save[$comment_name_on_disk])) {
                unset($records_to_delete[$itemcomment_id]);
                unset($records_to_save[$comment_name_on_disk]);
            }
        }

        // delete any from the delete list that are still there
        foreach ($records_to_delete as $itemcomment_id => $itemcomment) {
            $FieldComm  = new DBTableRow('itemcomment');
            $FieldComm ->getRecordById($itemcomment_id);
            $FieldComm ->delete();
        }

        // save any attachment types that are left in the $records_to_save list
        foreach ($records_to_save as $fieldname => $comment_id) {
            $FieldComm = new DBTableRow('itemcomment');
            $FieldComm->field_name = $fieldname;
            $FieldComm->belongs_to_itemversion_id = $this->itemversion_id;
            $FieldComm->has_a_comment_id = $comment_id;
            $FieldComm->save();

            $Comm = new DBTableRowComment();
            if ($Comm->getRecordById($comment_id)) {
                $Comm->itemobject_id = $this->itemobject_id;
                $Comm->save(array('itemobject_id'));
            }
        }


        $this->getRecordById($this->itemversion_id);
        self::updateCurrentItemVersionIds($this->itemobject_id);
        $_SESSION['most_recent_new_itemversion_id'] = $this->itemversion_id;

        // add any additional itemobjects that might need their last reference info updated
        $affected_itemobjects = array_merge($affected_itemobjects, self::getHasItemObjectsAsArray($this->itemversion_id));
        $affected_itemobjects[] = $this->itemobject_id;
        DBTableRowItemObject::updateCachedLastReferenceFields($affected_itemobjects);
        DBTableRowItemObject::updateCachedCreatedOnFields($this->itemobject_id);
        if ($this->hasASerialNumber()) {
            DBTableRowItemObject::invalidateValidationCacheOnAllWhereUsed($this->itemobject_id);
        }
    }

    /**
     * This loads the currently saved version of myself and compares to this class instance.
     * If it's different, say so, and also save an archive copy of the one on disk to the itemversionarchive table.
     * @return boolean
     */
    public function checkAndArchiveIfThisVersionHasChanges($ItemVersionOrig = null)
    {
        if (is_null($ItemVersionOrig)) {
            $ItemVersionOrig = new DBTableRowItemVersion();
            $ItemVersionOrig->getRecordById($this->itemversion_id);
        }
        $something_has_changed = $this->checkDifferencesFrom($ItemVersionOrig);
        if ($something_has_changed) {
            $record = DBTableRowItemObject::getItemObjectFullNestedArray($ItemVersionOrig->itemobject_id, $ItemVersionOrig->effective_date, 1, 0);
            $Arc = new DBTableRowItemVersionArchive();
            $Arc->itemversion_id = $record['itemversion_id'];
            $Arc->cached_user_id = $record['user_id'];
            $Arc->original_record_created = $record['record_created'];
            $event_description_array = array();
            $Arc->changes_html = EventStream::itemversionMarkupToHtmlTags($this->itemDifferencesFrom($ItemVersionOrig, true), null, 'ET_CHG', $event_description_array, false);
            $Arc->item_data = json_encode($record);
            $Arc->save();
        }
        return $something_has_changed;
    }

    /**
     * override of standard save. Calls the unversioned save function, but also updates the itemversionarchive
     * $user_id if set we will use that if something actually changed.  Otherwise we will use the default current user.  So if you don't want to change the user, better set it
     * @see DBTableRow::save()
     */
    public function save($fieldnames = array(), $handle_err_dups_too = true, $user_id = null)
    {
        if ($user_id==null) {
            $user_id = $_SESSION['account']->user_id;
        }
        $new_object = !$this->isSaved();
        if ($this->isSaved() && ($this->version_edit_mode != 'vem_finish_save_record')) {
            $something_has_changed = $this->checkAndArchiveIfThisVersionHasChanges();
            if ($something_has_changed) { // user and record_created should reflect the current user and time
                $this->user_id = $user_id;
                $this->record_created = time_to_mysqldatetime(script_time());
                DBTableRowChangeLog::changedItemVersion($this->itemobject_id, $this->itemversion_id);
            }
        }
        $this->saveUnversioned($fieldnames, $handle_err_dups_too);
        if ($new_object) {
            DBTableRowChangeLog::addedItemObject($this->itemobject_id, $this->itemversion_id);
        }
    }

    /**
     * Overridden to handle cleanup after deleting itemversion records, including deleting
     * the last one for an itemobject
     * @see DBTableRow::delete()
     */
    public function delete()
    {
        if ($this->hasASerialNumber()) {
            DBTableRowItemObject::invalidateValidationCacheOnAllWhereUsed($this->itemobject_id);
        }
        $itemobject_id = $this->itemobject_id;
        $itemversion_id = $this->itemversion_id;
        $typeversion_id = $this->typeversion_id;
        $partnumber_alias = $this->partnumber_alias;
        parent::delete();
        // gather all the itemobjects that will be affected by removing this itemversion.  These will be needed to refresh those itemobject fields
        $affected_itemobjects = self::getHasItemObjectsAsArray($itemversion_id);
        DbSchema::getInstance()->mysqlQuery("delete from itemcomponent where belongs_to_itemversion_id='{$itemversion_id}'");
        DbSchema::getInstance()->mysqlQuery("delete from itemcomment where belongs_to_itemversion_id='{$itemversion_id}'");
        DbSchema::getInstance()->mysqlQuery("delete from itemversionarchive where itemversion_id='{$itemversion_id}'");
        // if we have just deleted the last itemversion, then we should cleanup the whole itemobject
        $iv_records = DbSchema::getInstance()->getRecords('', "SELECT * FROM itemversion where itemobject_id='{$itemobject_id}'");
        if (count($iv_records)==0) {
            DbSchema::getInstance()->mysqlQuery("delete from itemobject where itemobject_id='{$itemobject_id}'");
            /*
                 * note that these next deletes should only do something under unusual circumstance,
                 * since normally we would not be allowed to delete when comments or references remained
             */
            DbSchema::getInstance()->mysqlQuery("delete from itemcomponent where has_an_itemobject_id='{$itemobject_id}'");
            DbSchema::getInstance()->mysqlQuery("delete from comment where itemobject_id='{$itemobject_id}'");
            DBTableRowChangeLog::deletedItemObject($itemobject_id, $typeversion_id, $partnumber_alias);
        } else {
            // these only make sense to update if $itemobject_id still exists
            DBTableRowItemObject::updateCachedCreatedOnFields($itemobject_id);
            self::updateCurrentItemVersionIds($itemobject_id);

            // now update reference fields for the new current itemversion_id
            $new_itemversion_id = self::getItemVersionIdFromByObjectId($itemobject_id);
            $affected_itemobjects = array_merge($affected_itemobjects, self::getHasItemObjectsAsArray($new_itemversion_id));
            $affected_itemobjects[] = $itemobject_id;
            DBTableRowChangeLog::deletedItemVersion($itemobject_id, $typeversion_id, $partnumber_alias, $new_itemversion_id);
        }
        DBTableRowItemObject::updateCachedLastReferenceFields($affected_itemobjects);
    }

    public static function getHasItemObjectsAsArray($itemversion_id)
    {
        return array_keys(DbSchema::getInstance()->getRecords('has_an_itemobject_id', "SELECT DISTINCT has_an_itemobject_id FROM itemcomponent WHERE belongs_to_itemversion_id='{$itemversion_id}'"));
    }

    /**
     * This updates cached_current_itemversion_id in itemobject table.  If $itemobject_id is specified, it will only
     * do this for the specified io.
     */
    static public function updateCurrentItemVersionIds($itemobject_id = null)
    {
        $where1 = is_null($itemobject_id) ? '' : " WHERE aa_io.itemobject_id='{$itemobject_id}'";
        DbSchema::getInstance()->mysqlQuery("
			UPDATE itemobject as cc_io,
        		# Table of all the current version itemversion_ids for each itemobject_id
				(SELECT bb_io.itemobject_id as bb_itemobject_id, bb_io.cached_current_itemversion_id as currently_cached_current_itemversion_id, bb_iv.itemversion_id as should_be_itemversion_id
				FROM itemobject bb_io
				LEFT JOIN itemversion bb_iv ON bb_io.itemobject_id = bb_iv.itemobject_id
				LEFT JOIN itemversion bb_iv_current ON bb_iv_current.itemversion_id = IFNULL(bb_io.cached_current_itemversion_id,0)
				LEFT JOIN
					(SELECT aa_io.itemobject_id as aa_itemobject_id, MAX(aa_iv.effective_date) as aa_max_effective_date
					FROM itemobject aa_io
					LEFT JOIN itemversion aa_iv ON aa_io.itemobject_id = aa_iv.itemobject_id
        			{$where1}
					GROUP BY aa_io.itemobject_id) as aa_io_max_dates ON aa_io_max_dates.aa_itemobject_id = bb_io.itemobject_id
				WHERE bb_iv.effective_date = aa_io_max_dates.aa_max_effective_date
				and IFNULL(bb_io.cached_current_itemversion_id,0)!=bb_iv.itemversion_id
				and bb_iv.effective_date!=IFNULL(bb_iv_current.effective_date,'0000-00-00')) as cc_corrected_itemobject
			SET cc_io.cached_current_itemversion_id=cc_corrected_itemobject.should_be_itemversion_id
			WHERE cc_io.itemobject_id=cc_corrected_itemobject.bb_itemobject_id
        			");

    }


    /**
     * This will return zero, one or more itemversion records matching the serial number that
     * correspond to the most current itemversion of an object on the given date.
     * @param unknown_type $serial_number
     * @param unknown_type $effective_date
     */
    static public function getRecordsBySerialNumbers($serial_number, $typeversion_id, $effective_date = null)
    {
        $date_where = is_null($effective_date) ? '(1=1)' : "(aa_iv.effective_date<='".time_to_mysqldatetime(strtotime($effective_date))."')";
        $records = DbSchema::getInstance()->getRecords('',
                "SELECT cc_iv.*
		FROM itemversion cc_iv
		LEFT JOIN
			(SELECT aa_date_comp.aa_itemobject_id as bb_itemobject_id, MAX(aa_date_comp.aa_effective_date) as bb_max_effective_date
			FROM
				(SELECT aa_iv.itemobject_id as aa_itemobject_id, aa_iv.itemversion_id as aa_itemversion_id, aa_iv.effective_date as aa_effective_date
				FROM itemversion aa_iv
				WHERE {$date_where}) as aa_date_comp
			GROUP BY aa_date_comp.aa_itemobject_id) as bb_date_comp ON bb_date_comp.bb_itemobject_id = cc_iv.itemobject_id
		WHERE (bb_date_comp.bb_max_effective_date=cc_iv.effective_date)
		 and (cc_iv.item_serial_number='".addslashes($serial_number)."') and (cc_iv.typeversion_id='{$typeversion_id}')");
        return $records;
    }

    /**
     * this will make sure the field cached_current_itemversion_id in the table is up to date.
     * It returns this value as well.
     */
    static public function updateCachedCurrentItemVersionId($itemobject_id)
    {
        $ItemObject = new DBTableRow('itemobject');
        if ($ItemObject->getRecordById($itemobject_id)) {
            $recs = DbSchema::getInstance()->getRecords('', "select itemversion_id, effective_date from itemversion where itemobject_id='{$itemobject_id}' order by effective_date desc, itemversion_id desc LIMIT 1");
            if (count($recs)==1) {
                $rec = reset($recs);
                $ItemObject->cached_current_itemversion_id = $rec['itemversion_id'];
                $ItemObject->save(array('cached_current_itemversion_id'));
                return $ItemObject->cached_current_itemversion_id;
            }
        }
        return null;
    }


    /**
     * Answers: in editviewdbAction(), what are the fields that should be passed into the validateFields() and save() methods?
     * This should include properties, components, and natives.  All will need to be saved.
     */
    public function getSaveFieldNames($join_names = null)
    {
        $out = parent::getSaveFieldNames($join_names);
        $out = array_merge($out, $this->_typeversion_digest['addon_property_fields']);
        $out = array_merge($out, $this->_typeversion_digest['addon_attachment_fields']);
        $out = array_merge($out, $this->_typeversion_digest['addon_component_fields']);
        $out = array_merge($out, $this->_typeversion_digest['addon_component_subfields']);
        return $out;
    }

    /**
     * returns true if we are an existing object that is not the current object, and when we are saved
     * we will NOT become the current version of this object.  This is normally used to decide if we
     * will enforce the "no duplicate serial number" rule.
     */
    public function weWillBeSavedAsAnOlderVersion()
    {
        /*
             * don't bother checking for a duplicate SN if we are existing object and after saving ourselves
            * we will still not be the most current version.
        */
        $saving_an_older_version = false;
        if ($this->isExistingObject()) {
            $records = $this->_dbschema->getRecords('itemversion_id', "
        				SELECT itemobject.cached_current_itemversion_id as itemversion_id, curr_iv.effective_date as current_effective_date
        				FROM itemobject
        				LEFT JOIN itemversion as curr_iv ON curr_iv.itemversion_id=itemobject.cached_current_itemversion_id
        				WHERE (itemobject.itemobject_id='{$this->itemobject_id}') LIMIT 1");
            if (count($records)>0) {
                $record = reset($records);
                if ((strtotime($this->effective_date)<strtotime($record['current_effective_date']))
                        && ($this->itemversion_id != $record['itemversion_id'])) {
                    $saving_an_older_version = true;
                }
            }
        }
        return $saving_an_older_version;
    }

    /**
     * look through other, current parts of the same type (but not self) and identify any items that
     * have the specified serial number.  It does not include older versions of other items in the search.
     * This is used to see if a serial number is already used.
     *
     * This assumes that $this->typeversion_id and $this->itemobject_id is set already.
     */
    public function serialNumberAlreadyUsed($serial_number)
    {

        $serial_number_slashes = addslashes($serial_number);
        $records = $this->_dbschema->getRecords('', "
        			SELECT other_iv.itemversion_id
        			FROM itemversion as other_iv
        			INNER JOIN typeversion as other_tv ON other_iv.typeversion_id = other_tv.typeversion_id
        			INNER JOIN itemobject as other_io ON other_io.cached_current_itemversion_id=other_iv.itemversion_id
        			WHERE (other_tv.typeobject_id=(SELECT tv.typeobject_id FROM typeversion AS tv WHERE tv.typeversion_id='{$this->typeversion_id}' LIMIT 1) )
        			AND (other_iv.itemobject_id!='{$this->itemobject_id}')
        			AND (other_iv.item_serial_number='{$serial_number_slashes}')
        			");

        return count($records) > 0;
    }


    /**
     * Look through all item versions for this itemobject_id and see if there are any other
     * itemversions with exactly the same effective date.
     */
    public function existsDuplicateEffectiveDate($effective_date)
    {

        $effective_date = time_to_mysqldatetime(strtotime($effective_date));
        $records = $this->_dbschema->getRecords('', "
        			SELECT other_iv.itemversion_id
        			FROM itemversion as other_iv
        			WHERE (other_iv.itemobject_id='{$this->itemobject_id}')
        			AND (other_iv.itemversion_id!='{$this->itemversion_id}')
        			AND (other_iv.effective_date='{$effective_date}')
        			");

        return count($records) > 0;
    }

    /**
     * This does a search of all the items of the current type (other than the current object) and returns
     * serial numbers (with itemversion_ids for keys) of any other objects that have this $value for $fieldname.
     */
    public function getItemsWithPropertyMatching($fieldname, $value)
    {

        // attempt to create a text match for the json encoded property value.
        $json_snippet = json_encode(array($fieldname => $value));
        $json_snippet = substr($json_snippet, 1, strlen($json_snippet)-2);
        $like_value = fetch_like_query($json_snippet);
        $records = $this->_dbschema->getRecords('itemversion_id', "
        			SELECT other_iv.itemversion_id, other_iv.item_serial_number, other_iv.item_data
        			FROM itemversion as other_iv
        			INNER JOIN typeversion as other_tv ON other_iv.typeversion_id = other_tv.typeversion_id
        			INNER JOIN itemobject as other_io ON other_io.cached_current_itemversion_id=other_iv.itemversion_id
        			WHERE (other_tv.typeobject_id=(SELECT tv.typeobject_id FROM typeversion AS tv WHERE tv.typeversion_id='{$this->typeversion_id}' LIMIT 1) )
        			AND (other_iv.itemobject_id!='{$this->itemobject_id}')
        			AND (other_iv.item_data {$like_value})
        			");
        $out = array();
        foreach ($records as $itemversion_id => $record) {
            $item_data = json_decode($record['item_data'], true);
            if ($item_data[$fieldname]==$value) {
                $out[$itemversion_id] = $record['item_serial_number'] ? $record['item_serial_number'] : "Item Version: {$itemversion_id}";
            }
        }

        return $out;
    }

    protected static function minMaxMessages($ft, $val, $fieldname, $msgsubjectclause, &$errormsg)
    {
        $min = isset($ft['minimum']) ? trim($ft['minimum']) : '';
        $max = isset($ft['maximum']) ? trim($ft['maximum']) : '';
        if (is_numeric($min) && is_numeric($max)) {
            $min_ok = ($val >=$min);
            $max_ok = ($val <=$max);
            $range_msg = $min==$max ? 'exactly '.$min : 'in the range '.$min.' to '.$max;
            if (!$min_ok || !$max_ok) {
                $errormsg[$fieldname] = $msgsubjectclause.' must be '.$range_msg;
            }
        } else if (is_numeric($min)) {
            $min_ok = ($val >=$min);
            if (!$min_ok) {
                $errormsg[$fieldname] = $msgsubjectclause.' should not be less than '.$min;
            }
        } else if (is_numeric($max)) {
            $max_ok = ($val <=$max);
            if (!$max_ok) {
                $errormsg[$fieldname] = $msgsubjectclause.' should not be greater than '.$max;
            }
        }
    }

    /**
     * overrides standard field input validation to catch duplicate serial numbers, and bad effective dates.
     * The messages are indexed by fieldname so the errors can later be placed in the right edit boxes.
     * @see TableRow::validateFields()
     */
    public function validateFields($fieldnames, &$errormsg)
    {
        $this->applyDictionaryOverridesToFieldTypes();

        if (in_array('item_serial_number', $fieldnames)) {
            if (!$this->hasASerialNumber()) {
                unset($fieldnames[array_search('item_serial_number', $fieldnames)]);
            } else {
                // need to see if this serial number exists already
                if (!$this->weWillBeSavedAsAnOlderVersion() && $this->serialNumberAlreadyUsed($this->item_serial_number)) {
                    $errormsg['item_serial_number'] = 'This serial number is already in use.  Please use a different serial number';
                } else {
                    // make sure the format of the serial number is OK.
                    $SerialNumber = SerialNumberType::typeFactory($this->_typeversion_digest['serial_number_format']);
                    $SerialNumber->validateEnteredSerialNumber($this->item_serial_number, $errormsg);
                }
            }
        }

        // check to make sure we are not saving a duplicate effective date
        if (in_array('effective_date', $fieldnames)) {
            if ($this->effective_date && (strtotime($this->effective_date) != -1) && $this->existsDuplicateEffectiveDate($this->effective_date)) {
                $errormsg['effective_date'] = 'The effective date ('.$this->effective_date.') is exactly the same as another exsting version.  You must use a different date (even if only 1 minute different).';
                unset($fieldnames[array_search('effective_date', $fieldnames)]);
            }
        }

        // unique value checking
        foreach ($fieldnames as $fieldname) {
            if (isset($this->_fieldtypes[$fieldname]['unique']) && $this->_fieldtypes[$fieldname]['unique'] && $this->{$fieldname}) {
                $serialnumbers = $this->getItemsWithPropertyMatching($fieldname, $this->{$fieldname});
                if (count($serialnumbers)>0) {
                    $errormsg[$fieldname] = 'The value "'.$this->{$fieldname}.'" for '.$this->formatFieldnameNoColon($fieldname).' must be unique.  However this same value also appears in '.implode(' and ', $serialnumbers).'.';
                }
            }
        }

        // apply min, max checking to appropriate fields
        foreach ($fieldnames as $fieldname) {
            $ft = $this->_fieldtypes[$fieldname];
            $val = $this->{$fieldname};
            $caption = $this->formatFieldnameNoColon($fieldname);
            if (isset($ft['type']) && in_array($ft['type'], array('float','calculated')) && is_numeric($val)) {  // the non-numeric and empty case is handled by the parent method
                self::minMaxMessages($ft, $val, $fieldname, 'The value "'.$val.'" for '.$caption, $errormsg);
            } elseif (isset($ft['type']) && ($ft['type']=='boolean') && !is_null($val) && ($val!=='')) {  // the non-numeric and empty case is handled by the parent method
                $min = isset($ft['minimum']) ? trim($ft['minimum']) : '';
                $max = isset($ft['maximum']) ? trim($ft['maximum']) : '';
                if (is_numeric($min) && is_numeric($max)) {
                    if (($min=="1") && !$val) {
                        $errormsg[$fieldname] = $caption.' should be Yes';
                    }
                    if (($max=="0") && $val) {
                        $errormsg[$fieldname] = $caption.' should be No';
                    }
                }
            }
        }

        // check other things like make sure components are of the right type (rare) or overused.
        foreach ($fieldnames as $fieldname) {
            $ft = $this->_fieldtypes[$fieldname];
            if (isset($ft['type'])) {
                if ($ft['type']=='component') {
                    if (is_numeric($this->{$fieldname})) {
                        $records = DbSchema::getInstance()->getRecords('', "SELECT tv.typeobject_id FROM itemobject iob
                            LEFT JOIN itemversion iv ON iv.itemversion_id=iob.cached_current_itemversion_id
                            LEFT JOIN typeversion tv ON tv.typeversion_id=iv.typeversion_id
                            WHERE iob.itemobject_id='{$this->{$fieldname}}'");
                        if (count($records)>0) {
                            $record=reset($records);
                            if (!in_array($record['typeobject_id'], $ft['can_have_typeobject_id'])) {
                                $errormsg[$fieldname] = 'Wrong type for this component!';
                            } else if (!$this->hasADisposition()) {
                                $this->checkComponentOverUsedOn($fieldname, $ft, $this->{$fieldname}, $errormsg);
                            }
                        }
                    }
                } elseif (($ft['type']=='attachment') && is_numeric($this->{$fieldname})) {
                    $min = isset($ft['minimum']) ? trim($ft['minimum']) : '';
                    $max = isset($ft['maximum']) ? trim($ft['maximum']) : '';
                    if (is_numeric($min) || is_numeric($max)) {
                        // get count of attachments
                        $records = DbSchema::getInstance()->getRecords('', "SELECT count(*) as count FROM document WHERE comment_id='{$this->{$fieldname}}'");
                        $val =  (count($records)>0) ? $records[0]['count'] : 0;
                        self::minMaxMessages($ft, $val, $fieldname, 'The number of attachments ('.$val.') for '.$caption, $errormsg);
                    }
                }
            }
        }


        parent::validateFields($fieldnames, $errormsg);
        if ($this->hasADisposition() && ((count($errormsg)>0) || $this->hasDictionaryOverrideErrors())) {
            if ($this->disposition=='Pass') {
                $errormsg['disposition'] = 'There cannot be errors and a disposition of Pass.  Use Signed Off instead.';
            }
        }
    }

    public function checkComponentOverUsedOn($fieldname, $component_fieldtype, $component_io, &$errormsg)
    {
        $query = "SELECT CAST((SELECT GROUP_CONCAT(concat(iv_them.itemobject_id,',',iv_them.item_serial_number) ORDER BY iv_them.effective_date SEPARATOR ';') as used_on
                            FROM itemcomponent
                            LEFT JOIN itemversion AS iv_them ON iv_them.itemversion_id=itemcomponent.belongs_to_itemversion_id
                            LEFT JOIN itemobject AS io_them ON io_them.itemobject_id=iv_them.itemobject_id
                            LEFT JOIN typeversion AS tv_them ON tv_them.typeversion_id=iv_them.typeversion_id
                            LEFT JOIN typecomponent as tc_them ON (tc_them.component_name=itemcomponent.component_name) and (tc_them.belongs_to_typeversion_id=iv_them.typeversion_id)
                            WHERE (io_them.cached_current_itemversion_id=iv_them.itemversion_id) and (iv_them.itemobject_id != '{$this->itemobject_id}')
                              and (itemcomponent.has_an_itemobject_id='{$component_io}') and (tv_them.typecategory_id=2) and (tc_them.max_uses!=-1)
                            ORDER BY iv_them.itemversion_id) AS CHAR) as used_on_io";
        $records = DbSchema::getInstance()->getRecords('', $query);
        $record = reset($records);
        $used_on_arr = $record['used_on_io'] ? explode(';', $record['used_on_io']) : array();
        $max_uses = isset($component_fieldtype['max_uses']) && is_numeric($component_fieldtype['max_uses']) ? $component_fieldtype['max_uses'] : 1;
        if ((count($used_on_arr) > $max_uses - 1) && ($max_uses > 0)) {
            $sn_arr = array();
            foreach ($used_on_arr as $useditem) {
                $sn_arr[] = explode(',', $useditem)[1];
            }
            $max_uses_txt = $max_uses==1 ? '.' : ', but Max Uses = '.$max_uses.'.';
            $used_count_txt = count($used_on_arr)>2 ? count($used_on_arr).' times' : 'on '.implode(' and ', $sn_arr);
            $errormsg[$fieldname] = 'Component already used '.$used_count_txt.$max_uses_txt;
        }
    }

    public function validateForFatalFields($fieldnames, &$errormsg)
    {
        $this->validateFields($fieldnames, $errormsg);
        foreach ($this->getAddOnFieldNames() as $fieldname) {
            if (isset($errormsg[$fieldname])) {
                unset($errormsg[$fieldname]);
            }
        }
    }

    public function getComponentValidationErrors(&$errormsg, $always_recheck_errors = false)
    {
        $itemobject_ids = array();
        foreach ($this->getComponentFieldNames() as $fieldname) {
            if (is_numeric($this->{$fieldname})) {
                $itemobject_ids[$fieldname] = $this->{$fieldname};
            }
        }
        if (count($itemobject_ids) > 0) {
            $error_counts_array = DBTableRowItemObject::refreshAndGetValidationErrorCounts($itemobject_ids, $always_recheck_errors);
            foreach ($itemobject_ids as $fieldname => $itemobject_id) {
                if ($error_counts_array[$itemobject_id] > 0) {
                    $errormsg[$fieldname] = 'Component has errors.';
                }
            }
        }
    }

    /**
     * Do a validation on all the fields, plus look for any override errors that have been placed
     * in the dictionary_overrides and create an array of these error messages.
     *
     * @return array of displayable errors for this object
     */
    public function getArrayOfAllFieldErrors()
    {
        $errormsg = array();
        $this->validateFields($this->getSaveFieldNames(), $errormsg);
        foreach ($errormsg as $fieldname => $error) {
            $errormsg[$fieldname] = array('name' => $this->formatFieldnameNoColon($fieldname), 'error' => $error);
        }
        // get the override errors
        foreach ($this->getFieldTypes() as $fieldname => $fieldtype) {
            if (isset($fieldtype['error'])) {
                $errormsg[$fieldname] = array('name' => $this->formatFieldnameNoColon($fieldname), 'error' => $fieldtype['error']);
            }
        }
        return $errormsg;
    }

    /**
     * this will only reload the typeversion information if $this->_typeversion_digest is inconsistent
     * This should also load the component type definitions.
     * TODO: There might be problem in that we don't load the tv__ fields here like would happen if the record was loaded from DB.  Not sure if this matters.
     */
    public function refreshLoadedTypeVersionFields($typeversion_id)
    {
        // does it look like the typeversion data is out of date?
        if (is_numeric($typeversion_id) && (!isset($this->_typeversion_digest['typeversion_id'])  || ($typeversion_id!=$this->_typeversion_digest['typeversion_id']))) {
            $TypeVersion = new DBTableRowTypeVersion(false, null);
            $this->_typeversion_digest = array();
            if ($TypeVersion->getRecordById($typeversion_id)) {
                $this->_typeversion_digest = $TypeVersion->getLoadedTypeVersionDigest(false);
                $this->setFieldTypes($this->_typeversion_digest['fieldtypes']);
                $SNFormat = SerialNumberType::typeFactory($this->_typeversion_digest['serial_number_format']);
                $this->setFieldAttribute('item_serial_number', 'subcaption', $SNFormat->getHelperCaption());
            }
        }
    }

    public function setFieldTypeForRecordLocator()
    {
        $this->setFieldType('record_locator', array('caption' => 'Record Locator','subcaption' => 'use in search box', 'mode' => 'R', 'type' => 'ft_other' ));
    }

    /**
     * This is the magic field set override.  Special things must happen when we assign a new typeversion_id.
     * @see TableRow::__set()
     */
    public function __set($key, $value)
    {
        parent::__set($key, $value);
        if ($key=='typeversion_id') {
            $this->refreshLoadedTypeVersionFields($value);
        }
    }

    /**
     * Reload just this $component_name component along with any associated component_subfields.
     * The relevant version loaded is based on the effecive date.
     */
    public function reloadComponent($component_name)
    {
        $out = array();
        $this->_last_loaded_component_objects[$component_name] = new DBTableRowItemVersion(false, null);
        $this->_last_loaded_component_objects[$component_name]->getCurrentRecordByObjectId($this->{$component_name}, $this->effective_date);

        // load the component subfield values for this component if any exist
        foreach ($this->_typeversion_digest['addon_component_subfields'] as $fieldname) {
            $fieldtype = $this->getFieldType($fieldname);
            if ($component_name==$fieldtype['component_name']) {
                $this->{$fieldname} = $this->_last_loaded_component_objects[$component_name]->{$fieldtype['component_subfield']};
                $out[$fieldname] = $this->{$fieldname};
            }
        }
        return $out;
    }

    /**
     * checks to see if any of the component subfields have changed.  If so, it saves new
     * versions of these components and sets the appropriate $this->{$component_name} fields
     * to their indexes.
     */
    public function saveComponentSubFieldsVersioned($effective_date)
    {
        /*
             * Need to loop through $this->_typeversion_digest['components_in_defined_subfields'] and then
             * for each component, load the itemversion object, see if there are any fields which have changed
             * other than the effective_date.  If so, then we have to save a new version of the component.  So,
             * in that case, map in the appropriate type fields, and saveVersioned() in the component itemversion.
         */
        $differences_found = false;
        foreach ($this->_typeversion_digest['components_in_defined_subfields'] as $component_name) {
            /*
                    Note that it only makes sense to save subfields if a component is actually selected.
            */
            if (is_numeric($this->{$component_name})) {
                $OldComponent = new DBTableRowItemVersion(false, null);
                $OldComponent->getCurrentRecordByObjectId($this->{$component_name}, $this->effective_date);
                $NewComponent = new DBTableRowItemVersion(false, null);
                $NewComponent->getCurrentRecordByObjectId($this->{$component_name}, $this->effective_date);

                foreach ($this->_typeversion_digest['addon_component_subfields'] as $fieldname) {
                    $fieldtype = $this->getFieldType($fieldname);
                    if ($component_name==$fieldtype['component_name']) {
                        $NewComponent->{$fieldtype['component_subfield']} = $this->{$fieldname};
                    }
                }

                /*
                     * If there are differences that we've created, then we need to save a new version
                 */
                $has_differences = $NewComponent->checkDifferencesFrom($OldComponent);
                if ($has_differences) {
                    // set the effective_date of the new component
                    $NewComponent->effective_date = $effective_date;
                    $NewComponent->saveVersioned();
                    $differences_found = true;
                }

                $this->_last_loaded_component_objects[$component_name] = $NewComponent;
            }
        }
        return $differences_found;
    }


    /**
     * Need to loop through $this->_typeversion_digest['components_in_defined_subfields'] and then
     * for each component, load the itemversion object, map in the appropriate type fields, and save
     */
    public function saveComponentSubFieldsUnversioned()
    {
        foreach ($this->_typeversion_digest['components_in_defined_subfields'] as $component_name) {
            if (is_numeric($this->{$component_name})) {
                $this->_last_loaded_component_objects[$component_name] = new DBTableRowItemVersion(false, null);
                $this->_last_loaded_component_objects[$component_name]->getCurrentRecordByObjectId($this->{$component_name}, $this->effective_date);

                $fieldstosave = array();
                foreach ($this->_typeversion_digest['addon_component_subfields'] as $fieldname) {
                    $fieldtype = $this->getFieldType($fieldname);
                    if ($component_name==$fieldtype['component_name']) {
                        $this->_last_loaded_component_objects[$component_name]->{$fieldtype['component_subfield']} = $this->{$fieldname};
                        $fieldstosave[] = $fieldtype['component_subfield'];
                    }
                }
                $this->_last_loaded_component_objects[$component_name]->save($fieldstosave);
            }
        }
    }


    /**
     * called from onAfterGetRecord() to set the local field values from the components.
     * If the dictionary has component subfields in it, then we have to go and db read these.
     * The $effective_date is just the current effective_date of the loading record.  It is needed
     * to get appropriate versions of the components.
     *
     * TODO: might be a problem here because what if effective_date is too early?  Might be
     * bad not to have anything loaded.
     */
    public function loadComponentValuesFromItemComponentsList($list_of_itemcomponents, $effective_date = null)
    {

        // extract the component values (just the value of itemcomponent_id) from the just processed record
        $this->_last_loaded_component_objects = array();
        foreach (explode(';', $list_of_itemcomponents) as $itemcomponent) {
            $component_params = explode(',', $itemcomponent);
            if (count($component_params)==3) {
                list($itemcomponent_id, $component_name, $has_an_itemobject_id) = $component_params;
                $this->{$component_name} = $has_an_itemobject_id;
                if (in_array($component_name, $this->_typeversion_digest['components_in_defined_subfields'])) {
                    $this->_last_loaded_component_objects[$component_name] = new DBTableRowItemVersion(false, null);
                    $this->_last_loaded_component_objects[$component_name]->getCurrentRecordByObjectId($has_an_itemobject_id, $effective_date);
                }
            }
        }

        /*
             * initialize any subfield component objects that were not present in $list_of_itemcomponents.  We need these
             * to be valid empty itemversion objects so we pick up any defaults for the subfields.
         */
        foreach ($this->_typeversion_digest['components_in_defined_subfields'] as $component_name) {
            if (!isset($this->_last_loaded_component_objects[$component_name])) {
                $this->_last_loaded_component_objects[$component_name] = new DBTableRowItemVersion(false, null);
            }
        }

        // load the component subfield values for any defined component subfields
        foreach ($this->_typeversion_digest['addon_component_subfields'] as $fieldname) {
            $fieldtype = $this->getFieldType($fieldname);
            $component_name = $fieldtype['component_name'];
            /*
                 * it is important to check that this exists, because not all defined compoent types may
                 * exists as saved itemcomponent records.
             */
            if (isset($this->_last_loaded_component_objects[$component_name])) {
                $this->{$fieldname} = $this->_last_loaded_component_objects[$component_name]->{$fieldtype['component_subfield']};
            }
        }

    }

    protected function onAfterGetRecord(&$record_vars)
    {

        if (is_numeric($record_vars['tv__typeversion_id'])) {
            $varsWithoutPrefix = extract_prefixed_keys($record_vars, 'tv__', true);
            $varsWithoutPrefix['partnumber_count'] = $record_vars['partnumber_count'];
            $this->_typeversion_digest = DBTableRowTypeVersion::getTypeVersionDigestFromFields($record_vars['list_of_typecomponents'], $record_vars['has_a_serial_number'], $record_vars['has_a_disposition'], $varsWithoutPrefix, false);
            $this->setFieldTypes($this->_typeversion_digest['fieldtypes']);
        }

        // extract the property values from the just processed record.
        $item_data = json_decode($record_vars['item_data'], true);
        foreach ($this->_typeversion_digest['addon_property_fields'] as $dictionary_field_name) {
            if (isset($item_data[$dictionary_field_name])) {
                $record_vars[$dictionary_field_name] = $item_data[$dictionary_field_name];
            }
        }

        $this->loadComponentValuesFromItemComponentsList($record_vars['list_of_itemcomponents'], $record_vars['effective_date']);

        foreach (explode(';', $record_vars['list_of_itemcomments']) as $itemcomment) {
            $comment_params = explode(',', $itemcomment);
            if (count($comment_params)==3) {
                list($itemcomment_id, $field_name, $has_a_comment_id) = $comment_params;
                $record_vars[$field_name] = $has_a_comment_id;
            }
        }

        return true;
    }

    /**
     * Get the record with all the usual addons.  In particular, we get packed lists of components and typecomponents.
     * @see DBTableRow::getRecordById()
     */
    public function getRecordById($id)
    {
        $DBTableRowQuery = new DBTableRowQuery($this);
        $DBTableRowQuery->setLimitClause('LIMIT 1')->addSelectors(array($this->_idField => $id));
        $DBTableRowQuery->addSelectFields(array("CAST((SELECT GROUP_CONCAT(concat(typecomponent.typecomponent_id,',',typecomponent.component_name,',',(
  SELECT GROUP_CONCAT(DISTINCT typecomponent_typeobject.can_have_typeobject_id ORDER BY typecomponent_typeobject.can_have_typeobject_id SEPARATOR '|') FROM typecomponent_typeobject WHERE typecomponent_typeobject.typecomponent_id=typecomponent.typecomponent_id
),',',CONVERT(HEX(IFNULL(typecomponent.caption,'')),CHAR),',',CONVERT(HEX(IFNULL(typecomponent.subcaption,'')),CHAR),',',IFNULL(typecomponent.featured,0),',',IFNULL(typecomponent.required,0),',',IFNULL(typecomponent.max_uses,1))  ORDER BY typecomponent.component_name SEPARATOR ';') FROM typecomponent WHERE typecomponent.belongs_to_typeversion_id=itemversion.typeversion_id) AS CHAR) as list_of_typecomponents"));
        $DBTableRowQuery->addSelectFields("(SELECT count(*) FROM partnumbercache WHERE itemversion.typeversion_id=partnumbercache.typeversion_id) as partnumber_count");
        $DBTableRowQuery->addSelectFields(array("CAST((SELECT GROUP_CONCAT(concat(itemcomponent.itemcomponent_id,',',itemcomponent.component_name,',',itemcomponent.has_an_itemobject_id)  ORDER BY itemcomponent.component_name SEPARATOR ';') FROM itemcomponent WHERE itemcomponent.belongs_to_itemversion_id=itemversion.itemversion_id) AS CHAR) as list_of_itemcomponents"));
        $DBTableRowQuery->addSelectFields(array("CAST((SELECT GROUP_CONCAT(concat(itemcomment.itemcomment_id,',',itemcomment.field_name,',',itemcomment.has_a_comment_id)  ORDER BY itemcomment.field_name SEPARATOR ';') FROM itemcomment WHERE itemcomment.belongs_to_itemversion_id=itemversion.itemversion_id) AS CHAR) as list_of_itemcomments"));
        $DBTableRowQuery->addJoinClause("LEFT JOIN typecategory ON typecategory.typecategory_id={$DBTableRowQuery->getJoinAlias('typeversion')}.typecategory_id")
                        ->addSelectFields('typecategory.*');
        $DBTableRowQuery->addJoinClause("LEFT JOIN partnumbercache ON partnumbercache.typeversion_id=itemversion.typeversion_id AND partnumbercache.partnumber_alias=itemversion.partnumber_alias")
                        ->addSelectFields('partnumbercache.part_number, partnumbercache.part_description');
        $DBTableRowQuery->addJoinClause("LEFT JOIN user ON user.user_id=itemversion.user_id")
                        ->addSelectFields('user.login_id');
        return $this->getRecord($DBTableRowQuery->getQuery());
    }

    public function hasASerialNumber()
    {
        $this->refreshLoadedTypeVersionFields($this->typeversion_id);
        return $this->_typeversion_digest['has_a_serial_number'];
    }

    public function hasAValidTypeVersionId()
    {
        $this->refreshLoadedTypeVersionFields($this->typeversion_id);
        return count($this->_typeversion_digest) > 0;
    }


    public function hasADisposition()
    {
        $this->refreshLoadedTypeVersionFields($this->typeversion_id);
        return $this->_typeversion_digest['has_a_disposition'];
    }

    public function hasAliases()
    {
        $this->refreshLoadedTypeVersionFields($this->typeversion_id);
        return $this->_typeversion_digest['partnumber_count']>1;
    }

    public static function getItemVersionIdFromByObjectId($itemobject_id, $effective_date = null)
    {
        $the_itemversion_id = null;
        if (!is_null($effective_date)) {
            $effective_date = time_to_mysqldatetime(strtotime($effective_date));
            $records = DbSchema::getInstance()->getRecords('', "SELECT itemversion_id from itemversion
       					WHERE itemobject_id='{$itemobject_id}'
       					and effective_date=(select MAX(effective_date) from itemversion where itemobject_id='{$itemobject_id}' and effective_date<='{$effective_date}')
       					LIMIT 1");
            if (count($records)==1) {
                $record = reset($records);
                $the_itemversion_id = $record['itemversion_id'];
            }
        }

        /*
             * We try again if the above failed, or for the first time if no effective_date
        */
        if (is_null($the_itemversion_id)) {
            $records = DbSchema::getInstance()->getRecords('', "SELECT * FROM itemobject WHERE itemobject_id='{$itemobject_id}' LIMIT 1");
            if (count($records)==1) {
                $record = reset($records);
                $the_itemversion_id = $record['cached_current_itemversion_id'];
            } else {
                $the_itemversion_id = null;
            }
        }

        return $the_itemversion_id;
    }

    /**
     * This will attempt to fetch an itemversion record give the itemobject_id.  Without the effective_date, the most current itemversion_id record is obtained.
     * If the effective_date is specified, then it attempt to get the itemversion record that was current as of the date specified.
     *
     * TODO: Should refactor so these are single mysql calls.
     *
     * @param integer $itemobject_id = the itemobject_id of the itemversion record being requested
     * @param time $effective_date =  effective date/time of the record being requested.
     * @return boolean
     */
    public function getCurrentRecordByObjectId($itemobject_id, $effective_date = null)
    {
        $the_itemversion_id = self::getItemVersionIdFromByObjectId($itemobject_id, $effective_date);
        if (!is_null($the_itemversion_id)) {
            return $this->getRecordById($the_itemversion_id);
        } else {
            return false;
        }
    }


    /*
         * Answers: what are the fields that should be shown in the editform for editing?
         * This should include properties AND components.
     */
    public function getEditFieldNames($join_names = null)
    {
        $fieldnames = DBTableRowTypeVersion::buildListOfItemFieldNames($this->_typeversion_digest);
        if ($_SESSION['account']->getRole()=='DataTerminal') {
            $fieldnames[] = 'user_id';
        }
        return $fieldnames;
    }

    /*
         * Alter the following to change the items that appear in the typeversion selection box.
     */
    public function getJoinOptions($join_name, $include_only_orphans)
    {

        if ('type_version'!=$join_name) {
            return parent::getJoinOptions($join_name, $include_only_orphans);
        }

        /*
             * in the following, we want to present a dropdown list of different version of the same
             * typeobject_id.  We don't want to allow changing of the type itself from here unless it has been explicitely allowed
             * Example: select * from typeversion where typeobject_id=(select tv.typeobject_id from typeversion as tv where tv.typeversion_id='{$this->typeversion_id}' LIMIT 1)
         */
        if (!AdminSettings::getInstance()->use_any_typeversion_id) {
            if (!isset($this->_join_options[$join_name])) {
                $joins = $this->getJoinFieldsAndTables();
                $target = $joins[$join_name];
                $DbTableObj = new DBTableRowTypeVersion(true, null);
                $ChildRecords = new DBRecords($DbTableObj, '', '');
                $ChildRecords->getRecords(
                    "SELECT typeversion.* FROM typeversion
	                    LEFT JOIN {$this->_table} on {$this->_table}.typeversion_id=typeversion.typeversion_id
	                    WHERE typeobject_id=(SELECT tv.typeobject_id FROM typeversion AS tv WHERE tv.typeversion_id='{$this->typeversion_id}' LIMIT 1)
	                    ORDER BY effective_date desc"
                    );
                $this->_join_options[$join_name] = array();
                foreach ($ChildRecords->keys() as $index) {
                    $this->_join_options[$join_name][$index] = $ChildRecords->getRowObject($index)->getCoreDescription();
                }
            }
            //if ($_SESSION['account']->getRole()=='Admin') $this->_join_options[$join_name]['show_all_types'] = 'Show All Types';
            return $this->_join_options[$join_name];
        }
        return parent::getJoinOptions($join_name, $include_only_orphans);
    }

    public function getComponentAsIVObject($fieldname)
    {
        if (is_numeric($this->{$fieldname})) {
            $ComponentItemVersion = new DBTableRowItemVersion(false, null);
            $ComponentItemVersion->getCurrentRecordByObjectId($this->{$fieldname});
            return $ComponentItemVersion;
        } else {
            return null;
        }
    }

    public function shortName()
    {
        return $this->hasASerialNumber() ?  $this->item_serial_number : date('m/d/Y H:i', strtotime($this->effective_date));
    }

    /**
     * Returns a single entry array with the named component's value (e.g.: itemobject_id) as the key
     * and the serial number of the component as the value.  This is used for displaying the human-readable
     * value of a component.  It shows the serial number of the most recent version of the component (which only
     * matters if the serial number of the component has changed).  It is just easier to comprehend this way.
     * @param unknown_type $fieldname
     * @return multitype:mixed |multitype:
     */
    public function getComponentValueAsArray($fieldname)
    {
        $ComponentItemVersion = $this->getComponentAsIVObject($fieldname);
        if (!is_null($ComponentItemVersion)) {
            return array($ComponentItemVersion->itemobject_id => $ComponentItemVersion->shortName());
        } else {
            return array();
        }
    }

    /**
     * Need to get the most recent versions of the given typeversion (typeobject actually) that is before
     * or at the same time as $effective_date.  This also works with components that are procedures.
     * In this case we return only procedures that
     * @param unknown_type $fieldname
     * @param unknown_type $effective_date
     * @return multitype:string unknown
     */
    public function getComponentSelectOptions($fieldname, $effective_date, $future_suffix_label = ' (future effective date)', $only_self_ref_proc = true, $show_types = false)
    {
        /*
             * This list should be ordered by effective_date
             *
             * Then we should fill an array in chronological order that is keyed by itemobject_id.
             * This will give us a list that contains only entries that are no younger than our date
             * but not multiple versions of the same object.
             *
             *  We then want to index this list by itemobject_id.
             *
             *  Finally we want to add in the currently set itemversion_id to make sure we have that covered.
         */
        $fieldtype = $this->getFieldType($fieldname);
        $effective_date = is_valid_datetime($effective_date) ?  time_to_mysqldatetime(strtotime($effective_date)) : null;

        /*
             * Gets all itemversion records with effective dates before $effective_date and matching the
             * input field's typeobject_id.  This is pretty broad, but we will widdle it down soon enough.
             * We dont worry about effective dates of the type in this case because we are lazy.
         */
        if (!is_null($effective_date)) {
            $query = "
	        		SELECT itemversion.itemversion_id, itemversion.item_serial_number as sn, typeversion.type_description,
	            		IF(itemversion.itemversion_id!=itemobject.cached_current_itemversion_id,1,0) as is_old_version,
	            		typecategory.is_user_procedure, itemversion.effective_date, itemversion.disposition,
                        (select count(*) from itemcomponent WHERE (itemcomponent.belongs_to_itemversion_id=itemversion.itemversion_id) and (itemcomponent.has_an_itemobject_id='{$this->itemobject_id}')) as self_count,
	            		IF(itemobject.cached_first_ver_date<='$effective_date',0,1) as is_future_component,
	            		itemversion.effective_date, itemversion.itemobject_id,
	            		partnumbercache.part_description, partnumbercache.part_number,
                        CAST((SELECT GROUP_CONCAT(concat(iv_them.itemobject_id,',',iv_them.item_serial_number,IF(tc_them.max_uses=-1,'*','')) ORDER BY iv_them.effective_date SEPARATOR ';') as used_on
                            FROM itemcomponent
                            LEFT JOIN itemversion AS iv_them ON iv_them.itemversion_id=itemcomponent.belongs_to_itemversion_id
                            LEFT JOIN itemobject AS io_them ON io_them.itemobject_id=iv_them.itemobject_id
                            LEFT JOIN typeversion AS tv_them ON tv_them.typeversion_id=iv_them.typeversion_id
                            LEFT JOIN typecomponent as tc_them ON (tc_them.component_name=itemcomponent.component_name) and (tc_them.belongs_to_typeversion_id=iv_them.typeversion_id)
                            WHERE (io_them.cached_current_itemversion_id=iv_them.itemversion_id) and (iv_them.itemobject_id != '{$this->itemobject_id}') and (itemcomponent.has_an_itemobject_id=itemversion.itemobject_id) and (tv_them.typecategory_id=2)
                            ORDER BY iv_them.itemversion_id) AS CHAR) as used_on_io
	            	FROM itemversion
	               	LEFT JOIN itemobject on itemversion.itemobject_id=itemobject.itemobject_id
	               	LEFT JOIN typeversion on typeversion.typeversion_id=itemversion.typeversion_id
	               	LEFT JOIN typecategory ON typecategory.typecategory_id=typeversion.typecategory_id
	               	LEFT JOIN partnumbercache ON partnumbercache.typeversion_id=itemversion.typeversion_id AND partnumbercache.partnumber_alias=itemversion.partnumber_alias
	               	WHERE (typeversion.typeobject_id IN ('".implode("','", $fieldtype['can_have_typeobject_id'])."'))
	               	ORDER BY itemversion.effective_date
	        	";

            $records = DbSchema::getInstance()->getRecords('', $query);
        } else {
            $records = array();
        }

        // This compacts to so that only the latest effective date is represented in this list,  Oh, and it of course indexes by itemobject_id too.
        $by_itemobject = array();
        foreach ($records as $record) {
            $by_itemobject[$record['itemobject_id']] = $record;
        }

        /*
            If the currently set value for the component is not in the list, we still need to have it show up in the list...
            It gets overwritten without "wrong type" if it is a legal value.
         */
        $out = array();
        $LLC = new DBTableRowItemVersion(false, null);
        if (is_numeric($this->{$fieldname}) && $LLC->getCurrentRecordByObjectId($this->{$fieldname})) {
            $out[$this->{$fieldname}] = TextToHtml($LLC->item_serial_number).' (wrong type for this component)';
        }

        foreach ($by_itemobject as $record) {
            $type_desc = $show_types && (count($fieldtype['can_have_typeobject_id']) > 1) ? ' ['.$record['part_description'].']' : '';
            if ($record['is_user_procedure']) {
                if (($record['self_count'] > 0) || !$only_self_ref_proc) {
                    $out[$record['itemobject_id']] = date("m/d/Y H:i", strtotime($record['effective_date'])).$type_desc.($record['disposition'] ? ' ('.$record['disposition'].')' : '');
                }
            } else {
                $used_on_arr = $record['used_on_io'] ? explode(';', $record['used_on_io']) : array();
                $used_on = '';
                if ((count($used_on_arr)>0) && $this->hasASerialNumber()) {  // TODO: Need to only do this if this is a part
                    $sn_arr = array();
                    foreach ($used_on_arr as $useditem) {
                        $sn_arr[] = explode(',', $useditem)[1];
                    }
                    if (count($used_on_arr)>2) {
                        $used_on = ' (used '.count($used_on_arr).' times)';
                    } else {
                        $used_on = ' (used on '.implode(', ', $sn_arr).')';
                    }
                }
                $out[$record['itemobject_id']] = $record['sn'].$type_desc.($record['is_future_component'] ? $future_suffix_label : '').$used_on;
            }
        }
        natcasesort($out);
        return $out;
    }

    /**
     * Determines if there are properties or components or attachments for this item that don't have a definition in the typeversion record.
     * Any such are put doublet return array to assign to list($this->hidden_properties_array,$this->hidden_components_array)
     */
    private function loadOrphanFieldsIntoHiddenArrays()
    {
        $hidden_properties_array = array();
        $hidden_components_array = array();
        $hidden_attachments_array = array();

        // we assume that inside >_typeversion_digest is a reliable source for property and components
        $SavedRecord = new self();
        if ($SavedRecord->getRecordById($this->itemversion_id)) {
            $item_data = json_decode($SavedRecord->item_data, true);
            if (!is_array($item_data)) {
                $item_data = array(); // sometimes this field is not initialized
            }
            foreach ($item_data as $fieldname => $fieldval) {
                if (!in_array($fieldname, $this->_typeversion_digest['addon_property_fields']) && !in_array($fieldname, $this->_typeversion_digest['addon_attachment_fields'])) {
                    $hidden_properties_array[$fieldname] = $fieldval;
                }
            }
            $itemcomponents = $SavedRecord->list_of_itemcomponents ? explode(';', $SavedRecord->list_of_itemcomponents) : array();
            foreach ($itemcomponents as $itemcomponent) {
                list($itemcomponent_id, $component_name, $has_an_itemobject_id) = explode(',', $itemcomponent);
                if ($component_name && !in_array($component_name, $this->_typeversion_digest['addon_component_fields'])) {
                    $hidden_components_array[$component_name] = $has_an_itemobject_id;
                }
            }
            $itemcomments = $SavedRecord->list_of_itemcomments ? explode(';', $SavedRecord->list_of_itemcomments) : array();
            foreach ($itemcomments as $itemcomment) {
                list($itemcomment_id, $field_name, $has_a_comment_id) = explode(',', $itemcomment);
                if ($field_name && !in_array($field_name, $this->_typeversion_digest['addon_attachment_fields'])) {
                    $hidden_attachments_array[$field_name] = $has_a_comment_id;
                }
            }
        }
        return array($hidden_properties_array, $hidden_components_array, $hidden_attachments_array);
    }

    public function previewDefinition()
    {
        return isset($this->preview_definition_flag) && $this->preview_definition_flag;
    }

    /**
     * Gets the field layout for the itemview.  This involves getting the dictionary-based layout
     * and merging it with the standard header layout. $layout_key can be "editview" and "itemview".  If
     * editview, then you will not have the option of changing the typeversion_id if this is a new
     * item.
     * @see DBTableRow::getEditViewFieldLayout()
     */
    public function getEditViewFieldLayout($default_fieldnames, $parent_fields_to_remove, $layout_key = null)
    {
        $holyheaderfields = array('typeversion_id','partnumber_alias','record_locator','effective_date','item_serial_number','disposition');
        if ($_SESSION['account']->getRole()=='DataTerminal') {
            $holyheaderfields[] = 'user_id';
        }

        /*
            construct the header layout.  We start with the fields that should appear and then chunk into rows.
        */
        $headerfields = $holyheaderfields;
        if (!$this->isSaved() && !$this->previewDefinition() && ($layout_key=='editview')) {
            $headerfields = array_diff($headerfields, array('typeversion_id'));
        }
        if ($layout_key=='editview') {
            $headerfields = array_diff($headerfields, array('record_locator'));
        }
        if (!$this->hasASerialNumber()) {
            $headerfields = array_diff($headerfields, array('item_serial_number'));
        }
        if (!$this->hasADisposition()) {
            $headerfields = array_diff($headerfields, array('disposition'));
        }
        if (($layout_key!='editview') || !$this->hasAliases()) {
            $headerfields = array_diff($headerfields, array('partnumber_alias'));
        }
        $header_layout = array();
        foreach (array_chunk($headerfields, 2) as $row) {
            $rowout = array('type' => 'columns', 'columns' => array());
            foreach ($row as $col) {
                $rowout['columns'][] = array('name' => $col);
            }
            $header_layout[] = $rowout;
        }

        $parent_fields_to_remove = array_merge($parent_fields_to_remove, $holyheaderfields);

        if (empty($this->_fieldlayout)) {
            $this->_fieldlayout = $this->_typeversion_digest['dictionary_field_layout'];
        }
        $fieldlayout = array_merge(
                $header_layout,
                DBTableRowTypeVersion::addDefaultsToAndPruneFieldLayout($this->_fieldlayout, $default_fieldnames, $parent_fields_to_remove, $layout_key)
        );

        // prepare the orphan field descriptions if needed.
        $orphan_layout = array();
        list($this->hidden_properties_array,$this->hidden_components_array, $this->hidden_attachments_array) = $this->loadOrphanFieldsIntoHiddenArrays();
        if (count($this->hidden_properties_array)>0) {
            $this->setFieldAttribute('hidden_properties_array', 'caption', 'Orphaned Fields');
            $this->setFieldAttribute('hidden_properties_array', 'subcaption', 'These fields were entered using a different version of this form.   Editing will erase these.');
            $orphan_layout[] = array('name' => 'hidden_properties_array');
        }
        if (count($this->hidden_components_array)>0) {
            $this->setFieldAttribute('hidden_components_array', 'caption', 'Orphaned Components');
            $this->setFieldAttribute('hidden_components_array', 'subcaption', 'These components were entered using a different version of this form.   Editing will erase these.');
            $orphan_layout[] = array('name' => 'hidden_components_array');
        }
        if (count($this->hidden_attachments_array)>0) {
            $this->setFieldAttribute('hidden_attachments_array', 'caption', 'Orphaned Attachments');
            $this->setFieldAttribute('hidden_attachments_array', 'subcaption', 'These attachments were entered using a different version of this form.   Editing will erase these.');
            $orphan_layout[] = array('name' => 'hidden_attachments_array');
        }
        if (count($orphan_layout)>0) {
            $fieldlayout[] = array('type' => 'columns', 'columns' => $orphan_layout);
        }

        return $fieldlayout;
    }

    protected function formatInputTagComponent($fieldname)
    {
        $fieldtype = $this->getFieldType($fieldname);
        $select_values = $this->getComponentSelectOptions($fieldname, $this->effective_date, ' (future effective date)', true, true);
        $select_values[''] = ''; // the way you unset a component

        /*
             * Prepare the Add button for new items
         */

        $link_info = array();

        if (!$this->previewDefinition()) {
            foreach ($fieldtype['can_have_typeobject_id'] as $typeobject_id) {
                // need to determine typeversion_id in case we want to create a new item here:
                $TV = new DBTableRowTypeVersion(false, null);
                $TV->getCurrentRecordByObjectId($typeobject_id);
                $typeversion_id = $TV->typeversion_id;
                //this line not needed, but I feel better checking
                if (!$typeversion_id) {
                    throw new Exception("formatInputTag(): typeversion_id not found");
                }
                $return_param = 'editsubcomponent,'.$fieldname.',itemversion'.$typeversion_id;

                // This is for the special case of creating a new item (procedure) from the New button where where we will be referencing ourself.
                $initialize_params = '';
                foreach (DBTableRowTypeVersion::groupConcatComponentsToFieldTypes($TV->list_of_typecomponents) as $targfieldname => $component_type) {
                    foreach ($component_type['can_have_typeobject_id'] as $targ_typeobject_id) {
                        if ( $targ_typeobject_id==$this->tv__typeobject_id) {
                            $initialize_params .= "&initialize[{$targfieldname}]={$this->itemobject_id}";
                        }
                    }
                }
                if (!$TV->isObsolete()) {
                    $link_info[] = array('js' => "document.theform.btnSubEditParams.value='action=editview&controller=struct&table=itemversion&itemversion_id=new&initialize[typeversion_id]={$typeversion_id}{$initialize_params}&subedit_return_value={$return_param}';document.theform.submit(); return false;",
                                     'desc' => $TV->type_description, 'pn' => $TV->type_part_number);
                }
            }
        }

        $buttons = '';
        if (count($link_info)==1) {
            $buttons = linkify('#', 'New', 'Add a component that does not already exist in the list', 'minibutton2', $link_info[0]['js']);
        } else if (count($link_info)>1) {
            $buttons = linkify('#', 'New...', 'Add a component that does not already exist in the list', 'minibutton2', "$(this).next('.comp_add_button_group').toggle(); return false;");
            $buttons .= '<div style="display:none;" class="comp_add_button_group">';
            foreach ($link_info as $li) {
                $buttons .= '<div>'.linkify('#', $li['desc'], 'Add a new '.$li['pn'].' ('.$li['desc'].')', '', $li['js']).'</div>';
            }
            $buttons .= '</div>';
        }

        $footer  = !is_valid_datetime($this->effective_date) ? '<span class="paren_red">Select Effective Date for more choices.</span>' : $buttons;
        return format_select_tag($select_values, $fieldname, $this->getArray(), $fieldtype['onchange_js']).'<br />'.$footer;
    }

    public static function getFieldCommentRecord($navigator, $comment_id, $gallery_id)
    {
        $config = Zend_Registry::get('config');
        $query = "
                SELECT comment.*, (SELECT GROUP_CONCAT(
                CONCAT(document.document_id,',',document.document_filesize,',',CONVERT(HEX(document.document_displayed_filename),CHAR),',',CONVERT(HEX(document.document_stored_filename),CHAR),',',document.document_stored_path,',',document.document_file_type,',',document.document_thumb_exists)
                ORDER BY document.document_date_added SEPARATOR ';') FROM document WHERE (document.comment_id = comment.comment_id) and (document.document_path_db_key='{$config->document_path_db_key}')) as documents_packed FROM comment
                WHERE comment_id='{$comment_id}'
            ";
        $records = DbSchema::getInstance()->getRecords('comment_id', $query);
        if (count($records)>0) {
            $record = reset($records);
            list($event_description,$event_description_array) = EventStream::textToHtmlWithEmbeddedCodes($record['comment_text'], $navigator, 'ET_COM', false);
            return array($record['documents_packed'] ? EventStream::documentsPackedToFileGallery(Zend_Controller_Front::getInstance()->getRequest()->getBaseUrl(), $gallery_id, $record['documents_packed']) : '', $event_description, $record);
        }
        return array("","",array());
    }

    protected function formatInputTagFieldAttachment($fieldname, $display_options = array())
    {
        $out = '';
        $got_a_valid_add_case = true;
        $initial_text = '';
        $got_something_to_edit = false;
        if (is_numeric($this->{$fieldname})) {
            list($documents_gallery_html, $comment_html, $record) = self::getFieldCommentRecord($this->_navigator, $this->{$fieldname}, 'id_edit_'.$this->{$fieldname});
            if ((count($record) > 0) && $record['is_fieldcomment']) {
                $got_something_to_edit = true;
                $initial_text = $record['comment_text']; // since there's a commment here, we can seed it if we want to use the add button.
                if ($record['documents_packed']) { // there are attachments
                    $got_a_valid_add_case = false;
                }
            }
        }

        if ($got_something_to_edit) {
            if (in_array('AlwaysAllowFieldAttachmentDelete', $display_options)  || !$record['documents_packed']) {
                $comment_actions['delete'] = array('buttonname' => 'Delete', 'privilege' => 'delete', 'confirm' => 'Are you sure you want to delete this?');
                $comment_actions['commenteditview'] = array('buttonname' => 'Edit', 'privilege' => 'view');
            } else {
                $comment_actions = DBTableRowComment::getListOfCommentActions($record['comment_added'], $record['user_id'], $record['proxy_user_id']);
            }
            $buttons = array();
            if (isset($comment_actions['commenteditview'])) {
                $buffer_key = $this->getTableName().$this->typeversion_id;
                $return_param = 'editfieldcomment,'.$fieldname.',comment';
                $js_go = "document.theform.btnSubEditParams.value='action=commenteditview&controller=struct&table=comment&comment_id={$this->{$fieldname}}&save_as_new=&buffer_key={$buffer_key}&fieldname={$fieldname}&subedit_return_value={$return_param}';document.theform.submit(); return false;";
                $detail_action = $comment_actions['commenteditview'];
                $icon_html = detailActionToHtml('commenteditview', $detail_action);
                $title = isset($detail_action['title']) ? $detail_action['title'] : $detail_action['buttonname'];
                $buttons[] = linkify('#', empty($icon_html) ? $detail_action['buttonname'] : $icon_html, $title, empty($icon_html) ? 'minibutton2' : '', $js_go, '', 'btn-edit-'.$this->{$fieldname});
            }

            if (isset($comment_actions['delete'])) {
                $buffer_key = $this->getTableName().$this->typeversion_id;
                $js_go = "document.theform.btnSubEditParams.value='action=deletefieldcomment&controller=struct&buffer_key={$buffer_key}&fieldname={$fieldname}';document.theform.submit(); return false;";
                $detail_action = $comment_actions['delete'];
                $icon_html = detailActionToHtml('delete', $detail_action);
                $title = isset($detail_action['title']) ? $detail_action['title'] : $detail_action['buttonname'];
                $confirm_js = isset($detail_action['confirm'])  ? "if (confirm('".$detail_action['confirm']."')) {".$js_go."} else {return true;};"
                                                                : (isset($detail_action['alert'])   ? "alert('".$detail_action['alert']."'); return false;"
                                                                                                    : "return true;");
                $buttons[] = linkify('#', empty($icon_html) ? $detail_action['buttonname'] : $icon_html, $title, empty($icon_html) ? 'minibutton2' : '', $confirm_js, '', 'btn-delete-'.$this->{$fieldname});
            }
            $out .= '<div class="bd-event-documents">';
            $out .= '<div style="float: right;">'.implode('', $buttons).'</div>';
            $out .= $documents_gallery_html;
            $out .= '</div><div>'.$comment_html.'</div>';
        }

        if ($got_a_valid_add_case) {
            $buffer_key = $this->getTableName().$this->typeversion_id;
            $return_param = 'editfieldcomment,'.$fieldname.',comment';
            $js = "document.theform.btnSubEditParams.value='action=commenteditview&controller=struct&table=comment&comment_id=new&initialize[itemobject_id]={$this->itemobject_id}&initialize[is_fieldcomment]=1&initialize[comment_text]=".rawurlencode($initial_text)."&buffer_key={$buffer_key}&fieldname={$fieldname}&subedit_return_value={$return_param}';document.theform.submit(); return false;";
            $out .= '<div>'.linkify('#', 'Add Attachments', 'add attachments and a caption to this field.', 'minibutton2', $js).'</div>';
        }

        return $out;
    }

    public function getAliases()
    {
        return DbSchema::getInstance()->getRecords('partnumber_alias', "select partnumber_alias, part_number, part_description, concat(part_number,' (',part_description,')') as description FROM partnumbercache WHERE typeversion_id='{$this->typeversion_id}' ORDER BY part_number");
    }

    /*
         * Take care of formatting the component selection widgets
     */
    public function formatInputTag($fieldname, $display_options = array())
    {
        $fieldtype = $this->getFieldType($fieldname);
        $attributes = isset($fieldtype['disabled']) && $fieldtype['disabled'] ? ' disabled' : '';
        $value = $this->$fieldname;

        switch (isset($fieldtype['type']) ? $fieldtype['type'] : '') {
            case 'component' :
                return $this->formatInputTagComponent($fieldname);

            case 'attachment' :
                return $this->formatInputTagFieldAttachment($fieldname, $display_options);

            // We don't want to use the normal jq_datetimepicker class for this, since we want to use our own handler
            case 'datetime' :
                if ($fieldname=='effective_date') {
                    if ($value == '0000-00-00 00:00:00') {  // equivalent of null in mysql
                        $value = '';
                    }
                    $current_effective_date_set = ($value && (strtotime($value) != -1));
                    $latest_effective_date = $this->getLatestOfComponentCreatedDates();

                    $now_btn = !$value ? '&nbsp<a class="minibutton2" id="effectiveDateNow" title="insert current time">now</a>' : '';
                    $date_warn_html = ($latest_effective_date!=null) ? '<div style="margin-top:4px;"><span class="'.(($current_effective_date_set && ($latest_effective_date>strtotime($value))) ? 'paren_red' : 'paren').'">Must be '.date("m/d/Y H:i", $latest_effective_date)." or later</span></div>" : '';

                    return '<div><INPUT class="inputboxclass" TYPE="text" NAME="'.$fieldname.'" VALUE="'.($current_effective_date_set ? date('m/d/Y H:i', strtotime($value)) : $value).'" SIZE="20" MAXLENGTH="24"'.$attributes.'>'.$now_btn.'</div>'.$date_warn_html;
                } else {
                    return parent::formatInputTag($fieldname, $display_options);
                }

            case 'boolean' :
                // put in an a revert symbol so we can unselect (effectively unanswering) this.
                $jsclear = "clear_field('".$fieldname."'); return false;";
                return '<a class="boolean_clearer" href="#" onclick="'.$jsclear.'" style="float:right;" title="unset this field" data-fname="'.$fieldname.'"><IMG style="vertical-align:middle;" src="'.Zend_Controller_Front::getInstance()->getRequest()->getBaseUrl().'/images/undoicon.gif" width="16" height="16" border="0" alt="delete"></a>'
                        .parent::formatInputTag($fieldname, $display_options);

            case 'calculated' :
                // put in a reload symbol for refreshing the calculated field.
                $jssub = "$('form').submit(); return false;";
                return '<a class="calc_reload_link" href="#" onclick="'.$jssub.'" style="float:right;" title="recalculate this field from the expression '.TextToHtml($fieldtype['expression']).'"><IMG style="vertical-align:middle;" src="'.Zend_Controller_Front::getInstance()->getRequest()->getBaseUrl().'/images/refresh-green-icon.png" width="16" height="16" border="0" alt="recalculate"></a>'
                        .parent::formatInputTag($fieldname, $display_options);

            default:

                switch ($fieldname) {
                    case 'item_serial_number' :
                        $SNFormat = SerialNumberType::typeFactory($this->_typeversion_digest['serial_number_format']);
                        if ((!$this->item_serial_number || !$this->isSaved()) && ($SNFormat->supportsGetNextNumber())) {
                            return '<div>'.parent::formatInputTag($fieldname, $display_options).'</div><div style="margin-top:4px;"><a class="minibutton2" id="serialNumberNew" title="get next available serial number">get next available</a></div>';
                        }
                        break;
                    case 'partnumber_alias' :
                        $records = $this->getAliases();
                        $arr = extract_column($records, 'description');
                        $this->setFieldAttribute($fieldname, 'type', 'enum');
                        $this->setFieldAttribute($fieldname, 'options', $arr);
                        return parent::formatInputTag($fieldname, $display_options);
                        break;
                    case 'typeversion_id' :
                        $TV = new DBTableRowTypeVersion();
                        $TV->getRecordById($this->typeversion_id);
                        $warning = '';
                        if (AdminSettings::getInstance()->use_any_typeversion_id) {
                            $warning =  '<div style="margin-top:4px;"><span class="paren_red">Type Version editing restrictions overridden.  Be careful!</span></div>';
                        } else if ((!$TV->isCurrentVersion()) && !$this->hasADisposition()) {
                            $warning =  '<div style="margin-top:4px;"><span class="paren_red">This is not the current version of this form.  You can select the current one from the above list.</span></div>';
                        }
                        $obsolete = $TV->isObsolete() ? '<div style="margin-top:4px;"><span class="disposition Obsolete">Obsolete</span><div>' : '';
                        return '<div>'.parent::formatInputTag($fieldname, $display_options).'</div>'.$warning.$obsolete;
                        break;
                    case 'user_id' :
                        if ($_SESSION['account']->getRole()=='DataTerminal') {
                            $this->setFieldCaption('user_id', 'Login ID');
                            $login_id_by_user_id = DbSchema::getInstance()->getRecords('user_id', "SELECT user_id,login_id FROM user where (user_type not in ('DataTerminal','Guest')) and (user_enabled=1) order by login_id");
                            $login_id_by_user_id = extract_column($login_id_by_user_id, 'login_id');
                            return format_select_tag($login_id_by_user_id, $fieldname, $this->getArray());
                        } else {
                            return parent::formatInputTag($fieldname, $display_options);
                        }
                        break;
                }
        }
        return parent::formatInputTag($fieldname, $display_options);
    }

    /**
     * creates a fully qualified URL that will take a browser to the itemview page for a specific itemobject.
     * If the current itemversion is NOT the most current one, then specify the itemversion number instead.
     * The assumption is that under 99.9 % of cases, one wants to link to the head.  Only in the case where one
     * is looking at a non-current version is it OK to assume we mean we want to link to a non-current version.
     * @param boolean $force_iv  makes it iv/nnnn style even if it's the current version.
     * @return string
     */
    public function absoluteUrl($force_iv = false)
    {
        $use_itemobject_id = (isset($this->io__cached_current_itemversion_id) && is_numeric($this->io__cached_current_itemversion_id) && ($this->io__cached_current_itemversion_id==$this->itemversion_id));
        return !$force_iv && $use_itemobject_id ? formatAbsoluteLocatorUrl('io', $this->itemobject_id) : formatAbsoluteLocatorUrl('iv', $this->itemversion_id);
    }

    public function locatorTerm($is_html)
    {
        $arr = explode('/', $this->absoluteUrl());
        $n = count($arr);
        $normal_locator = $arr[$n-2].'/'.$arr[$n-1];

        $arr = explode('/', $this->absoluteUrl(true));
        $n = count($arr);
        $iv_locator = $arr[$n-2].'/'.$arr[$n-1];

        $add_on_locator = $normal_locator!=$iv_locator ? ($is_html ? '<br /><span style="color:#888;">'.$iv_locator.'</span>' : ' or '.$iv_locator) : '';

        return $normal_locator.$add_on_locator;
    }

    public static function fetchOrphanedFieldsHtml($value_array)
    {
        $out = array();
        if (is_array($value_array)) {
            foreach ($value_array as $f => $v) {
                $out[] = ucwords(str_replace('_', ' ', $f)).':&nbsp;<b>'.TextToHtml($v).'</b>';
            }
        }
        return count($out)==0 ? '' : '<ul class="bd-bullet_features"><li style="margin-left:0px;">'.implode('</li><li style="padding-left:0px;">', $out).'</li></ul>';
    }

    /**
     * Creates a bulleted list of components in the hidden_components_array field
     * @param UrlCallRegistry $navigator
     * @param array $value_array
     * @return string HTML formatted list
     */
    public static function fetchOrphanedComponentsHtml($navigator, $value_array)
    {
        $is_html=true;
        $out = array();
        if (is_array($value_array)) {
            foreach ($value_array as $f => $v) {
                $ComponentItemVersion = null;
                if (is_numeric($v)) {
                    $ComponentItemVersion = new DBTableRowItemVersion(false, null);
                    $ComponentItemVersion->getCurrentRecordByObjectId($v);
                }
                $text_name = ucwords(str_replace('_', ' ', $f));
                $out[] = ucwords(str_replace('_', ' ', $f)).':&nbsp;'.self::fetchComponentPrintFieldHtml($navigator, $ComponentItemVersion, $text_name, $v, $is_html);
            }
        }
        return count($out)==0 ? '' : '<ul class="bd-bullet_features"><li>'.implode('</li><li>', $out).'</li></ul>';
    }

    public static function fetchComponentPrintFieldHtml($navigator, $ComponentItemVersion, $text_name, $value, $is_html)
    {
        $text = !is_null($ComponentItemVersion) && $ComponentItemVersion->hasAValidTypeVersionId() ? $ComponentItemVersion->shortName() : '';
        if (($navigator instanceof UrlCallRegistry)) {
            $query_params = array();
            $query_params['itemobject_id'] = $value;
            $query_params['return_url'] = $navigator->getCurrentViewUrl();
            $query_params['resetview'] = 1;
            $edit_url = $navigator->getCurrentViewUrl('itemview', '', $query_params);
            $html_text = linkify( $edit_url, $text, "View {$text_name}: {$text}");
        } else {
            $html_text = TextToHtml($text);
        }

        if (!is_null($ComponentItemVersion) && $ComponentItemVersion->hasAValidTypeVersionId() && !$ComponentItemVersion->hasASerialNumber()) {
            $features = array();
            foreach ($ComponentItemVersion->getFeaturedFieldTypes() as $fname => $fieldtype) {
                if (trim($ComponentItemVersion->{$fname})!=='') {
                    $features[] = '<li><span class="label">'.$ComponentItemVersion->formatFieldnameNoColon($fname).':</span> <span class="value">'.$ComponentItemVersion->formatPrintField($fname).'</span></li>';
                }
            }
            $featuresstr = implode('', $features);
            if ($featuresstr!='') {
                $featuresstr = '<div class="bd-event-message proc-component"><ul>'.$featuresstr.'</ul></div>';
            }
            $html_text .= '<span class="in-field-disposition">'.DBTableRowItemVersion::renderDisposition($ComponentItemVersion->getFieldType('disposition'), $ComponentItemVersion->disposition).'</span>'.$featuresstr;
        }
        return $is_html ? $html_text : $text;
    }


    public function formatEffectiveDatePrintField($is_html)
    {
        $value = $this->effective_date;
        $fmt_date = ($value && (strtotime($value) != -1)) ? date('M j, Y G:i', strtotime($value)) : $value;
        $fmt_created = date('M j, Y G:i', strtotime($this->createdOnDate()));
        $is_only_version = $fmt_date==$fmt_created;
        if ($is_html && $this->isCurrentVersion()) {
            if ($this->is_user_procedure) {
                if (!$is_only_version) {
                    $out = $fmt_created.' <span style="margin-left:4px; font-style:italic;" class="date_label"></span>';
                    $out .= '<br />'.$fmt_date.' <span style="margin-left:4px; font-style:italic;" class="date_label">last edit</span>';
                    return $out;
                }
            } else {
                // note that the extra styling is for the benefit of the PDF generation
                $out = $fmt_created.' <span style="margin-left:4px; font-style:italic;" class="date_label">created</span>';
                if (!$is_only_version) {
                    $out .= '<br />'.$fmt_date.' <span style="margin-left:4px; font-style:italic;" class="date_label">last change</span>';
                }
                return $out;
            }
        }
        return $fmt_date;
    }

    public function getDispositionDetails()
    {
        $out = array();
        if ($this->isCurrentVersion() && $this->disposition) {
            $records = DBSchema::getInstance()->getRecords('', "SELECT iv.itemversion_id,iv.effective_date,iv.user_id,iv.disposition,user.first_name,user.last_name
					FROM itemversion iv
					LEFT JOIN user ON user.user_id=iv.user_id
					WHERE iv.itemobject_id='{$this->itemobject_id}'
					ORDER BY iv.effective_date");
            // find the most oldest record that last set the current disposition
            $foundkey = null;
            $prev_disposition = null;
            foreach ($records as $key => $record) {
                if ((($record['disposition']==$this->disposition) && ($prev_disposition!=$record['disposition']))) {
                    $foundkey = $key;
                }
                $prev_disposition = $record['disposition'];
            }
            if (!is_null($foundkey)) {
                $out = $records[$foundkey];
            }
        }
        return $out;
    }

    public static function fetchOrphanedAttachmentsHtml($navigator, $value_array)
    {
        $is_html=true;
        $out = array();
        if (is_array($value_array)) {
            foreach ($value_array as $f => $v) {
                if (is_numeric($v)) {
                    $text_name = ucwords(str_replace('_', ' ', $f));
                    $out[] = ucwords(str_replace('_', ' ', $f)).':&nbsp;'.self::formatPrintFieldAttachment($navigator, $v, $is_html);
                }
            }
        }
        return count($out)==0 ? '' : '<ul class="bd-bullet_features"><li>'.implode('</li><li>', $out).'</li></ul>';
    }

    protected static function formatPrintFieldAttachment($navigator, $comment_id, $is_html)
    {
        $out = '';
        $got_a_valid_fieldcomment = false;
        if (is_numeric($comment_id)) {
            list($documents_gallery_html, $comment_html, $record) = self::getFieldCommentRecord($navigator, $comment_id, 'id_print_'.$comment_id);
            if ((count($record) > 0) && $record['is_fieldcomment']) {
                $got_a_valid_fieldcomment = true;
            }
        }
        if ($got_a_valid_fieldcomment) {
            if (count($record) != 0) { // only do this if the comment record really exists.
                if ($is_html) {
                    $out = '<div class="bd-event-documents">';
                    $out .= $documents_gallery_html;
                    $out .= '</div>';
                    $out .= $comment_html;
                } else {
                    $max_len_all_filenames = 100;
                    $max_len_one_filename = 40;
                    $max_len_comment = 80;
                    $fieldnames = array();
                    if ($record['documents_packed']) {
                        foreach (explode(';', $record['documents_packed']) as $document_packed) {
                            list($document_id,$document_filesize,$document_displayed_filename,$document_stored_filename,$document_stored_path,$document_file_type,$document_thumb_exists) = explode(',', $document_packed);
                            $fieldnames[] = trunc_text(hextobin($document_displayed_filename), $max_len_one_filename);
                        }
                    }
                    $document_summary = trunc_text((count($fieldnames) > 0) ? ' and file(s): '.implode(', ', $fieldnames) : '', $max_len_all_filenames);
                    $out = ($record['comment_text'] ? '"'.trunc_text($record['comment_text'], $max_len_comment).'" ' : '').'('.$comment_id.')'.$document_summary;
                }

            } else {
                $out = '(unknown id:'.$comment_id.')';
            }
        }
        return $out;
    }

    public function formatPrintField($fieldname, $is_html = true, $nowrap = true, $show_float_units = false)
    {
        $fieldtype = $this->getFieldType($fieldname);
        $value = $this->$fieldname;
        $type = !empty($fieldtype['type']) ? $fieldtype['type'] : '';
        switch ($type) {
            case 'component' :
                $ComponentItemVersion = $this->getComponentAsIVObject($fieldname);
                $text_name = $this->formatFieldnameNoColon($fieldname);
                // if this component can be more than one type, we should say what that type is.
                $type_addon = (count($fieldtype['can_have_typeobject_id']) > 1) && !is_null($ComponentItemVersion) ? ' ['.$ComponentItemVersion->part_description.']' : '';
                return self::fetchComponentPrintFieldHtml($this->_navigator, $ComponentItemVersion, $text_name, $value, $is_html).$type_addon;
            case 'attachment' :
                return self::formatPrintFieldAttachment($this->_navigator, $this->{$fieldname}, $is_html);
            default:
                // if float type and there are units, then show them if $show_float_units
                if (in_array($type, array('float','calculated')) && $show_float_units) {
                    $suffix = !empty($fieldtype['units']) && $is_html ? ' '.$fieldtype['units'] : '';
                    $valout = parent::formatPrintField($fieldname, $is_html, $nowrap);
                    return $valout!=='' ? parent::formatPrintField($fieldname, $is_html, $nowrap).$suffix : '';
                }
                switch ($fieldname) {
                    case 'disposition':
                        if ($is_html && $value) {
                            $arr = $this->getDispositionDetails();
                            $last_changed_by_html = !empty($arr) ? '<br /><span style="font-style:italic;" class="nm">by '.TextToHtml(strtoupper(DBTableRowUser::concatNames($arr))).'</span><br /><span style="font-style:italic;" class="tm">'.date('M j, Y G:i', strtotime($arr['effective_date'])).'</span>' : '';
                            $out = '<div class="ds">'.self::renderDisposition($fieldtype, $value).$last_changed_by_html.'</div>';
                        } else {
                            $out = $value;
                        }
                        return $out;
                    case 'effective_date':
                        return $this->formatEffectiveDatePrintField($is_html);
                    case 'record_locator':
                        return $this->locatorTerm($is_html);
                    case 'hidden_properties_array':
                        return self::fetchOrphanedFieldsHtml($value);
                    case 'hidden_components_array':
                        return self::fetchOrphanedComponentsHtml($this->_navigator, $value);
                    case 'hidden_attachments_array':
                        return self::fetchOrphanedAttachmentsHtml($this->_navigator, $value);
                    case 'typeversion_id':
                        $TV = new DBTableRowTypeVersion();
                        $TV->getRecordById($this->typeversion_id);
                        if ($is_html) {
                            $obs = $TV->isObsolete() ? '<div style="line-height:2.0em;"><span class="disposition Obsolete">Obsolete</span><div>' : '';
                            $out = '<div class="pn">'.TextToHtml($this->part_number).'<br /><span class="tm">'.date('M j, Y G:i', strtotime($TV->effective_date)).'</span>'.$obs.'</div>';
                        } else {
                            $obs = $TV->isObsolete() ? ' [Obsolete]' : '';
                            $out = $this->part_number.' ('.date('M j, Y G:i', strtotime($TV->effective_date)).')'.$obs;
                        }
                        if ($is_html && Zend_Registry::get('customAcl')->isAllowed($_SESSION['account']->getRole(), 'struct', 'partlistview') && ($this->_navigator instanceof UrlCallRegistry)) {
                            $jump_url = UrlCallRegistry::formatViewUrl('itemdefinitionview', 'struct', array('typeversion_id' => $this->typeversion_id, 'resetview' => 1));
                            $def_button = linkify($jump_url, 'Definition', "Jump to Definition for this type", 'minibutton2');
                            $list_url = UrlCallRegistry::formatViewUrl('lv', 'struct', array('to' => $this->tv__typeobject_id, 'resetview' => 1));
                            $list_button = linkify($list_url, 'List All Items', "List all Items of this type", 'minibutton2');
                            $out .= '<div class="bt">'.$def_button.$list_button.'</div>';
                        }
                        return $out;
                    case 'user_id':
                        if ($_SESSION['account']->getRole()=='DataTerminal') {
                            $this->setFieldCaption('user_id', 'Login ID');
                            $login_id_by_user_id = DbSchema::getInstance()->getRecords('user_id', "SELECT user_id,login_id FROM user where (user_type not in ('DataTerminal','Guest')) and (user_enabled=1) order by login_id");
                            $login_id_by_user_id = extract_column($login_id_by_user_id, 'login_id');
                            return $is_html ? TextToHtml($login_id_by_user_id[$this->user_id]) : $login_id_by_user_id[$this->user_id];
                        }
                    default:
                        return parent::formatPrintField($fieldname, $is_html, $nowrap);
                }
        }
    }

    public function getPageTypeTitleHtml($description_only = false)
    {
        $type_name = 'Unknown Item Type';
        if (is_numeric($this->typeversion_id) && is_numeric($this->partnumber_alias)) {
            $records = DbSchema::getInstance()->getRecords('partnumber_alias', "select partnumber_alias, part_number, part_description FROM partnumbercache WHERE typeversion_id='{$this->typeversion_id}' ORDER BY part_number");
            if (isset($records[$this->partnumber_alias])) {
                $rec = $records[$this->partnumber_alias];
                $type_name = $description_only ? TextToHtml($rec['part_description']) : TextToHtml($rec['part_number'].' ('.$rec['part_description'].')');
            }
        }
        return $type_name;
    }

    public function isCurrentVersion()
    {
        return $this->io__cached_current_itemversion_id==$this->itemversion_id;
    }

    public static function renderDisposition($fieldtype, $disposition, $is_html = true, $empty_val = '')
    {
        if (isset($fieldtype['options'][$disposition]) && $disposition) {
            $full_disposition = $fieldtype['options'][$disposition];
            return $is_html ? '<span class="disposition '.$disposition.'">'.$full_disposition.'</span>' : $full_disposition;
        } else {
            return $empty_val;
        }
    }



    /** return the editing links for a dependent row
     *
     * @param object $navigator
     * @param string $return_url
     * @param integer $itemobject_id
     * @param integer $comment_id
     * @param unknown_type $comments_added
     * @param integer $user_id
     * @return array of link structures
     */
    static public function itemversionEditLinks($navigator, $return_url, $delete_failed_return_url, $delete_done_return_url, $itemobject_id, $itemversion_id, $record_created, $user_id, $proxy_user_id, $is_a_procedure, $is_current_version)
    {
        $can_edit_self = true;
        $links = array();


        $config = Zend_Registry::get('config');
        $actions = array();

        $can_deleteblocked = false;
        $can_delete = false;
        $time = strtotime($record_created);
        $inside_grace_period = strtotime($record_created) + $config->delete_grace_in_sec > script_time();
        if (($_SESSION['account']->getRole() == 'Admin')) {
            if ($inside_grace_period) {
                $can_delete = true;
                $can_deleteblocked = false;
            } else { // outside of grace zone
                if (AdminSettings::getInstance()->delete_override) {
                    $can_delete = true;
                    $can_deleteblocked = false;
                } else {
                    $can_delete = false;
                    $can_deleteblocked = true;
                }
            }
        } else { // not an admin
            if ($_SESSION['account']->canIEditMyOwnVersion($user_id, $proxy_user_id, $record_created)) {
                $can_delete = true;
            }
        }

        $allow_new_version = Zend_Registry::get('customAcl')->isAllowed($_SESSION['account']->getRole(), 'ui:itemedit', $is_a_procedure ? 'new_proc_version' : 'new_part_version');
        $allow_edit_version = Zend_Registry::get('customAcl')->isAllowed($_SESSION['account']->getRole(), 'ui:itemedit', $is_a_procedure ? 'edit_proc_version' : 'edit_part_version');
        $allowed_to_alter_old_versions = Zend_Registry::get('customAcl')->isAllowed($_SESSION['account']->getRole(), 'ui:itemedit', 'can_edit_old_versions');
        $is_old_version = strtotime($record_created) < script_time() - Zend_Registry::get('config')->edit_grace_in_sec;
        $show_edit_version_button = ($allow_edit_version && (!$is_old_version || $allowed_to_alter_old_versions));
        if ($_SESSION['account']->canIEditMyOwnVersion($user_id, $proxy_user_id, $record_created)) {
            $show_edit_version_button = true;
        }

        if ($show_edit_version_button) {
            $links[] = linkify($navigator->getCurrentViewUrl('editview', null, array('table' => 'itemversion','itemversion_id' => $itemversion_id, 'initialize' => array('version_edit_mode' => 'vem_edit_version'), 'return_url' => $return_url, 'resetview' => 1)), 'Edit', 'Edit This Version', 'minibutton2', '', '', 'btn-edit-version-id-'.$itemversion_id);
        }

        if ($can_delete) {
            $actions['delete'] = array('buttonname' => 'Delete', 'privilege' => 'delete', 'confirm' => 'Are you sure you want to delete this?');
        }
        if ($can_deleteblocked) {
            $actions['delete'] = array('buttonname' => 'Delete (Blocked)', 'privilege' => 'delete', 'alert' => 'This record is older than '.(integer)($config->delete_grace_in_sec/3600).' hours.  If you want to delete it, you must go to the Settings menu and enable Delete Override.');
        }



        foreach ($actions as $action_name => $detail_action) {
            if (Zend_Registry::get('customAcl')->isAllowed($_SESSION['account']->getRole(), 'table:itemversion', $detail_action['privilege'])) {
                $icon_html = detailActionToHtml($action_name, $detail_action);
                $title = isset($detail_action['title']) ? $detail_action['title'] : $detail_action['buttonname'];
                $confirm_js = isset($detail_action['confirm'])  ? "return confirm('".$detail_action['confirm']."');"
                                                                : (isset($detail_action['alert'])   ? "alert('".$detail_action['alert']."'); return false;"
                                                                                                    : "return true;");
                $url = $navigator->getCurrentViewUrl($action_name, 'struct', array('table' => 'itemversion', 'itemversion_id' => $itemversion_id, 'return_url' => $delete_done_return_url, 'return_url_failed' => $delete_failed_return_url));
                $target = isset($detail_action['target']) ? $detail_action['target'] : "";
                $links[] = linkify($url, empty($icon_html) ? $detail_action['buttonname'] : $icon_html, $title, empty($icon_html) ? 'minibutton2' : '',
                        "{$confirm_js}", $target, $action_name.$itemversion_id);
            }
        }

        return $links;
    }

    /**
     * This assumes that the itemobject tables fields are in the current field list with the prefix io__.
     * The basically takes the latest comment, version, or reference
     */
    public function lastChangedBy()
    {
        if (!$this->io__cached_last_comment_date || (strtotime($this->io__cached_last_comment_date) <= strtotime($this->effective_date))) {
            if (!$this->io__cached_last_ref_date || (strtotime($this->io__cached_last_ref_date) <= strtotime($this->effective_date))) {
                return DBTableRowUser::getFullName($this->user_id);
            } else {
                return $this->io__cached_last_ref_person;
            }
        } else {
            if (!$this->io__cached_last_ref_date || (strtotime($this->io__cached_last_ref_date) <= strtotime($this->io__cached_last_comment_date))) {
                return $this->io__cached_last_comment_person;
            } else {
                return $this->io__cached_last_ref_person;
            }
        }
    }


    public function createdOnDate()
    {
        return $this->io__cached_first_ver_date;
    }

    public function hasDictionaryOverrides()
    {
        return isset($this->dictionary_overrides) && is_array(json_decode($this->dictionary_overrides, true));
    }

    public function hasDictionaryOverrideErrors()
    {
        $dictionary_overrides = $this->hasDictionaryOverrides() ? json_decode($this->dictionary_overrides, true) : array();
        foreach ($dictionary_overrides as $fieldname => $attributes) {
            if (is_array($attributes) && isset($attributes['error'])) {
                return true;
            }
        }
        return false;
    }

    public function applyDictionaryOverridesToFieldTypes()
    {
        $dictionary_overrides = isset($this->dictionary_overrides) && is_array(json_decode($this->dictionary_overrides, true)) ? json_decode($this->dictionary_overrides, true) : array();
        foreach ($dictionary_overrides as $fieldname => $attributes) {
            if (is_array($attributes)) {
                foreach ($attributes as $attr_key => $attr_value) {
                    if (in_array($attr_key, array('subcaption','error','locked','maximum','minimum','units'))) {
                        $this->setFieldAttribute($fieldname, $attr_key, $attr_value);
                    }
                }
            }
        }
    }

    /**
     * This will set field attributes for some of the header fields dependeing on if this is a part or procedure.
     * This is just to make it sound a little more seld-aware when editing or viewing.
     */
    public function applyCategoryDependentHeaderCaptions($is_editing)
    {
        if (!$is_editing) {
            $this->setFieldAttribute('typeversion_id', 'caption', $this->hasADisposition() ? 'Procedure Number' : 'Part Number');
            $this->setFieldAttribute('typeversion_id', 'subcaption', 'and version date');
        }
        $this->setFieldAttribute('effective_date', 'subcaption', $this->hasADisposition() ? 'when procedure performed' : 'when part created or changed');
    }


    /**
     * This is the form layout processor that takes the $fieldlayout (from the db field typeversion.type_form_layout) and outputs html
     * table rows.
     * @param array $fieldlayout
     * @param TableRow $dbtable
     * @param array $optionss  example: options = array('ALL' => array('Required'), 'track' => array('UseRadiosForMultiSelect'))
     * @param boolean $editable
     * @param unknown_type $callBackFunction
     * @throws Exception
     * @return string
     */
    static function fetchItemVersionEditTableTR($fieldlayout, DBTableRowItemVersion $dbtable, $errormsg = array(), $optionss = '', $editable = true, $callBackFunction = null, $fieldhistory = array())
    {
        $html = '';
        $editfields = $dbtable->getEditFieldNames();
        $dbtable->applyDictionaryOverridesToFieldTypes();
        $dbtable->applyCategoryDependentHeaderCaptions($editable);
        $calcparamfieldnames = $dbtable->getCalculatedParamFieldNames();

        foreach ($fieldlayout as $row) {
            if (!is_array($row)) {
                throw new Exception('DBTableRowItemVersion::fetchItemVersionEditTableTR(): layout row is not an array.');
            }

            if (!isset($row['type'])) {
                throw new Exception('DBTableRowItemVersion::fetchItemVersionEditTableTR(): row type is not defined.');
            }

            $row_class = ($row['type']=='html') ? 'editview_text' : '';

            if ($row['type']=='columns') {
                $html .= '<TR'.($row_class ? ' class="'.$row_class.'"' : '').'>';
                $is_single_column = (count($row['columns'])==1);
                foreach ($row['columns'] as $field_index => $field) {
                    $fieldname = $field['name'];
                    $fieldtype = $dbtable->getFieldType($fieldname);
                    if (isset($field['field_attributes'])) {
                        foreach ($field['field_attributes'] as $key => $attribute) {
                            $dbtable->setFieldAttribute($fieldname, $key, $attribute);
                        }
                    }
                    // set any add-on display options, e.g. UseRadiosForMultiSelect
                    $options = extractFieldOptions($optionss, $fieldname);
                    if (isset($field['display_options'])) {
                        $options = array_unique(array_merge($options, $field['display_options']));
                    }

                    $can_edit = in_array($fieldname, $editfields) && $editable;
                    $marker = $can_edit && (($dbtable->isRequired($fieldname) && !in_array('NotRequired', $options)) || in_array('Required', $options)) ? REQUIRED_SYM.' ' : '';
                    if ($can_edit && (isset($fieldtype['error']) || isset($fieldtype['locked']))) {
                        $can_edit = false;  // not allowed to edit something with a message
                        $marker = LOCKED_FIELD_SYM;
                    }

                    $cell_class = array($fieldname.'_cell');
                    $validate_msg = '';
                    if (isset($errormsg[$fieldname])) {
                        $validate_msg .= '<div class="errorred">'.$errormsg[$fieldname].'</div>';
                        $cell_class[] = 'cell_error';
                    }

                    if (isset($fieldtype['error'])) {
                        $validate_msg .= '<div class="errorred">'.$fieldtype['error'].'</div>';
                        $cell_class[] = 'cell_error';
                    }

                    if (in_array($fieldname, $calcparamfieldnames)) {
                        $cell_class[] = 'calc_param';
                    }

                    if ($callBackFunction!==null) {
                        $rhs_html = $callBackFunction($fieldname, $dbtable);
                    } else if ($can_edit) {
                        $rhs_html = $dbtable->formatInputTag($fieldname, $options);
                        if (isset($fieldtype['editinstructions']) && $fieldtype['editinstructions']) {
                            $rhs_html .= '<br /><div class="editinstructions">'.$fieldtype['editinstructions'].'</div>';
                        }
                    } else {
                        $rhs_html = $dbtable->formatPrintField($fieldname);
                        if (isset($fieldhistory[$fieldname])) {
                            $rhs_html .= EventStream::changeHistoryToHtmlPrintFieldDecoration($fieldhistory[$fieldname]);
                        }
                    }

                    $html .= '<TH class="'.implode(' ', $cell_class).'">'.$dbtable->formatFieldname($fieldname, $marker).'</TH>
						<TD class="'.implode(' ', $cell_class).'"'.($is_single_column ? ' colspan="3"' : '').'>'.$rhs_html.$validate_msg.'</TD>
								';
                }
                $html .= '</tr>';
            } else if ($row['type']=='html') {
                $html .= '<TR'.($row_class ? ' class="'.$row_class.'"' : '').'>';
                $html .= '<td colspan="4"><div class="editview_text_div">'.$row['html'].'<div></td>';
                $html .= '</tr>';
            }
        }
        return $html;
    }

    /** Override of standard touchtimers
     * (non-PHPdoc)
     * @see DBTableRow::startSelfTouchedTimer()
     */
    public function startSelfTouchedTimer()
    {
        self::startATouchedTimer('itemversion'.$this->tv__typeobject_id, $this->itemversion_id);
    }

    public function getTouchedRecently($max_time = null, $index_value = null, $scope_key = null)
    {
        if (empty($scope_key)) {
            $scope_key = 'itemversion'.$this->tv__typeobject_id;
        }
        if (empty($index_value)) {
            $index_value = $this->itemversion_id;
        }
        return DBTableRow::wasItemTouchedRecently($scope_key, $index_value, $max_time);
    }

    /**
     * Returns the number of months history based on most recent and oldest version or reference.
     */
    public function getTotalMonthsOfHistory()
    {
        $effective_date_exists = ($this->effective_date && (strtotime($this->effective_date) != -1));
        $most_recent_time = $effective_date_exists ? strtotime($this->effective_date) : script_time();
        $create_time = strtotime($this->createdOnDate());
        return ($most_recent_time - $create_time)/(30*24*3600);
    }

}

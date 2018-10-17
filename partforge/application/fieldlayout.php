<?php

/**
 *
 * PartForge Enterprise Groupware for recording parts and assemblies by serial number and version along with associated test data and comments.
 *
 * Copyright (C) 2013-2018 Randall C. Black <randy@blacksdesign.com>
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

$FIELDLAYOUT = array();

/*
  note that a row item can have one or two fields.  A text value 'field_name' is a shortcut for
  array('dbfield'=>'field_name').  Field attributes can be set here also with
  'field_attributes' => array('subcaption' => 'Bla Bla', 'caption' => 'Big Bla Bla').
  (Remember: This is not the right place to alter field attributes, because it
  might be inconsistent with data handler or editing checking logic.  Better to do that in
  fielddictionary.php or in the table class.)
  Display options can be set via 'display_options' = array('UseRadiosForMultiSelect',...);
  class is a special row-level attribute.  It is not included in the number of elements used
  to decide if this is a  single or double column row.
*/


$FIELDLAYOUT['comment']['editview'] = 
    array(
    	array(array('dbfield' => 'comment_text', 'field_attributes' => array('input_cols' => 70, 'input_rows' => 8))),
	);	

$FIELDLAYOUT['typecomment']['editview'] =
array(
		array(array('dbfield' => 'comment_text', 'field_attributes' => array('input_cols' => 70, 'input_rows' => 8))),
);

$FIELDLAYOUT['user']['editview'] = 
    array(
        array(array('dbfield' => 'first_name'),'last_name'),
        array('email'),
    	array('login_id','user_type'),
        array('user_enabled','has_temporary_password'),
        array('account_created','last_visit'),
        array('login_count','cached_items_created_count'),
    	array(array('dbfield' => 'comments', 'field_attributes' => array('input_cols' => 70, 'input_rows' => 8))),
        );

$FIELDLAYOUT['report']['editview'] = 
    array(
        array('report_title'),
        array('description'),
        array('query'),
        array('column_definitions'),
        array('group_key_field'),
        array('group_fields'),
        );
        
$FIELDLAYOUT['help']['editview'] = 
    array(
        array('help_tip'),
        array('help_markup'),
        );
        

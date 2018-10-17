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

/*
    This file contains some of the db table relationships and field definitions. This dictionary is a bit of a hold-over from
    previous version of PartForge when nearly all tables and data fields were defined like this.  Now there are only a few
    core tables done this way.
 
	array(
		'table' => table name. It is expected that this table will be constructed using DbSchema::dbTableRowObjectFactory(). So joins will be in there too
		'index' => this is the primary index name for this table.  By default it is the value from getPrimaryIndexName(table)
		'desc_func' => optional name of a method in the table DbTableRow object
		'desc_field' => optional field name with the text for describing this row
		'parent_index_in_parent' => for a dependent record. this is the name of the field in the parent table that we will match
					    by default it is the index name of the parent record
		'parent_index' => for a dependent record. This is the name of the field in this table that will match the value
				  of the field called parent_index_in_parent field in the parent record.  By default it is
				  parent_index_in_parent
		'parent_table' => in the case where a child is subordinate to an outgoing (and maybe incoming) joined table in the parent.
		'parent_calls_me/linkto_calls_me' => array('singular' => 'Admin Assistant', 'plural' => 'Admin Assistants').  singular used in short description and add button and title
		'linkto_table' => indicates that this record represents a link to another table.
		'linkto_index_in_parent' => for linkto, this is the name of the index in the parent table.  By default this is getPrimaryIndexName(linkto_table)
		'linkto_index' => for linkto, this is the name of the link pointer in the current table.
		'linkto_desc_field' => this is the field
		'children' => 	array(
				array(
					'table' => 'studentgrade',
					'parent_index_in_parent' => 'student_id',  // this is only a child if student_id is set
					'children' => 	array(
							)
				)
				)
	)
	
	
	Note: All valid relationships must be represented as either joins or tree relationships.  This ensures that we don't
	accidentally delete a parent of linked records.
  
*/

$DICTIONARY = array();
$DICTIONARY['tree'] =
		array(
		array(
			'heading' => 'USER',
			'children' => 	array(
					array(
						'table' => 'user',
						'children' => 	array(
								),
					)
					)
		),
		array(
			'heading' => 'COMMENTS (the only reason this record is here is to prevent deleting of comments without first removing documents)',
			'children' => 	array(
				array(
					'table' => 'comment',
					'children' => 	array(
						array(
							'table' => 'document',
							'children' => 	array(
							)
						),
					)
				)
			)
		),
		
		);

/*
  Warning: There are still plenty of places in the code where I assume that in an outgoing join, the rhs_index = lhs_table->getIndexName().
  I also assume that for incoming joins, the lhs_index = table->getIndexName().  So WATCH OUT!
  
  joins that are R mode are really just simple record id look up for convenience or to prefill selection boxes with choices.  They are not usually used for determining parent arrangements
  or dependencies. 
  
  one to one joins are represented by 'join' entries in the tables array with RW mode.
  
  many to one or many to many must be represented in the tree array.
  
$DICTIONARY['tables'] =
		array(
			'user' => array(
				'class' => '{classname for DbTableRow descendent}',
				'fields' => array(
					'{fieldname}' => array(
							'type' => 'enum',
							'options' => array('F' => 'F Option', 'G' => 'G Option', 'D' => 'D Option'),
							),
				),
				'joins' => array(
					'personal_information' => array('mode' => 'R', 'type' => 'outgoing', 'field_prefix' => 'p', 'lhs_index' => 'person_id', 'rhs_table' => 'person', 'rhs_index' => 'person_id'),
						),
			),
  
  
*/   

$DICTIONARY['tables'] =
		array(
			'user' => array(
				'class' => 'DBTableRowUser',
				'fields' => array(
					'user_type' => array(
							'type' => 'enum',
							'options' => array('Tech' => 'Technician', 'Eng' => 'Engineer/Author', 'Admin' => 'Administrator', 'DataTerminal' => 'Data Terminal', 'Guest' => 'Guest'),
							),
					'first_name' => array('input_cols' => 32),
					'last_name' => array('input_cols' => 32),
					'email' => array('input_cols' => 32),
					'login_id' => array('input_cols' => 32),
					'user_enabled' => array('caption' => 'Login Enabled'),
					'account_created' => array('mode' => 'R'),
					'login_count' => array('mode' => 'R'),
					'last_visit' => array('mode' => 'R'),
					'user_cryptpassword' => array('mode' => 'R'), 
					'cached_items_created_count' => array('mode' => 'R'), 
					'has_temporary_password' => array('mode' => 'R'), 
				),
			),
			'help' => array(
				'class' => 'DBTableRowHelp',
				'caption' => 'Help',
				'fields' => array(
					'help_markup' => array('caption' => 'Help Text', 'subcaption' => 'enter popup text in <a href="http://en.wikipedia.org/wiki/Markdown" target="_blank">Markdown syntax</a>', 'input_cols' => 70, 'input_rows' => 20),
					'help_tip' => array('subcaption' => 'message when hovering over help button'),
				),
			),
			'itemobject' => array(
				'class' => 'DBTableRowItemObject',
				'caption' => 'Item Object',
				'fields' => array(
				),
				'joins' => array(
					'item_version' => array('mode' => 'RW', 'type' => 'outgoing', 'options' => array('jo_link'), 'field_prefix' => 'iv', 'lhs_index' => 'cached_current_itemversion_id', 'rhs_table' => 'itemversion', 'rhs_index' => 'itemversion_id'),
				),
			),
			'itemversion' => array(
				'class' => 'DBTableRowItemVersion',
				'caption' => 'Item Version',
				'fields' => array(
					'effective_date' => array('subcaption' => 'when component created or changed, or procedure performed', 'required' => true),
					'item_serial_number' => array('required' => true, 'input_cols' => 30),
					'disposition' => array('type' => 'enum', 'options' => array('' => '', 'Pass' => 'Pass', 'Fail' => 'Fail', 'Review' => 'Needs Review', 'Invalid' => 'Invalid', 'InProcess' => 'In Process', 'SignedOff' => 'Signed Off' )),
					'typeversion_id' => array('caption' => 'Version of Type Definition'),
					'partnumber_alias' => array('caption' => 'Part Number'),
				),
				'joins' => array(
					'type_version' => array('mode' => 'R', 'type' => 'outgoing', 'options' => array('jo_link'), 'field_prefix' => 'tv', 'lhs_index' => 'typeversion_id', 'rhs_table' => 'typeversion', 'rhs_index' => 'typeversion_id'),
					'item_object' => array('mode' => 'R', 'type' => 'outgoing', 'options' => array('jo_link'), 'field_prefix' => 'io', 'lhs_index' => 'itemobject_id', 'rhs_table' => 'itemobject', 'rhs_index' => 'itemobject_id'),
				),
			),
			'typeobject' => array(
				'class' => 'DBTableRowTypeObject',
				'caption' => 'Type Object',
				'fields' => array(
				),
				'joins' => array(
					'type_version' => array('mode' => 'RW', 'type' => 'outgoing', 'options' => array('jo_link'), 'field_prefix' => 'tv', 'lhs_index' => 'cached_current_typeversion_id', 'rhs_table' => 'typeversion', 'rhs_index' => 'typeversion_id'),
				),
			),

			'typeversion' => array(
				'class' => 'DBTableRowTypeVersion',
				'caption' => 'Type Version',
				'fields' => array(
					'effective_date' => array('required' => true),
					'type_part_number' => array('required' => true),
					'type_description' => array('required' => true),
					'serial_number_type' => array('type' => 'enum', 'options' => array('3' => 'Simple Prefix', '0' => 'Free-form Serial Number', '1' => 'Generalized Sequential Number with Prefix/Suffix', '2' => 'MM-DD-YY-## where ## is a Sequential Number')),
					'typecategory_id' => array('caption' => 'Item Type'),
					'versionstatus' => array('caption' => 'Status', 'type' => 'enum', 'options' => array('A' => 'Active', 'D' => 'Draft', 'R' => 'Review' )),
						
				),
				'joins' => array(
					'type_object' => array('mode' => 'R', 'type' => 'outgoing', 'options' => array('jo_link'), 'field_prefix' => 'to', 'lhs_index' => 'typeobject_id', 'rhs_table' => 'typeobject', 'rhs_index' => 'typeobject_id'),
					'type_category' => array('mode' => 'R', 'type' => 'outgoing', 'options' => array('jo_link'), 'field_prefix' => 'tc', 'lhs_index' => 'typecategory_id', 'rhs_table' => 'typecategory', 'rhs_index' => 'typecategory_id'),
				),
			),
			'comment' => array(
				'class' => 'DBTableRowComment',
				'fields' => array(
				),
			),
			'typecomment' => array(
				'class' => 'DBTableRowTypeComment',
				'caption' => 'Type Comment',
				'fields' => array(
						'comment_text' => array('subcaption' => '<a href="#" class="a_pop_link">tips</a>
							                    <div style="display:none;">'.DBTableRowComment::commentTipsHtml().'</div>'),
				),
			),
);

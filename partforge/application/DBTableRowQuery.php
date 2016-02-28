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

    class DBTableRowQuery {
        private $_selectors=array();
        private $_and_where='';
        private $_tableobj=null;
        private $_order_by_clause='';
        private $_group_by_clause='';
        private $_limit_clause='';
        private $_select_fields='';
        private $_select_array=array();
        private $_join_array=array();
        protected $_fields = array();
        protected $_join_alias = array();
        
        
        public function __construct(DBTableRow $tableobj)
        {
            $this->_tableobj = $tableobj;
            $this->buildJoinAndSelect();
        }
        
        /*
          This assembles the basic query components.  Components can be changed or added to later.
        */
        private function buildJoinAndSelect() {
            $this->_join_array = array();
            $this->_join_alias = array();
            $join_fields_and_tables = $this->_tableobj->getJoinFieldsAndTables();
            $join_count = 0;
            foreach($join_fields_and_tables as $join_name => $target) {
                $join_count++;
                $this->_join_alias[$target['rhs_table']] = 't'.$join_count;
                $this->_join_array[] = "LEFT JOIN {$target['rhs_table']} as t{$join_count} ON {$this->_tableobj->getTableName()}.{$target['lhs_index']}=t{$join_count}.{$target['rhs_index']}";
            }
            
            $this->_select_array = array();
            $this->_select_array[] = "{$this->_tableobj->getTableName()}.*";
            foreach($join_fields_and_tables as $join_fieldname => $target) {
                foreach($target['rhs_dbtableobj']->getFieldNames() as $fieldname) {
                    $this->_select_array[] = "{$this->_join_alias[$target['rhs_table']]}.{$fieldname} as {$target['field_prefix']}__{$fieldname}";
                }
            }
            
        }
        
        public function getJoinAlias($join_table) {
            return isset($this->_join_alias[$join_table]) ? $this->_join_alias[$join_table] : '';
        }
        
        public function addSelectors($selectors) {
            $this->_selectors = array_merge($this->_selectors,$selectors);
            return $this;
        }
        
        public function addAndWhere($and_where) {
            $this->_and_where .= $and_where;
            return $this;
        }
        
        public function setOrderByClause($order_by_clause) {
            $this->_order_by_clause = $order_by_clause;
            return $this;
        }
        
        public function setLimitClause($limit_clause) {
            $this->_limit_clause = $limit_clause;
            return $this;
        }
        
        public function setGroupByClause($group_by_clause) {
            $this->_group_by_clause = $group_by_clause;
            return $this;
        }
        
        /*
          comma separated list of fields or array.  This overwrites list.  Use addSelectFields to augment the existing one.
        */
        public function setSelectFields($select_fields) {
            $this->_select_array = array();
            $this->addSelectFields($select_fields);
            return $this;
        }
        
        public function addSelectFields($select_fields) {
            if (!is_array($select_fields)) $select_fields = explode(',',$select_fields);
            $this->_select_array = array_merge($this->_select_array,$select_fields);
            return $this;
        }
        
        public function addJoinClause($join) {
            $this->_join_array[] = $join;
            return $this;
        }
        
        public function getQuery() {
            $sqlfields = implode(',',$this->_select_array);
            
            $and_where = $this->_and_where;
            foreach($this->_selectors as $fieldname => $value) {
                // no table "." or join "__" delimiters means the field refers to root table
                if (!str_contains($fieldname,'.') && !str_contains($fieldname,'__')) $fieldname = "{$this->_tableobj->getTableName()}.{$fieldname}";
                $and_where .= $value===null ? " and {$fieldname}=NULL" : " and {$fieldname}='".addslashes($value)."'";
            }
            
            return "SELECT {$sqlfields}
		FROM {$this->_tableobj->getTableName()}
                ".implode("\r\n",$this->_join_array)."
		WHERE (1=1)
		$and_where {$this->_group_by_clause} {$this->_order_by_clause} {$this->_limit_clause}";

        }

    }


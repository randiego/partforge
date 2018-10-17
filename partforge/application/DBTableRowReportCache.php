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

    class DBTableRowReportCache extends DBTableRow {
        
        public function __construct($ignore_joins=false,$parent_index=null) {
            parent::__construct('reportcache',$ignore_joins,$parent_index);  
            $this->last_run = time_to_mysqldatetime(script_time());
        }
        
        public function getRecordByClassName($class_name) {
        	$DBTableRowQuery = new DBTableRowQuery($this);
        	$DBTableRowQuery->setLimitClause('LIMIT 1')->addSelectors(array('class_name' => $class_name));
        	return $this->getRecord($DBTableRowQuery->getQuery());
        } 
       
    }

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

class DBTableRowTypeDocument extends DBTableRowDocument {
        
	public function __construct($user_id=null) {
		DBTableRow::__construct('typedocument');
		$this->document_date_added = time_to_mysqldatetime(script_time());
		if (is_null($user_id)) $user_id = $_SESSION['account']->user_id;
		$this->user_id = $user_id;
		$this->typeobject_id = -1;  // this is default until one is attached
		$this->document_path_db_key = Zend_Registry::get('config')->document_path_db_key;
		$this->document_stored_path = date('Y/m/',script_time()).$user_id;
	}

}

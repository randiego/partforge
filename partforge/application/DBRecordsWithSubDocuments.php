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

class DBRecordsWithSubDocuments extends DBRecords {
        
        protected $_document_type_id;

	public function __construct(DBTableRow $obj, $parent_id, $order_by,$document_type_id) {
		parent::__construct($obj, $parent_id, $order_by);
		$this->_document_type_id = $document_type_id;
	}

	public function getRecordsById($parent_id,$order_by='') {
		if ($order_by) {
                        $this->order_by = $order_by;
                }
		$this->getRecords("SELECT {$this->_row_object->getTableName()}.*, document.document_id FROM {$this->_row_object->getTableName()}
				LEFT JOIN document on (document.document_aux_id={$this->_row_object->getTableName()}.{$this->_row_object->getIndexName()})
                                                                and (document.document_type_id='{$this->_document_type_id}') 
				WHERE {$this->_row_object->getTableName()}.{$this->_parent_id_name}='{$parent_id}'
                                ORDER BY {$this->order_by}");
	}


	public function isDocumentPresent($key) {
                return isset($this->records[$key]['document_id']);
        }

	public function isAnyEditingAllowed() {
		// this could be optimized with a single call.
		$result = false;
		foreach($this->keys() as $key) {
		    if (!$this->isDocumentPresent($key)) {
                        $result = true;
                        break;
                    }
		}
		return $result;
	}

}

?>

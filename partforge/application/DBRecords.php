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

class DBRecords { 

	protected $records;
	protected $_row_object;
	protected $_parent_id_name;
	public $order_by;
	
	public function __construct(DBTableRow $row_object,$parent_id_name,$order_by='') {
		$this->records = array();
		$this->_row_object = $row_object;
		$this->_parent_id_name = $parent_id_name;
		$this->order_by = $order_by ? $order_by : $this->_row_object->getSortOrder();
	}
	
	// PERSISTENCE
        
    public function getRecords($query) {
		$this->records = DbSchema::getInstance()->getRecords($this->_row_object->getIndexName(),$query);
    }
        
    public function getQueryObject($parent_id,$order_by='') {
		if ($order_by) {
				$this->order_by = $order_by;
		}
		$DBTableRowQuery = new DBTableRowQuery($this->_row_object);
		if (!empty($this->_parent_id_name)) {
				$DBTableRowQuery->addSelectors(array($this->_parent_id_name => $parent_id));
		}
		$DBTableRowQuery->setOrderByClause("ORDER BY {$this->order_by}");
		return $DBTableRowQuery;
    }
	
	public function getRecordsById($parent_id,$order_by='') {
                $DBTableRowQuery = $this->getQueryObject($parent_id,$order_by);
                $this->getRecords($DBTableRowQuery->getQuery());
	}
	
	
	public function getArray()
	{
		return $this->records;
	}
        
	// inverse of getArray()
	public function assign($array) 
	{
		if (is_array($array)) {
			$this->records = $array;
		}
	}
	
	public function attachLooseItemsToParent($parent_id) {
		foreach ($this->keys() as $key) {
			if (!$this->hasBeenAttached($key)) {
				$this->records[$key][$this->_parent_id_name] = $parent_id;
				$this->_row_object->assign($this->records[$key]);
				$this->_row_object->save(array($this->_parent_id_name));
			}
	    }
	}
		
	public function hasBeenAttached($key) {
		return $this->records[$key][$this->_parent_id_name];
	}
		
	public function keys() {
		return array_keys($this->records);
	}
        
	public function getMinOfField($fieldname) {
			return min(extract_column($this->records,$fieldname));
	}
	
	public function getMaxOfField($fieldname) {
			return max(extract_column($this->records,$fieldname));
	}

	public function exists($key) {
			return is_array($this->records[$key]);
	}
	
	public function __call($name, $arguments) {
		if (in_array($name,$this->_row_object->getFieldNames()) && $this->exists($arguments[0])) {
			return $this->records[$arguments[0]][$name];
		}
	}
        
	public function getCurrentRowObject() {
			return $this->_row_object;
	}
	
	public function getRowObject($key) {
		if ($this->exists($key)) {
			$this->_row_object->assign($this->records[$key]);
			return $this->_row_object;
		} else {
			throw new Exception("Invalid key $key in ".get_class($this)."::getRowObject()");
		}
	}

	public function update(DBTableRow $Item, $parent_id) {
		// we expect that $Item already has all fields set except for parent_id
		// note that if ID field of Item is not 'new', this function updates the item rather than add it
		$Item->{$this->_parent_id_name} = 'new'==$parent_id ? 0 : $parent_id;
		$Item->save();
		$this->records[$Item->{$Item->getIndexName()}] = $Item->getArray();
		$this->onAfterUpdate($Item->{$this->_parent_id_name});
	}
        
	protected function onBeforeDelete($row_obj) {
		// override to do some that should be done before deleting db record
		return true; // true means go ahead an do it, otherwise exception
	}

	public function delete($key) {
                
                if ($this->exists($key)) {
                        $this->_row_object->assign($this->records[$key]);
                        if ($this->onBeforeDelete($this->_row_object)) {
                                $parent_id = $this->_row_object->{$this->_parent_id_name};
                                $this->_row_object->delete();
                                unset($this->records[$key]);
                                $this->onAfterUpdate($parent_id);
                        } else {
                                throw new Exception("Could not delete key $key in ".get_class($this)."::delete()");
                        }
		} else {
			throw new Exception("Invalid key $key in ".get_class($this)."::delete()");
                }
	}
        
    protected function onAfterUpdate($parent_id) {
            // stuff to be done after an update() or delete()
    }
        
    public function unsetItem($key) {
		if ($this->exists($key)) {
			unset($this->records[$key]);
		} else {
			throw new Exception("Invalid key $key in ".get_class($this)."::unsetItem()");
		}
    }
    
}

?>

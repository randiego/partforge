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

/*
    This is a thin redirect layer for the parent class.  It provides sensible defaults for any child classes but the actions are
    meant to be overridden as custom behavior is needed.
*/

class DBCustomControllerAction extends DBControllerActionAbstract
{
    
    public function init() {
        parent::init();
        $this->ensureTableParam();
    }
    
    public function indexAction() 
    {
        $this->navigator->jumpToView('listview');
    }
    
    protected function ensureTableParam() {
        $table = $this->getRequest()->getControllerName();
        
        if (!in_array($table,DbSchema::getInstance()->getTableNames())) {
            throw new Exception("controller name {$this->params['controller']} is not a valid table name in DBCustomControllerAction::ensureTableParam()");
        } else {
            $this->params['table'] = $table;
        }
    }
    
    public function editviewAction() {
        /*
            there are other ways to do this too.  First, I could redirect to /db/editview making sure ?table={tablename}.
            or set $this->params['table'] = 'user';   and  return parent::editviewAction();
        */
        $params = $this->getRequest()->getQuery();
        $this->navigator->jumpToView('editview','db',$params);
    }
    
    public function deleteAction() {
        return parent::deleteAction();
    }
    
    public function listviewAction() {
        $table_index = DbSchema::getInstance()->getPrimaryIndexName($this->params['table']);
        $this->navigator->jumpToView('listview','db',array($table_index => $this->params[$table_index], 'table' => $this->params['table']));
    }

    public function treeviewAction() {
        $table_index = DbSchema::getInstance()->getPrimaryIndexName($this->params['table']);
        $this->navigator->jumpToView('treeview','db',array($table_index => $this->params[$table_index], 'table' => $this->params['table']));
    }
}

?>

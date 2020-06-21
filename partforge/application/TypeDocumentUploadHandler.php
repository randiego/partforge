<?php
/**
 *
 * PartForge Enterprise Groupware for recording parts and assemblies by serial number and version along with associated test data and comments.
 *
 * Copyright (C) 2013-2020 Randall C. Black <randy@blacksdesign.com>
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

class TypeDocumentUploadHandler extends UploadHandler
{
	protected $db_typeobject_table; 
	protected $db_document_table;
    function __construct(DBTableRowTypeObject $db_typeobject_table) {
    	$uploader_options = array(
    			'script_url' => Zend_Controller_Front::getInstance()->getRequest()->getBaseUrl().'/struct/documentsajax/',
    			'upload_dir' => Zend_Registry::get('config')->document_path_base.Zend_Registry::get('config')->document_directory.'/',
    			'upload_url' => Zend_Controller_Front::getInstance()->getRequest()->getBaseUrl().Zend_Registry::get('config')->document_directory.'/',
    			'orient_image' => true,
    	);
    	parent::__construct($uploader_options,false);
    	$this->db_typeobject_table = $db_typeobject_table;        
    	$this->db_document_table = new DBTableRowTypeDocument();
    }
    
    public function getLastUploadedDocumentID() {
    	return $this->db_document_table->document_id;
    }
    
    /*
     * Returns the full path.  If file_name is specified then it includes the filename too.
     * The result depends on the current value of $this->db_document_table->document_stored_path 
     */
    protected function get_upload_path($file_name = null, $version = null) {
    	$file_name = $file_name ? $file_name : '';
    	$version_path = empty($version) ? '' : $version.'/';
    	return $this->options['upload_dir'].$this->db_document_table->document_stored_path.'/'.$version_path.$file_name;
    }    

    protected function get_file_objects($iteration_method = 'get_file_object') {
    	// if $iteration_method is set to 'is_valid_file_object' then we only need to return an array whos count is the number of files
    	$records = DbSchema::getInstance()->getRecords('document_id',"SELECT * FROM typedocument WHERE typeobject_id='{$this->db_typeobject_table->typeobject_id}'");
    	$out = array();
    	foreach($records as $document_id => $record) {
            $this->db_document_table = new DBTableRowTypeDocument();
            $this->db_document_table->assign($record);
            $fileobj = $this->get_file_object($record['document_stored_filename']); 
            if ($fileobj) {
                $fileobj->name = $this->db_document_table->document_displayed_filename;
            	$out[] = $fileobj;
            }
        }
        
        return $out;
    }    
    
    /*
     * Handles posted file uploads.  This stores the file, creates the thumb and adds a record to the DB.
     */
    protected function handle_file_upload($uploaded_file, $name, $size, $type, $error,
    		$index = null, $content_range = null) {
    	// we are uploading, so make a new one.
    	// use the comment user ID if we are not logged in.
    	$user_id = is_numeric($_SESSION['account']->user_id) ? $_SESSION['account']->user_id : $this->db_typeobject_table->user_id;
    	$this->db_document_table = new DBTableRowTypeDocument($user_id);
        
    	$fileobj = parent::handle_file_upload($uploaded_file, $name, $size, $type, $error,$index, $content_range);
    	if (empty($fileobj->error) && ($fileobj->size>0)) {
    		$this->db_document_table->document_displayed_filename = $name;
    		$this->db_document_table->document_stored_filename = $fileobj->name;
    		$this->db_document_table->document_filesize = $fileobj->size;
    		$this->db_document_table->document_file_type = $fileobj->type;
    		$this->db_document_table->document_thumb_exists = isset($fileobj->thumbnail_url);
    		if ($this->db_typeobject_table->isSaved()) {
    			$this->db_document_table->typeobject_id = $this->db_typeobject_table->typeobject_id;
    		}
    		$this->db_document_table->save();
    	}
    	return $fileobj;
    }  
    
    protected function get_download_url($file_name, $version = null) {
    	// download_via_php might not work, but I'm leaving it for now
    	if ($this->options['download_via_php']) {
    		$url = $this->options['script_url'].'?file='.rawurlencode($file_name);
    		if ($version) {
    			$url .= '&version='.rawurlencode($version);
    		}
    		return $url.'&download=1';
    	}
    	
    	$version_path = empty($version) ? '' : rawurlencode($version).'/';
    	return $this->options['upload_url'].$this->db_document_table->document_stored_path.'/'.$version_path.rawurlencode($file_name);
    }    


}

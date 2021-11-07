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

class MyUploadHandler extends UploadHandler
{
    protected $db_comment_table;
    protected $db_document_table;
    public function __construct(DBTableRowComment $db_comment_table, $process_now = true)
    {
        $uploader_options = array(
                'script_url' => Zend_Controller_Front::getInstance()->getRequest()->getBaseUrl().'/struct/documentsajax/',
                'upload_dir' => Zend_Registry::get('config')->document_path_base.Zend_Registry::get('config')->document_directory.'/',
                'upload_url' => Zend_Controller_Front::getInstance()->getRequest()->getBaseUrl().Zend_Registry::get('config')->document_directory.'/',
                'orient_image' => true,
        );
        parent::__construct($uploader_options, false);
        $this->db_comment_table = $db_comment_table;
        $this->db_document_table = new DBTableRowDocument();
        if ($process_now) {
            $this->initialize();
        }
    }

    public function getLastUploadedDocumentID()
    {
        return $this->db_document_table->document_id;
    }

    /*
     * Returns the full path.  If file_name is specified then it includes the filename too.
     * The result depends on the current value of $this->db_document_table->document_stored_path
     */
    protected function get_upload_path($file_name = null, $version = null)
    {
        return $this->db_document_table->getUploadPathByVersion($file_name, $version);
    }

    protected function set_file_delete_properties($file)
    {
        $file->delete_url = $this->options['script_url']
        .'?document_id='.rawurlencode($this->db_document_table->document_id).'&file='.rawurlencode($file->name);
        $file->delete_type = $this->options['delete_type'];
        if ($file->delete_type !== 'DELETE') {
            $file->delete_url .= '&_method=DELETE';
        }
        if ($this->options['access_control_allow_credentials']) {
            $file->delete_with_credentials = true;
        }
    }


    protected function get_file_objects($iteration_method = 'get_file_object')
    {
        // if $iteration_method is set to 'is_valid_file_object' then we only need to return an array whos count is the number of files
        if (count($this->db_comment_table->document_ids)>0) {
            $records = DbSchema::getInstance()->getRecords('document_id', "SELECT * FROM document WHERE document_id IN (".implode(',', $this->db_comment_table->document_ids).")");
        } else {
            $records = array();
        }
        $out = array();
        foreach ($records as $document_id => $record) {
            $this->db_document_table = new DBTableRowDocument();
            $this->db_document_table->assign($record);
            $fileobj = $this->get_file_object($record['document_stored_filename']);
            if ($fileobj) {
                $fileobj->name = $this->db_document_table->document_displayed_filename;
                // I have to do this again otherwise the built in one doesn't put in the icon. The db_document_table object has to be setup first.
                $fileobj->thumbnail_url = $this->get_download_url(null, 'thumbnail');
                $out[] = $fileobj;
            }
        }

        return $out;
    }

    /*
     * Handles posted file uploads.  This stores the file, creates the thumb and adds a record to the DB.
     */
    protected function handle_file_upload(
        $uploaded_file,
        $name,
        $size,
        $type,
        $error,
        $index = null,
        $content_range = null
    ) {
        // we are uploading, so make a new one.
        // use the comment user ID if we are not logged in.
        $user_id = is_numeric($_SESSION['account']->user_id) ? $_SESSION['account']->user_id : $this->db_comment_table->user_id;
        $this->db_document_table = new DBTableRowDocument($user_id);

        $fileobj = parent::handle_file_upload($uploaded_file, $name, $size, $type, $error, $index, $content_range);
        if (empty($fileobj->error) && ($fileobj->size>0)) {
            $this->db_document_table->document_displayed_filename = $name;
            $this->db_document_table->document_stored_filename = $fileobj->name;
            $this->db_document_table->document_filesize = $fileobj->size;
            $this->db_document_table->document_file_type = $fileobj->type;
            $this->db_document_table->document_thumb_exists = isset($fileobj->thumbnail_url);
            if ($this->db_comment_table->isSaved()) {
                $this->db_document_table->comment_id = $this->db_comment_table->comment_id;
            }
            $this->db_document_table->save();

            // in the call to handle_file_upload above it didn't yet have the document_id, so it set the json response incorrectly.  So now we populate the url fields with the actual document_ids
            $fileobj->url = $this->get_download_url(null);
            foreach ($this->options['image_versions'] as $version => $options) {
                $fileobj->{$version.'_url'} = $this->get_download_url(null, $version);
            }

            $this->set_file_delete_properties($fileobj);
            // add to the current list of documents being displayed.
            $this->db_comment_table->document_ids = array_unique(array_merge($this->db_comment_table->document_ids, array($this->db_document_table->document_id)));
        }
        return $fileobj;
    }

    public function delete($print_response = true)
    {
        $this->db_document_table = new DBTableRowDocument();
        if ($this->db_document_table->getRecordById($_GET['document_id'])) {
            $success = $this->db_document_table->unlinkAssociatedFiles();
            if ($success) {
                $this->db_comment_table->document_ids = array_diff($this->db_comment_table->document_ids, array($this->db_document_table->document_id));
                $this->db_document_table->delete();
            }
            return $this->generate_response(array('success' => $success), $print_response);
        }
    }

    protected function get_download_url($file_name, $version = null)
    {
        if ($this->options['download_via_php']) {
            throw new Exception('download_via_php option not allowed in MyUploadHandler:get_download_url().');
        }

        $fmt = empty($version) ? '' :  '?fmt='.$version;
        return Zend_Controller_Front::getInstance()->getRequest()->getBaseUrl().'/items/documents/'.$this->db_document_table->document_id.$fmt;
    }


}

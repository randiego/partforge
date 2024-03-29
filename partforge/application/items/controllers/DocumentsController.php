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

class Items_DocumentsController extends RestControllerActionAbstract
{

   /*
    * GET /items/documents
    *
    * Return a list of documents, subject to query variables.  For example,
    * /items/documents?itemobject_id=12 will return a list of document_ids belonging to the indicated itemobject.
    * Adding &get_info=1 will return an array of objects with detailed information about the document.
    */
    public function indexAction()
    {
        $select = "SELECT document.document_id, document.comment_id, document.document_displayed_filename as filename, document.document_thumb_exists as thumb_exists, document.document_filesize as size, document.document_file_type as mime_type, document.document_date_added as date_added, user.user_id, user.login_id
				FROM document
				LEFT JOIN comment ON comment.comment_id=document.comment_id
				LEFT JOIN user ON user.user_id=document.user_id
                LEFT JOIN itemobject ON itemobject.itemobject_id=comment.itemobject_id
				LEFT JOIN itemversion ON itemversion.itemversion_id=itemobject.cached_current_itemversion_id";

        $itemobject_id = isset($this->params['itemobject_id']) ? (is_numeric($this->params['itemobject_id']) ? addslashes($this->params['itemobject_id']) : null) : null;
        $get_info = isset($this->params['get_info']) && $this->params['get_info']  ? true : false;
        $field_name =  isset($this->params['field_name']) ? addslashes($this->params['field_name']) : null;
        $is_fieldcomment = isset($this->params['is_fieldcomment']) ? ($this->params['is_fieldcomment'] ? true : false) : false;

        if (!is_null($itemobject_id)) {
            $where = array();
            $where[] = "(comment.itemobject_id='{$itemobject_id}')";
            if ($is_fieldcomment) {
                $where[] = "((comment.is_fieldcomment=1) and EXISTS(SELECT * FROM itemcomment WHERE (itemversion.itemversion_id=itemcomment.belongs_to_itemversion_id) and (itemcomment.has_a_comment_id=comment.comment_id)))";
            } else {
                $where[] = "(comment.is_fieldcomment=0)";
            }
            if ($field_name) {
                $where[] = "((comment.is_fieldcomment=1) and EXISTS(SELECT * FROM itemcomment WHERE (itemversion.itemversion_id=itemcomment.belongs_to_itemversion_id) and (itemcomment.has_a_comment_id=comment.comment_id) and (itemcomment.field_name='{$field_name}')))";
            }
            $records = DbSchema::getInstance()->getRecords('', "{$select} WHERE ".implode(' AND ', $where)." ORDER BY document.document_date_added");
            if ($get_info) {
                $this->view->documents = $records;
            } else {
                $this->view->document_ids = extract_column($records, 'document_id');
            }
        }
    }

    public function headAction()
    {
        return $this->getAction(true);
    }

    /**
     * GET /items/documents/N
     *
     * outputs the given document to the browser where N = document_id
     * use fmt=thumbnail or medium for images where document_thumb_exists
     *
     * @see Zend_Rest_Controller::getAction()
     */
    public function getAction($headers_only = false)
    {
        $Document = new DBTableRowDocument();
        if ($Document->getRecordById($this->params['id'])) {
            $fmt = isset($this->params['fmt']) ? $this->params['fmt'] : 'full';
            if ($Document->document_thumb_exists) { // this  is one way we decide if this is an image vs some other document type.
                if ($fmt=='thumbnail') {
                    $Document->outputThumbnailImageToBrowser(true, $headers_only);
                } else if ($fmt=='medium') {
                    $Document->outputMediumImageToBrowser(true, $headers_only);
                } else if ($fmt=='full') {
                    $Document->outputToBrowser(false, true, $headers_only);
                } else if (($fmt=='customwidth') && is_numeric($this->params['width'])) {
                    $Document->outputCustomSizeImageToBrowser($this->params['width'], $headers_only);
                }
            } else {
                if ($fmt=='thumbnail') { // if I'm asking for a thumbnail and !document_thumb_exists, output an icon instead
                    $Document->outputIconToBrowser(true, $headers_only);
                } else {
                    $Document->outputToBrowser(true, true, $headers_only);
                }
            }
        } else {
            $this->view->errormessages = 'document not found.';
        }
    }

    /*
     * POST /items/documents?format=json&comment_id=N
     *
     * Create a new document from posted data and save it to comment_id=N.
     *
     * Input (at minimum):
     *  user_id
     *  comment_id
     *  effective_date
     *  item_serial_number
     *
     * Output (json):
     *  errormessages = []
     *  itemversion_id
     *  itemobject_id
     *
     */
    public function postAction()
    {
        $errormsg = array();
        $record = $this->params;

        try {
            if (isset($record['id'])) {
                $errormsg[] = 'The parameters for this call cannot contain an ID parameter.';
            }

            $Comment = DbSchema::getInstance()->dbTableRowObjectFactory('comment', false, 'itemobject_id');
            if (isset($record['comment_id'])) {
                if (!$Comment->getRecordById($record['comment_id'])) {
                    $errormsg[] = 'Comment_id = '.$record['comment_id'].' not found.';
                }
            } else {
                $errormsg[] = 'You must enter either a valid comment_id to associate the document with.';
            }

            if (empty($errormsg)) {
                $upload_handler = new MyUploadHandler($Comment, false);
                $upload_handler->post(false);
                $this->view->document_id = $upload_handler->getLastUploadedDocumentID();
            }
        } catch (Exception $e) {
            $errormsg[] = $e->getMessage();
        }

        $this->view->errormessages = $errormsg;

    }

    /*
     * PUT /items/documents/:id?format=json
     *
     * Update fields in the document table as specified by document_id = id
     *
     * There are a number of possibilities.  But the idea is that document_id is a minimum requirement
     * and any other fields specified should be updated.  But in practice, the only field we will
     * update is comment_id.  This is basically how one would link a document to a comment.
     *
     * Input (at minimum):
     *  document_id
     * Input (optional):
     *  itemobject_id
     *  comment_id
     *
     * Output (json):
     *  errormessages = []
     *
     */
    public function putAction()
    {
        $this->noOp();
        //$this->view->output = var_export($this->params,true);
    }

    public function deleteAction()
    {
        $this->noOp();
        /*
        $upload_handler = new MyUploadHandler(1234);
        $upload_handler->delete();
        die();
        */
    }
}

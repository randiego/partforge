<?php
/**
 *
 * PartForge Enterprise Groupware for recording parts and assemblies by serial number and version along with associated test data and comments.
 *
 * Copyright (C) 2013-2021 Randall C. Black <randy@blacksdesign.com>
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

class Types_DocumentsController extends RestControllerActionAbstract
{

    /*
     * GET /types/documents?comment_id=N&format=json
     *
     * Return a list of documents for the specified comment_id
     * Input (at minimum):
     *  comment_id
     *
     * Output (json):
     *  errormessages = []
     *  documents = []
     */

    public function indexAction()
    {
        $this->noOp();
    }

    public function headAction()
    {
        return $this->getAction(true);
    }

    public function getAction($headers_only = false)
    {
        $Document = new DBTableRowTypeDocument();
        if ($Document->getRecordById($this->params['id'])) {
            if ($Document->document_thumb_exists) {
                $fmt = isset($this->params['fmt']) ? $this->params['fmt'] : 'full';
                if ($fmt=='thumbnail') {
                    $Document->outputThumbnailImageToBrowser();
                } else if ($fmt=='medium') {
                    $Document->outputMediumImageToBrowser();
                } else if ($fmt=='full') {
                    $Document->outputToBrowser(false);
                } else if (($fmt=='customwidth') && is_numeric($this->params['width'])) {
                    $Document->outputCustomSizeImageToBrowser($this->params['width']);
                }
            } else {
                $Document->outputToBrowser(true, true, $headers_only);
            }
        } else {
            $this->view->errormessages = 'document not found.';
        }
    }

    /*
     * POST /types/documents?format=json
     *
     * Create a new document from posted data.
     *
     * Input (at minimum):
     *  user_id
     *  typeobject_id
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

            $TypeObject = new DBTableRowTypeObject(false, null);
            if (isset($record['typeobject_id'])) {
                if (!$TypeObject->getRecordById($record['typeobject_id'])) {
                    $errormsg[] = 'TypeObject_id = '.$record['typeobject_id'].' not found.';
                }
            } else {
                $errormsg[] = 'You must enter either a valid typeobject_id to associate the document with.';
            }

            if (empty($errormsg)) {
                $upload_handler = new TypeDocumentUploadHandler($TypeObject);
                $upload_handler->post(false);
                $this->view->document_id = $upload_handler->getLastUploadedDocumentID();
            }
        } catch (Exception $e) {
            $errormsg[] = $e->getMessage();
        }

        $this->view->errormessages = $errormsg;

    }

    /*
     * PUT /types/documents/:id?format=json
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
    }

    public function deleteAction()
    {
        $this->noOp();
    }
}

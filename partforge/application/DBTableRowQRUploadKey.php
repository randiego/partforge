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

class DBTableRowQRUploadKey extends DBTableRow {

    public function __construct()
    {
        parent::__construct('qruploadkey');
    }

    public function getRecordByUploadKey($key)
    {
        return $this->getRecordWhere("qruploadkey_value='".addslashes($key)."'");
    }

    public function deleteChildren()
    {
        DbSchema::getInstance()->deleteRecord('qruploaddocument', $this->qruploadkey_id, 'qruploadkey_id', "");
    }

    public function timeElapsed()
    {
        return isset($this->created_on) ? script_time() - strtotime($this->created_on) : null;
    }

    /**
     * Return an array of document_ids from the qruploaddocument table. Do a little house-cleaning while at it.
     *
     * @return array of document_id numbers
     */
    public function syncAndGetDocumentIds()
    {
        $records = DbSchema::getInstance()->getRecords('qruploaddocument_id', "SELECT qruploaddocument.qruploaddocument_id, document.document_id FROM qruploaddocument
        LEFT JOIN document ON qruploaddocument.document_id=document.document_id
        WHERE qruploaddocument.qruploadkey_id='".$this->qruploadkey_id."'
        ORDER BY qruploaddocument.qruploaddocument_id");
        $out = array();
        foreach ($records as $qruploaddocument_id => $record) {
            if (isset($record['document_id'])) {
                $out[] = $record['document_id'];
            } else {
                $QRUploadDocument = new DBTableRow('qruploaddocument');
                if ($QRUploadDocument->getRecordById($qruploaddocument_id)) {
                    $QRUploadDocument->delete();
                }
            }
        }
        return $out;
    }

    /**
     * Take an array of document_ids and make sure there are matching records in the qruploaddocument table
     * by adding and deleting as needed.
     *
     * @param array $document_ids
     *
     * @return void
     */
    public function saveDocumentIds($document_ids)
    {
        $records = DbSchema::getInstance()->getRecords('qruploaddocument_id', "SELECT qruploaddocument_id, document_id FROM qruploaddocument
        WHERE qruploadkey_id='".$this->qruploadkey_id."'");
        $existing_document_ids = array();
        // for each record in the database, we see if it needs to be deleted
        foreach ($records as $qruploaddocument_id => $record) {
            if (!in_array($record['document_id'], $document_ids)) {
                $QRUploadDocument = new DBTableRow('qruploaddocument');
                if ($QRUploadDocument->getRecordById($qruploaddocument_id)) {
                    $QRUploadDocument->delete();
                }
            } else {
                $existing_document_ids[] = $record['document_id'];
            }
        }
        // for each of the new current document_ids we see if it needs to be added
        foreach ($document_ids as $document_id) {
            if (!in_array($document_id, $existing_document_ids)) {
                $QRUploadDocument = new DBTableRow('qruploaddocument');
                $QRUploadDocument->qruploadkey_id = $this->qruploadkey_id;
                $QRUploadDocument->document_id = $document_id;
                $QRUploadDocument->save();
            }
        }
    }

    public static function cleanupOldRecords()
    {
        // cleanup anything older than 24 hours
        DbSchema::getInstance()->mysqlQuery("DELETE qruploaddocument FROM qruploaddocument
        			INNER JOIN qruploadkey ON qruploadkey.qruploadkey_id=qruploaddocument.qruploadkey_id
		        	WHERE (UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(qruploadkey.created_on))/3600 > 24");
        DbSchema::getInstance()->mysqlQuery("DELETE qruploadkey FROM qruploadkey
                    WHERE (UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(created_on))/3600 > 24");
    }

}

<?php
/**
 *
 * PartForge Enterprise Groupware for recording parts and assemblies by serial number and version along with associated test data and comments.
 *
 * Copyright (C) 2013-2025 Randall C. Black <randy@blacksdesign.com>
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

namespace App;

require_once(__DIR__.'/../library/tcpdf/tcpdf.php');
require_once(__DIR__.'/../library/fpdi/src/autoload.php');

class ItemViewNestedPDF extends \setasign\Fpdi\Tcpdf\Fpdi {

    public $dbtable;
    protected $temporary_directory_unique;

    public function __construct()
    {
        parent::__construct('P', 'mm', 'LETTER');
        $this->SetAutoPageBreak(true, 10);
        $this->SetDisplayMode('default', 'continuous');  // makes it nicer to scroll through a multipage PDF.
        $this->setPrintFooter(true);
    }

    public static function tempDirPrefix()
    {
        return \Zend_Registry::get('config')->document_path_base.\Zend_Registry::get('config')->document_directory.'/temp-';
    }

    public function addTemporaryStorage($itemversion_id)
    {
        $this->temporary_directory_unique = $itemversion_id.'-'.date('Ymd-His', script_time());
        if (!is_dir(self::tempDirPrefix().$this->temporary_directory_unique)) {
            mkdir(self::tempDirPrefix().$this->temporary_directory_unique, 0755, true);
        }
        return $this->temporary_directory_unique;
    }

    public static function deleteTemporaryStorage($temporary_directory_unique)
    {
        $dir = self::tempDirPrefix().$temporary_directory_unique;
        if (is_dir($dir)) {
            $items = scandir($dir);
            foreach ($items as $item) {
                // Skip special entries "." and ".."
                if (!in_array($item, ['.', '..'])) {
                    unlink($dir . DIRECTORY_SEPARATOR . $item);
                }
            }
            rmdir($dir);
        }
    }

    public static function flattenFullNestedObject($fields, &$flatlist = array(), $level = 0, $self_field_name = '')
    {
        if (isset($fields['item_serial_number']) || ($level == 0)) { // special exception for level 0 for procedures as a starting point
            $ItemVersion = new \DBTableRowItemVersion();
            if ($ItemVersion->getRecordById($fields['itemversion_id'])) {
                $identifier = $ItemVersion->hasASerialNumber() ? $fields['item_serial_number'] : $ItemVersion->formatEffectiveDatePrintField(false);
                $flatlist[$fields['itemversion_id']] = array('item_serial_number' => $identifier, 'typeversion_id' => $fields['typeversion_id'], 'level' => $level, 'self_field_name' => $self_field_name);
                foreach ($fields as $name => $value) {
                    if (is_array($value) && isset($value['item_serial_number'])) {
                        self::flattenFullNestedObject($value, $flatlist, $level + 1, $ItemVersion->formatFieldnameNoColon($name));
                    }
                }
            }
        }
    }


    protected function renderNestedItemViewPdfs($queryvars)
    {
        $flatlist = array();
        if (is_dir(self::tempDirPrefix().$this->temporary_directory_unique)) { // just make sure we have a place to write the files
            $level = 0;
            self::flattenFullNestedObject(\DBTableRowItemObject::getItemObjectFullNestedArray($this->dbtable->itemobject_id), $flatlist, $level);
            foreach ($flatlist as $itemversion_id => $objfields) {
                $ItemVersion = new \DBTableRowItemVersion();
                if ($ItemVersion->getRecordById($itemversion_id)) {
                    $Pdf = new \ItemViewPDF();
                    $Pdf->dbtable = $ItemVersion;
                    $Pdf->buildDocument($queryvars);
                    $full_pdf_name = self::tempDirPrefix().$this->temporary_directory_unique.'/'.make_filename_safe('ItemVersion_'.$itemversion_id.'_'.$objfields['item_serial_number']).'.pdf';
                    $Pdf->Output($full_pdf_name, 'F');
                    $flatlist[$itemversion_id]['pdf_file'] = $full_pdf_name;
                }
            }
        }
        return $flatlist;
    }

    public function buildDocument($queryvars)
    {
        $flatlist = $this->renderNestedItemViewPdfs($queryvars);

        // Process each file
        foreach ($flatlist as $fileobj) {
            $pageCount = $this->setSourceFile($fileobj['pdf_file']);
            // Import all pages
            for ($i = 1; $i <= $pageCount; $i++) {
                $tplIdx = $this->importPage($i);
                $s = $this->getTemplatesize($tplIdx);
                $this->AddPage($s['orientation'], $s);
                $this->useTemplate($tplIdx);
                if ($i === 1) {
                    $this->Bookmark((!empty($fileobj['self_field_name']) ? $fileobj['self_field_name'].': ' : '').$fileobj['item_serial_number'], $fileobj['level']);
                }
            }
        }

        // add a new page for TOC
        $this->addTOCPage();

        // write the TOC title
        $this->SetFont('times', 'B', 16);
        $toc_page_title = $this->dbtable->getPageTypeTitleHtml(true, false);
        $this->MultiCell(0, 0, $toc_page_title, 0, 'C', 0, 1, '', '', true, 0);
        $this->Ln();

        $this->SetFont('freesans', '', 12);
        $this->addTOC(1, 'courier', '.', 'INDEX', 'B', array(128,0,0));
        $this->endTOCPage();
    }

    public function Footer()
    {
        $this->SetXY(0, -9);
        $this->Cell($this->w + 2.0, 0, $this->getAliasRightShift().$this->getAliasNumPage(), 0, 0, 'R');
    }

}

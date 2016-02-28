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

class DBTableRowDocument extends DBTableRow {
        
	public function __construct($user_id=null) {
		parent::__construct('document');
		$this->document_date_added = time_to_mysqldatetime(script_time());
		if (is_null($user_id)) $user_id = $_SESSION['account']->user_id;
		$this->user_id = $user_id;
		$this->comment_id = -1;  // this is default until one is attached
		$this->document_path_db_key = Zend_Registry::get('config')->document_path_db_key;
		$this->document_stored_path = date('Y/m/',script_time()).$user_id;
	}
	
	/**
	 * @param string $file_mime_type like 'application/pdf'
	 * @param string $filename like 'MyFile.pdf'
	 * @return string
	 */
	public static function findIconFileName($file_mime_type,$filename) {
		$icon = self::fileMimeTypeToIconName($file_mime_type);
		if (!$icon) {
			$icon = self::fileNameToIconName($filename);
		}
		if (!$icon) {
			$icon = 'attachfile.gif';
		}
		return $icon;
	}
	
	public static function fileNameToIconName($filename) {
		$decomp = pathinfo ( $filename );
		$ext = strtolower($decomp['extension']);
				
		$iconfiles = array(	'pdf' => 'pdficon.gif',
				'doc' => 'wordicon.gif',
				'docx' => 'wordicon.gif',
				'dot' => 'wordicon.gif',
				'rtf' => 'wordicon.gif',
				'xls' => 'excelicon.gif',
				'xlsx' => 'excelicon.gif',
				'xlk' => 'excelicon.gif',
				'csv' => 'excelicon.gif',
				'ppt' => 'ppticon.gif',
				'htm' => 'htmlicon.gif',
				'html' => 'htmlicon.gif',
				'css' => 'htmlicon.gif',
				'gif' => 'gificon.gif',
				'jpg' => 'jpgicon.gif',
				'jpeg' => 'jpgicon.gif',
				'png' => 'pngicon.gif',
				'zip' => 'zipicon.gif',
				'tar' => 'zipicon.gif',
				'gz' => 'zipicon.gif',
				'tgz' => 'zipicon.gif',
				'rar' => 'zipicon.gif',
				'arj' => 'zipicon.gif',
				'lzh' => 'zipicon.gif',
				'uc2' => 'zipicon.gif',
				'ace' => 'zipicon.gif',
				'txt' => 'texticon.gif',
				'log' => 'texticon.gif',
				'eml' => 'mailicon.gif',
				'xml' => 'code.gif',
				'htm' => 'htmlicon.gif',
				'html' => 'htmlicon.gif',
				'?' => 'attachfile.gif' );
	
		if (!$ext || !isset($iconfiles[$ext])) {
			return '';
		} else {
			return $iconfiles[$ext];
		}
	}	
	
	public static function fileMimeTypeToIconName($file_mime_type) {
		$file = '';  //'attachfile.gif';
		switch ($file_mime_type) {
			case 'application/pdf':
				$file = 'pdficon.gif'; break;
			case 'application/x-zip-compressed':
				$file = 'zipicon.gif'; break;
			case 'text/plain':
				$file = 'texticon.gif'; break;
			case 'text/csv':
			case 'application/vnd.ms-excel':
			case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
				$file = 'excelicon.gif'; break;
			case 'application/msword':
			case 'application/rtf':
			case 'text/richtext':
			case 'text/rtf':
			case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
				$file = 'wordicon.gif'; break;
			case 'application/vnd.ms-powerpoint':
			case 'application/vnd.openxmlformats-officedocument.presentationml.presentation':
				$file = 'ppticon.gif'; break;
			case 'application/vnd.oasis.opendocument.text':
				$file = 'openoffice.gif'; break;
			case 'video/mp4':
				$file = 'movie.gif'; break;
			case 'text/html':
				$file = 'htmlicon.gif'; break;
			case 'text/xml':
				$file = 'code.gif'; break;
		}
		return $file;
	}
	
	public function fullStoredFileName($image_version_path='') {
		$fname = Zend_Registry::get('config')->document_path_base.Zend_Registry::get('config')->document_directory.'/'.$this->document_stored_path.$image_version_path.'/'.$this->document_stored_filename;
		return $fname;
	}
	
	public function generateMediumImageIfNeeded() {
		$medium_file = $this->fullStoredFileName('/medium');
		if (!is_file($medium_file)) {
			$dir = dirname($medium_file);
			if (!is_dir($dir)) {
				mkdir($dir,0755, true);
			}
				
			$options = array(
					'max_width' => 1920,
					'max_height' => 1200,
					'jpeg_quality' => 90
			);
			UploadHandler::create_scaled_image_core($this->document_stored_filename, $options, $this->fullStoredFileName(), $this->fullStoredFileName('/medium'));
		}
	}
	
	/**
	 * This assumes we really are an image.  Then it checks to see if there is a cached /medium scale verion of the image available.
	 * If there is not, it creates one in the subdirectory /version under the location of the full size image (note: this is a peer of
	 * the thumbnail folder).  Finally it outputs the medium image.  It uses the UploadHandler:: class for doing the resizing.
	 */
	public function outputMediumImageToBrowser($cache_it=true) {
		$is_download = false;
		send_download_headers($this->document_file_type, $this->document_displayed_filename,$is_download ? 'attachment; ' : '', $cache_it ? 'max-age=2592000' : 'max-age=0');
		$this->generateMediumImageIfNeeded();
		$medium_file = $this->fullStoredFileName('/medium');
		header( 'Content-Length: '.filesize($medium_file) );
		header( 'Content-Description: Download Data' );
		readfile($medium_file);
		exit;		
	}
	
	public function outputCustomSizeImageToBrowser($width) {
		$is_download = false;
		send_download_headers($this->document_file_type, $this->document_displayed_filename,$is_download ? 'attachment; ' : '');
		$this->generateMediumImageIfNeeded();
		$medium_file = $this->fullStoredFileName('/medium');
		
		$tempfile = tempnam(Zend_Registry::get('config')->document_path_base.Zend_Registry::get('config')->document_directory.'/'.$this->document_stored_path,'PDFImage');
		$options = array(
				'max_width' => $width,
				'max_height' => 1200,
				'jpeg_quality' => 90
		);
		UploadHandler::create_scaled_image_core($this->document_stored_filename, $options, $this->fullStoredFileName('/medium'), $tempfile);
		header( 'Content-Length: '.filesize($tempfile) );
		header( 'Content-Description: Download Data' );
		readfile($tempfile);
		unlink($tempfile);
		exit;
	}	
	
	public function outputThumbnailImageToBrowser($cache_it=true) {
		$thumbnail_file = $this->fullStoredFileName('/thumbnail');
		$is_download = false;
		send_download_headers($this->document_file_type, $this->document_displayed_filename,$is_download ? 'attachment; ' : '', $cache_it ? 'max-age=2592000' : 'max-age=0');
		if (is_file($thumbnail_file)) {
			header( 'Content-Length: '.filesize($thumbnail_file) );
			header( 'Content-Description: Download Data' );
			readfile($thumbnail_file);
		}
		exit;
	}	
		
	public function outputToBrowser($is_download=true, $cache_it=true) {
		send_download_headers($this->document_file_type, $this->document_displayed_filename,$is_download ? 'attachment; ' : '', $cache_it ? 'max-age=2592000' : 'max-age=0');
		header( 'Content-Length: '.$this->document_filesize );
		header( 'Content-Description: Download Data' );
		readfile($this->fullStoredFileName());
		exit;
	}	
	
}

<?php

require_once(dirname(__FILE__).'/../library/phpmailer/class.phpmailer.php'); 
require_once(dirname(__FILE__).'/../library/phpmailer/class.smtp.php');

class Email {
	var $PHPMailer;
	
	function Email($to,$toname,$from,$fromname,$cc,$bcc,$subject,$message) {
		
		$this->PHPMailer = new PHPMailer();
		
		if ((getenv('OS') == 'Windows_NT') && isset(Zend_Registry::get('config')->phpmailer_host)) { // for testing only
			$this->PHPMailer->isSMTP();                                      // Set mailer to use SMTP
			$this->PHPMailer->Host = Zend_Registry::get('config')->phpmailer_host;  
			$this->PHPMailer->Port = Zend_Registry::get('config')->phpmailer_port;    // TCP port to connect to		
		}
		
		$this->PHPMailer->From = trim($from);
		$this->PHPMailer->FromName = trim(str_replace(",", "", $fromname));
		$this->PHPMailer->AddAddress(trim($to), trim(str_replace(",", "", $toname)));
		if ($cc) $this->PHPMailer->AddCC(trim($cc));
		if ($bcc) $this->PHPMailer->AddBCC(trim($bcc));
		$this->PHPMailer->AddReplyTo(trim($from), trim(str_replace(",", "", $fromname)));
		$this->PHPMailer->WordWrap = 76;
		$this->PHPMailer->Subject = trim($subject);
		$this->PHPMailer->Body = $message;
	}

	function AttachFile($path, $name = '') {
		$this->PHPMailer->addAttachment($path, $name);
	}
	
	function ErrorInfo() {
		return $this->PHPMailer->ErrorInfo;
	}
	
	function Send() {
		return $this->PHPMailer->Send();
	}
}
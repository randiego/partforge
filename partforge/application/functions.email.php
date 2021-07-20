<?php

require_once(dirname(__FILE__).'/../library/phpmailer/class.phpmailer.php');
require_once(dirname(__FILE__).'/../library/phpmailer/class.smtp.php');

class Email {
    var $PHPMailer;

    public function __construct($to, $toname, $from, $fromname, $cc, $bcc, $subject, $message)
    {

        $this->PHPMailer = new PHPMailer();

        if (!empty(Zend_Registry::get('config')->phpmailer_host)) {
            $this->PHPMailer->isSMTP();                                      // Set mailer to use SMTP
            $this->PHPMailer->Host = Zend_Registry::get('config')->phpmailer_host;
            if (!empty(Zend_Registry::get('config')->phpmailer_port)) {
                $this->PHPMailer->Port = Zend_Registry::get('config')->phpmailer_port;    // TCP port to connect to
            }
            if (!empty(Zend_Registry::get('config')->phpmailer_username)) {
                $this->PHPMailer->SMTPAuth = true;
                $this->PHPMailer->Username = Zend_Registry::get('config')->phpmailer_username;
                if (!empty(Zend_Registry::get('config')->phpmailer_password)) {
                    $this->PHPMailer->Password = Zend_Registry::get('config')->phpmailer_password;
                }
                if (!empty(Zend_Registry::get('config')->phpmailer_smtpsecure)) {
                    $this->PHPMailer->SMTPSecure = Zend_Registry::get('config')->phpmailer_smtpsecure;
                }
            }
        }

        $this->PHPMailer->From = trim($from);
        $this->PHPMailer->FromName = trim(str_replace(",", "", $fromname));
        $this->PHPMailer->AddAddress(trim($to), trim(str_replace(",", "", $toname)));
        if ($cc) {
            $this->PHPMailer->AddCC(trim($cc));
        }
        if ($bcc) {
            $this->PHPMailer->AddBCC(trim($bcc));
        }
        $this->PHPMailer->AddReplyTo(trim($from), trim(str_replace(",", "", $fromname)));
        $this->PHPMailer->WordWrap = 76;
        $this->PHPMailer->Subject = trim($subject);
        $this->PHPMailer->Body = $message;
    }

    public function setContentType($content_type)
    {
        $this->PHPMailer->ContentType = $content_type;
    }

    public function AttachFile($path, $name = '')
    {
        $this->PHPMailer->addAttachment($path, $name);
    }

    public function ErrorInfo()
    {
        return $this->PHPMailer->ErrorInfo;
    }

    public function Send()
    {
        return $this->PHPMailer->Send();
    }
}

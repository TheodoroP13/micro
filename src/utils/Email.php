<?php

namespace Pgf\Utils;

use \PHPMailer\PHPMailer\PHPMailer;
use \PHPMailer\PHPMailer\SMTP;
use \PHPMailer\PHPMailer\Exception;

class Email{
	private $debug = false;
	private $server = [];
	private $error;

	public function __construct($server = null){
		if(empty($server)){
			if(\PGF::getConfig()->smtp && !empty(\PGF::getConfig()->smtp['hostname'])){
				$this->server = \PGF::getConfig()->smtp;
			}else{
				$this->error = "Não foi possível localizar a configuração do servidor SMTP...";
				
				if($this->debug == true){
					echo $this->error;
				}

				return false;
			}
		}else{
			$this->server = $server;
		}
	}

	public static function send(array $sendTo, $subject, array $content, array $parseString = [], array $attachs = [], array $smtpOptions = [], $debug = false) : bool{
		$email = new Email;

		if(!empty($parseString)){
			foreach($parseString as $key => $value){
				$stringReplace = '{$' . $key . '$}';
				$content['html'] = str_replace($stringReplace, $value, $content['html']);
				$content['alt'] = str_replace($stringReplace, $value, $content['alt']);
				$subject = str_replace($stringReplace, $value, $subject);
			}
		}

		$mail = new PHPMailer(true);

		try {
			if($debug){
				$mail->SMTPDebug = SMTP::DEBUG_SERVER;
			}
			$mail->isSMTP();              
			$mail->Host = $email->server['hostname'];
			$mail->SMTPAuth = true;                   
			$mail->Username = $email->server['user'];
			$mail->Password = $email->server['password'];
			// $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;  
			$mail->SMTPSecure = "tls";  
			$mail->Port = (int) $email->server['port'];

			if(!empty($smtpOptions)){
				$mail->SMTPOptions = $smtpOptions;
			}

			if($email->server['hostname'] == "smtp.mailtrap.io"){
				$mail->setFrom("teste@gmail.com", $email->server['name']); // De
			}else{
				$mail->setFrom($email->server['user'], $email->server['name']); // De
			}			

			$mail->addAddress($sendTo["email"], $sendTo["name"]); // Para
			$mail->CharSet = 'UTF-8';
		    $mail->isHTML(true);
		    $mail->Subject = $subject;
		    $mail->Body = $content['html'];
		    $mail->AltBody = !empty($content['alt']) ? $content['alt'] : "O seu provedor de e-mail não aceitou o e-mail enviado por nosso sistema, por favor, entre em contato com nossa equipe.";

		    if(!empty($attachs) && is_array($attachs)){
		    	foreach($attachs as $file){
		    		if(!is_array($file)){
		    			$mail->addAttachment($file);
		    		}else{
		    			$mail->addStringAttachment(file_get_contents($file['file']), $file['name'] . (isset($file['extension']) ? ('.' . $file['extension']) : ''));
		    		}
		    	}
		    }

		    if($mail->send()){
		    	return true;
		    }else{
		    	return false;
		    }
		}catch(phpmailerException $e){
			// self::$error = $e->errorMessage();
			if($debug == true){
				echo $e->errorMessage();
			}
			return false;
		}catch(Exception $e){
			// self::$error = $mail->ErrorInfo;
			if($debug == true){
				echo $mail->ErrorInfo;
			}
			return false;
		}
	}

	public function getError(){
		return $this->error;
	}
}
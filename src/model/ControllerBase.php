<?php

namespace Prospera\Model;

use \Prospera\Utils\JWT;
use \Prospera\Database\Connect;

class ControllerBase{
	public $method;
	public $data;
	public $token;

	public function __construct(){
		$this->method = filter_input(\INPUT_SERVER, 'REQUEST_METHOD', \FILTER_SANITIZE_SPECIAL_CHARS);

		$content = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : (isset($_SERVER['HTTP_CONTENT_TYPE']) ? $_SERVER['HTTP_CONTENT_TYPE'] : null);

		if (!empty($content)) {
		    if ($content === "application/json") {
		        $requestData = json_decode(file_get_contents('php://input'), true);
		        $this->data = array_merge($this->data ?? [], $requestData ?? []);
		    } else {
		        $this->data = array_merge($this->data ?? [], $_POST ?? []);
		    }
		} else {
		    $requestData = json_decode(file_get_contents('php://input'), true);
		    $this->data = array_merge($this->data ?? [], $requestData ?? []);
		}

		$this->data = array_merge($this->data ?? [], $_GET);
		unset($this->data['_url']);

		$headers = apache_request_headers();
		if(isset($headers) && isset($headers['Authorization']) && !empty($headers['Authorization'])){
			$this->token = str_replace("Bearer ", "", $headers['Authorization']);
		}else{
			return false;
		}
	}

	public function isGet(){
		if(strtoupper($method) === "GET"){
			return true;
		}
		return false;
	}

	public function isPost(){
		if(strtoupper($method) == "POST"){
			return true;
		}
		return false;
	}

	public function isPut(){
		if(strtoupper($method) == "PUT"){
			return true;
		}
		return false;
	}

	public function isDelete(){
		if(strtoupper($method) == "DELETE"){
			return true;
		}
		return false;
	}

	public function initTransaction($database = 'default'){
		Connect::initTransaction($database);
	}

	public function rollBackTransaction($database = 'default'){
		Connect::rollBackTransaction($database);
	}

	public function commitTransaction($database = 'default'){
		Connect::commitTransaction($database);
	}
}
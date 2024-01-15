<?php

require_once "../vendor/autoload.php";

// use \Pgf\Api\Router;
// use \Pgf\Http\{Http, StatusCode};

PSF::init(['config' => 'config.php']);

// var_dump(PSF::getConfig());

use \Prospera\Api\{Router};
use \Prospera\Helper\{CheckFields, Format, IP, Text, TimeZone, UUID, Valid};
// use \Prospera\Http\
use \Prospera\Api\{Model};


class User extends \Prospera\Model\Model{
	use \Prospera\Model\ModelTrait;
	
	private static $logged = null;

	public function onConstruct(){
		$this->tableName = 'user';
	}
}

class Usuario extends \Prospera\Model\Model{
	use \Prospera\Model\ModelTrait;

	public function onConstruct(){
		$this->database = 'exported';
		$this->tableName = 'MGN_USUARIO';
	}
}

// var_dump(User::find()->one());
// // var_dump(Usuario::find()->getRowQuery());
// var_dump(Usuario::find()->fields([
// 	Usuario::class . '.CODIGO',
// ])
// // ->innerJoin()
// ->all());


class Solicitacao extends \Prospera\Model\Model{
	use \Prospera\Model\ModelTrait;

	public function onConstruct(){
		$this->database = 'exported';
		$this->tableName = 'astec_solicitacao';
	}
}

// $createSolicitation = (new Solicitacao)->assign([
// 	'prioridade'	=> 1,
// 	'status'		=> 1,
// 	'id_cliente'	=> 1
// ]);

// $createSolicitation->create();

// var_dump($createSolicitation);

$findSolicitation = Solicitacao::find()->andWhere([Solicitacao::class . '.id' => 3])->one();
// var_dump($findSolicitation);

if($findSolicitation){
	$findSolicitation->delete();
}
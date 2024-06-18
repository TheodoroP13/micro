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


// class User extends \Prospera\Model\Model{
// 	use \Prospera\Model\ModelTrait;
	
// 	private static $logged = null;

// 	public function onConstruct(){
// 		$this->tableName = 'user';
// 	}
// }

// class Usuario extends \Prospera\Model\Model{
// 	use \Prospera\Model\ModelTrait;

// 	public function onConstruct(){
// 		$this->database = 'exported';
// 		$this->tableName = 'MGN_USUARIO';
// 	}
// }

// var_dump(User::find()->one());
// // var_dump(Usuario::find()->getRowQuery());
// var_dump(Usuario::find()->fields([
// 	Usuario::class . '.CODIGO',
// ])
// // ->innerJoin()
// ->all());


// class Solicitacao extends \Prospera\Model\Model{
// 	use \Prospera\Model\ModelTrait;

// 	public function onConstruct(){
// 		$this->database = 'exported';
// 		$this->tableName = 'astec_solicitacao';
// 	}
// }

// $createSolicitation = (new Solicitacao)->assign([
// 	'prioridade'	=> 1,
// 	'status'		=> 1,
// 	'id_cliente'	=> 1
// ]);

// $createSolicitation->create();

// var_dump($createSolicitation);

// $findSolicitation = Solicitacao::find()->andWhere([Solicitacao::class . '.id' => 3])->one();
// var_dump($findSolicitation);

// if($findSolicitation){
	// $findSolicitation->delete();
// }

use \Psf\Enumerators\{DBDriver};

#[Table('ponto_extra_registro'), Database('exported')]
class PontoExtra extends \Psf\Model\Model{
	use \Psf\Model\ModelTrait;

	#[Column('id'), Type('int'), PrimaryKey]
	public $id;

	#[Column('status'), Type('int'), Standard(DBDriver::MySQL)]
	public $status;

	#[Column('id_colaborador'), Type('int')]
	public $idColaborador;
	
	#[Column('incluido'), Type('datetime'), ColumnCreatedDate]
	public $incluido;

	#[Column('alterado'), Type('datetime'), ColumnUpdatedDate]
	public $alterado;

	#[Column('deletado'), Type('datetime'), ColumnDeletedDate]
	public $deletado;

	#[Column('data_inicio'), Standard('Now()'), Type('datetime')]
	public $dataInicio;

	#[Column('data_fim'), Type('datetime')]
	public $dataFim;

	#[Column('id_dispositivo'), Type('int')]
	public $idDispositivo;

	#[Column('id_departamento'), Type('int')]
	public $idDepartamento;

	public function onConstruct(){
		
	}
}

// $pontoExtra = new PontoExtra;
// // var_dump($pontoExtra);

// $teste = (new PontoExtra)->assign([
// 	// 'dataInicio'	=> 'teste',
// 	// 'status'		=> 1,
// 	'idDispositivo' => 1,
// 	'idColaborador'	=> 5,
// ]);

// var_dump($teste);
// var_dump($teste->create());

// var_dump(PontoExtra::find()->all());

$findPonto = PontoExtra::find()
->andWhere([PontoExtra::class . '.id', 'NOT IN', [9, 14, 13]])
->all();

// // $findPonto->idDepartamento = 5;
// // $findPonto->save();

var_dump($findPonto);

// $findPonto->delete();

// var_dump($findPonto);
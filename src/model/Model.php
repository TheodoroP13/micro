<?php

namespace Prospera\Model;

use \Prospera\Database\{Connect, Create, Delete, Update};
use \Prospera\Model\ModelQuery;
use \Prospera\Helper\UUID;
use \Prospera\Http\Http;

use \Prospera\Enumerators\{DBDriver};

class Model{
	public $tableName;
	public $database = 'default';

	public function __construct(){
		if(method_exists($this, 'onConstruct') && is_callable([$this, 'onConstruct'])){
			call_user_func([$this, 'onConstruct']);
		}

		if(empty($this->tableName)){
			unset($this->database);
			unset($this->cache);
			unset($this->tableName);
		}else{
			$listColuns = $this->getColunsForTable();

			foreach($listColuns as $colun){
				$this->{$colun->Field} = null;
			}
		}

		if(property_exists($this, 'saveCache') && $this->saveCache === TRUE){
			$this->{'cache'} = [];
		}
	}

	public function getPrimarysKeys(){
		return array_values(array_map(function($item){
			return $item->Field;
		}, array_filter($this->getColunsForTable(), function($item){
			return $item->Key === 'PRI';
		})));
	}

	public function getPrimarysQuery(){
		$configDb 	= \PSF::getConfig()->db[$this->database];
		$driver 	= !empty($configDb['driver']) ? $configDb['driver'] : DBDriver::MySQL;

		$primarys = $this->getPrimarysKeys();

       	if(count($primarys) == 1){
       		return $driver === DBDriver::MySQL ? ("`".$this->table."`.`".$primarys[0]."` = ".$this->{$primarys[0]}) : ($driver === DBDriver::SQLServer ? ($primarys[0]." = ".$this->{$primarys[0]}) : []);
       	}else if(count($primarys) > 1){
       		$string = "";
       		$count = 0;

       		foreach($primarys as $item){
       			if($count > 0){
       				$string .= " AND ";
       			}
       			if($driver === DBDriver::MySQL){
       				$string .= " `" . $this->table ."`.`". $item . "` = " . $this->{$item};
       			}else if($driver === DBDriver::SQLServer){
       				$string .= " " . $item . " = " . $this->{$item};
       			}
       			$count++;
       		}

       		return $string;
       	}else{
       		throw new \Exception("DB Error - Primary Key Not Found");
       	}
	}

	public function getColunsForTable(){
		return Connect::getColunsForTable($this->tableName, $this->database);
	}

	public function create(){
		$configDb 	= \PSF::getConfig()->db;
		$driver 	= !empty($configDb[$this->database]['driver']) ? $configDb[$this->database]['driver'] : DBDriver::MySQL;
		
		if(property_exists($this, "incluido")){
			$this->incluido = date("Y-m-d H:i:s");
		}
		if(property_exists($this, "hash") && empty($this->hash)){
			$this->hash = UUID::generate(4);
		}
		if(property_exists($this, "status") && empty($this->status)){
			$this->status = 1;
		}

		$fields = [];
		$columns = array_map(function($item){
			return $item->Field;
		}, $this->getColunsForTable());

		foreach($columns as $item){
			if(property_exists($this, $item)){
				$fields[$item] = $this->{$item};
			}
		}

		if($driver == DBDriver::SQLServer){
			unset($fields[$this->getIdentityColumn()]);
		}
		
		$Create = Create::exe($this->tableName, $fields, $this->database);
		if($Create->getResult() !== FALSE){
			$this->id = $Create->getResult();

			if(property_exists($this, 'saveCache') && $this->saveCache && !isset($this->cache) || empty($this->cache)){
				$this->cache = clone $this;
			}

			return TRUE;
		}else{
			return FALSE;
		}
	}

	public function save(){
		$configDb 	= \PSF::getConfig()->db;
		$driver 	= !empty($configDb[$this->database]['driver']) ? $configDb[$this->database]['driver'] : DBDriver::MySQL;

		if(property_exists($this, "alterado")){
			if(property_exists($this, "deletado") && empty($this->deletado)){
				$this->alterado = date("Y-m-d H:i:s");
			}else if(!property_exists($this, "deletado")){
				$this->alterado = date("Y-m-d H:i:s");
			}
		}

		$fields = [];
		$columns = array_map(function($item){
			return $item->Field;
		}, $this->getColunsForTable());

		foreach($columns as $item){
			$fields[$item] = $this->{$item};
		}

		if($driver == DBDriver::SQLServer){
			unset($fields[$this->getIdentityColumn()]);
		}

		$Update = Update::exe($this->tableName, $fields, "WHERE " . $this->getPrimarysQuery(), null, $this->database);

		return $Update->getResult();
	}

	public function delete(){
		$softDelete = false;

		if(property_exists($this, "deletado")){
			$this->deletado = date("Y-m-d H:i:s");
			$softDelete = true;
		}else{
			if(property_exists($this, "status")){
				$this->status = -1;
				$softDelete = true;
			}
		}

		if($softDelete){
			$this->save();
		}else{
			Delete::exe($this->tableName, "WHERE " . $this->getPrimarysQuery(), null, $this->database);
		}
	}

	public function assign(object|array $values, bool $force = false){
		foreach($values as $key => $value){
			if($force){
				$this->$key = $value;
			}else{
				if(property_exists($this, $key)){
					$this->$key = $value;
				}
			}
		}
		return $this;
	}

	public function toArray() : array {
		$arrReturn = [];
		foreach($this->getColunsForTable() as $item){	
			if(property_exists($this, $item)){
				$arrReturn[$item] = $this->$item;
			}
		}		
		return $arrReturn;
	}

	public function getTableName(){
		return $this->tableName;
	}

	public function getIdentityColumn(){
		$configDb 	= \PSF::getConfig()->db;
		$driver 	= !empty($configDb[$this->database]['driver']) ? $configDb[$this->database]['driver'] : DBDriver::MySQL;

		$db = Connect::getConnection($this->database);

		if($driver == DBDriver::SQLServer){
			$query = "SELECT COLUMN_NAME
			FROM INFORMATION_SCHEMA.COLUMNS
			WHERE TABLE_NAME = '" . $this->tableName . "' AND COLUMNPROPERTY(OBJECT_ID(TABLE_SCHEMA + '.' + TABLE_NAME), COLUMN_NAME, 'IsIdentity') = 1";
		}

		if(isset($query)){
			$statement = $db->prepare($query);

			try{    
	            $statement->execute();
	            $coluns = $statement->fetchAll(\PDO::FETCH_ASSOC);

	            if($coluns){
	            	return $coluns[0]['COLUMN_NAME'];
	            }

	            return $coluns;
	        }catch (\PDOException $e){
	            explodeException($e); 
	            return FALSE;
	        }
		}

		return FALSE;
	}
}
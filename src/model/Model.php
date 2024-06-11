<?php

namespace Prospera\Model;

use \Prospera\Database\{Connect, Create, Delete, Update};
use \Prospera\Model\ModelQuery;
use \Prospera\Helper\UUID;
use \Prospera\Http\Http;

use \Prospera\Enumerators\{DBDriver};

class Model{
	public $table;
	public $tableName;
	public $database = 'default';

	public function __construct(){
		if(method_exists($this, 'onConstruct') && is_callable([$this, 'onConstruct'])){
			call_user_func([$this, 'onConstruct']);
		}

		if(!isset($this->table) && !empty($this->tableName)){
			$this->table = $this->tableName;
			unset($this->tableName);
		}

		if(empty($this->table)){
			unset($this->database);
			unset($this->cache);
			unset($this->table);
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
		return Connect::getColunsForTable($this->table, $this->database);
	}

	public function create(){
		$configDb 	= \PSF::getConfig()->db[$this->database];
		$driver 	= !empty($configDb['driver']) ? $configDb['driver'] : DBDriver::MySQL;
		
		if(isset($this->configDb['fields']['incluido']) && !empty(isset($this->configDb['fields']['incluido']))){
			if(property_exists($this, $this->configDb['fields']['incluido'])){
				$this->{$this->configDb['fields']['incluido']} = date("Y-m-d H:i:s");
			}
		}else{
			if(property_exists($this, "incluido")){
				$this->incluido = date("Y-m-d H:i:s");
			}
		}

		if(isset($this->configDb['fields']['hash']) && !empty(isset($this->configDb['fields']['hash']))){
			if(property_exists($this, $this->configDb['fields']['hash'])){
				$this->{$this->configDb['fields']['hash']} = $this->hash = UUID::generate(4);
			}
		}else{
			if(property_exists($this, "hash") && empty($this->hash)){
				$this->hash = UUID::generate(4);
			}
		}

		if(isset($this->configDb['fields']['status']) && !empty(isset($this->configDb['fields']['status']))){
			if(property_exists($this, $this->configDb['fields']['status'])){
				$this->{$this->configDb['fields']['status']} = 1;
			}
		}else{
			if(property_exists($this, "status") && empty($this->status)){
				$this->status = 1;
			}
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
		
		$Create = Create::exe($this->table, $fields, $this->database);
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
		$configDb 	= \PSF::getConfig()->db[$this->database];
		$driver 	= !empty($configDb['driver']) ? $configDb['driver'] : DBDriver::MySQL;

		$propertyChange = isset($this->configDb['fields']['alterado']) && !empty(isset($this->configDb['fields']['alterado'])) ? $this->configDb['fields']['alterado'] : (property_exists($this, 'alterado') ? 'alterado' : NULL);

		if(!empty($propertyChange)){
			$propertyDeleted = isset($this->configDb['fields']['deletado']) && !empty(isset($this->configDb['fields']['deletado'])) ? $this->configDb['fields']['deletado'] : (property_exists($this, 'deletado') ? 'deletado' : NULL);

			if(!empty($propertyDeleted) && empty($this->{$propertyDeleted})){
				$this->{$propertyChange} = date('Y-m-d H:i:s');
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

		$Update = Update::exe($this->table, $fields, "WHERE " . $this->getPrimarysQuery(), null, $this->database);

		return $Update->getResult();
	}

	public function delete(){
		$configDb 	= \PSF::getConfig()->db[$this->database];
		$softDelete = false;

		if(isset($this->configDb['fields']['deletado']) && !empty(isset($this->configDb['fields']['deletado']))){
			if(property_exists($this, $this->configDb['fields']['deletado'])){
				$this->{$this->configDb['fields']['deletado']} = date("Y-m-d H:i:s");
				$softDelete = true;
			}
		}else{
			if(property_exists($this, "deletado")){
				$this->deletado = date("Y-m-d H:i:s");
				$softDelete = true;
			}
		}

		if(!$softDelete){
			if(isset($this->configDb['fields']['status']) && !empty(isset($this->configDb['fields']['status']))){
				if(property_exists($this, $this->configDb['fields']['status'])){
					$this->{$this->configDb['fields']['status']} = date("Y-m-d H:i:s");
					$softDelete = true;
				}
			}else{
				if(property_exists($this, "status")){
					$this->status = date("Y-m-d H:i:s");
					$softDelete = true;
				}
			}
		}

		if($softDelete){
			$this->save();
		}else{
			Delete::exe($this->table, "WHERE " . $this->getPrimarysQuery(), null, $this->database);
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
		foreach($this->getColunsForTable() as $item){
			if(property_exists($this, $item->Field)){
				$fieldName = $item->Field;
				$arrReturn[$item->Field] = $this->$fieldName;
			}
		}		
		return $arrReturn ?? [];
	}

	public function getTableName(){
		return $this->table;
	}

	public function getIdentityColumn(){
		$configDb 	= \PSF::getConfig()->db;
		$driver 	= !empty($configDb[$this->database]['driver']) ? $configDb[$this->database]['driver'] : DBDriver::MySQL;

		$db = Connect::getConnection($this->database);

		if($driver == DBDriver::SQLServer){
			$query = "SELECT COLUMN_NAME
			FROM INFORMATION_SCHEMA.COLUMNS
			WHERE TABLE_NAME = '" . $this->table . "' AND COLUMNPROPERTY(OBJECT_ID(TABLE_SCHEMA + '.' + TABLE_NAME), COLUMN_NAME, 'IsIdentity') = 1";
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
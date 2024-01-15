<?php

namespace Prospera\Model;

use \Prospera\Database\{Connect, Create, Delete, Update};
use \Prospera\Model\ModelQuery;
use \Prospera\Helpers\UUID;
use \Prospera\Http\Http;

use \Prospera\Enumerators\{DBDriver};

class Model{
	public $tableName;
	public $database = 'default';
	public $cache;

	public function __construct(){
		if(is_callable([$this, 'onConstruct'])){
			call_user_func([$this, 'onConstruct']);
		}
		if(!empty($this->tableName)){
			$listColuns = $this->getColunsForTable();
			foreach($listColuns as $colun){
				$this->{$colun} = null;
			}
		}
	}

	public function getPrimarysKeys(){
		// var_dump($this->database);
		$configDb 	= \PSF::getConfig()->db;
		$driver 	= !empty($configDb[$this->database]['driver']) ? $configDb[$this->database]['driver'] : DBDriver::MySQL;

		$db = Connect::getConnection($this->database);

		if($driver == DBDriver::MySQL){
			$query = "SHOW KEYS FROM " . ModelQuery::getHandleTableName($this->database, $this->tableName) . " WHERE Key_name = 'PRIMARY'";
		}

		if($driver == DBDriver::SQLServer){
			$query = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE OBJECTPROPERTY(OBJECT_ID(CONSTRAINT_SCHEMA + '.' + CONSTRAINT_NAME), 'IsPrimaryKey') = 1 AND TABLE_NAME = '" . $this->tableName . "'";
		}

		// var_dump($query);

		if(isset($query)){
			$statement = $db->prepare($query);

			try{    
	            $statement->execute();
	            $coluns = $statement->fetchAll(\PDO::FETCH_ASSOC);

	            // var_dump($coluns);

	            return $coluns;
	        }catch (\PDOException $e){
	            explodeException($e); 
	            return false;
	        }
		}

		return FALSE;
	}

	public function getPrimarysQuery(){
		$configDb 	= \PSF::getConfig()->db;
		$driver 	= !empty($configDb[$this->database]['driver']) ? $configDb[$this->database]['driver'] : DBDriver::MySQL;

		$primarys = $this->getPrimarysKeys();

		if($driver == DBDriver::MySQL){
	       	if(count($primarys) == 1){
	       		return "`".$primarys[0]['Table']."`.`".$primarys[0]['Column_name']."` = ".$this->{$primarys[0]['Column_name']};
	       	}else if(count($primarys) > 1){
	       		$string = "";
	       		$count = 0;

	       		foreach($primarys as $item){
	       			if($count > 0){
	       				$string .= " AND ";
	       			}
	       			$string .= " `" . $item['Table'] ."`.`". $item['Column_name'] . "` = " . $this->{$item['Column_name']};
	       			$count++;
	       		}

	       		return $string;
	       	}else{
	       		throw new \Exception("MySql Error - Primary Key Not Found");
	       	}
	    }

	    if($driver == DBDriver::SQLServer){
	    	if(count($primarys) == 1){
	       		return $primarys[0]['COLUMN_NAME']." = ".$this->{$primarys[0]['COLUMN_NAME']};
	       	}else if(count($primarys) > 1){
	       		$string = "";
	       		$count = 0;

	       		foreach($primarys as $item){
	       			if($count > 0){
	       				$string .= " AND ";
	       			}
	       			$string .= " " . $item['COLUMN_NAME'] . " = " . $this->{$item['COLUMN_NAME']};
	       			$count++;
	       		}

	       		return $string;
	       	}else{
	       		throw new \Exception("SQLServer Error - Primary Key Not Found");
	       	}
	    }
	}

	public function getColunsForTable(){
		return Connect::getColunsForTable($this->tableName, $this->database);
	}

	public function create(){
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
		foreach($this->getColunsForTable() as $item){
			if(property_exists($this, $item) && !empty($this->$item)){
				$fields[$item] = $this->{$item};
			}
		}
		
		$Create = Create::exe($this->tableName, $fields, $this->database);
		if($Create->getResult() !== FALSE){
			$this->id = $Create->getResult();

			if(!isset($this->cache) || empty($this->cache)){
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

		if(empty($this->cache)){
			$trace = debug_backtrace();

			Http::response('You can only use the "save" method with existing records, for new records use the "create" method', [
	            'class' 	=> $this::class,
	            'callIn'	=> $trace[0]['file'] . ' on line ' . $trace[0]['line'],
	        ], 500);
		}
			
		$fieldsChanged = [];
		$lastData = [];
		$newData = [];

		if(property_exists($this, "alterado")){
			if(property_exists($this, "deletado") && empty($this->deletado)){
				$this->updated = date("Y-m-d H:i:s");
			}else if(!property_exists($this, "deletado")){
				$this->updated = date("Y-m-d H:i:s");
			}
		}

		$fields = [];
		foreach($this->getColunsForTable() as $item){
			$fields[$item] = $this->{$item};
			if(!empty($this->cache)){
				if($this->{$item} != $this->cache->{$item}){
					$lastData[$item] = $this->cache->{$item};
					$newData[$item] = $this->{$item};
					$fieldsChanged[] = $item;
				}
			}else{
				$newData[$item] = $this->{$item};
				$fieldsChanged[] = $item;
			}
		}

		if($driver == DBDriver::SQLServer){
			unset($fields[$this->getIdentityColumn()]);
		}

		$Update = Update::exe($this->tableName, $fields, "WHERE " . $this->getPrimarysQuery(), null, $this->database);

		if($Update->getResult() == true){
			Connect::getConnection($this->database);

			$auditTables = \PSF::getConfig()->pgf['audittables'] ?? false;
			if($auditTables && is_callable([$auditTables[0], $auditTables[1]])){
				$primarys = $this->getPrimarysKeys();

				call_user_func_array([$auditTables[0], $auditTables[1]], [$last, $new, $primarys]);
			}	
		}

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
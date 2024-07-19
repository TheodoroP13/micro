<?php

namespace Psf\Model;

use \Psf\Database\{Connect, Create, Delete, Update};
use \Psf\Model\ModelQuery;
use \Psf\Helper\UUID;
use \Psf\Http\Http;

use \Psf\Enumerators\{DBDriver};

class Model{
	public function __construct(){
		if(method_exists($this, 'onConstruct') && is_callable([$this, 'onConstruct'])){
			call_user_func([$this, 'onConstruct']);
		}
	}

	public function getPrimarysKeys(){
		return array_values(array_map(function($item){
			return $item->Field;
		}, array_filter($this->getColunsForTable(), function($item){
			return $item->Key === 'PRI';
		})));
	}

	public function getPrimarysQuery(bool $query = false){
		$configDb 	= \PSF::getConfig()->db[Model::getDatabase($this)];
		$driver 	= !empty($configDb['driver']) ? $configDb['driver'] : DBDriver::MySQL;
		$primarys = [];

		$refClass = new \ReflectionClass($this::class);
		foreach($refClass->getProperties() as $property){
			$attributes = $property->getAttributes();

			$primarysKey = array_values(array_filter($attributes, function($attr) use ($property){
				return $attr->getName() === 'PrimaryKey';
			}));

			if(!empty($primarysKey)){
				foreach($primarysKey as $column){
					$primarys[] = $property->getName();
				}
				break;
			}
		}

		if(empty($primarys)){
			throw new \Exception("DB Error - Primary Key Not Found");
		}

		if($query){
	       	if(count($primarys) == 1){
	       		return $driver === DBDriver::MySQL ? ("`". Model::getTable($this) ."`.`".$primarys[0]."` = ".$this->{$primarys[0]}) : ($driver === DBDriver::SQLServer ? ($primarys[0]." = ".$this->{$primarys[0]}) : []);
	       	}else if(count($primarys) > 1){
	       		$string = "";
	       		$count = 0;

	       		foreach($primarys as $item){
	       			if($count > 0){
	       				$string .= " AND ";
	       			}
	       			if($driver === DBDriver::MySQL){
	       				$string .= " `" . Model::getTable($this) ."`.`". $item . "` = " . $this->{$item};
	       			}else if($driver === DBDriver::SQLServer){
	       				$string .= " " . $item . " = " . $this->{$item};
	       			}
	       			$count++;
	       		}

	       		return $string;
	       	}
	    }

	    return $primarys;
	}

	public function getColunsForTable(){
		return Connect::getColunsForTable(Model::getTable($this), Model::getDatabase($this));
	}

	public static function serializeFields($object) : array{
		$refClass = new \ReflectionClass($object::class);
		foreach($refClass->getProperties() as $property){
			$attributes = $property->getAttributes();

			$column = array_values(array_filter($attributes, function($attr) use ($property){
				return $attr->getName() === 'Column';
			}));

			if(!empty($column)){
				$column = $column[0]->getArguments()[0];

				$standardValue = array_values(array_filter($attributes, function($attr) use ($property){
					return $attr->getName() === 'Standard' && !empty($attr->getArguments()[0]);
				}));

				if(!empty($standardValue) && empty($object->{$property->getName()})){
					if(property_exists($standardValue[0]->getArguments()[0], 'value')){
						$object->{$property->getName()} = $standardValue[0]->getArguments()[0]->value;
					}else{
						if(strtoupper($standardValue[0]->getArguments()[0]) === 'NOW()'){
							$object->{$property->getName()} = date('Y-m-d H:i:s');
						}else{
							$object->{$property->getName()} = $standardValue[0]->getArguments()[0];
						}
					}

					$fields[$column] = $object->{$property->getName()};
				}

				$columnCreatedDate = array_values(array_filter($attributes, function($attr) use ($property){
					return $attr->getName() === 'ColumnCreatedDate';
				}));

				if(!empty($columnCreatedDate) && empty($object->{$property->getName()})){
					$object->{$property->getName()} = date('Y-m-d H:i:s');
					$fields[$column] = date('Y-m-d H:i:s');
				}

				$columnUpdatedDate = array_values(array_filter($attributes, function($attr) use ($property){
					return $attr->getName() === 'ColumnUpdatedDate';
				}));

				if(!empty($columnUpdatedDate) && empty($object->{$property->getName()})){
					$object->{$property->getName()} = date('Y-m-d H:i:s');
					$fields[$column] = date('Y-m-d H:i:s');
				}

				$required = array_values(array_filter($attributes, function($attr) use ($property){
					return $attr->getName() === 'Nullable';
				}));

				if(!empty($required)){
					$required = $required[0]->getArguments()[0];
				}

				if($required === FALSE && empty($object->{$property->getName()})){
					return throw new \Exception("O campo '" . $property->getName() . "' não pode ser nulo");
				}else if(!empty($required)){
					$fields[$column] = $object->{$property->getName()};
				}

				if(!empty($object->{$property->getName()}) && empty($fields[$column])){
					$fields[$column] = $object->{$property->getName()};
				}
			}
		}

		return $fields ?? [];
	}

	public function create(){
		$fields = Model::serializeFields($this);

		$Create = Create::exe(
			table: Model::getTable($this), 
			data: $fields, 
			database: Model::getDatabase($this)
		);

		if(!empty($Create)){
			if(property_exists($this::class, 'id')){
				$this->id = $Create->getResult();
			}

			return TRUE;
		}

		return FALSE;
	}

	public function save(){
		$primarysKey = $this->getPrimarysQuery();
		$fields = Model::serializeFields($this);

		$fieldsExclude = array_filter(array_keys($fields), function($item) use ($primarysKey){
			return in_array($item, $primarysKey);
		});

		if(!empty($fieldsExclude)){
			foreach($fieldsExclude as $field){
				unset($fields[$field]);
			}
		}

		$Update = Update::exe(
			table: Model::getTable($this::class), 
			data: $fields, 
			terms: 'WHERE ' . $this->getPrimarysQuery(true), 
			database: Model::getDatabase($this::class)
		);

		return $Update->getResult();
	}

	public function delete(){
		$softDelete = FALSE;

		$refClass = new \ReflectionClass($this::class);
		foreach($refClass->getProperties() as $property){
			$attributes = $property->getAttributes();

			$column = array_values(array_filter($attributes, function($attr) use ($property){
				return $attr->getName() === 'Column';
			}));

			if(!empty($column)){
				$column = $column[0]->getArguments()[0];

				$columnDeleted = array_values(array_filter($attributes, function($attr) use ($property){
					return $attr->getName() === 'ColumnDeletedDate';
				}));

				if(!empty($columnDeleted)){
					$softDelete = $column;
					$this->{$property->getName()} = date('Y-m-d H:i:s');
					break;
				}
			}
		}

		if(!$softDelete){
			Delete::exe(
				table: Model::getTable($this::class),
				terms: 'WHERE ' . $this->getPrimarysQuery(true),
				database: Model::getDatabase($this::class)
			);

			return TRUE;
		}

		return $this->save();
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
		return Model::getTable($this::class);
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

	public static function getTable($class){
		$table = array_values(array_filter((new \ReflectionClass($class))->getAttributes(), function($attr){
			return $attr->getName() === 'Table';
		}));

		return !empty($table) ? $table[0]->getArguments()[0] : FALSE; 
	}

	public static function getDatabase($class){
		$database = array_values(array_filter((new \ReflectionClass($class))->getAttributes(), function($attr){
			return $attr->getName() === 'Database';
		}));

		return !empty($database) ? $database[0]->getArguments()[0] : 'default'; 
	}

	public static function serializeData($class, array $data, bool $asArray = FALSE) : object|array|null{
		$response = new $class;
		$refClass = new \ReflectionClass($class);

		if(!$asArray){
			foreach($refClass->getProperties() as $property){
				$attributes = $property->getAttributes();

				$column = array_values(array_filter($attributes, function($attr) use ($property){
					return $attr->getName() === 'Column';
				}));

				if(!empty($column)){
					$response->{$property->getName()} = !empty($data[$column[0]->getArguments()[0]]) ? $data[$column[0]->getArguments()[0]] : NULL;
				}
			}
		}else{
			$response = [];
			
			foreach(array_keys($data) as $key){
				$findColumnExist = array_values(array_filter($refClass->getProperties(), function($prop) use ($key){
					$attributes = $prop->getAttributes();

					$column = array_values(array_filter($attributes, function($attr) use ($prop, $key){
						return $attr->getName() === 'Column' && $attr->getArguments()[0] == $key;
					}));

					if(!empty($column)){
						$column = $column[0]->getArguments()[0];

						return $column;
					}
				}));

				if(!empty($findColumnExist)){
					$response[$findColumnExist[0]->getName()] = $data[$key];
				}else{
					$response[$key] = $data[$key];
				}
			}
		}

		return $response;
	}

	public static function getPropByColumn($class, $column){
		$refClass = new \ReflectionClass($class);
        $findPropertie = array_values(array_filter($refClass->getProperties(), function($item) use ($column){
            return $column === $item->getName();
        }));

        if(!empty($findPropertie)){
            $attributes = $findPropertie[0]->getAttributes();

            $column = array_values(array_filter($attributes, function($attr){
                return $attr->getName() === 'Column';
            }));

            if(!empty($column)){
                return $column[0]->getArguments()[0];
            }
        }

		return FALSE;
	}

	public static function getPrimaryKey($class, $type = 'column'){
		$refClass = new \ReflectionClass($class);
		foreach($refClass->getProperties() as $property){
			$attributes = $property->getAttributes();

			$primarysKey = array_values(array_filter($attributes, function($attr) use ($property){
				return $attr->getName() === 'PrimaryKey';
			}));

			if(!empty($primarysKey)){
				foreach($primarysKey as $column){
					$primarys[] = $type == 'column' ? Model::getPropByColumn($class, $property->getName()) : $property->getName();
				}
				break;
			}
		}

		return !empty($primarys) ? $primarys : NULL; 
	}

	public static function getColumnByProp($class, $prop = null) : string|array|bool{
		$refClass = new \ReflectionClass($class);

		if(!empty($prop)){
	        $findPropertie = array_values(array_filter($refClass->getProperties(), function($item) use ($prop){
	            return $prop === $item->getName();
	        }));
	    }else{
	    	$findPropertie = $refClass->getProperties();
	    }

        if(!empty($findPropertie)){
        	foreach($findPropertie as $propItem){
        		$attributes = $propItem->getAttributes();

	            $column = array_values(array_filter($attributes, function($attr){
	                return $attr->getName() === 'Column';
	            }));

	            if(!empty($column)){
	                $columns[] = $column[0]->getArguments()[0];
	            }
        	}

        	return empty($prop) ? $columns : (!empty($columns) ? $columns[0] : FALSE);
        }

		return FALSE;
	}
}
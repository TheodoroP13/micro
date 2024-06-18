<?php

namespace Psf\Database;

class Delete extends Connect{
    private $table;
    private $terms;
    private $palces;
    private $result;
    private $delete;
    private $connection;
    private $database;

    public static function exe($table, $terms, $parseString = null, $database = 'default'){
        $obj = new Delete;

        if(empty(self::$connection)){
            $obj->connection = parent::getConnection($database);
        }

        $obj->table = (string) $table;
        $obj->terms = (string) $terms;

        if(self::verifyTableExist($table, $database)){
            parse_str($parseString, $obj->places);
            
            $obj->delete = "DELETE FROM {$obj->table} {$obj->terms}";

            try{
                $obj->delete = $obj->connection->prepare($obj->delete);

                $obj->delete->execute($obj->places);
                $obj->result = true;

                return $obj;
            }catch (\PDOException $e){
                if($obj->connection->inTransaction()){
                    $obj->connection->rollBack();
                }

                $obj->result = null;
                explodeException($e);

                return false;  
            }
            return $this;
        }
    }
    
    public function getResult(){
        return $this->delete;
    }
    
    public function getRowCount(){
        return $this->delete->rowCount();
    }
}
<?php

namespace Prospera\Database;

use \Prospera\Enumerators\{DBDriver};

class Connect{      
    static $connect = null;
    static $tables = null;

    private static function doConnect($database = 'default'){
        $configDb = \PSF::getConfig()->db;

        if(isset($configDb[$database]) && !empty($configDb[$database])){
            $driver = !empty($configDb[$database]['driver']) ? $configDb[$database]['driver'] : DBDriver::MySQL;

            $hostname   = $configDb[$database]['hostname'];
            $username   = $configDb[$database]['username'];
            $password   = $configDb[$database]['password'];
            $base       = $configDb[$database]['database'];
            $port       = $configDb[$database]['port'] ?? 3306;
            $extras     = $configDb[$database]['extras'] ?? []; 

            try{
                if(empty(self::$connect[$database])){
                    if($driver == DBDriver::MySQL){
                        self::$connect[$database] = new \PDO(
                            'mysql:host=' . $hostname .';dbname=' . $base . ';port=' . $port . ';charset=utf8;', 
                            $username, 
                            $password, $extras
                        );
                    }

                    if($driver == DBDriver::SQLServer){
                        self::$connect[$database] = new \PDO(
                            'sqlsrv:Server=' . $configDb[$database]['hostname'] . ';Database=' . $configDb[$database]['database'], 
                            $configDb[$database]['username'], 
                            $configDb[$database]['password'], $extras
                        );
                    }                    
                }
            }catch(\PDOException $e){
                explodeException($e);
            }
            
            self::$connect[$database]->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            self::listTables($database);
            return self::$connect[$database];
        }else{
            throw new \Exception("Database not found");
        }        
    }

    public static function getConnection($database = 'default'){
        return self::doConnect($database);
    }

    public static function listTables($database = 'default'){
        if(extension_loaded('apcu') && (isset(\PSF::getConfig()->pgf['savedbcache']) && \PSF::getConfig()->pgf['savedbcache'] == TRUE)){
            $stringCache = "db_" . \PSF::getConfig()->db[$database]['database'] . "_cache_" . $database;
            $itens = apcu_fetch($stringCache, $recoverOnApcu);

            if($recoverOnApcu){
                self::$tables[$database] = array_values($itens);
                return TRUE;
            }
        }

        try{
            $configDb = \PSF::getConfig()->db;
            $driver = !empty($configDb[$database]['driver']) ? $configDb[$database]['driver'] : DBDriver::MySQL;

            if($driver == DBDriver::MySQL){
                $statement = self::$connect[$database]->prepare("SHOW TABLES");
            }

            if($driver == DBDriver::SQLServer){
                $statement = self::$connect[$database]->prepare("SELECT TABLE_NAME
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_TYPE = 'BASE TABLE'");
            }  

            if(isset($statement) && $statement instanceof \PDOStatement){
                $statement->execute();
                $tables = $statement->fetchAll(\PDO::FETCH_NUM);

                $itens = [];
                foreach($tables as $item){        
                    $itens[] = $item[0];
                }
                
                self::$tables[$database] = array_values($itens);

                if(extension_loaded('apcu') && (isset(\PSF::getConfig()->pgf['savedbcache']) && \PSF::getConfig()->pgf['savedbcache'] == TRUE)){
                    apcu_store($stringCache, array_values($itens), 604800);
                }
            }
        }catch (\PDOException $e){
            explodeException($e);
        }
    }

    public static function getColunsForTable($table, $database = 'default') : array{
        self::getConnection($database);

        if(extension_loaded('apcu') && (isset(\PSF::getConfig()->pgf['savedbcache']) && \PSF::getConfig()->pgf['savedbcache'] == TRUE)){
            $stringCache = 'db_' . \PSF::getConfig()->db[$database]['database'] . '_cache_' . \PSF::getConfig()->db[$database]['database'] . '_' . $table;
            $itens = apcu_fetch($stringCache, $recoverOnApcu);

            if($recoverOnApcu){
                return $itens;
            }
        }

        try{
            $configDb = \PSF::getConfig()->db;
            $driver = !empty($configDb[$database]['driver']) ? $configDb[$database]['driver'] : DBDriver::MySQL;

            if($driver == DBDriver::MySQL){
                $statement = self::$connect[$database]->prepare("SHOW COLUMNS FROM `" . $table . "`");
            }

            if($driver == DBDriver::SQLServer){
                $statement = self::$connect[$database]->prepare("SELECT COLUMN_NAME as Field
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_NAME = '" . $table . "'");
            }  

            if(isset($statement) && $statement instanceof \PDOStatement){
                $statement->execute();
                $coluns = $statement->fetchAll(\PDO::FETCH_ASSOC);

                foreach($coluns as $item){ 
                    $arrReturn[] = $item['Field'];
                }
                
                if(extension_loaded('apcu') && (isset(\PSF::getConfig()->pgf['savedbcache']) && \PSF::getConfig()->pgf['savedbcache'] == TRUE)){
                    apcu_store($stringCache, $arrReturn, 604800);
                }

                return $arrReturn;
            }
        }catch (\PDOException $e){
            explodeException($e); 
        }
    }

    public static function getConnect($database = 'default'){
        return self::$connect[$database];
    }

    public static function initTransaction($database = 'default'){
        self::getConnection($database);
        self::$connect[$database]->beginTransaction();
        return self::$connect[$database];
    }

    public static function commitTransaction($database = 'default'){
        if(self::$connect[$database]->inTransaction()){
            self::$connect[$database]->commit();
            self::$connect[$database] = null;
        }
    }

    public static function rollBackTransaction($database = 'default'){
        if(self::$connect[$database]->inTransaction()){
            self::$connect[$database]->rollBack();
            self::$connect[$database] = null;
        }
    }

    public static function inTransactionQuery($database = 'default'){
        if(self::$connect[$database]->inTransaction()){
            return true;
        }
        return false;
    }

    public static function verifyTableExist($table, $database = 'default'){
        if(in_array($table, self::$tables[$database])){
            return true;
        }else{
            return false;
        }
    }
    
}
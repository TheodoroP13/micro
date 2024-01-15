<?php

namespace Prospera\Model;

use \Prospera\Enumerators\{DBDriver};

class ModelQuery{
    private $obj;
    private $query;

    public function __construct($class){
        $this->obj = new $class;

        $this->query = [
            'fields'        => null,
            'wheres'        => null,
            'orWheres'      => null,
            'innerJoins'    => null,
            'leftJoins'     => null,
            'orderBy'       => null,
            'groupBy'       => null,
            'limit'         => null,
            'parses'        => null,
            'freequery'     => null,
            'isCount'       => false,
            'asArray'       => false,
            'database'      => $this->obj->database ?? 'default'
        ];
    }

    private function getDatabaseName() : string{
        return \PSF::getConfig()->db[$this->query['database']]['database'];
    }

    private function handleTableName() : string{
        $configDb = \PSF::getConfig()->db;
        $driver = !empty($configDb[$this->query['database']]['driver']) ? $configDb[$this->query['database']]['driver'] : DBDriver::MySQL;

        if($driver == DBDriver::MySQL){
            return '`' . $this->getDatabaseName() . '`.`' . $this->obj->tableName . '`';
        }

        if($driver == DBDriver::SQLServer){
            return '[' . $this->getDatabaseName() . '].[dbo].[' . $this->obj->tableName . ']';
        }

        return $this->getDatabaseName() . '.' . $this->obj->tableName;
    }

    public static function getHandleTableName(string $database = 'default', string $table) : string{
        $configDb   = \PSF::getConfig()->db;
        $driver     = !empty($configDb[$database]['driver']) ? $configDb[$database]['driver'] : DBDriver::MySQL;

        if($driver == DBDriver::MySQL){
            return '`' . \PSF::getConfig()->db[$database]['database'] . '`.`' . $table . '`';
        }

        if($driver == DBDriver::SQLServer){
            return '[' . $table . ']';
        }

        return \PSF::getConfig()->db[$database]['database'] . '.' . $table;
    }

    private function getAcceptComparativeOperators() : array{
        return ["=", "<>", "IS NULL", "IS NOT NULL", "LIKE"];
    }

    private function generateField($field){
        // var_dump($field);

        $configDb = \PSF::getConfig()->db;
        $driver = !empty($configDb[$this->query['database']]['driver']) ? $configDb[$this->query['database']]['driver'] : DBDriver::MySQL;

        $arrIgnoreRules = ["SUM", "COUNT"];

        if(is_array($field)){
            $tableName = class_exists($field[0]) ? (new $field[0])->getTableName() : $field[0];

            if($driver == DBDriver::MySQL){
                return $tableName . ".`" . $field[1] . "`";
            }

            if($driver == DBDriver::SQLServer){
                // return "[" . $tableName . "].[" . $field[1] . "]";
                return "[" . $field[1] . "]";
            }

            return $tableName . "." . $field[1];            
        }else{
            if(isset($this->obj->tableName) && !empty($this->obj->tableName) && !in_array(substr($field, 0, 5), $arrIgnoreRules) && !in_array(substr($field, 0, 3), $arrIgnoreRules)){
                $explodeField = explode(".", $field);

                if(count($explodeField) == 2){
                    if(class_exists($explodeField[0])){
                        if($driver == DBDriver::MySQL){
                            $field = '`' . (new $explodeField[0])->getTableName() . '`.`' . ($this->handleAliasField($explodeField[1])) . '`';
                            echo 31;
                        }

                        if($driver == DBDriver::SQLServer){
                            $field = $this->handleAliasField($explodeField[1]);
                            echo 32;
                        }

                        if(!isset($field)){
                            $field = (new $explodeField[0])->getTableName() . ($this->handleAliasField($explodeField[1]));
                            echo 33;
                        }
                    }else{
                        echo 34;
                        $field = ($explodeField[0] . $this->handleAliasField($explodeField[1]));
                    }

                    // $field = class_exists($explodeField[0]) ? ((new $explodeField[0])->getTableName() . ($this->handleAliasField($explodeField[1]))) : ($explodeField[0] . $this->handleAliasField($explodeField[1]));
                    
                    // echo 1;
                    return $field;
                }else if(count($explodeField) > 2){
                    echo 2;
                    return $field;
                }else{
                    if($driver == DBDriver::MySQL){
                        return $this->obj->tableName . ".`" . $field . "`";
                    }

                    if($driver == DBDriver::SQLServer){
                        // return '[' . $this->obj->tableName . "].[" . $field . "]";
                        return "[" . $field . "]";
                    }

                    echo 3;

                    return $this->obj->tableName . "." . $field;
                }
            }else{
                echo 4;
                return $field;
            }
        }
    }

    private function handleAliasField(string $field) : string{
        $configDb = \PSF::getConfig()->db;
        $driver = !empty($configDb[$this->query['database']]['driver']) ? $configDb[$this->query['database']]['driver'] : DBDriver::MySQL;

        $explodeAs = explode(' ', $field);

        if(in_array('as', $explodeAs) || in_array('AS', $explodeAs)){
            if($driver == DBDriver::MySQL){
                return '.`' . $explodeAs[0] . '` AS `' . $explodeAs[2] . '`';
            }

            if($driver == DBDriver::SQLServer){
                return '.[' . $explodeAs[0] . '] AS [' . $explodeAs[2] . ']';
            }            

            return '.' . $explodeAs[0] . ' AS ' . $explodeAs[2];
        }   

        if($field == '*'){
            return '.' . $field;
        }

        if($driver == DBDriver::MySQL){
            return '.`' . $field . '`';
        }

        if($driver == DBDriver::SQLServer){
            return '[' . $field . ']';
        }
    }   

    private function handleExtraQuery($query){
        $explodeSpaces = explode(' ', $query);

        if(count($explodeSpaces) > 0){
            foreach ($explodeSpaces as &$itemSpaced) {
                $explodeField = explode('.', $itemSpaced);
                
                if(count($explodeField) > 0){
                    foreach ($explodeField as &$itemField) {
                        if (strpos($itemField, "\\") !== false) {
                            $itemField = class_exists($itemField) ? (new $itemField)->getTableName() : $itemField;
                        }
                    }

                    $itemSpaced = implode('.', $explodeField);
                }
            }

            $query = implode(' ', $explodeSpaces); 
        }

        return $query;
    }   

    public function andWhere(string|array $query, array|null $parses = null) : ModelQuery{
        $parse = uniqid();
        if(is_array($query)){
            if(count($query) == 1){
                $this->query['parses'][$parse] = $query[array_keys($query)[0]];
                $this->query['wheres'][] = $this->generateField(array_keys($query)[0]) . " = :" . $parse;
            }else if(count($query) == 3){
                if(in_array($query[1], $this->getAcceptComparativeOperators())){
                    if($query[1] == "IS NULL" || $query[1] == "IS NOT NULL"){
                        $this->query['wheres'][] = $this->generateField($query[0]) . " " . $query[1];
                    }else{
                        $this->query['parses'][$parse] = $query[2];
                        $this->query['wheres'][] = $this->generateField($query[0]) . " " . $query[1] . " :" . $parse;
                    }
                }else{
                    //Estourar erro
                }
            }else if(count($query) == 2){
                if($query[0] == "OR" && is_array($query[1])){
                    $stringFinal = "(";
                    $countItens = 0;
                    foreach($query[1] as $key => $value){
                        if(count($value) == 1){
                            $parse = uniqid();
                            $this->query['parses'][$parse] = $value[array_keys($value)[0]];
                            $stringFinal .= $this->generateField(array_keys($value)[0]) . " = :" . $parse;
                            if($countItens < count($query[1]) - 1){
                                $stringFinal .= " OR ";
                            }
                            $countItens++;
                        }else if(count($value) == 3){
                            if(in_array($value[1], $this->getAcceptComparativeOperators())){
                                if($value[1] == "IS NULL" || $value[1] == "IS NOT NULL"){
                                    $stringFinal .= $this->generateField($value[0]) . " " . $value[1];
                                }else{
                                    $parse = uniqid();
                                    $this->query['parses'][$parse] = $value[2];
                                    $stringFinal .= $this->generateField($value[0]) . " " . $value[1] . " :" . $parse;
                                }
                                if($countItens < count($query[1]) - 1){
                                    $stringFinal .= " OR ";
                                }
                                $countItens++;
                            }else{
                                //Estourar erro
                            }
                        }
                    }
                    $stringFinal .= ")";
                    if(!empty($stringFinal)){
                        $this->query['wheres'][] = $stringFinal;
                    }
                }else if($query[0] == 'AND' && is_array($query[1])){
                    $stringFinal = "(";
                    $countItens = 0;
                    foreach($query[1] as $key => $value){
                        if(count($value) == 1){
                            $parse = uniqid();
                            $this->query['parses'][$parse] = $value[array_keys($value)[0]];
                            $stringFinal .= $this->generateField(array_keys($value)[0]) . " = :" . $parse;
                            if($countItens < count($query[1]) - 1){
                                $stringFinal .= " AND ";
                            }
                            $countItens++;
                        }else if(count($value) == 3){
                            if(in_array($value[1], $this->getAcceptComparativeOperators())){
                                if($value[1] == "IS NULL" || $value[1] == "IS NOT NULL"){
                                    $stringFinal .= $this->generateField($value[0]) . " " . $value[1];
                                }else{
                                    $parse = uniqid();
                                    $this->query['parses'][$parse] = $value[2];
                                    $stringFinal .= $this->generateField($value[0]) . " " . $value[1] . " :" . $parse;
                                }
                                if($countItens < count($query[1]) - 1){
                                    $stringFinal .= " AND ";
                                }
                                $countItens++;
                            }else{
                                //Estourar erro
                            }
                        }
                    }
                    $stringFinal .= ")";
                    if(!empty($stringFinal)){
                        $this->query['wheres'][] = $stringFinal;
                    } 
                }
            }
        }else if(is_string($query)){
            if(!empty($parses)){
                foreach($parses as $key => $item){
                    $parse = uniqid();
                    $query = str_replace(':' . $key . ':', ':' . $parse, $query);
                    $this->query['parses'][$parse] = $item;
                }
                $this->query['wheres'][] = $query;
            }else{
                $this->query['wheres'][] = $query;
            }
        }
        return $this;
    }

    public function orWhere(string|array $query, array|null $parses = null) : ModelQuery{
        $parse = uniqid();
        if(is_array($query)){
            if(count($query) == 1){
                $this->query['parses'][$parse] = $query[array_keys($query)[0]];
                $this->query['orWheres'][] = $this->generateField(array_keys($query)[0]) . " = :" . $parse;
            }else if(count($query) == 3){
                if(in_array($query[1], $this->getAcceptComparativeOperators())){
                    if($query[1] == "IS NULL" || $query[1] == "IS NOT NULL"){
                        $this->query['orWheres'][] = $this->generateField($query[0]) . " " . $query[1];
                    }else{
                        $this->query['parses'][$parse] = $query[2];
                        $this->query['orWheres'][] = $this->generateField($query[0]) . " " . $query[1] . " :" . $parse;
                    }
                }else{
                    //Estourar erro
                }
            }else if(count($query) == 2){
                if($query[0] == "OR" && is_array($query[1])){
                    $stringFinal = "(";
                    $countItens = 0;
                    foreach($query[1] as $key => $value){
                        if(count($value) == 1){
                            $parse = uniqid();
                            $this->query['parses'][$parse] = $value[array_keys($value)[0]];
                            $stringFinal .= $this->generateField(array_keys($value)[0]) . " = :" . $parse;
                            if($countItens < count($query[1]) - 1){
                                $stringFinal .= " OR ";
                            }
                            $countItens++;
                        }else if(count($value) == 3){
                            if(in_array($value[1], $this->getAcceptComparativeOperators())){
                                if($value[1] == "IS NULL" || $value[1] == "IS NOT NULL"){
                                    $stringFinal .= $this->generateField($value[0]) . " " . $value[1];
                                }else{
                                    $parse = uniqid();
                                    $this->query['parses'][$parse] = $value[2];
                                    $stringFinal .= $this->generateField($value[0]) . " " . $value[1] . " :" . $parse;
                                }
                                if($countItens < count($query[1]) - 1){
                                    $stringFinal .= " OR ";
                                }
                                $countItens++;
                            }else{
                                //Estourar erro
                            }
                        }
                    }
                    $stringFinal .= ")";
                    if(!empty($stringFinal)){
                        $this->query['orWheres'][] = $stringFinal;
                    }
                }else if($query[0] == 'AND' && is_array($query[1])){
                    $stringFinal = "(";
                    $countItens = 0;
                    foreach($query[1] as $key => $value){
                        if(count($value) == 1){
                            $parse = uniqid();
                            $this->query['parses'][$parse] = $value[array_keys($value)[0]];
                            $stringFinal .= $this->generateField(array_keys($value)[0]) . " = :" . $parse;
                            if($countItens < count($query[1]) - 1){
                                $stringFinal .= " AND ";
                            }
                            $countItens++;
                        }else if(count($value) == 3){
                            if(in_array($value[1], $this->getAcceptComparativeOperators())){
                                if($value[1] == "IS NULL" || $value[1] == "IS NOT NULL"){
                                    $stringFinal .= $this->generateField($value[0]) . " " . $value[1];
                                }else{
                                    $parse = uniqid();
                                    $this->query['parses'][$parse] = $value[2];
                                    $stringFinal .= $this->generateField($value[0]) . " " . $value[1] . " :" . $parse;
                                }
                                if($countItens < count($query[1]) - 1){
                                    $stringFinal .= " AND ";
                                }
                                $countItens++;
                            }else{
                                //Estourar erro
                            }
                        }
                    }
                    $stringFinal .= ")";
                    if(!empty($stringFinal)){
                        $this->query['orWheres'][] = $stringFinal;
                    } 
                }
            }
        }else if(is_string($query)){
            if(!empty($parses)){
                foreach($parses as $key => $item){
                    $parse = uniqid();
                    $query = str_replace(':' . $key . ':', ':' . $parse, $query);
                    $this->query['parses'][$parse] = $item;
                }
                $this->query['orWheres'][] = $query;
            }else{
                $this->query['orWheres'][] = $query;
            }
        }
        return $this;
    }

    public function dump(){

        echo "<pre>";
        var_dump($this);
        die;

    }

    public function fields(array|null $fields = null) : ModelQuery{
        if(is_array($fields)){
            foreach($fields as $item){
                $this->query['fields'][] = $this->generateField($item);
            }
        }
        return $this;
    }

    public function one(){
        $this->query['limit'] = 1;
        return $this->execute();
    }

    public function all(){
        $this->query['limit'] = null;
        return $this->execute();
    }

    public function innerJoin(array|string $table, string $query) : ModelQuery{
        $this->query['innerJoins'][] = [
            "table" => $table, 
            "query" => $query
        ];

        return $this;
    }

    public function leftJoin(array|string $table, string $query) : ModelQuery{
        $this->query['leftJoins'][] = [
            "table" => $table, 
            "query" => $query
        ];

        return $this;
    }

    public function orderBy(string $field, string $type = "ASC") : ModelQuery{
        if($type == "ASC" || $type == "DESC"){
            $this->query['order'][] = $this->generateField($field) . " " . $type;
        }
        return $this;
    }

    public function limit(int $limit) : ModelQuery{
        $this->query['limit'] = $limit;
        return $this;
    }

    public function groupBy(string $field) : ModelQuery{
        $this->query['groupBy'][] = $this->generateField($field);
        return $this;
    }

    public function database(string $database){
        $this->query['database'] = $database;
        return $this;
    }

    public function execute(){
        if(property_exists($this->obj, "status")){
            $this->andWhere(['status', '<>' , -1]);
        }

        if(property_exists($this->obj, "deleted")){
            $this->andWhere($this->obj->tableName . '.deleted IS NULL');
        }

        // var_dump($this->writeQuery());

        $Read = new \Prospera\Database\Read($this->obj->databaseConnect ?? null);
        $Read->exe(
            $this->obj->tableName,
            $this->writeQuery(),
            $this->getParses(),
            !empty($this->query['database']) ? $this->query['database'] : 'default',
            true
        );
        return $this->queryResult($Read);
    }

    private function writeQuery() : string{
        $configDb = \PSF::getConfig()->db;
        $driver = !empty($configDb[$this->query['database']]['driver']) ? $configDb[$this->query['database']]['driver'] : DBDriver::MySQL;

        $stringQuery = "SELECT ";

        if($driver == DBDriver::SQLServer){
            if(isset($this->query['limit']) && !empty($this->query['limit'])){
                $stringQuery .= " TOP " . $this->query['limit'] . ' ';
            }
        }   

        if(isset($this->query['isCount']) && $this->query['isCount'] === true){
            if(property_exists($this->obj, "code")){
                $fieldsQuery = "COUNT(" . $this->generateField("code") . ") as qtd";
            }else{
                // if(!empty($fieldToCount)){

                // var_dump($this->generateField($this->obj->getColunsForTable())[0]);
                // die;
                    $fieldsQuery = "COUNT(" . $this->generateField($this->obj->getColunsForTable()[0]) . ") as qtd";
                // }
            }
        }else if(!isset($this->query['fields']) || empty($this->query['fields'])){
            if((isset($this->query['innerJoins']) && !empty($this->query['innerJoins'])) || (isset($this->query['leftJoins']) && !empty($this->query['leftJoins']))){
                $fieldsQuery = '`' . $this->obj->tableName . '`.*';
            }else{
                $fieldsQuery = '*';
            }
        }else{
            $fieldsQuery = implode(", ", $this->query['fields']);
        }

        $stringQuery .= $fieldsQuery;
        $stringQuery .= " FROM " . $this->handleTableName() . " ";

        // var_dump($this->handleTableName());

        if(isset($this->query['innerJoins']) && !empty($this->query['innerJoins'])){
            foreach($this->query['innerJoins'] as $itemJoin){
                if(is_array($itemJoin['table'])){
                    $tableName = class_exists($itemJoin['table'][0]) ? (new $itemJoin['table'][0])->getTableName() : $itemJoin['table'][0];

                    $stringQuery .= " INNER JOIN " . $tableName . " AS " .  $itemJoin['table'][1] . " ON " . $this->handleExtraQuery($itemJoin['query']) . " ";
                }else{
                    $tableName = class_exists($itemJoin['table']) ? (new $itemJoin['table'])->getTableName() : $itemJoin['table'];
                    
                    $stringQuery .= " INNER JOIN " . $tableName . " ON " . $this->handleExtraQuery($itemJoin['query']) . " ";
                }
            }
        }

        if(isset($this->query['leftJoins']) && !empty($this->query['leftJoins'])){
            foreach($this->query['leftJoins'] as $itemJoin){
                if(is_array($itemJoin['table'])){
                    $tableName = class_exists($itemJoin['table'][0]) ? (new $itemJoin['table'][0])->getTableName() : $itemJoin['table'][0];

                    $stringQuery .= " LEFT JOIN " . $tableName . " AS " .  $itemJoin['table'][1] . " ON " . $this->handleExtraQuery($itemJoin['query']) . " ";
                }else{
                    $tableName = class_exists($itemJoin['table']) ? (new $itemJoin['table'])->getTableName() : $itemJoin['table'];
                    
                    $stringQuery .= " LEFT JOIN " . $tableName . " ON " . $this->handleExtraQuery($itemJoin['query']) . " ";
                }
            }
        }

        if(isset($this->query['wheres']) && !empty($this->query['wheres'])){
            $stringWhere = "WHERE ";
            $countWheres = 0;
            $countOrWheres = 0;

            foreach($this->query['wheres'] as $item){
                $stringWhere .= $item;
                if($countWheres < (count($this->query['wheres']) - 1)){
                    $stringWhere .= " AND ";
                }
                $countWheres++;
            }

            if(!empty($this->query['wheres']) && !empty($this->query['orWheres'])){
                $stringWhere .= " OR ";
            }

            if(!empty($this->query['orWheres'])){
                foreach ($this->query['orWheres'] as $item) {
                    $stringWhere .= $item;
                    if($countOrWheres < (count($this->query['orWheres']) - 1)){
                        $stringWhere .= " OR ";
                    }
                    $countOrWheres++;
                }               
            }

        }

        $stringQuery .= ($stringWhere ?? "");

        if(isset($this->query['groupBy']) && !empty($this->query['groupBy'])){
            if(count($this->query['groupBy']) == 1){
                $stringQuery .= " GROUP BY " . $this->query['groupBy'][0];
            }else{
                $countGroup = 0;
                $stringQuery .= " GROUP BY ";
                foreach($this->query['groupBy'] as $item){
                    if($countGroup > 0){
                        $stringQuery .= ", " . $item;
                    }else{
                        $stringQuery .= $item;
                    }
                    $countGroup++;
                }
            }
        }
        
        if(isset($this->query['order']) && !empty($this->query['order'])){
            $stringQuery .= " ORDER BY ";
            $countOrder = 0;

            foreach($this->query['order'] as $item){
                if(count($this->query['order']) > 1 && $countOrder > 0){
                    $stringQuery .= ", ";
                }

                $stringQuery .= $item;
                $countOrder++;
            }

        }

        if($driver == DBDriver::MySQL){
            if(isset($this->query['limit']) && !empty($this->query['limit'])){
                $stringQuery .= " LIMIT " . $this->query['limit'];
            }

            if(isset($this->query['offset']) && !empty($this->query['offset'])){
                $stringQuery .= " OFFSET " . $this->query['offset'];
            }
        }

        return trim(str_replace("  ", " ", $stringQuery));
    }

    public function getRowQuery() : string {
        if(property_exists($this->obj, "status")){
            $this->andWhere(['status', '<>' , -1]);
        }

        if(property_exists($this->obj, "deletado")){
            $this->andWhere($this->obj->tableName . '.[deletado] IS NULL');
        }

        $query = $this->writeQuery();
        if(!empty($this->query['parses']) && is_array($this->query['parses'])){
            foreach($this->query['parses'] as $key => $value){
                $query = str_replace(':' . $key, $value, $query);
            }
        }
        return $query;
    }

    public function getParses(){
        if(isset($this->query['parses']) && !empty($this->query['parses'])){
            $parseString = "";
            $countItens = 1;

            foreach($this->query['parses'] as $key => $value){
                $parseString .= $key . "=" . $value;
                if($countItens < count($this->query['parses'])){
                    $parseString .= "&";
                }
                $countItens++;
            }

            return $parseString;
        }else{
            return false;
        }
    }

    public function asArray(){
        $this->query['asArray'] = true;
        return $this;
    }

    private function queryResult($Read) : object|bool|array|int {
        if($this->query['isCount'] === true){ 
            return $Read->getResult()[0]['qtd'];
        }

        if($Read->getRowCount() == 0){
            return false;
        }

        if($Read->getRowCount() == 1 && $this->query['limit'] == 1){
            if($this->query['asArray'] === true){
                return $Read->getResult()[0];
            }else{
                $class = $this->obj::class;
                $newObj = new $class;
                $newObj->assign($Read->getResult()[0], true);
                $newObj->assign(["cache" => (object) $Read->getResult()[0]]);
                $newObj->database = !empty($this->query['database']) ? $this->query['database'] : 'default';
                return $newObj;
            }
        }else if($Read->getRowCount() >= 1){
            if($this->query['asArray'] === true){
                return $Read->getResult();
            }else{
                $return = [];
                $class = $this->obj::class;
                foreach($Read->getResult() as $item){
                    $newObj = new $class;
                    $newObj->assign($item, true);
                    $newObj->assign(["cache" => (object) $item]);
                    $newObj->database = !empty($this->query['database']) ? $this->query['database'] : 'default';
                    $return[] = $newObj;
                }
                return $return;
            }
        }
    }

    public function count() : int{
        $this->query['isCount'] = true;
        return $this->execute();
    }

    public function countAll() : int{
        $countTotal = clone $this;
        $countTotal->query['isCount'] = true;
        $countTotal->query['limit'] = null;
        $countTotal->query['offset'] = null;
        return $countTotal->execute();
    }

    public function paginator(int $page = 1, int $itensPerPage = 25, $callbackFunction = null) : object|array|bool|null{
        if($page < 1){
            $page = 1;
        }

        $initIn = $page == 1 ? 0 : (($page - 1) * $itensPerPage);

        $this->query['asArray'] = true;
        $this->query['limit'] = $itensPerPage;
        $this->query['offset'] = $initIn;

        $itens = $this->execute();
        if($itens == false){
            $itens = [];
        }
        $total = $this->countAll();

        $estimatedPages = ceil(
            $total / $itensPerPage
        );

        $paginatorData = [
            'itens' => [
                'total' => $total,
                'perPage' => $itensPerPage,
                'inThisPage' => count($itens)
            ],
            'pages' => [
                'atual' => $page,
                'estimated' => $estimatedPages,
                'hasBefore' => $page <= 1 ? false : true,
                'hasAfter' => $page >= $estimatedPages ? false : true
            ]
        ];

        $result = [
            "itens" => $itens,
            "paginator" => $paginatorData
        ];

        if(empty($callbackFunction)){
            return $result;
        }else{
            return call_user_func($callbackFunction, $result);
        }
    }

    public function query($query, $parseString = null, $database = 'default'){
        if(!isset($this->query['fields']) || empty($this->query['fields'])){
            $query = "* FROM " . $this->handleTableName() . " " . $query;
        }else{
            $fieldsQuery = implode(", ", $this->query['fields']);
            $query = $fieldsQuery . " FROM " . $this->handleTableName() . " " . $query;
        }

        if(!empty($parseString) && is_array($parseString)){
            $stringParseString = "";
            $countParses = 1;
            foreach($parseString as $key => $value){
                $stringParseString .= $key . "=" . $value;
                if($countParses < count($parseString)){
                    $stringParseString .= "&";
                }
                $countParses++;
            }
        }else{
            $stringParseString = null;
        }

        $Read = new \Prospera\Database\Read($this->obj->databaseConnect ?? null);
        $Read->exe(
            $this->obj->tableName,
            $query,
            $stringParseString,
            $database,
            true
        ); 

        return $this->queryResult($Read);
    }

    public function exist() : bool{
        $this->query['limit']       = 1;
        $this->query['fields']      = ['1'];
        $this->query['asArray']     = TRUE;
        $executeSelect = $this->execute();

        if($executeSelect && $executeSelect['1'] === 1){
            return TRUE;
        }

        return FALSE;
    }

    public function sum(string $field) : float{
        $this->query['limit']       = 1;
        $this->query['fields']      = ['SUM(' . $this->generateField($field) . ') AS result'];
        $this->query['asArray']     = TRUE;
        $executeSelect = $this->execute();

        if($executeSelect && isset($executeSelect['result']) && !empty($executeSelect['result'])){
            return (float)$executeSelect['result'];
        }

        return 0;
    }
}
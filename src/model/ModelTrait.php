<?php

namespace Psf\Model;

trait ModelTrait{
    public static function find() : ModelQuery {
        return new ModelQuery(self::class);
    }

    public static function findById(int $id) : ModelQuery {
        return (new ModelQuery(self::class))->fields([
            self::class . '.*',
        ])->andWhere([self::class . '.id' => $id]);
    }

    public function __call($function, $value){
        $attrb = strtolower(substr($function, 3));
        $func = strtolower(substr($function, 0, 3));

        if($func == "set"){
            $this->$attrb = $value[0];
            return $this;
        }else if($func == "get"){
            return $this->$attrb;
        }else if(function_exists($function)){
            call_user_func($function);
        }else{
            // Http::response("A variável/função '" . $function . "' não foi encontrada...", [], 500);
        }
    }

    public function hasError(){
        if(property_exists($this, 'error')){
            if(!empty($this->error)){
                return TRUE;
            }
        }

        return FALSE;
    }
}
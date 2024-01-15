<?php

namespace Prospera\Model;

trait EnumTrait{
	public static function verifyCaseExistByKey($key){
		return current(array_filter(
		    self::cases(),
		    fn($item) => $item->value == $key
		));
	}

	public static function verifyCaseExistByName($name){
		return current(array_filter(
		    self::cases(),
		    fn($item) => $item->name == $name
		));
	}

	public static function verifyCaseExistByMethod($method, $response){
		return current(array_filter(
		    self::cases(),
		    fn($item) => call_user_func([$item, $method]) == $response
		));
	}

	public static function getListByMethod($method) : array{
		foreach(self::cases() as $item){
            $itens[] = $item->{$method}();
        }

        return $itens ?? [];
	}
}
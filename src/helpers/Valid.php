<?php

namespace Psf\Helper;

class Valid{

	public static function cpf($number){
		if(empty($number)){
			return false;
		}

		$number = preg_replace("/[^0-9]/", "", $number);
		$number = str_pad($number, 11, '0', STR_PAD_LEFT);
		
		$invalid = ["00000000000", "11111111111", "22222222222", "33333333333", "44444444444", "55555555555", "66666666666", "77777777777", "88888888888", "99999999999"];

		if(strlen($number) != 11){
			return false;
		}else if(in_array($number, $invalid)){
			return false;
		}else{ 
			for($t = 9; $t < 11; $t++){
				for($d = 0, $c = 0; $c < $t; $c++){
					$d += $number[$c] * (($t + 1) - $c);
				}

				$d = ((10 * $d) % 11) % 10;

				if($number[$c] != $d){
					return false;
				}
			}
			return true;
		}
	}

	public static function cnpj($number){
		if(empty($number)){
			return false;
		}

		$number = preg_replace("/[^0-9]/", "", $number);
		$number = str_pad($number, 14, '0', STR_PAD_LEFT);
	 		
	 	$invalid = ["00000000000000", "11111111111111", "22222222222222", "33333333333333", "44444444444444", "55555555555555", "66666666666666", "77777777777777", "88888888888888", "99999999999999"];

		if(strlen($number) != 14){
			return false;
		}else if(in_array($number, $invalid)){
			return false;
		}else{   
			$digit = str_split($number);
	 
			$j = 5;
			$k = 6;
			$soma1 = 0;
			$soma2 = 0;

			for($i = 0; $i < 13; $i++){
				$digit[$i] = (int) $digit[$i];

				$j = $j == 1 ? 9 : $j;
				$k = $k == 1 ? 9 : $k;

				$soma2 += ($digit[$i] * $k);

				if($i < 12){
					$soma1 += ($digit[$i] * $j);
				}

				$k--;
				$j--;
			}

			$digito1 = $soma1 % 11 < 2 ? 0 : 11 - $soma1 % 11;
			$digito2 = $soma2 % 11 < 2 ? 0 : 11 - $soma2 % 11;

			return (($digit[12] == $digito1) and ($digit[13] == $digito2));
		}
	}

	public static function email($email){
		return filter_var($email, FILTER_VALIDATE_EMAIL);
	}

	public static function date($date){
		if(date("Y-m-d", strtotime($date)) == "1969-12-31"){
			return false;
		}

		return checkdate(date("m", strtotime($date)), date("d", strtotime($date)), date("Y", strtotime($date)));
	}

	public static function pixRandomKey($key){
		return preg_match('/^[0-9A-F]{8}-[0-9A-F]{4}-4[0-9A-F]{3}-[89AB][0-9A-F]{3}-[0-9A-F]{12}$/i', $key);
	}

}
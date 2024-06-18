<?php

namespace Psf\Helper;

class Text{
	public static function capitalizeName($name){
		$prepositions = ["de", "da", "di", "do", "dos", "du"];

		$stringReturn = "";
		$explodeName = explode(" ", $name);

		if(count($explodeName) > 0){
			foreach($explodeName as $item){
				$stringAtual = strtolower($item);
				if(!in_array($stringAtual, $prepositions)){
					$stringAtual = ucfirst($stringAtual);
				}
				$stringReturn .= $stringAtual . " ";
			}
		}else{
			$stringReturn = $name;
		}

		return trim($stringReturn);
	}

	public static function reduceName($name, $size = 20, $removePrepositions = true){
		$prepositions = ["de", "De", "da", "Da", "di", "do", "Do", "du", "Du", "dos", "Dos"];

        if(strlen($name) > ($size - 2)){
            $name = strip_tags($name);
            $explodeName = explode(" ", $name);

            $firstName = $explodeName[0];
            $surname = trim($explodeName[count($explodeName) - 1]);
            $surnamePosition = count($explodeName) - 1;

            $middle = "";

            for($a = 1; $a < $surnamePosition; $a++){
            	if(in_array($explodeName[$a], $prepositions)){
            	}else{
                	if(strlen($firstName . " " . $middle . " " . $surname) <= $size){
                    	$middle .= " " . strtoupper(substr($explodeName[$a], 0, 1)) . ".";
                	}	
                }
            }
        }else{
           $firstName = $name;
           $middle = '';
           $surname = '';
        }

        return trim($firstName . $middle . " " . $surname);
	}

	public static function limit($text, $limit){
		$total = strlen($text);
        if($total >= $limit){
            return substr($text, 0, strrpos(substr($text, 0, $limit), ' ')) . '...';
        }else{
            return $text;
        }
	}

	public static function slugify($string){
		$list = [
			'Š' => 'S',
			'š' => 's',
			'Đ' => 'Dj',
			'đ' => 'dj',
			'Ž' => 'Z',
			'ž' => 'z',
			'Č' => 'C',
			'č' => 'c',
			'Ć' => 'C',
			'ć' => 'c',
			'À' => 'A',
			'Á' => 'A',
			'Â' => 'A',
			'Ã' => 'A',
			'Ä' => 'A',
			'Å' => 'A',
			'Æ' => 'A',
			'Ç' => 'C',
			'È' => 'E',
			'É' => 'E',
			'Ê' => 'E',
			'Ë' => 'E',
			'Ì' => 'I',
			'Í' => 'I',
			'Î' => 'I',
			'Ï' => 'I',
			'Ñ' => 'N',
			'Ò' => 'O',
			'Ó' => 'O',
			'Ô' => 'O',
			'Õ' => 'O',
			'Ö' => 'O',
			'Ø' => 'O',
			'Ù' => 'U',
			'Ú' => 'U',
			'Û' => 'U',
			'Ü' => 'U',
			'Ý' => 'Y',
			'Þ' => 'B',
			'ß' => 'Ss',
			'à' => 'a',
			'á' => 'a',
			'â' => 'a',
			'ã' => 'a',
			'ä' => 'a',
			'å' => 'a',
			'æ' => 'a',
			'ç' => 'c',
			'è' => 'e',
			'é' => 'e',
			'ê' => 'e',
			'ë' => 'e',
			'ì' => 'i',
			'í' => 'i',
			'î' => 'i',
			'ï' => 'i',
			'ð' => 'o',
			'ñ' => 'n',
			'ò' => 'o',
			'ó' => 'o',
			'ô' => 'o',
			'õ' => 'o',
			'ö' => 'o',
			'ø' => 'o',
			'ù' => 'u',
			'ú' => 'u',
			'û' => 'u',
			'ý' => 'y',
			'þ' => 'b',
			'&' => 'e',
			'ÿ' => 'y',
			'Ŕ' => 'R',
			'ŕ' => 'r',
			'/' => '-',
			' ' => '-',
			'.' => '-',
		];

		$string = strtr($string, $list);
		$string = preg_replace('/\(|\)/', '', $string);
		$string = preg_replace('/[\t\n]/', ' ', $string);
		$string = preg_replace('/\s{2,}/', ' ', $string);
		$string = preg_replace('/[^[:alnum:]]/', '-', $string);
		$string = preg_replace('/-{2,}/', '-', $string);
		$string = strtolower($string);
		
		return $string;
	}

	public static function accentClear(string $string) : string{
		$accents = [
	        'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a', 'å' => 'a',
	        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
	        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
	        'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o', 'ø' => 'o',
	        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
	        'ç' => 'c',
	        'Á' => 'A', 'À' => 'A', 'Ã' => 'A', 'Â' => 'A', 'Ä' => 'A', 'Å' => 'A',
	        'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
	        'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
	        'Ó' => 'O', 'Ò' => 'O', 'Õ' => 'O', 'Ô' => 'O', 'Ö' => 'O', 'Ø' => 'O',
	        'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
	        'Ç' => 'C'
	    ];

    	return strtr($string, $accents);
	}
}
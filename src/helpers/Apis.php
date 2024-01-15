<?php

namespace Pgf\Helpers;

use \Pgf\Http\Http;

class Apis{

	public function BrasilApiHolidays($year){
		$request = Http::request("GET", "https://brasilapi.com.br/api/feriados/v1/" . $year, [], []);

		if($request['code'] == 200){
			return $request['response'];
		}else{
			return false;
		}
	}

	public function BrasilApiFindCnpj($cnpj){
		$cnpj = preg_replace("/[^0-9]/", "", $cnpj);

		if(empty($cnpj)){
			return false;
		}else{
			$request = Http::request("GET", "https://brasilapi.com.br/api/cnpj/v1/" . $cnpj, [], []);

			if($request['code'] == 200){
				return $request['response'];
			}else{
				return false;
			}
		}
	}

	public function BrasilApiFindCep($cep){
		$cep = preg_replace("/[^0-9]/", "", $cep);

		if(empty($cep)){
			return false;
		}else{
			$request = Http::request("GET", "https://brasilapi.com.br/api/cep/v2/" . $cep, [], []);

			if($request['code'] == 200){

				$response = $request['response'];
				$arrReturn = [
					"cep" 			=> $response['cep'] ?? null,
					"state" 		=> $response['state'] ?? null,
					"city" 			=> $response['city'] ?? null,
					"neighborhood"	=> $response['neighborhood'] ?? null,
					"street" 		=> $response['street'] ?? null,
					"location" 		=> [
						"lat" => $response['location']['coordinates']['latitude'] ?? null,
						"lng" => $response['location']['coordinates']['longitude'] ?? null
					]
				];

				return $arrReturn;
			}else{
				return false;
			}
		}
	}

	public function BrasilApiBanks($bank = null){
		if(empty($bank)){
			$request = Http::request("GET", "https://brasilapi.com.br/api/banks/v1", [], []);

			if($request['code'] == 200){
				return $request['response'];
			}else{
				return false;
			}
		}else{
			$bank = preg_replace("/[^0-9]/", "", $bank);

			$request = Http::request("GET", "https://brasilapi.com.br/api/banks/v1/" . $bank , [], []);

			if($request['code'] == 200){
				return $request['response'];
			}else{
				return false;
			}
		}
	}

	public function BrasilApiTax($tax = null){
		if(empty($tax)){
			$request = Http::request("GET", "https://brasilapi.com.br/api/taxas/v1", [], []);

			if($request['code'] == 200){
				return $request['response'];
			}else{
				return false;
			}
		}else{
			$request = Http::request("GET", "https://brasilapi.com.br/api/taxas/v1/" . $tax, [], []);

			if($request['code'] == 200){
				return $request['response'];
			}else{
				return false;
			}
		}
	}

}
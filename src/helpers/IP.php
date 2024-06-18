<?php

namespace Psf\Helper;

use \Psf\Http\Http;

class IP{
	protected static $url = "http://www.geoplugin.net/json.gp?ip=";

	public static function getInfo(string|null $ip = null) : array{
		$framework = true;

		if(empty($ip)){
			$client = @$_SERVER['HTTP_CLIENT_IP'];
			$forward = @$_SERVER['HTTP_X_FORWARDED_FOR'];
			$remote = $_SERVER['REMOTE_ADDR'];

			if(filter_var($client, FILTER_VALIDATE_IP)){
				$ip = $client;
			}else if(filter_var($forward, FILTER_VALIDATE_IP)){
				$ip = $forward;
			}else{
				$ip = $remote;
			}
		}else{
			$framework = false;
		}

		$request = Http::request("GET", self::$url . $ip, [], []);

		if(isset($request['response'])){
			$responseIp = $request['response'];

			if(isset($responseIp['geoplugin_status'])){
				if($responseIp['geoplugin_status'] == 200 || $responseIp['geoplugin_status'] == 206){
					return [
						"framework" => $framework,
						"ip" => [
							"send" => $ip,
							"apiFind" => $responseIp['geoplugin_request'],
							"changed" => $ip != $responseIp['geoplugin_request'] ? true : false
						],
						"geolocation" => [
							"city" => $responseIp['geoplugin_city'],
							"region" => [
								"identify" => $responseIp['geoplugin_region'],
								"name" => $responseIp['geoplugin_regionName'],
								"code" => $responseIp['geoplugin_regionCode']
							],
							"country" => [
								"name" => $responseIp['geoplugin_countryName'],
								"code" => $responseIp['geoplugin_countryCode']
							],
							"coordenates" => [
								"lat" => $responseIp['geoplugin_latitude'],
								"lng" => $responseIp['geoplugin_longitude'],
								"accuracyRadius" => $responseIp['geoplugin_locationAccuracyRadius']
							]
						],
						"timezone" => $responseIp['geoplugin_timezone'],
						"currency" => [
							"code" => $responseIp['geoplugin_currencyCode'],
							"symbol" => $responseIp['geoplugin_currencySymbol_UTF8']
						]
					];
				}
			}
		}
		return [];
	}


	public static function getIp(){
		$ip = null;

		$client = @$_SERVER['HTTP_CLIENT_IP'];
		$forward = @$_SERVER['HTTP_X_FORWARDED_FOR'];
		$remote = $_SERVER['REMOTE_ADDR'];

		if(filter_var($client, FILTER_VALIDATE_IP)){
			$ip = $client;
		}else if(filter_var($forward, FILTER_VALIDATE_IP)){
			$ip = $forward;
		}else{
			$ip = $remote;
		}

		return $ip;
	}
}
<?php

namespace Prospera\Api;

use \Pgf\Http\StatusCode;
use \Pgf\Http\Http;

#[Attribute]
class Router{
	private array 	$routes 	= [];
	private ?string $method 	= null;
	private ?int 	$version 	= 0;
	private ?int 	$pieces 	= 0;
	private ?array 	$piecesArr 	= [];
	private ?array 	$fields 	= [];
	private static  $patterns  	= [
		'UUID4' 	=> "/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/i",
	];

	public function __construct(...$args){
		$this->method = filter_input(\INPUT_SERVER, 'REQUEST_METHOD', \FILTER_SANITIZE_SPECIAL_CHARS);

		$this->getRoutesList();
	}

	private function getRoutesList(){
		function getMethodsOnTheController(string $file){
			$explodeFileName = explode("/", str_replace(\PGF::getConfig()->pgf['controllers'], '', $file));
            $getControllerName = $explodeFileName[count($explodeFileName) - 1];
            $replaceName = str_replace(".php", "", $getControllerName);

            $className = "\\App\\Controllers\\";
            foreach($explodeFileName as $key => $item){
            	if(!empty($item) && $key != (count($explodeFileName) - 1)){
            		$className .= "{$item}\\";
            	}
            }
            $className .= "{$replaceName}";

            if(!class_exists($className)){
                // explodeException();
                return;
            }
            $reflectionClass = new \ReflectionClass($className);

            $methods = array_filter($reflectionClass->getMethods(), function ($method) use ($reflectionClass) {
                return $method->getDeclaringClass()->getName() === $reflectionClass->getName();
            });

            foreach ($methods as $method) {
                $methodItem = [
                	'class'	=> $reflectionClass->getName(),
                	'name'	=> $method->getName()
                ];

                $reflectionMethod 	= new \ReflectionMethod($className, $method->getName());
                $attributes 		= $reflectionMethod->getAttributes();

                foreach ($attributes as $attribute) {
                	if($attribute->getName() == Router::class){
	                	$methodItem['attributes'] = [
                			'name' 			=> $attribute->getName(),
                			'arguments'		=> $attribute->getArguments()
	                	];

		                $methodsMap[] = $methodItem;
		            }
                }
            }

            return $methodsMap ?? [];
		}

		function mapPathRoutes(string $path){
			$arrListController = [];
			$files = glob($path . '/*');

			foreach($files as $file){
				if(is_file($file)){
		            $arrListController[] = getMethodsOnTheController($file);
		        }else{
		        	$return = mapPathRoutes($file);
		        	if(!empty($return)){
		        		foreach ($return as $finalFile){
		        			array_push($arrListController, $finalFile);
		        		}
		        	}
		        }
			}

			return $arrListController;
		}

        $this->setRoutes(mapPathRoutes(\PGF::getConfig()->pgf['controllers']));
	}

	private function setRoutes(array $arr){
		foreach($arr as $controller){
			foreach ($controller as $methods) {
				$this->routes[] = $methods;
			}
		}
	}

	private function setRoute(array $route){
		$this->routes[] = $route;
	}

	private function callMethodRoute(){
		$urlFind = $_GET['_url'];

		if(substr($urlFind, 0, 1) == '/'){
			$urlFind = substr($urlFind, 1);
		}
		
		if(substr($urlFind, -1) == '/'){
			$urlFind = substr($urlFind, 0, -1);
		}

		if(substr($urlFind, 0, 1) == 'v' && is_numeric(substr($urlFind, 1, 1))){
			$this->version = (int) substr($urlFind, 1, 1);
			$urlFind = substr($urlFind, 3);
		}

		$explodeUrl = explode("/", $urlFind);
		$this->piecesArr = $explodeUrl;
		$j = count($explodeUrl);

		$filterMetch = array_filter($this->routes, function($item){
		    return $item['attributes']['arguments']['method'] == $this->method;
		});

		if(!empty($this->version)){
			$filterMetch = array_filter($filterMetch, function($item){
			    return $item['attributes']['arguments']['version'] == $this->version;
			});
		}

		$this->pieces = empty($explodeUrl[0]) ? count($explodeUrl) - 1 : count($explodeUrl);

		$filterMetch = array_filter($filterMetch, function($item){
			return count(explode("/", $item['attributes']['arguments']['path'])) == $this->pieces;
		});

		$filterMetch = array_filter($filterMetch, function($item){
			$explodeFields 	= explode("/", $item['attributes']['arguments']['path']);

			$check = true;
			for ($i=0; $i < $this->pieces; $i++) {
				if(substr($explodeFields[$i], 0, 1) == "{" && substr($explodeFields[$i], -1) == "}"){
					$getExpression = explode(":", substr($explodeFields[$i], 1, -1))[1];

					if(isset(self::$patterns[$getExpression]) && !empty(self::$patterns[$getExpression])){
						if(!preg_match(self::$patterns[$getExpression], $this->piecesArr[$i])){
							$check = false;
							break;
						}
					}else if(!preg_match("/" . $getExpression . "/", $this->piecesArr[$i])){
						$check = false;
						break;
					}
				}else{
					if($explodeFields[$i] != $this->piecesArr[$i]){
						$check = false;
						break;
					}
				}
			}

			if($check){
				return $item;
			}	
		});

		if(empty($filterMetch) || count($filterMetch) > 1){
			throw new \Exception("Não foi possível encontrar a rota correspondente.");
		}else{
			$filterMetch = (reset($filterMetch));

			if(is_callable([new $filterMetch['class'], $filterMetch['name']])){
				if(isset($filterMetch['attributes']['arguments']['middlewares']) && is_array($filterMetch['attributes']['arguments']['middlewares']) && in_array('authentication', $filterMetch['attributes']['arguments']['middlewares'])){
					$verifyAuth = \PGF::getConfig()->pgf['verifyauth'] ?? false;

					if(!empty($verifyAuth) && $verifyAuth != false){
						$objVerify = new $verifyAuth[0];
						if(is_callable([$objVerify, $verifyAuth[1]])){
							$doValid = call_user_func([$objVerify, $verifyAuth[1]]);
							if($doValid !== true){
								if(is_array($doValid)){
									Http::response("Erro ao validar a autenticação", $doValid, StatusCode::UNAUTHORIZED);
								}else{
									throw new \Exception(StatusCode::UNAUTHORIZED);
								}							
							}
						}
					}else{
						throw new \Exception(StatusCode::UNAUTHORIZED);
					}
				}

				$this->validFields($filterMetch);

				$return = call_user_func_array([new $filterMetch['class'], $filterMetch['name']], $this->fields);
					
				if(isset($filterMetch['attributes']['arguments']['middlewares']) && is_array($filterMetch['attributes']['arguments']['middlewares']) && in_array('loggin', $filterMetch['attributes']['arguments']['middlewares']) && !in_array('webview', $filterMetch['attributes']['arguments']['middlewares'])){
					$logRequest = \PGF::getConfig()->pgf['logrequest'] ?? false;
					if(!empty($logRequest) && $logRequest != false){
						$logObj = new $logRequest[0];
						if(is_callable([$logObj, $logRequest[1]])){
							call_user_func_array([$logObj, $logRequest[1]], [$urlFind, $this->getBody(), apache_request_headers() ?? [], $return[1] ?? $return, $return[2] ?? null]);
						}
					}
				}

				return $return;
			}
		}
		throw new \Exception(StatusCode::NOT_FOUND);
	}

	private function validFields(array $item) : void{
		$explodeFields 	= explode("/", $item['attributes']['arguments']['path']);
		foreach($explodeFields as $key => $field){
			if(substr($field, 0, 1) == "{" && substr($field, -1) == "}"){
				$this->fields[explode(":", str_replace(["{", "}"], "", $field))[0]] = $this->piecesArr[$key];
			}
		}
	}

	private function getBody(){
		$data = [];

		$contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : (isset($_SERVER['HTTP_CONTENT_TYPE']) ? $_SERVER['HTTP_CONTENT_TYPE'] : null);

		if (!empty($content)) {
		    if ($contentType === "application/json") {
		        $requestData = json_decode(file_get_contents('php://input'), true);
		        $data = array_merge($data ?? [], $requestData ?? []);
		    } else {
		        $data = array_merge($data ?? [], $_POST ?? []);
		    }
		} else {
		    $requestData = json_decode(file_get_contents('php://input'), true);
		    $data = array_merge($data ?? [], $requestData ?? []);
		}

		$data = array_merge($data ?? [], $_GET);
		unset($data['_url']);

		return $data;
	}

	public function handle(){
		return $this->callMethodRoute();
	}
}
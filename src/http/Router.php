<?php

namespace Psf\Http;

use \Psf\Http\{StatusCode, Http};

#[Attribute]
class Router{
	private array 	$routes 	= [];
	private ?string $method 	= null;
	private ?int 	$version 	= 0;
	private ?int 	$pieces 	= 0;
	private ?array 	$piecesArr 	= [];
	private ?array 	$fields 	= [];
	public static 	$auth 		= [];
	private static  $patterns  	= [
		'UUID4' 	=> "/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/i",
	];

	public function __construct(...$args){
		$this->method = filter_input(\INPUT_SERVER, 'REQUEST_METHOD', \FILTER_SANITIZE_SPECIAL_CHARS);

		$this->getRoutesList();
	}

	private function getRoutesList(){
		function getMethodsOnTheController(string $file){
			$explodeFileName = explode("/", str_replace(\PSF::getConfig()->settings['controllers'], '', $file));
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

        $this->setRoutes(mapPathRoutes(\PSF::getConfig()->settings['controllers']));
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

	private function clearUrl(string $url) : string{
		if(substr($url, 0, 1) == '/'){
			$url = substr($url, 1);
		}
		
		if(substr($url, -1) == '/'){
			$url = substr($url, 0, -1);
		}

		return $url;
	}

	private function saveLoggin(string $url, array $route, null|array $response = []){
		$middlewares = isset($route['attributes']['arguments']['middlewares']) && is_array($route['attributes']['arguments']['middlewares']) ? $route['attributes']['arguments']['middlewares'] : [];

		if(in_array('loggin', $middlewares) && !in_array('webview', $middlewares)){
			$logRequest = \PSF::getConfig()->settings['logrequest'] ?? FALSE;
			if(!empty($logRequest) && $logRequest != FALSE){
				$logObj = new $logRequest[0];
				if(is_callable([$logObj, $logRequest[1]])){
					call_user_func_array([$logObj, $logRequest[1]], [
						'endpoint'	=> $url,
						'version'	=> $route['attributes']['arguments']['version'],
						'method'	=> $route['attributes']['arguments']['method'],
						'body'		=> $this->getBody(),
						'headers'	=> apache_request_headers() ?? [], 
						'response' 	=> isset($response[1]) && !empty($response[1]) ? [
							'message' 	=> $response[0],
							'data'		=> $response[1],
						] : [
							'message' => $response[0]
						],
						'httpCode' 	=> $response[2] ?? StatusCode::OK,
					]);
				}
			}
		}
	}

	// private function callMethodRoute(){
	// 	$urlFind = self::clearUrl($_GET['_url']);

	// 	if(substr($urlFind, 0, 1) == 'v' && is_numeric(substr($urlFind, 1, 1))){
	// 		$this->version = (int) substr($urlFind, 1, 1);
	// 		$urlFind = substr($urlFind, 3);
	// 	}

	// 	$explodeUrl = explode("/", $urlFind);
	// 	$this->piecesArr = $explodeUrl;
	// 	$j = count($explodeUrl);

	// 	$filterMetch = array_filter($this->routes, function($item){
	// 	    return $item['attributes']['arguments']['method'] == $this->method;
	// 	});

	// 	if(!empty($this->version)){
	// 		$filterMetch = array_filter($filterMetch, function($item){
	// 		    return $item['attributes']['arguments']['version'] == $this->version;
	// 		});
	// 	}

	// 	$this->pieces = empty($explodeUrl[0]) ? count($explodeUrl) - 1 : count($explodeUrl);

	// 	$filterMetch = array_filter($filterMetch, function($item){
	// 		return count(explode("/", $item['attributes']['arguments']['path'])) == $this->pieces;
	// 	});

	// 	$filterMetch = array_filter($filterMetch, function($item){
	// 		$explodeFields 	= explode("/", $item['attributes']['arguments']['path']);

	// 		$check = true;
	// 		for ($i=0; $i < $this->pieces; $i++) {
	// 			if(substr($explodeFields[$i], 0, 1) == "{" && substr($explodeFields[$i], -1) == "}"){
	// 				$getExpression = explode(":", substr($explodeFields[$i], 1, -1))[1];

	// 				if(isset(self::$patterns[$getExpression]) && !empty(self::$patterns[$getExpression])){
	// 					if(!preg_match(self::$patterns[$getExpression], $this->piecesArr[$i])){
	// 						$check = false;
	// 						break;
	// 					}
	// 				}else if(!preg_match("/" . $getExpression . "/", $this->piecesArr[$i])){
	// 					$check = false;
	// 					break;
	// 				}
	// 			}else{
	// 				if($explodeFields[$i] != $this->piecesArr[$i]){
	// 					$check = false;
	// 					break;
	// 				}
	// 			}
	// 		}

	// 		if($check){
	// 			return $item;
	// 		}	
	// 	});

	// 	if(empty($filterMetch) || count($filterMetch) > 1){
	// 		throw new \Exception("Não foi possível encontrar a rota correspondente.");
	// 	}else{
	// 		$filterMetch = (reset($filterMetch));

	// 		if(is_callable([new $filterMetch['class'], $filterMetch['name']])){
	// 			$middlewares = isset($filterMetch['attributes']['arguments']['middlewares']) && is_array($filterMetch['attributes']['arguments']['middlewares']) ? $filterMetch['attributes']['arguments']['middlewares'] : [];

	// 			if(in_array('authentication', $middlewares)){
	// 				$verifyAuth = \PSF::getConfig()->settings['verifyauth'] ?? FALSE;

	// 				if(!empty($verifyAuth) && $verifyAuth !== FALSE){
	// 					$objVerify = new $verifyAuth[0];
	// 					if(is_callable([$objVerify, $verifyAuth[1]])){
	// 						$doValid = call_user_func([$objVerify, $verifyAuth[1]]);
	// 						if(is_object($doValid) || $doValid === TRUE){
	// 							self::$auth = $doValid;			
	// 						}else{
	// 							$return = ["Erro ao validar a autenticação", (is_bool($doValid) ? NULL : (is_array($doValid) ? $doValid : ['msg' => $doValid])), StatusCode::UNAUTHORIZED];
	// 							$this->saveLoggin($urlFind, $filterMetch, $return);

	// 							Http::response("Erro ao validar a autenticação", (is_bool($doValid) ? NULL : (is_array($doValid) ? $doValid : ['msg' => $doValid])), StatusCode::UNAUTHORIZED);
	// 							throw new \Exception(StatusCode::UNAUTHORIZED);
	// 						}
	// 					}
	// 				}else{
	// 					throw new \Exception(StatusCode::UNAUTHORIZED);
	// 				}
	// 			}

	// 			$this->validFields($filterMetch);

	// 			try{
	// 				$return = call_user_func_array([new $filterMetch['class'], $filterMetch['name']], $this->fields);
	// 				$this->saveLoggin($urlFind, $filterMetch, $return);
	// 				return $return;
	// 			}catch (Exception $e) {					
	// 				throw new \Exception(StatusCode::NOT_FOUND);
	// 			}
	// 		}
	// 	}
	// 	throw new \Exception(StatusCode::NOT_FOUND);
	// }

	private function callMethodRoute(){
		$urlFind = self::clearUrl($_GET['_url']);
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
				$middlewares = isset($filterMetch['attributes']['arguments']['middlewares']) && is_array($filterMetch['attributes']['arguments']['middlewares']) ? $filterMetch['attributes']['arguments']['middlewares'] : [];
				foreach ($middlewares as $middlewareClass) {
					if ($middlewareClass == "authentication") {
						$this->authMiddleware($urlFind, $filterMetch);
					} else if($middlewareClass){
						$className = "\\App\\Middlewares\\{$middlewareClass}";
						if(!class_exists($className)){
							throw new \Exception(StatusCode::NOT_FOUND);
						}
						$middlewareInstance = new $className();
						if (method_exists($middlewareInstance, 'handle')) {
							$response = $middlewareInstance->handle($_REQUEST, function($req) {
								return true; 
							});
							if ($response !== true) {
								throw new \Exception($response["mensagem"] ?? NULL,  StatusCode::NOT_FOUND);
							}
						}
					}
				}
				$this->validFields($filterMetch);
				try {
					$return = call_user_func_array([new $filterMetch['class'], $filterMetch['name']], $this->fields);
					$this->saveLoggin($urlFind, $filterMetch, $return);
					return $return;
				} catch (Exception $e) {
					throw new \Exception(StatusCode::NOT_FOUND);
				}
			}
		}
		throw new \Exception(StatusCode::NOT_FOUND);
	}

	function authMiddleware($urlFind, $filterMetch) {
		$verifyAuth = \PSF::getConfig()->settings['verifyauth'] ?? FALSE;
		if(!empty($verifyAuth) && $verifyAuth !== FALSE){
			$objVerify = new $verifyAuth[0];
			if(is_callable([$objVerify, $verifyAuth[1]])){
				$doValid = call_user_func([$objVerify, $verifyAuth[1]]);
				if(is_object($doValid) || $doValid === TRUE){
					self::$auth = $doValid;			
				}else{
					$return = ["Erro ao validar a autenticação", (is_bool($doValid) ? NULL : (is_array($doValid) ? $doValid : ['msg' => $doValid])), StatusCode::UNAUTHORIZED];
					$this->saveLoggin($urlFind, $filterMetch, $return);

					Http::response("Erro ao validar a autenticação", (is_bool($doValid) ? NULL : (is_array($doValid) ? $doValid : ['msg' => $doValid])), StatusCode::UNAUTHORIZED);
					throw new \Exception(StatusCode::UNAUTHORIZED);
				}
			}
		}else{
			throw new \Exception(StatusCode::UNAUTHORIZED);
		}
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
		if(isset(\PSF::getConfig()->settings['docsapi'])){
			$docsApiUrl = \PSF::getConfig()->settings['docsapi'];


			if(self::clearUrl($_GET['_url']) === $docsApiUrl){
				self::generateDocsAPI($this);
				die;
			}
		}

		return $this->callMethodRoute();
	}

	public static function generateDocsAPI($router){
		$itens = [];

		$getRoutesDocs = array_filter($router->routes, function($item){
			if(isset($item['attributes']['arguments']['docs'])){
				return $item;
			}
			return false;
		});

		// echo "<pre>";
		// var_dump($getRoutesDocs);
		// die;

		if($getRoutesDocs){
			foreach($getRoutesDocs as $item){
				$arrAdd = array_merge([
					'method'		=> $item['attributes']['arguments']['method'],
					'uri'			=> $item['attributes']['arguments']['path'],
					'version'		=> $item['attributes']['arguments']['version'],
					'protected'		=> in_array('authentication', $item['attributes']['arguments']['middlewares']),
				], $item['attributes']['arguments']['docs']);

				if(!isset($arrAdd['title']) || empty($arrAdd['title'])){
					$arrAdd['title'] = $item['name'];
				}

				$itens[] = $arrAdd;
			}
		}

		$htmlContent = '<!DOCTYPE html>
		<html lang="pt-br">
			<head>
				<meta charset="UTF-8">
            	<title>Documentação da API</title>
     			<meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
	            <link href="https://cdn.porglin.com/css/bootstrap/bootstrap.min.css" rel="stylesheet" type="text/css">
	            <link href="https://cdn.porglin.com/css/pg/pg.css" rel="stylesheet" type="text/css">
	            <link href="https://cdn.porglin.com/css/fonts/icons.min.css" rel="stylesheet" type="text/css">
            	<link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800&display=swap" rel="stylesheet" type="text/css">

            	<link href="" rel="shortcut icon" type="image/x-icon">
            
            	<style>
            		.headerDocs{
            			background: #ddd;
            			height: 72.5px;
            		}

            		.contentFinal{
            			height: calc(100vh - 72.5px);
            			overflow-y: auto;
            		}

            		.sidebar{
            			background: #eee;
            			width: 400px;
            			height: calc(100vh - 72.5px);
            		}

            		.sidebar .searchBar{
            			// background: #000;
            			height: 75px;
            		}

            		.sidebar .contentRequestList{
            			// background: blue;
            			height: calc(100vh - 147.5px);
            			overflow-y: auto;
            		}

            		.requestPlayground{
            			background: #2B2D34;
            			width: 35vw;
            			border-bottom: 2px solid #3A3D47;
            		}

            		.itemRequest .docReal{
            			border-bottom: 2px dashed #ddd;
            			padding-bottom: 30px;
            		}

            		.itemRequest .docReal .name{
            			font-weight: 700;
            			padding: 0px;
            			margin-left: 30px;
            			margin-top: 20px;
            			font-size: 1.2rem;
            			color: #444;
            			height: 30px;
            			line-height: 30px;
            		}

            		.itemRequest .docReal .methodLabel{
            			height: 25px;
            			background: #eee;
            			margin-right: 30px;
            			margin-top: 20px;
            			border-radius: 5px;
            			padding: 0px 10px 0px 10px;
            			text-transform: uppercase;
            			font-weight: 700;
            			font-size: 0.8rem;
            			line-height: 25px;
            			color: #FFF;
            		}

            		.itemRequest .docReal .contentType{
            			height: 25px;
            			// background: #eee;
            			margin-right: 30px;
            			margin-top: 22.5px;
            			border-radius: 5px;
            			padding: 0px;
            			// text-transform: uppercase;
            			font-weight: 600;
            			font-size: 0.85rem;
            			line-height: 20px;
            			color: #666;
            			user-select: all;
            		}

            		.itemRequest .docReal .methodLabel.put{
            			background: #ff9a29;
            		}

            		.itemRequest .docReal .methodLabel.get{
            			background: #6058ff;
            		}

            		.itemRequest .docReal .methodLabel.post{
            			background: #35b062;
            		}

            		.itemRequest .docReal .methodLabel.delete{
            			background: red;
            		}

            		.itemRequest .docReal .description{
            			padding: 0px;
            			margin: 15px 30px 0px 30px;
            			font-size: 0.9rem;
            			color: #666;
            		}

            		.itemRequest .docReal .description span{
            			color: #315ebc;
            			font-weight: 600;
            		}

            		.itemRequest .docReal .uri{
            			background: #eee;
            			margin: 20px 30px 0px 30px;
            			border-radius: 5px;
            		}

            		.itemRequest .docReal .uri .methodLabel{
            			margin: 10px 0px 10px 10px;
            		}

            		.itemRequest .docReal .uri .final{
            			padding: 0px;
            			margin-left: 15px;
            			line-height: 22.5px;
            			color: #666;
            			font-weight: 500;
            			font-size: 0.9rem;
            			user-select: all;
            			margin-top: 10px;
            		}

            		.itemRequest .docReal .uri .iconCopy{
            			line-height: 32.5px;
            			margin-top: 5px;
            			padding: 0px;
            			font-size: 1.2rem;
            			color: #315ebc;
            			cursor: pointer;
            			margin-right: 12.5px;
            		}

            		.itemRequest .docReal .titleSect{
            			padding: 0px;
            			margin-left: 30px;
            			font-weight: 600;
            			font-size: 1.1rem;
            			color: #666;
            		}

            		.itemRequest .docReal .paramnsList{
            			margin-top: 25px;
            		}

            		.itemRequest .docReal .paramnsList .finalList{
            			border: 1px solid #eee;
            			border-radius: 5px;
            			margin-left: 30px;
            			margin-right: 30px;
            			margin-top: 7.5px;
            		}

            		.itemRequest .docReal .paramnsList .finalList .itemFinal{
            			border-top: 1px dashed #ddd;
            			min-height: 50px;
            			padding-bottom: 15px;
            		}

            		.itemRequest .docReal .paramnsList .finalList .row:last-of-type .itemFinal{
            			border-bottom: 0px;
            		}

            		.itemRequest .docReal .paramnsList .finalList .itemFinal .descriptionField{
            			padding: 0px;
            			width: 50%;
            			font-size: 0.85rem;
            			margin: 15px 15px 0px 15px;
            		}

            		.itemRequest .docReal .paramnsList .finalList .itemFinal .descriptionField span{
            			font-weight: 500;
            			color: #315ebc;
            		}

            		.itemRequest .docReal .paramnsList .finalList .itemFinal .nameField{
            			padding: 0px;
            			margin-left: 15px;
            			margin-top: 15px;
            			font-weight: 600;
            			font-size: 0.925rem;
            			color: #315ebc;
            			height: 25px;
            			line-height: 25px;
            			user-select: all;
            		}

            		.itemRequest .docReal .paramnsList .finalList .itemFinal .typeField{
            			font-size: 0.85rem;
            			margin-top: 15px;
            			height: 25px;
            			line-height: 25px;
            			padding: 0px;
            			margin-left: 12.5px;
            		}

            		.itemRequest .docReal .paramnsList .finalList .itemFinal .requiredField{
            			padding: 0px;
            			margin-left: 12.5px;
            			margin-top: 15px;
            			font-size: 0.7rem;
            			font-weight: 600;
            			color: red;
            			line-height: 25px;
            			height: 25px;
            		}

            		.itemRequest .docReal .paramnsList .finalList .itemFinal .childList{
            			border-left: 3px solid #eee;
            			margin-left: 15px;
            			margin-top: 15px;
            			border-top: 1px dashed #ddd;
            			border-top-left-radius: 5px;
            			border-bottom-left-radius: 5px;
            		}

            		.itemRequest .docReal .paramnsList .finalList .itemFinal .childList .childItem{
            			// background: green;
            			padding-bottom: 7.5px;
            			border-bottom: 1px dashed #ddd;
            		}

            		// .itemRequest .docReal .paramnsList .finalList .itemFinal .childList > .row:last-of-type .childItem{
            		// 	// background: blue;
            		// 	padding-bottom: 0px;
            		// }

            		.itemRequest .docReal .paramnsList .finalList .itemFinal .childList .childItem .nameField{
            			margin-left: 12.5px;
            			margin-top: 7.5px;
            			font-size: 0.875rem;
            			height: 20px;
            			line-height: 20px;
            		}

            		.itemRequest .docReal .paramnsList .finalList .itemFinal .childList .childItem .typeField{
            			font-size: 0.8rem;
            			margin-top: 7.5px;
            			height: 20px;
            			line-height: 20px;
            		}

            		.itemRequest .docReal .paramnsList .finalList .itemFinal .childList .childItem .requiredField{
            			margin-top: 7.5px;
            			height: 20px;
            			line-height: 20px;
            		}

            		.itemRequest .docReal .paramnsList .finalList .itemFinal .childList .childItem .descriptionField{
            			width: calc(50% + 7.5px);
            			font-size: 0.85rem;
            			margin: 7.5px 15px 0px 15px;
            		}

            		// .itemRequest .docReal .paramnsList .finalList .itemFinal .childList > .row:first-of-type .childItem{
            		// 	& .nameField{
            		// 		margin-top: 0px;
            		// 	}

            		// 	& .typeField{
            		// 		margin-top: 0px;
            		// 	}

            		// 	& .descriptionField{
            		// 		margin-top: 0px;
            		// 	}

            		// 	& .requiredField{
            		// 		margin-top: 0px;
            		// 	}
            		// }

            		.itemRequest .docReal .paramnsList .finalList .headerItem{
            			background: #eee;
            			line-height: 40px;
            			height: 40px;
            			text-align: center;
            			text-transform: uppercase;
            			font-size: 0.8rem;
            			font-weight: 600;
            			color: #666;
            			margin-bottom: -1px;
            			z-index: 1;
            		}

            		.itemRequest .docReal .authConfig{
            			margin-top: 30px;
            		}

            		.itemRequest .docReal .authType{
            			margin-top: 5px;
            			border-radius: 5px;
            			background: #eee;
            			margin-left: 30px;
            			margin-right: 30px;
            			line-height: 42.5px;
            		}

            		.itemRequest .docReal .authType .icon{
            			padding: 0px;
            			margin-left: 12.5px;
            			color: #34bf23;
            			font-size: 1.1rem;
            		}

            		.itemRequest .docReal .authType .text{
            			padding: 0px;
            			margin-left: 12.5px;
            			color: #666;
            			font-weight: 500;
            			font-size: 0.9rem;
            		}

            		.itemRequest .docReal .authType span{
            			color: #315ebc;
            			font-weight: 600;
            		}

					.swal-overlay {
						z-index:  99999999999999999999999 !important;
						background: rgba(0,0,0,0.8);
					}

					.swal-modal {
						z-index: 9999999999999999999999999999 !important;
					}

					.swal-text {
						text-align: center;
					}
            	</style>
            </head>
            <body>
	        	<noscript>
					<p>JavaScript desabilitado!</p>
				</noscript>

				<div class="container-fluid">
					<div class="row">
						<div class="col headerDocs">
							Documentação da API
						</div>
					</div>

					<div class="row">
						<div class="col-auto sidebar">
						<div class="row">
						<div class="col searchBar">
						
						</div>
						</div>
						<div class="row">
							<div class="col contentRequestList">

							</div>
						</div>
						</div>
						<div class="col contentFinal">';

						foreach ($itens as $request) {
							preg_match_all('/{([^:]+):([^}]+)}/', $request['uri'], $matches);

							if(isset($matches[1]) && count($matches[1]) > 0){
							    foreach ($matches[1] as $key => $match) {
							        $url = str_replace("{" . $match . ":" . $matches[2][$key] . "}", ":" . $match, $request['uri']);
							    }
							}else{
								$url = $request['uri'];
							}

							$htmlContent .= '<div class="row">
								<div class="col itemRequest">
									<div class="row">
										<div class="col docReal">
											<div class="row">
												<div class="col name">
												' . $request['title'] . '
												</div>

												<div class="col-auto contentType">';

												if(!isset($request['contentType']) || empty($request['contentType'])){
													$htmlContent .= 'Without Content';
												}else{
													$htmlContent .= ($request['contentType'] === 'json' ? 'application/json' : ($request['contentType'] === 'form-data' ? 'multipart/form-data' : $request['contentType']));
												}

												$htmlContent .= '</div>
											</div>';

											if(isset($request['description']) && !empty($request['description'])){
												$htmlContent .= '<div class="row">
													<div class="col description">
													' . $request['description'] . '
													</div>
												</div>';
											}

											$htmlContent .= '<div class="row">
												<div class="col uri">
													<div class="row">
														<div class="col-auto methodLabel ' . strtolower($request['method']) . '">
														' . $request['method'] . '
														</div>
														<div class="col final">
														v' . $request['version'] . '/' . $url . '
														</div>
														<div class="col-auto iconCopy">
														<i class="fal fa-copy"></i>
														</div>
													</div>
												</div>
											</div>';

											if($request['protected']){
												$htmlContent .= '<div class="row">
													<div class="col authConfig">
														<div class="row">
															<div class="col titleSect">
																Autenticação
															</div>
														</div>
														<div class="row">
															<div class="col authType">
																<div class="row">
																	<div class="col-auto icon">
																		<i class="far fa-lock-alt"></i>
																	</div>
																	<div class="col-auto text">';
																	
																	if(!isset($request['authentication']) || empty($request['authentication'])){
																		$htmlContent .= 'Esse endpoint requer autenticação';
																	}else{
																		$explodeAuth = explode('/', $request['authentication']);
																		if(count($explodeAuth) > 1){
																			$makeStringAuth = '';
																			foreach ($explodeAuth as $key => $itemAuth) {
																				$makeStringAuth .= '<span>' . (strtolower($itemAuth) === 'bearer' ? ('Bearer Token') : ((strtolower($itemAuth) === 'basic') ? 'Basic Auth' : '')) . '</span>';

																				if($key < count($explodeAuth) - 1){
																					$makeStringAuth .= ' ou ';
																				}
																			}
																		}else{
																			$makeStringAuth = '<span>' . (strtolower($request['authentication']) === 'bearer' ? ('Bearer Token') : ((strtolower($request['authentication']) === 'basic') ? 'Basic Auth' : '')) . '</span>';
																		}

																		$htmlContent .= 'Esse endpoint requer autenticação com ' . $makeStringAuth;
																	}
																	
																	$htmlContent .= '</div>
																</div>
															</div>
														</div>
													</div>
												</div>';
											}

											if(isset($request['fields']) && count($request['fields']) > 0){
												$htmlContent .= '<div class="row">
													<div class="col paramnsList">
														<div class="row">
															<div class="col titleSect">
																Parâmetros
															</div>
														</div>

														<div class="row">
															<div class="col finalList">';

															$urlComponentsItens = '';
															$urlFieldsItens = '';
															$bodyFieldsItens = '';

															foreach ($request['fields'] as $key => $value) {
																$htmlField = '<div class="row">
																	<div class="col itemFinal">
																		<div class="row">
																			<div class="col-auto nameField">
																				' . $value['name'] . '
																			</div>
																			<div class="col-auto typeField">
																				' . $value['type'] . '
																			</div>';

																			if(isset($value['required']) && $value['required'] === TRUE){
																				$htmlField .= '<div class="col-auto requiredField">
																					OBRIGATÓRIO
																				</div>';
																			}

																			if(isset($value['description']) && !empty($value['description'])){
																				$htmlField .= '<div class="col-auto ml-auto descriptionField">
																					' . $value['description'] . '
																					</div>';
																			}
																		$htmlField .= '</div>';

																		if(isset($value['childs']) && !empty($value['childs'])){
																			$htmlField .= '<div class="row">
																			<div class="col childList">';

																			foreach ($value['childs'] as $child){
																				$htmlField .= '<div class="row">
																				<div class="col childItem">
																				<div class="row">
																				<div class="col-auto nameField">
																					' . $child['name'] . '
																				</div>
																				<div class="col-auto typeField">
																					' . $child['type'] . '
																				</div>';

																				if(isset($child['required']) && $child['required'] === TRUE){
																					$htmlField .= '<div class="col-auto requiredField">
																						OBRIGATÓRIO
																					</div>';
																				}

																				if(isset($child['description']) && !empty($child['description'])){
																					$htmlField .= '<div class="col-auto ml-auto descriptionField">
																						' . $child['description'] . '
																						</div>';
																				}

																				$htmlField .= '</div></div></div>';
																			}

																			$htmlField .= '</div></div>';
																		}

																	$htmlField .= '</div>
																</div>';

																if($value['location'] == 'urlparam'){
																	$urlFieldsItens .= $htmlField;
																}else if($value['location'] == 'body'){
																	$bodyFieldsItens .= $htmlField;
																}else if($value['location'] == 'urlcomponent'){
																	$urlComponentsItens .= $htmlField;
																}
															}

															if(!empty($urlComponentsItens)){
																$htmlContent .= '<div class="row">
																<div class="col headerItem">
																Componentes da URL
																</div>
																</div>';

																$htmlContent .= $urlComponentsItens;
															}

															if(!empty($urlFieldsItens)){
																$htmlContent .= '<div class="row">
																<div class="col headerItem">
																Parametros na URL
																</div>
																</div>';

																$htmlContent .= $urlFieldsItens;
															}

															if(!empty($bodyFieldsItens)){
																$htmlContent .= '<div class="row">
																<div class="col headerItem">
																Parametros no Corpo da Requisição
																</div>
																</div>';

																$htmlContent .= $bodyFieldsItens;
															}

															$htmlContent .= '</div>
														</div>
													</div>
												</div>';
											}

										$htmlContent .= '</div>
										<div class="col-auto requestPlayground">
											Playground
										</div>
									</div>
								</div>
							</div>';
						}

						$htmlContent .= '</div>
					</div>
				</div>
				
				<script src="https://cdn.porglin.com/js/jquery/jquery.min.js"></script>
				<script src="https://cdn.porglin.com/js/bootstrap/bootstrap.min.js"></script>
				<script src="https://cdn.porglin.com/js/sweetalert/sweetalert.min.js"></script>
				<script src="https://cdn.porglin.com/js/pg/pg.js"></script>
				<script>
					$(document).on("click", ".itemRequest .docReal .uri .iconCopy", function(){
						var getUrl = $(this).closest(".uri").find(".final").text().trim();
						pg.copyToClipboard(getUrl);

						swal("URL Copiada!", "A URL da requisição foi copiada para área de transferência", "success", {
	       					 buttons: false,
					        closeOnClickOutside: false,
					        closeOnEsc: false,
					        timer: 2500
				    	});
					});
				</script>
        	</body>
		</html>';
		
		echo $htmlContent;

		die;
	}
}
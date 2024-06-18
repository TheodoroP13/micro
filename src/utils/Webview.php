<?php

namespace Psf\Utils;

use \Psf\Http\Http;
use \Psf\Http\StatusCode;

class Webview{
	public static function renderize(
		string $title  				= '',
		string $view 				= '',
		bool $precontent			= true,
		array|bool $css 			= false,
		array|bool $javascript 		= false,
		array $data 				= [],
		array $html 				= [],
	){
		$themeAssetsPath = \PSF::getConfig()->settings['webviews']['path'] . DR . \PSF::getConfig()->settings['webviews']['template'] . DR . "ThemeAssets.php";
		$themeAssets = file_exists($themeAssetsPath) ? require_once($themeAssetsPath) : FALSE;

		if(!empty($css)){
			$themeAssets['css'] = array_merge($themeAssets['css'], $css);
		}
		if(!empty($javascript)){
			$themeAssets['js'] = array_merge($themeAssets['js'], $javascript);
		}

		$html = array_merge($themeAssets['html'] ?? [], $html);

		$htmlContent = '<!DOCTYPE html>
		<html lang="' . (isset($html['lang']) && !empty($html['lang']) ? $html['lang'] : 'pt-br') . '">
			<head>
				<meta charset="' . (isset($html['charset']) && !empty($html['charset']) ? $html['charset'] : 'UTF-8') . '">
            	<title>' . $title . '</title>
            	';

            	if(isset($html['head']) && !empty($html['head'])){
            		$htmlContent .= '';
	            	foreach($html['head'] as $item){
	            		$htmlContent .= $item;
	            	}
	            }
	            $htmlContent .= '
	            ';
            	foreach($themeAssets['css'] as $file){
            		$htmlContent .= '<link href="' . $file . '" rel="stylesheet" type="text/css">';
            	}

            	if($themeAssets != FALSE && isset($themeAssets['html']['favicon'])){
            		$htmlContent .= '
            	';
            		$htmlContent .= '<link href="' . $themeAssets['html']['favicon'] . '" rel="shortcut icon" type="image/x-icon">';
            	}
            	
				$htmlContent .= '</head>
            <body>

        	<noscript>
				<p>JavaScript desabilitado!</p>
			</noscript>

'; 

			$viewPath = \PSF::getConfig()->settings['webviews']['path'] . DR . \PSF::getConfig()->settings['webviews']['template'] . DR . 'pages' . DR . $view . '.php';

			if(!empty($view) && is_file($viewPath)){
				ob_start();
				require_once($viewPath);
				$viewHtml = ob_get_clean();
			}else{
				$viewHtml = "";
			}

			if($precontent && isset($themeAssets['precontent'])){
				ob_start();
				require_once($themeAssets['precontent']);
				$htmlContent .= str_replace("---CONTENT---", $viewHtml , ob_get_clean());
			}else{
				$htmlContent .= $viewHtml;
			}

			$htmlContent .= '
			';

        	foreach($themeAssets['js'] as $file){
        		$htmlContent .= '<script src="' . $file . '"></script>';
        	}
            $htmlContent .= '</body>
		</html>';

     	echo $htmlContent;
	}
}
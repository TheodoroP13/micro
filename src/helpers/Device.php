<?php

namespace Pgf\Helpers;

class Device{
	public static function getInfo(string|null $agentDescription = null) : array{
		$framework = true;

		if(empty($agentDescription)){
			if(!isset($_SERVER['HTTP_USER_AGENT']) || empty($_SERVER['HTTP_USER_AGENT'])){
				return [];
			}else{
				$agentDescription = $_SERVER['HTTP_USER_AGENT'];
			}
		}else{
			$agentDescription = $agentDescription;
			$framework = false;
		}

		$Agent = new \Jenssegers\Agent\Agent;
		$Agent->setUserAgent($agentDescription);

		return array(
			"operationalSystem"	=> [
				"name" 		=> $Agent->platform(),
				"version" 	=> $Agent->version($Agent->platform()) 
			],
			"browser" 			=> [
				"engine" 	=> $Agent->browser(),
				"version" 	=> $Agent->version($Agent->browser())
			],
			"device" 			=> [
				"name" 		=> $Agent->device(),
				"phone"		=> $Agent->isPhone(),
				"desktop" 	=> $Agent->isDesktop()
			],
			"framework" 		=> $framework
		);
	}
}
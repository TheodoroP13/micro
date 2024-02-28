<?php

namespace Prospera\Utils;

class JWT{
    private static function base64urlEncode($data){
        return str_replace(['+','/','='], ['-','_',''], base64_encode($data));
    }
 
    private static function base64urlDescode($string){
        return base64_decode(str_replace(['-','_'], ['+','/'], $string));
    }
 
    public static function encode(array $payload){
        $header = json_encode([
            "alg" => "HS256",
            "typ" => "JWT"
        ]);
 
        $payload = json_encode($payload);
     
        $header_payload = static::base64urlEncode($header) . '.'. 
                        static::base64urlEncode($payload);
 
        $signature = hash_hmac('sha256', $header_payload, \PSF::getConfig()->jwt['secret'], true);

        return static::base64urlEncode($header) . '.' .
        static::base64urlEncode($payload) . '.' .
        static::base64urlEncode($signature);
    }
 
    public static function decode(string $token, bool $valid){
        $token = explode('.', $token);
        $header = static::base64urlDescode($token[0]);
        $payload = static::base64urlDescode($token[1]);
 
        $signature = static::base64urlDescode($token[2]);
 
        $header_payload = $token[0] . '.' . $token[1];
 
        if($valid){
            if(hash_hmac('sha256', $header_payload, \PSF::getConfig()->jwt['secret'], true) !== $signature){
                return false;
            }
        }
        return json_decode($payload, true);
    }
}
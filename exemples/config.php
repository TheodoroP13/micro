<?php

@date_default_timezone_set('America/Sao_Paulo');
@setlocale(LC_ALL, 'pt_BR');

// use \App\Models\Security\LogApi;
// use \App\Models\User\User;

use \Psf\Enumerators\{DBDriver};

return [
    'project'    => [
        'name'              => 'API',
        'defaulttimezone'   => 'America/Sao_Paulo',
    ],
    'env'       => 'dev', // dev, qa, prd
    'pgf'       => [
        'routes'        => ROOT . '/app/routes/',
        'controllers'   => ROOT . '/app/controllers/',
        'webviews'      => ROOT . '/',
        'logrequest'    => FALSE, // [Instancia do Objeto, método]
        'verifyauth'    => FALSE, // [Instancia do Objeto, método]
        'audittables'   => false, // [Instancia do Objeto, método],
        'debug'         => TRUE,
        'savedbcache'   => FALSE,
    ],
    'app'       => [
        'maxuploadfilesize' => 5
    ], 
    'jwt'       => [
        'secret' => '7e27d5a17c3910138ba66391e266a79762e51240',
    ],
    'db'        => [
        'default'    => [
            'driver'        => DBDriver::MySQL,
            "hostname"      => "porglin.dev",
            "username"      => "prospastec",
            "password"      => "Thw8HoOsnRaV3eMR76BI",
            "database"      => "prospastec",
            "port"          => "3306",
            "extras"        => [
                \PDO::MYSQL_ATTR_INIT_COMMAND   => "SET NAMES utf8",
                // \PDO::MYSQL_ATTR_SSL_CA         => ROOT . "/cacert.pem"
            ]
        ],
        'exported'  => [
            'driver'        => DBDriver::SQLServer,
            'hostname'      => '192.168.1.6',
            'username'      => 'sa',
            'password'      => '89ica@tobix?',
            'database'      => 'portalhml',  
            'extras'        => [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::SQLSRV_ATTR_ENCODING    => \PDO::SQLSRV_ENCODING_UTF8,
            ],
            'savecache'     => TRUE,
        ],
    ],
];
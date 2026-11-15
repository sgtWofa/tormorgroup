<?php

define('DEBUG', true);

define('APP_NAME', 'tormorgroup');
define('APP_DESCRIPTION', 'Your neighbourhood bank');
define('IP_ADDRESS',  $_SERVER['REMOTE_ADDR'] ?? 'unknown');
define('AGENT', $_SERVER['HTTP_USER_AGENT'] ?? 'unknown');

define('WHATSAPP_META_TOKEN', 'your_meta_access_token');
define('WHATSAPP_PHONE_NUMBER_ID', 'your_whatsapp_phone_number_id');
define('WHATSAPP_GRAPH_VERSION', 'v23.0');

if((empty($_SERVER['SERVER_NAME']) && strpos(PHP_SAPI, 'cgi') !== 0) || (!empty($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] == 'localhost'))
{
	/** The name of your database */
    define('DB_NAME', getenv('DB_NAME') ?: 'sgtglob1_Test');

    /** Database username */
    define('DB_USER', getenv('DB_USER') ?: 'root');

    /** Database password */
    define('DB_PASSWORD', getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '');

    /** Database hostname */
    define('DB_HOST', getenv('DB_HOST') ?: 'localhost');

    /** Database driver */
    define('DB_DRIVER', getenv('DB_DRIVER') ?: 'mysql');

    define('ROOT', getenv('APP_ROOT') ?: 'http://localhost:8080');

}else
{
	/** The name of your database */
    define('DB_NAME', getenv('DB_NAME') ?: 'sgtglob1_Test');

    /** Database username */
    define('DB_USER', getenv('DB_USER') ?: 'root');

    /** Database password */
    define('DB_PASSWORD', getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '');

    /** Database hostname */
    define('DB_HOST', getenv('DB_HOST') ?: 'localhost');

    /** Database driver */
    define('DB_DRIVER', getenv('DB_DRIVER') ?: 'mysql');

    define('ROOT', getenv('APP_ROOT') ?: 'http://localhost:8080');
}

// Dynamically set the App logo paths based on the ROOT directory
define('APP_LOGO', ROOT . '/assets/images/loader.gif');
//define('SIGN', ROOT . '/assets/images/signature_headmaster.png');
define('APP_LOGO2', ROOT . '/assets/images/tormorgroup3.png');




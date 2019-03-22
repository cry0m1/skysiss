<?php

//ini_set('mysql.connect_timeout', 300);
//ini_set('default_socket_timeout', 300);
//ini_set('opcache.enable_cli', 1);

/* INIT
 *
 *
 *
 *
 */
set_time_limit(0);

defined('ROOT_PATH') || define('ROOT_PATH', realpath(dirname(dirname(dirname(__FILE__)))));
defined('CURR_PATH') || define('CURR_PATH', realpath(dirname(__FILE__)));

set_include_path(implode(PATH_SEPARATOR, array(
    realpath(ROOT_PATH . '/library'),
    get_include_path(),
)));

require_once(dirname(dirname(dirname(__FILE__))) . "/vendor/autoload.php");

require_once "Zend/Loader.php";
Zend_Loader::loadClass('Zend_Loader_Autoloader');
$oAutoloader = Zend_Loader_Autoloader::getInstance();
$oAutoloader->setFallbackAutoloader(true);
require_once(CURR_PATH . "/lib/assist_lib.php");

$aConfig = parse_ini_file(CURR_PATH . '/config.ini');

if (!$aConfig['debug']) {
    ini_set('error_reporting', 0);
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
} else {
    //define('AMQP_DEBUG', true);
}

date_default_timezone_set($aConfig['date_default_timezone_set']);
Zend_Registry::set('$aConfig', $aConfig);

/* Init MEMCACHE */
$oMemcache = new Memcached();
$oMemcache->addServer($aConfig['memcache_server_ip'], $aConfig['memcache_server_port']) or die("Could not connect");
Zend_Registry::set('$oMemcache', $oMemcache);

/* Init DB */
$aDbConfig['host'] = $aConfig['db.host'];
$aDbConfig['dbname'] = $aConfig['db.name'];
$aDbConfig['username'] = $aConfig['db.username'];
$aDbConfig['password'] = $aConfig['db.password'];
$aDbConfig['charset'] = $aConfig['db.charset'];

$oDbAdapter = Zend_Db::factory('PDO_MYSQL', $aDbConfig);
$oDbAdapter->setFetchMode(Zend_Db::FETCH_OBJ);
Zend_Db_Table::setDefaultAdapter($oDbAdapter);
$oDbAdapter->query("SET time_zone = '" . date('P') . "'");
Zend_Registry::set('$oDbAdapter', $oDbAdapter);

/* Init Cache */
$aFrontendOptions = array('caching' => true, 'lifetime' => 7200, 'automatic_serialization' => true);
$aBackendOptions = array(
    'servers' => array(
        array('host' => $aConfig['memcache_server_ip'], 'port' => $aConfig['memcache_server_port'])
    ),
    'compression' => false,
);
$oCache = Zend_Cache::factory('Core', 'Libmemcached', $aFrontendOptions, $aBackendOptions);
Zend_Registry::set('$oCache', $oCache);

/* Init i18n */
$oTranslate = new Zend_Translate(array(
    'adapter' => 'tmx',
    'content' => CURR_PATH . '/i18n/base.tmx',
    'locale' => 'en'));
Zend_Translate::setCache($oCache);
Zend_Registry::set('$oTranslate', $oTranslate);

Zend_Registry::set('$argv', $argv);

/* add_user */
//$argv[1] //action_type
//$argv[2] //uid
//$argv[3] //service_short_name
//$argv[4] //requestor

/* message */
//$argv[1] //action_type
//$argv[2] //uid
//$argv[3] //service_short_name
//$argv[4] //message

register_shutdown_function(function () {
    if ($mException = error_get_last()) {
        error_log('Bootstrap_helper', 3, ROOT_PATH . '/log/assistant_' . date('Y-m-d') . '.log');
        error_log(print_r($mException, true), 3, ROOT_PATH . '/log/assistant_' . date('Y-m-d') . '.log');
        error_log("===================================================\n", 3, ROOT_PATH . '/log/assistant_' . date('Y-m-d') . '.log');
    }
});

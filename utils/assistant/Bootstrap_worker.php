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
require_once(CURR_PATH . "/lib/services/xmess_service.php");
require_once(CURR_PATH . "/lib/services/calc_service.php");
require_once(CURR_PATH . "/lib/services/clock_service.php");
require_once(CURR_PATH . "/lib/services/currency_service.php");
require_once(CURR_PATH . "/lib/services/weather_service.php");
require_once(CURR_PATH . "/lib/services/horo_service.php");
require_once(CURR_PATH . "/lib/services/magic8_service.php");
require_once(CURR_PATH . "/lib/services/hash_service.php");
require_once(CURR_PATH . "/lib/services/habr_service.php");
require_once(CURR_PATH . "/lib/services/stat_service.php");
require_once(CURR_PATH . "/lib/services/tower_service.php");
require_once(CURR_PATH . "/lib/services/news_service.php");
require_once(CURR_PATH . "/lib/services/ssh_service.php");

/* Define RabbitMQ Routings */
defined('API_WORKER_ROUTING') || define('API_WORKER_ROUTING', 'API_WORKER_ROUTING');
defined('SPAMER_WORKER_ROUTING') || define('SPAMER_WORKER_ROUTING', 'SPAMER_WORKER_ROUTING');
defined('USER_OPERATIONS_WORKER_ROUTING') || define('USER_OPERATIONS_WORKER_ROUTING', 'USER_OPERATIONS_WORKER_ROUTING');

/* Define RabbitMQ Queues */
define('API_WORKER_QUEUE', 'API_WORKER_QUEUE');
define('SPAMER_WORKER_QUEUE', 'SPAMER_WORKER_QUEUE');
define('USER_OPERATIONS_WORKER_QUEUE', 'USER_OPERATIONS_WORKER_QUEUE');

$aConfig = parse_ini_file(CURR_PATH . '/config.ini');
Zend_Registry::set('$aConfig', $aConfig);

if (!$aConfig['debug']) {
    ini_set('error_reporting', 0);
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
} else {
    //define('AMQP_DEBUG', true);
}

date_default_timezone_set($aConfig['date_default_timezone_set']);

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

/* Init MEMCACHE */
$oMemcache = new Memcached();
$oMemcache->addServer($aConfig['memcache_server_ip'], $aConfig['memcache_server_port']) or die("Could not connect");
Zend_Registry::set('$oMemcache', $oMemcache);

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

if (!isset($argv[2])) {
    $argv[2] = 0;
}

/* Detect protocol/worker type */
$sType = 'n/a';
if (preg_match('/user_operations_worker/', $argv[0])) {
    $sType = 'USER_OPERATIONS WORKER #' . $argv[1];
} elseif (preg_match('/spamer_worker/', $argv[0])) {
    $sType = 'SPAMER WORKER #' . $argv[1];
} elseif (preg_match('/api_worker/', $argv[0])) {
    $sType = 'API WORKER #' . $argv[1];
}

echo PHP_EOL . $sType . ': restart #' . ++$argv[2] . PHP_EOL;

$_ = '/usr/bin/php';
if (array_key_exists('_', $_SERVER)) {
    if (preg_match('/php/', $_SERVER['_'])) {
        $_ = $_SERVER['_'];
    }
}

register_shutdown_function(function () {
    global $_, $argv;

    if ($mException = error_get_last()) {
        error_log('Bootstrap_worker', 3, ROOT_PATH . '/log/assistant_' . date('Y-m-d') . '.log');
        error_log(print_r($mException, true), 3, ROOT_PATH . '/log/assistant_' . date('Y-m-d') . '.log');
        error_log("===================================================\n", 3, ROOT_PATH . '/log/assistant_' . date('Y-m-d') . '.log');
    }

    // restart myself
    sleep(2);
    pcntl_exec($_, $argv);
});

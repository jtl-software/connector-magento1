<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @copyright 2010-2013 JTL-Software GmbH
 */

require_once (__DIR__ . "/../vendor/autoload.php");

use \jtl\Connector\Magento\Installer\Installer as ConnectorInstaller;

$condir = getenv('APPLICATION_ENV') == 'development' ? __DIR__ . '/../vendor/jtl/connector/' : __DIR__ . '/';
define('CONNECTOR_DIR', $condir);
define('INSTALLER_DIR', __DIR__ . '/');
define('ENDPOINT_DIR', realpath(__DIR__ . '/../'));

$requestURI = $_SERVER['SCRIPT_NAME'];
define('INSTALLER_BASE_URI', '//' . $_SERVER['HTTP_HOST'] . substr($requestURI, 0, strrpos($requestURI, '/')));

// Installer instance
$installer = ConnectorInstaller::getInstance();
$installer->run();

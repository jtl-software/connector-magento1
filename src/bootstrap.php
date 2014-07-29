<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @copyright 2010-2013 JTL-Software GmbH
 */

require_once (__DIR__ . "/../vendor/autoload.php");

use jtl\Connector\Application\Application;
use jtl\Core\Rpc\RequestPacket;
use jtl\Core\Rpc\ResponsePacket;
use jtl\Core\Rpc\Error;
use jtl\Core\Http\Response;
use jtl\Connector\Magento\Connector;

define('CONNECTOR_DIR', __DIR__ . '/../vendor/jtl/connector/');
define('ENDPOINT_DIR', realpath(__DIR__ . '/../'));

function exception_handler(\Exception $exception)
{
    $trace = $exception->getTrace();
    if (isset($trace[0]['args'][0])) {
        $requestpacket = $trace[0]['args'][0];
    }
    
    $error = new Error();
    $error->setCode($exception->getCode())
        ->setData("Exception: " . substr(strrchr(get_class($exception), "\\"), 1) . " - File: {$exception->getFile()} - Line: {$exception->getLine()}")
        ->setMessage($exception->getMessage());

    $responsepacket = new ResponsePacket();
    $responsepacket->setError($error)
        ->setJtlrpc("2.0");
        
    if (isset($requestpacket) && $requestpacket !== null && is_object($requestpacket) && get_class($requestpacket) == "jtl\\Core\\Rpc\\RequestPacket") {
        $responsepacket->setId($requestpacket->getId());
    }
    
    Response::send($responsepacket);
}

set_exception_handler('exception_handler');

// Connector instance
$connector = Connector::getInstance();
$application = Application::getInstance();
$application->register($connector);
$application->run();

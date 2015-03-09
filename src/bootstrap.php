<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @copyright 2010-2013 JTL-Software GmbH
 */

require_once (__DIR__ . "/../vendor/autoload.php");

use jtl\Connector\Application\Application;
use jtl\Connector\Core\Rpc\RequestPacket;
use jtl\Connector\Core\Rpc\ResponsePacket;
use jtl\Connector\Core\Rpc\Error;
use jtl\Connector\Core\Http\Response;
use jtl\Connector\Magento\Connector;

// Get the error handler by pushing a dummy handler on the stack.
// Then, set the real handler wrapping the original.
$mageHandler = set_error_handler(function () {});
set_error_handler(function ($errno, $errstr, $errfile, $errline) use ($mageHandler) {
  if (E_WARNING === $errno
    && 0 === strpos($errstr, 'include(')
    && substr($errfile, -19) == 'Varien/Autoload.php'
  ){
    return null;
  }
  return call_user_func($mageHandler, $errno, $errstr, $errfile, $errline);
});

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

try
{
    // Connector instance
    $connector = Connector::getInstance();
    $application = Application::getInstance();
    $application->register($connector);
    $application->run();
}
catch (\Exception $e)
{
    exception_handler($e);
}

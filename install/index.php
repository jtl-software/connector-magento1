<?php

/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Magento
 */

if (version_compare(PHP_VERSION, '5.3.0', '<')) {
	die('Mindestens PHP 5.3.3 wird benötigt, um den Connector ausführen zu können.');
}
elseif (version_compare(PHP_VERSION, '5.3.3', '<')) {
	die('Sie verwenden zwar PHP 5.3. Allerdings enhält Ihre PHP-Version einige kritische Fehler, mit denen der Connector nicht funktionieren kann. Sie müssen mindestens auf PHP 5.3.3 aktualisieren.');
}

define("APP_DIR", __DIR__);

include (APP_DIR . "/bootstrap.php");

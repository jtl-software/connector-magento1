<?php

/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Magento\Installer
 */
namespace jtl\Connector\Magento\Installer;

use jtl\Connector\Installer\Installer as CoreInstaller;

/**
 * Description of Installer
 *
 * @access public
 * @author Christian Spoo <christian.spoo@jtl-software.de>
 */
class Installer extends CoreInstaller
{
    protected $_textDomain = 'magento-connector';
    
    protected function getInstallSteps()
    {
        $steps = parent::getInstallSteps();

        array_splice($steps, 1, 0, array(
          '\\jtl\\Connector\\Magento\\Installer\\Step\\MappingDatabaseStep',
          '\\jtl\\Connector\\Magento\\Installer\\Step\\StoreViewStep'
        ));
        return $steps;
    }    
}

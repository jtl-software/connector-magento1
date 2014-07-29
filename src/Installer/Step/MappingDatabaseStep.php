<?php

/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Magento\Installer
 */
namespace jtl\Connector\Magento\Installer\Step;

use \jtl\Connector\Installer\Step\FormStep;
use \jtl\Connector\Magento\Mapper\Database as MappingDatabase;

/**
 * Description of MappingDatabaseStep
 *
 * @access public
 * @author Christian Spoo <christian.spoo@jtl-software.com>
 */
class MappingDatabaseStep extends FormStep
{
    protected $_template = 'step_mappingdatabase';

    protected function processFormData()
    {
        // Get SQLite database object
        $database = MappingDatabase::getInstance();
        
        // Initialize DB structures
        $database->initialize();
        
        // Advance to next step
        $this->_installer->advance();
    }

    protected function validateFormData()
    {
        return true;
    }    
}

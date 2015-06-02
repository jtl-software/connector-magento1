<?php

$setup = new Mage_Sales_Model_Resource_Setup('core_setup');

$setup->addAttribute('order_payment', 'jtl_erp_id', array(
    'type'             => 'int',
    'label'            => 'JTL-Wawi-ID',
    'backend_input'    => 'text',
    'frontend_input'   => 'text',
    'visible'          => true,
    'required'         => false,
    'visible_on_front' => true,
    'user_defined'     => false,
    'default'          => 0
));

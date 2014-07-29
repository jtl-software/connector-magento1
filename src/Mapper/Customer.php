<?php

/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Magento
 */
namespace jtl\Connector\Magento\Mapper;

use jtl\Connector\Magento\Mapper\Database as MapperDatabase;

use jtl\Core\Model\QueryFilter;
use jtl\Connector\Magento\Magento;
use jtl\Connector\Model\Customer as ConnectorCustomer;
use jtl\Connector\ModelContainer\CustomerContainer;

/**
 * Description of Customer
 *
 * @access public
 * @author Christian Spoo <christian.spoo@jtl-software.com>
 */
class Customer
{
	public function getAvailableCount()
	{
		Magento::getInstance();

        try {
	        $customerModel = \Mage::getModel('customer/customer');
	        $customerCollection = $customerModel->getCollection()
	            ->addAttributeToSelect('*');

	        return $customerCollection->count();
        }
        catch (Exception $e) {
        	return 0;
        }
	}

	public function pull(QueryFilter $filter = null)
	{
		Magento::getInstance();

        $stores = MapperDatabase::getInstance()->getStoreMapping();

        $customerModel = \Mage::getModel('customer/customer');
        $customerCollection = $customerModel->getCollection()
            ->addAttributeToSelect('*');

        if (!is_null($filter)) {
        	$customerCollection
        		->getSelect()
        		->limit($filter->getLimit(), $filter->getOffset());
        }

        $customerCollection->load();

        // Build result array
        $result = array();
        foreach ($customerCollection as $customerEntry) {
        	$container = new CustomerContainer();

        	$created_at = new \DateTime($customerEntry->created_at);

			$customer = new ConnectorCustomer();
			$customer->_id = $customerEntry->entity_id;
			$customer->_customerGroupId = (int)$customerEntry->group_id;
			$customer->_localeName = array_search($customerEntry->store_id, $stores) ?: key($stores);
			$customer->_customerNumber = NULL;
			$customer->_password = $customerEntry->password_hash;
			$customer->_salutation = NULL;
			$customer->_title = $customerEntry->prefix;
			$customer->_firstName = $customerEntry->firstname;
			$customer->_lastName = $customerEntry->lastname;
			$customer->_company = NULL;
			$customer->_vatNumber = $customerEntry->taxvat;
			$customer->_eMail = $customerEntry->email;
			$customer->_isActive = ($customerEntry->is_active == 1);
			$customer->_hasCustomerAccount = true;
			$customer->_hasNewsletterSubscription = false;
			$customer->_discount = 0.00;
			$customer->_created = $created_at->format('c');

			if (!is_null($customerEntry->default_billing)) {
				$address = $customerEntry->getDefaultBillingAddress();

				$customer->_company = $address->getCompany();
				$customer->_street = implode('', $address->getStreet());
				$customer->_zipCode = $address->getPostcode();
				$customer->_city = $address->getCity();
				$customer->_state = $address->getRegion();
				$customer->_countryIso = $address->getCountryId();
				$customer->_phone = $address->getTelephone();
				$customer->_mobile = NULL;
				$customer->_fax = NULL;
			}

			$container->add('customer', $customer->getPublic(array('_fields')));

			$result[] = $container->getPublic(array('items'), array('_fields'));
        }
        unset($customerCollection);
        
        return $result;
	}
}

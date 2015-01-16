<?php

/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Magento
 */
namespace jtl\Connector\Magento\Mapper;

use jtl\Connector\Magento\Mapper\Database as MapperDatabase;

use jtl\Connector\Core\Model\QueryFilter;
use jtl\Connector\Magento\Magento;
use jtl\Connector\Model\Customer as ConnectorCustomer;
use jtl\Connector\Model\Identity;

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
        	$created_at = new \DateTime($customerEntry->created_at);
            $birthday = new \DateTime($customerEntry->dob);

			$customer = new ConnectorCustomer();
			$customer->setId(new Identity($customerEntry->entity_id));
			$customer->setCustomerGroupId(new Identity($customerEntry->group_id));
			$customer->setLocaleName(array_search($customerEntry->store_id, $stores) ?: key($stores));
			$customer->setCustomerNumber(NULL);
			// $customer->setPassword($customerEntry->password_hash);
            // $customer->setBirthday($birthday);
			$customer->setSalutation(NULL);
			$customer->setTitle($customerEntry->prefix);
			$customer->setFirstName($customerEntry->firstname);
			$customer->setLastName($customerEntry->lastname);
			$customer->setCompany(NULL);
			$customer->setVatNumber($customerEntry->taxvat);
			$customer->setEMail($customerEntry->email);
			$customer->setIsActive(($customerEntry->is_active == 1));
			// $customer->setHasCustomerAccount(true);
			$customer->setHasNewsletterSubscription(false);
			$customer->setDiscount(0.00);
			$customer->setCreationDate($created_at);

			if (!is_null($customerEntry->default_billing)) {
				$address = $customerEntry->getDefaultBillingAddress();

				$customer->setCompany($address->getCompany());
				$customer->setStreet(implode('', $address->getStreet()));
				$customer->setZipCode($address->getPostcode());
				$customer->setCity($address->getCity());
				$customer->setState($address->getRegion());
				$customer->setCountryIso($address->getCountryId());
				$customer->setPhone($address->getTelephone());
				$customer->setMobile(NULL);
				$customer->setFax(NULL);
			}

			$result[] = $customer->getPublic();
        }
        unset($customerCollection);
        
        return $result;
	}
}

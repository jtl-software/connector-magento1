<?php

/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package jtl\Connector\Magento
 */
namespace jtl\Connector\Magento\Mapper;

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
            $customerCollection = \Mage::getModel('customer/customer')
                ->getCollection()
                ->addAttributeToSelect('*')
                ->addAttributeToFilter('jtl_erp_id',
                    array(
                        array('eq' => 0),
                        array('null' => true)
                    ),
                    'left'
                );

            return $customerCollection->count();
        }
        catch (Exception $e) {
            return 0;
        }
    }

	public function pull(QueryFilter $filter = null)
	{
		Magento::getInstance();

        $stores = Magento::getInstance()->getStoreMapping();

        $customerCollection = \Mage::getModel('customer/customer')
            ->getCollection()
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('jtl_erp_id',
                array(
                    array('eq' => 0),
                    array('null' => true)
                ),
                'left'
            );


        if (!is_null($filter)) {
        	$customerCollection
        		->getSelect()
        		->limit($filter->getLimit());
        }

        $customerCollection->load();

        // Build result array
        $result = array();
        foreach ($customerCollection as $customerEntry) {
            $customerGroup = \Mage::getModel('customer/group')
                ->load($customerEntry->group_id);

        	$created_at = new \DateTime($customerEntry->created_at);
            $birthday = new \DateTime($customerEntry->dob);

			$customer = new ConnectorCustomer();
			$customer->setId(new Identity($customerEntry->entity_id, $customerEntry->jtl_erp_id));
			$customer->setCustomerGroupId(new Identity($customerEntry->group_id, $customerGroup->jtl_erp_id));
			$customer->setLanguageIso(
                LocaleMapper::localeToLanguageIso(
                    array_search($customerEntry->store_id, $stores) ?: key($stores)
                )
            );
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

			$result[] = $customer;
        }
        unset($customerCollection);
        
        return $result;
	}
}

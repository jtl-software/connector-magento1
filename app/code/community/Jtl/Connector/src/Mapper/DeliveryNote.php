<?php

namespace jtl\Connector\Magento\Mapper;

use jtl\Connector\Model\DeliveryNote as ConnectorDeliveryNote;

/**
 * Description of DeliveryNote
 *
 * @access public
 * @author Christian Spoo <christian.spoo@jtl-software.com>
 */
class DeliveryNote
{
    public function updateDeliveryNote(ConnectorDeliveryNote $deliveryNote)
    {
        $order = \Mage::getModel('sales/order')
            ->loadByIncrementId($deliveryNote->getCustomerOrderId()->getEndpoint());

        $result = new ConnectorDeliveryNote();
        $result->setCustomerOrderId($deliveryNote->getCustomerOrderId());

        if (!$order->canShip())
            return $result;

        $shipmentItems = array();
        foreach ($order->getAllItems() as $item) {
            $item_id = $item->getItemId();
            $qty = $item->getQtyOrdered();

            $shipmentItems[$item_id] = $qty;
        }

        $shipmentId = \Mage::getModel('sales/order_shipment_api')
            ->create(
                $order->getIncrementId(),
                $shipmentItems,
                'comment',
                false,
                1
            );

        $methods = \Mage::getSingleton('shipping/config')->getAllCarriers();
        array_walk($methods, function(&$method, $code) {
            $method = \Mage::getStoreConfig('carriers/' . $code . '/title');
        });
        $methods = array_flip($methods);

        foreach ($deliveryNote->getTrackingLists() as $trackingList) {
            $shippingMethodCode = $methods[$trackingList->getName()];

            foreach ($trackingList->getCodes() as $code) {
                $tracking = \Mage::getModel('sales/order_shipment_api')
                    ->addTrack(
                        $shipmentId,
                        $shippingMethodCode,
                        $trackingList->getName(),
                        $code
                    );
            }
        }

        return $result;
    }

    public function getAvailableCount()
    {
        return 0;
    }
}

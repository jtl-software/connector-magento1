<?php

class Jtl_Connector_Rss_Block_Catalog_Tag extends Mage_Rss_Block_Catalog_Tag
{
    public function addTaggedItemXml($args)
    {
        $product = $args['product'];
        $product->unsetData()->load($args['row']['entity_id']);
        $description = '<table><tr>'.
        '<td><a href="'.$product->getProductUrl().'"><img src="'. $this->helper('catalog/image')->init($product, 'thumbnail')->resize(75, 75) .'" border="0" align="left" height="75" width="75"></a></td>'.
        '<td  style="text-decoration:none;">'.$product->getDescription().
        ($product->isConfigurable() ? '<p> Price From:' : '<p> Price:').
        Mage::helper('core')->currency($product->getFinalPrice()).'</p>'.
        '</td>'.
        '</tr></table>';
        $rssObj = $args['rssObj'];
        $data = array(
                'title'         => $product->getName(),
                'link'          => $product->getProductUrl(),
                'description'   => $description,
                );
        $rssObj->_addEntry($data);
    }
}

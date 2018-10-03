<?php

class Avestique_UrlRewrite_Helper_Data extends Mage_Core_Helper_Abstract
{
    public function getTypes()
    {
        $value = Mage::getConfig()->getNode('global/filter_product_type_index/values')->asArray();

        return is_array($value) ? $value : null;
    }

    public function useProductCategoryMap()
    {
        return Mage::getConfig()->getNode('global/filter_product_category_index')->__toString();
    }
}
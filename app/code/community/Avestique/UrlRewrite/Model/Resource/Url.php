<?php

class Avestique_UrlRewrite_Model_Resource_Url extends Mage_Catalog_Model_Resource_Url
{

    const URL_REWRITE_CACHE_TAG = 'av_url_rewrite';

    protected $_chunkLimit = 30000;

    /**
     * Limit products for select
     *
     * @var int
     */
    protected $_productLimit = 5000;

    protected $_attributeLimit = 10000;

    protected $_cacheFlag = null;

    protected $_inserts = [];

    protected $_attributeTables = [];

    protected $_attributeInserts = [];

    protected $_historyInserts = [];

    protected $_removed = [];

    protected $cnt = 0;

    protected $_lastInsert = 0;

    /**
     * Save category attribute
     *
     * @param Varien_Object $category
     * @param string $attributeCode
     * @return Mage_Catalog_Model_Resource_Url
     */
    public function saveCategoryAttribute(Varien_Object $category, $attributeCode)
    {
        $adapter = $this->_getWriteAdapter();
        if (!isset($this->_categoryAttributes[$attributeCode])) {
            $attribute = $this->getCategoryModel()->getResource()->getAttribute($attributeCode);

            $this->_categoryAttributes[$attributeCode] = array(
                'entity_type_id' => $attribute->getEntityTypeId(),
                'attribute_id' => $attribute->getId(),
                'table' => $attribute->getBackend()->getTable(),
                'is_global' => $attribute->getIsGlobal()
            );
            unset($attribute);
        }

        $attributeTable = $this->_categoryAttributes[$attributeCode]['table'];

        $attributeDataDefault = array(
            'entity_type_id' => $this->_categoryAttributes[$attributeCode]['entity_type_id'],
            'attribute_id' => $this->_categoryAttributes[$attributeCode]['attribute_id'],
            'store_id' => 0,
            'entity_id' => $category->getId(),
            'value' => $category->getData($attributeCode)
        );

        $attributeData = array(
            'entity_type_id' => $this->_categoryAttributes[$attributeCode]['entity_type_id'],
            'attribute_id' => $this->_categoryAttributes[$attributeCode]['attribute_id'],
            'store_id' => $category->getStoreId(),
            'entity_id' => $category->getId(),
            'value' => $category->getData($attributeCode)
        );

        if (!isset($this->_attributeInserts[$attributeTable])) {
            $this->_attributeInserts[$attributeTable] = [];
        }

        $key = $attributeData['entity_id'] . "|" . $attributeData['attribute_id'] . "|" . 0;

        $this->_attributeInserts[$attributeTable][$key] = $attributeDataDefault;

        if (!$this->_categoryAttributes[$attributeCode]['is_global'] && $category->getStoreId() != 0) {
            $key = $attributeData['entity_id'] . "|" . $attributeData['attribute_id'] . "|" . $attributeData['store_id'];
            $this->_attributeInserts[$attributeTable][$key] = $attributeData;
        }

        if (count($this->_attributeInserts[$attributeTable]) >= $this->_attributeLimit) {
            $adapter->insertOnDuplicate($attributeTable, $this->_attributeInserts[$attributeTable]);
            $this->_attributeInserts[$attributeTable] = [];
        }

        unset($attributeData, $attributeDataDefault);

        return $this;
    }

    /**
     * Save product attribute
     *
     * @param Varien_Object $product
     * @param string $attributeCode
     * @return Mage_Catalog_Model_Resource_Url
     */
    public function saveProductAttribute(Varien_Object $product, $attributeCode)
    {
        /** @var Magento_Db_Adapter_Pdo_Mysql $adapter */
        $adapter = $this->_getWriteAdapter();
        if (!isset($this->_productAttributes[$attributeCode])) {
            $attribute = $this->getProductModel()->getResource()->getAttribute($attributeCode);

            $this->_productAttributes[$attributeCode] = array(
                'entity_type_id' => $attribute->getEntityTypeId(),
                'attribute_id' => $attribute->getId(),
                'table' => $attribute->getBackend()->getTable(),
                'is_global' => $attribute->getIsGlobal()
            );
            unset($attribute);
        }

        $attributeTable = $this->_productAttributes[$attributeCode]['table'];

        $attributeDataDefault = array(
            'entity_type_id' => $this->_productAttributes[$attributeCode]['entity_type_id'],
            'attribute_id' => $this->_productAttributes[$attributeCode]['attribute_id'],
            'store_id' => 0,
            'entity_id' => $product->getId(),
            'value' => $product->getData($attributeCode)
        );

        $attributeData = array(
            'entity_type_id' => $this->_productAttributes[$attributeCode]['entity_type_id'],
            'attribute_id' => $this->_productAttributes[$attributeCode]['attribute_id'],
            'store_id' => $product->getStoreId(),
            'entity_id' => $product->getId(),
            'value' => $product->getData($attributeCode)
        );


        if (!isset($this->_attributeInserts[$attributeTable])) {
            $this->_attributeInserts[$attributeTable] = [];
        }

        $key = $attributeData['entity_id'] . "|" . $attributeData['attribute_id'] . "|" . 0;

        $this->_attributeInserts[$attributeTable][$key] = $attributeDataDefault;

        if (!$this->_productAttributes[$attributeCode]['is_global'] && $product->getStoreId() != 0) {
            $key = $attributeData['entity_id'] . "|" . $attributeData['attribute_id'] . "|" . $attributeData['store_id'];
            $this->_attributeInserts[$attributeTable][$key] = $attributeData;
        }

        if (count($this->_attributeInserts[$attributeTable]) >= $this->_attributeLimit) {
            $adapter->insertOnDuplicate($attributeTable, $this->_attributeInserts[$attributeTable]);
            $this->_attributeInserts[$attributeTable] = [];
        }

        unset($attributeData, $attributeDataDefault);

        return $this;
    }

    public function fetchPairs($row)
    {
        $result = $row[0];
        $row = $row['row'];

        \Mage::app()->getCache()->save($row['data'], $row['request_path'], [
            self::URL_REWRITE_CACHE_TAG
        ]);

        $result->lastnum = $row['url_rewrite_id'];
        $result->cnt++;
    }

    /**
     * @return $this
     */
    public function readPaths($useLastInsert = false)
    {
        if (empty($this->_cacheFlag) || $useLastInsert) {
            /** @var Magento_Db_Adapter_Pdo_Mysql $adapter */
            $adapter = $this->_getWriteAdapter();

            $lastId = $this->_lastInsert ?: 0;

            $done = 0;

            if (!$lastId) {
                \Mage::app()->getCache()->clean();
            }

            do {
                $select = $adapter->select()
                    ->from($this->getMainTable(), [
                        'request_path' => new Zend_Db_Expr("CONCAT(`request_path`,'|',`store_id`)"),
                        'data' => new Zend_Db_Expr("CONCAT(`target_path`,'|',`id_path`)"),
                        'url_rewrite_id'
                    ])
                    ->where('url_rewrite_id > ?', $lastId)
                    ->limit($this->_chunkLimit);

                $result = new stdClass();
                $result->cnt = 0;

                Mage::getSingleton('core/resource_iterator')->walk($select, array(
                    array($this, 'fetchPairs')
                ), [$result]);

                if ($lastId == $result->lastnum) {
                    $this->_lastInsert = $lastId;
                    break;
                }

                $lastId = $result->lastnum;
                $done += $result->cnt;
            } while ($lastId);

            $this->_cacheFlag = true;
        }

        return $this;
    }

    /**
     * Validate array of request paths. Return first not used path in case if validations passed
     *
     * @param array $paths
     * @param int $storeId
     * @return false | string
     */
    public function checkRequestPaths($paths, $storeId)
    {
        $this->readPaths();

        $data = [];

        foreach ($paths as $path) {
            if (Mage::app()->getCache()->load($path . '|' . $storeId)) {
                $data[] = $path;
            } else if (isset($this->_inserts[$path . '|' . $storeId])) {
                $data[] = $path;
            }
        }

        $paths = array_diff($paths, $data);

        if (empty($paths)) {
            return false;
        }
        reset($paths);

        return current($paths);
    }

    /**
     * Saves rewrite history
     *
     * @param array $rewriteData
     * @return Mage_Catalog_Model_Resource_Url
     */
    public function saveRewriteHistory($rewriteData)
    {
        $rewriteData = new Varien_Object($rewriteData);
        // check if rewrite exists with save request_path
        $rewrite = $this->getRewriteByRequestPath($rewriteData->getRequestPath(), $rewriteData->getStoreId());
        if ($rewrite === false) {
            // create permanent redirect
            $this->_historyInserts[] = $rewriteData->getData();

            if (count($this->_historyInserts) >= $this->_productLimit) {
                $this->_getWriteAdapter()->insertOnDuplicate($this->getMainTable(), $this->_historyInserts);
            }
        }

        return $this;
    }

    /**
     * Save rewrite URL
     *
     * @param array $rewriteData
     * @param int|Varien_Object $rewrite
     * @return Mage_Catalog_Model_Resource_Url
     */
    public function saveRewrite($rewriteData, $rewrite)
    {
        $adapter = $this->_getWriteAdapter();

        try {
            if (empty($rewriteData['category_id'])) {
                $this->_inserts[$rewriteData['request_path'] . '|' . $rewriteData['store_id']] = $rewriteData;

                if (count($this->_inserts) >= $this->_productLimit) {
                    $this->cnt++;

                    var_dump($this->cnt * $this->_productLimit);

                    $adapter->insertOnDuplicate($this->getMainTable(), $this->_inserts);

                    $this->readPaths(true);

                    $this->_inserts = [];
                }
            } else {
                $adapter->insertOnDuplicate($this->getMainTable(), $rewriteData);
            }
        } catch (Exception $e) {
            var_dump($e->getMessage());
            Mage::logException($e);
            Mage::throwException(Mage::helper('catalog')->__('An error occurred while saving the URL rewrite'));
        }

        if ($rewrite && $rewrite->getId()) {
            if ($rewriteData['request_path'] != $rewrite->getRequestPath()) {
                // Update existing rewrites history and avoid chain redirects
                $where = array('target_path = ?' => $rewrite->getRequestPath());
                if ($rewrite->getStoreId()) {
                    $where['store_id = ?'] = (int)$rewrite->getStoreId();
                }
                $adapter->update(
                    $this->getMainTable(),
                    array('target_path' => $rewriteData['request_path']),
                    $where
                );
            }
        }
        unset($rewriteData);

        return $this;
    }

    /**
     * @throws Zend_Db_Exception
     */
    public function runLastInserts()
    {
        /** @var Magento_Db_Adapter_Pdo_Mysql $adapter */
        $adapter = $this->_getWriteAdapter();

        foreach ($this->_attributeInserts as $attributeTable) {
            if (count($this->_attributeInserts[$attributeTable])) {
                $adapter->insertOnDuplicate($attributeTable, $this->_attributeInserts[$attributeTable]);
                $this->_attributeInserts[$attributeTable] = [];
            }
        }

        if (count($this->_inserts)) {
            $adapter->insertOnDuplicate($this->getMainTable(), $this->_inserts);
            $this->_inserts = [];
        }

        if (count($this->_historyInserts)) {
            $adapter->insertOnDuplicate($this->getMainTable(), $this->_historyInserts);
            $this->_historyInserts = [];
        }
    }

    /**
     * Find and return final id path by request path
     * Needed for permanent redirect old URLs.
     *
     * @param string $requestPath
     * @param int $storeId
     * @param array $_checkedPaths internal varible to prevent infinite loops.
     * @return string | bool
     */
    public function findFinalTargetPath($requestPath, $storeId, &$_checkedPaths = array())
    {
        if (in_array($requestPath, $_checkedPaths)) {
            return false;
        }

        $this->readPaths();

        $_checkedPaths[] = $requestPath;

        if ($row = Mage::app()->getCache()->load($requestPath . '|' . $storeId)) {
            list($target_path, $id_path_row) = explode('|', $row);

            $idPath = $this->findFinalTargetPath($target_path, $storeId, $_checkedPaths);
            if (!$idPath) {
                return $id_path_row;
            } else {
                return $idPath;
            }
        }

        return false;
    }

    /**
     * Delete rewrite path record from the database with RP checking.
     *
     * @param string $requestPath
     * @param int $storeId
     * @param bool $rp whether check rewrite option to be "Redirect = Permanent"
     * @return void
     */
    public function postponedDeleteRewriteRecord($requestPath, $storeId, $rp = false)
    {
        if (!isset($this->_removed[$storeId])) {
            $this->_removed[$storeId] = [];
        }

        $rp = $rp ? 'yes' : 'no';

        if (!isset($this->_removed[$storeId][$rp])) {
            $this->_removed[$storeId][$rp] = [];
        }

        $this->_removed[$storeId][$rp][] = $requestPath;
    }

    public function massRemoveOldTargetPath()
    {
        foreach ($this->_removed as $storeId => $item) {
            foreach ($item as $rp => $requestPaths) {
                $conditions = array(
                    'store_id = ?' => $storeId,
                    'request_path in (?)' => $requestPaths,
                );

                if ($rp == 'yes') {
                    $conditions['options = ?'] = 'RP';
                }

                $this->_getWriteAdapter()->delete($this->getMainTable(), $conditions);
            }
        }
    }

    /**
     * Retrieve Product data objects
     *
     * @param int|array $productIds
     * @param int $storeId
     * @param int $entityId
     * @param int $lastEntityId
     * @return array
     */
    protected function _getProducts($productIds, $storeId, $entityId, &$lastEntityId)
    {
        $products = array();
        $websiteId = Mage::app()->getStore($storeId)->getWebsiteId();
        $adapter = $this->_getReadAdapter();
        if ($productIds !== null) {
            if (!is_array($productIds)) {
                $productIds = array($productIds);
            }
        }

        $bind = array(
            'website_id' => (int)$websiteId,
            'entity_id' => (int)$entityId
        );

        $select = $adapter->select()
            ->useStraightJoin(true)
            ->from(array('e' => $this->getTable('catalog/product')), array('entity_id'))
            ->join(
                array('w' => $this->getTable('catalog/product_website')),
                'e.entity_id = w.product_id AND w.website_id = :website_id',
                array()
            )
            ->where('e.entity_id > :entity_id')
            ->order('e.entity_id')
            ->limit($this->_productLimit);

        if ($types = Mage::helper('av_urlrewrite')->getTypes()) {
            $select->where('e.type_id IN (?)', $types);
        }

        if ($productIds !== null) {
            $select->where('e.entity_id IN(?)', $productIds);
        }

        $rowSet = $adapter->fetchAll($select, $bind);
        foreach ($rowSet as $row) {
            $product = new Varien_Object($row);
            $product->setIdFieldName('entity_id');
            $product->setCategoryIds(array());
            $product->setStoreId($storeId);
            $products[$product->getId()] = $product;
            $lastEntityId = $product->getId();
        }

        unset($rowSet);

        if ($products) {

            if (Mage::helper('av_urlrewrite')->useProductCategoryMap()) {
                $select = $adapter->select()
                    ->from(
                        $this->getTable('catalog/category_product'),
                        array('product_id', 'category_id')
                    )
                    ->where('product_id IN(?)', array_keys($products));
                $categories = $adapter->fetchAll($select);
                foreach ($categories as $category) {
                    $productId = $category['product_id'];
                    $categoryIds = $products[$productId]->getCategoryIds();
                    $categoryIds[] = $category['category_id'];
                    $products[$productId]->setCategoryIds($categoryIds);
                }
            }

            foreach (array('name', 'url_key', 'url_path') as $attributeCode) {
                $attributes = $this->_getProductAttribute($attributeCode, array_keys($products), $storeId);
                foreach ($attributes as $productId => $attributeValue) {
                    $products[$productId]->setData($attributeCode, $attributeValue);
                }
            }
        }

        return $products;
    }
}
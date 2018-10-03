<?php

class Avestique_UrlRewrite_Model_Url extends Mage_Catalog_Model_Url
{
    /**
     * Refresh category rewrite
     *
     * @param Varien_Object $category
     * @param string $parentPath
     * @param bool $refreshProducts
     * @return Mage_Catalog_Model_Url
     */
    protected function _refreshCategoryRewrites(Varien_Object $category, $parentPath = null, $refreshProducts = false)
    {
        if ($category->getId() != $this->getStores($category->getStoreId())->getRootCategoryId()) {
            if ($category->getUrlKey() == '') {
                $urlKey = $this->getCategoryModel()->formatUrlKey($category->getName());
            }
            else {
                $urlKey = $this->getCategoryModel()->formatUrlKey($category->getUrlKey());
            }

            $idPath      = $this->generatePath('id', null, $category);
            $targetPath  = $this->generatePath('target', null, $category);
            $requestPath = $this->getCategoryRequestPath($category, $parentPath);

            $rewriteData = array(
                'store_id'      => $category->getStoreId(),
                'category_id'   => $category->getId(),
                'product_id'    => null,
                'id_path'       => $idPath,
                'request_path'  => $requestPath,
                'target_path'   => $targetPath,
                'is_system'     => 1
            );

            $this->getResource()->saveRewrite($rewriteData, $this->_rewrite);

            if ($this->getShouldSaveRewritesHistory($category->getStoreId())) {
                $this->_saveRewriteHistory($rewriteData, $this->_rewrite);
            }

            if ($category->getUrlKey() != $urlKey) {
                $category->setUrlKey($urlKey);
                $this->getResource()->saveCategoryAttribute($category, 'url_key');
            }
            if ($category->getUrlPath() != $requestPath) {
                $category->setUrlPath($requestPath);
                $this->getResource()->saveCategoryAttribute($category, 'url_path');
            }
        }
        else {
            if ($category->getUrlPath() != '') {
                $category->setUrlPath('');
                $this->getResource()->saveCategoryAttribute($category, 'url_path');
            }
        }

        if ($refreshProducts) {
            $this->_refreshCategoryProductRewrites($category);
        }

        foreach ($category->getChilds() as $child) {
            $this->_refreshCategoryRewrites($child, $category->getUrlPath() . '/', $refreshProducts);
        }

        return $this;
    }

    /**
     * Refresh category and childs rewrites
     * Called when reindexing all rewrites and as a reaction on category change that affects rewrites
     *
     * @param int $categoryId
     * @param int|null $storeId
     * @param bool $refreshProducts
     * @return Mage_Catalog_Model_Url
     */
    public function refreshCategoryRewrite($categoryId, $storeId = null, $refreshProducts = true)
    {
        if (is_null($storeId)) {
            foreach ($this->getStores() as $store) {
                $this->refreshCategoryRewrite($categoryId, $store->getId(), $refreshProducts);
            }
            return $this;
        }

        $category = $this->getResource()->getCategory($categoryId, $storeId);
        if (!$category) {
            return $this;
        }

        // Load all childs and refresh all categories
        $category = $this->getResource()->loadCategoryChilds($category);
        $categoryIds = array($category->getId());
        if ($category->getAllChilds()) {
            $categoryIds = array_merge($categoryIds, array_keys($category->getAllChilds()));
        }
        $this->_rewrites = $this->getResource()->prepareRewrites($storeId, $categoryIds);
        $this->_refreshCategoryRewrites($category, null, $refreshProducts);

        unset($category);
        $this->_rewrites = array();

        $this->getResource()->runLastInserts();

        return $this;
    }

    /**
     * Refresh all product rewrites for designated store
     *
     * @param int $storeId
     * @return Mage_Catalog_Model_Url
     */
    public function refreshProductRewrites($storeId)
    {
        $this->_categories      = array();
        $storeRootCategoryId    = $this->getStores($storeId)->getRootCategoryId();
        $storeRootCategoryPath  = $this->getStores($storeId)->getRootCategoryPath();
        $this->_categories[$storeRootCategoryId] = $this->getResource()->getCategory($storeRootCategoryId, $storeId);
        
        $lastEntityId = 0;
        $process = true;

        while ($process == true) {
            $products = $this->getResource()->getProductsByStore($storeId, $lastEntityId);
            
            if (!$products) {
                $process = false;
                break;
            }

            $this->_rewrites = $this->getResource()->prepareRewrites($storeId, false, array_keys($products));

            if (Mage::helper('av_urlrewrite')->useProductCategoryMap()) {
                $loadCategories = array();
                foreach ($products as $product) {
                    foreach ($product->getCategoryIds() as $categoryId) {
                        if (!isset($this->_categories[$categoryId])) {
                            $loadCategories[$categoryId] = $categoryId;
                        }
                    }
                }

                if ($loadCategories) {
                    foreach ($this->getResource()->getCategories($loadCategories, $storeId) as $category) {
                        $this->_categories[$category->getId()] = $category;
                    }
                }
            }

            foreach ($products as $product) {
                $this->_refreshProductRewrite($product, $this->_categories[$storeRootCategoryId]);

                if (Mage::helper('av_urlrewrite')->useProductCategoryMap()){
                    foreach ($product->getCategoryIds() as $categoryId) {
                        if ($categoryId != $storeRootCategoryId && isset($this->_categories[$categoryId])) {
                            if (strpos($this->_categories[$categoryId]['path'], $storeRootCategoryPath . '/') !== 0) {
                                continue;
                            }
                            $this->_refreshProductRewrite($product, $this->_categories[$categoryId]);
                        }
                    }
                }
            }

            unset($products);
            $this->_rewrites = array();
        }

        $this->getResource()->runLastInserts();
        $this->getResource()->massRemoveOldTargetPath();

        $this->_categories = array();
        return $this;
    }


    /**
     * Refresh product rewrite
     *
     * @param Varien_Object $product
     * @param Varien_Object $category
     * @return Mage_Catalog_Model_Url
     */
    protected function _refreshProductRewrite(Varien_Object $product, Varien_Object $category)
    {
        if ($category->getId() == $category->getPath()) {
            return $this;
        }

        if ($product->getUrlKey() == '') {
            $urlKey = $this->getProductModel()->formatUrlKey($product->getName());
        }
        else {
            $urlKey = $this->getProductModel()->formatUrlKey($product->getUrlKey());
        }

        $idPath      = $this->generatePath('id', $product, $category);
        $targetPath  = $this->generatePath('target', $product, $category);
        $requestPath = $this->getProductRequestPath($product, $category);

        $categoryId = null;
        $updateKeys = true;
        if ($category->getLevel() > 1) {
            $categoryId = $category->getId();
            $updateKeys = false;
        }

        $rewriteData = array(
            'store_id'      => $category->getStoreId(),
            'category_id'   => $categoryId,
            'product_id'    => $product->getId(),
            'id_path'       => $idPath,
            'request_path'  => $requestPath,
            'target_path'   => $targetPath,
            'is_system'     => 1
        );

        $this->getResource()->saveRewrite($rewriteData, $this->_rewrite);

        if ($this->getShouldSaveRewritesHistory($category->getStoreId())) {
            $this->_saveRewriteHistory($rewriteData, $this->_rewrite);
        }

        if ($updateKeys && $product->getUrlKey() != $urlKey) {
            $product->setUrlKey($urlKey);
            $this->getResource()->saveProductAttribute($product, 'url_key');
        }
        if ($updateKeys && $product->getUrlPath() != $requestPath) {
            $product->setUrlPath($requestPath);
            $this->getResource()->saveProductAttribute($product, 'url_path');
        }

        return $this;
    }

    /**
     * Get unique category request path
     *
     * @param Varien_Object $category
     * @param string $parentPath
     * @return string
     */
    public function getCategoryRequestPath($category, $parentPath)
    {
        $storeId = $category->getStoreId();
        $idPath  = $this->generatePath('id', null, $category);
        $suffix  = $this->getCategoryUrlSuffix($storeId);

        if (isset($this->_rewrites[$idPath])) {
            $this->_rewrite = $this->_rewrites[$idPath];
            $existingRequestPath = $this->_rewrites[$idPath]->getRequestPath();
        }

        if ($category->getUrlKey() == '') {
            $urlKey = $this->getCategoryModel()->formatUrlKey($category->getName());
        }
        else {
            $urlKey = $this->getCategoryModel()->formatUrlKey($category->getUrlKey());
        }

        $categoryUrlSuffix = $this->getCategoryUrlSuffix($category->getStoreId());
        if (null === $parentPath) {
            $parentPath = $this->getResource()->getCategoryParentPath($category);
        }
        elseif ($parentPath == '/') {
            $parentPath = '';
        }
        $parentPath = Mage::helper('catalog/category')->getCategoryUrlPath($parentPath,
                                                                           true, $category->getStoreId());

        $requestPath = $parentPath . $urlKey . $categoryUrlSuffix;
        if (isset($existingRequestPath) && $existingRequestPath == $requestPath . $suffix) {
            return $existingRequestPath;
        }

        if ($this->_deleteOldTargetPath($requestPath, $idPath, $storeId)) {
            return $requestPath;
        }

        return $this->getUnusedPath($category->getStoreId(), $requestPath,
                                    $this->generatePath('id', null, $category)
        );
    }

    /**
     * Check if current generated request path is one of the old paths
     *
     * @param string $requestPath
     * @param string $idPath
     * @param int $storeId
     * @return bool
     */
    protected function _deleteOldTargetPath($requestPath, $idPath, $storeId)
    {
        $finalOldTargetPath = $this->getResource()->findFinalTargetPath($requestPath, $storeId);
        if ($finalOldTargetPath && $finalOldTargetPath == $idPath) {
            $this->getResource()->postponedDeleteRewriteRecord($requestPath, $storeId, true);
            return true;
        }

        return false;
    }

    /**
     * Get unique product request path
     *
     * @param   Varien_Object $product
     * @param   Varien_Object $category
     * @return  string
     */
    public function getProductRequestPath($product, $category)
    {
        if ($product->getUrlKey() == '') {
            $urlKey = $this->getProductModel()->formatUrlKey($product->getName());
        } else {
            $urlKey = $this->getProductModel()->formatUrlKey($product->getUrlKey());
        }
        $storeId = $category->getStoreId();
        $suffix  = $this->getProductUrlSuffix($storeId);
        $idPath  = $this->generatePath('id', $product, $category);
        /**
         * Prepare product base request path
         */
        if ($category->getLevel() > 1) {
            // To ensure, that category has path either from attribute or generated now
            $this->_addCategoryUrlPath($category);
            $categoryUrl = Mage::helper('catalog/category')->getCategoryUrlPath($category->getUrlPath(),
                false, $storeId);
            $requestPath = $categoryUrl . '/' . $urlKey;
        } else {
            $requestPath = $urlKey;
        }

        if (strlen($requestPath) > self::MAX_REQUEST_PATH_LENGTH + self::ALLOWED_REQUEST_PATH_OVERFLOW) {
            $requestPath = substr($requestPath, 0, self::MAX_REQUEST_PATH_LENGTH);
        }

        $this->_rewrite = null;
        /**
         * Check $requestPath should be unique
         */
        if (isset($this->_rewrites[$idPath])) {
            $this->_rewrite = $this->_rewrites[$idPath];
            $existingRequestPath = $this->_rewrites[$idPath]->getRequestPath();

            if ($existingRequestPath == $requestPath . $suffix) {
                return $existingRequestPath;
            }

            $existingRequestPath = preg_replace('/' . preg_quote($suffix, '/') . '$/', '', $existingRequestPath);
            /**
             * Check if existing request past can be used
             */
            if (!empty($requestPath)
                && strpos($existingRequestPath, $requestPath) === 0
            ) {
                $existingRequestPath = preg_replace(
                    '/^' . preg_quote($requestPath, '/') . '/', '', $existingRequestPath
                );
                if (preg_match('#^-([0-9]+)$#i', $existingRequestPath)) {
                    return $this->_rewrites[$idPath]->getRequestPath();
                }
            }

            $fullPath = $requestPath.$suffix;
            if ($this->_deleteOldTargetPath($fullPath, $idPath, $storeId)) {
                return $fullPath;
            }
        }
        /**
         * Check 2 variants: $requestPath and $requestPath . '-' . $productId
         */
        $validatedPath = $this->getResource()->checkRequestPaths(
            array($requestPath.$suffix, $requestPath.'-'.$product->getId().$suffix),
            $storeId
        );

        if ($validatedPath) {
            return $validatedPath;
        }
        /**
         * Use unique path generator
         */
        return $this->getUnusedPath($storeId, $requestPath.$suffix, $idPath);
    }
}

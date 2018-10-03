Avestique_UrlRewrite
======================

Tech Information
-----
- version: 1.0.1 Beta
- extension key: Avestique_UrlRewrite
- [extension on GitHub](https://github.com/avestique/magento1_url_reindex)
- [direct download link](https://github.com/avestique/magento1_url_reindex/archive/master.zip)
- you can use composer

Description
-----------
Definitely you ever had a problem with table core_url_rewrite indexing if you have large catalog with several thousand skus.

The bottleneck is single query for getting rewrite data for each product. Each query takes about 0.02 msec. It means that reindexing will take at least 4 minutes for each 10000 skus only for getting rewrite product data.

Lets assume you have 200k skus in the catalog. If you sum up the total evaluation for each product then it will take forever.

There is the same problem for the category rewrites. This extension will help you to solve this problem.

FAQ
------------
1. What is the main concept to solve this problem?
   
   It accumulates all current data from core_url_rewrite in Redis cache before the reindexing and accumulates insert queries and runs it by portions after that.
   
2. How does optimization work for product rewrites? 
   
    It generates and saves accumulated url path product attribute values to related tables by pocket. Checking the path if it exists in the cache and generates new portions of rewrite insert queries.
        
3. How does optimization work for category rewrites?

    Uses rewrite cache to get the quick data but there is no pocket inserts for categories. Each category has one insert query. But it generates an array of inserts for url/path category attribute values.
    It works a bit slower than for the product reindexing. 

4. Which options are there?

    There are two optons how to improve performance:
    * You can reduce an amount of records by disabling product/category mapping (catalog_category_product). Set <code>filter_product_category_index</code> as 0 in global xml section to disable it. By default its enabled. Do not forget to set the module dependency. 
    * You can filter rewrite generation by the product types. Add this code to global xml section to generate rewrites only for grouped and simple products: 
        ~~~~~~ 
        <filter_product_type_index>
            <values>
                <grouped>grouped</grouped>
                <simple>simple</simple>
            </values>
        </filter_product_type_index>
        ~~~~~~
        By default all product types are taking a part in the reindexing. 
                                                                                                                
5. Why do I have to use Mage_Cache_Backend_Redis cache?

   This is the fastest way to store the data and to read it quick back. If you would use standard magento cache it would write thousands of cache files only for this reindexing.
6. Does this extension flush the cache automatically?

   Yes. It cleans the cache before reading core_url_rewrite table. You can use tags to filter the cache but it's quite risky to get RAM runs out of. 

7. How long does it run?
 
   I tested it with large DB but without catalog-product mapping. In my case it takes about 7-10 minutes for 10000 categories. And about 20 minutes for 200k skus. 

Compatibility
-------------
- Magento >= 1.9.2.0
- Magento >= 1.8.0.0 (probably, not tested)

Requirements
------------
- PHP >= 7.0
- Mage_Catalog
- Mage_Cache_Backend_Redis

Cache <code>Mage_Cache_Backend_Redis</code>  must be enabled as backend cache. Otherwise do NOT use it.

Installation Instructions
-------------------------
There are 3 choises how to install it. You can choose more appropriated way:
1. Install the extension using composer [Recommended] <pre>composer require avestique/url_rewrite_optimization</pre>
2. Copy the content to <i>.modman</i> folder and ther run the command from the console: <pre>modman deploy Avestique_UrlRewrite</pre>
3. Copy all the files into your document root and clear the cache.

Uninstallation
--------------
1. Remove all extension files from your Magento installation. There are no setup scripts.
2. Reindex your url indexes using stadnard tool.

Support
-------
If you have any questons/issues, open an issue on [GitHub](https://github.com/avestique/magento1_url_reindex/issues).

Contribution
------------
Any contribution is highly appreciated. Here you can find howto: [pull request on GitHub](https://help.github.com/articles/using-pull-requests).

Licence
-------
[OSL - Open Software Licence 3.0](http://opensource.org/licenses/osl-3.0.php)

Copyright
---------
(c) 2018 Avestique
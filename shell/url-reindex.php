<?php

error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', 1);

require_once 'abstract.php';

class Url_Reindex extends Mage_Shell_Abstract
{
    public function run()
    {
        Mage::getSingleton('catalog/url')->refreshRewrites();
    }
}

$shell = new Url_Reindex();
$shell->run();
<?php
/**
 * @category   Reload
 * @package    Reload_Seo
 * @copyright  Copyright (c) 2013-2015 AndCode (http://www.andcode.nl)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 * Reload_Seo_Helper_Massaction is the helper class for the massaction update
 */
class Reload_Seo_Helper_Massaction extends Reload_Seo_Helper_Data
{
    /**
     * Field for storing the product collection in.
     * @var Mage_Catalog_Resource_Product_Collection
     */
    protected $productCollection;

    /**
     * Field for storing the store id in. 
     * @var int
     */
    protected $storeId;

    /**
     * Field for keeping track of the scores by product id.
     * @var array
     */
    protected $scoresByProductId;

    /**
     * Field for storing the collected field mapping
     * @var array
     */
    protected $fieldMapping;

    /**
     * Whether the name should be used as default keywords or not.
     * @var boolean
     */
    protected $useNameAsDefaultKeywords;

    /**
     * prepareAction prepares and initializes all data required to process this action.
     * 
     * @param  array $productIds
     * @return void
     */
    protected function prepareAction($productIds)
    {
        //Obtain all products were the entity_id is in the array.
        $this->productCollection = Mage::getModel('catalog/product')->getCollection();

        $this->storeId = (int) Mage::app()->getRequest()->getParam('store');
        if($this->storeId > 0)
        {
            $this->productCollection->setStoreId($this->storeId);
        }
            
        $this->productCollection = $this->productCollection
            ->addAttributeToFilter('entity_id', array('in' => $productIds))
            ->addAttributeToSelect('*');

        $this->scoresByProductId = array();
        $scoreCollection = Mage::getModel('reload_seo/score')
            ->getCollection()
            ->addFieldToFilter('type', array('eq' => 'product'))
            ->addFieldToFilter('reference_id', array('in' => $productIds))
            ->addFieldToFilter('store_id', array('eq' => $this->storeId));

        foreach($scoreCollection as $score)
        {
            $this->scoresByProductId[$score->getReferenceId()] = $score;
        }

        //Obtain the field mapping for the products.
        $this->fieldMapping = $this->getFieldMappings('product');

        $this->useNameAsDefaultKeywords = Mage::getStoreConfig('reload/reload_seo_group/reload_seo_title_default');
    }

    /**
     * collectData collects all data for the selected products.
     * @return array
     */
    protected function collectData()
    {
        $data = array();

        //Loop over all the products.
        foreach($this->productCollection as $product)
        {
            $sku = $product->getSku();

            //Add the SKU to the data array.
            $data[] = http_build_query(array('products[]sku' => $sku));

            if(array_key_exists($product->getId(), $this->scoresByProductId))
            {
                $score = $this->scoresByProductId[$product->getId()];
                if($this->useNameAsDefaultKeywords && $score->getKeywords() == null)
                {
                    $score->generateKeywords($product->getName());
                }

                if($score->getKeywords() == null && $this->storeId > 0)
                {
                    $defaultScore = Mage::getModel('reload_seo/score')->getCollection()
                        ->addFieldToFilter('type', array('eq' => $score->getType()))
                        ->addFieldToFilter('reference_id', array('eq' => $score->getReferenceId()))
                        ->addFieldToFilter('store_id', array('eq' => 0))
                        ->getFirstItem();

                    if($defaultScore != null)
                    {
                        $score->setKeywords($defaultScore->getKeywords());
                    }
                }

                $data[] = http_build_query(array('products[]keywords' => $score->getKeywords()));
            }
            elseif($this->useNameAsDefaultKeywords)
            {
                $data[] = http_build_query(array('products[]keywords' => $product->getName()));
            }

            $data[] = http_build_query(array('products[]store_id' => $product->getStoreId()));

            foreach($this->fieldMapping as $external => $internal)
            {
                //Obtain all the field names and data and append them to the data array.
                if($product->getData($internal) != null)
                {
                    $data[] = http_build_query(array('products[]' . $external => $product->getData($internal)));
                }
            }

            $data[] = http_build_query(array('products[]status' => $product->getStatus()));
            $data[] = http_build_query(array('products[]visibility' => $product->getVisibility()));

            $images = array();
            foreach($product->getMediaGalleryImages() as $image)
            {
                $images[] = array(
                    'url' => $image->getUrl(),
                    'name' => $image->getLabel(),
                );
            }
            if(count($images) > 0)
            {
                $data[] = http_build_query(array('products[]images[]' => $images));
            }
        }

        return $data;
    }

    /**
     * updateProducts updates all prodcuts with the provided product ids.
     * 
     * @param  array $productIds
     * @return void
     */
    public function updateProducts($productIds)
    {
        $this->prepareAction($productIds);

        $data = $this->collectData();

        //Build the url for the mass update.
        $url = $this->buildUrl('index', 
            array(
                'key' => Mage::getStoreConfig('reload/reload_seo_group/reload_seo_key'), 
                'language' => Mage::app()->getLocale()->getLocaleCode(),
                'type' => 'product',
                'framework' => 'magento',
                'website' => Mage::getBaseUrl(),
            )
        );

        $data[] = http_build_query(array('stores' => $this->collectStores()));

        //Execute the request.
        $results = $this->executeCurlRequest($url, implode('&', $data), true);

        if($results === null)
        {
            //Something went wrong.
            throw new Exception($this->__('Something went wrong while updating the product SEO statusses.'));
        }

        //Sort the results by the sku's.
        $resultsBySku = array();
        foreach($results as $result)
        {
            $resultsBySku[$result['sku']] = $result;
        }

        try
        {
            //Loop over all products and get the result for each product.
            foreach($this->productCollection as $product)
            {
                if(array_key_exists($product->getSku(), $resultsBySku))
                {
                    //Load the score object or create it if it doesn't exist and merge the results into the object.
                    $score = Mage::getModel('reload_seo/score')->loadById($product->getId(), 'product');
                    if($this->useNameAsDefaultKeywords && $score->getKeywords() == null)
                    {
                        $score->generateKeywords($product->getName());
                    }
                    $score->mergeFromResult($resultsBySku[$product->getSku()]);
                }
            }
        }
        catch(Exception $ex)
        {
            //Something went wrong while saving the results.
            throw new Exception($this->__('Something went wrong while processing the product SEO results.'));
        }
    }
}
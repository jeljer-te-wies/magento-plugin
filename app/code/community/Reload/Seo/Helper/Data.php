<?php
/**
 * @category   Reload
 * @package    Reload_Seo
 * @copyright  Copyright (c) 2013-2015 AndCode (http://www.andcode.nl)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 * Reload_Seo_Helper_Data is the helper class for this module, mostly it contains functions
 * to handle the request with the Reload API.
 */
class Reload_Seo_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * Variable for storing the API url in.
     * @var string
     */
	protected $url = 'http://api.reloadseo.com/api/';

    /**
     * Variable for storing the version in.
     * @var string
     */
    protected $version = 'v1/';

    /**
     * Variable for keeping track of the possible urls.
     * @var array
     */
    protected $fields = array(
        'description', 
        'short_description', 
        'meta_description', 
        'meta_keyword', 
        'meta_title', 
        'name',
        'url_key',
        'status',
    );

    /**
     * keyIsValid makes an API call to the Reload API to check if the API key is valid.
     * 
     * @return boolean
     */
    public function keyIsValid()
    {
        //Obtain the API key from the configuration and obtain the adminhtml session.
        $apiKey = Mage::getStoreConfig('reload/reload_seo_group/reload_seo_key');
        $session = Mage::getSingleton('adminhtml/session');
        if($session->getSeoApiKey() == $apiKey && (time() - $session->getSeoApiKeyCheckTime()) < 300)
        {
            //The check has already been made with this key and the check is not 5 minutes old yet, return the result.
            return $session->getSeoApiKeyValid();
        }

        //Create the complete url and execute it.
        $url = $this->url . $this->version . 'seo/validate_key?' . http_build_query(array('key' => $apiKey, 'website' => Mage::getBaseUrl()));
        $result = $this->executeCurlRequest($url);
        if($result === null)
        {
            //No result, something went wrong.
            throw new Exception($this->__('Something went wrong while connection to our API.'));
        }

        $session->unsCustomSEOMessage();
        $session->unsCustomSEOMessageType();

        //Store the API key in the session.
        $session->setSeoApiKey($apiKey);
        $session->setSeoApiKeyCheckTime(time());

        if(array_key_exists('message', $result) && array_key_exists('title', $result['message']) && array_key_exists('type', $result['message']) && $result['message']['title'] != null && $result['message']['type'] != null)
        {
            $session->setCustomSEOMessage(sprintf($result['message']['title'], Mage::helper("adminhtml")->getUrl('adminhtml/system_config/edit/section/reload')));
            $session->setCustomSEOMessageType($result['message']['type']);
        }

        if(array_key_exists('key', $result) && $result['key'] == 'valid')
        {
            //The API key is valid, set the session flag and return true.
            $session->setSeoApiKeyValid(true);
            return true;
        }
        //The API key is not valid, set the session falg and return false.
        $session->setSeoApiKeyValid(false);
        return false;
    }

    /**
     * updateProducts updates all prodcuts with the provided product ids.
     * 
     * @param  array $productIds
     * @return void
     */
    public function updateProducts($productIds)
    {
        //Obtain all products were the entity_id is in the array.
        $productCollection = Mage::getModel('catalog/product')->getCollection();

        $storeId = (int) Mage::app()->getRequest()->getParam('store');
        if($storeId > 0)
        {
            $productCollection->setStoreId($storeId);
        }
            
        $productCollection = $productCollection
            ->addAttributeToFilter('entity_id', array('in' => $productIds))
            ->addAttributeToSelect('*');

        $scoresByProductId = array();
        $scoreCollection = Mage::getModel('reload_seo/score')
            ->getCollection()
            ->addFieldToFilter('type', array('eq' => 'product'))
            ->addFieldToFilter('reference_id', array('in' => $productIds));

        foreach($scoreCollection as $score)
        {
            $scoresByProductId[$score->getReferenceId()] = $score;
        }

        //Obtain the field mapping for the products.
        $fieldMapping = $this->getFieldMappings('product');
        $data = array();

        $useNameAsDefaultKeywords = Mage::getStoreConfig('reload/reload_seo_group/reload_seo_title_default');

        $hasDisabledProducts = false;
        $hasEnabledProducts = false;

        //Loop over all the products.
        foreach($productCollection as $product)
        {
            if(!$this->shouldProductBeChecked($product))
            {
                $hasDisabledProducts = true;
            }
            else
            {
                $hasEnabledProducts = true;

                $sku = $product->getSku();

                //Add the SKU to the data array.
                $data[] = http_build_query(array('products[]sku' => $sku));

                if(array_key_exists($product->getId(), $scoresByProductId))
                {
                    $score = $scoresByProductId[$product->getId()];
                    if($useNameAsDefaultKeywords && $score->getKeywords() == null)
                    {
                        $score->generateKeywords($product->getName());
                    }

                    if($score->getKeywords() == null && $storeId > 0)
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
                elseif($useNameAsDefaultKeywords)
                {
                    $data[] = http_build_query(array('products[]keywords' => $product->getName()));
                }

                $data[] = http_build_query(array('products[]store_id' => $product->getStoreId()));

                foreach($fieldMapping as $external => $internal)
                {
                    //Obtain all the field names and data and append them to the data array.
                    if($product->getData($internal) != null)
                    {
                        $data[] = http_build_query(array('products[]' . $external => $product->getData($internal)));
                    }
                }

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
        }

        if($hasEnabledProducts)
        {
            //Build the url for the mass update.
            $url = $this->url . $this->version . 'seo/index?' . http_build_query(
                array(
                    'key' => Mage::getStoreConfig('reload/reload_seo_group/reload_seo_key'), 
                    'language' => Mage::app()->getLocale()->getLocaleCode(),
                    'type' => 'product',
                    'framework' => 'magento',
                    'website' => Mage::getBaseUrl(),
                )
            );
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
                foreach($productCollection as $product)
                {
                    if(array_key_exists($product->getSku(), $resultsBySku))
                    {
                        //Load the score object or create it if it doesn't exist and merge the results into the object.
                        $score = Mage::getModel('reload_seo/score')->loadById($product->getId(), 'product');
                        if($useNameAsDefaultKeywords && $score->getKeywords() == null)
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

            if($hasDisabledProducts)
            {
                Mage::getSingleton('adminhtml/session')->addNotice($this->__('Some selected products are disabled or invisible and were not updated, only enabled products will be updated.'));
            }
        }
        else
        {
            throw new Exception($this->__('Only enabled and visible products will be updated, please select enabled products.'));
        }
    }

    /**
     * removeItem removes one product or category SEO status.
     * 
     * @param Mage_Catalog_Model_Category|Mage_Catalog_Model_Product $item
     * @return void
     */
    public function removeItem($item)
    {
        if($item instanceof Mage_Catalog_Model_Category)
        {
            //The item is a category, the sku will be category-<id>
            $sku = 'category-' . $item->getId();

            //Prepare the error for later use.
            $error = $this->__('Something went wrong while removing the category SEO status.');

            //Obtain the score object for later use.
            $score = Mage::getModel('reload_seo/score')->loadById($item->getId(), 'category');
            $type = 'category';
        }
        else
        {
            //The item is a product, the sku will be used as is.
            $sku = $item->getSku();

            //Prepare the error for later use.
            $error = $this->__('Something went wrong while removing the product SEO status.');

            //Obtain the score object for later use.
            $score = Mage::getModel('reload_seo/score')->loadById($item->getId(), 'product');
            $type = 'product';
        }

        //Create the url for the update.
        $url = $this->url . $this->version . 'seo?' . http_build_query(
            array(
                'key' => Mage::getStoreConfig('reload/reload_seo_group/reload_seo_key'), 
                'language' => Mage::app()->getLocale()->getLocaleCode(),
                'type' => $type,
                'framework' => 'magento',
                'product[sku]' => $sku,
                'website' => Mage::getBaseUrl(),
            )
        );

        //Execute the request.
        $result = $this->executeCurlRequest($url, null, false, true);
        if($result === null || !array_key_exists('sku', $result))
        {
            //Something went wrong, throw the prepared error.
            throw new Exception($error);
        }

        try
        {
            $score->delete();
        }
        catch(Exception $ex)
        {
            //Something went wrong, throw the prepared error.
            throw new Exception($error);
        }
    }

    /**
     * updateItem updates one product or category.
     * 
     * @param Mage_Catalog_Model_Category|Mage_Catalog_Model_Product $item
     * @return void
     */
    public function updateItem($item)
    {
        //Create an array for the post data.
        $data = array();
        if($item instanceof Mage_Catalog_Model_Category)
        {
            //The item is a category, the sku will be category-<id>
            $data['product[sku]'] = 'category-' . $item->getId();

            //Prepare the error for later use.
            $error = $this->__('Something went wrong while updating the category SEO status.');

            //Obtain the score object for later use.
            $score = Mage::getModel('reload_seo/score')->loadById($item->getId(), 'category');
            $type = 'category';
        }
        else
        {
            //The item is a product, the sku will be used as is.
            $data['product[sku]'] = $item->getSku();

            //Prepare the error for later use.
            $error = $this->__('Something went wrong while updating the product SEO status.');

            //Obtain the score object for later use.
            $score = Mage::getModel('reload_seo/score')->loadById($item->getId(), 'product');
            $type = 'product';

            $data['product[images]'] = array();
            foreach($item->getMediaGalleryImages() as $image)
            {
                $data['product[images]'][] = array(
                    'url' => $image->getUrl(),
                    'name' => $image->getLabel(),
                );
            }
        }

        if($score->getKeywords() == null && Mage::getStoreConfig('reload/reload_seo_group/reload_seo_title_default'))
        {
            $score->generateKeywords($item->getName());
        }

        $data['product[product_id]'] = $type . '-' . $item->getId();
        $data['product[keywords]'] = $score->getKeywords();
        $data['product[store_id]'] = $item->getStoreId();

        //Obtain the field mapping by the type and loop over each field, obtain the data and store it.
        $fieldMapping = $this->getFieldMappings($type);
        foreach($fieldMapping as $external => $internal)
        {
            $data['product[' . $external . ']'] = $item->getData($internal);
        }

        //Create the url for the update.
        $url = $this->url . $this->version . 'seo/show?' . http_build_query(
            array(
                'key' => Mage::getStoreConfig('reload/reload_seo_group/reload_seo_key'), 
                'language' => Mage::app()->getLocale()->getLocaleCode(),
                'type' => $type,
                'framework' => 'magento',
                'website' => Mage::getBaseUrl(),
            )
        );

        //Check if the sku is not null
        if($data['product[sku]'] === null)
        {
            $data['product[sku]'] = '0';
        }

        //Execute the request.
        $result = $this->executeCurlRequest($url, $data);
        if($result === null || !array_key_exists('score', $result))
        {
            //Something went wrong, throw the prepared error.
            throw new Exception($error);
        }

        //Merge the result in the score object.
        $score->mergeFromResult($result);
    }

    /**
     * ajaxCheck executes the same as the updateItem function but does not save the results.
     * 
     * @param  string $type
     * @param  string $referenceId
     * @param  array $data
     * @return array
     */
    public function ajaxCheck($type, $referenceId, $data)
    {
        //Create an array for the prepared data.
        $preparedData = array();
        if($type === 'product')
        {
            //The type is product.
            $type = 'product';
        }
        else
        {
            //The type is category.
            $preparedData['product[sku]'] = 'category-' . $referenceId;
            $type = 'category';
        }

        $preparedData['product[product_id]'] = $type . '-' . $referenceId;

        foreach($data as $key => $value)
        {
            //Loop over all data and prepare it.
            $preparedData['product['.$key.']'] = $value;
        }

        //Prepare the url for the call.
        $url = $this->url . $this->version . 'seo/show?' . http_build_query(
            array(
                'key' => Mage::getStoreConfig('reload/reload_seo_group/reload_seo_key'), 
                'language' => Mage::app()->getLocale()->getLocaleCode(),
                'type' => $type,
                'framework' => 'magento',
                'website' => Mage::getBaseUrl(),
            )
        );

        //Excecute the call.
        $result = $this->executeCurlRequest($url, $preparedData);
        if($result === null || !array_key_exists('score', $result))
        {
            //Somethineg went wrong, throw an exception.
            throw new Exception();
        }
        //The call was successfull, return the result.
        return $result;
    }

    /**
     * validConfig checks if the configuration is valid.
     * 
     * @return void
     */
    public function validConfig()
    {
        //Obtain the API key from the configuration.
        $apiKey = Mage::getStoreConfig('reload/reload_seo_group/reload_seo_key');

        if($apiKey === null || strlen($apiKey) <= 0)
        {
            //The API key is not filled in, throw an exception.
            throw new Exception($this->__("No API key given, please enter your API key in the <a href='%s'>configuration</a>.", Mage::helper("adminhtml")->getUrl('adminhtml/system_config/edit/section/reload')));
        }

        $session = Mage::getSingleton('adminhtml/session');
        $keyIsValid = $this->keyIsValid();

        if(!$keyIsValid && $session->getCustomSEOMessage() == null)
        {
            //The API key is not valid, and no message was provided.
            throw new Exception($this->__("The given API key is invalid, please enter a valid API key in the <a href='%s'>configuration</a>.", Mage::helper("adminhtml")->getUrl('adminhtml/system_config/edit/section/reload')));
        }
        elseif($session->getCustomSEOMessage() != null)
        {
            //The API returned an message, let's add it to the session.
            if($session->getCustomSEOMessageType() === 'notice')
            {
                $session->addNotice($session->getCustomSEOMessage());
            }
            elseif($session->getCustomSEOMessageType() === 'warning')
            {
                $session->addWarning($session->getCustomSEOMessage());
            }
            elseif($session->getCustomSEOMessageType() === 'error')
            {
                $session->addError($session->getCustomSEOMessage());
            }
            else
            {
                $session->addSuccess($session->getCustomSEOMessage());
            }
        }
    }

    /**
     * exec executes an API call to the Reload API.
     * 
     * @param  string  $url
     * @param  array|string  $postdata
     * @param  boolean $postAsString If true, the $postdata is an string.
     * @return array
     */
    protected function executeCurlRequest($url, $postdata = null, $postAsString = false, $isDelete = false)
    {
        //Obtain an curl handle.
        $ch = curl_init($url);

        //Tell the handle to wait for a response.
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        if($postdata !== null)
        {
            //Set the handle to make an POST request.
            curl_setopt($ch, CURLOPT_POST, 1);
            if($postAsString)
            {
                //Set the post data
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
            }
            else
            {
                //Build the post data query and set it.
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postdata));
            }
            //Set the content type.
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
        }
        elseif($isDelete)
        {
            //Set the handle to make an DELETE request.
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        //Execute the request and close the handle.
        $result = curl_exec($ch);
        curl_close($ch);

        //Decode the result and return the value if successfull.
        $result = json_decode($result, true);
        if($result)
        {
            return $result;
        }
        return null;
    }

    /**
     * getFieldMappings creates the field mapping.
     * 
     * @param  string $type
     * @return array
     */
    public function getFieldMappings($type)
    {
        $fieldMapping = array();
        if($type === 'product')
        {
            //We want the field mapping for a product, loop over all fields.
            foreach($this->fields as $field)
            {
                //Get the attribute code from the configuration, only add it when it was configured.
                $attributeCode = $this->getFieldAttributeCode($field);
                if($attributeCode !== null)
                {
                    $fieldMapping[$field] = $attributeCode;
                }
            }
        }
        else
        {
            //We want the field mapping for a category, the fields are fixed/hardcoded.
            $fieldMapping['name'] = 'name';
            $fieldMapping['description'] = 'description';
            $fieldMapping['url_key'] = 'url_key';
            $fieldMapping['meta_title'] = 'meta_title';
            $fieldMapping['meta_keyword'] = 'meta_keywords';
            $fieldMapping['meta_description'] = 'meta_description';
        }
        return $fieldMapping;
    }

    /**
     * getFieldAttributeCode loads the configured attribute code from the configuration.
     * 
     * @param  string $field
     * @return string
     */
    protected function getFieldAttributeCode($field)
    {
        $attributeCode = Mage::getStoreConfig('reload/reload_seo_mappings/reload_seo_mapping_' . $field);
        if($attributeCode == null)
        {
            return null;
        }
        return $attributeCode;
    }

    public function shouldProductBeChecked($product)
    {
        if($product == null)
        {
            return false;
        }

        if($product instanceof Mage_Catalog_Model_Product)
        {
            if($product->getStatus() == 2)
            {
                return false;
            }

            if($product->getVisibility() == Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE)
            {
                return false;
            }
        }
        return true;
    }
}

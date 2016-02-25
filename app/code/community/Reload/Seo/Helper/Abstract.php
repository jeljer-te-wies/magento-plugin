<?php
/**
 * @category   Reload
 * @package    Reload_Seo
 * @copyright  Copyright (c) 2013-2015 AndCode (http://www.andcode.nl)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 * Reload_Seo_Helper_Abstract contains the basic functionality for the Reload_Seo_Helper_Data class
 */
class Reload_Seo_Helper_Abstract extends Mage_Core_Helper_Abstract
{
    /**
     * Variable for storing the API url in.
     * @var string
     */
    protected $url = 'http://api.reloadseo.com/api/';

    /**
     * Variable for storing the API version in.
     * @var string
     */
    protected $version = 'v1/';

    /**
     * buildUrl creates the url with the given action and parameters.
     * 
     * @param  string $action
     * @param  array  $params
     * @return string      
     */
    public function buildUrl($action, $params = array())
    {
        $url = $this->url . $this->version . 'seo';

        if($action != null)
        {
            $url .= '/' . $action;
        }

        if($params != null)
        {
            $url .= '?' . http_build_query($params);
        }

        return $url;
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
     * collectStores collects the data about the websites and stores.
     * 
     * @return array
     */
    public function collectStores()
    {
        $storesData = array();

        //Loop over the websites.
        foreach(Mage::app()->getWebsites() as $website)
        {
            $websiteData = array(
                'id' => $website->getWebsiteId(),
                'name' => $website->getName(),
                'code' => $website->getCode(),
                'stores' => array()
            );

            //Loop over the stores.
            foreach($website->getStores() as $store)
            {
                //Check wheter the secure url or non-secure url is used in the frontend.
                if(Mage::getStoreConfig('web/secure/use_in_frontend', $store->getStoreId()))
                {
                    $frontendBaseUrl = Mage::app()->getStore($store->getStoreId())->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK, array('_secure' => true));
                }
                else
                {
                    $frontendBaseUrl = Mage::app()->getStore($store->getStoreId())->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK);
                }

                $websiteData['stores'][] = array(
                    'id' => $store->getStoreId(),
                    'name' => $store->getName(),
                    'code' => $store->getCode(),
                    'is_active' => $store->getIsActive(),
                    'frontend_base_url' => $frontendBaseUrl
                );
            }

            $storesData[] = $websiteData;
        }
        return $storesData;
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
        $url = $this->buildUrl('validate_key', array('key' => $apiKey, 'website' => Mage::getBaseUrl()));
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
}
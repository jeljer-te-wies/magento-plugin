<?php
/**
 * @category   Reload
 * @package    Reload_Seo
 * @copyright  Copyright (c) 2013-2015 AndCode (http://www.andcode.nl)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 * Reload_Seo_Model_Observer contains the basis functions for alterning pages when events get fired.
 */
class Reload_Seo_Model_Observer
{
	/**
	 * catalogProductLoadAfter is called at the catalog_product_load_after event.
	 * 
	 * @param  Varien_Event_Observer $observer
	 * @return void
	 */
	public function catalogProductLoadAfter($observer)
	{
		$this->_registerScore($observer->getProduct()->getId(), 'product', $observer->getProduct());
	}

	/**
	 * catalogCategoryLoadAfter is called at the catalog_category_load_after event.
	 * 
	 * @param  Varien_Event_Observer $observer
	 * @return void
	 */
	public function catalogCategoryLoadAfter($observer)
	{
		$this->_registerScore($observer->getCategory()->getId(), 'category', $observer->getCategory());
	}

	/**
	 * _registerScore registers the score object for a category or a product.
	 * 
	 * @param  int $referenceId
	 * @param  string $type
	 * @param  Mage_Catalog_Model_Product|Mage_Catalog_Model_Category $observerObject
	 * @return void
	 */
	protected function _registerScore($referenceId, $type, $observerObject)
	{
		//If the reference === null, we want to load the 0 object.
		if($referenceId === null)
		{
			$referenceId = 0;
		}

		//Load the score from the database where the reference_id and type matches.
		$scoreObject = Mage::getModel('reload_seo/score')->loadById($referenceId, $type);
		if($scoreObject == null)
		{
			$scoreObject = Mage::getModel('reload_seo/score');
		}

		$observerObject->setScoreObject($scoreObject);
		$observerObject->setReloadSeoKeywords($scoreObject->getKeywords());
		$observerObject->setReloadSeoSynonyms($scoreObject->getSynonyms());
		$observerObject->setAttributeDefaultValue('reload_seo_keywords', $scoreObject->getDefaultKeywords());

		if($scoreObject->getKeywords() != null  && $scoreObject->getKeywords() != $scoreObject->getDefaultKeywords())
		{
			$observerObject->setExistsStoreValueFlag('reload_seo_keywords');
		}

		if($scoreObject->getSynonyms() != null && $scoreObject->getSynonyms() != $scoreObject->getDefaultSynonyms())
		{
			$observerObject->setExistsStoreValueFlag('reload_seo_synonyms');
		}

		if(Mage::registry('seo_score') != null)
		{
			Mage::unregister('seo_score');
		}

		Mage::register('seo_score', $scoreObject);
	}

	/**
	 * catalogCategoryDeleteAfter is called at the catalog_category_delete_after event.
	 * 
	 * @param  Varien_Event_Observer $observer
	 * @return void
	 */
	public function catalogCategoryDeleteAfter($observer)
	{
		try
		{
			//Tell the api to remove the item from the list.
			Mage::helper('reload_seo')->removeItem($observer->getCategory());
		}
		catch(Exception $ex)
		{
			//Hmz.
			Mage::getSingleton('adminhtml/session')->addError($ex->getMessage());
		}
	}

	/**
	 * catalogProductDeleteAfter is called at the catalog_product_delete_after event.
	 * 
	 * @param  Varien_Event_Observer $observer
	 * @return void
	 */
	public function catalogProductDeleteAfter($observer)
	{
		try
		{
			//Tell the api to remove the item from the list.
			Mage::helper('reload_seo')->removeItem($observer->getProduct());
		}
		catch(Exception $ex)
		{
			//Hmz.
			Mage::getSingleton('adminhtml/session')->addError($ex->getMessage());
		}
	}

	/**
	 * catalogProductSaveAfter is called at the catalog_product_save_after event.
	 * 
	 * @param  Varien_Event_Observer $observer
	 * @return void
	 */
	public function catalogProductSaveAfter($observer)
	{
		$this->_afterSave($observer->getProduct()->getId(), 'product', 'product');
	}

	/**
	 * catalogCategorySaveAfter is called at the catalog_category_save_after event.
	 * 
	 * @param  Varien_Event_Observer $observer
	 * @return void
	 */
	public function catalogCategorySaveAfter($observer)
	{
		$this->_afterSave($observer->getCategory()->getId(), 'category', 'general');
	}

	/**
	 * _afterSave saves the score for a product or a category.
	 * 
	 * @param  int 		$id        The product or category id.
	 * @param  string 	$type      Either product or category
	 * @param  string  	$postField Either product or general
	 * 
	 * @return void
	 */
	protected function _afterSave($id, $type, $postField)
	{
		$post = Mage::app()->getRequest()->getPost($postField);

		try
		{
			if($post != null)
			{
				if(array_key_exists('reload_seo_keywords', $post))
				{
					$keywords = $post['reload_seo_keywords'];
				}
				else
				{
					$keywords = '';
				}

				if(array_key_exists('reload_seo_synonyms', $post))
				{
					$synonyms = $post['reload_seo_synonyms'];
				}
				else
				{
					$synonyms = '';
				}

				Mage::getModel('reload_seo/score')->loadById($id, $type)->setKeywords($keywords)->setSynonyms($synonyms)->save();
			}
		}
		catch(Exception $ex)
		{
			//Hmz.
			Mage::getSingleton('adminhtml/session')->addError(Mage::helper('reload_seo')->__('Something went wrong while updating the ' . $type . ' SEO status.'));
		}

		$storeId = (int) Mage::app()->getRequest()->getParam('store');
		Mage::helper('reload_seo')->addScoreUpdateRequest($id, $type, $storeId);
	}

	/**
	 * catalogProductCollectionLoadBefore is called at the catalog_product_collection_load_before event.
	 * 
	 * @param  Varien_Event_Observer $observer
	 * @return void
	 */
	public function catalogProductCollectionLoadBefore($observer)
	{
		try
		{
			$storeId = (int) Mage::app()->getRequest()->getParam('store');

			//Obtain the collection from the observer.
			$collection = $observer->getCollection();

			//Add an left join to load the scores and colors from the scores table also load the product when the 
			//score object does not exist.
			$collection = $collection->getSelect()->joinLeft(
	            array(
	                'scores' => Mage::getSingleton('core/resource')->getTableName('reload_seo/score')
	            ), 
	            "e.entity_id = scores.reference_id AND scores.type = 'product' AND scores.store_id = '" . $storeId . "'", 
	            array(
	                'seo_score' => 'scores.score', 
	                'seo_color' => 'scores.color'
	            )
	        );

			//Obtain the session to get the sorting field and the sorting direction.
			$session = Mage::getSingleton('adminhtml/session');

			if($session->getData('productGridsort') === 'seo_score' && $session->getData('productGriddir') != null)
			{
				//The user wants to sort by the score, we need to handle this ourselves.
				$collection = $collection->order('scores.score '. strtoupper($session->getData('productGriddir')));
			}

			//Set the collection in the observer so it gets used.
	        $observer->setCollection($collection);
	    }
	    catch(Exception $ex)
	    {
	    	//Hmzzz
	    	Mage::getSingleton('adminhtml/session')->addError(Mage::helper('reload_seo')->__('Something went wrong while loading the product SEO statusses.'));
	    }
	}
	/**
	 * prepareLayoutAfter is called at the core_block_abstract_prepare_layout_after event.
	 * 
	 * @param  Varien_Event_Observer $observer
	 * @return void
	 */
	public function prepareLayoutAfter($observer)
	{
		//Obtain the block which is being prepared.
		$block = $observer->getBlock();
		if($block instanceof Mage_Adminhtml_Block_Catalog_Product_Grid)
		{
			//If the block is a product grid, we want to add an seo_score column with a custom renderer.
			//Add the column after the entity_id column.
	        $block->addColumnAfter('seo_score',
	            array(
	                'header' => Mage::helper('reload_seo')->__('SEO Score'),
	                'width' => '50px',
	                'index' => 'seo_score',
	                'renderer' => 'Reload_Seo_Block_Adminhtml_Products_Renderer',
	                'align' => 'center',
	                'filter'    => false,
	        ), 'entity_id');
		}
	}

	/**
	 * prepareProductGridMassactions is called at the adminhtml_catalog_product_grid_prepare_massaction event.
	 * 
	 * @param  Varien_Event_Observer $observer
	 * @return void
	 */
	public function prepareProductGridMassactions($observer)
	{
		//Obtain the block in which the massactions are being prepared.
		$block = $observer->getBlock();
		if($block instanceof Mage_Adminhtml_Block_Catalog_Product_Grid)
		{
			//If the block is an product grid obtain the massactions grid.
			$massactions = $block->getMassactionBlock();
			if($massactions != null)
			{
				//Add a mass action for updating the seo scores.
				$massactions->addItem('mass_update_seo', array(
	                'label' => Mage::helper('reload_seo')->__('Update SEO statusses'),
	                'url'   => $block->getUrl('adminhtml/seo/updateproducts', array('_current'=>true))
	            ));
			}
		}
	}

	/**
	 * afterToHtml is called at the core_block_abstract_to_html_after event.
	 * 
	 * @param  Varien_Event_Observer $observer
	 * @return void
	 */
	public function afterToHtml($observer)
	{
		//Obtain the block and the transport object.
		$block = $observer->getBlock();
		$transport = $observer->getTransport();		

		$html = $transport->getHtml();
		if($block instanceof Mage_Adminhtml_Block_Catalog_Category_Edit_Form)
		{
			//If the block is an category edit form create an Reload_Seo_Block_Adminhtml_Seo block.

			$seoBlock = $block->getLayout()->createBlock(
				'Reload_Seo_Block_Adminhtml_Seo',
				'seoscore',
				array(
					'is_product_view' => '0'
				)
			);

			//Append the html to the Mage_Adminhtml_Block_Catalog_Category_Edit_Form it's html.
			$html .= $seoBlock->toHtml();
		}
		elseif($block instanceof Mage_Adminhtml_Block_Catalog_Form_Renderer_Fieldset_Element && $block->getAttribute() != null)
		{
			try
			{
				//We want to add an fake attribute for the seo keywords.
				$attribute = $block->getAttribute();

				//The $attribute->getEntityType() function contains a Mage::throw.
				if($attribute->getAttributeCode() === 'name' && $attribute->getEntityType() != null && ($attribute->getEntityType()->getEntityTypeCode() === 'catalog_product' || $attribute->getEntityType()->getEntityTypeCode() === 'catalog_category'))
				{
					$value = '';
					if(Mage::registry('seo_score') != null)
					{
						//This is an edit action.
						$scoreObject = Mage::registry('seo_score');
						$value = $scoreObject->getKeywords();

						//Update the product or category with the keywords, default keywords and if the defualt flag is set or not.
						$block->getDataObject()->setReloadSeoKeywords($scoreObject->getKeywords());
						$block->getDataObject()->setAttributeDefaultValue('reload_seo_keywords', $scoreObject->getDefaultKeywords());

						if($scoreObject->getKeywords() != null  && $scoreObject->getKeywords() != $scoreObject->getDefaultKeywords())
						{
							$block->getDataObject()->setExistsStoreValueFlag('reload_seo_keywords');
						}
					}

					//Clone the attribute 
					$keywordsAttribute = clone $attribute;
					$keywordsAttribute->setAttributeCode('reload_seo_keywords');

					$keywordsElement = new Varien_Data_Form_Element_Text(array(
						'label' => 'SEO keyword',
						'html_id' => 'reload_seo_keywords',
						'name' => 'reload_seo_keywords',
						'class' => 'input-text reload-seo-keywords-field',
						'entity_attribute' => $keywordsAttribute,
						'value' => $value
					));

					$keywordsElement->setForm($block->getElement()->getForm());

					$html .= $block->render($keywordsElement);

					$value = '';
					if(Mage::registry('seo_score') != null)
					{
						//This is an edit action.
						$scoreObject = Mage::registry('seo_score');
						$value = $scoreObject->getSynonyms();

						//Update the product or category with the synonyms, default synonyms and if the defualt flag is set or not.
						$block->getDataObject()->setReloadSeoSynonyms($scoreObject->getSynonyms());
						$block->getDataObject()->setAttributeDefaultValue('reload_seo_synonyms', $scoreObject->getDefaultSynonyms());

						if($scoreObject->getSynonyms() != null  && $scoreObject->getSynonyms() != $scoreObject->getDefaultSynonyms())
						{
							$block->getDataObject()->setExistsStoreValueFlag('reload_seo_synonyms');
						}
					}

					//Clone the attribute 
					$synonymsAttribute = clone $attribute;
					$synonymsAttribute->setAttributeCode('reload_seo_synonyms');

					$synonymsElement = new Varien_Data_Form_Element_Text(array(
						'label' => 'SEO synonyms',
						'html_id' => 'reload_seo_synonyms',
						'name' => 'reload_seo_synonyms',
						'class' => 'input-text reload-seo-synonyms-field',
						'entity_attribute' => $synonymsAttribute,
						'value' => $value
					));

					$synonymsElement->setForm($block->getElement()->getForm());

					$html .= $block->render($synonymsElement);
				}
			}
			catch(Exception $ex)
			{

			}			
		}

		if($block instanceof Mage_Adminhtml_Block_Catalog_Product_Edit || $block instanceof Mage_Adminhtml_Block_Catalog_Product_Grid || $block instanceof Mage_Adminhtml_Block_Catalog_Category_Edit_Form)
		{
			$vars = array(
				//Obtain the API key from the configuration
				'api_key' => Mage::getStoreConfig('reload/reload_seo_group/reload_seo_key'),

				//Create the validate key url.
				'check_url' => Mage::helper('reload_seo')->buildUrl('validate_key', array('key' => Mage::getStoreConfig('reload/reload_seo_group/reload_seo_key'), 'website' => Mage::getBaseUrl())),

				//Create a set with default messages.
				'messages' => array(
					'empty_key' => Mage::helper('reload_seo')->__("No API key given, please enter your API key in the <a href='%s'>configuration</a>.", Mage::helper("adminhtml")->getUrl('adminhtml/system_config/edit/section/reload')),
					'default_invalid_message' => Mage::helper('reload_seo')->__("The given API key is invalid, please enter a valid API key in the <a href='%s'>configuration</a>.", Mage::helper("adminhtml")->getUrl('adminhtml/system_config/edit/section/reload'))
				),

				//Get the config url.
				'config_url' => Mage::helper("adminhtml")->getUrl('adminhtml/system_config/edit/section/reload')
			);

			$html .= '<script type="text/javascript">reloadseo.checkKey(' . json_encode($vars) . ');</script>';
		}

		if($block instanceof Mage_Adminhtml_Block_Page_Head || $block instanceof Mage_Adminhtml_Block_Catalog_Category_Edit_Form)
		{
			//Load the request queue.
			$requests = Mage::helper('reload_seo')->getScoreUpdateRequests();
			$requestsWithData = array();
			foreach($requests as $requestKey => $request)
			{
				//Load the category or product.
				$item = Mage::getModel('catalog/' . $request['type'])
					->setStoreId($request['store'])
					->load($request['id']);

				//Obtain the data for the update.
				$requestsWithData[] = Mage::helper('reload_seo')->getDataForUpdate($item, $requestKey);
			}

			if(count($requestsWithData) > 0)
			{
				$message = Mage::helper('reload_seo')->__('The SEO scores are being updated.');
				$doneMessage = Mage::helper('reload_seo')->__('The SEO scores have been updated.');

				//Execute the javascript function to update the scores.
				$html .= '<script type="text/javascript">reloadseo.processUpdates(' . json_encode($requestsWithData) . ', ' . json_encode($message) . ', ' . json_encode($doneMessage) . ');</script>';
			}
		}

		//Store the complete html in the transport object.
		$transport->setHtml($html);
	}
}
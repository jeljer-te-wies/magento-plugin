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
		$observerObject->setAttributeDefaultValue('reload_seo_keywords', $scoreObject->getDefaultKeywords());

		if($scoreObject->getKeywords() != null  && $scoreObject->getKeywords() != $scoreObject->getDefaultKeywords())
		{
			$observerObject->setExistsStoreValueFlag('reload_seo_keywords');
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
		$post = Mage::app()->getRequest()->getPost('product');
		try
		{
			if($post != null)
			{
				if(array_key_exists('reload_seo_keywords', $post))
				{
					Mage::getModel('reload_seo/score')->loadById($observer->getProduct()->getId(), 'product')->setKeywords($post['reload_seo_keywords'])->save();
				}
				else
				{
					Mage::getModel('reload_seo/score')->loadById($observer->getProduct()->getId(), 'product')->setKeywords('')->save();
				}
			}
		}
		catch(Exception $ex)
		{
			//Hmz.
			Mage::getSingleton('adminhtml/session')->addError(Mage::helper('reload_seo')->__('Something went wrong while updating the product SEO status.'));
		}
	}

	/**
	 * catalogCategorySaveAfter is called at the catalog_category_save_after event.
	 * 
	 * @param  Varien_Event_Observer $observer
	 * @return void
	 */
	public function catalogCategorySaveAfter($observer)
	{
		$post = Mage::app()->getRequest()->getPost('general');
		try
		{
			if($post != null)
			{
				if(array_key_exists('reload_seo_keywords', $post))
				{
					Mage::getModel('reload_seo/score')->loadById($observer->getCategory()->getId(), 'category')->setKeywords($post['reload_seo_keywords'])->save();
				}
				else
				{
					Mage::getModel('reload_seo/score')->loadById($observer->getCategory()->getId(), 'category')->setKeywords('')->save();
				}
			}
		}
		catch(Exception $ex)
		{
			//Hmz.
			Mage::getSingleton('adminhtml/session')->addError(Mage::helper('reload_seo')->__('Something went wrong while updating the category SEO status.'));
		}
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
	 * prepareLayoutBefore is called at the core_block_abstract_prepare_layout_before event.
	 * 
	 * @param  Varien_Event_Observer $observer
	 * @return void
	 */
	public function prepareLayoutBefore($observer)
	{
		//Obtain the block which is being prepared.
		$block = $observer->getBlock();
		if($block instanceof Mage_Adminhtml_Block_Catalog_Product_Edit || $block instanceof Mage_Adminhtml_Block_Catalog_Product_Grid || $block instanceof Mage_Adminhtml_Block_Catalog_Category_Edit_Form)
		{
			//If the block is an Mage_Adminhtml_Block_Catalog_Product_Edit or Grid or Category_Edit_Form we want to check the config.
			try
			{
				//Check the configuration.
				Mage::helper('reload_seo')->validConfig();
			}
			catch(Exception $ex)
			{
				//The configuration is not valid, show the message.
				Mage::getSingleton('adminhtml/session')->addError($ex->getMessage());
			}
		}

		if($block instanceof Mage_Adminhtml_Block_Catalog_Product_Edit || $block instanceof Mage_Adminhtml_Block_Catalog_Category_Edit_Form)
		{
			//If the block is Product_Edit or Category_Edit block we want to load the score from the API.
			if($block instanceof Mage_Adminhtml_Block_Catalog_Product_Edit)
			{
				//Get the item and prepare the error message for later use.
				$item = $block->getProduct();
				$errorMessage = Mage::helper('reload_seo')->__('Something went wrong while updating the product SEO status.');

				if($item->getData('attribute_set_id') === null)
				{
					//This is a new product without an attribute set yet, so do nothing.
					return;
				}

				if(!Mage::helper('reload_seo')->shouldProductBeChecked($item))
				{
					//If the product has been disabled, do not check it.
					Mage::getSingleton('adminhtml/session')->addNotice(Mage::helper('reload_seo')->__('This product has been disabled or is invisble, the SEO-score will not be calculated.'));
					return;
				}
			}
			else
			{
				//Get the item and prepare the error message for later use.
				$item = $block->getCategory();
				$errorMessage = Mage::helper('reload_seo')->__('Something went wrong while updating the category SEO status.');
			}

			try
			{
				//Ask the helper to update the item.
				Mage::helper('reload_seo')->updateItem($item);
			}
			catch(Exception $ex)
			{
				//Something went wrong while updating, show the error message.
				Mage::getSingleton('adminhtml/session')->addError($errorMessage);
			}
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

		if($block instanceof Mage_Adminhtml_Block_Catalog_Category_Edit_Form)
		{
			//If the block is an category edit form create an Reload_Seo_Block_Adminhtml_Seo block.
			$html = $transport->getHtml();

			$seoBlock = $block->getLayout()->createBlock(
				'Reload_Seo_Block_Adminhtml_Seo',
				'seoscore',
				array(
					'is_product_view' => '0'
				)
			);

			//Append the html to the Mage_Adminhtml_Block_Catalog_Category_Edit_Form it's html.
			$html .= $seoBlock->toHtml();

			//Store the complete html in the transport object.
			$transport->setHtml($html);
		}
		elseif($block instanceof Mage_Adminhtml_Block_Catalog_Form_Renderer_Fieldset_Element && $block->getAttribute() != null)
		{
			//We want to add an fake attribute for the seo keywords.
			$attribute = $block->getAttribute();

			try
			{
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

					$html = $transport->getHtml();

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
					$transport->setHtml($html);
				}
			}
			catch(Exception $ex)
			{

			}			
		}
	}
}
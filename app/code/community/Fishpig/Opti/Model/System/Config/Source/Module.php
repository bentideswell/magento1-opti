<?php
/**
 * @category	Fishpig
 * @package		Fishpig_Opti
 * @license		http://fishpig.co.uk/license.txt
 * @author		Ben Tideswell <help@fishpig.co.uk>
 */

class Fishpig_Opti_Model_System_Config_Source_Module
{
	/**
	 * Options cache
	 *
	 * @return array
	 */
	protected $_options = null;
	
	/**
	 * Modules to ignore
	 *
	 * @var array
	 */
	protected $_toIgnore = array(
		'Aschroder_Email',
		'AW_Core',
		'Ebizmarts_SagePayReporting',
#		'Ebizmarts_SagePaySuite',
		'Fishpig_Bolt',
		'Fishpig_CrossLink',
		'Fishpig_CouponUrl',
		'Fishpig_NoBots',
#		'Idev_OneStepCheckout',
#		'IWD_OnepageCheckout',
		'MageWorx_XSitemap',
		'Mage_Api',
		'Mage_Authorizenet',
		'Mage_Captcha',
		'Mage_Centinel',
#		'Mage_Checkout',
		'Mage_Core',
#		'Mage_Customer',
		'Mage_Directory',
#		'Mage_Downloadable',
		'Mage_GiftMessage',
		'Mage_GoogleCheckout',
		'Mage_Install',
		'Mage_Media',
		'Mage_Newsletter',
		'Mage_Oauth',
		'Mage_Paygate',
		'Mage_Paypal',
		'Mage_PaypalUk',
		'Mage_Persistent',
		'Mage_Poll',
		'Mage_ProductAlert',
		'Mage_Rss',
#		'Mage_Sales',
		'Mage_Sendfriend',
		'Mage_Shipping',
#		'Mage_Wishlist',
		'Mage_XmlConnect',
		'Phoenix_Moneybookers',
	);
	
	/**
	 * Modules to include regardless of frontName
	 *
	 * @return array
	 */
	protected $_forceInclude = array(
		'amshopby' => 'Amasty_Shopby',
	);
	
	/**
	 * Retrieve the option array of modules
	 *
	 * @return array
	 */
	public function toOptionArray()
	{
		if (!is_null($this->_options)) {
			return $this->_options;
		}

		$config = Mage::app()->getConfig();
		$modules = (array)$config->getNode('modules')->asArray();
		
		ksort($modules);

		foreach($modules as $module => $data) {
			if (in_array($module, $this->_toIgnore)) {
				unset($modules[$module]);
			}
		}

		$this->_options = array();

		foreach($modules as $module => $data) {
			if (isset($data['active']) && $data['active'] === 'true') {
				
				if (!in_array($module, $this->_forceInclude)) {
					$frontNames = (array)$config->getNode('frontend')->xpath('routers//args[module="' . $module . '"]/frontName');
				
					if (count($frontNames) === 0) {
						continue;
					}
					
					$frontName = array_shift($frontNames);
				}
				else {
					$frontName = array_search($module, $this->_forceInclude);
				}

				$this->_options[] = array(
					'value' => (string)$frontName,
					'label' => $module,
				);
			}
		}

		return $this->_options;
	}
}

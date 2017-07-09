<?php
/**
 * @category	Fishpig
 * @package		Fishpig_Opti
 * @license		http://fishpig.co.uk/license.txt
 * @author		Ben Tideswell <help@fishpig.co.uk>
 * @info			http://fishpig.co.uk/magento-optimisation.html
 */

class Fishpig_Opti_Helper_Data extends Mage_Core_Helper_Abstract
{
	/**
	 * Debug flag
	 *
	 * @const bool
	 */
	const DEBUG = false;
	
	/**
	 * Determine whether the extension has been enabled/disabled via the config
	 *
	 * @return bool
	 */
	public function isEnabled()
	{
		return Mage::getStoreConfigFlag('opti/conditions/enabled') || self::DEBUG === true;
	}
}
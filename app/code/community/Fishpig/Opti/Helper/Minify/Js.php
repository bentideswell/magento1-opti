<?php
/**
 * @category	Fishpig
 * @package		Fishpig_Opti
 * @license		http://fishpig.co.uk/license.txt
 * @author		Ben Tideswell <help@fishpig.co.uk>
 * @info			http://fishpig.co.uk/magento-optimisation.html
 */

class Fishpig_Opti_Helper_Minify_Js extends Fishpig_Opti_Helper_Minify_Abstract
{
	/**
	 *
	**/
	public function getClassType()
	{
		return Fishpig_Opti_Model_Observer::TYPE_JS;
	}
	
	/**
	 *
	**/
	public function getRegexPatterns()
	{
		$patterns = array(
			'<script[^>]{1,}src=[\'"]{1}(.*)[\'"]{1}[^>]{0,}><\/script>' => array('type' => 'external', 'attribute' => 'src'),
		);
		
		if ($this->isMinifyInlineAllowed()) {
			$patterns['<script[^>]{0,}>(.*)<\/script>'] = array('type' => 'inline', 'attribute' => null);
		}
		
		return $patterns;
	}
		
	/**
	 *
	 *
	 * @return bool
	**/
	public function isDeferAllowed($storeId = null)
	{
		return false;
	}
	
	/**
	 * Get the sources
	 *
	 * @return array
	 **/
	protected function _getSources()
	{
		return array(
			Mage::getBaseDir('skin') . DS => Mage::getBaseUrl('skin'),
			Mage::getBaseDir() . DS . 'js' . DS => Mage::getBaseUrl('js'),
			Mage::getBaseDir('media') . DS . 'js' . DS => Mage::getBaseUrl('media') . 'js/',
		);
	}
	
	/**
	 * Get the target directory
	 *
	 * @return string
	 **/
	protected function _getTargetDir()
	{
		return Mage::getBaseDir('media') . DS . 'js' . DS;
	}
	
	/**
	 * Get the target URL
	 *
	 * @return string
	 **/
	protected function _getTargetUrl()
	{
		return Mage::getBaseUrl('media') . 'js/';
	}

	/**
	 * Minify the JavaScript string
	 *
	 * @param string $js
	 * @return string
	 */
	public function minify($js, $url = null)
	{		
		if (trim($js) === '') {
			return '';
		}

		$js = $this->_removeExtraComments($js);

#		$cdata = !$url && strpos($js, '&') !== false || strpos($js, '<') !== false;

		if (!$this->_includeLibrary('JSMin')) {
			return $js;
		}
		
		try {
			$js = JSMin::minify($js);
		}
		catch (Exception $e) {}

		return $js;
		return $cdata ? "//<![CDATA[\n" . $js . "//]]>" : $js;
	}
}

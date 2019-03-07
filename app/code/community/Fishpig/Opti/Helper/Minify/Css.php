<?php
/**
 * @category	Fishpig
 * @package		Fishpig_Opti
 * @license		http://fishpig.co.uk/license.txt
 * @author		Ben Tideswell <help@fishpig.co.uk>
 * @info			http://fishpig.co.uk/magento-optimisation.html
 */

class Fishpig_Opti_Helper_Minify_Css extends Fishpig_Opti_Helper_Minify_Abstract
{	
	/**
	 *
	**/
	const MAX_CSS_LENGTH = 5000;
	
	/**
	 * Cache for CSSmin object
	 *
	 * @var CSSmin
	**/
	protected $_cssMin = null;
	
	/**
	 *
	**/
	public function getClassType()
	{
		return Fishpig_Opti_Model_Observer::TYPE_CSS;
	}
	
	/**
	 *
	**/
	public function getRegexPatterns()
	{
		$patterns = array(
			'<link[^>]{1,}href=[\'"]{1}(.*)[\'"]{1}[^>]{0,}>' => array('type' => 'external', 'attribute' => 'href'),
		);
		
		if ($this->isMinifyInlineAllowed()) {
			$patterns['<style[^>]{0,}>(.*)<\/style>'] = array('type' => 'inline', 'attribute' => null);
		}
		
		return $patterns;
	}
	
	/**
	 *
	**/
	protected function _getSources()
	{
		$sources = array(
			Mage::getBaseDir('media') . DS . 'css' . DS => Mage::getBaseUrl('media') . 'css/',
			Mage::getBaseDir('media') . DS . 'css_secure' . DS => Mage::getBaseUrl('media') . 'css_secure/',
			Mage::getBaseDir('skin') . DS => Mage::getBaseUrl('skin'),
		);
		
    if (Mage::helper('opti')->isWordPressIntegrationInstalled()) {
      if ($path = Mage::helper('wordpress')->getWordPressPath()) {
        $sources[$path] = Mage::helper('wordpress')->getBaseUrl();
      }
    }

    return $sources;
	}
	
	/**
	 *
	**/
	protected function _getTargetDir()
	{
		return Mage::getBaseDir('media') . DS . (Mage::app()->getStore()->isCurrentlySecure() ? 'css_secure' : 'css') . DS;
	}
	
	/**
	 *
	**/
	protected function _getTargetUrl()
	{
		return Mage::getBaseUrl('media') . (Mage::app()->getStore()->isCurrentlySecure() ? 'css_secure' : 'css') . '/';
	}
	
	/**
	 * Minify the CSS string
	 *
	 * @param string $css
	 * @param string $baseUrl
	 * @return string
	 */
	public function minify($css, $url = null, $id = 1)
	{
		// Fix for the big issue
		$css = str_replace('\"', '"', $css); 
		
		// Remove comments that don't want to go
		$css = $this->_removeExtraComments($css);

		// If SVG data is found, add it to the safe
		$svgSafe = array();

		if (strpos($css, 'data:image/svg+xml') !== false) {
			if (preg_match_all('/url\([ ]{0,}"data:image\/svg\+xml.*"[ ]{0,}\)/U', $css, $svgMatches)) {
				foreach($svgMatches[0] as $svgMatch) {
					$svgKey = '__SVG_KEY__' . count($svgSafe);
					$svgSafe[$svgKey] = $svgMatch;
					
					$css = str_replace($svgSafe[$svgKey], $svgKey, $css);
				}
			}
		}

		$calcSafe = array();
		
		if (strpos($css, 'calc(') !== false) {
			if (preg_match_all('/(calc\(.*)([;\}]{1})/Us', $css, $matches)) {
				foreach($matches[1] as $key => $match) {
					$endChar = $matches[2][$key];
					
					$css = str_replace($match, 'calc-fix-' . $key . ';', $css);
					
					$calcSafe[$key] = $match;
				}
			}
		}
		
		// Minify the CSS
		$css = $this->_getCssMin()->run($css);

		// If SVG data was extracted, get it from the safe and add back in
		if (count($svgSafe) > 0) {
			foreach($svgSafe as $svgKey => $svgMatch) {
				$css = str_replace($svgKey, $svgMatch, $css);
			}
		}

		if (count($calcSafe) > 0) {
			foreach($calcSafe as $key => $match) {
				$css = str_replace('calc-fix-' . $key, $match, $css);
			}
		}
		
		/*
		// Fix broken calc's
		if (strpos($css, 'calc(') !== false) {
			if (preg_match_all('/calc\(.*[;\}]{1}/Us', $css, $matches)) {
				foreach($matches[0] as $match) {
					$fixed = str_replace(array('+', '-'), array(' + ', ' - '), $match);	
					$fixed = preg_replace('/([\*+-]{1}) - /', '$1 -', $fixed);

					$css = str_replace($match, $fixed, $css);
				}
			}
			
			$css = str_replace(
				array('min - width', 'min - height', 'max - width', 'max - height'),
				array('min-width', 'min-height', 'max-width', 'max-height'),
				$css
			);
		}
*/
		// Let's trim the fat from those base URLs
		$baseUrl = $url === null ? null : dirname($url);

		if (!is_null($baseUrl)) {
			$baseUrl = rtrim($baseUrl, '/') . '/';
	
			if (preg_match_all('/url\((.*)\)/iU', $css, $matches)) {
				foreach($matches[0] as $it => $find) {
					$url = trim($matches[1][$it], "'\"");

					if (strpos($url, 'data:') === 0) {
						// Ignore data URIs
					}
					else if (strpos($url, 'http://') === false && strpos($url, 'https://') === false && substr($url, 0, 1) !== '/') {
						$url = $baseUrl . trim($url, '" \'/');
	
						$css = str_replace($find, "url('{$url}')", $css);
					}
				}
			}
		}

		return trim($css);
	}

	/**
	 * Get the CSSmin object
	 *
	 * @return CSSmin
	**/
	protected function _getCssMin()
	{
		if ($this->_cssMin === null) {
			$this->_cssMin = new tubalmartin\CssMin\Minifier;
		}
		
		return $this->_cssMin;
	}
	
	/**
	 *
	 * @param int $storeId - null
	 * @return bool
	**/
	public function isMoveToBottomAllowed($storeId = null)
	{
		return false;
	}
	
	/**
	 *
	 * @param int $storeId - null
	 * @return bool
	**/
	public function isDeferAllowed($storeId = null)
	{
		return false;
	}
}

<?php
/**
 * @category	Fishpig
 * @package		Fishpig_Opti
 * @license		http://fishpig.co.uk/license.txt
 * @author		Ben Tideswell <help@fishpig.co.uk>
 * @info			http://fishpig.co.uk/magento-optimisation.html
 */

class Fishpig_Opti_Helper_Minify_Abstract extends Mage_Core_Helper_Abstract
{
	/**
	 *
	**/
	const DEBUG = false;

	/*
	 *
	 *
	 */
	public function isAllowedForModule($module, $storeId = null)
	{
		return in_array(
			$module, 
			(array)explode(',', trim(Mage::getStoreConfig('opti/' . $this->getClassType() . '/modules', $storeId), ','))
		);
	}

	/**
	 *
	 * @param int $storeId - null
	 * @return bool
	**/
	public function isMinifyAllowed($storeId = null)
	{
		return Mage::getStoreConfigFlag('opti/' . $this->getClassType() . '/minify', $storeId);
	}
	
	/**
	 *
	 * @param int $storeId - null
	 * @return bool
	**/
	public function isMinifyMinfiedAllowed($storeId = null)
	{
		return Mage::getStoreConfigFlag('opti/' . $this->getClassType() . '/minify_dot_min', $storeId);
	}
	
	/**
	 *
	 * @param int $storeId - null
	 * @return bool
	**/
	public function isMinifyInlineAllowed($storeId = null)
	{
		return Mage::getStoreConfigFlag('opti/' . $this->getClassType() . '/minify_inline', $storeId);
	}
	
	/**
	 *
	 * @param int $storeId - null
	 * @return bool
	**/
	public function isMoveToBottomAllowed($storeId = null)
	{
		return Mage::getStoreConfigFlag('opti/' . $this->getClassType() . '/move_to_bottom', $storeId);
	}
	
	/**
	 *
	 * @param int $storeId - null
	 * @return bool
	**/
	public function isDeferAllowed($storeId = null)
	{
		return Mage::getStoreConfigFlag('opti/' . $this->getClassType() . '/defer', $storeId);
	}
	
	/**
	 *
	 * @param int $storeId - null
	 * @return bool
	**/
	public function canVersionFilename($storeId = null)
	{
		return Mage::getStoreConfigFlag('opti/' . $this->getClassType() . '/version_filenames', $storeId);
	}
	
	/**
	 *
	 * @param int $storeId - null
	 * @return bool
	**/
	public function minifyUrl($url)
	{
  	// Add protocol to protocol-less URLs
  	if (substr($url, 0, 2) === '//') {
      $url = 'http' . (Mage::app()->getStore()->isCurrentlySecure() ? 's' : '') . ':' . $url;
  	}

		# If file already minified, don't minify anymore
		if (strpos(basename($url), '.min.') !== false) {
			if (!$this->isMinifyMinfiedAllowed()) {
				return $url;
			}
		}
		
		# If file already minified by Opti, no point in minifying again
		if (strpos($url, 'opti-') !== false) {
			return $url;
		}

		if (!($sources = $this->_getSources())) {
			return $url;
		}

		$targetDir = $this->_getTargetDir();
		$targetUrl = $this->_getTargetUrl();

		if (!is_dir($targetDir)) {
			if (!(@mkdir($targetDir))) {
				return $url;
			}
		}

		$localFile = false;
		$queryString = '';
		
		foreach($this->_getSources() as $sourceDir => $sourceUrl) {
			if (strpos($url, $sourceUrl) !== 0) {
				continue;
			}

			$localFilename = substr($url, strlen($sourceUrl));
			
			if (($queryPosition = strpos($localFilename, '?')) !== false) {
				$queryString = substr($localFilename, $queryPosition);
				$localFilename = substr($localFilename, 0, $queryPosition);
			}
			
			$localFile = $sourceDir . $localFilename;
			
			if (!is_file($localFile)) {
				break;
			}
		}

		if (!$localFile || !is_file($localFile)) {
			return $url;
		}

		$minifiedLocalFilename = 'opti-' . $this->getStoreId() . '-' . str_replace(DS, '-', $localFilename);
		$fileExtension = substr($minifiedLocalFilename, strpos($minifiedLocalFilename, '.'));

		if ($this->canVersionFilename()) {
			$minifiedLocalFilename = basename($minifiedLocalFilename, $fileExtension) . '-' . filemtime($localFile) . $fileExtension;			

      # If filename is longer than 32 chars, hash it to shorten it		
      if (strlen($minifiedLocalFilename) > 35) {
  			$minifiedLocalFilename = md5($minifiedLocalFilename) . $fileExtension;
      }
    }

		if (self::DEBUG || !is_file($targetDir . $minifiedLocalFilename)) {
			 if (($content = trim(file_get_contents($localFile))) === '') {
				 return $url;
			}

			if (($minfiedContent = trim($this->minify($content, $url))) === '') {
				$minfiedContent = $content;
			}

			@file_put_contents($targetDir . $minifiedLocalFilename, $minfiedContent);
				
			if (!is_file($targetDir . $minifiedLocalFilename)) {

				return $url;
			}
		}

		return $targetUrl . $minifiedLocalFilename;
	}
	
	/**
	 *
	 * @return
	**/
	public function minifyInline($data)
	{
		$data = str_replace(array('//<![CDATA[', '//]]>'), '', $data);
		
		return $this->minify($data);
	}

	/**
	 *
	 * @return
	**/
	protected function _getSources()
	{
  	return false;
	}
	
	/**
	 * Determine whether the refresh parameter is present
	 * This triggers a refresh of all cached CSS/JS files
	 *
	 * @return bool
	 */
	protected function _isRefresh()
	{
		return Mage::app()->getRequest()->getParam('___refresh') === 'opti'
			|| Fishpig_Opti_Helper_Data::DEBUG === true;
	}
	
	/**
	 * Get the store ID and apply padding
	 *
	 * @return string
	 */
	protected function _getStoreId()
	{
		return str_pad(Mage::app()->getStore()->getId(), 4, '0', STR_PAD_LEFT);
	}
	
	/**
	 * Include a minification library class file
	 *
	 * @param string $class
	 * @return bool
	 */
	protected function _includeLibrary($class)
	{


		return $result;
	}
	
	/**
	 * Remove the extra comments that won't be removed!
	 *
	 * @param string $s
	 * @return string
	**/
	protected function _removeExtraComments($s)
	{
		$s = str_replace("/*!", "/*", $s);
		
		return $s;
	}

	/**
	 * @return int
	**/	
	public function getStoreId()
	{
		return (int)Mage::app()->getStore()->getId();
	}
}

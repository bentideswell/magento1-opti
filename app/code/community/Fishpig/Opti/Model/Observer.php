<?php
/**
 * @category Fishpig
 * @package Fishpig_Opti
 * @author Ben Tideswell <help@fishpig.co.uk>
 * @info http://fishpig.co.uk/magento/extensions/minify/
 */

class Fishpig_Opti_Model_Observer extends Varien_Object
{
	/**
	 * Types
	 *
	 * @const string
	 **/
	const TYPE_JS = 'js';
	const TYPE_CSS = 'css';
	const TYPE_HTML = 'html';
	const SAFE_KEY_TEMPLATE = '<!--SF-%d-->';
	
	/**
	 * Allows storage of HTML in exchange for a key
	 * Key's can be swapped from HTML to reverse the process
	 *
	 * @var array
	 */
	protected $_safe = array();
  
  /*
   * @const bool
   */
  static protected $_libsIncluded = false;
  
	/**
	 * Minify and move content
	 *
	 * @param Varien_Event_Observer $observer
	 * @return $this
	 **/
	public function minifyAndMoveObserver(Varien_Event_Observer $observer)
	{
		if (!Mage::helper('opti')->isEnabled() || !$this->isAllowedRoute()) {
			return $this;
		}

    if (Mage::helper('opti')->isWordPressIntegrationInstalled()) {
      Mage::getSingleton('wordpress/observer')->injectWordPressContentObserver($observer);
    }
    
    $this->_includeMinifyLibs();
    
		$html = $observer
			->getEvent()
				->getFront()
					->getResponse()
						->getBody();

		$canUpdateBodyHtml = false;
		$elements = array();
		$htmlHelper = Mage::helper('opti/minify_html');
		$moduleName = Mage::app()->getRequest()->getModuleName();
		
		$helpers = array(
			self::TYPE_CSS => Mage::helper('opti/minify_css'),
			self::TYPE_JS => Mage::helper('opti/minify_js'),
		);
		
		$html = preg_replace_callback('/<pre[^>]{0,}>.*<\/pre>/Us', array($this, 'addToSafeCallback'), $html);
			
		foreach($helpers as $type => $helper) {
			if ($helper->isAllowedForModule($moduleName) && ($helper->isMinifyAllowed() || $helper->isMoveToBottomAllowed())) {
				$elements[$type] = $this->_getHtmlByTags($html, $helper->getRegexPatterns(), $type);
			}
		}

		if (count($elements) > 0) {
			$canUpdateBodyHtml = true;
			$deferRequire = false;

			foreach($elements as $type => $tags) {
				$helper = $helpers[$type];

				foreach($tags as $position => $tag) {	
  								
					if ($helper->isMinifyAllowed()) {
						if (strpos($tag['original'], 'opti-skip-minify') !== false) {
							// Do nothing as minify is set to skip
						}
						else if (isset($tag['urls'])) {
							foreach($tag['urls'] as $key => $url) {
								$tag['urls'][$key] = $helper->minifyUrl($url);
								$tag['optimised'] = str_replace($url, $tag['urls'][$key], $tag['optimised']);
							}
						}
						else if ($tag['type'] === 'inline') {
							$tag['optimised'] = $helper->isMinifyInlineAllowed() ? $helper->minifyInline($tag['optimised']) : "\n" . $tag['optimised'] . "\n";
						}
						
						if (isset($tag['inline_wrapper'])) {
							$tag['optimised'] = sprintf($tag['inline_wrapper'], $tag['optimised']);
						}
						
						if (isset($tag['ie_tags'])) {							
							$tag['optimised'] = sprintf($tag['ie_tags'], $tag['optimised']);
						}
					}
					else {
						$tag['optimised'] = $tag['original'];
					}
					
					// Defer (only applies to CSS currently)
					if ($helper->isDeferAllowed() && isset($tag['optimised']) && isset($tag['urls'])) {
						if (!isset($tag['ie_tags'])  || empty($tag['ie_tags'])) {
							$deferRequire = true;
							$tag['optimised'] = '<noscript class="defer-css">' . $tag['optimised'] . '</noscript>';
						}
					}

					$html = str_replace($tag['original'], $tag['optimised'], $html);
					
					$elements[$type][$position] = $tag;
				}
			}

			if ($deferRequire) {
				$deferCssJs = '<script type="text/javascript">var fpoptidcss=function(){var d=document;var n=d.getElementsByClassName("defer-css");var r=d.createElement("div");for(i=0; i<n.length;i++){r.innerHTML+=n[i].textContent;}d.body.appendChild(r);for(i=0; i<n.length;i++){n[i].parentElement.removeChild(n[i]);}};var raf=requestAnimationFrame||mozRequestAnimationFrame||webkitRequestAnimationFrame||msRequestAnimationFrame;if(raf){raf(function(){window.setTimeout(fpoptidcss,0);});}else{window.addEventListener("load",fpoptidcss);}</script>';
				
				$html = substr($html, 0, strpos($html, '</body>')) . "\n" . $deferCssJs . "\n" . substr($html, strpos($html, '</body>'));
			}

			$inTransit = '';
			$newLine = $htmlHelper->isMinifyAllowed() ? '' : "\n";
	
			foreach($elements as $type => $tags) {
				ksort($tags);
	
				$helper = Mage::helper('opti/minify_' . $type);
				
				foreach($tags as $tag) {
					$this->removeFromSafe($tag['safe_key']);
					
					if ($helper->isMoveToBottomAllowed() && strpos($tag['original'], 'opti-skip-move') === false) {
						$html = str_replace($tag['safe_key'], '', $html);
						$inTransit .= $tag['optimised'] . $newLine;
					}
					else {
						$html = str_replace($tag['safe_key'], $tag['optimised'], $html);
					}
				}
			}

			if ($inTransit) {
				$html = substr($html, 0, strpos($html, '</body>')) . "\n" . $inTransit . "\n" . substr($html, strpos($html, '</body>'));
			}
			
			$html = str_replace(array(' opti-skip-minify="true"', ' opti-skip-move="true"'), '', $html);
		}

		// Empty the safe before HTML minification
		foreach($this->_safe as $key => $value) {
			unset($this->_safe[$key]);

			$html = str_replace(sprintf(self::SAFE_KEY_TEMPLATE, $key), $value, $html);
		}

		if ($htmlHelper->isAllowedForModule($moduleName) && $htmlHelper->isMinifyAllowed()) {
			$canUpdateBodyHtml = true;
			$html = $htmlHelper->minify($html);
		}

		if ($canUpdateBodyHtml) {
			$observer->getEvent()
				->getFront()
					->getResponse()
						->setBody($html);
		}

		return $this;
	}
	
	/**
	 * Get HTML by a specific tag
	 *
	 * @param string &$html
	 * @param array $ipatterns
	 * @param string $type
	 * @return array
	 **/
	protected function _getHtmlByTags(&$html, $ipatterns, $type)
	{
		$tags = array();
		$patterns = array();
		
		foreach($ipatterns as $ipattern => $options) {
			$patterns = array(
				'ie' => '/<\!--\[if[^\>]*>[\s]{0,}' . $ipattern . '[\s]{0,}<\!\[endif\]-->/sUi',
				'solo' => '/' . $ipattern . '/Usi',
			);

			foreach($patterns as $patternType => $pattern) {
				if (preg_match_all($pattern, $html, $mTags)) {
					foreach($mTags[0] as $key => $tag) {
						if ($type === self::TYPE_CSS && (strpos($tag, '<style') === false && !preg_match('/rel=[\'"]{1}stylesheet[\'"]{1}/Ui', $tag))) {
							continue;
						}

						$position = strpos($html, $tag);

						if ($position === false) {
							continue;
						}

						$safeKey = $this->addToSafe($tag, $html);

						if (preg_match('/(<\!--\[if[^>]*>).*(<\!\[endif\]-->)/sUi', $tag, $ieTags)) {
							$optimised =  trim(str_replace(array($ieTags[1], $ieTags[2]), '', $tag));
							$ieTags = $ieTags[1] . "\n%s\n" . $ieTags[2];
						}
						else {
							$ieTags = null;
							$optimised = $tag;
						}

						$tags[$position] = array(
							'type'=> $options['type'],
							'safe_key' => $safeKey, 
							'original' => $tag,
							'ie_tags' => $ieTags,
							'optimised' => $optimised,
							'pattern' => $pattern,
						);

						if ($options['type'] === 'external') {
							if (preg_match_all('/' . $options['attribute'] . '=[\'"]{1}(.*)[\'"]{1}/Us', $tag, $urlMatches)) {
								$tags[$position]['urls'] = $urlMatches[1];
							}
						}
						else {
							if (preg_match('/(<(script|style)[^>]{0,}>)(.*)(<\/\2>)/Us', $tag, $wrapper)) {
								$tags[$position]['inline_wrapper'] = $wrapper[1] . "%s" . $wrapper[4];
								$tags[$position]['optimised'] = trim(str_replace(array($wrapper[1], $wrapper[4]), '', $tags[$position]['optimised']));
							}
						}
					}
				}
			}
		}

		return $tags;
	}
		
	/**
	 * Determine whether to run on the route
	 *
	 * @return bool
	 */
	public function isAllowedRoute()
	{
		if (isset($_GET['isAjax'])) {
			return false;
		}

		if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
			return false;
		}

		$isTextHtml = false;
		
		foreach((array)Mage::app()->getResponse()->getHeaders() as $header) {
			if (isset($header['name']) && strtolower($header['name']) === 'content-type') {
				$isTextHtml = strpos($header['value'], 'text/html') !== false;
			}
		}

		if (!$isTextHtml) {
			return false;
		}

		return true;
	}
	
	/**
	 * Debug an array of HTML tags
	 *
	 * @param array $a
	 * @return void
	 **/
	protected function _debugTags($tags)
	{
		$tags = $this->__debugTags($tags);
		
		echo sprintf('<pre style="overflow-y:scroll;text-align: left !important;background:#fafafa;color: #444;padding:20px;border:2px solid #666;">%s</pre>', print_r($tags, true));
		exit;
	}
	
	/**
	 * Debug an array of HTML tags
	 *
	 * @param array $a
	 * @return array
	 **/
	protected function __debugTags($a)
	{
		foreach($a as $k => $v) {
			$a[$k] = is_array($v) ? $this->__debugTags($v) : htmlentities($v);
		}
		
		return $a;
	}
	
	/**
	 * Add some content to the safe
	 *
	 * @param string $html
	 * @return string 
	 */
	public function addToSafe($str, &$html)
	{
		$safeKey = $this->addToSafeCallback($str);

		$html = str_replace($str, $safeKey . str_repeat(' ', strlen($str) - strlen($safeKey)), $html);

		return $safeKey;
	}	
	
	/**
	 * Add some content to the safe
	 *
	 * @param string $html
	 * @return string 
	 */
	public function addToSafeCallback($str)
	{
		if (is_array($str)) {
			$str = count($str) === 0 ? '' : array_shift($str);
		}
		
		$key = count($this->_safe);
		$this->_safe[$key] = $str;	
		$safeKey = sprintf(self::SAFE_KEY_TEMPLATE, $key);
		
		return $safeKey;
	}	
	
	/**
	 *
	 * @param string $key
	 * @return mixed
	 **/
	public function removeFromSafe($key)
	{
		if (isset($this->_safe[$key])) {
			$value = $this->_safe[$key];
			unset($this->_safe[$key]);
			
			return $value;
		}
		
		return false;
	}
	
	/*
   *
   */
	protected function _includeMinifyLibs()
	{
  	if (!self::$_libsIncluded) {
    	self::$_libsIncluded = true;

      $files = array(
	      // JSMin
        Mage::getModuleDir('', 'Fishpig_Opti') . DS . 'lib' . DS . 'JSMin' . DS . 'JSMin.php',
        Mage::getModuleDir('', 'Fishpig_Opti') . DS . 'lib' . DS . 'JSMin' . DS . 'UnterminatedCommentException.php',
        Mage::getModuleDir('', 'Fishpig_Opti') . DS . 'lib' . DS . 'JSMin' . DS . 'UnterminatedRegExpException.php',
        Mage::getModuleDir('', 'Fishpig_Opti') . DS . 'lib' . DS . 'JSMin' . DS . 'UnterminatedStringException.php',
        
        // CSSMin
        Mage::getModuleDir('', 'Fishpig_Opti') . DS . 'lib' . DS . 'CSSMin' . DS . 'Minifier.php',
        Mage::getModuleDir('', 'Fishpig_Opti') . DS . 'lib' . DS . 'CSSMin' . DS . 'Utils.php',
        Mage::getModuleDir('', 'Fishpig_Opti') . DS . 'lib' . DS . 'CSSMin' . DS . 'Colors.php',
        Mage::getModuleDir('', 'Fishpig_Opti') . DS . 'lib' . DS . 'CSSMin' . DS . 'Command.php',
      );

      foreach($files as $file) {
        include($file);
      }
    }
	}
}

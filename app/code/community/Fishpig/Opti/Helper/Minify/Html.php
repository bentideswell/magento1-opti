<?php
/**
 * @category	Fishpig
 * @package		Fishpig_Opti
 * @license		http://fishpig.co.uk/license.txt
 * @author		Ben Tideswell <help@fishpig.co.uk>
 * @info			http://fishpig.co.uk/magento-optimisation.html
 */

class Fishpig_Opti_Helper_Minify_Html extends Fishpig_Opti_Helper_Minify_Abstract
{
	/**
	 *
	 *
	 * @return string
	**/
	public function getClassType()
	{
		return Fishpig_Opti_Model_Observer::TYPE_HTML;
	}
	
	/**
	 * Minify a HTML string
	 *
	 * @param string $html
	 * @return string
	 */
	public function minify($html)
	{
		$protect = array(
			'pre' => array(),
			'script' => array(),
			'style' => array(),
			'textarea' => array(),
		);
		
		$tagString = '#____%s-%d____#';
		
		foreach($protect as $tag => $storage) {
			if (strpos($html, '<' . $tag) === false) {
				continue;
			}
			
			if (preg_match_all('/(<' . $tag . '.*>.*<\/' . $tag . '>)/iUs', $html, $matches)) {
				foreach($matches[1] as $it => $code) {
					$protect[$tag][$it] = $code;

					$html = str_replace($code, sprintf($tagString, $tag, $it), $html);
				}
			}
		}

		if (preg_match_all('/(<!--\[if[^>]{1,}>|<!\[endif\]-->|<!--<!\[endif\]-->)/', $html, $matches)) {
			$tag = 'ietag';
			$protect[$tag] = array();

			foreach($matches[1] as $it => $code) {
				$protect[$tag][$it] = $code;

				$html = str_replace($code, sprintf($tagString, $tag, $it), $html);
			}
		}
		
		$html  = preg_replace('/ (class|id|target|rel|title)(="[ ]{0,}")/U', '', $html);
		$html = preg_replace('/[\s]+/', ' ', $html);
		$html = preg_replace('/ \/>/U', '/>', $html);

		if (preg_match('/(<head>.*<\/head>)/sU', $html, $m)) {
			$html = str_replace($m[0], preg_replace('/>[ \t\n]{1,}</', '><', $m[1]), $html);
		}

		if (defined('FISHPIG_BOLT')) {
			$html = preg_replace('/(<!--[^B>]{1,}-->)/U', '', $html);
		}
		else {
			$html = preg_replace('/(<!--[^>]{1,}-->)/U', '', $html);			
		}
		
		foreach($protect as $tag => $storage) {
			foreach($storage as $it => $code) {
				$html = str_replace(sprintf($tagString, $tag, $it), $code, $html);
			}
		}

		if (Mage::getStoreConfigFlag('opti/html/remove_base_url')) {
			$canonicalMatch = false;

			if (preg_match('/(<link rel="canonical" href=".*"\/>)/sU', $html, $m)) {
				$canonicalMatch = $m[1];
				$html = str_replace($canonicalMatch, '<!--CANONICAL-->', $html);
			}
			
			$httpHost = 'http' . (Mage::app()->getStore()->isCurrentlySecure() ? 's' : '') . '://' . Mage::app()->getRequest()->getHttpHost();
	
			$html = str_replace('src="' . $httpHost, 'src="', $html);
			$html = str_replace('src=\'' . $httpHost, 'src=\'', $html);
	
			$html = str_replace('href="' . $httpHost, 'href="', $html);
			$html = str_replace('href=\'' . $httpHost, 'href=\'', $html);
			
			if ($canonicalMatch) {
				$html = str_replace('<!--CANONICAL-->', $canonicalMatch, $html);
			}
		}

		// Remove type="text" and type='text'
		$html = preg_replace('/ type=[\'"]{1}text[\'"]{1}/', '', $html);

		$tags = implode('|', array(
			'h1',
			'h2',
			'h3',
			'h4',
			'h5',
			'h6',
			'div',
			'p',
			'ul',
			'li',
			'script',
			'meta',
			'link',
			'html',
			'body',
			'head',
			'form',
			'option',
			'!DOCTYPE',
			'table',
			'colgroup',
			'thead',
			'tbody',
			'tfoot',
			'tr',
			'th',
			'td',
			'option',
		));
			
		for($i = 1; $i <= 2; $i++) {
			// Closed Closed
			$html = preg_replace('/<\/(' . $tags . ')>\s+<\/(' . $tags . ')/', '</$1></$2', $html);	
					
			// Closed Open
			$html = preg_replace('/<\/(' . $tags . ')>\s+<(' . $tags . ')/', '</$1><$2', $html);
			
			// Open Open
			$html = preg_replace('/<(' . $tags . ')([^>]{0,})>\s+<(' . $tags . ')/', '<$1$2><$3', $html);		
			
			// Open Closed
			$html = preg_replace('/<(' . $tags . ')([^>]{0,})>\s+<\/(' . $tags . ')/', '<$1$2></$3', $html);		
		}

		return $html;
	}	
	
	/**
	 * Clean HTML after minifying
	 * This fixes CSS/JS filenames
	 *
	 * @param string $html
	 * @return string
	 */
	public function clean($html)
	{
		if (strpos($html, '../media/') === false) {
			return $html;
		}

		if (preg_match_all('/(src|href)="([^"]{1,}\/\.\.\/media[^"]{1,})"/U', $html, $matches)) {
			foreach($matches[0] as $key => $value) {
				$url = $matches[2][$key];
				$base = substr($url, 0, strpos($url, '../'));
				$after = substr($url, strrpos($url, '../')+3);
				$it = substr_count($url, '../');
				
				while($it-- > 0) {
					$base = dirname($base);
				}

				$html = str_replace($value, sprintf('%s="%s"', $matches[1][$key], rtrim($base, '/') . '/' . $after), $html);
			}
		}
		
		return $html;
	}
	
	/**
	 *
	 * @param int $storeId - null
	 * @return bool
	**/
	public function isMinifyInlineAllowed($storeId = null)
	{
		return false;
	}
}

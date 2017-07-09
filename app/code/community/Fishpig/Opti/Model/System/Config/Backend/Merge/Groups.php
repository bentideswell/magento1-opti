<?php
/**
 * @category  Fishpig
 * @package  Fishpig_Opti
 * @license    http://fishpig.co.uk/license.txt
 * @author    Ben Tideswell <help@fishpig.co.uk>
 */

class Fishpig_Opti_Model_System_Config_Backend_Merge_Groups extends Mage_Adminhtml_Model_System_Config_Backend_Serialized_Array
{
	/**
	 * Order files by group to make reading easier
	 *
	 * @return $this
	 */
	protected function _beforeSave()
	{
		$values = $this->getValue();

		if ($values && is_array($values) && count($values) > 0) {
			$sorted = array();

			foreach($values as $id => $value) {
				if (!is_array($value)) {
					continue;
				}

				foreach($value as $k => $v) {
					$value[$k] = trim($v);
				}
				
				if (empty($value['group']) || empty($value['file']) || empty($value['type'])) {
					continue;
				}
				
				$group = $value['group'];
				
				if (!isset($sorted[$group])) {
					$sorted[$group] = array();
				}
				
				$sorted[$group][$id] = $value;
			}
		}
		
		ksort($sorted);

		$final = array();
		
		foreach($sorted as $group => $files) {
			$final += $files;
		}

		$this->setValue($final);
		
		return parent::_beforeSave();
	}
}
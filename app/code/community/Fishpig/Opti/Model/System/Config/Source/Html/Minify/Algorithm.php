<?php
/*
 *
 */
class Fishpig_Opti_Model_System_Config_Source_Html_Minify_Algorithm
{
	const TYPE_QUICK = 'quick';
	const TYPE_FULL  = 'full';
	
	public function toOptionArray()
	{
		return array(
			self::TYPE_QUICK => 'Quick',
			self::TYPE_FULL  => 'Full',
		);
	}
}

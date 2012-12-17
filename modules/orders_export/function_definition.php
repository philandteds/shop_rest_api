<?php
/**
 * @package ShopRestAPI
 * @author  Serhey Dolgushev <dolgushev.serhey@gmail.com>
 * @date    17 Dec 2012
 **/

$FunctionList = array();
$FunctionList['fetch_export_history'] = array(
	'name'             => 'fetch_export_history',
	'call_method'      => array(
		'class'  => 'ezOrderExportHistory',
		'method' => 'fetchListWrapper'
	),
	'parameter_type'   => 'standard',
	'parameters'       => array(
		array(
			'name'     => 'conditions',
			'type'     => 'mixed',
			'required' => false
		),
		array(
			'name'     => 'limitations',
			'type'     => 'mixed',
			'required' => false
		)
	)
);

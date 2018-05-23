<?php


class ptStockLevelQuery
{

    static function getProductStock($longcode)
    {

        if (!$longcode) {
            throw new Exception('"longcode" is not specified');
        }

        $db = eZDB::instance();
        $q = '
			SELECT InStock, OnOrder, Eta
			FROM product
			WHERE
				LOWER( LongCode ) = LOWER( \'' . $db->escapeString($longcode) . '\' );
		';
        eZDebug::writeDebug($q, 'quantityCheck sql');
        $r = $db->arrayQuery($q);
        if (count($r) === 0) {
            throw new Exception('Product not found');
        }
        $format = (strlen($r[0]['Eta']) == 10) ? 'd/m/Y' : 'D M d H:i:s T Y';
        $gmt_date = ($r[0]['Eta'] != '') ? DateTime::createFromFormat($format, $r[0]['Eta'], new DateTimeZone('GMT')) : '';

        $result = array(
            'stock_level' => (int)$r[0]['InStock'],
            'stock_coming' => (int)$r[0]['OnOrder'],
            'eta' => ($r[0]['Eta'] != '' && is_object($gmt_date)) ? $gmt_date->format('Y-m-d\TH:i:sP') : ''
        );

        return $result;
    }
}

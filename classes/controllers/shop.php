<?php
/**
 * @package ShopRestAPI
 * @class   ShopController
 * @author  Serhey Dolgushev <dolgushev.serhey@gmail.com>
 * @date    25 Nov 2012
 **/

class ShopController extends ezpRestMvcController
{
	private static $priceAttributes = array(
		'product_total_inc_vat',
		'product_total_ex_vat',
		'total_inc_vat',
		'total_ex_vat'
	);
	private static $billingAttributes = array(
		'first_name',
		'last_name',
		'address1',
		'address2',
		'city',
		'zip',
		'country',
		'state',
		'phone'
	);

	public function doExportOrders() {
		$orders = $this->fetchOrders();
		if( (bool) $this->request->variables['onlyNew'] === true ) {
			foreach( $orders as $order ) {
				$this->markOrderAsExported( $order );
			}
		}

		$regions  = eZINI::instance( 'site.ini' )->variable( 'RegionalSettings', 'TranslationSA' );
		$shopName = eZINI::instance( 'xrowecommerce.ini' )->variable( 'Settings', 'Shop' );

		$feed = array(
			'_tag'       => 'orders',
			'collection' => array()
		);
		$paymentObjectClass = class_exists( 'xrowPaymentObject' ) ? 'xrowPaymentObject' : 'eZPaymentObject';
		foreach( $orders as $order ) {
			if( $order instanceof eZOrder === false ) {
				continue;
			}

			$exportHistory     = ezOrderExportHistory::fetchByOrderID( $order->attribute( 'id' ) );
			$productCollection = $order->attribute( 'productcollection' );
			$currency          = $productCollection->attribute( 'currency_code' );
			$productItems      = $order->attribute( 'product_items' );
			$paymentObject     = call_user_func( array( $paymentObjectClass, 'fetchByOrderID' ), $order->attribute( 'id' ) );
			$isPaid            = is_object( $paymentObject ) ? (int) $paymentObject->attribute( 'status' ) : 0;
			$accountInfo       = $order->attribute( 'account_information' );
			$paymentGateway    = is_object( $paymentObject ) ? $paymentObject->attribute( 'payment_string' ) : null;
			if(
				class_exists( $paymentGateway . 'Gateway' )
				&& is_callable( array( $paymentGateway. 'Gateway', 'name' ) )
			) {
				$paymentGateway = call_user_func( array( $paymentGateway. 'Gateway', 'name' ) );
			}

			$regionName   = 'Unknown';
			$saiteacceses = eZOrderItem::fetchListByType( $order->attribute( 'id' ), 'siteaccess' );
			if( count( $saiteacceses ) > 0 ) {
				$saiteaccess   = $saiteacceses[0];
				$regionShopINI = eZSiteAccess::getIni( $saiteaccess->attribute( 'description' ), 'shop.ini' );
				$regionName    = $regionShopINI->variable( 'PriceSettings', 'PriceGroup' );
			}

			$orderInfo                        = array( '_tag' => 'order' );
			$orderInfo['id']                  = $shopName . $order->attribute( 'id' );
			$orderInfo['is_archived']         = $order->attribute( 'is_archived' );
			$orderInfo['was_exported_before'] = (int) ( $exportHistory instanceof ezOrderExportHistory );
			$orderInfo['status']              = $order->attribute( 'status_name' );
			$orderInfo['is_paid']             = $isPaid;
			$orderInfo['payment_gateway']     = $paymentGateway;
			$orderInfo['created']             = date( 'c', $order->attribute( 'created' ) );
			$orderInfo['updated']             = date( 'c', $order->attribute( 'status_modified' ) );
			$orderInfo['account_name']        = $order->attribute( 'account_name' );
			$orderInfo['account_email']       = $order->attribute( 'account_email' );
			$orderInfo['user_comment']        = $accountInfo['message'];
			$orderInfo['region']              = $regionName;
			$orderInfo['siteaccess']          = $saiteaccess->attribute( 'description' );
			foreach( self::$priceAttributes as $attribute ) {
				$orderInfo[ $attribute ] = $order->attribute( $attribute ) . ' ' . $currency;
			}

			$shippingCost = 0;
			$discount     = 0;
			$items = $order->attribute( 'order_items' );
			foreach( $items as $item ) {
				if( $item->attribute( 'type' ) === 'ezcustomshipping' ) {
					$shippingCost = $item->attribute( 'price' );
					break;
				} elseif(
					$item->attribute( 'type' ) === 'product_discount'
					&& $item->attribute( 'description' ) !== 'all'
				) {
					$discount += $item->attribute( 'price' );
				}
			}
			$orderInfo['shipping_cost'] = $shippingCost . ' ' . $currency;
			$orderInfo['discount']      = $discount . ' ' . $currency;

			$tmp = $order->attribute( 'order_info' );
			$orderInfo['vat_amount'] = $tmp['total_price_info']['total_price_vat'] . ' ' . $currency;
			$orderInfo['vat']        = $productItems[0]['vat_value'] . '%';

			$orderInfo['billing_info']  = array();
			$orderInfo['shipping_info'] = array();
			foreach( self::$billingAttributes as $attribute ) {
				if( isset( $accountInfo[ $attribute ] ) && $attribute == 'state' ) {
					$value = xrowGeonames::getSubdivisionName( $accountInfo['country'], $accountInfo[ $attribute ] );
				} else {
					$value = isset( $accountInfo[ $attribute ] ) ? $accountInfo[ $attribute ] : null;
				}
				$orderInfo['billing_info'][ $attribute ] = $value;

				$spinningAttribute = 's_' . $attribute;
				if ( isset( $accountInfo[ $spinningAttribute ] ) && $attribute == 'state' ) {
					$value = xrowGeonames::getSubdivisionName($accountInfo[ 'country' ], $accountInfo[ $spinningAttribute ] );
				} else {
					$value = isset( $accountInfo[ $spinningAttribute ] ) ? $accountInfo[ $spinningAttribute ] : null;
				}
				$orderInfo['shipping_info'][ $attribute ] = $value;
			}

			$orderInfo['products'] = array();
			foreach( $productItems as $productItem ) {
				$productInfo = array( '_tag' => 'product' );

				$discount = 0;
				foreach( $items as $item ) {
					if(
						$item->attribute( 'type' ) === 'product_discount'
						&& $item->attribute( 'description' ) === $productItem['id']
					) {
						$discount = $item->attribute( 'price' );
						break;
					}
				}				


				$productInfo['SKU']                 = false;
				$productInfo['name']                = $productItem['object_name'];
				$productInfo['count']               = $productItem['item_count'];
				$productInfo['total_price_ex_vat']  = $productItem['total_price_ex_vat'] . ' ' . $currency;
				$productInfo['total_price_inc_vat'] = $productItem['total_price_inc_vat'] . ' ' . $currency;
				$productInfo['vat_amount']          = ( $productInfo['total_price_inc_vat'] - $productInfo['total_price_ex_vat'] ) . ' ' . $currency;
				$productInfo['discount']            = $discount . ' ' . $currency;

				$options = eZProductCollectionItemOption::fetchList( $productItem['id'] );
				if( $productItem['item_object']->attribute( 'contentobject' )->attribute( 'class_identifier' ) === 'sale_bundle' ) {
					foreach( $options as $option ) {
						if( $option->attribute( 'name' ) == ProductVariationsType::PRODUCT_OPTION_SKU_LIST ) {
							$productInfo['SKU'] = $option->attribute( 'value' );
							break;
						}
					}
				} else {
					foreach( $options as $option ) {
						if( $option->attribute( 'name' ) == 'variations' ) {
							$productInfo['SKU'] = $option->attribute( 'value' );
							break;
						}
					}
				}

				$orderInfo['products'][] = $productInfo;
			}

			$feed['collection'][] = $orderInfo;
		}

		$result = new ezpRestMvcResult();
		$result->variables['feed'] = $feed;
		return $result;
	}

	public function doProcessOrders() {
		$orderIDs = isset( $this->request->get['order_ids'] ) === false
			? isset( $this->request->post['order_ids'] )
				? $this->request->post['order_ids']
				: null
			: $this->request->get['order_ids'];

		if( $orderIDs === null ) {
			throw new Exception( 'order_ids parameter is missing' );
		}

		$feed = array(
			'_tag'       => 'response',
			'collection' => array()
		);

		$shopName = eZINI::instance( 'xrowecommerce.ini' )->variable( 'Settings', 'Shop' );
		$orderIDs = explode( ',', $orderIDs );
		foreach( $orderIDs as $orderID ) {
			$orderID = (int) str_replace( $shopName, '', trim( $orderID ) );

			$historyItem = ezOrderExportHistory::fetchByOrderID( $orderID );
			if( $historyItem instanceof ezOrderExportHistory ) {
				$historyItem->setAttribute( 'is_processed_lj', 1 );
				$historyItem->store();
				$isProcessed = true;
			}

			$feed['collection'][] = array(
				'_tag'         => 'order',
				'id'           => $orderNumber,
				'is_processed' => (int) $isProcessed
			);
		}

		$result = new ezpRestMvcResult();
		$result->variables['feed'] = $feed;
		return $result;
	}

	public function doImportProducts() {
		if( isset( $this->request->post['request'] ) === false ) {
			throw new Exception( '"request" is not specified' );
		}

		$DOMDocument = new DOMDocument();
		if( @$DOMDocument->loadXML( $this->request->post['request'] ) === false ) {
			throw new Exception( '"request" is not valid XML' );
		}

		if( isset( $this->request->get['cli'] ) ) {
			$sourceFile = eZINI::instance( 'ljimport.ini' )->variable( 'General', 'SourceFile' );
			if( $DOMDocument->save( $sourceFile ) === false ) {
				throw new Exception( 'Could not save XML to file' );
			}
			exec( '$(which php) extension/lj_import/bin/php/import.php > var/log/cli_import.log &' );
		} else {
			$moduleRepositories = eZModule::activeModuleRepositories( false );
			eZModule::setGlobalPathList( $moduleRepositories );

			$configs   = array(
				'ljImportConfigProductImage',
				'ljImportConfigProductSKU',
				'ljImportConfigPrice'
			);
			$timestamp = time();
			$emailLogs = array();
			foreach( $configs as $configClass ) {
				$startTime = time();

				$importConfig = new $configClass;
				$importConfig->setDOMDocument( $DOMDocument );
				$importConfig->clearLogMessages();

				$importController = new ljImportController( $importConfig );
				$importController->log( 'Starting import for ' . get_class( $importConfig ), array( 'blue' ) );
				$importController->run( $timestamp );

				$executionTime = round( microtime( true ) - $startTime, 2 );

				$importController->log( 'Import took ' . $executionTime . ' secs.' );
				$importController->log( 'Created ' . $importController->counter['create'] . ' items, updated ' . $importController->counter['update'] . ' items, skiped ' . $importController->counter['skip'] . ' items.' );
				$importController->log( 'Available items in feed: ' . count( $importController->config->dataList ) . '.' );

				if( $importController->counter['create'] + $importController->counter['update'] > 0) {
					$speed = ( $importController->counter['create'] + $importController->counter['update'] ) / $executionTime;
					$speed = round( $speed, 2 );
					$importController->log( 'Average speed: ' . $speed . ' items/sec.' );
				}

				$emailLogs[ str_replace( 'ljImportConfig', '', $configClass ) ] = $importConfig->getLogMessages();

				unset( $importController );
			}

			$emailLogs = ljImportController::groupLogMessages( $emailLogs );
			ljImportController::sendResultsEmail( $emailLogs );
		}

		$feed = array(
			'_tag'       => 'response',
			'collection' => array(
				'status' => 'SUCCESS'
			)
		);

		$result = new ezpRestMvcResult();
		$result->variables['feed'] = $feed;
		return $result;
	}

	public function doGetProductStock() {
		$params = array(
			'longcode' => false
		);

		foreach( $params as $param => $value ) {
			if( isset( $this->request->post[ $param ] ) === false ) {
				throw new Exception( '"' . $param . '" is not specified' );
			} else {
				$params[ $param ] = $this->request->post[ $param ];
			}
		}

		$db = eZDB::instance();
		$q  = '
			SELECT InStock, Eta
			FROM product
			WHERE
				LOWER( LongCode ) = LOWER( \'' . $db->escapeString( $params['longcode'] ) . '\' );
		';
		eZDebug::writeDebug($q, 'quantityCheck sql');
		$r = $db->arrayQuery( $q );
		if( count( $r ) === 0 ) {
			throw new Exception( 'Product not found' );
		}

		$result = new ezpRestMvcResult();
		$result->variables['stock_level'] = $r[0]['InStock'];
		$result->variables['eta']         = $r[0]['Eta'];
		return $result;
	}

	private function fetchOrders() {
		/**
		 * eZPersistentObject does not support NOT IN SQL statement. Thats why all
		 * orders should be fetched and filterd the new ones (if it is required)
		 **/
		$orders = eZPersistentObject::fetchObjectList(
			eZOrder::definition(),
			null,
			array( 'is_temporary' => 0 ),
			array( 'created' => 'asc' )
		);

		if( (bool) $this->request->variables['onlyNew'] === true ) {
			foreach( $orders as $key => $order ) {
				$exportHistory = ezOrderExportHistory::fetchByOrderID( $order->attribute( 'id' ) );
				if(
					$exportHistory instanceof ezOrderExportHistory
					&& (bool) $exportHistory->attribute( 'is_sent_lj' )
				) {
					unset( $orders[ $key ] );
				}
			}
		}

		return $orders;
	}

	private function markOrderAsExported( eZOrder $order ) {
		$exportHistory = new ezOrderExportHistory(
			array(
				'order_id'      => $order->attribute( 'id' ),
				'is_sent_lj'    => 1,
				'sent_to_lj_at' => time()
			)
		);
		$exportHistory->store();
	}
}


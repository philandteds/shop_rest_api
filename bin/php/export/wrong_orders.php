<?php
/**
 * @package ShopRestAPI
 * @author  Serhey Dolgushev <serhey.dolgushev@nxc.no>
 * @date    06 Dec 2012
 **/

require 'autoload.php';

$cli = eZCLI::instance();
$cli->setUseStyles( true );

$scriptSettings = array();
$scriptSettings['description']    = 'Sends notification about wrongly exported to LongJump order';
$scriptSettings['use-session']    = false;
$scriptSettings['use-modules']    = false;
$scriptSettings['use-extensions'] = true;

$script = eZScript::instance( $scriptSettings );
$script->startup();
$script->initialize();

$cli->output( 'Starting script...' );
$startTime = microtime( true );

$ini = eZINI::instance( 'rest.ini' );

$timestamp     = time() - (int) $ini->variable( 'Export', 'WrongOrdersTimeDiff' );
$ordersHistory = ezOrderExportHistory::fetchList(
	array(
		'is_sent_lj'      => 1,
		'is_processed_lj' => 0,
		'sent_to_lj_at'   => array( '<', $timestamp )
	)
);

if( count( $ordersHistory ) > 0 ) {
	$body = 'Exported, but no processed by LJ orders:' . "\n";
	foreach( $ordersHistory as $orderHistory ) {
		$body .= '- Order ID: ' . $orderHistory->attribute( 'order_id' ) . ' (sent to LJ on '
			. date( 'c', $orderHistory->attribute( 'sent_to_lj_at' ) ) . ')' . "\n";
	}

	$mail = new eZMail();
	$mail->setContentType( 'text/plain' );
	$mail->setSender( 'noreplay@mokopuna.com' );
	$mail->setSubject( 'Export warning' );
	$mail->setBody( $body );
	$receivers = (array) $ini->variable( 'Notifications', 'Receivers' );;
	foreach( $receivers as $email ) {
		$mail->addReceiver( $email );
	}
	eZMailTransport::send( $mail );
}

$executionTime   = microtime( true ) - $startTime;
$memoryPeakUsage = memory_get_peak_usage( true ) / 1024 / 1024;
$cli->output( 'Script took ' . $executionTime . ' seconds. Peak memory usage: ' . number_format( $memoryPeakUsage, 2 ) . ' Mb.' );

$script->shutdown( 0 );
?>

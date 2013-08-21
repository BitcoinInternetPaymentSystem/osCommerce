<?php
	require 'includes/application_top.php';

	$BIPS = $_POST;
	$hash = hash('sha512', $BIPS['transaction']['hash'] . MODULE_PAYMENT_BIPS_SECRET);

	header('HTTP/1.1 200 OK');
	print '1';

	if ($BIPS['hash'] == $hash && $BIPS['status'] == 1)
	{
		@tep_db_query("update " . TABLE_ORDERS . " set orders_status = " . MODULE_PAYMENT_BIPS_PAID_STATUS_ID . " where orders_id = " . intval($BIPS["custom"]["order_id"]));
	}
?>
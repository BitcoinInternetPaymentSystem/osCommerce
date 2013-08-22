<?php
	class bips
	{
		var $code, $title, $description, $enabled, $payment;

		function bips()
		{
			global $order;

			$this->signature = 'BIPS|BIPS_standard|1.0|2.2';

			$this->code = 'bips';
			$this->title = MODULE_PAYMENT_BIPS_TEXT_TITLE;
			$this->description = MODULE_PAYMENT_BIPS_TEXT_DESCRIPTION;
			$this->sort_order = MODULE_PAYMENT_BIPS_SORT_ORDER;
			$this->enabled = ((MODULE_PAYMENT_BIPS_STATUS == 'True') ? true : false);

			if ((int)MODULE_PAYMENT_BIPS_ORDER_STATUS_ID > 0)
			{
				$this->order_status = MODULE_PAYMENT_BIPS_ORDER_STATUS_ID;
				$payment = 'bips';
			}
			else if ($payment == 'bips')
			{
				$payment = '';
			}

			if (is_object($order)) $this->update_status();

			$this->email_footer = MODULE_PAYMENT_BIPS_TEXT_EMAIL_FOOTER;
		}

		function update_status()
		{
			global $order;

			if (($this->enabled == true) && ((int)MODULE_PAYMENT_BIPS_ZONE > 0))
			{
				$check_flag = false;
				$check = tep_db_query("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_PAYMENT_BIPS_ZONE . "' and zone_country_id = '" . $order->billing['country']['id'] . "' order by zone_id");
				
				while (!$check->EOF)
				{
					if ($check->fields['zone_id'] < 1)
					{
						$check_flag = true;
						break;
					}
					else if ($check->fields['zone_id'] == $order->billing['zone_id'])
					{
						$check_flag = true;
						break;
					}

					$check->MoveNext();
				}

				if ($check_flag == false)
				{
					$this->enabled = false;
				}
			}

			if (!MODULE_PAYMENT_BIPS_APIKEY OR !strlen(MODULE_PAYMENT_BIPS_APIKEY))
			{
				print 'No API key';
				$this->enabled = false;
			}

			if (!MODULE_PAYMENT_BIPS_SECRET OR !strlen(MODULE_PAYMENT_BIPS_SECRET))
			{
				print 'No Secret';
				$this->enabled = false;
			}
		}

		function selection()
		{
			return array('id' => $this->code, 'module' => $this->title);
		}

		function javascript_validation()
		{
			return false;
		}

		function confirmation()
		{
			return false;
		}

		function process_button()
		{
			return false;
		}

		function pre_confirmation_check()
		{
			return false;
		}

		function before_process()
		{
			return false; 
		}

		function after_process()
		{
			global $insert_id, $order, $db;
					
			// change order status to value selected by merchant
			tep_db_query("update ". TABLE_ORDERS. " set orders_status = " . MODULE_PAYMENT_BIPS_UNPAID_STATUS_ID . " where orders_id = ". $insert_id);

			$ch = curl_init();
			curl_setopt_array($ch, array(
			CURLOPT_URL => 'https://bips.me/api/v1/invoice',
			CURLOPT_USERPWD => MODULE_PAYMENT_BIPS_APIKEY,
			CURLOPT_POSTFIELDS => 'price=' . number_format($order->info['total'], 2, '.', '') . '&currency=' . $order->info['currency'] . '&item=' . $item_name . '&custom=' . json_encode(array('order_id' => $insert_id, 'physical' => ($order->content_type == 'physical' ? 'true' : 'false'), 'returnurl' => rawurlencode(tep_href_link('account')), 'cancelurl' => rawurlencode(tep_href_link('account')))),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPAUTH => CURLAUTH_BASIC));
			$url = curl_exec($ch);
			curl_close($ch);
			
			$_SESSION['cart']->reset(true);
			tep_redirect($url);

			return false;
		}

		function get_error()
		{
			return false;
		}

		function check()
		{
			if (!isset($this->_check))
			{
				$check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_BIPS_STATUS'");
				$this->_check = tep_db_num_rows($check_query);
			}
			
			return $this->_check;
		}

		function install()
		{
			if (defined('MODULE_PAYMENT_BIPS_STATUS'))
			{
				return 'failed';
			}

			tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) "
			."values ('Enable BIPS Module', 'MODULE_PAYMENT_BIPS_STATUS', 'True', 'Do you want to accept bitcoin payments via BIPS?', '6', '0', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now());");

			tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) "
			."values ('BIPS API key', 'MODULE_PAYMENT_BIPS_APIKEY', '', 'Enter your BIPS Invoice API key', '6', '0', now());");

			tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) "
			."values ('BIPS Secret', 'MODULE_PAYMENT_BIPS_SECRET', '', 'Enter your merchant secret from BIPS', '6', '0', now());");

			tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) "
			."values ('Unpaid Order Status', 'MODULE_PAYMENT_BIPS_UNPAID_STATUS_ID', '" . DEFAULT_ORDERS_STATUS_ID .  "', 'Automatically set the status of unpaid orders to this value.', '6', '0', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");

			tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) "
			."values ('Paid Order Status', 'MODULE_PAYMENT_BIPS_PAID_STATUS_ID', '2', 'Automatically set the status of paid orders to this value.', '6', '0', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");
				
			tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) "
			."values ('Payment Zone', 'MODULE_PAYMENT_BIPS_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '2', 'tep_get_zone_class_title', 'tep_cfg_pull_down_zone_classes(', now())");
			
			tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) "
			."values ('Sort order of display.', 'MODULE_PAYMENT_BIPS_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '2', now())");
		}

		function remove()
		{
			tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
		}

		function keys()
		{
			return array(
				'MODULE_PAYMENT_BIPS_STATUS', 
				'MODULE_PAYMENT_BIPS_APIKEY',
				'MODULE_PAYMENT_BIPS_SECRET',
				'MODULE_PAYMENT_BIPS_UNPAID_STATUS_ID',
				'MODULE_PAYMENT_BIPS_PAID_STATUS_ID',
				'MODULE_PAYMENT_BIPS_SORT_ORDER',
				'MODULE_PAYMENT_BIPS_ZONE'
			);
		}
	}
?>

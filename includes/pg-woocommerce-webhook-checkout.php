<?php
require_once('../../../../wp-load.php');
require_once( dirname( __FILE__ ) . '/pg-woocommerce-helper.php' );
require_once( dirname( __DIR__ ) . '/pg-woocommerce-plugin.php' );

$requestBodyJs = json_decode(file_get_contents('php://input'), true);

$status = $requestBodyJs["transaction"]['status'];
$status_detail = (int)$requestBodyJs["transaction"]['status_detail'];
$transaction_id = $requestBodyJs["transaction"]['id'];
$authorization_code = $requestBodyJs["transaction"]['authorization_code'];
$dev_reference = $requestBodyJs["transaction"]['dev_reference'];
$payment_message = $requestBodyJs["transaction"]['message'];

$order = new WC_Order($dev_reference);
$status_order = $order->get_status();

if (!in_array($status_order, ['completed', 'cancelled', 'refunded', 'processing'])) {
    $description = __("Payment Response: ", "pg_woocommerce") .
        __(" | Status: ", "pg_woocommerce") . $status .
        __(" | Status_detail: ", "pg_woocommerce") . $status_detail .
        __(" | Dev_Reference: ", "pg_woocommerce") . $dev_reference .
        __(" | Authorization_Code: ", "pg_woocommerce") . $authorization_code .
        __(" | Transaction_Code: ", "pg_woocommerce") . $transaction_id;

    if ($status_detail == 3) {
        $comments = __("Successful Payment", "pg_woocommerce");
        $order->add_meta_data('nuvei_auth_code', $authorization_code);
        $order->add_meta_data('nuvei_tx_id', $transaction_id);
        $order->update_status('processing');
        $order->add_order_note( __('The payment has been made successfully. Transaction Code: ', 'pg_woocommerce') . $transaction_id . __(' and its Authorization Code is: ', 'pg_woocommerce') . $authorization_code);
    } elseif (in_array($status_detail, [0, 1, 31, 35, 36])) {
        $comments = __("Pending Payment", "pg_woocommerce");
        $order->update_status('on-hold');
        $order->add_order_note( __('The payment is pending.', 'pg_woocommerce') . $transaction_id . __(' the reason is: ', 'pg_woocommerce') . $payment_message);
    } elseif (in_array($status_detail, [7, 34, 21, 22, 23, 24, 25, 26, 27, 28, 29])) {
        $order->update_status('refunded');
        $order->add_order_note( __('Transaction refunded: ', 'pg_woocommerce') . $transaction_id . __(' status: ', 'pg_woocommerce') . $payment_message);
    } elseif ($status_detail == 8) {
        $description = "Chargeback";
        $comments = __("Payment Cancelled", "pg_woocommerce");
        $order->update_status('cancelled');
        $order->add_order_note( __('The payment was cancelled. Transaction Code: ', 'pg_woocommerce') . $transaction_id . __(' the reason is chargeback. ', 'pg_woocommerce'));
    } else {
        $comments = __("Failed Payment", "pg_woocommerce");
        $order->update_status('failed');
        $order->add_order_note( __('The payment has failed. Transaction Code: ', 'pg_woocommerce') . $transaction_id . __(' the reason is: ', 'pg_woocommerce') . $payment_message);
    }
}

PG_WC_Helper::insert_data($status, $comments, $description, $dev_reference, $transaction_id);

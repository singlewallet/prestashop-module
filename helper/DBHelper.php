<?php

namespace singlewallet\helper;

use Db;
use Order;

class DBHelper {
    protected const PREFIX = 'singlewallet_';
    protected const ORDERS_TABLE = self::PREFIX.'orders';
    protected const PAYMENTS_TABLE = self::PREFIX.'payments';

    public static function createOrdersTable(){
        return Db::getInstance()->execute("CREATE TABLE IF NOT EXISTS ".self::ORDERS_TABLE." (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `order_id` varchar(255) NOT NULL DEFAULT '',
            `invoice_id` varchar(255) NOT NULL DEFAULT '',
            `url` varchar(255) NOT NULL DEFAULT '',
            `status` varchar(255) NOT NULL DEFAULT 'new',
            `expire_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
         )");
    }

    public static function createPaymentsTable(){
        return Db::getInstance()->execute("CREATE TABLE IF NOT EXISTS ".self::PAYMENTS_TABLE." (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `order_id` varchar(255) NOT NULL DEFAULT '',
            `invoice_id` varchar(255) NOT NULL DEFAULT '',
            `invoice_amount` varchar(255) NOT NULL DEFAULT '0',
            `fiat_invoice_amount` varchar(255) NOT NULL DEFAULT '0',
            `paid_amount` varchar(255) NOT NULL DEFAULT '0',
            `fiat_paid_amount` varchar(255) NOT NULL DEFAULT '0',
            `exchange_rate` varchar(255) NOT NULL DEFAULT '0',
            `currency_code` varchar(255) NOT NULL DEFAULT '',
            `txid` varchar(500) NOT NULL DEFAULT '0',
            `txid_url` varchar(500) NOT NULL DEFAULT '0',
            `status` varchar(255) NOT NULL DEFAULT 'pending',
            `exception` varchar(255) NOT NULL DEFAULT '',
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
         );") && Db::getInstance()->execute("ALTER TABLE `singlewallet_payments` ADD UNIQUE `txid` (`txid`);");
    }

    public static function dropOrdersTable(){
        return Db::getInstance()->execute("DROP TABLE ".self::ORDERS_TABLE);
    }

    public static function dropPaymentsTable(){
        return Db::getInstance()->execute("DROP TABLE ".self::PAYMENTS_TABLE);
    }

    public static function createOrder($orderId, $invoiceId, $invoiceUrl, $expireAt){
        return Db::getInstance()->execute("insert into `".self::ORDERS_TABLE."` 
        (order_id, invoice_id, url, status, expire_at) 
values  ('$orderId','$invoiceId','$invoiceUrl','new','$expireAt');
        ");
    }

    public static function createOrderPayment($orderId, $invoiceId, $invoiceAmount, $fiatInvoiceAmount, $paidAmount, $fiatPaidAmount, $exchangeRate, $currencyCode, $txid, $txidUrl, $status, $exception=''){
        return Db::getInstance()->execute("insert into `".self::PAYMENTS_TABLE."` 
        (order_id, invoice_id, invoice_amount, fiat_invoice_amount, paid_amount, fiat_paid_amount, exchange_rate, currency_code, txid, txid_url, status, exception) 
values  ('$orderId','$invoiceId','$invoiceAmount','$fiatInvoiceAmount','$paidAmount','$fiatPaidAmount','$exchangeRate', '$currencyCode','$txid', '$txidUrl', '$status', '$exception');
        ");
    }

    public static function getOrderPayments($orderId){
        return DB::getInstance()->executeS("select * from ".self::PAYMENTS_TABLE." where `order_id`='$orderId' order by id");
    }

    public static function totalPaidForOrder($orderId){
        $payments = DBHelper::getOrderPayments($orderId);
        $totalPaid = 0;
        foreach($payments as $payment){
            $totalPaid += $payment['paid_amount'];
        }

        return $totalPaid;
    }

    public static function isUnderpaid($orderId){
        $order = new Order($orderId);
        $totalPaid = self::totalPaidForOrder($orderId);

        return $totalPaid < $order->total_paid_tax_incl;
    }

    public static function getPayment($txid){
        $payment = DB::getInstance()->executeS("select * from ".self::PAYMENTS_TABLE." where `txid`='$txid'");
        if(count($payment) == 1){
            return $payment[0];
        }

        return null;
    }

    public static function getOrder($id){
        $order = DB::getInstance()->executeS("select * from ".self::ORDERS_TABLE." where `order_id`='$id' order by id desc limit 1");
        if(count($order) == 1){
            return $order[0];
        }

        return null;
    }

    public static function getOrderByInvoiceId($invoiceId){
        $order = DB::getInstance()->executeS("select * from ".self::ORDERS_TABLE." where `invoice_id`='$invoiceId'");
        if(count($order) == 1){
            return $order[0];
        }

        return null;
    }

    public static function updateOrderStatus($invoiceId, $status){
        return DB::getInstance()->execute("update ".self::ORDERS_TABLE." set `status`='$status' where invoice_id='$invoiceId'");
    }

    public static function updatePaymentStatus($txid, $status){
        return DB::getInstance()->execute("update ".self::PAYMENTS_TABLE." set `status`='$status' where txid='$txid'");
    }

}
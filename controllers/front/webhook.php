<?php

use singlewallet\helper\DBHelper;

class SinglewalletWebhookModuleFrontController extends ModuleFrontController {

    public $ajax;

    public function initContent(){
        $this->ajax = 1;

        $data = trim(file_get_contents("php://input"));

        $apiKey = Configuration::get('singlewallet_api_key');
        $secret = Configuration::get('singlewallet_secret');
        $signature = $_SERVER['HTTP_SW_SIGNATURE'];

        if (empty($apiKey) || empty($data) || empty($secret) || empty($signature)) {
            Tools::redirect('/');
        }

        $sw = new \SingleWallet\SingleWallet($apiKey, $secret);

        if(!$sw->isValidPayload($data, $signature)){
            die;
        }

        $data = json_decode($data, true);
        $invoiceId = $data['id'];
        $checkInvoice = DBHelper::getOrderByInvoiceId($invoiceId);

        if(is_null($checkInvoice) || in_array($checkInvoice['status'],['paid','cancelled','expired'])){
            return;
        }

        $invoice = $sw->getInvoice($invoiceId);

        $payload = json_decode($invoice->getPayload(),true);

        $invoiceStatus = $invoice->getStatus();

        $orderId = $payload['id'];

        DBHelper::updateOrderStatus($invoice->getId(), $invoiceStatus);

        if($invoiceStatus == 'pending' || $invoiceStatus == 'paid'){
            $invoiceId = $invoice->getId();
            $invoiceAmount = $invoice->getInvoiceAmount();
            $fiatInvoiceAmount = $invoice->getFiatInvoiceAmount();
            $paidAmount = $invoice->getPaidAmount();
            $fiatPaidAmount = $invoice->getFiatPaidAmount();
            $exchangeRate = $invoice->getExchangeRate();
            $txid = $invoice->getTxid();
            $txidUrl = $invoice->getBlockchainUrl();
            $exception = $invoice->getException();
            $currency = $invoice->getCurrencyCode();

            $checkPayment = DBHelper::getPayment($txid);
            if(is_null($checkPayment)){
                DBHelper::createOrderPayment($orderId, $invoiceId, $invoiceAmount, $fiatInvoiceAmount, $paidAmount, $fiatPaidAmount, $exchangeRate, $currency, $txid, $txidUrl, $invoiceStatus, $exception);
            }

            if($invoiceStatus == 'paid'){
                if($exception == 'none' || $exception == 'overpaid'){
                    DBHelper::updatePaymentStatus($txid, $invoiceStatus);
                    $this->confirmOrder($orderId);
                }
            }
        }

    }

    public function confirmOrder($orderId){
        $order = new Order($orderId);
        $orderCurrentState = $order->current_state;
        $paidState = Configuration::get('singlewallet_order_state');
        if($orderCurrentState != $paidState){
            $history = new OrderHistory();
            $history->id_order = $order->id;
            $history->id_employee = 1;
            $history->id_order_state = $paidState;
            $history->save();
            $history->changeIdOrderState($paidState, $order , true);
            $history->sendEmail($order);
        }
    }
}
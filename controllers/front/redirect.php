<?php

use singlewallet\helper\DBHelper;

class SinglewalletRedirectModuleFrontController extends ModuleFrontController {

    public function initContent(){
        $orderId = Tools::getValue('order_id');

        $invoiceOrder = DBHelper::getOrder($orderId);

        $orderLink = Context::getContext()->link->getPageLink('order-detail', true,null,[
            'id_order'=>$orderId,
        ]);

        if(is_null($invoiceOrder)){
            Tools::redirect($orderLink);
        }

        $isUnderpaid = false;

        if($invoiceOrder['status'] == 'new' || $invoiceOrder['status'] == 'expired'){
            $remaining = $invoiceOrder['expire_at'] - time();

            if($remaining > 600){ // 10 minutes
                Tools::redirect($invoiceOrder['url']);
            }
        }else if($invoiceOrder['status'] == 'pending'){
            Tools::redirect($orderLink);
        }else if($invoiceOrder['status'] == 'paid'){
            $isUnderpaid = DBHelper::isUnderpaid($orderId);
        }

        $order = new Order($orderId);
        $products = $this->getProductsList($order->getCartProducts());

        if($isUnderpaid){
            $totalAmount = $order->total_paid - DBHelper::totalPaidForOrder($orderId);
        }else{
            $totalAmount = $order->total_paid;
        }

        $payload = json_encode($order);

        $apiKey = Configuration::get('singlewallet_api_key');

        $language = $this->context->language->iso_code;
        $currency = $this->context->currency->iso_code;

        $ttl = Configuration::get('singlewallet_ttl',null,null,null,60);

        $sw = new \SingleWallet\SingleWallet($apiKey, '');
        $invoice = (new \SingleWallet\Models\Request\Invoice())
            ->setOrderName($products)
            ->setOrderNumber("#$orderId")
            ->setAmount($totalAmount)
            ->setCurrencyCode($currency)
            ->setLanguage($language)
            ->setTtl($ttl)
            ->setCallbackUrl(Context::getContext()->link->getModuleLink('singlewallet', 'webhook'))
            ->setRedirectUrl($orderLink)
            ->setCancelUrl($orderLink)
            ->setPayload($payload);

        if(Configuration::get('singlewallet_send_customer_email')){
            $customer = new Customer($order->id_customer);
            $invoice->setCustomerEmail($customer->email);
        }

        $newInvoice = null;
        try{
            $newInvoice = $sw->createInvoice($invoice);
        }catch(\Exception $e){}

        if(is_null($newInvoice)){
            $this->redirectToErrorPage('error occurred while creating your invoice');
        }

        $expireAt = time()+($ttl*60);
        $createOrder = DBHelper::createOrder($orderId,$newInvoice->getId(), $newInvoice->getUrl(),$expireAt);

        if(!$createOrder){
            $this->redirectToErrorPage('error occurred while creating your invoice');
        }

        Tools::redirect($newInvoice->getUrl());
    }

    private function redirectToErrorPage($message){
        Tools::redirect($this->context->link->getModuleLink('singlewallet','error',['msg'=>$message]));
    }

    private function getProductsList($products){
        $count = count($products);
        if($count == 1){
            return $products[0]['product_name'];
        }else if($count > 1){
            return $products[0]['product_name']." and ".($count-1)." other items";
        }

        return "Order";
    }

}
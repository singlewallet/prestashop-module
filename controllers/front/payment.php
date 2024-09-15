<?php


use singlewallet\helper\DBHelper;

class SinglewalletPaymentModuleFrontController extends ModuleFrontController {

    public $auth = true;

    public function postProcess(){
        $cart = $this->context->cart;
        $apiKey = Configuration::get('singlewallet_api_key');

        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active || empty($apiKey)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $authorized = false;

        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'singlewallet') {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
            $this->redirectToErrorPage('This payment method is not available');
        }

        $customer = new Customer($cart->id_customer);

        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $language = $this->context->language->iso_code;
        $currency = $this->context->currency->iso_code;
        $products = $this->getProductsList($cart->getProducts());
        $ttl = Configuration::get('singlewallet_ttl',null,null,null,60);

        $total = (string)$cart->getOrderTotal(true, Cart::BOTH);

        $result = $this->module->validateOrder($cart->id, 1, $cart->getOrderTotal(), 'Single Wallet');
        if(!$result){
            $this->redirectToErrorPage('error occurred while validating order');
        }

        $orderId = Order::getIdByCartId($cart->id);
        $order = new Order($orderId);
        $payload = json_encode($order);

        $redirect = Context::getContext()->link->getPageLink('order-detail', true,null,[
            'id_order'=>$orderId,
        ]);

        $sw = new \SingleWallet\SingleWallet($apiKey, '');
        $invoice = (new \SingleWallet\Models\Request\Invoice())
            ->setOrderName($products)
            ->setOrderNumber("#$orderId")
            ->setAmount($total)
            ->setCurrencyCode($currency)
            ->setLanguage($language)
            ->setTtl($ttl)
            ->setCallbackUrl(Context::getContext()->link->getModuleLink('singlewallet', 'webhook'))
            ->setRedirectUrl($redirect)
            ->setCancelUrl($redirect)
            ->setPayload($payload);

        if(Configuration::get('singlewallet_send_customer_email')){
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
            return $products[0]['name'];
        }else if($count > 1){
            return $products[0]['name']." and ".($count-1)." other items";
        }

        return "Order";
    }
}
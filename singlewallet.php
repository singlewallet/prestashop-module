<?php


include dirname(__FILE__)."/vendor/autoload.php";

if(!class_exists('DBHelper')){
    include _PS_MODULE_DIR_."singlewallet/helper/DBHelper.php";
}

if (!defined('_PS_VERSION_')) {
    exit;
}

use singlewallet\helper\DBHelper;
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

class SingleWallet extends PaymentModule {
    public function __construct(){
        $this->name = 'singlewallet';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->author = 'SingleWallet';
        $this->need_instance = 1;
        $this->ps_versions_compliancy = [
            'min' => '1.7.0.0',
            'max' => '8.99.99',
        ];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('SingleWallet', []);
        $this->description = $this->trans('Accept Tether in all popular blockchains with SingleWallet.', []);

        $this->confirmUninstall = $this->trans('Are you sure you want to uninstall?', []);
    }

    public function install(){
        if(!parent::install() || !$this->registerHook('paymentOptions') || !$this->registerHook('paymentReturn')){
            return false;
        }

        $this->registerHook('displayOrderDetail');

        // setup configurations
        Configuration::updateValue('singlewallet_title', 'Pay with cryptocurrency');
        Configuration::updateValue('singlewallet_description', 'Pay with Tether anonymously');
        Configuration::updateValue('singlewallet_api_key', '');
        Configuration::updateValue('singlewallet_secret', '');
        Configuration::updateValue('singlewallet_language', 'en');
        Configuration::updateValue('singlewallet_minimum_amount', 5);
        Configuration::updateValue('singlewallet_ttl', 60);
        Configuration::updateValue('singlewallet_send_customer_email', false);
        Configuration::updateValue('singlewallet_order_state', 2);

        // create db tables
        DBHelper::createOrdersTable();
        DBHelper::createPaymentsTable();

        return true;
    }

    public function hookPaymentOptions($params){
        if (!$this->active || !empty($this->warning)) {
            return;
        }

        if(Configuration::get('singlewallet_minimum_amount') > $this->context->cart->getOrderTotal()){
            return;
        }

        $externalOption = new PaymentOption();
        $externalOption->setModuleName($this->name);
        $externalOption->setCallToActionText($this->trans(Configuration::get('singlewallet_title')));
        $externalOption->setAction($this->context->link->getModuleLink($this->name, 'payment', ['option' => 'external'], true));
        $this->context->smarty->assign([
            'description'=>Configuration::get('singlewallet_description'),
        ]);
        $externalOption->setAdditionalInformation($this->context->smarty->fetch($this->local_path.'views/templates/front/payment-option.tpl'));


        return [$externalOption];
    }

    public function hookDisplayOrderDetail($order){
        if($order['order']->module == 'singlewallet'){
            $orderId = Tools::getValue('id_order');
            return $this->orderDetailTemplate($orderId);
        }

        return '';
    }

    public function orderDetailTemplate($orderId){
        $payments = DBHelper::getOrderPayments($orderId);

        $isUnderpaid = DBHelper::isUnderpaid($orderId);

        $this->context->smarty->assign(array(
            'pay_url' => $this->context->link->getModuleLink($this->name, 'redirect', [
                'order_id'=>$orderId,
            ]),
            'payments'=>$payments,
            'is_underpaid'=>$isUnderpaid,
        ));

        return $this->context->smarty->fetch($this->local_path.'views/templates/front/order-detail.tpl');
    }

    public function generateForm(){
        $this->context->smarty->assign(array(
            'action' => $this->context->link->getModuleLink($this->name, 'redirect', array(), true),
            'description' => Configuration::get('singlewallet_description'),
        ));

        return $this->context->smarty->fetch($this->local_path.'views/templates/front/payment_form.tpl');
    }

    public function uninstall(){
        // keep db

        return parent::uninstall();
    }

    public function getContent(){
        $output = '';

        if (Tools::isSubmit('submit' . $this->name)) {
            $title = (string) Tools::getValue('singlewallet_title');
            $description = (string) Tools::getValue('singlewallet_description');

            $apiKey = (string) Tools::getValue('singlewallet_api_key');
            $secret = (string) Tools::getValue('singlewallet_secret');
            $language = (string) Tools::getValue('singlewallet_language');
            $minimumAmount = (int) Tools::getValue('singlewallet_minimum_amount');
            $ttl = (int) Tools::getValue('singlewallet_ttl');
            $sendCustomerEmail = (bool) Tools::getValue('singlewallet_send_customer_email','off');
            $orderStateId = (int) Tools::getValue('singlewallet_order_state',2);

            $error = false;

            if(strlen($title) < 5){
                $output .= $this->displayError($this->trans('title must have 5 characters at least'));
                $error = true;
            }

            if(!empty($apiKey)){
                if(strlen($apiKey) == 16){
                    $sw = new \SingleWallet\SingleWallet($apiKey, $secret);
                    try{
                        $sw->getAccountInfo();
                    }catch(Throwable $e){
                        $output .= $this->displayError($this->trans($e->getMessage()));
                        $error = true;
                    }
                }else{
                    $output .= $this->displayError($this->trans('invalid api key'));
                    $error = true;
                }
            }

            if(!empty($secret) && !$error){
                if(!preg_match("/^([a-f0-9]{64})$/", $secret)){
                    $output .= $this->displayError($this->trans('Invalid Configuration value'));
                    $error = true;
                }
            }

            if($language != 'en'){
                if(empty($apiKey)){
                    $apiKey = Configuration::get('singlewallet_api_key');
                    $secret = '';
                }

                if(strlen($apiKey) == 16 && !$error){
                    $sw = new \SingleWallet\SingleWallet($apiKey, $secret);
                    try{
                        $isExists = array_filter($sw->getLanguageList(), function($el) use($language){
                            return $el->getCode() === $language;
                        });

                        if(count($isExists) == 0){
                            $output .= $this->displayError($this->trans('invalid language code'));
                            $error = true;
                        }
                    }catch(Throwable $e){
                        $output .= $this->displayError($this->trans('please set a valid api key to change language'));
                        $error = true;
                    }
                }else{
                    $output .= $this->displayError($this->trans('please set a valid api key to change language'));
                    $error = true;
                }
            }

            if(!Validate::isPositiveInt($minimumAmount)){
                if($minimumAmount < 3){
                    $output .= $this->displayError($this->trans('minimum amount is 3'));
                    $error = true;
                }
            }

            if(!Validate::isPositiveInt($ttl)){
                if($ttl < 15){
                    $output .= $this->displayError($this->trans('minimum invoice expire time is 15'));
                    $error = true;
                }else if($ttl > 10080){
                    $output .= $this->displayError($this->trans('maximum invoice expire time is 10080'));
                    $error = true;
                }
            }

            if(!Validate::isBool($sendCustomerEmail)){
                $output .= $this->displayError($this->trans('send customer email must be boolean'));
                $error = true;
            }

            if(!Validate::isPositiveInt($orderStateId)){
                if($this->checkOrderState($orderStateId) == 0){
                    $output .= $this->displayError($this->trans('invalid order state'));
                    $error = true;
                }
            }

            if(!$error){
                if(!empty($apiKey)){
                    Configuration::updateValue('singlewallet_api_key', $apiKey);
                }
                if(!empty($secret)){
                    Configuration::updateValue('singlewallet_secret', $secret);
                }

                Configuration::updateValue('singlewallet_title', $title);
                Configuration::updateValue('singlewallet_description', $description);
                Configuration::updateValue('singlewallet_language', $language);
                Configuration::updateValue('singlewallet_minimum_amount', $minimumAmount);
                Configuration::updateValue('singlewallet_ttl', $ttl);
                Configuration::updateValue('singlewallet_send_customer_email', $sendCustomerEmail);
                Configuration::updateValue('singlewallet_order_state', $orderStateId);
                $output = $this->displayConfirmation($this->trans('Settings updated'));
            }
        }

        return $output . $this->displayForm();
    }

    public function displayForm(){
        $form = [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Settings'),
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->trans('Gateway name'),
                        'name' => 'singlewallet_title',
                        'description'=>'This name will be displayed for the customer on the checkout page.',
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Description'),
                        'name' => 'singlewallet_description',
                        'description'=>'This description will be displayed for the customer on the checkout page.',
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('API Key'),
                        'name' => 'singlewallet_api_key',
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Secret'),
                        'name' => 'singlewallet_secret',
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Minimum amount'),
                        'name' => 'singlewallet_minimum_amount',
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Invoice Expire Time (in minutes)'),
                        'name' => 'singlewallet_ttl',
                        'required' => true,
                    ],
                    [
                        'type'  => 'select',
                        'label' => $this->trans('Invoice Language'),
                        'name'  => 'singlewallet_language',
                        'options' => array(
                            'query' => $this->languagesList(),
                            'id' => 'code',
                            'name' => 'name',

                        ),
                        'required' => true,
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->trans('Order State'),
                        'name' => 'singlewallet_order_state',
                        'desc' => $this->trans('When customer pay the invoice, What the order state should be?'),
                        'options' => [
                            'query' => $this->getOrderStates(),
                            'id' => 'id',
                            'name' => 'name',
                        ],
                    ],
                    [
                        'type'  => 'switch',
                        'label' => $this->trans('Add customer email to invoice'),
                        'name'  => 'singlewallet_send_customer_email',
                        'desc'  => "Customer will get updates about the invoice from SingleWallet, customer emails will never be shared with third party",
                        'required' => false,
                        'is_bool' => true,
                        'values'=>[
                            [
                                'id'=>'enabled',
                                'value'=>true,
                                'label' => $this->trans('Enabled')
                            ],
                            [
                                'id'=>'disabled',
                                'value'=>false,
                                'label' => $this->trans('Disabled')
                            ],
                        ],
                    ],
                ],
                'submit' => [
                    'title' => 'Save',
                    'class' => 'btn btn-default pull-right',
                ],
            ],
        ];

        $helper = new HelperForm();

        $helper->table = $this->table;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&' . http_build_query(['configure' => $this->name]);
        $helper->submit_action = 'submit' . $this->name;

        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');

        $helper->fields_value['singlewallet_title'] = Tools::getValue('singlewallet_title', Configuration::get('singlewallet_title'));
        $helper->fields_value['singlewallet_description'] = Tools::getValue('singlewallet_description', Configuration::get('singlewallet_description'));

        $helper->fields_value['singlewallet_api_key'] = '';// Tools::getValue('singlewallet_api_key', Configuration::get('singlewallet_api_key'));
        $helper->fields_value['singlewallet_secret'] = '';// Tools::getValue('singlewallet_secret', Configuration::get('singlewallet_secret'));

        $helper->fields_value['singlewallet_minimum_amount'] = Tools::getValue('singlewallet_minimum_amount', Configuration::get('singlewallet_minimum_amount'));
        $helper->fields_value['singlewallet_ttl'] = Tools::getValue('singlewallet_ttl', Configuration::get('singlewallet_ttl'));
        $helper->fields_value['singlewallet_language'] = Tools::getValue('singlewallet_language', Configuration::get('singlewallet_language'));
        $helper->fields_value['singlewallet_send_customer_email'] = Tools::getValue('singlewallet_send_customer_email', Configuration::get('singlewallet_send_customer_email'));
        $helper->fields_value['singlewallet_order_state'] = Tools::getValue('singlewallet_order_state', Configuration::get('singlewallet_order_state'));

        return $helper->generateForm([$form]);
    }

    public function languagesList(){
        $output = [
            [
                'code'=>'ar',
                'name'=>'Arabic',
            ],
            [
                'code'=>'en',
                'name'=>'English',
            ],
            [
                'code'=>'fr',
                'name'=>'French',
            ],
            [
                'code'=>'ru',
                'name'=>'Russian',
            ],
        ];


        return $output;
    }


    public function getOrderStates(){
        $results = Db::getInstance()->ExecuteS('SELECT * FROM '._DB_PREFIX_.'order_state_lang ORDER BY name ASC');
        $output = [];
        foreach ($results as $i=>$row) {
            $output[$i]['id'] = $row['id_order_state'];
            $output[$i]['name'] = $row['name'];
        }

        return $output;
    }

    public function checkOrderState($id){
        $id = intval($id);
        $results = Db::getInstance()->executeS('SELECT count(*) FROM '._DB_PREFIX_."order_state_lang where id_order_state='$id'");

        return count($results);
    }

}
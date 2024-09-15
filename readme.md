SingleWallet module for Prestashop
--
SingleWallet is cryptocurrency payment service, allows you to accept tether via popular blockchains eg: Tron, BSC, Ethereum, Ton, Polygon.


# Requirements
* Prestashop >=1.7 ,<=8.
* PHP >= 7.1

# Installation
1. Download this zip file
2. Open Prestashop admin panel 
and head to **Modules** > **Module Manager**.
3. Click on **Upload a module** button.
4. Choose the zip file downloaded in the first step.
5. Once completed, Click on **configure** button.

# Setup SingleWallet API key
1. Login to [SingleWallet](https://app.singlewallet.cc/login) or [Register](https://app.singlewallet.cc/register) an account if you don't have one.
2. From the left panel click on Projects
3. Click on **New Project** button.
4. Write the project name and click on save.
5. Once project is created, click on configure button.
6. Fill the fields as needed.
7. Scroll down to the bottom of configure project and click on **Generate New Key** button.
8. Write API Key **name**, **allowed ip** addresses, keep **allow withdraw** option **unchecked** for safety, once done click on **Generate** button.
9. You will see a message contains the **API key**, and the **secret key** please keep both of them safe, secret key will only be visible once.


# Setup Prestashop
Once Prestashop installation is completed, click on Configure button.

You will see the following fields

| field                         | Default                     | description                                                                                              |
|-------------------------------|-----------------------------|----------------------------------------------------------------------------------------------------------|
| Gateway name                  | Pay with cryptocurrency     | Payment method title which will be displayed to the customer on the checkout page                        |
| Description                   | Pay with Tether anonymously | This description will be displayed to the customer once payment method is chosen                         |
| API Key                       |                             | This key will be used to to create invoices, should be obtained from singlewallet.cc                     |
| Secret                        |                             | This secret will be used to validate webhook signatures, should be obtained from singlewallet.cc         |
| Minimum amount                | 5                           | If order total amount is less than this value, this payment method won't be visible in the checkout page |
| Invoice Expire Time           | 60 minutes                  | Invoice expiration time                                                                                  |
| Invoice Language              | store language              | The language which the invoice will be shown in.                                                         |
| Order State                   | Payment Accepted            | The state which the order will be set to when the invoice is paid successfully.                          |
| Add customer email to invoice | disabled                    | Add customer email to invoice so customer can have notifications about the invoice.                      |


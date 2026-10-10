=== Paymob for WooCommerce ===
Contributors: nspaymob, nermeenshoman, amlfares, babarzafar
Tags: paymob, payment, gateway, woocommerce
Requires at least: 5.0
Tested up to: 7.1
Requires PHP: 7.0
WC requires at least: 4.0
WC tested up to: 11.1
Stable tag: 4.1.15
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Service link: https://paymob.com

Paymob Payment for WooCommerce.

== Description ==
= Why should you choose Paymob Checkout? =
Paymob Checkout is a secure, pre-built payment solution and the easiest way to integrate payments into your Woo store.

We offer quick onboarding, seamless integration, and access to 20+ global and local payment methods, including Apple Pay and Google Pay. Enjoy quick payment options, superior customer service, and fast settlements.

== Key features ==

= Highest payment success rates =
We deliver industry-leading payment success rates, ensuring smoother transactions every time.
= 3DS OTP authentication =
Our 3DS one-time password (OTP) is embedded directly into the checkout, eliminating the need for redirection during authentication.
= Super-secure payments =
PCI-compliant, supporting 3D Secure transactions to protect cardholder data.
= Mobile-optimized design =
Built with a mobile-first approach.
= Fully customizable checkout =
Personalize the checkout experience by adding logos, business details (address & phone number), customising the background, and much more.
= Multiple Currency Support =
Accept payments in various currencies.
= Card Tokenization =
Securely store card details for easy and safe future transactions.
= Retry payment feature =
If the initial transaction fails, users can attempt payment up to three times, increasing their chances of success.
= Settlements =
We offer settlements within one business day (T+1) for most payment methods and instant settlements in select countries. For BNPL and more details on settlements and refunds, please see the FAQ section below.

== Insights at a glance: dashboards for data-driven decisions ==
* View transaction details and analytics. 
* Initiate refunds directly from the dashboard. 
* Check settlement summaries and available balance. 
* Download reports on payments, settlements, and refunds. 
* Control checkout customization. 
* Manage your API Keys && Payment Methods.

== Frequently Asked Questions ==
=Which payment methods are available through Paymob?=
* Egypt: Debit/credit cards, wallets, bank instalments, Kiosk, Instapay (launching soon) and various BNPL providers such as ValU, Souhoola, Halan, Premium6, SYMPL, Aman, Forsa, MidTakseet.
* United Arab Emirates (UAE): Debit/credit cards, Apple Pay, Google Pay, and BNPL services such as Tabby and Tamara.
* Oman: Debit/credit cards and Oman Net.
* Saudi Arabia (KSA): Debit/credit cards, Apple Pay, Google Pay, STC Pay and BNPL services such as Tabby and Tamara.
* Pakistan: Debit/credit cards, EasyPaisa, and Jazz Cash.

=What is the settlement cycle for primary payment methods such as cards, Apple Pay, and Google Pay?=
The settlement cycle for primary payment methods such as cards, Apple Pay, and Google Pay is trade date plus one day (T+1). This may vary for BNPL payment methods.

=How can merchants receive their funds instantly?=
Merchants can opt for instant settlement to receive funds immediately.
 
=How can merchants process refunds for their customers?=
Refunds can be initiated through API or the merchant dashboard.

=Does Paymob offer BNPL?=
 We offer payment flexibility through our partners in select regions.

== Installation ==
= Paymob Checkout for WooCommerce = 
Paymob is a leading payment service provider in Egypt, United Arab Emirates (UAE), Oman, Saudi Arabia (KSA), and Pakistan. Since its launch, Paymob has empowered enterprises and SMEs to accept online and in-store payments, revolutionizing payment infrastructure across the MENA-P region. 

= Sign up for a Paymob account =
1. [Click here](https://onboarding.paymob.com/?partner=woocommerce&redirect_url=wordpress) to register,upload your business documents, and choose the payment methods you want to integrate. The process is simple and can be resumed anytime from where you left off .
2.  Once all steps are completed, document verification will take up to 3 days. If there are any issues with your documents, you will receive an email notification, and our sales representative will assist you in completing the remaining steps. 
3. After your documents are verified, you will receive an email notification.
5. Once the live payment integrations you requested are set up, you will be notified via email. 

= Install the Paymob extension =
1. In your WordPress Admin Dashboard, go to Plugins > Installed Plugins. 
2. If "Paymob for WooCommerce" is not listed, follow these steps to install the plugin

= Steps to Install the Paymob Plugin =
1. Navigate to Plugins > Add New. 
2. Click on Add New Plugin. 
3. Search for "Paymob for WooCommerce." 
4. Click on Install next to the plugin. 
5. Once installed, click Activate. 
6. After activation, the plugin will appear as "Paymob for WooCommerce." Click on Paymob Settings. 

= Main Configuration = 
1. Follow either Step 1.1 OR 1.2 to configure the Plugin for use

1.1 Click on “Connect your Paymob Account” to be redirected to the sign-in page. Select your country, enter your username or mobile number along with your password, and verify your account using an OTP. Once authenticated, you will be taken to the main configuration section. Your plugin is now ready to use & will be enabled by default on the Store.
Note – If your onboarding is not yet complete, click on “Connect your Paymob Account” to proceed with the onboarding steps. 

1.2 You can also click on “Manual Setup”, enter the API Key, Public Secret Key &  Client Key and click on Confirm to connect your account. Once the Keys are validated, your plugin is now ready to use & will be enabled by default on the Store. 

Keys are different for Test Mode and Live Mode.

= Where to find API Keys? =
Log in to the Paymob Dashboard and navigate to Settings -> Account Info. Click on “View” to access the details. Ensure the correct mode is selected, as highlighted on the top panel: choose LIVE to access Live Keys or TEST to access Test Keys. 
 
Paymob Dashboard Link : 
Egypt - [https://accept.paymob.com/portal2/en/login](https://accept.paymob.com/portal2/en/login)
UAE - [https://uae.paymob.com/portal2/en/login](https://uae.paymob.com/portal2/en/login)
Oman - [https://oman.paymob.com/portal2/en/login](https://oman.paymob.com/portal2/en/login)
KSA - [https://ksa.paymob.com/portal2/en/login](https://ksa.paymob.com/portal2/en/login)

2. Once your account is authenticated through Step 1.1 or 1.2, your LIVE & TEST payment method integrations will be displayed in the Payment Integrations tab. The store mode is indicated in the Main Configuration section, and you can toggle between modes as needed. 

In LIVE Mode, your store will use live payment method integrations. 
In TEST Mode, your store will use test payment method integrations. 

If you make any changes, such as switching the mode, enabling/disabling Show Product Details on Paymob's Checkout, or adjusting the Debug Log, be sure to click “Save Changes” to apply them. 

=  Payment Method Integration Settings =
* This section displays both Live and Test Payment Method Integrations, with the ability to toggle between Test and Live modes.
* Paymob provides flexible checkouts options.  
* Refer to Payment Method Header: 
* Option 1. paymob-pixel (Card Embedded Settings)  
Enable seamless payments directly on your WooCommerce store for Cards, Apple Pay, and Google Pay without redirecting users to Paymob’s Hosted Checkout. 
By default, this option is enabled on your store with Card Payments only. To enable Apple Pay or Google Pay via Paymob Pixel, please contact your account manager or email us at support@paymob.com. Make sure to receive confirmation from Paymob before enabling Apple Pay or Google Pay via the Card Embedded Settings section. 
* Option 2: Display payment methods as a list on your WooCommerce store. 
Users will select a payment method and be redirected to Paymob’s Hosted Checkout to enter their payment details and complete the transaction. 
Enabling a payment method in this section will make it visible on your WooCommerce store. 
* Option 3: Using Paymob Main App 
Paymob’s main app is disabled by default. 
If enabled, users selecting this option will be redirected to Paymob’s Checkout, where they can choose from all available payment methods. 
This will appear as the last option in your payment method list.
* You can have all set-up’ enabled or a combination of any.
* The best recommendation is to use paymob-pixel for Cards, Apple Pay, and Google Pay while displaying other payment methods as a list.

= Customization Options =
* Rearrange payment methods by dragging and dropping them to your preferred order. 
* Edit titles and descriptions or enable/disable payment methods as needed. 
* It is recommended not to modify payment method logos. 
Payment methods will appear in the WooCommerce checkout in the same order as listed here. 

= Webhook Settings =
* Webhooks will be automatically configured for all payment method integrations. Merchants can view the current Webhook URL by clicking on the “Webhook URL” option. 
* The correct format for the Webhook URL is Store URL_ wc-api=paymob_callback 
* Upon Clicking on “Webhook URL”, a dialog pop-up will display the current Webhook URL. If the displayed Webhook URL does not match the correct format, merchants can click “Confirm” to update it to the correct Webhook URL for their store. 

= Card Embedded Settings = 
* Feature enables consumers to complete their payments directly on your WooCommerce store. It is enabled by default on your store. To disable it, navigate to the Payment Integrations section and disable “paymob-pixel”. If you wish to hide a specific payment method, simply avoid selecting its integration ID. 
* For card payments, select the required integration ID. By default, all integration IDs will be pre-selected. 
* For Apple Pay and Google Pay, certain actions must be completed on Paymob side. Please reach out to your account manager or contact us at support@paymob.com. Make sure to receive confirmation from Paymob before enabling Apple Pay or Google Pay for the embedded experience. 
* Payment Method – Title: Merchants can customize the title of the payment method displayed at checkout. 
* Cards, Google Pay & Apple Pay: Select the Integration ID to be used for payments. If integration id is not selected, payment method will not be displayed 
* Show Save Card: Allows users to save their card for future transactions if they provide consent. 
* Force Save Card: When enabled, cards are automatically saved after a transaction without user consent. Users will be notified that their card will be saved on Checkout. 
* Customization: Customize the component using CSS properties. Default settings are applied initially, and you can click the “Reset Default” button to restore them. 

= Subscription Payments (Recurring Billing) =
* Supports subscription-based products and services using card-based payment methods that support tokenization.
* Requires the WooCommerce Subscriptions plugin to be installed and activated.
* Compatible with “Simple Subscription” and “Variable Subscription” product types in WooCommerce. 
* for more details please visit this link https://developers.paymob.com/egypt/subscriptions-1


Remember to save your changes for them to reflect on the checkout. 

= Final Step - Enabling Paymob =
1. Go to WooCommerce > Settings > Payments.
2. Ensure enabling Paymob. 

== Screenshots ==
1. screenshot-1.png
2. screenshot-2.png
3. screenshot-3.png
4. screenshot-4.png
5. screenshot-5.png
6. screenshot-6.png
7. screenshot-7.png
8. screenshot-8.png
9. screenshot-9.png
10. screenshot-10.png
11. screenshot-11.png
12. screenshot-12.png


== Changelog ==
2026-10-05 - version 4.1.15
- bank installment on pixel

  



See [changelog.txt](http://plugins.svn.wordpress.org/paymob-for-woocommerce/trunk/changelog.txt) for older logs.



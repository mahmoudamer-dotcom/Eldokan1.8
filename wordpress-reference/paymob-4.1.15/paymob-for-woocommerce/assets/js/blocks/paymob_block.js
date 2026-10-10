jQuery(function ($) {
    
    if (typeof window.wc !== 'undefined' && typeof window.wp !== 'undefined' && typeof window.wc.wcSettings !== 'undefined' && typeof window.wc.wcBlocksRegistry !== 'undefined') {
        const settings = window.wc.wcSettings.getSetting('paymob_data', {});
        const label = window.wp.htmlEntities.decodeEntities(settings.title) || window.wp.i18n.__('Pay with Paymob', 'paymob-for-woocommerce');

        const Icon = () => {
            return settings.icon
                ? window.wp.element.createElement('img', {
                    src: settings.icon, id: 'paymob-logo', style: {
                        maxWidth: '70px',
                        float: 'right',
                        paddingTop: '6px'
                    }
                })
                : null;
        };

        const Content = () => {
            return window.wp.htmlEntities.decodeEntities(settings.description || '');
        };

        const LabelWithIcon = () => {
            return window.wp.element.createElement('span', { style: { width: '100%' } }, label, window.wp.element.createElement(Icon));
        };

        const Block_Gateway = {
            name: 'paymob',
            label: window.wp.element.createElement(LabelWithIcon),
            content: window.wp.element.createElement(Content, null),
            edit: window.wp.element.createElement(Content, null),
            canMakePayment: () => true,
            ariaLabel: label,
            supports: {
                features:  [
                    'products',
                    'refunds',
                    'subscriptions',
                    'subscription_cancellation',
                    'subscription_suspension',
                    'subscription_reactivation',
                    'subscription_amount_changes',
                    'subscription_date_changes',
                    'subscription_payment_method_change',
                    'subscription_payment_method_change_customer',
                    'subscription_payment_method_change_admin',
                    'multiple_subscriptions',
                ],
            },
        };

        window.wc.wcBlocksRegistry.registerPaymentMethod(Block_Gateway);

        // Append the dynamic CSS
        const css = `
         html[lang="en"] #paymob-logo {
             float: right !important;
         }
         html[lang="ar"] #paymob-logo {
             float: left !important;
         }
     `;

        const style = document.createElement('style');
        style.appendChild(document.createTextNode(css));
        document.head.appendChild(style);
    }
});
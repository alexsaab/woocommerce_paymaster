<?php
/*
Plugin Name: PayMaster Payment Gateway
Plugin URI: https://www.agaxx.com
Description: Allows you to use PayMaster payment gateway with the WooCommerce plugin.
Version: 1.0
Author: Alex Agafonov
Author URI: https://www.agaxx.com
 */

//TODO: Выбор платежной системы на стороне магазина

if (!defined('ABSPATH')) {
    exit;
}
// Exit if accessed directly

/**
 * Add roubles in currencies
 *
 * @since 0.3
 */
function paymaster_rub_currency_symbol($currency_symbol, $currency)
{
    if ($currency == "RUB") {
        $currency_symbol = 'р.';
    }

    return $currency_symbol;
}

function paymaster_rub_currency($currencies)
{
    $currencies["RUB"] = 'Russian Roubles';

    return $currencies;
}

add_filter('woocommerce_currency_symbol', 'paymaster_rub_currency_symbol', 10, 2);
add_filter('woocommerce_currencies', 'paymaster_rub_currency', 10, 1);

/* Add a custom payment class to WC
------------------------------------------------------------ */
add_action('plugins_loaded', 'woocommerce_paymaster', 0);
function woocommerce_paymaster()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }
    // if the WC payment gateway class is not available, do nothing
    if (class_exists('WC_PAYMASTER')) {
        return;
    }

    class WC_PAYMASTER extends WC_Payment_Gateway
    {
        public function __construct()
        {

            $plugin_dir = plugin_dir_url(__FILE__);

            global $woocommerce;

            $this->id         = 'paymaster';
            $this->icon       = apply_filters('woocommerce_paymaster_icon', '' . $plugin_dir . 'paymaster.png');
            $this->has_fields = false;
            $this->liveurl    = 'https://paymaster.ru/Payment/Init';

            // Load the settings
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables
            $this->title                  = $this->get_option('title');
            $this->paymaster_merchant     = $this->get_option('paymaster_merchant');
            $this->paymaster_secret       = $this->get_option('paymaster_secret');
            $this->paymaster_hash_method  = $this->get_option('paymaster_hash_method');
            $this->paymaster_vat_products = $this->get_option('paymaster_vat_products');
            $this->paymaster_vat_delivery = $this->get_option('paymaster_vat_delivery');
            $this->testmode               = $this->get_option('testmode');
            $this->debug                  = $this->get_option('debug');
            $this->description            = $this->get_option('description');
            $this->instructions           = $this->get_option('instructions');

            // Logs
            if ($this->debug == 'yes') {
                $this->log = $woocommerce->logger();
            }

            // Actions
            add_action('valid-paymaster-standard-request', array($this, 'successful_request'));
            add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));

            // Save options
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            // Payment listener/API hook
            add_action('woocommerce_api_wc_' . $this->id, array($this, 'check_response'));

            if (!$this->is_valid_for_use()) {
                $this->enabled = false;
            }
        }

        /**
         * Check if this gateway is enabled and available in the user's country
         */
        function is_valid_for_use()
        {
            if (!in_array(get_option('woocommerce_currency'), array('RUB'))) {
                return false;
            }

            return true;
        }

        /**
         * Admin Panel Options
         * - Options for bits like 'title' and availability on a country-by-country basis
         *
         * @since 0.1
         **/
        public function admin_options()
        {
            ?>
            <h3><?php _e('PAYMASTER', 'woocommerce');?></h3>
            <p><?php _e('Настройка приема электронных платежей через Merchant PAYMASTER.', 'woocommerce');?></p>

			<?php if ($this->is_valid_for_use()): ?>

            <table class="form-table">

				<?php
// Generate the HTML For the settings form.
            $this->generate_settings_html();
            ?>
            </table><!--/.form-table-->

		<?php else: ?>
            <div class="inline error"><p>
                    <strong><?php _e('Шлюз отключен', 'woocommerce');?></strong>: <?php _e('PAYMASTER не поддерживает валюты Вашего магазина.', 'woocommerce');?>
                </p></div>
			<?php
endif;

        } // End admin_options()

        /**
         * Initialise Gateway Settings Form Fields
         *
         * @access public
         * @return void
         */
        function init_form_fields()
        {
            $this->form_fields = array(
                'enabled'                => array(
                    'title'   => __('Включить/Выключить', 'woocommerce'),
                    'type'    => 'checkbox',
                    'label'   => __('Включен', 'woocommerce'),
                    'default' => 'yes',
                ),
                'title'                  => array(
                    'title'       => __('Name of payment method', 'woocommerce'),
                    'type'        => 'text',
                    'description' => __('User see this title in order checkout.', 'woocommerce'),
                    'default'     => __('PayMaster', 'woocommerce'),
                ),
                'paymaster_merchant'     => array(
                    'title'       => __('ID of merchant', 'woocommerce'),
                    'type'        => 'text',
                    'description' => __('Please find your merchant id in merchant in PayMaster merchant backoffice', 'woocommerce'),
                    'default'     => '',
                ),
                'paymaster_secret'       => array(
                    'title'       => __('Secret', 'woocommerce'),
                    'type'        => 'password',
                    'description' => __('Paymaster secret word, please set it also in PayMaster merchant backoffice', 'woocommerce'),
                    'default'     => '',
                ),
                'paymaster_hash_method'  => array(
                    'title'       => __('Hash method', 'woocommerce'),
                    'type'        => 'select',
                    'options'     => array(
                        'md5'    => 'md5',
                        'sha1'   => 'sha1',
                        'sha256' => 'sha256',
                    ),
                    'description' => __('Setup hash method calculation, attention: you must set it same in PayMaster merchant backoffice', 'woocommerce'),
                    'default'     => 'md5',
                ),
                'paymaster_vat_products' => array(
                    'title'       => __('Paymaster VAT products', 'woocommerce'),
                    'type'        => 'select',
                    'options'     => array(
                        'vat18'  => __('VAT 18%'),
                        'vat10'  => __('VAT 10%'),
                        'vat118' => __('VAT formula 18/118'),
                        'vat110' => __('VAT formula 10/110'),
                        'vat0'   => __('VAT 0%'),
                        'no_vat' => __('No VAT'),
                    ),
                    'description' => __('Setup VAT for products', 'woocommerce'),
                    'default'     => 'vat18',
                ),
                'paymaster_vat_delivery' => array(
                    'title'       => __('Paymaster VAT delivery', 'woocommerce'),
                    'type'        => 'select',
                    'options'     => array(
                        'vat18'  => __('VAT 18%'),
                        'vat10'  => __('VAT 10%'),
                        'vat118' => __('VAT formula 18/118'),
                        'vat110' => __('VAT formula 10/110'),
                        'vat0'   => __('VAT 0%'),
                        'no_vat' => __('No VAT'),
                    ),
                    'description' => __('Setup VAT for delivery', 'woocommerce'),
                    'default'     => 'no_vat',
                ),
                'paymaster_order_status' => array(
                    'title'       => __('Order status', 'woocommerce'),
                    'type'        => 'select',
                    'options'     => wc_get_order_statuses(),
                    'description' => __('Setup order status after successfull payment', 'woocommerce'),
                ),
                'debug'                  => array(
                    'title'   => __('Debug', 'woocommerce'),
                    'type'    => 'checkbox',
                    'label'   => __('Switch on logging in file (<code>woocommerce/logs/paypal.txt</code>)', 'woocommerce'),
                    'default' => 'no',
                ),
                'description'            => array(
                    'title'       => __('Description', 'woocommerce'),
                    'type'        => 'textarea',
                    'description' => __('Description of payment method which user can see in you site.', 'woocommerce'),
                    'default'     => 'Оплата с помощью сервиса приема платежей PayMaster.',
                ),
                'instructions'           => array(
                    'title'       => __('Instructions', 'woocommerce'),
                    'type'        => 'textarea',
                    'description' => __('Instructions which can added in page of thank-you-payment page.', 'woocommerce'),
                    'default'     => 'Оплата с помощью сервиса приема платежей PayMaster. Спасибо за оплату.',
                ),
            );
        }

        /**
         * There are no payment fields for paymaster, but we want to show the description if set.
         **/
        function payment_fields()
        {
            if ($this->description) {
                echo wpautop(wptexturize($this->description));
            }
        }

        /**
         * Generate the dibs button link
         **/
        public function generate_form($order_id)
        {
            global $woocommerce;

            $order = new WC_Order($order_id);

            $action_adr = $this->liveurl;

            $out_summ = $this->getOrderTotal($order);

            $crc = $this->paymaster_merchant . ':' . $out_summ . ':' . $order_id . ':' . $this->paymaster_key1;

            $notify_url = get_site_url().'/'.'?wc-api=wc_paymaster&paymaster=result';
            $success_url = get_site_url().'/'.'?wc-api=wc_paymaster&paymaster=success';
            $fail_url = get_site_url().'/'.'?wc-api=wc_paymaster&paymaster=fail';



            $args = array(
                // Merchant
                'LMI_MERCHANT_ID'    => $this->paymaster_merchant,
                'LMI_PAYMENT_AMOUNT' => $out_summ,
                'LMI_PAYMENT_NO'     => $order_id,
                'LMI_CURRENCY'       => $order->currency,
                'LMI_PAYMENT_DESC'   => 'Оплата заказа №' . $order_id,
                'LMI_PAYMENT_NOTIFICATION_URL' => $notify_url,
	            'LMI_SUCCESS_URL' => $success_url,
	            'LMI_FAILURE_URL' => $fail_url,
                'SIGN'               => md5($crc),
            );

            $pos = 0;
            //Получам продукты в форме
            foreach ($order->get_items() as $product) {
                $args["LMI_SHOPPINGCART.ITEM[{$pos}].NAME"]  = $product['name'];
                $args["LMI_SHOPPINGCART.ITEM[{$pos}].QTY"]   = $product['quantity'];
                $args["LMI_SHOPPINGCART.ITEM[{$pos}].PRICE"] = number_format($product['total'], 2, '.', '');
                $args["LMI_SHOPPINGCART.ITEM[{$pos}].TAX"]   = $this->paymaster_vat_products;
                $pos++;
            }

            $args["LMI_SHOPPINGCART.ITEM[{$pos}].NAME"]  = 'Доставка заказа №' . $order_id;
            $args["LMI_SHOPPINGCART.ITEM[{$pos}].QTY"]   = 1;
            $args["LMI_SHOPPINGCART.ITEM[{$pos}].PRICE"] = number_format($order->shipping_total, 2, '.', '');
            $args["LMI_SHOPPINGCART.ITEM[{$pos}].TAX"]   = $this->paymaster_vat_delivery;

            $args_array = array();

            foreach ($args as $key => $value) {
                $args_array[] = '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" />';
            }

            return
            '<form action="' . esc_url($action_adr) . '" method="POST" id="paymaster_payment_form">' . "\n" .
            implode("\n", $args_array) .
            '<input type="submit" class="button alt" id="submit_paymaster_payment_form" value="' . __('Оплатить', 'woocommerce') . '" /> <a class="button cancel" href="' . $order->get_cancel_order_url() . '">' . __('Отказаться от оплаты & вернуться в корзину', 'woocommerce') . '</a>' . "\n" .
                '</form>';
        }

        /**
         * Process the payment and return the result
         **/
        function process_payment($order_id)
        {
            $order = new WC_Order($order_id);

            return array(
                'result'   => 'success',
                'redirect' => add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay')))),
            );
        }

        /**
         * receipt_page
         **/
        function receipt_page($order)
        {
            echo '<p>' . __('Спасибо за Ваш заказ, пожалуйста, нажмите кнопку ниже, чтобы заплатить.', 'woocommerce') . '</p>';
            echo $this->generate_form($order);
        }

        /**
         * Check Paymaster hash validity
         **/
        function check_hash_value($posted)
        {
            $hash = $posted['LMI_HASH'];

            if ($hash == $this->get_hash($posted)) {
                echo 'OK';
                return true;
            }

            return false;
        }

        /**
         * Функция вычисляющая HASH
         * внимание, чтобы не "городить огород" с передаванием и проверкой дополнительного параметра
         * типа SIGN, как в предыдущих версиях модуля, я сделал проще , смотрите коментарии внутри функции
         **/
        public function get_hash($posted)
        {

            global $woocommerce;

            // Получаем ID продавца не из POST запроса, а из модуля (исключаем, тем самым его подмену)
            $LMI_MERCHANT_ID = $this->paymaster_merchant;

            //Получили номер заказа очень нам он нужен, смотрите ниже, что мы с ним будем вытворять
            $LMI_PAYMENT_NO = $posted['LMI_PAYMENT_NO'];

            //Номер платежа в системе PayMaster
            $LMI_SYS_PAYMENT_ID = $posted['LMI_SYS_PAYMENT_ID'];

            //Дата платежа
            $LMI_SYS_PAYMENT_DATE = $posted['LMI_SYS_PAYMENT_DATE'];

            //А вот здесь идут "фишечки", которые предотвращают замену HASH злоумышленниками :)
            //А именно мы берем  LMI_PAYMENT_AMOUNT и LMI_CURRENCY не из пост запроса, а из модуля

            $order = new WC_Order($LMI_PAYMENT_NO);

            $LMI_PAYMENT_AMOUNT = $this->getOrderTotal($order);

            //Теперь получаем валюту заказа, то что была в заказе

            $LMI_CURRENCY = $order->currency;

            $LMI_PAID_AMOUNT = $posted['LMI_PAID_AMOUNT'];

            $LMI_PAID_CURRENCY = $posted['LMI_PAID_CURRENCY'];
            
            $LMI_PAYMENT_SYSTEM = $posted['LMI_PAYMENT_SYSTEM'];

            $LMI_SIM_MODE = $posted['LMI_SIM_MODE'];

            $SECRET = $this->paymaster_secret;


            $string = $LMI_MERCHANT_ID . ";" . $LMI_PAYMENT_NO . ";" . $LMI_SYS_PAYMENT_ID . ";" . $LMI_SYS_PAYMENT_DATE . ";" . $LMI_PAYMENT_AMOUNT . ";" . $LMI_CURRENCY . ";" . $LMI_PAID_AMOUNT . ";" . $LMI_PAID_CURRENCY . ";" . $LMI_PAYMENT_SYSTEM . ";" . $LMI_SIM_MODE . ";" . $SECRET;


            $hash = base64_encode(hash($this->paymaster_hash_method, $string, TRUE));

    		return $hash;


        }

        /**
         * Возвращаем сумму заказа в формате 0.00
         **/
        public function getOrderTotal($order)
        {
            return number_format($order->order_total, 2, '.', '');
        }

        /**
         * Check Response
         **/
        function check_response()
        {
            global $woocommerce;

            if (isset($_GET['paymaster']) and $_GET['paymaster'] == 'result') {
                @ob_clean();

                $_POST = stripslashes_deep($_POST);

                if ($this->check_hash_value($_POST)) {
                    do_action('valid-paymaster-standard-request', $_POST);
                } else {
                    wp_die('Request Failure');
                }
            } else if (isset($_GET['paymaster']) and $_GET['paymaster'] == 'success') {
                $orderId = $_POST['LMI_PAYMENT_NO'];
                $order  = new WC_Order($orderId);
                WC()->cart->empty_cart();

                wp_redirect($this->get_return_url($order));
            } else if (isset($_GET['paymaster']) and $_GET['paymaster'] == 'fail') {
                $orderId = $_POST['LMI_PAYMENT_NO'];
                $order  = new WC_Order($orderId);
                $order->update_status('failed', __('Платеж не оплачен', 'woocommerce'));

                wp_redirect($order->get_cancel_order_url());
                exit;
            }

        }

        /**
         * Successful Payment!
         **/
        function successful_request($posted)
        {
            global $woocommerce;

            $orderID = $posted['LMI_PAYMENT_NO'];

            $order = new WC_Order($orderID);

            // Check order not already completed
            if ($order->status == 'completed') {
                exit;
            }

            // Payment completed
            $order->add_order_note(__('Payment made with success!', 'woocommerce'));
            $order->update_status($this->paymaster_order_status, __('Order was paid success', 'woocommerce'));
            $order->payment_complete();

            exit;
        }
    }

    /**
     * Add the gateway to WooCommerce
     **/
    function add_paymaster_gateway($methods)
    {
        $methods[] = 'WC_PAYMASTER';

        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'add_paymaster_gateway');
}

?>
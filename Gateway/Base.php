<?php if (!defined('ABSPATH')) {
    exit;
}

/**'
 * Class Paylane_Gateway_Base
 */
abstract class Paylane_Gateway_Base extends WC_Payment_Gateway
{
    /**
     * @var array
     */
    public $settings = array();

    /**
     * @var array
     */
    public $countries = array();

    /**
     * @var string
     */
    protected $gateway_id = 'paylane-custom';

    /**
     * @var string
     */
    protected $design;

    protected $paylane_settings = array();

    /**
     * Constructor for the gateway.
     *
     * @access public
     *
     *
     * @global type $woocommerce
     */
    private static $paylane_methods = array(
        WC_Gateway_Paylane::PAYMENT_METHOD_SECURE_FORM => 'Secure Form',
        WC_Gateway_Paylane::PAYMENT_METHOD_CREDIT_CARD => 'Credit Card',
        WC_Gateway_Paylane::PAYMENT_METHOD_BANK_TRANSFER => 'Bank Transfer',
        WC_Gateway_Paylane::PAYMENT_METHOD_SEPA => 'SEPA',
        WC_Gateway_Paylane::PAYMENT_METHOD_SOFORT => 'Sofort',
        WC_Gateway_Paylane::PAYMENT_METHOD_PAYPAL => 'PayPal',
        WC_Gateway_Paylane::PAYMENT_METHOD_IDEAL => 'iDEAL',
        WC_Gateway_Paylane::PAYMENT_METHOD_APPLEPAY => 'Apple Pay',
        WC_Gateway_Paylane::PAYMENT_METHOD_BLIK0 => 'BLIK',
    );

    /**
     * Paylane_Gateway_Base constructor.
     */
    public function __construct()
    {
        global $woocommerce;

        $this->id = $this->gateway_id;
        $this->has_fields = true;
        $this->notify_link = add_query_arg('wc-api', 'WC_Gateway_Paylane', home_url('/'));
        $this->notify_link_3ds = add_query_arg('wc-api', 'WC_Gateway_Paylane_3ds', home_url('/'));
        $this->supports = array(
            'products',
            'refunds',
            'subscriptions',
            'subscription_cancellation',
            'subscription_suspension',
            'subscription_reactivation',
            'subscription_amount_changes',
            'subscription_date_changes',
            'subscription_payment_method_change',
        );

        $this->first_name = '';
        $this->last_name = '';

        $this->method_description = __(
            sprintf(
                'All settings can be adjusted %s',
                '<a href=" ' . admin_url('admin.php?page=wc-settings&tab=checkout&section=paylane') . '">' . __('here') . '</a>'
            ),
            'wc-gateway-paylane'
        );

        add_filter('payment_fields', array($this, 'payment_fields'));

        $this->init_settings();
        $this->paylane_settings = get_option('woocommerce_paylane_settings');

        $this->method_title = 'Polskie ePłatności: ' . $this->getMethodTitle();
        $this->description = $this->get_paylane_option('description');
        $this->payment_method = $this->get_paylane_option('payment_method');
        $this->secure_form = $this->get_paylane_option('secure_form');
        $this->merchant_id = $this->get_paylane_option('merchant_id');
        $this->fraud_check = $this->get_paylane_option('fraud_check');
        $this->ds_check = 'true'; //$this->get_paylane_option('3ds_check');
        $this->enable_notification = $this->get_paylane_option('notifications_enabled');
        $this->design = $this->get_paylane_option('design', 'basic');
        $this->title = $this->getMethodTitle();

        if (version_compare($woocommerce->version, '3.2.0', '<')) {
            $this->enabled = $this->get_paylane_option($this->form_name . '_legacy_enabled', 'no');
        }

        // add_action('woocommerce_scheduled_subscription_payment_' . $this->id, array($this, 'scheduled_subscription_payment'), 10, 2);
        // add_action('admin_init', array($this, 'handle_subscriptions_hooks'));
        $this->handle_subscriptions_hooks();

    }

    /**
     * Init settings for gateways.
     */
    public function init_settings()
    {
        parent::init_settings();

        $this->enabled = !empty($this->settings['enabled']) && 'yes' === $this->settings['enabled'] ? 'yes' : 'no';
    }

    protected function get_paylane_option($key, $empty_value = null)
    {
        if (isset($this->paylane_settings[$key])) {
            return $this->paylane_settings[$key];
        }

        return $empty_value;
    }

    /**
     * @return string
     */
    protected function getMethodTitle()
    {
        return get_called_class();
    }

    /**
     * @return string
     */
    protected function getGatewayTitle()
    {
        return get_called_class();
    }

    protected function modTitle($org, $custom, $disableSufix = false)
    {
        $org = trim($org);
        $custom = trim($custom);
        if (is_null($custom) || empty($custom) || $org == $custom) {
            $sufix = '';
            // if (!$disableSufix) {
            //     $sufix = ' (Polskie ePłatności)';
            // }
            return $org . $sufix;
        }

        return $custom;
    }

    /**
     * @param string $version
     * @return bool
     */
    public function woocommerce_version_check($version = '3.0')
    {
        if (class_exists('WooCommerce')) {
            global $woocommerce;

            if (version_compare($woocommerce->version, $version, ">=")) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return mixed
     */
    public function get_icon()
    {
        $iconUrl = plugins_url('../assets/pep.svg', __FILE__);
        $iconHtml = '';
        if ($this->get_paylane_option('display_payment_methods_logo', 'yes') == 'yes') {
            $iconHtml .= '<img src="' . $iconUrl . '" class="paylane-payment-method-label-logo" alt="' . esc_attr__(
                'Polskie ePłatności image', 'woocommerce'
            ) . '">';
        }

        return apply_filters('woocommerce_gateway_icon', $iconHtml, $this->id);
    }

    /**
     * Show Polskie ePłatności methods fields at checkout
     */
    public function payment_fields()
    {
        echo $this->prepare_paylane_form($this->form_name);
    }

    /**
     * @return bool
     */
    public function validate_fields()
    {
        $method = $this->get_method_by_class(get_called_class());

        switch ($method) {
            case WC_Gateway_Paylane::PAYMENT_METHOD_CREDIT_CARD:
                $errors = $this->validate_credit_card();
                break;

            case WC_Gateway_Paylane::PAYMENT_METHOD_SEPA:
                $errors = $this->validate_sepa();
                break;

            case WC_Gateway_Paylane::PAYMENT_METHOD_BANK_TRANSFER:
                $errors = $this->validate_transfer();
                break;
            case WC_Gateway_Paylane::PAYMENT_METHOD_BLIK0:
                $errors = $this->validate_blik0();
                break;
            default:
                $errors = array();
        }

        if (!empty($errors)) {
            foreach ($errors as $error) {
                wc_add_notice($error, 'error');
            }

            return false;
        }

        return true;
    }

    /**
     * Function which prepare data and parameters for gateway API and process it to communication function
     *
     * @param $order_id
     * @return array
     */
    public function process_payment($order_id)
    {
        $method = $this->get_method_by_class(get_called_class());

        if (!$method) {
            wc_add_notice(__('Unsupported payment method', 'wc-gateway-paylane'), 'error');
            WCPL_Logger::log("[process_payment]\nUnsupported payment method\norder_id: " . $order_id, 'error');
            return array('success' => false);
        }

        if (!$this->validate_fields()) {
            WCPL_Logger::log("[process_payment]\nNOT valid fields\norder_id: " . $order_id, 'error');
            return array('success' => false);
        }

        global $woocommerce;

        $order = new WC_Order($order_id);
        $current_order_status = $order->get_status();
        if ($current_order_status == 'wc-completed') {
            WCPL_Logger::log("[process_payment]\nTransaction action AFTER COMPLETED\norder_id: " . $order_id, 'error');
            return array('success' => false);
        }
        $order->update_status('on-hold', __('Awaiting payment confirmation', 'wc-gateway-paylane'));

        update_post_meta($order_id, '_payment_method_title', 'Polskie ePłatności - ' . self::$paylane_methods[$method]);

        if ($this->woocommerce_version_check()) {
            wc_reduce_stock_levels($order_id);
        } else {
            $order->reduce_order_stock();
        }

        if ($method != "secure_form") {
            if ($method === 'paypal') {
                $data = array(
                    'sale' => array(
                        'amount' => $order->get_total(),
                        'currency' => get_woocommerce_currency(),
                        'description' => $order_id,
                    ),
                    'back_url' => $this->notify_link,
                );
            } elseif ($method === 'apple_pay') {
                $ap = sanitize_text_field(wcpl_gp_param_isset($_POST, 'paylane_apple_pay_payload'));
                $payload = json_decode(base64_decode($ap), true);

                if (is_null($payload) || !$this->isCorrectpayload($payload)) {
                    wc_add_notice(__('Incorrect Apple Pay payload', 'wc-gateway-paylane'), 'error');
                    WCPL_Logger::log("[process_payment]\nIncorrect Apple Pay payload\norder_id: " . $order_id, 'error');
                    return array('success' => false);
                }

                $data = array(
                    'sale' => array(
                        'amount' => $order->get_total(),
                        'currency' => get_woocommerce_currency(),
                        'description' => $order_id,
                    ),
                    'customer' => $payload['customer'],
                    'card' => [
                        'token' => $payload['card']['token'],
                    ],
                    'back_url' => $this->notify_link,
                );

                $data['customer']['ip'] = $_SERVER['REMOTE_ADDR'];

            } elseif ($method === 'blik0') {
                $data = array(
                    'sale' => array(
                        'amount' => $order->get_total(),
                        'currency' => get_woocommerce_currency(),
                        'description' => $order_id,
                    ),
                    'customer' => [
                        'name' => sanitize_text_field(wcpl_gp_param_isset($_POST, 'billing_first_name')) . ' ' . sanitize_text_field(wcpl_gp_param_isset($_POST, 'billing_last_name')),
                        'email' => sanitize_text_field(wcpl_gp_param_isset($_POST, 'billing_email')),
                        'ip' => WC_Geolocation::get_ip_address(),
                    ],
                    'code' => sanitize_text_field(preg_replace('/\s+/', '', (wcpl_gp_param_isset($_POST, 'blik0_code')))),
                );

            } else {
                $address = $order->get_address('billing');
                $customer_name = $address['first_name'] . ' ' . $address['last_name'];
                $customer_address = $address['address_1'] . ' ' . $address['address_2'];

                $data = array(
                    'sale' => array(
                        'amount' => $order->get_total(),
                        'currency' => get_woocommerce_currency(),
                        'description' => $order_id,
                    ),
                    'customer' => array(
                        'name' => $customer_name,
                        'email' => $address['email'],
                        'ip' => WC_Geolocation::get_ip_address(),
                        'address' => array(
                            'street_house' => $customer_address,
                            'city' => $address['city'],
                            'zip' => $address['postcode'],
                            'country_code' => $address['country'],
                        ),
                    ),
                );

                switch ($method) {
                    case 'credit_card':
                        $data['card'] = array(
                            "token" => sanitize_text_field(wcpl_gp_param_isset($_POST, 'payment_params_token')),
                        );
                        $data['back_url'] = $this->notify_link_3ds;
                        break;

                    case 'sepa':
                        $data['account'] = array(
                            'account_holder' => sanitize_text_field(wcpl_gp_param_isset($_POST, 'sepa_account_holder')),
                            'account_country' => sanitize_text_field(wcpl_gp_param_isset($_POST, 'sepa_account_country')),
                            'iban' => sanitize_text_field(wcpl_gp_param_isset($_POST, 'sepa_iban')),
                            'bic' => sanitize_text_field(wcpl_gp_param_isset($_POST, 'sepa_bic')),
                        );
                        $data['account']['mandate_id'] = $order_id;
                        break;

                    case 'ideal':
                        $data['back_url'] = $this->notify_link;
                        $data['bank_code'] = sanitize_text_field(wcpl_gp_param_isset($_POST, 'bank-code'));
                        break;

                    case 'transfer':
                        $data['payment_type'] = sanitize_text_field(wcpl_gp_param_isset($_POST, 'transfer_bank'));
                        $data['back_url'] = $this->notify_link;
                        break;

                    case 'sofort':
                        $data['back_url'] = $this->notify_link;
                        break;
                }

            }

            @session_start();

            $_SESSION['paylane-data'] = $data;
            $_SESSION['paylane-type'] = $method;
        }

        $this->set_order_paylane_type($order_id, $method);
        WCPL_Logger::log("[process_payment]\nWoocommerce process finished\norder_id: " . $order_id . "\ntype: " . $method);
        return array(
            'result' => 'success',
            'redirect' => add_query_arg(array('order_id' => $order_id, 'type' => $method), $this->notify_link),
        );
    }

    /**
     * Set meta data required to process orders in gateway
     *
     * @param $order_id
     * @param $type
     * @return void
     */
    private function set_order_paylane_type($order_id, $type)
    {
        update_post_meta($order_id, 'paylane-type', $type);
    }

    /**
     * @param $order_id
     * @param $id
     * @return void
     */
    private function set_order_paylane_id($order_id, $id)
    {
        update_post_meta($order_id, 'paylane-id-sale', $id);
    }

    /**
     * @param $class
     * @return null|string
     */
    private function get_method_by_class($class)
    {
        switch ($class) {
            case 'Paylane_Gateway_BankTransfer':
                return 'transfer';
                break;

            case 'Paylane_Gateway_CreditCard':
                return 'credit_card';

                break;
            case 'Paylane_Gateway_Ideal':
                return 'ideal';
                break;

            case 'Paylane_Gateway_Paypal':
                return 'paypal';
                break;

            case 'Paylane_Gateway_Secure':
                return 'secure_form';
                break;

            case 'Paylane_Gateway_Sepa':
                return 'sepa';
                break;

            case 'Paylane_Gateway_Sofort':
                return 'sofort';
                break;

            case 'Paylane_Gateway_ApplePay':
                return 'apple_pay';
                break;

            case 'Paylane_Gateway_Blik0':
                return 'blik0';
                break;

            // Unsupported payment method
            default:
                return null;
        }
    }

    /**
     * for old woocommerce
     * @param $data
     * @param $token
     * @param $communication_id
     */
    public function handle_notification($token, $communication_id)
    {
        if (!isset($_POST['content'])) {
            die(-1);
        }

        if (!is_array($_POST['content'])) {
            die(-2);
        }

        WCPL_Logger::log("[handle_notification]\nResponse\nData: " . json_encode(WCPL_Logger::secure($_POST['content'])), 'info');

        // check communication
        if (!empty($this->get_option('notification_login_PayLane')) && !empty($this->get_option('notification_password_PayLane'))) {
            $this->checkBasicAuth();
        }
        if (empty($_POST['communication_id'])) {
            WCPL_Logger::log("[handle_notification]\nEmpty communication id", 'error');
            die('Empty communication id');
        }

        foreach ($_POST['content'] as $notification) {
            $_txt = json_decode(stripslashes($notification['text']), true);
            if (is_array($_txt)) {
                $order_id = sanitize_text_field($_txt['description']);
            } else {
                $order_id = sanitize_text_field($notification['text']);
            }
            $order = wc_get_order($order_id);

            if (!$order) {
                continue;
            }

            if (empty($notification['type']) || empty($notification['id_sale']) || empty($notification['date']) || empty($notification['amount']) || empty($notification['currency_code']) || empty($notification['text'])) {
                continue;
            }

            $notification['id_sale'] = sanitize_text_field($notification['id_sale']);

            $this->parseNotification($notification, $order);
        }

        die($_POST['communication_id']);
    }

    private function parseNotification($notification, $order)
    {
        $id_sale = $notification['id_sale'];

        $notificationType = get_post_meta($order->get_id(), 'paylane-notification-type', true);

        if ($notificationType === false || ($notificationType !== false && $this->canUpdateStatus($notificationType, $notification['type']))) {
            //first time or not final type

            if ($notification['type'] === 'S') {
                $order->update_status($this->getCorrectOrderStatus($this->get_option('status_successful_order')), '.Polskie ePłatności: ' . __('Transaction complete', 'wc-gateway-paylane'));
                WCPL_Logger::log("[handle_notification]\nTransaction complete (S)\nsale_id: " . $id_sale);

            } elseif ($notification['type'] === 'R') {
                $order->update_status(WC_Gateway_Paylane::ORDER_STATUS_REFUNDED, '.Polskie ePłatności: ' . __('Refund complete', 'wc-gateway-paylane'));
                WCPL_Logger::log("[handle_notification]\nRefund complete (R)\nsale_id: " . $id_sale);
            } elseif ($notification['type'] === 'RV') {
                $order->update_status('on-hold', __('Reversal received', 'wc-gateway-paylane'));
                WCPL_Logger::log("[handle_notification]\nReversal received (RV)\nsale_id: " . $id_sale);
            } elseif ($notification['type'] === 'RRO') {
                $order->update_status('on-hold', __('Retrieval request / chargeback opened', 'wc-gateway-paylane'));
                WCPL_Logger::log("[handle_notification]\nRetrieval request / chargeback opened (RRO)\nsale_id: " . $id_sale);
            } elseif ($notification['type'] === 'CAD') {
                $order->update_status('on-hold', __('Retrieval request / chargeback opened', 'wc-gateway-paylane'));
                WCPL_Logger::log("[handle_notification]\nRetrieval request / chargeback opened (CAD)\nsale_id: " . $id_sale);
            } else {
                $notification['type'] = 'ERROR';
            }

            update_post_meta($order->get_id(), 'paylane-notification-timestamp', time());
            update_post_meta($order->get_id(), 'paylane-notification-type', $notification['type']);
        }

    }

    /**
     * @param $method
     * @return false|null|string
     */
    public function prepare_paylane_form($method)
    {
        $form = null;
        switch ($method) {
            case "secure_form":
                $form = $this->get_form('secure_form');
                break;

            case "credit_card":
                wp_enqueue_script('woocommerce_paylane_api_script', 'https://js.paylane.com/v1/', array());
                $form = $this->get_form('credit_card', array(
                    'api_key' => $this->get_paylane_option('api_key_val'),
                ));
                break;

            case "transfer":
                $form = $this->get_form('forms/transfer');
                break;

            case "sepa":
                $form = $this->get_form('forms/sepa', array(
                    'countries' => $this->get_countries(),
                ));
                break;

            case "sofort":
                $form = $this->get_form('forms/sofort');
                break;

            case "paypal":
                $form = $this->get_form('forms/paypal');
                break;

            case "blik0":
                $form = $this->get_form('forms/blik0');
                break;

            case "ideal":
                $banks = Paylane_Woocommerce_Tools::getIdealBanks(
                    $this->get_paylane_option('login_PayLane'),
                    $this->get_paylane_option('password_PayLane')
                );

                $form = $this->get_form('forms/ideal', array(
                    'banks' => $banks,
                ));
                break;

            case "apple_pay":
                $form = $this->getPreparedForm();
                break;
        }

        return $form;
    }

    /**
     * @return array
     */
    private function validate_credit_card()
    {
        $errors = array();

        if (!isset($_POST['payment_params_token']) || empty($_POST['payment_params_token'])) {
            $errors[] = __('Card token is empty', 'wc-gateway-paylane');
        } else if (!preg_match('/^[a-z\d]{64}$/u', $_POST['payment_params_token'])) {
            $errors[] = __('Unrecognized or malformed token', 'wc-gateway-paylane');
        }

        return $errors;
    }

    /**
     * @return array
     */
    private function validate_sepa()
    {
        $errors = array();

        if (!$_POST["sepa_account_holder"]) {
            $errors[] = __('Account holder name is empty', 'wc-gateway-paylane');
        }

        if (!$_POST["sepa_account_country"]) {
            $errors[] = __('Account country is empty', 'wc-gateway-paylane');
        }

        if (!$_POST["sepa_iban"]) {
            $errors[] = __('IBAN is empty', 'wc-gateway-paylane');
        }

        if (!$_POST["sepa_bic"]) {
            $errors[] = __('BIC is empty', 'wc-gateway-paylane');
        }

        return $errors;
    }

    private function validate_transfer()
    {
        $errors = array();

        if (!isset($_POST['transfer_bank']) || empty($_POST['transfer_bank'])) {
            $errors[] = __('The bank was not chosen', 'wc-gateway-paylane');
        }

        return $errors;
    }

    private function validate_blik0()
    {
        $errors = array();

        if (!isset($_POST['blik0_code']) || empty($_POST['blik0_code'])) {
            $errors[] = __('The CODE is required!', 'wc-gateway-paylane');
        } else {
            $code = preg_replace('/\s+/', '', $_POST['blik0_code']);
            if (strlen($code) != 6 || !is_numeric($code)) {
                $errors[] = __('The CODE is required!', 'wc-gateway-paylane');
            }
        }

        return $errors;
    }

    /**
     * @return void
     */
    protected function checkBasicAuth()
    {
        $user = $this->get_paylane_option('notifications_login');
        $password = $this->get_paylane_option('notifications_password');

        if (
            !isset($_SERVER['PHP_AUTH_USER']) ||
            !isset($_SERVER['PHP_AUTH_PW']) ||
            $user != $_SERVER['PHP_AUTH_USER'] ||
            $password != $_SERVER['PHP_AUTH_PW']
        ) {
            // authentication failed
            header("WWW-Authenticate: Basic realm=\"Secure Area\"");
            header("HTTP/1.0 401 Unauthorized");
            exit();
        }
    }

    /**
     * @return array
     */
    private function get_countries()
    {
        if (!isset($this->countries_obj)) {
            $countries_obj = new WC_Countries();
            $this->countries = $countries_obj->__get('countries');
        }

        return $this->countries;
    }

    /**
     * @param       $form_name
     * @param array $vars
     * @return false|null|string
     */
    protected function get_form($form_name, $vars = array())
    {
        extract($vars);

        $form = __DIR__ . '/../forms/' . $this->design . '/' . basename($form_name) . '.php';

        if (!file_exists($form)) {
            return null;
        }

        ob_start();
        include $form;

        return ob_get_clean();
    }

    public function handle_subscriptions_hooks()
    {
        if (class_exists('WC_Subscriptions_Order')) {
            add_action('woocommerce_scheduled_subscription_payment_' . $this->id, array($this, 'scheduled_subscription_payment'), 10, 2);
        }
    }

    /**
     * @param $amount_to_charge
     * @param $order
     */
    public function scheduled_subscription_payment($amount_to_charge, $order)
    {
        WCPL_Logger::log("[scheduled_subscription_payment]\nInit payment\nAmount: " . $amount_to_charge . "\nOrder id: " . $order->get_id());

        global $woocommerce, $post;

        if (!class_exists('PayLaneRestClient')) {
            require_once __DIR__ . '/../includes/paylane-rest.php';
        }

        $subscriptions = wcs_get_subscriptions_for_renewal_order($order->get_id());
        foreach ($subscriptions as $subscription) {
            $parent_id = $subscription->get_data()['parent_id'];
            $parent_order = new WC_Order($parent_id);

            $params = array(
                'id_sale' => get_post_meta($parent_order->get_id(), 'paylane-id-sale', true),
                'amount' => $amount_to_charge,
                'currency' => get_woocommerce_currency(),
                'description' => $order->get_id(),
            );

            $paymentMethod = get_post_meta($parent_order->get_id(), 'paylane-type', true);
            $this->set_order_paylane_type($order->get_id(), $paymentMethod);

            WCPL_Logger::log("[scheduled_subscription_payment]\nStart payment\nParams: " . json_encode($params) . "\nOrder: " . $order->get_id() . "\nParent Order: " . $parent_order->get_id());

            $client = new PayLaneRestClient($this->get_paylane_option('login_PayLane'), $this->get_paylane_option('password_PayLane'));
            $result = $client->resaleBySale($params);

            if ($client->isSuccess()) {
                $this->set_order_paylane_id($order->get_id(), $result['id_sale']);
                WC_Subscriptions_Manager::process_subscription_payments_on_order($parent_order);

                WCPL_Logger::log("[scheduled_subscription_payment]\nPayment SUCCESS\nid_sale: " . $result['id_sale']);
            } else {
                WCPL_Logger::log("[scheduled_subscription_payment]\nPayment FAILURE\nResult: " . json_encode($result), 'warning');

                WC_Subscriptions_Manager::process_subscription_payment_failure_on_order($parent_order);
            }

        }
    }
}

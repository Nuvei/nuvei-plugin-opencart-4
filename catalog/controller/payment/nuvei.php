<?php

namespace Opencart\Catalog\Controller\Extension\Nuvei\Payment;

require_once DIR_EXTENSION . DIRECTORY_SEPARATOR . 'nuvei' . DIRECTORY_SEPARATOR . 'nuvei_class.php';
require_once DIR_EXTENSION . DIRECTORY_SEPARATOR . 'nuvei' . DIRECTORY_SEPARATOR . 'nuvei_version_resolver.php';

/**
 * @author Nuvei
 */
class Nuvei extends \Opencart\System\Engine\Controller
{
    private $is_user_logged;
	private $order_info;
    private $plugin_settings    = [];
    private $order_addresses    = [];
    private $new_order_status   = 0;
    private $total_curr_alert   = false;
    
	public function index(): string
    {
        $this->load->model('checkout/order');
        $this->load->model('account/address'); // get customer billing address
        $this->load_settings();
        $this->language->load(NUVEI_CONTROLLER_PATH);
        
        $data                   = [];
        $subscriptions          = count($this->getSubscriptions());
        $this->order_info       = $this->model_checkout_order->getOrder($this->session->data['order_id']);
        $this->order_addresses  = $this->get_order_addresses();
        $this->is_user_logged   = !empty($this->session->data['customer_id']) ? 1 : 0;
        $products               = $this->cart->getProducts();

        // Rebilling products checks
        if ($subscriptions > 0) {
            // before call Open Order check for not allowed combination of prdocusts
            if (count($products) > 1) {
                $data['nuvei_error'] = $this->language->get('error_nuvei_products');
                
                \Nuvei_Class::create_log($this->plugin_settings, $data['nuvei_error']);
                
                return $this->load->view(NUVEI_CONTROLLER_PATH, $data);
            }
            
            // disable Rebilling if into the plugin settings Rebilling Plan ID is not set
            if (empty($this->plugin_settings[NUVEI_SETTINGS_PREFIX . 'plan_id'])) {
                $data['nuvei_error'] = $this->language->get('error_nuvei_no_plan_id');
                
                \Nuvei_Class::create_log($this->plugin_settings, $data['nuvei_error']);
                
                return $this->load->view(NUVEI_CONTROLLER_PATH, $data);
            }
        }
                
        // Open Order
        $order_data = $this->open_order($products);
		
		if(empty($order_data) || empty($order_data['sessionToken'])) {
			\Nuvei_Class::create_log(
                $this->plugin_settings, 
                $order_data, 
                'Open Order problem with the response', 
                'CRITICAL'
            );
			
            $data['nuvei_error'] = $this->language->get('error_nuvei_gateway');
            return $this->load->view(NUVEI_CONTROLLER_PATH, $data);
		}
        
        $pm_black_list = [];
        
        if(!empty($this->plugin_settings[NUVEI_SETTINGS_PREFIX . 'block_pms'])
            && is_array($this->plugin_settings[NUVEI_SETTINGS_PREFIX . 'block_pms'])
        ) {
            $pm_black_list = $this->plugin_settings[NUVEI_SETTINGS_PREFIX . 'block_pms'];
        }
        
        $use_upos = $save_pm = (bool) $this->plugin_settings[NUVEI_SETTINGS_PREFIX . 'use_upos'];
        
        if(0 == $this->is_user_logged) {
            $use_upos = $save_pm = false;
        }
        elseif($subscriptions > 0) {
            $save_pm = 'always';
        }
        
        $test_mode  = $this->plugin_settings[NUVEI_SETTINGS_PREFIX . 'test_mode'];
        $useDCC     = $this->plugin_settings[NUVEI_SETTINGS_PREFIX . 'use_dcc'];
        
        if (0 == $order_data['amount']) {
            $useDCC = 'false';
        }
        
        $locale     = substr($this->get_locale(), 0, 2);
        $sdk_transl = html_entity_decode($this->plugin_settings[NUVEI_SETTINGS_PREFIX . 'sdk_transl']);
        
        $data['nuvei_sdk_params'] = [
            'renderTo'                  => '#nuvei_checkout',
            'strict'                    => false,
            'alwaysCollectCvv'          => true,
            'maskCvv'                   => true,
            'showResponseMessage'       => false,
            'sessionToken'              => $order_data['sessionToken'],
            'env'                       => 1 == $test_mode ? 'test' : 'prod',
            'merchantId'                => trim($this->plugin_settings[NUVEI_SETTINGS_PREFIX . 'merchantId']),
            'merchantSiteId'            => trim($this->plugin_settings[NUVEI_SETTINGS_PREFIX . 'merchantSiteId']),
            'country'                   => $order_data['billingAddress']['country'],
            'currency'                  => $order_data['currency'],
            'amount'                    => $order_data['amount'],
            'useDCC'                    => $useDCC,
            'savePM'                    => $save_pm,
            'showUserPaymentOptions'    => $use_upos,
            'pmWhitelist'               => null,
            'pmBlacklist'               => $pm_black_list,
            'email'                     => $order_data['billingAddress']['email'],
            'fullName'                  => trim($order_data['billingAddress']['firstName'] 
                . ' ' . $order_data['billingAddress']['lastName']),
            'payButton'                 => $this->plugin_settings[NUVEI_SETTINGS_PREFIX . 'pay_btn_text'],
            'locale'                    => $locale,
            'autoOpenPM'                => (bool) $this->plugin_settings[NUVEI_SETTINGS_PREFIX . 'auto_expand_pms'],
            'logLevel'                  => $this->plugin_settings[NUVEI_SETTINGS_PREFIX . 'sdk_log_level'],
            'i18n'                      => json_decode($sdk_transl, true),
            'theme'                     => $this->plugin_settings[NUVEI_SETTINGS_PREFIX . 'sdk_theme'],
            'apmConfig'                 => [
                'googlePay' => [
                    'locale' => $locale
                ]
            ],
            'sourceApplication'         => NUVEI_SOURCE_APP
//            'apmWindowType'             => $this->plugin_settings[NUVEI_SETTINGS_PREFIX . 'apm_window_type'],
        ];
        
        $data['language']               = $this->config->get('config_language');
        $data['NUVEI_CONTROLLER_PATH']  = NUVEI_CONTROLLER_PATH;
        $data['sdkUrl']                 = $this->getSdkUrl();
        
        // check for product with a plan
        if($subscriptions > 0 || 0 == $order_data['amount']) {
            $data['nuvei_sdk_params']['pmWhitelist'][] = 'cc_card';
            unset($data['nuvei_sdk_params']['pmBlacklist']);
            
            if ($subscriptions > 0) {
                $data['is_nuvei_rebilling'] = 1;
            }
        }
        
        $data['NUVEI_PLUGIN_CODE']  = NUVEI_PLUGIN_CODE;
        $data['ocVersionInt']       = (int) str_replace('.', '', VERSION);
        
        \Nuvei_Class::create_log($data['nuvei_sdk_params'], 'nuvei_sdk_params');
        
        return $this->load->view(NUVEI_CONTROLLER_PATH, $data);
	}
    
    /**
     * This function is called from an event to include the
     * Checkout SDK to the Catalog.
     */
    public function event_add_sdk_lib(): void
    {
        $this->load_settings();
        
        \Nuvei_Class::create_log($this->plugin_settings, 'event_add_sdk_lib');
        
        $this->document->addScript($this->getSdkUrl());
    }
    
    /**
     * This function is called from an event to prevent
     * combining a product with Nuvei Payment Plan with ordinary product.
     * 
     * @return void
     */
    public function event_before_add_product(): void
    {
        $this->load_settings();
        
        \Nuvei_Class::create_log($this->plugin_settings, 'event_before_add_product');
        
        // in case there is no incoming product
        if (empty($this->request->post['product_id'])) {
            return;
        }
        
        $this->load->model('checkout/cart');
        $this->language->load(NUVEI_CONTROLLER_PATH);
        
        $json       = [];
		$products   = $this->model_checkout_cart->getProducts(); // get the cart products
        
        # 1. if cart is empty
        if (count($products) == 0) {
            // 1.1. if user is logged in - continue
            if (!empty($this->session->data['customer_id'])) {
                return;
            }
            
            // 1.2. if the user is not logged in and there is Nuvei Subs, do not add the product
            if (!empty($this->request->post['subscription_plan_id'])
                && is_numeric($this->request->post['subscription_plan_id'])
            ) {
                $this->load->model('extension/nuvei/payment/nuvei');

                if ($this->model_extension_nuvei_payment_nuvei
                    ->isNuveiSubscr($this->request->post['subscription_plan_id'])
                ) {
                    $json['nuvei_add_product_error'] = $this->language->get('error_guest_nuvei_subs');
                }
            }
            
        }
        # 2. in case cart is not empty
        else {
            // 2.1. incoming product has Subs Plan, check the plan
            if (!empty($this->request->post['subscription_plan_id'])
                && is_numeric($this->request->post['subscription_plan_id'])
            ) {
                $this->load->model('extension/nuvei/payment/nuvei');

                if ($this->model_extension_nuvei_payment_nuvei->isNuveiSubscr($this->request->post['subscription_plan_id'])) {
                    $json['nuvei_add_product_error'] = $this->language->get('error_nuvei_subs_prod');
                }
            }

            // 2.2. incoming product does not have Nuvei Subs plan. Check for Nuvei plan into the cart
            foreach ($products as $product) {
                if (!empty($product['subscription']['name'])
                    && false !== strpos(strtolower($product['subscription']['name']), 'nuvei')
                ) {
                    $json['nuvei_add_product_error'] = $this->language->get('error_nuvei_subs_prod');
                    break;
                }
            }
        }
        
        // if there is a Nuvei Subscr Plan stop adding
        if (!empty($json)) {
            $this->load_settings();
            
            \Nuvei_Class::create_log($this->plugin_settings, $json, 'Before Add Product event error');
            
            header('Content-Type: application/json');
            exit(json_encode($json));
        }
        
        return;
    }
    
    public function event_add_product_mod()
    {
        $this->document->addScript('/extension/nuvei/catalog/view/javascript/nuvei_product_mod.js', 'footer');
        
        if (4020 >= \Nuvei_Class::get_plugin_version()) {
            $this->document->addScript('/extension/nuvei/catalog/view/javascript/nuvei_hide_sdk_container.js', 'footer');
        }
    }
    
    public function event_check_subsc_data(&$route, &$args)
    {
        if (empty($args)) {
            return;
        }
        
        // add some value for the date_next
        foreach ($args as $key => $arg) {
            if (empty($arg['products']) || !is_array($arg['products'])) {
                continue;
            }
            
            foreach ($arg['products'] as $key2 => $product) {
                if (empty($product['subscription']) || !is_array($product['subscription'])) {
                    continue;
                }
                
                if (isset($product['subscription']['date_next'])) {
                    continue;
                }
                
                // if there is no trial period the payment starts same day
                if (isset($product['subscription']['trial_status'])
                    && 0 == $product['subscription']['trial_status']
                ) {
                    $args[$key]['products'][$key2]['subscription']['date_next'] = date('Y-m-d H:i:s');
                    continue;
                }
                
                // if there is trial, we have to calculate the next payment day
                $date_next = '';

                if ('day' == $product['subscription']['trial_frequency']) {
                    // day - days
                    if ($product['subscription']['trial_cycle'] > 1) {
                        $date_next = date('Y-m-d H:i:s', strtotime('+ ' 
                            . $product['subscription']['trial_cycle'] . ' days'));
                    } else {
                        $date_next = date('Y-m-d H:i:s', strtotime('+ 1 day'));
                    }
                }
                if ('week' == $product['subscription']['trial_frequency']) {
                    // 7 * week nums
                    if ($product['subscription']['trial_cycle'] > 1) {
                        $date_next = date('Y-m-d H:i:s', strtotime('+ ' 
                            . ($product['subscription']['trial_cycle'] * 7) . ' days'));
                    } else {
                        $date_next = date('Y-m-d H:i:s', strtotime('+ 7 days'));
                    }
                }
                if ('month' == $product['subscription']['trial_frequency']) {
                    // month - months
                    if ($product['subscription']['trial_cycle'] > 1) {
                        $date_next = date('Y-m-d H:i:s', strtotime('+ ' 
                            . $product['subscription']['trial_cycle'] . ' months'));
                    } else {
                        $date_next = date('Y-m-d H:i:s', strtotime('+ 1 month'));
                    }
                }
                if ('year' == $product['subscription']['trial_frequency']) {
                    // year - years
                    if ($product['subscription']['trial_cycle'] > 1) {
                        $date_next = date('Y-m-d H:i:s', strtotime('+ ' 
                            . $product['subscription']['trial_cycle'] . ' years'));
                    }
                    else {
                        $date_next = date('Y-m-d H:i:s', strtotime('+ 1 year'));
                    }
                }

                $args[$key]['products'][$key2]['subscription']['date_next'] = $date_next;
            }
        }
    }
    
    public function event_filter_payment_providers(&$route, &$data, &$methods)
    {
        $this->load_settings();
        
        \Nuvei_Class::create_log($this->plugin_settings, $methods, 'event_filter_payment_providers');
        
        $rebilling_data     = $this->getSubscriptions();
        $remove_providers   = false;
        
        if(count($rebilling_data) > 0) {
            foreach($rebilling_data as $reb_data) {
                // check for nuvei into subscription name
                if (strpos(strtolower($reb_data['subscription']['name']), NUVEI_PLUGIN_CODE) !== false) {
                    $remove_providers = true;
                    break;
                }
        
            }
        }
        
        if ($remove_providers && isset($methods[NUVEI_PLUGIN_CODE])) {
            $methods = [
                NUVEI_PLUGIN_CODE => $methods[NUVEI_PLUGIN_CODE]
            ];
            
        }
    }
    
    /**
     * We use this method only to set the Order to pending.
     * 
     * TODO - remove it in some of the next versions!
     * 
     * @return void
     * @deprecated since version 1.8
     */
    public function confirm(): void
    {
        $this->load_settings();
        $this->load->language(NUVEI_CONTROLLER_PATH);
        
        \Nuvei_Class::create_log($this->plugin_settings, $this->request->post, 'confirm page');

		$json = [];
                
        // set errors
		if (!isset($this->session->data['order_id'])) {
            \Nuvei_Class::create_log($this->plugin_settings, 'Missing session order_id.');
            
			$json['error'] = $this->language->get('error_order_id');
		}
        
        if (empty($this->session->data['payment_method'])) {
            \Nuvei_Class::create_log($this->plugin_settings, 'Session Payment method is empty.');
            
			$json['error'] = $this->language->get('error_payment_method');
        }
        
        $session_payment_method = \Nuvei_Version_Resolver::get_checkout_pm($this->session->data);
        
        // expected "nuvei" or "nuvei.nuvei"
		if (false === strpos($session_payment_method, NUVEI_PLUGIN_CODE)) {
            \Nuvei_Class::create_log(
                $this->plugin_settings,
                [
                    'NUVEI_PLUGIN_CODE'         => NUVEI_PLUGIN_CODE,
                    'session payment_method'    => $this->session->data['payment_method'],
                ],
                'Payment method is incorrect.'
            );
            
			$json['error'] = $this->language->get('error_payment_method');
		}
        
        if(empty($this->request->post['nuvei_tr_id'])
            || !is_numeric($this->request->post['nuvei_tr_id'])
		) {
            \Nuvei_Class::create_log($this->plugin_settings, 'nuvei_tr_id is empty or missing.');
        
			$json['error'] = $this->language->get('error_missing_tr_id');
		}
        // /set errors
        
		if (!$json) {
            $this->load->model('checkout/order');
            
            $this->order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
            
            // the order is still invisible into the admin
            if (isset($this->order_info['order_status_id'])
                && (int) $this->order_info['order_status_id'] == 0
            ) {
                    $this->model_checkout_order->addHistory(
                        $this->session->data['order_id'], 
                        $this->config->get(NUVEI_SETTINGS_PREFIX . 'pending_status_id')
                    );
                }
                
            $this->session->data['nuvei_last_oo_details'] = [];
            
			$json['redirect'] = $this->url->link(
                'checkout/success',
                'order_id=' . $this->session->data['order_id']
                    . '&language=' . $this->config->get('config_language'), 
                true
            );
        }
        
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
    }
    
    /**
     * Receive DMNs here
     */
	public function callback()
    {
        $this->load_settings();
        $this->load->model('checkout/order');
        
        \Nuvei_Class::create_log($this->plugin_settings, $_REQUEST, 'DMN request');
        
        ### Manual stop DMN is possible only in test mode
//        \Nuvei_Class::create_log($this->plugin_settings, 'manually stoped');
//        die('manually stoped');
        
        // exit
        if (\Nuvei_Class::get_param('type') == 'CARD_TOKENIZATION') {
            $this->return_message('DMN report: CARD_TOKENIZATION. The process ends here.');
        }
        
        $req_status = $this->get_request_status();
        
        // exit
//        if('pending' == strtolower($req_status)) {
//            $this->return_message('DMN status is Pending. Wait for another status.');
//		}
        
        // exit
        if(!$this->validate_dmn()) {
            $this->return_message('DMN report: You receive DMN from not trusted source. The process ends here.');
        }
        
        // check for Subscription State DMN
        $this->process_subs_state();
        
        // check for Subscription Payment DMN
        $this->process_subs_payment();
        
        $this->get_order_info_by_dmn();
        
        // exit
        if (!empty($this->order_info['payment_custom_field'])
            && 'pending' == strtolower($req_status)
        ) {
            $this->return_message('This is Pending DMN, but Order is already processed.');
        }
        
        $order_id = $this->order_info['order_id'];
        
        $this->new_order_status = $this->order_info['order_status_id'];
        
        $trans_type = \Nuvei_Class::get_param('transactionType');
        
        # Sale and Auth
        if(in_array($trans_type, array('Sale', 'Auth'))) {
            \Nuvei_Class::create_log(
                $this->plugin_settings,
                array(
                    'order_status_id'           => $this->order_info['order_status_id'],
                    'default complete status'   => $this->config->get(NUVEI_SETTINGS_PREFIX . 'order_status_id'),
                ),
                'DMN Sale/Auth compare order status and default complete status:'
            );
            
            // on decline do not finish the order
            if ('declined' == strtolower($req_status)) {
                $this->return_message('Declined DMN received. Wait for new payment try.');
            }
            
			// if is different than the default Complete status
			if($this->order_info['order_status_id'] 
                != $this->config->get(NUVEI_SETTINGS_PREFIX . 'order_status_id')
            ) {
				$this->change_order_status($order_id, $req_status, $trans_type);
			}
            
            $this->update_custom_fields($order_id);
            $this->subscription_start($trans_type, $order_id);
            $this->return_message('DMN Sale/Auth received.');
        }
        
        # Refund
        if(in_array($trans_type, array('Credit', 'Refund'))) {
            $this->update_custom_fields($order_id);
            $this->change_order_status($order_id, $req_status, 'Credit');
            $this->return_message('DMN Refund received.');
        }
        
        # Void, Settle
        if(in_array($trans_type, array('Void', 'Settle'))) {
            $this->update_custom_fields($order_id);
            $this->change_order_status($order_id, $req_status, $trans_type);
            
            if ('Settle' == $trans_type) {
                \Nuvei_Class::create_log($this->plugin_settings, 'DMN Settle');
                $this->subscription_start($trans_type, $order_id);
            }
            else {
                \Nuvei_Class::create_log($this->plugin_settings, 'DMN Void');
                $this->subscription_cancel($trans_type);
            }
            
			$this->return_message('DMN Void/Settle received.');
        }
        
        $this->return_message('DMN was not recognized!');
	}
    
    public function checkout_pre_payment(): void
    {
        // load needed models
        $this->load->model('account/address');
        $this->load->model('checkout/cart');
        $this->load->model('checkout/order');
        $this->load_settings();
        
        $this->response->addHeader('Content-Type: application/json');

        if (!isset($this->session->data['order_id'])) {
            \Nuvei_Class::create_log(
                $this->plugin_settings,
                $this->session->data,
                'Missing Order ID into the session.',
                'WARN'
            );
            
            $this->response->setOutput(json_encode(['success' => 0]));
            return;
        }

        $this->order_info       = $this->model_checkout_order->getOrder($this->session->data['order_id']);
        $this->order_addresses  = $this->get_order_addresses();
        $products               = $this->get_products_main_data($this->model_checkout_cart->getProducts());
        $nuvei_last_oo_details  = [];
        
        if (isset($this->session->data['nuvei_last_oo_details'])) {
            $nuvei_last_oo_details = $this->session->data['nuvei_last_oo_details'];
        }
        
        \Nuvei_Class::create_log(
            $this->plugin_settings,
            [
                $nuvei_last_oo_details,
                $products,
                md5(serialize($products))
            ]
        );
        
        // success
        if (!empty($nuvei_last_oo_details['productsHash'])
            && $nuvei_last_oo_details['productsHash'] == md5(serialize($products))
        ) {
            $this->response->setOutput(json_encode(['success' => 1]));
            return;
        }
        
        // on error
        $this->response->setOutput(json_encode(['success' => 0]));
        return;
    }
    
    private function get_products_main_data($products): array
    {
        $products_main_data = [];
        
        foreach ($products as $product) {
            $products_main_data[] = [
                'cart_id'       => @$product['cart_id'],
                'product_id'    => @$product['product_id'],
                'name'          => @$product['name'],
                'model'         => @$product['model'],
                'option'        => @$product['option'],
                'quantity'      => @$product['quantity'],
                'price'         => @$product['price'],
                'total'         => @$product['total'],
            ];
        }
        
        return $products_main_data;
    }
    
    private function open_order($products): array
    {
        \Nuvei_Class::create_log($this->plugin_settings, 'open_order()');
        
        // check for product quantity just before pay
//        $this->load->model('checkout/cart');
//        
//        if ($is_ajax) {
//            $this->load->model('catalog/product');
//            
//            foreach ($this->cart->getProducts() as $data) {
//                $prod_data = $this->model_catalog_product->getProduct($data['product_id']);
//                
//                if (0 >= (float) $prod_data['quantity']) {
//                    $this->language->load(NUVEI_CONTROLLER_PATH);
//                    
//                    \Nuvei_Class::create_log($this->plugin_settings, $prod_data, 'Not enough quantity for a product.');
//                    
//                    $resp = [
//                        'status'    => 'error',
//                        'msg'       => $this->language->get('error_product_quantity'),
//                    ];
//                    
//                    $this->response->addHeader('Content-Type: application/json');
//                    $this->response->setOutput(json_encode($resp));
//                    
//                    return $resp;
//                }
//            }
//        }
        
        $amount                 = $this->get_price($this->order_info['total']);
        $rebilling_params       = $this->preprare_rebilling_params();
        $try_update_order       = false;
        $nuvei_last_oo_details  = [];
        $products_main_data     = $this->get_products_main_data($products);
        $session_tr_type        = '';
        
        if (isset($this->session->data['nuvei_last_oo_details'])) {
            $nuvei_last_oo_details = $this->session->data['nuvei_last_oo_details'];
        }
        
        # try to update Order
        if (! (empty($nuvei_last_oo_details['userTokenId']) 
            && !empty($rebilling_params['merchantDetails']['customField3']))
        ) {
            \Nuvei_Class::create_log(
                $this->plugin_settings,
                'Added product with subscription.'
            );
            
            $try_update_order = true;
        }
        
        if (empty($nuvei_last_oo_details['transactionType'])) {
            \Nuvei_Class::create_log(
                $this->plugin_settings,
                'transactionType is empty.'
            );
            
            $try_update_order = false;
        }
        else {
            $session_tr_type = $nuvei_last_oo_details['transactionType'];
        }
        
        if ($amount == 0
            && (empty($session_tr_type) || 'Auth' != $session_tr_type)
        ) {
            \Nuvei_Class::create_log(
                $this->plugin_settings,
                $session_tr_type,
                '0-order with not allowed transaction type.'
            );
            
            $try_update_order = false;
        }
        
        if ($amount > 0
            && !empty($session_tr_type)
            && 'Auth' == $session_tr_type
            && $session_tr_type != $this->plugin_settings[NUVEI_SETTINGS_PREFIX . 'payment_action']
        ) {
            \Nuvei_Class::create_log(
                $this->plugin_settings,
                @$session_tr_type,
                'Non-0-total with not allowed payment action.'
            );
            
            $try_update_order = false;
        }
        
        if ($try_update_order) {
            $resp = $this->update_order($products_main_data);
            
            if (!empty($resp['status']) && 'SUCCESS' == $resp['status']) {
                return $resp;
            }
        }
        # /try to update Order
        
		$oo_params = array(
			'clientUniqueId'	=> $this->session->data['order_id'] . '_' . uniqid(),
			'amount'            => $amount,
            'transactionType'	=> (float) $amount == 0 
                ? 'Auth' : $this->plugin_settings[NUVEI_SETTINGS_PREFIX . 'payment_action'],
			'currency'          => $this->order_info['currency_code'],
            'userDetails'       => $this->order_addresses['billingAddress'],
			'billingAddress'	=> $this->order_addresses['billingAddress'],
            'shippingAddress'   => $this->order_addresses['shippingAddress'],
            'userTokenId'       => $this->order_addresses['billingAddress']['email'],
			'urlDetails'        => array(
				'backUrl'			=> $this->url->link('checkout/checkout', '', true),
				'notificationUrl'   => $this->url->link(NUVEI_CONTROLLER_PATH . '%7Ccallback'),
			),
		);
        
        // change urlDetails
        if (!empty($this->plugin_settings[NUVEI_SETTINGS_PREFIX . 'auto_close_apm_popup'])) {
            $oo_params['urlDetails']['successUrl']  = $oo_params['urlDetails']['failureUrl']
                                                    = $oo_params['urlDetails']['pendingUrl']
                                                    = NUVEI_SDK_AUTOCLOSE_URL;
        }
        
        $oo_params = array_merge_recursive($oo_params, $rebilling_params);
        
        $oo_params['merchantDetails']['customField1'] = $amount;
        $oo_params['merchantDetails']['customField4'] = $this->order_info['currency_code'];
        
		$resp = \Nuvei_Class::call_rest_api(
            'openOrder',
            $this->plugin_settings,
            array('merchantId', 'merchantSiteId', 'clientRequestId', 'amount', 'currency', 'timeStamp'),
            $oo_params
        );
		
		if (empty($resp['status']) || empty($resp['sessionToken']) || 'SUCCESS' != $resp['status']) {
			if(!empty($resp['message'])) {
				return $resp;
			}
			
			return [];
		}
        
        // set them to session for the check before submit the data to the webSDK
        $this->session->data['nuvei_last_oo_details']['productsHash']       = md5(serialize($products_main_data));
        $this->session->data['nuvei_last_oo_details']['amount']             = $oo_params['amount'];
        $this->session->data['nuvei_last_oo_details']['transactionType']    = $oo_params['transactionType'];
        $this->session->data['nuvei_last_oo_details']['sessionToken']       = $resp['sessionToken'];
        $this->session->data['nuvei_last_oo_details']['clientRequestId']    = $resp['clientRequestId'];
        $this->session->data['nuvei_last_oo_details']['orderId']            = $resp['orderId'];
        $this->session->data['nuvei_last_oo_details']['userTokenId']        = $oo_params['userTokenId'];
        $this->session->data['nuvei_last_oo_details']['billingAddress']['country']
            = $oo_params['billingAddress']['country'];
        
        $oo_params['sessionToken'] = $resp['sessionToken'];
		
		return $oo_params;
	}

    /**
     * Function validate_dmn
     * Check if the DMN is not fake.
     * 
     * @return boolean
     */
    private function validate_dmn()
    {
        $advanceResponseChecksum = \Nuvei_Class::get_param('advanceResponseChecksum');
		$responsechecksum        = \Nuvei_Class::get_param('responsechecksum');
		
		if (empty($advanceResponseChecksum) && empty($responsechecksum)) {
            \Nuvei_Class::create_log(
                $this->plugin_settings,
                'advanceResponseChecksum and responsechecksum parameters are empty.',
                '',
                'CRITICAL'
            );
			return false;
		}
		
		// advanceResponseChecksum case
		if (!empty($advanceResponseChecksum)) {
            $str = hash(
                $this->config->get(NUVEI_SETTINGS_PREFIX . 'hash'),
                trim($this->config->get(NUVEI_SETTINGS_PREFIX . 'secret'))
                    . \Nuvei_Class::get_param('totalAmount')
                    . \Nuvei_Class::get_param('currency')
                    . \Nuvei_Class::get_param('responseTimeStamp')
                    . \Nuvei_Class::get_param('PPP_TransactionID')
                    . $this->get_request_status()
                    . \Nuvei_Class::get_param('productId')
            );

            if (\Nuvei_Class::get_param('advanceResponseChecksum') == $str) {
                return true;
            }

            \Nuvei_Class::create_log(
                $this->plugin_settings,
                [
                    'checksum string' => \Nuvei_Class::get_param('totalAmount')
                        . \Nuvei_Class::get_param('currency')
                        . \Nuvei_Class::get_param('responseTimeStamp')
                        . \Nuvei_Class::get_param('PPP_TransactionID')
                        . $this->get_request_status()
                        . \Nuvei_Class::get_param('productId')
                ],
                'advanceResponseChecksum validation fail.',
                'WARN'
            );
            return false;
		}
		
		# subscription DMN with responsechecksum case
		$concat        = '';
		$request_arr   = $_REQUEST;
		$custom_params = array(
			'route'             => '',
			'responsechecksum'  => '',
		);
		
		// remove parameters not part of the checksum
		$dmn_params = array_diff_key($request_arr, $custom_params);
		$concat     = implode('', $dmn_params);
		
		$concat_final = $concat . trim($this->config->get(NUVEI_SETTINGS_PREFIX . 'secret'));
		$checksum     = hash($this->config->get(NUVEI_SETTINGS_PREFIX . 'hash'), $concat_final);
		
		if ($responsechecksum !== $checksum) {
            \Nuvei_Class::create_log(
                $this->plugin_settings,
                'responsechecksum validation fail.',
                '',
                'WARN'
            );
			return false;
		}
		
		return true;
	}
    
    /**
     * Function get_request_status
     * 
     * We need this stupid function because as response request variable
     * we get 'Status' or 'status'...
     * 
     * @return string
     */
    private function get_request_status($params = array())
    {
        if(empty($params)) {
            if(isset($_REQUEST['Status'])) {
                return $_REQUEST['Status'];
            }

            if(isset($_REQUEST['status'])) {
                return $_REQUEST['status'];
            }
        }
        else {
            if(isset($params['Status'])) {
                return $params['Status'];
            }

            if(isset($params['status'])) {
                return $params['status'];
            }
        }
        
        return '';
    }
    
    /**
     * Function get_locale
     * Extract locale code in format "en_GB"
     * 
     * @return string
     */
    private function get_locale()
    {
		$langs = $this->model_localisation_language->getLanguages();
        
        \Nuvei_Class::create_log($langs, 'get_locale');
        
        $langs = current($langs);
        
        if(isset($langs['locale']) && $langs['locale'] != '') {
            $locale_parts = explode(',', $langs['locale']);
            
            foreach($locale_parts as $part) {
                if(strlen($part) == 5 && strpos($part, '_') != false) {
                    return $part;
                }
            }
        }
        
        return '';
	}
    
    /**
     * Function change_order_status
     * Change the status of the order.
     * 
     * @param int $order_id - escaped
     * @param string $status
     * @param string $transactionType - not mandatory for the DMN
     */
    private function change_order_status($order_id, $status, $transactionType = ''): void
    {
        \Nuvei_Class::create_log($this->plugin_settings, 'change_order_status()');
        
        $message		= '';
        $send_message	= true;
        $trans_id       = (int) \Nuvei_Class::get_param('TransactionID');
        $rel_tr_id      = (int) \Nuvei_Class::get_param('relatedTransactionId');
        $payment_method = \Nuvei_Class::get_param('payment_method');
        $total_amount   = (float) \Nuvei_Class::get_param('totalAmount');
        $status_id      = $this->order_info['order_status_id'];
        $order_total    = $this->get_price($this->order_info['total']);
        
        $comment_details = '<br/>' 
            . $this->language->get('Status: ') . $status . '<br/>'
            . $this->language->get('Transaction Type: ') . $transactionType . '<br/>'
            . $this->language->get('Transaction ID: ') . $trans_id . '<br/>'
            . $this->language->get('Related Transaction ID: ') . $rel_tr_id . '<br/>'
            . $this->language->get('Payment Method: ') . $payment_method . '<br/>'
            . $this->language->get('Total Amount: ') . $total_amount . '<br/>'
            . $this->language->get('Currency: ') . \Nuvei_Class::get_param('currency') . '<br/>';
                
        switch($status) {
            case 'CANCELED':
                $message = $this->language->get('Your request was Canceled.') . $comment_details;
                break;

            case 'APPROVED':
                if($transactionType == 'Void') {
                    $message    = $this->language->get('Your Order was Voided.') . $comment_details;
                    $status_id  = $this->config->get(NUVEI_SETTINGS_PREFIX . 'canceled_status_id');
                    break;
                }
                
                // Refund
                if($transactionType == 'Credit') {
                    $send_message   = false;
                    $status_id      = $this->config->get(NUVEI_SETTINGS_PREFIX . 'refunded_status_id');

                    $message = $this->language->get('Your Order was Refunded.') . $comment_details;

                    $formated_refund = $this->currency->format(
                        $total_amount,
                        $this->order_info['currency_code'],
                        1 // because we pass converted amount, else - $this->order_info['currency_value']
                    );

                    $message .= $this->language->get('Refund Amount: ') . $formated_refund;
                    
                    break;
                }
                
                $status_id = $this->config->get(NUVEI_SETTINGS_PREFIX . 'order_status_id'); // "completed"
                
                if($transactionType == 'Auth') {
                    $message    = $this->language->get('The amount has been authorized and wait for Settle.');
                    $status_id  = $this->config->get(NUVEI_SETTINGS_PREFIX . 'pending_status_id');
                    
                    if(0 == $total_amount) {
                        $status_id  = $this->config->get(NUVEI_SETTINGS_PREFIX . 'order_status_id');
                        $message    = $this->language->get('The amount has been authorized.');
                    }
                }
                elseif($transactionType == 'Settle') {
                    $message = $this->language->get('The amount has been captured by Nuvei.');
                }
                // set the Order status to Complete
                elseif($transactionType == 'Sale') {
                    $message = $this->language->get('The amount has been authorized and captured by Nuvei.');
                }
                
                // check for different Order Amount
                if(in_array($transactionType, array('Sale', 'Auth'))) {
                    $original_amount    = (float) \Nuvei_Class::get_param('customField1');
                    $original_curr      = \Nuvei_Class::get_param('customField4');
                    
                    if ($order_total != $total_amount && $order_total != $original_amount) {
                        $this->total_curr_alert = true;
                    }
                    
                    if ($this->order_info['currency_code'] != \Nuvei_Class::get_param('currency') 
                        && $this->order_info['currency_code'] != $original_curr
                    ) {
                        $this->total_curr_alert = true;
                    }
                    
                    if ($this->total_curr_alert) {
                        $msg = $this->language->get('Attention - the Order total is ') 
                            . $this->order_info['currency_code'] . ' ' . $order_total
                            . $this->language->get(', but the Captured amount is ')
                            . \Nuvei_Class::get_param('currency')
                            . ' ' . $total_amount . '!';

                        $this->model_checkout_order->addHistory($order_id, $status_id, $msg, false);
                    }
                }
                
				$message .= $comment_details;
                break;

            case 'ERROR':
            case 'DECLINED':
            case 'FAIL':
                $message = $this->language->get('Your request faild.') . $comment_details
                    . $this->language->get('Reason: ');
                
                if( ($reason = \Nuvei_Class::get_param('reason')) ) {
                    $message .= $reason;
                }
                elseif( ($reason = \Nuvei_Class::get_param('Reason')) ) {
                    $message .= $reason;
                }
                elseif( ($reason = \Nuvei_Class::get_param('paymentMethodErrorReason')) ) {
                    $message .= $reason;
                }
                elseif( ($reason = \Nuvei_Class::get_param('gwErrorReason')) ) {
                    $message .= $reason;
                }
                
                $message .= '<br/>';
                
                $message .= 
                    $this->language->get("Error code: ") 
                    . (int) \Nuvei_Class::get_param('ErrCode') . '<br/>'
                    . $this->language->get("Message: ") 
                    . \Nuvei_Class::get_param('message') . '<br/>';
                
                if(in_array($transactionType, array('Sale', 'Auth'))) {
                    $status_id = $this->config->get(NUVEI_SETTINGS_PREFIX . 'failed_status_id');
                    break;
                }

                // Void, do not change status
                if($transactionType == 'Void') {
                    $status_id = $this->order_info['order_status_id'];
                    break;
                }
                
                // Refund
                if($transactionType == 'Credit') {
					//if($cl_unique_id) {
						$formated_refund = $this->currency->format(
                            $total_amount,
							$this->order_info['currency_code'],
							$this->order_info['currency_value']
						);
						
						$message .= $this->language->get('Refund Amount: ') . $formated_refund;
					//}
                    
                    $status_id = $this->order_info['order_status_id'];
                    $send_message = false;
                    break;
                }
                
                $status_id = $this->config->get(NUVEI_SETTINGS_PREFIX . 'failed_status_id');
                break;

            case 'PENDING':
                $message = $this->language->get('The Transaction is Pending.') . $comment_details;
                break;
                
            default:
                \Nuvei_Class::create_log($this->plugin_settings, $status, 'Unexisting status:');
        }
        
        \Nuvei_Class::create_log(
            $this->plugin_settings,
            array(
                'order_id'  => $order_id,
                'status_id' => $status_id,
            ),
            'addOrderHistory()'
        );
        
        $this->model_checkout_order->addHistory($order_id, $status_id, $message, $send_message);
        
        $this->new_order_status = $status_id;
    }
    
    private function update_order($products)
    {
        \Nuvei_Class::create_log($this->plugin_settings, 'update_order()');
        
        if (empty($this->session->data['nuvei_last_oo_details'])
			|| empty($this->session->data['nuvei_last_oo_details']['sessionToken'])
			|| empty($this->session->data['nuvei_last_oo_details']['orderId'])
			|| empty($this->session->data['nuvei_last_oo_details']['clientRequestId'])
		) {
			\Nuvei_Class::create_log(
                $this->plugin_settings,
                'update_order() - exit updateOrder logic, continue with new openOrder.'
            );
			
			return array('status' => 'ERROR');
		}
        
        $amount = $this->get_price($this->order_info['total']);
        
        // updateOrder params
		$params = array(
			'sessionToken'		=> $this->session->data['nuvei_last_oo_details']['sessionToken'],
			'orderId'			=> $this->session->data['nuvei_last_oo_details']['orderId'],
            'clientUniqueId'	=> $this->session->data['order_id'] . '_' . uniqid(),
            'clientRequestId'	=> $this->session->data['nuvei_last_oo_details']['clientRequestId'],
            'currency'          => $this->order_info['currency_code'],
            'amount'            => $amount,
            'transactionType'	=> (float) $amount == 0 ? 'Auth' : $this->plugin_settings[NUVEI_SETTINGS_PREFIX . 'payment_action'],
            
            'userDetails'       => $this->order_addresses['billingAddress'],
            'billingAddress'	=> $this->order_addresses['billingAddress'],
            'shippingAddress'   => $this->order_addresses['shippingAddress'],
            
            'items'				=> array(
				array(
					'name'		=> 'oc_order',
					'price'		=> $amount,
					'quantity'	=> 1
				)
			),
		);

        $rebilling_params   = $this->preprare_rebilling_params();
        $params             = array_merge_recursive($params, $rebilling_params);
        
        $oo_params['merchantDetails']['customField1'] = $amount;
        $oo_params['merchantDetails']['customField4'] = $this->order_info['currency_code'];
        
		$resp = \Nuvei_Class::call_rest_api(
            'updateOrder', 
            $this->plugin_settings, 
            array('merchantId', 'merchantSiteId', 'clientRequestId', 'amount', 'currency', 'timeStamp'), 
            $params
        );
        
        # Success
		if (!empty($resp['status']) && 'SUCCESS' == $resp['status']) {
            \Nuvei_Class::create_log(
                $this->plugin_settings,
                [
                    $products,
                    md5(serialize($products))
                ]
            );
            
            $this->session->data['nuvei_last_oo_details']['productsHash']   = md5(serialize($products));
            $this->session->data['nuvei_last_oo_details']['amount']         = $params['amount'];
            $this->session->data['nuvei_last_oo_details']['billingAddress']['country'] 
                = $params['billingAddress']['country'];
            
			return array_merge($resp, $params);
		}
		
		\Nuvei_Class::create_log($this->plugin_settings, 'Order update was not successful.');

		return array('status' => 'ERROR');
    }
    
    private function get_order_addresses()
    {
        $order_addr = array(
            'billingAddress'	=> array(
				"firstName"	=> $this->order_info['payment_firstname'],
				"lastName"	=> $this->order_info['payment_lastname'],
				"address"   => $this->order_info['payment_address_1'],
				"phone"     => $this->order_info['telephone'],
				"zip"       => $this->order_info['payment_postcode'],
				"city"      => $this->order_info['payment_city'],
				'country'	=> $this->order_info['payment_iso_code_2'],
				'email'		=> $this->order_info['email'],
			),
            
            'shippingAddress'    => [
				"firstName"	=> $this->order_info['shipping_firstname'],
				"lastName"	=> $this->order_info['shipping_lastname'],
				"address"   => $this->order_info['shipping_address_1'],
				"phone"     => $this->order_info['telephone'],
				"zip"       => $this->order_info['shipping_postcode'],
				"city"      => $this->order_info['shipping_city'],
				'country'	=> $this->order_info['shipping_iso_code_2'],
				'email'		=> $this->order_info['email'],
			],
        );
        
        $customer_id = !empty($this->session->data['customer_id']) ? $this->session->data['customer_id'] : 0;
        
        /**
         * @since 4.0.2.1 have to pass $customer_id in the bottom method
         */
        $address = $this->model_account_address->getAddresses($customer_id); // get address by user id
        
        if (empty($this->order_info['payment_firstname']) 
            && !empty($address) 
            && is_array($address)
        ) {
            $user_address = current($address);
            
            $order_addr['billingAddress']['firstName']  = $user_address['firstname'];
            $order_addr['billingAddress']['lastName']   = $user_address['lastname'];
            $order_addr['billingAddress']['address']    = $user_address['address_1'];
            $order_addr['billingAddress']['zip']        = $user_address['postcode'];
            $order_addr['billingAddress']['city']       = $user_address['city'];
            $order_addr['billingAddress']['country']    = $user_address['iso_code_2'];
        }
        
        // because of the Guest User, if billing address is still empty use shipping address
        if (empty($order_addr['billingAddress']['firstName'])
            && !empty($order_addr['shippingAddress']['firstName'])
        ) {
            $order_addr['billingAddress'] = $order_addr['userDetails'] = $order_addr['shippingAddress'];
        }
        
        return $order_addr;
    }

    /**
     * Function return_message
     * 
     * @param string    $msg
     * @param mixed     $data
     */
    private function return_message($msg, $data = '')
    {
        if(!is_string($msg)) {
            $msg = json_encode($msg);
        }
        
        if(!empty($data)) {
            \Nuvei_Class::create_log($this->plugin_settings, $data, $msg);
        }
        else {
            \Nuvei_Class::create_log($this->plugin_settings, $msg);
        }
        
        exit($msg);
    }
    
    private function preprare_rebilling_params()
    {
        $params                 = [];
        $nuvei_rebilling_data   = [];
        
        if(!isset($this->order_info)) {
            $this->order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
        }
        
        # check for a product with a Payment Plan
        $rebilling_data = $this->getSubscriptions();
        
        \Nuvei_Class::create_log($this->plugin_settings, $rebilling_data, 'Rebilling products data');
        
        if(count($rebilling_data) > 0) {
            foreach($rebilling_data as $data) {
                // check for nuvei into subscription name
                if (strpos(strtolower($data['subscription']['name']), NUVEI_PLUGIN_CODE) === false) {
                    continue;
                }
                
                // get subscription amount for all items
                $subscription_amount_base = $data['subscription']['price'] * $data['quantity'];
                
                // add taxes
                $rec_am_base_taxes = $this->tax->calculate(
                    $subscription_amount_base,
                    $data['tax_class_id'],
                    $this->config->get('config_tax')
                );
                
                // convert the amount with the taxes to the Store currency
                $subscription_amount = $this->get_price($rec_am_base_taxes);
                
                // format base amount with taxes by the Store currency
                $subscription_amount_formatted = $this->currency->format(
                    $rec_am_base_taxes,
                    $this->session->data['currency']
                );
                
                $nuvei_rebilling_data = [
                    'product_id'            => $data['product_id'],
                    'subscription_id'       => $data['subscription']['subscription_plan_id'],
                    'subscription_amount'   => $subscription_amount,
                    'subs_am_formatted'     => $subscription_amount_formatted,
                ];
            }
            
            $params['merchantDetails']['customField3'] = json_encode($nuvei_rebilling_data);
        }
        
        return $params;
    }
    
    private function load_settings()
    {
        if(empty($this->plugin_settings) || null === $this->plugin_settings) {
            $this->load->model('setting/setting');
            $this->plugin_settings  = $this->model_setting_setting->getSetting(trim(NUVEI_SETTINGS_PREFIX, '_'));
        }
    }
    
    /**
     * Get some price by the currency convert rate.
     */
    private function get_price($price)
    {
        $new_price = round((float) $price * $this->order_info['currency_value'], 2);
        return number_format($new_price, 2, '.', '');
    }
    
    private function get_order_info_by_dmn()
    {
        $order_id               = 0;
        $dmn_type               = \Nuvei_Class::get_param('dmnType');
        $trans_type             = \Nuvei_Class::get_param('transactionType');
        $relatedTransactionId   = (int) \Nuvei_Class::get_param('relatedTransactionId');
        $merchant_unique_id     = \Nuvei_Class::get_param('merchant_unique_id');
        $merchant_uid_arr       = explode('_', $merchant_unique_id);
        
        // default case
        if (is_array($merchant_uid_arr)
            && count($merchant_uid_arr) > 1
            && is_numeric($merchant_uid_arr[0])
        ) {
            $order_id = $merchant_uid_arr[0];
        }
        // CPanel made action
        elseif (!empty($relatedTransactionId)) {
            $query = $this->db->query(
                'SELECT order_id FROM ' . DB_PREFIX . 'order '
                . 'WHERE custom_field = ' . $relatedTransactionId
            );
            
            $order_id = (int) @$query->row['order_id'];
        }
        // Subscription case
        elseif (in_array($dmn_type, ['subscription', 'subscriptionPayment'])) {
            $client_req_id_arr = explode('_', \Nuvei_Class::get_param('clientRequestId'));
            
            if (is_array($client_req_id_arr)
                && count($client_req_id_arr) > 0
                && is_numeric($client_req_id_arr[0])
            ) {
                $order_id = $client_req_id_arr[0];
            }
        }
        
        $this->order_info = $this->model_checkout_order->getOrder($order_id);
        
        if (!is_array($this->order_info) || empty($this->order_info)) {
            // create Auto-Void
            $curr_time          = time();
            $order_request_time	= \Nuvei_Class::get_param('customField2'); // time of create/update order
            
            if (!is_numeric($order_request_time)) {
                $order_request_time = strtotime($order_request_time);
            }
            
            if ($curr_time - $order_request_time > 1800) {
                $this->create_auto_void();
            }
            // /create Auto-Void
            
            if (in_array($trans_type, ['Auth', 'Sale'])) {
                http_response_code(400);
                $this->return_message('There is no order info, Let\'s wait one more DMN try.');
            }
            
            http_response_code(200);
            $this->return_message('There is no order info.');
        }
        
        $isNuveiOrder = \Nuvei_Version_Resolver::check_for_nuvei_order($this->order_info);
        
        if (!$isNuveiOrder) {
            \Nuvei_Class::create_log(
                $this->plugin_settings,
                $this->order_info,
            );
            
            $this->return_message('The Order does not belongs to the Nuvei.');
        }
        
        // success
        return;
    }
    
    private function create_auto_void()
    {
        \Nuvei_Class::create_log($this->plugin_settings, 'Try Auto Void.');
        
        // not allowed Auto-Void
        if (!in_array(\Nuvei_Class::get_param('transactionType'), array('Auth', 'Sale'), true)) {
            \Nuvei_Class::create_log($this->plugin_settings, 'The transacion is not in allowed range.');
            return;
        }
        
        $notify_url     = $this->url->link(NUVEI_CONTROLLER_PATH . '%7Ccallback');
        $void_params    = [
            'clientUniqueId'        => date('YmdHis') . '-' . uniqid(),
            'amount'                => (float) \Nuvei_Class::get_param('totalAmount'),
            'currency'              => \Nuvei_Class::get_param('currency'),
            'relatedTransactionId'  => \Nuvei_Class::get_param('TransactionID', FILTER_SANITIZE_NUMBER_INT),
            'url'                   => $notify_url,
            'urlDetails'            => ['notificationUrl' => $notify_url],
            'customData'            => 'This is Auto-Void transaction',
        ];

        $resp = \Nuvei_Class::call_rest_api(
            'voidTransaction',
            $this->plugin_settings,
            ['merchantId', 'merchantSiteId', 'clientRequestId', 'clientUniqueId', 'amount', 'currency', 'relatedTransactionId', 'url', 'timeStamp'],
            $void_params
        );
        
        
        // Void Success
        if (!empty($resp['transactionStatus'])
            && 'APPROVED' == $resp['transactionStatus']
            && !empty($resp['transactionId'])
        ) {
            http_response_code(200);
            $this->return_message('The searched Order does not exists, a Void request was made for this Transacrion.');
        }
        
        return;
    }
    
    private function update_custom_fields($order_id)
    {
        $req_status             = $this->get_request_status();
        $trans_id               = (int) \Nuvei_Class::get_param('TransactionID');
        $relatedTransactionId   = (int) \Nuvei_Class::get_param('relatedTransactionId');
        $trans_type             = \Nuvei_Class::get_param('transactionType');
        $order_data             = $this->order_info['payment_custom_field'];
        
        if(empty($order_data)) {
            $order_data = array();
        }
        
        \Nuvei_Class::create_log($this->plugin_settings, $order_data, 'callback() payment_custom_field');
        
        // prevent dublicate data
        foreach($order_data as $trans) {
            if($trans['transactionId'] == $trans_id
                && $trans['transactionType'] == $trans_type
                && $trans['status'] == strtolower($req_status)
            ) {
                \Nuvei_Class::create_log(
                    $this->plugin_settings, 
                    'Dublicate DMN. We already have this information. Stop here.'
                );

                $this->return_message('Dublicate DMN. We already have this information. Stop here.');
            }
        }
        
        $order_data[] = array(
            'status'                => strtolower((string) $req_status),
            'clientUniqueId'        => \Nuvei_Class::get_param('clientUniqueId'),
            'transactionType'       => $trans_type,
            'transactionId'         => $trans_id,
            'relatedTransactionId'  => $relatedTransactionId,
            'userPaymentOptionId'   => (int) \Nuvei_Class::get_param('userPaymentOptionId'),
            'authCode'              => (int) \Nuvei_Class::get_param('AuthCode'),
            'totalAmount'           => round((float) \Nuvei_Class::get_param('totalAmount'), 2),
            'currency'              => \Nuvei_Class::get_param('currency'),
            'paymentMethod'         => \Nuvei_Class::get_param('payment_method'),
            'responseTimeStamp'     => \Nuvei_Class::get_param('responseTimeStamp'),
            'originalTotal'         => \Nuvei_Class::get_param('customField1'),
            'originalCurrency'      => \Nuvei_Class::get_param('customField4'),
            'totalCurrAlert'        => $this->total_curr_alert,
        );
        
        // all data
        $this->db->query(
            "UPDATE `" . DB_PREFIX . "order` "
            . "SET payment_custom_field = '" . json_encode($order_data) . "' "
            . "WHERE order_id = " . $order_id
        );
        
        // add only transaction ID if the transactions is Auth, Settle or Sale
        if(in_array($trans_type, array('Auth', 'Settle', 'Sale'))) {
            $this->db->query(
                "UPDATE `" . DB_PREFIX . "order` "
                . "SET custom_field = '" . $trans_id . "' "
                . "WHERE order_id = " . $order_id
            );
        }
    }
    
    /**
	 * The start of create subscriptions logic.
	 * We call this method when we've got Settle or Sale DMNs.
	 * 
	 * @param string    $transactionType
	 * @param int       $order_id
	 */
    private function subscription_start($transactionType, $order_id)
    {
        \Nuvei_Class::create_log(
            $this->plugin_settings,
            [
                'status'        => $this->new_order_status,
                //'order info'    => $this->order_info,
            ],
            'subscription_start()'
        );
        
        if (empty($this->plugin_settings[NUVEI_SETTINGS_PREFIX . 'plan_id'])) {
            \Nuvei_Class::create_log(
                $this->plugin_settings,
                'Missing Nuvei Subscription Plan ID into the plugin settings!'
            );
			return;
        }
        
        $subscr_data    = json_decode(\Nuvei_Class::get_param('customField3'), true);
        $upo_id         = \Nuvei_Class::get_param('userPaymentOptionId');
        
		if (!in_array($transactionType, array('Settle', 'Sale', 'Auth'))
            || 'APPROVED' != $this->get_request_status()
            || !is_array($subscr_data)
            || empty($subscr_data['product_id'])
            || empty($subscr_data['subscription_id'])
            || empty($subscr_data['subscription_amount'])
            || empty($subscr_data['subs_am_formatted'])
            || empty($upo_id)
        ) {
            \Nuvei_Class::create_log(
                $this->plugin_settings,
                [
                    '$transactionType'      => $transactionType,
                    'get_request_status'    => $this->get_request_status(),
                    'subscr_data'           => $subscr_data,
                    'userPaymentOptionId'   => $upo_id,
                ],
                'subscription_start() first check fail.'
            );
			return;
		}
        
        // allow subs_am_formatted only for Zero Auth Orders
        if('Auth' == $transactionType && 0 !== (int) \Nuvei_Class::get_param('totalAmount')) {
            \Nuvei_Class::create_log(
                $this->plugin_settings,
                'The Auth Order total is not Zero. Do not start Rebilling'
            );
            return;
        }
        
        // get data
        $query = 
            'SELECT * FROM ' . DB_PREFIX . 'subscription '
            . 'WHERE subscription_plan_id = ' . (int) $subscr_data['subscription_id'] . ' '
                . 'AND order_id = ' . (int) $order_id;
        
        $prod_plan = $this->db->query($query);
		
		if (!is_object($prod_plan) || empty($prod_plan)) {
            \Nuvei_Class::create_log(
                $this->plugin_settings,
                [
                    '$query'        => $query,
                    '$prod_plan'    => $prod_plan,
                ],
                'Error - $prod_plan problem.'
            );
			return;
		}
        
//        \Nuvei_Class::create_log(
//            $this->plugin_settings,
//            [$query, $prod_plan],
//            '$prod_plan.',
//        );
        
        // check for more than one products of same type
        $query = 
            'SELECT product_id, name, quantity, total '
            . 'FROM ' . DB_PREFIX . 'order_product '
            . 'WHERE order_id = ' . (int) $order_id;
        
        $order_products = $this->db->query($query);
        
        if (!is_object($order_products) || empty($order_products->row['quantity'])) {
            \Nuvei_Class::create_log(
                $this->plugin_settings, 
                [
                    '$query'            => $query,
                    '$order_products'   => $order_products,
                ], 
                'Error - $order_products problem.',
                'WARN'
            );
			return;
		}
        
        // about recurring duration
        $recurringPeriod_frequency  = $prod_plan->row['frequency'];
        $recurringPeriod_cycle      = $prod_plan->row['cycle'];
        $endAfter_frequency         = $prod_plan->row['frequency'];
        $endAfter_duration          = $prod_plan->row['duration'];
        
        if ('semi_month' == $recurringPeriod_frequency) {
            $recurringPeriod_frequency  = $endAfter_frequency = 'week';
            $recurringPeriod_cycle      = 2 * $recurringPeriod_cycle;
            $endAfter_duration          = 2 * $endAfter_duration;
        }
        
        
        // /about recurring duration
        
        // about trial duration
        $trial_frequency = $prod_plan->row['trial_frequency'];
        $trial_duration = $prod_plan->row['trial_duration'];
        
        if ('semi_month' == $trial_frequency) {
            $trial_frequency    = 'week';
            $trial_duration     = 2 * $trial_duration;
        }

        // check if Trial is activ
        if(0 == $prod_plan->row['trial_status']) {
            $trial_duration = 0;
        }
        // /about trial duration
        
        $params = array(
            'clientRequestId'       => $order_id . '_' . uniqid(),
            'userPaymentOptionId'   => (int) \Nuvei_Class::get_param('userPaymentOptionId'),
            'userTokenId'           => \Nuvei_Class::get_param('user_token_id'),
            'currency'              => \Nuvei_Class::get_param('currency'),
            'initialAmount'         => 0,
            'planId'                => $this->plugin_settings[NUVEI_SETTINGS_PREFIX . 'plan_id'] ?? 0,
            'recurringAmount'       => $subscr_data['subscription_amount'],
            'recurringPeriod'       => [
                $recurringPeriod_frequency => $recurringPeriod_cycle,
            ],
            'startAfter'            => [
                $trial_frequency => $trial_duration
            ],
            'endAfter'              => [
                $endAfter_frequency => $endAfter_duration,
            ],
        );

        $resp = \Nuvei_Class::call_rest_api(
            'createSubscription',
            $this->plugin_settings,
            array('merchantId', 'merchantSiteId', 'userTokenId', 'planId', 'userPaymentOptionId', 'initialAmount', 'recurringAmount', 'currency', 'timeStamp'),
            $params
        );

        // On Error
        if (!$resp || !is_array($resp) || empty($resp['status']) || 'SUCCESS' != $resp['status']) {
            $msg = $this->language->get('Error when try to start a Subscription by the Order.');

            if (!empty($resp['reason'])) {
                $msg .= '<br/>' . $this->language->get('Reason: ') . $resp['reason'];
            }

            \Nuvei_Class::create_log($this->plugin_settings, $msg, 'Subscription Error');

            $this->model_checkout_order->addHistory(
                $this->order_info['order_id'],
                $this->new_order_status,
                $msg,
                true // $send_message
            );
        }

        // On Success
        $msg = $this->language->get('Subscription was created. ') . '<br/>'
            . $this->language->get('Subscription ID: ') . $resp['subscriptionId'] . '.<br/>' 
            . $this->language->get('Recurring amount: ') . $subscr_data['subs_am_formatted'];

        $this->model_checkout_order->addHistory(
            $this->order_info['order_id'],
            $this->new_order_status,
            $msg,
            true // $send_message
        );
			
		return;
    }
    
    /**
     * @param string $transactionType
     * @return void
     */
    private function subscription_cancel($transactionType)
    {
        \Nuvei_Class::create_log(
            $this->plugin_settings,
//            ['order info'    => $this->order_info,],
            'subscription_cancel()'
        );
        
        if ('Void' != $transactionType || 'APPROVED' != $this->get_request_status()) {
            \Nuvei_Class::create_log(
                $this->plugin_settings,
                'We Cancel Subscription only when the Void request is APPROVED.'
            );
			return;
		}
        
        // check for active subscription
        $query =
            "SELECT subscription_id "
            . "FROM ". DB_PREFIX ."subscription "
            . "WHERE order_id = " . (int) $this->order_info['order_id'] . " "
            . "AND subscription_status_id IN (1, 2)"; // pending or active

        $res = $this->db->query($query);
        
        if(!isset($res->num_rows) || $res->num_rows == 0) {
            \Nuvei_Class::create_log(
                $this->plugin_settings, 
                'There is no active Subscription for this Order.'
            );
            return;
        }
        // /check for active subscription
        
        $order_data = $this->order_info['payment_custom_field'];
        
        foreach (array_reverse($order_data) as $transaction) {
            if (!empty($transaction['subscrIDs'])) {
                $resp = \Nuvei_Class::call_rest_api(
                    'cancelSubscription',
                    $this->plugin_settings,
                    array('merchantId', 'merchantSiteId', 'subscriptionId', 'timeStamp'),
                    ['subscriptionId' => $transaction['subscrIDs']]
                );

                // On Error
                if (!$resp || !is_array($resp) || 'SUCCESS' != $resp['status']) {
                    $msg = $this->language->get('Error when try to cancel Subscription #')
                        . $transaction['subscrIDs'] . ' ';

                    if (!empty($resp['reason'])) {
                        $msg .= '<br/>' . $this->language->get('Reason: ', 'nuvei_woocommerce') 
                            . $resp['reason'];
                    }

                    $this->model_checkout_order->addHistory(
                        $this->order_info['order_id'],
                        $this->new_order_status,
                        $msg,
                        true // $send_message
                    );
                }
                
                break;
            }
        }
        
		return;
    }
    
    private function process_subs_state()
    {
        \Nuvei_Class::create_log($this->plugin_settings, 'process_subs_state order_info');
        
        $dmnType            = \Nuvei_Class::get_param('dmnType');
        $subscriptionState  = \Nuvei_Class::get_param('subscriptionState');
        $subscriptionId     = \Nuvei_Class::get_param('subscriptionId', FILTER_SANITIZE_NUMBER_INT);
        
        if ('subscription' != $dmnType) {
            return;
        }

        if (empty($subscriptionState)) {
            $this->return_message('Subscription DMN missing subscriptionState. Stop the process.');
        }

        $this->get_order_info_by_dmn();
        
        if(!$this->order_info || empty($this->order_info)) {
            $this->return_message('DMN error - there is no order info.');
        }

        $order_data     = $this->order_info['payment_custom_field'];
        $subs_status    = 1; // pending

        if ('active' == strtolower($subscriptionState)) {
            $message = $this->language->get('Subscription is Active.') . '<br/>'
                . $this->language->get('Subscription ID: ') . $subscriptionId . '<br/>'
                . $this->language->get('Plan ID: ') . (int) \Nuvei_Class::get_param('planId');

            $subs_status = 2; // active
        }
        elseif ('inactive' == strtolower($subscriptionState)) {
            $message = $this->language->get('Subscription is Inactive.') . '<br/>'
                . $this->language->get('Subscription ID:') . ' ' . $subscriptionId . '<br/>'
                . $this->language->get('Plan ID:') . ' ' . (int) \Nuvei_Class::get_param('planId');

            $subs_status = 6; // Expired
        }
        elseif ('canceled' == strtolower($subscriptionState)) {
            $message = $this->language->get('Subscription was canceled.') . '<br/>'
                . $this->language->get('Subscription ID:') . ' ' . $subscriptionId . '<br/>';

            $subs_status = 4; // Cancelled
        }

        // save the Subscription ID
        // just add the ID without the details, we need only the ID to cancel the Subscription
        foreach($order_data as $key => $tansaction) {
            if(in_array($tansaction['transactionType'], ['Sale', 'Settle'])) {
                $order_data[$key]['subscrIDs'] = (int) \Nuvei_Class::get_param('subscriptionId');
                break;
            }
            elseif ('Auth' == $tansaction['transactionType'] && 0 == $tansaction['totalAmount']) {
                $order_data[$key]['subscrIDs'] = (int) \Nuvei_Class::get_param('subscriptionId');
                break;
            }
            
        }

        // update Order payment_custom_field
        $this->db->query(
            "UPDATE `" . DB_PREFIX . "order` "
            . "SET payment_custom_field = '" . json_encode($order_data) . "' "
            . "WHERE order_id = " . $this->order_info['order_id']
        );

//        \Nuvei_Class::create_log(
//            $this->plugin_settings, 
//            $this->order_info['order_status_id'],
//            'Order status before update Subscription'
//        );

        // update Subscription status
        $query = 
            "UPDATE `" . DB_PREFIX . "subscription` "
            . "SET subscription_status_id = " . $subs_status . " "
            . "WHERE order_id = " . (int) $this->order_info['order_id'];
        
        $res = $this->db->query($query);
        
//        \Nuvei_Class::create_log(
//            $this->plugin_settings, 
//            [$query, $res],
//            'Order update Subscription $query'
//        );

        $this->model_checkout_order->addHistory(
            $this->order_info['order_id'],
            $this->order_info['order_status_id'],
            $message,
            true // $send_message
        );

        $this->return_message('DMN Proccess Subscription State received.');
    }
    
    /**
     * Order recurring transactions types:
     * 0 - Date Added
     * 1 - Payment
     * 2 - Outstanding Payment
     * 3 - Transaction Skipped
     * 4 - Transaction Failed
     * 5 - Transaction Cancelled
     * 6 - Transaction Suspended
     * 7 - Transaction Suspended Failed
     * 8 - Transaction Outstanding Failed
     * 9 - Transaction Expired
     * 
     * 
     * @return void
     */
    private function process_subs_payment()
    {
        \Nuvei_Class::create_log($this->plugin_settings, 'process_subs_payment()');
        
        $dmnType        = \Nuvei_Class::get_param('dmnType');
        $trans_id       = (int) \Nuvei_Class::get_param('TransactionID');
        $planId         = (int) \Nuvei_Class::get_param('planId');
        $subscriptionId = (int) \Nuvei_Class::get_param('subscriptionId');
        $totalAmount    = (float) \Nuvei_Class::get_param('totalAmount');
        $req_status     = $this->get_request_status();
        
        if ('subscriptionPayment' != $dmnType || 0 == $trans_id) {
            return;
        }
        
        $this->get_order_info_by_dmn();
        
        // in order_recurring_transaction table we save the total in default value
        // but we get it in Order currency
        $rec_amount_default     = $totalAmount / $this->order_info['currency_value'];
        $rec_amount_formatted   = $this->currency->format(
            $rec_amount_default,
            \Nuvei_Class::get_param('currency')
        );
        
        $message = $this->language->get('Subscription Payment was made.') . '<br/>'
            . $this->language->get('Status: ') . $req_status . '<br/>'
            . $this->language->get('Plan ID: ') . $planId . '<br/>'
            . $this->language->get('Subscription ID: ') . $subscriptionId . '<br/>'
            . $this->language->get('Amount: ') . $rec_amount_formatted . '<br/>'
            . $this->language->get('TransactionId: ') . $trans_id;

        \Nuvei_Class::create_log(
            $this->plugin_settings, 
            $this->order_info['order_status_id'], 
            'order status when get subscriptionPayment'
        );

        $this->model_checkout_order->addHistory(
            $this->order_info['order_id'],
            $this->order_info['order_status_id'],
            $message,
            true // $send_message
        );

        $order_rec = $this->db->query(
            "SELECT subscription_id, remaining, frequency, cycle "
            . "FROM " . DB_PREFIX . "subscription "
            . "WHERE order_id = ". (int) $this->order_info['order_id']
        );
        
//        \Nuvei_Class::create_log(
//            $this->plugin_settings, 
//            $order_rec, 
//            'query result'
//        );

        switch(strtolower($req_status)) {
            case 'approved':
                $trans_type = 1;
                break;
            
            case 'declined':
                $trans_type = 5;
                break;
            
            default:
                $trans_type = 4;
                break;
        }
        
        // save the recurring transaction
        $query =
            "INSERT INTO `" . DB_PREFIX . "subscription_transaction` "
                . "(`subscription_id`, `order_id`, `transaction_id`, `amount`, `type`, `payment_method`, `payment_code`, `date_added`) "
            
            . "VALUES (". $order_rec->row['subscription_id'] .", ". $this->order_info['order_id'] .", " 
                . $trans_id . ", " . $rec_amount_default . ", " . $trans_type . ", '" . NUVEI_PLUGIN_TITLE . "', '" 
                . NUVEI_PLUGIN_CODE . "', " . "NOW())";
        
        $this->db->query($query);
        
        // decrease remaining payments count and change next payment date
        $date_next = '';
        
        if ('day' == $order_rec->row['frequency']) {
            // day - days
            if ($order_rec->row['cycle'] > 1) {
                $date_next = date('Y-m-d H:i:s', strtotime('+ ' . $order_rec->row['cycle'] . ' days'));
            } else {
                $date_next = date('Y-m-d H:i:s', strtotime('+ 1 day'));
            }
        }
        if ('week' == $order_rec->row['frequency']) {
            // 7 * week nums
            if ($order_rec->row['cycle'] > 1) {
                $date_next = date('Y-m-d H:i:s', strtotime('+ ' . ($order_rec->row['cycle'] * 7) . ' days'));
            } else {
                $date_next = date('Y-m-d H:i:s', strtotime('+ 7 days'));
            }
        }
        if ('semi_month' == $order_rec->row['frequency']) {
            // 7 * week nums
            if ($order_rec->row['cycle'] > 1) {
                $date_next = date('Y-m-d H:i:s', strtotime('+ ' . ($order_rec->row['cycle'] * 14) . ' days'));
            } else {
                $date_next = date('Y-m-d H:i:s', strtotime('+ 14 days'));
            }
        }
        if ('month' == $order_rec->row['frequency']) {
            // month - months
            if ($order_rec->row['cycle'] > 1) {
                $date_next = date('Y-m-d H:i:s', strtotime('+ ' . $order_rec->row['cycle'] . ' months'));
            } else {
                $date_next = date('Y-m-d H:i:s', strtotime('+ 1 month'));
            }
        }
        if ('year' == $order_rec->row['frequency']) {
            // year - years
            if ($order_rec->row['cycle'] > 1) {
                $date_next = date('Y-m-d H:i:s', strtotime('+ ' . $order_rec->row['cycle'] . ' years'));
            }
            else {
                $date_next = date('Y-m-d H:i:s', strtotime('+ 1 year'));
            }
        }
        
        $remaining_days = (int) $order_rec->row['remaining'] - 1;
        
        $query =
            "UPDATE ". DB_PREFIX ."subscription "
            . "SET remaining = ". $remaining_days;
        if (!empty($date_next) && $remaining_days > 0) {
            $query .= ", date_next = '" . $date_next . "'";
        }
        $query .= " WHERE order_id = ". (int) $this->order_info['order_id'];
        
        $this->db->query($query);

        $this->return_message('DMN Proccess Subscription Payment received.');
    }
    
    /**
     * Get the SDK URL depending from the server name.
     * 
     * @return string
     */
    private function getSdkUrl()
    {
        if (!empty($_SERVER['SERVER_NAME']) 
            && defined('NUVEI_SDK_URL_TAG')
            && defined('NUVEI_QA_HOSTS')
            && in_array($_SERVER['SERVER_NAME'], NUVEI_QA_HOSTS)
        ) {
            return NUVEI_SDK_URL_TAG;
        }
        
        return NUVEI_SDK_URL_PROD;
    }
    
    /**
     * A helper class to find correct name of the method in the Cart object.
     * Looks like OC developers like to change method names in new releases...
     * 
     * @return array
     */
    private function getSubscriptions()
    {
        // for OC4 before v4.0.2.3, at least we detect it in this version
        if (method_exists($this->cart, 'getSubscription')) {
            return $this->cart->getSubscription();
        }
        
        // the new nema in at least OC4 v4.0.2.3
        if (method_exists($this->cart, 'getSubscriptions')) {
            return $this->cart->getSubscriptions();
        }
        
        \Nuvei_Class::create_log(
            '', 
            'The plugin can not find the method who get the subscriptions in the Cart object', 
            'WARN'
        );
        
        return [];
    }
}

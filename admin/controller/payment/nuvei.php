<?php

namespace Opencart\Admin\Controller\Extension\Nuvei\Payment;

require_once DIR_EXTENSION . DIRECTORY_SEPARATOR . 'nuvei' . DIRECTORY_SEPARATOR . 'nuvei_class.php';

class Nuvei extends \Opencart\System\Engine\Controller
{
    private $required_settings  = [
        'test_mode',
        'merchantId',
        'merchantSiteId',
        'secret',
        'hash',
        'payment_action',
        'create_logs',
    ];
    
    private $data               = []; // the data for the admin template
    private $plugin_settings    = [];
	private $notify_url         = '';
    
    public function index(): void
    {
        $this->load->language(NUVEI_CONTROLLER_PATH);
		$this->load->model('setting/setting');
        $this->load->model('localisation/order_status');
        $this->document->setTitle($this->language->get('heading_title'));
        
        // added path menu
        $data['breadcrumbs'] = [];
        
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link(
                'common/dashboard',
                NUVEI_TOKEN_NAME . '=' . $this->session->data[NUVEI_TOKEN_NAME],
                true
            ),
            'separator' => false
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link(
                NUVEI_ADMIN_EXT_URL,
                NUVEI_TOKEN_NAME . '=' . $this->session->data[NUVEI_TOKEN_NAME] . '&type=payment',
                true
            ),
            'separator' => ' :: '
		);
        
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
			'href' => $this->url->link(
                $this->request->get['route'],
                NUVEI_TOKEN_NAME . '=' . $this->session->data[NUVEI_TOKEN_NAME],
                true
            ),
            'separator' => ' :: '
   		);
        // /added path menu
        
        // form save url
        $data['save'] = $this->url->link(
            NUVEI_CONTROLLER_PATH . '|save',
            'user_token=' . $this->session->data['user_token']
        );
		
        // cancel (back) link
        $data['back'] = $this->url->link(
            NUVEI_ADMIN_EXT_URL,
            NUVEI_TOKEN_NAME . '=' . $this->session->data[NUVEI_TOKEN_NAME] . '&type=payment',
            true
        );
        
        $data['nuvei_settings_prefix'] = NUVEI_SETTINGS_PREFIX;
        
        // get plugin settings and pass them to template
        $this->plugin_settings = $this->model_setting_setting->getSetting(trim(NUVEI_SETTINGS_PREFIX, '_'));
        $data = array_merge($data, $this->plugin_settings );
        
        /*
         * get Order Statuses
         * 
         * 1 - Pending
         * 2 - Processing
         * 3 - Shipped
         * 5 - Complete
         * 7 - Canceled
         * 8 - Denied
         * 9 - Canceled Reversal
         * 10 - Failed
         * 11 - Refunded
         * 12 - Reversed
         * 13 - Chargeback
         * 14 - Expired
         * 15 - Processed
         * 16 - Voided
         */
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
        
        # set default statuses if merchent did't set them
        // default Order Status
        if(empty($data[NUVEI_SETTINGS_PREFIX . 'order_status_id'])) {
            $data[NUVEI_SETTINGS_PREFIX . 'order_status_id'] = 5;
        }
        // Pending Order Status
        if(empty($data[NUVEI_SETTINGS_PREFIX . 'pending_status_id'])) {
            $data[NUVEI_SETTINGS_PREFIX . 'pending_status_id'] = 1;
        }
        // Canceled Order Status
        if(empty($data[NUVEI_SETTINGS_PREFIX . 'canceled_status_id'])) {
            $data[NUVEI_SETTINGS_PREFIX . 'canceled_status_id'] = 7;
        }
        // Failed Order Status
        if(empty($data[NUVEI_SETTINGS_PREFIX . 'failed_status_id'])) {
            $data[NUVEI_SETTINGS_PREFIX . 'failed_status_id'] = 10;
        }
        // Refunded Order Status
        if(empty($data[NUVEI_SETTINGS_PREFIX . 'refunded_status_id'])) {
            $data[NUVEI_SETTINGS_PREFIX . 'refunded_status_id'] = 11;
        }
        # /set default statuses if merchent did't set them
        
        // if we have added general settings allready get merchan payment methods
        $data['nuvei_pms'] = $this->get_payment_methods();
        
        // DMN URL
        $data['nuvei_dmn_url'] = str_replace('admin/', '', $this->url->link(NUVEI_CONTROLLER_PATH . '%7Ccallback'));
//
        // set statuses manually
//        $statuses = array(
//            1   => 'pending_status_id',
//            5   => 'order_status_id',
//            7   => 'canceled_status_id',
//            10  => 'failed_status_id',
//            11  => 'refunded_status_id',
////            13  => 'chargeback_status_id',
//        );
//        
//        foreach($statuses as $id => $name) {
//            if (isset($this->request->post[NUVEI_SETTINGS_PREFIX . $name])) {
//                $data[NUVEI_SETTINGS_PREFIX . $name] = $this->request->post[NUVEI_SETTINGS_PREFIX . $name];
//            }
//            elseif (isset($this->plugin_settings [NUVEI_SETTINGS_PREFIX . $name])) {
//                $data[NUVEI_SETTINGS_PREFIX . $name] = $this->config->get(NUVEI_SETTINGS_PREFIX . $name); 
//            }
//            else {
//                $data[NUVEI_SETTINGS_PREFIX . $name] = $id;
//            }
//        }
//        // /set statuses manually
//        
        // get all statuses
		$this->load->model('localisation/order_status');
		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
        
        // get all geo-zones
		$this->load->model('localisation/geo_zone');
		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

//        if (isset($this->session->data['success'])) {
//            $data['success'] = $this->session->data['success'];
//            unset($this->session->data['success']);
//        }
//        elseif (isset($this->session->data['error_warning'])) {
//            $data['error_warning'] = $this->session->data['error_warning'];
//            unset($this->session->data['error_warning']);
//        }
//        
        // set template parts
        $data['header']         = $this->load->controller('common/header');
        $data['column_left']    = $this->load->controller('common/column_left');
        $data['footer']         = $this->load->controller('common/footer');
        
        // if we want twig file only
        $this->response->setOutput($this->load->view(NUVEI_CONTROLLER_PATH, $data));
	}
    
    public function install()
    {
        // add events
        $this->load->model('setting/event');
        
        // add checkout.js to the checkout header
        $this->model_setting_event->addEvent([
            'code'          => 'nuvei_load_sdk',
            'description'   => 'Load Nuvei Checkout SDK into the catalog.',
            'trigger'       => 'catalog/controller/checkout/checkout/before', 
            'action'        => 'extension/nuvei/payment/nuvei|event_add_sdk_lib',
            'status'        => 1,
            'sort_order'    => 1,
        ]);
        
        // add nuvei_orders.js to the admin
        $this->model_setting_event->addEvent([
            'code'          => 'nuvei_load_orders_js',
            'description'   => 'Load Nuvei additional JS to the Order Info.',
            'trigger'       => 'admin/controller/sale/order|info/before', 
            'action'        => 'extension/nuvei/payment/nuvei|event_add_order_script',
            'status'        => 1,
            'sort_order'    => 1,
        ]);
        
        // add nuvei_version_checker.js to the admin
        $this->model_setting_event->addEvent([
            'code'          => 'nuvei_load_version_checker',
            'description'   => 'Load Nuvei Plugin version checker JS.',
            'trigger'       => 'admin/controller/common/header/before', 
            'action'        => 'extension/nuvei/payment/nuvei|event_version_checker',
            'status'        => 1,
            'sort_order'    => 1,
        ]);
        
        // add check for product with subscription before add it to the cart
        $this->model_setting_event->addEvent([
            'code'          => 'nuvei_before_add_product',
            'description'   => 'Do not combine a product with a Nuvei Payment Plan with ordinary product.',
            'trigger'       => 'catalog/controller/checkout/cart|add/before', 
            'action'        => 'extension/nuvei/payment/nuvei|event_before_add_product',
            'status'        => 1,
            'sort_order'    => 1,
        ]);
        
        // add a JS scrtipt to show the error, the aboive event eventualy return
        $this->model_setting_event->addEvent([
            'code'          => 'nuvei_product_mod',
            'description'   => 'If there is Nuvei error when try to add a product to the cart - show an error.',
            'trigger'       => 'catalog/controller/common/footer/before', 
            'action'        => 'extension/nuvei/payment/nuvei|event_add_product_mod',
            'status'        => 1,
            'sort_order'    => 1,
        ]);
        
        // add missing date_next parameter for Product Subscription data on the Checkout page
        $this->model_setting_event->addEvent([
            'code'          => 'nuvei_product_subscr_data_mod',
            'description'   => 'On Checkout page into the Product Subscription data add date_next parameter if missing.',
            'trigger'       => 'catalog/model/checkout/order/addOrder/before',
            'action'        => 'extension/nuvei/payment/nuvei|event_check_subsc_data',
            'status'        => 1,
            'sort_order'    => 1,
        ]);
        
        // filter payment providers on the checkout page
        $this->model_setting_event->addEvent([
            'code'          => 'nuvei_filter_payment_providers',
            'description'   => 'On Checkout page remove all payment providers if there is a product with Nuvei Payment Plan.',
            'trigger'       => 'catalog/model/checkout/payment_method/getMethods/after',
            'action'        => 'extension/nuvei/payment/nuvei|event_filter_payment_providers',
            'status'        => 1,
            'sort_order'    => 1,
        ]);
    }
    
    public function uninstall()
    {
        // remove plugin events
        $this->load->model('setting/event');
        
        $this->model_setting_event->deleteEventByCode('nuvei_load_sdk');
        $this->model_setting_event->deleteEventByCode('nuvei_load_orders_js');
        $this->model_setting_event->deleteEventByCode('nuvei_load_version_checker');
        $this->model_setting_event->deleteEventByCode('nuvei_before_add_product');
        $this->model_setting_event->deleteEventByCode('nuvei_product_mod');
        $this->model_setting_event->deleteEventByCode('nuvei_product_subscr_data_mod');
    }
    
    public function event_add_order_script()
    {
        if($this->user->isLogged()) {
            $this->document->addScript('/extension/nuvei/admin/view/javascript/nuvei_orders.js');
        }
    }
    
    public function event_version_checker()
    {
        if($this->user->isLogged() 
            && isset($this->request->get['user_token'])
            && isset($this->session->data['user_token']) 
            && ($this->request->get['user_token'] == $this->session->data['user_token'])
        ) {
            $this->document->addScript('/extension/nuvei/admin/view/javascript/nuvei_version_checker.js');
        }
    }
    
    /**
     * Save settings from the admin Ajax call
     * 
     * @return void
     */
    public function save(): void 
    {
		$this->load->language(NUVEI_CONTROLLER_PATH);

		$json = [];

		if (!$this->user->hasPermission('modify', NUVEI_CONTROLLER_PATH)) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			$this->load->model('setting/setting');
			$this->model_setting_setting->editSetting(trim(NUVEI_SETTINGS_PREFIX, '_'), $this->request->post);

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
    
    public function get_nuvei_vars()
    {
        $this->load->model('sale/order');
        $this->load->model('setting/setting');
        $this->load->language(NUVEI_CONTROLLER_PATH);
        
        $this->plugin_settings  = $this->model_setting_setting->getSetting(trim(NUVEI_SETTINGS_PREFIX, '_'));
        $order_id               = (int) $this->request->post['orderId'];
        $this->data             = $this->model_sale_order->getOrder($order_id);
        $nuvei_last_trans       = [];
        $nuvei_refunds          = [];
        $remainingTotalCurr     = '';
        $nuvei_remaining_total  = $this->get_price($this->data['total']);
        $nuveiAllowRefundBtn    = 0;
        $nuveiAllowVoidBtn      = 0;
        $nuveiAllowSettleBtn    = 0;
        $allowCancelSubsBtn     = 0;
        $last_voidalbe_tr_time  = 0;
        $isNuveiOrder           = NUVEI_PLUGIN_CODE == $this->data['payment_code'] ? 1 : 0;
        
//        \Nuvei_Class::create_log($this->plugin_settings, $nuvei_remaining_total, 'Admin Order Total');
        
        if(1 == $isNuveiOrder
            && !empty($this->data['payment_custom_field']) 
            && is_array($this->data['payment_custom_field'])
        ) {
            $nuvei_last_trans       = end($this->data['payment_custom_field']);
            $data['paymentMethod']  = $nuvei_last_trans['paymentMethod'];
            
            foreach($this->data['payment_custom_field'] as $trans_data) {
                if(in_array($trans_data['transactionType'], array('Refund', 'Credit'))
                    && 'approved' == $trans_data['status']
                ) {
                    $nuvei_remaining_total		-= $trans_data['totalAmount'];
                    
                    \Nuvei_Class::create_log($this->plugin_settings, $trans_data['totalAmount']);
                    \Nuvei_Class::create_log($this->plugin_settings, $nuvei_remaining_total);
                    
                    $ref_data					= $trans_data;
                    $ref_data['amount_curr']	= '-' . $this->currency->format(
                        $trans_data['totalAmount'],
                        $this->data['currency_code'],
                        1 // 1 in case the amout is converted, else - $this->data['currency_value']
                    );

                    $nuvei_refunds[] = $ref_data;
                }
                
                // get last voidable transaction time
                $resp_time = strtotime($trans_data['responseTimeStamp']);
                
                if(in_array($trans_data['transactionType'], array('Auth', 'Settle', 'Sale'))) {
                    if ($resp_time > strtotime($last_voidalbe_tr_time)) {
                        $last_voidalbe_tr_time = $trans_data['responseTimeStamp'];
                    }
                }
            }
            
            // can we show Refund button
            if(in_array($nuvei_last_trans['transactionType'], array('Refund', 'Credit', 'Sale', 'Settle'))
                && in_array($nuvei_last_trans['paymentMethod'], array("cc_card", "apmgw_expresscheckout"))
                && 'approved' == $nuvei_last_trans['status']
                && in_array($this->data['order_status_id'], [
                    $this->plugin_settings[NUVEI_SETTINGS_PREFIX . 'refunded_status_id'],
                    $this->plugin_settings[NUVEI_SETTINGS_PREFIX . 'order_status_id']
                ])
                && round($nuvei_remaining_total, 2) > 0
            ) {
                $nuveiAllowRefundBtn = 1;
            }

            // can we show Void button
            if("cc_card" == $nuvei_last_trans['paymentMethod']
                && in_array($this->data['order_status_id'], [
                    $this->plugin_settings[NUVEI_SETTINGS_PREFIX . 'order_status_id'],
                    $this->plugin_settings[NUVEI_SETTINGS_PREFIX . 'pending_status_id'], // auth
                ])
                && time() < strtotime($last_voidalbe_tr_time . " +48 hours") // after 48 hours hide void button
            ) {
                if (in_array($nuvei_last_trans['transactionType'], array('Settle', 'Sale'))) {
                    $nuveiAllowVoidBtn = 1;
                }
                elseif ('Auth' == $nuvei_last_trans['transactionType']
                    && $nuvei_last_trans['totalAmount'] > 0
                ) {
                    $nuveiAllowVoidBtn = 1;
                }
                else {
                    $nuveiAllowVoidBtn = 0;
                }
            }
            
            // can we show Settle button
            if('Auth' == $nuvei_last_trans['transactionType']
                && 'approved' == $nuvei_last_trans['status']
                && (float) $nuvei_last_trans['totalAmount'] > 0
                && $this->data['order_status_id'] 
                    == $this->plugin_settings[NUVEI_SETTINGS_PREFIX . 'pending_status_id'] // auth
            ) {
                $nuveiAllowSettleBtn = 1;
            }
            
            // can we show Cancel Subscription Button
//            $query =
//                "SELECT subscription_id "
//                . "FROM ". DB_PREFIX ."subscription "
//                . "WHERE order_id = " . $order_id . " "
//                . "AND subscription_status_id = 2"; // active
//            
//            $res = $this->db->query($query);
//            
//            if(isset($res->num_rows) && $res->num_rows > 0 && $this->data['order_status_id'] != 2) {
            if ($this->isActiveSubscription($order_id)) {
                $allowCancelSubsBtn = 1;
            }
            // /do we allow Cancel Subscription Button
            
            $remainingTotalCurr = $this->currency->format(
                $nuvei_remaining_total,
                $this->data['currency_code'],
                1 // 1 in case the amout is converted, else - $this->data['currency_value']
            );
        }
        
        $json = json_encode([
            'nuveiAllowRefundBtn'           => $nuveiAllowRefundBtn,
            'nuveiAllowVoidBtn'             => $nuveiAllowVoidBtn,
            'nuveiAllowSettleBtn'           => $nuveiAllowSettleBtn,
            'nuveiAllowCancelSubsBtn'       => $allowCancelSubsBtn,
            'nuveiRefunds'                  => json_encode($nuvei_refunds),
            'remainingTotalCurr'            => $remainingTotalCurr, // formated
            'isNuveiOrder'                  => $isNuveiOrder,
            'orderTotal'                    => round($nuvei_remaining_total, 2),
            'currSymbolRight'               => $this->currency->getSymbolRight($this->data['currency_code']),
            'currSymbolLeft'                => $this->currency->getSymbolLeft($this->data['currency_code']),
            
            'nuveiRefundAmountError'        => $this->language->get('nuvei_refund_amount_error'),
            'nuveiUnexpectedError'          => $this->language->get('error_unexpected'),
            'nuveiOrderConfirmDelRefund'    => $this->language->get('nuvei_order_confirm_del_refund'),
            'nuveiCreateRefund'             => $this->language->get('nuvei_create_refund'),
            'nuveiOrderConfirmRefund'       => $this->language->get('nuvei_order_confirm_refund'),
            'nuveiBtnManualRefund'          => $this->language->get('nuvei_btn_manual_refund'),
            'nuveiBtnRefund'                => $this->language->get('nuvei_btn_refund'),
            'nuveiBtnVoid'                  => $this->language->get('nuvei_btn_void'),
            'nuveiBtnCancelSubs'            => $this->language->get('button_nuvei_cancel_subs'),
            'nuveiConfirmCancelSubs'        => $this->language->get('entry_confirm_cancel_subs'),
            'nuveiOrderConfirmCancel'       => $this->language->get('nuvei_order_confirm_cancel'),
            'nuveiBtnSettle'                => $this->language->get('nuvei_btn_settle'),
            'nuveiOrderConfirmSettle'       => $this->language->get('nuvei_order_confirm_settle'),
            'nuveiMoreActions'              => $this->language->get('nuvei_more_actions'),
            'nuveiRefundId'                 => $this->language->get('nuvei_refund_id'),
            'nuveiDate'                     => $this->language->get('nuvei_date'),
            'nuveiRemainingTotal'           => $this->language->get('nuvei_remaining_total'),
        ]);
        
        $this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput($json);
    }
    
    public function refund()
    {
        if(!isset($this->request->post['orderId'])) {
            exit(json_encode(array(
                'status'    => 0,
                'msg'       => 'orderId parameter is not set.')
            ));
        }
        
        $this->load->model('sale/order');
        $this->load->model('setting/setting');
        
        $this->plugin_settings = $this->model_setting_setting->getSetting(trim(NUVEI_SETTINGS_PREFIX, '_'));
        
        $order_id           = (int) $this->request->post['orderId'];
        $this->notify_url   = $this->url->link(
            NUVEI_CONTROLLER_PATH . '%7Ccallback'
        );

        $this->notify_url   = str_replace('admin/', '', $this->notify_url);
		$request_amount     = round((float) $this->request->post['amount'], 2);
		
		\Nuvei_Class::create_log(
            $this->plugin_settings,
			array('order_id' => $order_id,),
			'order_refund()'
		);
		
        if($request_amount <= 0) {
            echo json_encode(array(
                'status'    => 0,
                'msg'       => 'The Refund Amount must be greater than 0!')
            );
            exit;
        }
        
        $this->data             = $this->model_sale_order->getOrder($order_id);
        $remaining_ref_amound   = $this->get_price($this->data['total']);
        $last_sale_tr           = [];
        
        \Nuvei_Class::create_log(
            $this->plugin_settings,
            $this->data['payment_custom_field'],
            'refund payment_custom_field'
        );
        
        // get the refunds
        foreach(array_reverse($this->data['payment_custom_field']) as $tr_data) {
            if(in_array($tr_data['transactionType'], array('Refund', 'Credit'))
                && 'approved' == $tr_data['status']
            ) {
                $remaining_ref_amound -= $tr_data['totalAmount'];
            }
            
            if(empty($last_sale_tr)
                && in_array($tr_data['transactionType'], array('Sale', 'Settle'))
                && 'approved' == $tr_data['status']
            ) {
                $last_sale_tr = $tr_data;
            }
        }
        
        if(round($remaining_ref_amound, 2) < $request_amount) {
            echo json_encode(array(
                'status'    => 0,
                'msg'       => 'Refunds sum exceeds Order Amount')
            );
            exit;
        }
        
		$time = date('YmdHis');
		
        $ref_parameters = array(
			'clientUniqueId'        => $order_id . '_' . $time . '_' . uniqid(),
            'clientRequestId'       => $time . '_' . uniqid(),
			'amount'                => $this->request->post['amount'],
			'currency'              => $this->data['currency_code'],
			'relatedTransactionId'  => $last_sale_tr['transactionId'],
			'authCode'              => $last_sale_tr['authCode'],
			'url'                   => $this->notify_url,
			'customData'            => $request_amount, // optional - pass the Refund Amount here
			'urlDetails'            => array('notificationUrl' => $this->notify_url),
			'url'                   => $this->notify_url,
		);
		
		$resp = \Nuvei_Class::call_rest_api(
            'refundTransaction',
            $this->plugin_settings,
            ['merchantId', 'merchantSiteId', 'clientRequestId', 'clientUniqueId', 'amount', 'currency', 'relatedTransactionId', 'authCode', 'url', 'timeStamp'],
            $ref_parameters
        );
			
        if(!$resp || !is_array($resp)) {
            exit(json_encode(array(
                'status'    => 0, 
                'msg'       => $this->language->load('error_invalid_req_resp')
            )));
        }
        
        // in case we have message but without status
        if(!isset($resp['status']) && isset($resp['msg'])) {
            exit(json_encode(array(
                'status'    => 0,
                'msg'       => $resp['msg']
            )));
        }
        
        // the status of the request is ERROR
        if(!empty($resp['status']) && $resp['status'] == 'ERROR') {
            exit(json_encode(array(
                'status'    => 0, 
                'msg'       => !empty($resp['reason']) ? $resp['reason'] : $this->language->load('error_unexpected')
            )));
        }
        
        // if request is success, we will wait for DMN
//        $order_status = 1; // pending
//        
//        if($remaining_ref_amound == $request_amount) {
//            $order_status = 11; // refunded
//        }
        
        $this->db->query(
            "UPDATE " . DB_PREFIX ."order "
            . "SET order_status_id = 2 " // processing
            . "WHERE order_id = {$order_id};"
        );
        
        exit(json_encode(array(
            'status' => 1
        )));
    }
    
    /**
     * We use one function for both because the only
     * difference is the endpoint, all parameters are same
     */
    public function settle(): string
    {
        $this->load->model('sale/order');
        $this->load->model('setting/setting');
        $this->load->language(NUVEI_CONTROLLER_PATH);
        
        $this->plugin_settings = $this->model_setting_setting->getSetting(trim(NUVEI_SETTINGS_PREFIX, '_'));
        
        if(!isset($this->request->post['orderId'])) {
            header('Content-Type: application/json');
            exit(json_encode([
                'status'    => 0,
                'msg'       => $this->language->get('error_orderId_param')
            ]));
        }
        
        $order_id           = (int) $this->request->post['orderId'];
        $this->notify_url   = $this->url->link(NUVEI_CONTROLLER_PATH . '%7Ccallback');
        $this->notify_url   = str_replace('admin/', '', $this->notify_url);
        $this->data         = $this->model_sale_order->getOrder($order_id);
        $time               = date('YmdHis', time());
        $last_allowed_trans = [];
        
        foreach(array_reverse($this->data['payment_custom_field']) as $tr_data) {
            if('settle' == $this->request->post['action']
                && 'Auth' == $tr_data['transactionType']
            ) {
                $last_allowed_trans = $tr_data;
                break;
            }
            
            if('void' == $this->request->post['action']
                && in_array($tr_data['transactionType'], array('Auth', 'Settle', 'Sale'))
            ) {
                $last_allowed_trans = $tr_data;
                break;
            }
        }
        
        $amount = $this->get_price($this->data['total']);
        
        # when try to Void/Settle Zero Auth Transaction
        if (0 == $amount) {
            header('Content-Type: application/json');
            exit(json_encode(array(
                'status'    => 0,
                'msg'       => $this->language->get('error_0_settle_void')
            )));
        }
        
        # normal Void or Settle
        $params = array(
            'clientRequestId'       => $time . '_' . uniqid(),
            'clientUniqueId'        => $order_id . '_' . $time . '_' . uniqid(),
            'amount'                => $amount,
            'currency'              => $this->data['currency_code'],
            'relatedTransactionId'  => $last_allowed_trans['transactionId'],
            'urlDetails'            => array('notificationUrl' => $this->notify_url),
            'url'                   => $this->notify_url, // a custom parameter
            'authCode'              => $last_allowed_trans['authCode'],
        );

        $resp = \Nuvei_Class::call_rest_api(
            'settle' == $this->request->post['action'] ? 'settleTransaction' : 'voidTransaction',
            $this->plugin_settings,
            ['merchantId', 'merchantSiteId', 'clientRequestId', 'clientUniqueId', 'amount', 'currency', 'relatedTransactionId', 'authCode', 'url', 'timeStamp'],
            $params
        );
		
        // error
		if(!$resp || !is_array($resp)
            || (isset($resp['status']) && $resp['status'] == 'ERROR')
            || (isset($resp['transactionStatus']) && $resp['transactionStatus'] == 'ERROR')
        ) {
			header('Content-Type: application/json');
            exit(json_encode(array(
                'status'    => 0,
                'msg'       => $this->language->get('error_settle_void_resp')
            )));
        }
		// decline
		if(isset($resp['transactionStatus']) && $resp['transactionStatus'] == 'DECLINED') {
			header('Content-Type: application/json');
            exit(json_encode(array(
                'status'    => 0,
                'msg'       => $this->language->get('error_req_denied')
            )));
        }
        // approve
        $this->db->query(
            'UPDATE ' . DB_PREFIX . 'order '
            . 'SET order_status_id = 2 ' // processing
            . 'WHERE order_id = ' . $order_id
        );
		
        header('Content-Type: application/json');
        exit(json_encode(array(
            'status' => 1,
        )));
    }
    
    public function void()
    {
        $this->settle();
    }
    
    public function cancelSubscription()
    {
        $this->load->model('sale/order');
        $this->load->model('setting/setting');
        $this->load->language(NUVEI_CONTROLLER_PATH);
        
        $this->plugin_settings = $this->model_setting_setting->getSetting(trim(NUVEI_SETTINGS_PREFIX, '_'));
        
        if(!isset($this->request->post['orderId'])) {
            header('Content-Type: application/json');
            exit(json_encode([
                'status'    => 0,
                'msg'       => $this->language->get('error_orderId_param')
            ]));
        }
        
        $order_id = (int) $this->request->post['orderId'];

        // do we allow Cancel Subscription
        if(!$this->isActiveSubscription($order_id)) {
            header('Content-Type: application/json');
            exit(json_encode([
                'status'    => 0,
                'msg'       => $this->language->get('error_no_activ_subs')
            ]));
        }
        
        $this->notify_url   = $this->url->link(NUVEI_CONTROLLER_PATH . '%7Ccallback');
        $this->notify_url   = str_replace('admin/', '', $this->notify_url);
        $this->data         = $this->model_sale_order->getOrder($order_id);
        $last_allowed_trans = [];
        
        foreach(array_reverse($this->data['payment_custom_field']) as $tr_data) {
            if(in_array($tr_data['transactionType'], array('Settle', 'Sale', 'Auth'))) {
                $last_allowed_trans = $tr_data;
                break;
            }
        }
        
        if (empty($last_allowed_trans['subscrIDs'])) {
            header('Content-Type: application/json');
            exit(json_encode([
                'status'    => 0,
                'msg'       => $this->language->get('error_missing_subs_id')
            ]));
        }
        
        // default Error response
        $resp = array('status' => 0);

        $resp = \Nuvei_Class::call_rest_api(
            'cancelSubscription',
            $this->plugin_settings,
            ['merchantId', 'merchantSiteId', 'subscriptionId', 'timeStamp'],
            ['subscriptionId' => $last_allowed_trans['subscrIDs']]
        );

        if(!$resp || !is_array($resp)
            || (isset($resp['status']) && $resp['status'] == 'ERROR')
            || (isset($resp['transactionStatus']) && $resp['transactionStatus'] == 'ERROR')
        ) {
            $resp['msg'] = $this->language->get('Cancel requrest for Subscription ID ') 
                . $last_allowed_trans['subscrIDs'] . $this->language->get('failed.') . ' ';
        }
        elseif(isset($resp['transactionStatus']) && $resp['transactionStatus'] == 'DECLINED') {
            $resp['msg'] = $this->language->get('Cancel requrest for Subscription ID ') 
                . $last_allowed_trans['subscrIDs'] . $this->language->get('was declined.') . ' ';
        }

        $resp['status'] = 1;

        header('Content-Type: application/json');
        exit(json_encode($resp));
    }
    
    /**
     * Check for newer version of the plugin.
     */
    public function check_for_update()
    {
        $this->load->language(NUVEI_CONTROLLER_PATH);
        
        $matches = array();
        $ch      = curl_init();

        curl_setopt(
            $ch,
            CURLOPT_URL,
            'https://raw.githubusercontent.com/SafeChargeInternational/nuvei_checkout_opencart3/main/CHANGELOG.md'
        );

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $file_text = curl_exec($ch);
        curl_close($ch);

        preg_match('/(#\s[0-9]\.[0-9])(\n)?/', $file_text, $matches);

        if (!isset($matches[1])) {
            exit(json_encode([
                'status'    => 0,
                'msg'       => $this->language->get('text_no_github_plugin_version')
            ]));
        }
        
        $git_v  = (int) str_replace('.', '', trim($matches[1]));
        $curr_v = (int) str_replace('.', '', NUVEI_PLUGIN_V);
        
        if($git_v <= $curr_v) {
            exit(json_encode([
                'status'    => 0,
                'msg'       => $this->language->get('text_github_plugin_same_version')
            ]));
        }
        
        exit(json_encode([
            'status'    => 1,
            'msg'       => $this->language->get('text_github_new_plugin_version'),
        ]));
    }
    
    /**
     * Just check for an active subscription for an order.
     * 
     * @param int $order_id
     * @return bool
     */
    private function isActiveSubscription($order_id)
    {
        $query =
            "SELECT subscription_id "
            . "FROM ". DB_PREFIX ."subscription "
            . "WHERE order_id = " . (int) $order_id . " "
            . "AND subscription_status_id IN (1, 2)"; // pending or active

        $res = $this->db->query($query);
        
        if(!isset($res->num_rows) || $res->num_rows == 0) {
            return false;
        }
        
        return true;
    }
    
    /**
	 * Here we only set template variables
	 */
	private function get_payment_methods(): array
    {
        $payment_methods = [];
        
        # get session token
        $resp = \Nuvei_Class::call_rest_api(
            'getSessionToken', 
            $this->plugin_settings, 
            ['merchantId', 'merchantSiteId', 'clientRequestId', 'timeStamp'],
            ['clientRequestId' => date('YmdHis', time()) . '_' . uniqid()]
        );
        
        // on missing session token
        if(empty($resp['sessionToken'])) {
            \Nuvei_Class::create_log($this->plugin_settings, '','Missing session token', 'WARN');
            return $payment_methods;
        }
        # /get session token
        
        if (empty($this->plugin_settings[NUVEI_SETTINGS_PREFIX . 'block_pms'])
            || !is_array($this->plugin_settings[NUVEI_SETTINGS_PREFIX . 'block_pms'])
        ) {
            $nuvei_block_pms = [];
        }
        else {
            $nuvei_block_pms = $this->plugin_settings[NUVEI_SETTINGS_PREFIX . 'block_pms'];
        }
        
        \Nuvei_Class::create_log($this->plugin_settings, $nuvei_block_pms, '$nuvei_block_pms');
		
        # get APMs
		$apms_params = array(
			'sessionToken'      => $resp['sessionToken'],
			'languageCode'      => $this->language->get('code'),
            'clientRequestId'   => date('YmdHis', time()) . '_' . uniqid(),
		);
        
		$res = \Nuvei_Class::call_rest_api(
            'getMerchantPaymentMethods',
            $this->plugin_settings,
            array('merchantId', 'merchantSiteId', 'clientRequestId', 'timeStamp'),
            $apms_params,
        );
        
		if(!empty($res['paymentMethods']) && is_array($res['paymentMethods'])) {
            foreach($res['paymentMethods'] as $pm) {
                if(empty($pm['paymentMethod'])) {
                    continue;
                }
                
                $pm_name = '';
                
                if(!empty($pm['paymentMethodDisplayName'][0]['message'])) {
                    $pm_name = $pm['paymentMethodDisplayName'][0]['message'];
                }
                else {
                    $pm_name = ucfirst(str_replace('_', ' ', $pm['paymentMethod']));
                }
                
                $payment_methods[] = [
                    'code'      => $pm['paymentMethod'],
                    'name'      => $pm_name,
                    'selected'  => in_array($pm['paymentMethod'], $nuvei_block_pms) ? 1 : 0
                ];
            }
		}
        
		return $payment_methods;
	}
    
    /**
     * Get some price by the currency convert rate.
     */
    private function get_price($price)
    {
        $new_price = round((float) $price * $this->data['currency_value'], 2);
        return number_format($new_price, 2, '.', '');
    }
    
}

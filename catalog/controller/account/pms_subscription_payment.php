<?php
namespace Opencart\Catalog\Controller\Account;

class PmsSubscriptionPayment extends \Opencart\System\Engine\Controller {

    private $bkash_config = [
        'sandbox_mode' => true,  // Set to false for live
        'sandbox_api_url' => 'https://tokenized.sandbox.bka.sh/v1.2.0-beta',
        'live_api_url' => 'https://tokenized.pay.bka.sh/v1.2.0-beta',
        'app_key' => '',  // Your bKash App Key
        'app_secret' => '',  // Your bKash App Secret
        'username' => '',  // Your bKash Username
        'password' => '',  // Your bKash Password
    ];

    public function index(): void {
        $this->load->language('pms/subscription_payment');
        
        $unique_id = isset($this->request->get['unique_id']) ? $this->request->get['unique_id'] : '';
        
        if (!$unique_id) {
            $this->response->redirect($this->url->link('account/pms_subscription', 'language=' . $this->config->get('config_language')));
        }
        
        $this->load->model('pms/project_subscription');
        $payment_request = $this->model_pms_project_subscription->getPaymentRequest($unique_id);
        
        if (!$payment_request) {
            $this->response->redirect($this->url->link('account/pms_subscription', 'language=' . $this->config->get('config_language')));
        }
        
        $data['unique_id'] = $unique_id;
        $data['total_amount'] = $payment_request['total_amount'];
        $data['duration_months'] = $payment_request['duration_months'];
        
        $data['action'] = $this->url->link('account/pms_subscription_payment.initiate', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token'], true);
        $data['back'] = $this->url->link('account/pms_subscription', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token'], true);
        
        $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
        
        $this->response->setOutput($this->load->view('account/pms_subscription_payment', $data));
    }
    
    public function initiate(): void {
        $this->load->language('pms/subscription_payment');
        
        $json = [];
        
        $unique_id = $this->request->post['unique_id'];
        $amount = (float)$this->request->post['amount'];
        $payment_method = $this->request->post['payment_method'];
        
        if ($payment_method == 'bkash') {
            // Get bKash token
            $token = $this->getBkashToken();
            
            if ($token) {
                // Create bKash payment
                $payment_data = [
                    'amount' => $amount,
                    'currency' => 'BDT',
                    'merchantInvoiceNumber' => $unique_id,
                    'intent' => 'sale'
                ];
                
                $ch = curl_init();
                $url = ($this->bkash_config['sandbox_mode'] ? $this->bkash_config['sandbox_api_url'] : $this->bkash_config['live_api_url']) . '/payment/create';
                
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payment_data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Authorization: ' . $token,
                    'X-APP-Key: ' . $this->bkash_config['app_key']
                ]);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                
                $response = curl_exec($ch);
                $response_data = json_decode($response, true);
                curl_close($ch);
                
                if (isset($response_data['paymentID'])) {
                    $json['success'] = true;
                    $json['bkashURL'] = $response_data['bkashURL'];
                    $json['paymentID'] = $response_data['paymentID'];
                } else {
                    $json['error'] = $response_data['errorMessage'] ?? 'Payment initiation failed';
                }
            } else {
                $json['error'] = 'Failed to get bKash token';
            }
        } else {
            // Manual payment (Bank transfer, etc.)
            $this->load->model('pms/project_subscription');
            $payment_request = $this->model_pms_project_subscription->getPaymentRequest($unique_id);
            
            if ($payment_request) {
                $json['success'] = true;
                $json['redirect'] = $this->url->link('account/pms_subscription_payment.manualConfirm', 'language=' . $this->config->get('config_language') . '&unique_id=' . $unique_id . '&customer_token=' . $this->session->data['customer_token'], true);
            } else {
                $json['error'] = 'Invalid payment request';
            }
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    
    public function callback(): void {
        $payment_id = $this->request->get['paymentID'];
        $status = $this->request->get['status'];
        
        if ($status == 'success') {
            // Execute payment
            $this->load->model('pms/project_subscription');
            
            $token = $this->getBkashToken();
            
            $ch = curl_init();
            $url = ($this->bkash_config['sandbox_mode'] ? $this->bkash_config['sandbox_api_url'] : $this->bkash_config['live_api_url']) . '/payment/execute/' . $payment_id;
            
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: ' . $token,
                'X-APP-Key: ' . $this->bkash_config['app_key']
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            $response = curl_exec($ch);
            $response_data = json_decode($response, true);
            curl_close($ch);
            
            if (isset($response_data['merchantInvoiceNumber'])) {
                $unique_id = $response_data['merchantInvoiceNumber'];
                $trx_id = $response_data['trxID'];
                
                $this->model_pms_project_subscription->completePaymentRequest($unique_id, $trx_id, 'bkash', $response_data);

                $project_info = $this->customer->getProject();
                $project_id = $project_info['project_id'];

                // After activating subscription, clear cache
                $this->load->helper('pms_subscription');
                $this->helper_pms_subscription->clearCache($project_id);
                
                $this->response->redirect($this->url->link('account/pms_subscription_payment.success', 'language=' . $this->config->get('config_language') . '&unique_id=' . $unique_id));
            } else {
                $this->response->redirect($this->url->link('account/pms_subscription_payment.failed', 'language=' . $this->config->get('config_language')));
            }
        } else {
            $this->response->redirect($this->url->link('account/pms_subscription_payment.failed', 'language=' . $this->config->get('config_language')));
        }
    }
    
    public function manualConfirm(): void {
        $unique_id = isset($this->request->get['unique_id']) ? $this->request->get['unique_id'] : '';
        
        $data['unique_id'] = $unique_id;
        $data['action'] = $this->url->link('account/pms_subscription_payment.submitManual', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token'], true);
        $data['back'] = $this->url->link('account/pms_subscription', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token'], true);
        
        $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
        
        $this->response->setOutput($this->load->view('account/pms_subscription_manual_payment', $data));
    }
    
    public function submitManual(): void {
        $this->load->language('pms/subscription_payment');
        
        $json = [];
        
        $unique_id = $this->request->post['unique_id'];
        $transaction_id = $this->request->post['transaction_id'];
        $payment_method = $this->request->post['payment_method'];
        $reference = $this->request->post['reference'];
        
        $this->load->model('pms/project_subscription');
        $result = $this->model_pms_project_subscription->completePaymentRequest($unique_id, $transaction_id, $payment_method, ['reference' => $reference]);
        
        // After activating subscription, clear cache
        $project_info = $this->customer->getProject();
        $project_id = $project_info['project_id'];
        
        $this->load->helper('pms_subscription');
        $this->helper_pms_subscription->clearCache($project_id);

        if ($result) {
            $json['success'] = true;
            $json['redirect'] = $this->url->link('account/pms_subscription_payment.success', 'language=' . $this->config->get('config_language') . '&unique_id=' . $unique_id . '&customer_token=' . $this->session->data['customer_token'], true);
        } else {
            $json['error'] = 'Failed to process payment';
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    
    public function success(): void {
        $this->load->language('pms/subscription_payment');
        
        $unique_id = isset($this->request->get['unique_id']) ? $this->request->get['unique_id'] : '';
        
        $this->load->model('pms/project_subscription');
        $payment = $this->db->query("
            SELECT * FROM `" . DB_PREFIX . "pms_subscription_payment`
            WHERE payment_unique_id = '" . $this->db->escape($unique_id) . "'
        ");
        
        $data['payment'] = $payment->row;
        $data['dashboard'] = $this->url->link('account/manager', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token'], true);
        
        $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
        
        $this->response->setOutput($this->load->view('account/pms_payment_success', $data));
    }
    
    public function failed(): void {
        $this->load->language('pms/subscription_payment');
        
        $data['retry'] = $this->url->link('account/pms_subscription', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token'], true);
        
        $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
        
        $this->response->setOutput($this->load->view('account/pms_payment_failed', $data));
    }
    
    private function getBkashToken(): ?string {
        $post_data = [
            'app_key' => $this->bkash_config['app_key'],
            'app_secret' => $this->bkash_config['app_secret']
        ];
        
        $url = ($this->bkash_config['sandbox_mode'] ? $this->bkash_config['sandbox_api_url'] : $this->bkash_config['live_api_url']) . '/tokenized/checkout/token/grant';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $response_data = json_decode($response, true);
        curl_close($ch);
        
        return $response_data['id_token'] ?? null;
    }
}
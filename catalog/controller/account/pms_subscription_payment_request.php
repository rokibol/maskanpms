<?php
namespace Opencart\Catalog\Controller\Account;

class PmsSubscriptionPaymentRequest extends \Opencart\System\Engine\Controller {

    public function index(): void {
        if (!$this->customer->isLogged()) {
            $this->response->redirect($this->url->link('account/login', 'language=' . $this->config->get('config_language')));
        }

        $this->load->language('pms/subscription_payment_request');
        $this->document->setTitle($this->language->get('heading_title'));

        // Get current project
        $this->load->model('pms/project');
        $project = $this->model_pms_project->getProjectByCustomerId($this->customer->getId());

        if (!$project) {
            $this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language')));
        }

        // Get subscription calculation
        $this->load->model('pms/project_subscription');
        $calculation = $this->model_pms_project_subscription->getSubscriptionCalculationDisplay($project['project_id']);

        // Get duration from URL or session
        $duration_months = isset($this->request->get['duration']) ? (int)$this->request->get['duration'] : 0;
        
        if ($duration_months <= 0) {
            $this->response->redirect($this->url->link('account/pms_subscription', 'language=' . $this->config->get('config_language')));
        }

        // Calculate amount for the duration
        $amount_data = $this->model_pms_project_subscription->calculateSubscriptionAmount($project['project_id'], $duration_months);
        
        $data['project_id'] = $project['project_id'];
        $data['project_name'] = $project['name'];
        $data['duration_months'] = $duration_months;
        $data['amount'] = $amount_data['final_amount'];
        $data['apartment_count'] = $calculation['apartment_count'];
        $data['rate_per_apartment'] = $calculation['rate_per_apartment'];
        $data['bkash_number'] = '01775141572'; // Your business bKash number

        $data['action'] = $this->url->link('account/pms_subscription_payment_request.submit', 'language=' . $this->config->get('config_language'));
        $data['back'] = $this->url->link('account/pms_subscription', 'language=' . $this->config->get('config_language'));

        $data['header'] = $this->load->controller('common/header');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('account/pms_subscription_payment_request', $data));
    }

    public function submit(): void {
        $this->load->language('pms/subscription_payment_request');
        
        $json = [];

        if (!$this->customer->isLogged()) {
            $json['error'] = $this->language->get('error_permission');
        }

        if (!$json) {
            $this->load->model('pms/project');
            $project = $this->model_pms_project->getProjectByCustomerId($this->customer->getId());

            if (!$project) {
                $json['error'] = 'Project not found';
            }
        }

        if (!$json) {
            $bkash_number = $this->request->post['bkash_number'];
            $transaction_id = $this->request->post['transaction_id'];
            $duration_months = (int)$this->request->post['duration_months'];
            $amount = (float)$this->request->post['amount'];

            // Validate
            if (empty($bkash_number) || strlen($bkash_number) < 11) {
                $json['error']['bkash_number'] = $this->language->get('error_bkash_number');
            }

            if (empty($transaction_id)) {
                $json['error']['transaction_id'] = $this->language->get('error_transaction_id');
            }

            // Check for duplicate transaction ID
            $this->load->model('pms/subscription_payment_request');
            $existing = $this->model_pms_subscription_payment_request->getRequestByTransactionId($transaction_id);
            
            if ($existing) {
                $json['error']['transaction_id'] = $this->language->get('error_transaction_exists');
            }

            if ($duration_months <= 0) {
                $json['error']['duration'] = $this->language->get('error_duration');
            }

            if ($amount <= 0) {
                $json['error']['amount'] = $this->language->get('error_amount');
            }
        }

        if (!$json) {
            $request_data = [
                'project_id' => $project['project_id'],
                'duration_months' => $duration_months,
                'amount' => $amount,
                'bkash_number' => $bkash_number,
                'transaction_id' => $transaction_id,
                'submitted_by' => $this->customer->getId()
            ];

            $request_id = $this->model_pms_subscription_payment_request->addRequest($request_data);

            $json['success'] = $this->language->get('text_request_submitted');
            $json['redirect'] = $this->url->link('account/pms_subscription_payment_request.success', 'language=' . $this->config->get('config_language'));
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function success(): void {
        $this->load->language('pms/subscription_payment_request');
        $this->document->setTitle($this->language->get('heading_success'));

        $data['dashboard'] = $this->url->link('account/manager', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        
        $data['header'] = $this->load->controller('common/header');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('account/pms_subscription_payment_success', $data));
    }

    /**
     * Check if transaction ID already exists (AJAX)
     */
    public function checkTransaction(): void {
        $json = [];
        
        $transaction_id = isset($this->request->get['transaction_id']) ? $this->request->get['transaction_id'] : '';
        
        if (!empty($transaction_id)) {
            $this->load->model('pms/subscription_payment_request');
            $existing = $this->model_pms_subscription_payment_request->getRequestByTransactionId($transaction_id);
            
            if ($existing) {
                $json['exists'] = true;
                $json['message'] = 'This transaction ID has already been used. Please check and try again.';
            } else {
                $json['exists'] = false;
            }
        } else {
            $json['exists'] = false;
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
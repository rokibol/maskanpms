<?php
namespace Opencart\Catalog\Controller\Account;

class PmsSubscription extends \Opencart\System\Engine\Controller {

    public function index(): void {
        if (!$this->customer->isLogged()) {
            $this->response->redirect($this->url->link('account/login', 'language=' . $this->config->get('config_language')));
        }
        
        $this->load->language('pms/subscription');
        $this->document->setTitle($this->language->get('heading_title'));
        
        // Get current project
        $this->load->model('pms/project');
        $project = $this->model_pms_project->getProjectByCustomerId($this->customer->getId());
        
        if (!$project) {
            $this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']));
        }
        
        $this->load->model('pms/project_subscription');
        
        $data['project_name'] = $project['name'];
        $data['subscription'] = $this->model_pms_project_subscription->getSubscriptionByProject($project['project_id']);
        $data['is_active'] = $this->model_pms_project_subscription->isSubscriptionActive($project['project_id']);
        $data['expiry_days'] = $this->model_pms_project_subscription->getExpiryDays($project['project_id']);
        $data['monthly_rate'] = $this->model_pms_project_subscription->getMonthlyRate();
        $data['duration_options'] = $this->model_pms_project_subscription->getDurationOptions();
        
        // Calculate amounts for each duration option
        $data['duration_amounts'] = [];
        foreach ($data['duration_options'] as $months => $label) {
            $data['duration_amounts'][$months] = $this->model_pms_project_subscription->calculateAmount($months);
        }
        
        $data['action'] = $this->url->link('account/pms_subscription.activate', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        $data['back'] = $this->url->link('account/manager', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        
        $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
        
        $this->response->setOutput($this->load->view('account/pms_subscription', $data));
    }
    
    /*public function activate(): void {
        $this->load->language('pms/subscription');
        
        $json = [];
        
        if (!$this->customer->isLogged()) {
            $json['error'] = $this->language->get('error_permission');
        }
        
        if (!$json) {
            $this->load->model('pms/project');
            $project = $this->model_pms_project->getProjectByCustomerId($this->customer->getId());
            
            if ($project) {
                $this->load->model('pms/project_subscription');
                
                $duration_months = (int)$this->request->post['duration_months'];
                
                if ($duration_months <= 0) {
                    $json['error'] = 'Invalid duration selected';
                } else {
                    $result = $this->model_pms_project_subscription->activateSubscription($project['project_id'], $duration_months);
                    
                    if ($result['success']) {
                        $json['success'] = $this->language->get('text_activation_success');
                        $json['total_amount'] = $result['total_amount'];
                        $json['end_date'] = date('d M Y', strtotime($result['end_date']));
                        $json['redirect'] = $this->url->link('account/pms_subscription', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
                    } else {
                        $json['error'] = $result['message'];
                    }
                }
            } else {
                $json['error'] = 'Project not found';
            }
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }*/

    public function activate(): void {
        $this->load->language('pms/subscription');
        
        $json = [];
        
        if (!$this->customer->isLogged()) {
            $json['error'] = $this->language->get('error_permission');
        }
        
        if (!$json) {
            $this->load->model('pms/project');
            $project = $this->model_pms_project->getProjectByCustomerId($this->customer->getId());
            
            if ($project) {
                $this->load->model('pms/project_subscription');
                
                $duration_months = (int)$this->request->post['duration_months'];
                $monthly_rate = $this->model_pms_project_subscription->getMonthlyRate();
                $total_amount = $duration_months * $monthly_rate;
                
                // Create payment request instead of direct activation
                $payment_request = $this->model_pms_project_subscription->createPaymentRequest($project['project_id'], $duration_months, $total_amount);
                
                if ($payment_request) {
                    $json['success'] = true;
                    $json['redirect'] = $this->url->link('account/pms_subscription_payment', 'language=' . $this->config->get('config_language') . '&unique_id=' . $payment_request['payment_unique_id']);
                } else {
                    $json['error'] = 'Failed to create payment request';
                }
            } else {
                $json['error'] = 'Project not found';
            }
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Subscription Expired Page
     */
    public function expired(): void {
        $this->index();
    }
}
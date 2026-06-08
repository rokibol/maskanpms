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
            $this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language')));
        }
        
        $this->load->model('pms/project_subscription');
        
        $data['project_name'] = $project['name'];
        $data['subscription'] = $this->model_pms_project_subscription->getSubscriptionByProject($project['project_id']);
        $data['is_active'] = $this->model_pms_project_subscription->isSubscriptionActive($project['project_id']);
        $data['expiry_days'] = $this->model_pms_project_subscription->getExpiryDays($project['project_id']);
        $data['plans'] = $this->model_pms_project_subscription->getAvailablePlans();
        
        $data['action'] = $this->url->link('account/pms_subscription.upgrade', 'language=' . $this->config->get('config_language'));
        $data['back'] = $this->url->link('account/pms_manager_dashboard', 'language=' . $this->config->get('config_language'));
        
        $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
        
        $this->response->setOutput($this->load->view('account/pms_subscription', $data));
    }
    
    public function upgrade(): void {
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
                
                $plan_id = (int)$this->request->post['plan_id'];
                $billing_type = $this->request->post['billing_type'];
                
                $result = $this->model_pms_project_subscription->activateSubscription($project['project_id'], $plan_id, $billing_type);
                
                if ($result) {
                    $json['success'] = $this->language->get('text_upgrade_success');
                    $json['redirect'] = $this->url->link('account/pms_subscription', 'language=' . $this->config->get('config_language'));
                } else {
                    $json['error'] = $this->language->get('error_upgrade_failed');
                }
            } else {
                $json['error'] = 'Project not found';
            }
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
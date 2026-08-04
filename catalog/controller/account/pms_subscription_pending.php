<?php
namespace Opencart\Catalog\Controller\Account;

class PmsSubscriptionPending extends \Opencart\System\Engine\Controller {

    public function index(): void {
        $this->load->language('pms/subscription_pending');
        
        $this->document->setTitle($this->language->get('heading_title'));
        
        // Get current project
        $this->load->model('pms/project');
        $project = $this->model_pms_project->getProjectByCustomerId($this->customer->getId());
        
        if (!$project) {
            $this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language')));
        }
        
        $data['project_name'] = $project['name'];
        
        // Get pending request details
        $this->load->model('pms/project_subscription');
        $pending_request = $this->model_pms_project_subscription->getPendingPaymentRequest($project['project_id']);
        
        $data['submitted_at'] = $pending_request ? date('d F Y h:i A', strtotime($pending_request['submitted_at'])) : 'N/A';
        $data['transaction_id'] = $pending_request['transaction_id'] ?? 'N/A';
        $data['amount'] = $pending_request['amount'] ?? 0;
        
        // Support contact
        $data['support_email'] = $this->config->get('config_email');
        $data['support_phone'] = $this->config->get('config_telephone');
        
        // Check if request is still pending (refresh check)
        $data['is_pending'] = $this->model_pms_project_subscription->hasPendingPaymentRequest($project['project_id']);
        
        $data['logout'] = $this->url->link('account/logout', 'language=' . $this->config->get('config_language'));
        $data['refresh'] = $this->url->link('account/pms_subscription_pending', 'language=' . $this->config->get('config_language'));
        
        $data['header'] = $this->load->controller('common/header');
        $data['footer'] = $this->load->controller('common/footer');
        
        $this->response->setOutput($this->load->view('account/pms_subscription_pending', $data));
    }
}
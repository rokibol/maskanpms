<?php
namespace Opencart\Catalog\Controller\Account;

class PmsSubscriptionExpired extends \Opencart\System\Engine\Controller {

    public function index(): void {
        $this->load->language('pms/subscription_expired');
        
        $this->document->setTitle($this->language->get('heading_title'));
        
        // Get current project
        $this->load->model('pms/project');
        $project = $this->model_pms_project->getProjectByCustomerId($this->customer->getId());
        
        if ($project) {
            $data['project_name'] = $project['name'];
        } else {
            $data['project_name'] = '';
        }
        
        // Get subscription details
        $this->load->model('pms/project_subscription');
        $subscription = $this->model_pms_project_subscription->getSubscriptionByProject($project['project_id']);
        $data['expiry_date'] = $subscription ? date('d F Y', strtotime($subscription['end_date'])) : 'N/A';
        
        // Get monthly rate
        $data['monthly_rate'] = $this->model_pms_project_subscription->getMonthlyRate();
        
        // Duration options
        $data['duration_options'] = [
            1 => '1 Month',
            3 => '3 Months',
            6 => '6 Months',
            12 => '12 Months (1 Year)',
            24 => '24 Months (2 Years)'
        ];
        
        // Calculate amounts
        $data['duration_amounts'] = [];
        foreach ($data['duration_options'] as $months => $label) {
            $data['duration_amounts'][$months] = $months * $data['monthly_rate'];
        }
        
        $data['action'] = $this->url->link('account/pms_subscription.activate', 'language=' . $this->config->get('config_language'));
        $data['back'] = $this->url->link('account/logout', 'language=' . $this->config->get('config_language'));
        
        $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
        
        $this->response->setOutput($this->load->view('account/pms_subscription_expired', $data));
    }
}
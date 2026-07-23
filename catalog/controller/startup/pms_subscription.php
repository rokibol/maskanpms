<?php
namespace Opencart\Catalog\Controller\Startup;

class PmsSubscription extends \Opencart\System\Engine\Controller {

    private $excluded_routes = [
        'account/pms_subscription',
        'account/pms_subscription_payment',
        'account/pms_subscription.activate',
        'account/pms_subscription_expired',
        'account/logout',
        'account/login',
        'common/home',
        'account/account',
        'account/pms_my_bills',
        'account/pms_dashboard',
        'account/pms_my_apartments',
        'account/pms_my_payment_allocations',
        'account/pms_my_transactions'
    ];

    public function index(): void {
        // Skip if not logged in
        if (!$this->customer->isLogged()) {
            return;
        }
        
        // Skip if no route
        $route = isset($this->request->get['route']) ? $this->request->get['route'] : '';
        if (empty($route)) {
            return;
        }
        
        // Skip excluded routes
        foreach ($this->excluded_routes as $excluded) {
            if (strpos($route, $excluded) === 0) {
                return;
            }
        }
        
        // Skip AJAX requests
        if (!empty($this->request->server['HTTP_X_REQUESTED_WITH'])) {
            return;
        }
        
        // Check if user is Manager or Accountant
        $customer_group_id = $this->customer->getGroupId();
        $manager_group_id = (int)$this->config->get('config_pms_manager_group_id');
        $accountant_group_id = (int)$this->config->get('config_pms_accountant_group_id');
        
        if ($customer_group_id != $manager_group_id && $customer_group_id != $accountant_group_id) {
            return;
        }
        
        // Get current project
        $this->load->model('pms/project');
        $project = $this->model_pms_project->getProjectByCustomerId($this->customer->getId());
        
        if (!$project) {
            return;
        }
        
        // Load subscription library with registry passed
        $this->load->library('pms/subscription');
        $subscription = new \Opencart\System\Library\Pms\Subscription($this->registry);
        $is_active = $subscription->isActive($project['project_id']);
        
        // If expired, redirect
        if (!$is_active && strpos($route, 'pms_subscription') === false && strpos($route, 'pms_subscription_payment') === false) {
            $this->response->redirect($this->url->link('account/pms_subscription_expired', 'language=' . $this->config->get('config_language')));
        }
    }
}
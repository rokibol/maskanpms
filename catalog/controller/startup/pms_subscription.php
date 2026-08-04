<?php
namespace Opencart\Catalog\Controller\Startup;

class PmsSubscription extends \Opencart\System\Engine\Controller {

    private static $executed = false;
    private $public_routes = [
        'account/pms_subscription',
        'account/pms_subscription_payment',
        'account/pms_subscription.activate',
        'account/pms_subscription_expired',
        'account/pms_subscription_pending',
        'account/pms_subscription_payment_request',
        'account/pms_subscription_payment_request.submit',
        'account/pms_subscription_payment_request.success',
        'account/logout',
        'account/login',
        'common/home',
        'account/account',
        'account/register',
        'account/pms_features',
        'account/pms_faq',
        'account/pms_contact'
    ];

    public function index(): void {
        // Prevent multiple executions
        if (self::$executed) {
            return;
        }
        self::$executed = true;

        // Skip if not logged in
        if (!$this->customer->isLogged()) {
            return;
        }
        
        // Skip if no route
        $route = isset($this->request->get['route']) ? $this->request->get['route'] : '';
        if (empty($route)) {
            return;
        }
        
        // Skip AJAX requests
        if (!empty($this->request->server['HTTP_X_REQUESTED_WITH'])) {
            return;
        }
        
        // Check if route is public
        foreach ($this->public_routes as $public) {
            if (strpos($route, $public) === 0) {
                return;
            }
        }
        
        // Get user role
        $this->load->model('pms/settings');
        $customer_group_id = $this->customer->getGroupId();
        $shareholder_group_id = $this->model_pms_settings->getShareholderGroupId();
        
        // Shareholders skip
        if ($customer_group_id == $shareholder_group_id) {
            return;
        }
        
        $manager_group_id = $this->model_pms_settings->getManagerGroupId();
        $accountant_group_id = $this->model_pms_settings->getAccountantGroupId();
        
        // Only check for Manager and Accountant
        if ($customer_group_id != $manager_group_id && $customer_group_id != $accountant_group_id) {
            return;
        }
        
        // Get project
        $this->load->model('pms/project');
        $project = $this->model_pms_project->getProjectByCustomerId($this->customer->getId());
        
        if (!$project) {
            return;
        }
        
        // ======================================================
        // CHECK SUBSCRIPTION STATUS
        // ======================================================
        $this->load->model('pms/project_subscription');
        $status = $this->model_pms_project_subscription->getSubscriptionStatus($project['project_id']);
        
        switch ($status) {
            case 'active':
                // Subscription is active - allow access
                $this->session->data['pms_subscription_active'] = true;
                return;
                
            case 'pending_approval':
                // Pending approval - redirect to pending page
                $this->session->data['pms_subscription_active'] = false;
                $this->response->redirect($this->url->link('account/pms_subscription_pending', 'language=' . $this->config->get('config_language')));
                return;
                
            case 'expired':
            default:
                // No subscription - redirect to expired page
                $this->session->data['pms_subscription_active'] = false;
                $this->response->redirect($this->url->link('account/pms_subscription_expired', 'language=' . $this->config->get('config_language')));
                return;
        }
    }
}
<?php
namespace Opencart\Catalog\Controller\Startup;

class PmsSubscription extends \Opencart\System\Engine\Controller {

    // Routes that are always allowed (even for expired subscription)
    private $public_routes = [
        'account/pms_subscription',
        'account/pms_subscription_payment',
        'account/pms_subscription.activate',
        'account/pms_subscription_expired',
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
        
        // Check if route is public (always allowed)
        foreach ($this->public_routes as $public) {
            if (strpos($route, $public) === 0) {
                return;
            }
        }
        
        // ======================================================
        // GET USER ROLE GROUP IDs FROM SETTINGS
        // ======================================================
        $this->load->model('pms/settings');
        
        $manager_group_id = $this->model_pms_settings->getManagerGroupId();
        $accountant_group_id = $this->model_pms_settings->getAccountantGroupId();
        $shareholder_group_id = $this->model_pms_settings->getShareholderGroupId();
        
        $customer_group_id = $this->customer->getGroupId();
        
        // ======================================================
        // SHAREHOLDER LOGIC - Skip subscription check
        // ======================================================
        if ($customer_group_id == $shareholder_group_id) {
            return;
        }
        
        // ======================================================
        // MANAGER/ACCOUNTANT LOGIC - Check subscription
        // ======================================================
        if ($customer_group_id != $manager_group_id && $customer_group_id != $accountant_group_id) {
            // Unknown role - allow access (shouldn't happen)
            return;
        }
        
        // ======================================================
        // GET PROJECT
        // ======================================================
        $this->load->model('pms/project');
        $project = $this->customer->getProject();
        
        if (!$project) {
            return;
        }
        
        // ======================================================
        // CHECK SUBSCRIPTION STATUS
        // ======================================================
        $this->load->model('pms/project_subscription');
        $is_active = $this->model_pms_project_subscription->isSubscriptionActiveOptimized($project['project_id']);
        
        // Store in session
        $this->session->data['pms_subscription_active'] = $is_active;
        $this->session->data['pms_project_id'] = $project['project_id'];
        
        // ======================================================
        // BLOCK ACCESS IF EXPIRED
        // ======================================================
        if (!$is_active) {
            $this->response->redirect($this->url->link('account/pms_subscription_expired', 'language=' . $this->config->get('config_language')));
        }
    }
}
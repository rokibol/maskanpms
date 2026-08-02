<?php
namespace Opencart\Catalog\Controller\Startup;

class PmsSubscription extends \Opencart\System\Engine\Controller {

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
        // Load the library and check subscription (runs once per request)
        //$this->load->library('pms/subscription_check');
        $registry = new \Opencart\System\Engine\Registry();
        
        $checker = new \Opencart\System\Library\Pms\SubscriptionCheck($registry);
        $checker->check();
    }


    public function index__(): void {
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
        
        // ======================================================
        // GET USER ROLE GROUP IDs - DIRECT DATABASE QUERY (CACHED)
        // ======================================================
        $customer_group_id = $this->customer->getGroupId();
        
        // Check session cache for role IDs
        $cache_key = 'pms_role_ids';
        
        if (isset($this->session->data['pms_cache'][$cache_key])) {
            $cached = $this->session->data['pms_cache'][$cache_key];
            if ($cached['expires'] > time()) {
                $manager_group_id = $cached['manager_group_id'];
                $accountant_group_id = $cached['accountant_group_id'];
                $shareholder_group_id = $cached['shareholder_group_id'];
            } else {
                // Cache expired, load fresh
                $role_ids = $this->loadRoleIds();
                $manager_group_id = $role_ids['manager'];
                $accountant_group_id = $role_ids['accountant'];
                $shareholder_group_id = $role_ids['shareholder'];
            }
        } else {
            // Load from database
            $role_ids = $this->loadRoleIds();
            $manager_group_id = $role_ids['manager'];
            $accountant_group_id = $role_ids['accountant'];
            $shareholder_group_id = $role_ids['shareholder'];
        }
        
        // Shareholders skip subscription check
        if ($customer_group_id == $shareholder_group_id) {
            return;
        }
        
        // Only check for Manager and Accountant
        if ($customer_group_id != $manager_group_id && $customer_group_id != $accountant_group_id) {
            return;
        }
        
        // ======================================================
        // GET PROJECT (Cached in session)
        // ======================================================
        $project = $this->getProject();
        
        if (!$project) {
            return;
        }
        
        // ======================================================
        // CHECK SUBSCRIPTION STATUS (Cached)
        // ======================================================
        $is_active = $this->checkSubscriptionStatus($project['project_id']);
        
        if (!$is_active) {
            $this->response->redirect($this->url->link('account/pms_subscription_expired', 'language=' . $this->config->get('config_language')));
        }
    }

    /**
     * Load role IDs from database with caching
     */
    private function loadRoleIds(): array {
        // Try session first
        $cache_key = 'pms_role_ids';
        if (isset($this->session->data['pms_cache'][$cache_key])) {
            $cached = $this->session->data['pms_cache'][$cache_key];
            if ($cached['expires'] > time()) {
                return [
                    'manager' => $cached['manager_group_id'],
                    'accountant' => $cached['accountant_group_id'],
                    'shareholder' => $cached['shareholder_group_id']
                ];
            }
        }

        // Single query to get all three role IDs
        $query = $this->db->query("
            SELECT 
                MAX(CASE WHEN setting_key = 'manager_group_id' THEN setting_value END) as manager_group_id,
                MAX(CASE WHEN setting_key = 'accountant_group_id' THEN setting_value END) as accountant_group_id,
                MAX(CASE WHEN setting_key = 'shareholder_group_id' THEN setting_value END) as shareholder_group_id
            FROM `" . DB_PREFIX . "pms_settings`
            WHERE setting_key IN ('manager_group_id', 'accountant_group_id', 'shareholder_group_id')
        ");

        $result = $query->row;

        $role_ids = [
            'manager' => (int)($result['manager_group_id'] ?? 1),
            'accountant' => (int)($result['accountant_group_id'] ?? 2),
            'shareholder' => (int)($result['shareholder_group_id'] ?? 3)
        ];

        // Store in session cache
        $this->session->data['pms_cache'][$cache_key] = [
            'manager_group_id' => $role_ids['manager'],
            'accountant_group_id' => $role_ids['accountant'],
            'shareholder_group_id' => $role_ids['shareholder'],
            'expires' => time() + 3600 // 1 hour
        ];

        return $role_ids;
    }

    /**
     * Get project with caching
     */
    private function getProject(): array {
        $cache_key = 'pms_project_' . $this->customer->getId();
        
        // Check session cache
        if (isset($this->session->data['pms_cache'][$cache_key])) {
            $cached = $this->session->data['pms_cache'][$cache_key];
            if ($cached['expires'] > time()) {
                return $cached['data'];
            }
        }
        
        // Query database
        $this->load->model('pms/project');
        $project = $this->model_pms_project->getProjectByCustomerId($this->customer->getId());
        
        // Store in session cache
        $this->session->data['pms_cache'][$cache_key] = [
            'data' => $project,
            'expires' => time() + 300 // 5 minutes
        ];
        
        return $project;
    }

    /**
     * Check subscription status with caching
     */
    private function checkSubscriptionStatus(int $project_id): bool {
        $cache_key = 'pms_sub_active_' . $project_id;
        
        // Check session cache
        if (isset($this->session->data['pms_cache'][$cache_key])) {
            $cached = $this->session->data['pms_cache'][$cache_key];
            if ($cached['expires'] > time()) {
                $this->session->data['pms_subscription_active'] = $cached['status'];
                return $cached['status'];
            }
        }
        
        // Query database
        $this->load->model('pms/project_subscription');
        $is_active = $this->model_pms_project_subscription->isSubscriptionActive($project_id);

        $myfile = fopen("subscription_check.txt", "a+");
        fwrite($myfile, print_r($is_active, true) . PHP_EOL);
        fclose($myfile);
        
        // Store in session cache
        $this->session->data['pms_cache'][$cache_key] = [
            'status' => $is_active,
            'expires' => time() + 300 // 5 minutes
        ];
        
        $this->session->data['pms_subscription_active'] = $is_active;
        
        return $is_active;
    }
}
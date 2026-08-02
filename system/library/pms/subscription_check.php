<?php
namespace Opencart\System\Library\Pms;

class SubscriptionCheck {
    private $registry;
    private static $checked = false;
    private static $result = null;

    public function __construct($registry) {
        $this->registry = $registry;
    }

    /**
     * Check subscription status - runs only once per request
     */
    public function check(): bool {
        // Prevent multiple checks on same request
        if (self::$checked) {
            return self::$result;
        }
        self::$checked = true;

        $db = $this->registry->get('db');
        $customer = $this->registry->get('customer');
        $session = $this->registry->get('session');
        $config = $this->registry->get('config');
        $request = $this->registry->get('request');
        $response = $this->registry->get('response');
        $url = $this->registry->get('url');

        // Skip if not logged in
        if (!$customer->isLogged()) {
            self::$result = true;
            return true;
        }
        
        // Skip if no route
        $route = isset($request->get['route']) ? $request->get['route'] : '';
        if (empty($route)) {
            self::$result = true;
            return true;
        }
        
        // Skip AJAX requests
        if (!empty($request->server['HTTP_X_REQUESTED_WITH'])) {
            self::$result = true;
            return true;
        }

        // Skip public routes
        $public_routes = [
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

        foreach ($public_routes as $public) {
            if (strpos($route, $public) === 0) {
                self::$result = true;
                return true;
            }
        }

        // Get role IDs
        $role_ids = $this->getRoleIds($db, $session);
        
        $customer_group_id = $customer->getGroupId();
        
        // Shareholders skip
        if ($customer_group_id == $role_ids['shareholder']) {
            self::$result = true;
            return true;
        }

        // Only check for Manager and Accountant
        if ($customer_group_id != $role_ids['manager'] && $customer_group_id != $role_ids['accountant']) {
            self::$result = true;
            return true;
        }

        // Get project
        $project = $this->getProject($db, $session, $customer);
        
        if (!$project) {
            self::$result = true;
            return true;
        }

        // Check subscription
        $is_active = $this->checkSubscription($db, $session, $project['project_id']);
        
        if (!$is_active) {
            $response->redirect($url->link('account/pms_subscription_expired', 'language=' . $config->get('config_language')));
            return false;
        }

        self::$result = true;
        return true;
    }

    private function getRoleIds($db, $session): array {
        $cache_key = 'pms_role_ids';
        
        if (isset($session->data['pms_cache'][$cache_key])) {
            $cached = $session->data['pms_cache'][$cache_key];
            if ($cached['expires'] > time()) {
                return [
                    'manager' => $cached['manager_group_id'],
                    'accountant' => $cached['accountant_group_id'],
                    'shareholder' => $cached['shareholder_group_id']
                ];
            }
        }

        $query = $db->query("
            SELECT 
                MAX(CASE WHEN setting_key = 'manager_group_id' THEN setting_value END) as manager_group_id,
                MAX(CASE WHEN setting_key = 'accountant_group_id' THEN setting_value END) as accountant_group_id,
                MAX(CASE WHEN setting_key = 'shareholder_group_id' THEN setting_value END) as shareholder_group_id
            FROM `" . DB_PREFIX . "pms_settings`
            WHERE setting_key IN ('manager_group_id', 'accountant_group_id', 'shareholder_group_id')
        ");

        $result = $query->row;

        $role_ids = [
            'manager' => (int)($result['manager_group_id'] ?? 2),
            'accountant' => (int)($result['accountant_group_id'] ?? 3),
            'shareholder' => (int)($result['shareholder_group_id'] ?? 4)
        ];

        $session->data['pms_cache'][$cache_key] = [
            'manager_group_id' => $role_ids['manager'],
            'accountant_group_id' => $role_ids['accountant'],
            'shareholder_group_id' => $role_ids['shareholder'],
            'expires' => time() + 3600
        ];

        return $role_ids;
    }

    private function getProject($db, $session, $customer): array {
        $customer_id = $customer->getId();
        $cache_key = 'pms_project_' . $customer_id;
        
        if (isset($session->data['pms_cache'][$cache_key])) {
            $cached = $session->data['pms_cache'][$cache_key];
            if ($cached['expires'] > time()) {
                return $cached['data'];
            }
        }

        $query = $db->query("
            SELECT p.* 
            FROM `" . DB_PREFIX . "pms_project` p
            INNER JOIN `" . DB_PREFIX . "customer` c ON c.project_id = p.project_id
            WHERE c.customer_id = '" . (int)$customer_id . "'
            AND p.is_deleted = 0
        ");

        $project = $query->row;

        $session->data['pms_cache'][$cache_key] = [
            'data' => $project,
            'expires' => time() + 300
        ];

        return $project;
    }

    private function checkSubscription($db, $session, int $project_id): bool {
        $cache_key = 'pms_sub_active_' . $project_id;
        
        if (isset($session->data['pms_cache'][$cache_key])) {
            $cached = $session->data['pms_cache'][$cache_key];
            if ($cached['expires'] > time()) {
                return $cached['status'];
            }
        }

        $query = $db->query("
            SELECT COUNT(*) as total
            FROM `" . DB_PREFIX . "pms_project_subscription`
            WHERE project_id = '" . (int)$project_id . "'
            AND status = 'active'
            AND end_date > NOW()
        ");

        $is_active = $query->row['total'] > 0;

        $session->data['pms_cache'][$cache_key] = [
            'status' => $is_active,
            'expires' => time() + 300
        ];

        return $is_active;
    }
}
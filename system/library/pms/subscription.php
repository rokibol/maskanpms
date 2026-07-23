<?php
namespace Opencart\System\Library\Pms;

class Subscription {
    private $registry;
    private $db;
    private $session;
    private $config;
    private $cache_time = 300; // 5 minutes

    public function __construct($registry) {
        $this->registry = $registry;
        $this->db = $registry->get('db');
        $this->session = $registry->get('session');
        $this->config = $registry->get('config');
    }

    /**
     * Check if project subscription is active with caching
     * @param int $project_id
     * @return bool
     */
    public function isActive(int $project_id): bool {
        $cache_key = 'pms_sub_active_' . $project_id;
        
        // Check session cache first
        if (isset($this->session->data['pms_cache'][$cache_key])) {
            $cached = $this->session->data['pms_cache'][$cache_key];
            if ($cached['expires'] > time()) {
                return $cached['status'];
            }
        }
        
        // Single optimized query
        $query = $this->db->query("
            SELECT 
                CASE 
                    WHEN admin_override = 1 AND override_until > NOW() THEN 1
                    WHEN status = 'active' AND end_date > NOW() THEN 1
                    ELSE 0
                END as is_active
            FROM `" . DB_PREFIX . "pms_project_subscription`
            WHERE project_id = '" . (int)$project_id . "'
            ORDER BY subscription_id DESC
            LIMIT 1
        ");
        
        $is_active = (int)($query->row['is_active'] ?? 0) === 1;
        
        // Store in session cache
        $this->session->data['pms_cache'][$cache_key] = [
            'status' => $is_active,
            'expires' => time() + $this->cache_time
        ];
        
        return $is_active;
    }

    /**
     * Get subscription details with caching
     * @param int $project_id
     * @return array
     */
    public function getDetails(int $project_id): array {
        $cache_key = 'pms_sub_details_' . $project_id;
        
        // Check session cache
        if (isset($this->session->data['pms_cache'][$cache_key])) {
            $cached = $this->session->data['pms_cache'][$cache_key];
            if ($cached['expires'] > time()) {
                return $cached['data'];
            }
        }
        
        // Single optimized query
        $query = $this->db->query("
            SELECT * 
            FROM `" . DB_PREFIX . "pms_project_subscription`
            WHERE project_id = '" . (int)$project_id . "'
            ORDER BY subscription_id DESC
            LIMIT 1
        ");
        
        $details = $query->row;
        
        // Store in session cache
        $this->session->data['pms_cache'][$cache_key] = [
            'data' => $details,
            'expires' => time() + $this->cache_time
        ];
        
        return $details;
    }

    /**
     * Get expiry days with caching
     * @param int $project_id
     * @return int
     */
    public function getExpiryDays(int $project_id): int {
        $cache_key = 'pms_sub_days_' . $project_id;
        
        if (isset($this->session->data['pms_cache'][$cache_key])) {
            $cached = $this->session->data['pms_cache'][$cache_key];
            if ($cached['expires'] > time()) {
                return $cached['days'];
            }
        }
        
        $query = $this->db->query("
            SELECT 
                CASE 
                    WHEN admin_override = 1 AND override_until > NOW() THEN DATEDIFF(override_until, NOW())
                    WHEN status = 'active' AND end_date > NOW() THEN DATEDIFF(end_date, NOW())
                    ELSE 0
                END as days_remaining
            FROM `" . DB_PREFIX . "pms_project_subscription`
            WHERE project_id = '" . (int)$project_id . "'
            ORDER BY subscription_id DESC
            LIMIT 1
        ");
        
        $days = (int)($query->row['days_remaining'] ?? 0);
        
        $this->session->data['pms_cache'][$cache_key] = [
            'days' => $days,
            'expires' => time() + $this->cache_time
        ];
        
        return $days;
    }

    /**
     * Clear cache for a project
     * @param int $project_id
     */
    public function clearCache(int $project_id): void {
        $keys = [
            'pms_sub_active_' . $project_id,
            'pms_sub_details_' . $project_id,
            'pms_sub_days_' . $project_id
        ];
        
        foreach ($keys as $key) {
            unset($this->session->data['pms_cache'][$key]);
        }
    }

    /**
     * Clear all cache
     */
    public function clearAllCache(): void {
        unset($this->session->data['pms_cache']);
    }
}
<?php
namespace Opencart\Catalog\Helper;

class PmsSubscription extends \Opencart\System\Engine\Controller {

    private static $subscription_cache = [];
    private static $cache_time = 300; // 5 minutes cache

    /**
     * Check subscription status with caching
     * @param int $project_id
     * @return bool
     */
    public function isSubscriptionActive(int $project_id): bool {
        $cache_key = 'pms_subscription_active_' . $project_id;
        
        // Check session cache first (fastest)
        if (isset($this->session->data['pms_subscription_cache'][$cache_key])) {
            $cached = $this->session->data['pms_subscription_cache'][$cache_key];
            if ($cached['expires'] > time()) {
                return $cached['status'];
            }
        }
        
        // Query database using optimized method
        $this->load->model('pms/project_subscription');
        $is_active = $this->model_pms_project_subscription->isSubscriptionActiveOptimized($project_id);
        
        // Store in session cache
        $this->session->data['pms_subscription_cache'][$cache_key] = [
            'status' => $is_active,
            'expires' => time() + self::$cache_time
        ];
        
        return $is_active;
    }

    /**
     * Get subscription details with caching
     * @param int $project_id
     * @return array
     */
    public function getSubscriptionDetails(int $project_id): array {
        $cache_key = 'pms_subscription_details_' . $project_id;
        
        // Check session cache
        if (isset($this->session->data['pms_subscription_cache'][$cache_key])) {
            $cached = $this->session->data['pms_subscription_cache'][$cache_key];
            if ($cached['expires'] > time()) {
                return $cached['data'];
            }
        }
        
        // Query database
        $this->load->model('pms/project_subscription');
        $details = $this->model_pms_project_subscription->getSubscriptionByProject($project_id);
        
        // Store in cache
        $this->session->data['pms_subscription_cache'][$cache_key] = [
            'data' => $details,
            'expires' => time() + self::$cache_time
        ];
        
        return $details;
    }

    /**
     * Clear subscription cache
     * @param int $project_id
     */
    public function clearCache(int $project_id): void {
        $keys = [
            'pms_subscription_active_' . $project_id,
            'pms_subscription_details_' . $project_id,
            'pms_subscription_days_' . $project_id
        ];
        
        foreach ($keys as $key) {
            unset($this->session->data['pms_subscription_cache'][$key]);
        }
    }
}
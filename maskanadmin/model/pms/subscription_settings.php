<?php
namespace Opencart\Admin\Model\Pms;

class SubscriptionSettings extends \Opencart\System\Engine\Model {

    /**
     * Get setting value
     */
    public function getSetting(string $key): string {
        $query = $this->db->query("
            SELECT setting_value 
            FROM `" . DB_PREFIX . "pms_subscription_settings` 
            WHERE setting_key = '" . $this->db->escape($key) . "'
        ");
        
        return $query->row['setting_value'] ?? '';
    }

    /**
     * Update setting
     */
    public function updateSetting(string $key, string $value): void {
        $exists = $this->db->query("
            SELECT COUNT(*) as total 
            FROM `" . DB_PREFIX . "pms_subscription_settings` 
            WHERE setting_key = '" . $this->db->escape($key) . "'
        ");
        
        if ($exists->row['total'] > 0) {
            $this->db->query("
                UPDATE `" . DB_PREFIX . "pms_subscription_settings` 
                SET setting_value = '" . $this->db->escape($value) . "'
                WHERE setting_key = '" . $this->db->escape($key) . "'
            ");
        } else {
            $this->db->query("
                INSERT INTO `" . DB_PREFIX . "pms_subscription_settings` 
                SET setting_key = '" . $this->db->escape($key) . "',
                    setting_value = '" . $this->db->escape($value) . "'
            ");
        }
    }

    /**
     * Get all project subscriptions with filters
     */
    public function getSubscriptions(array $data = []): array {
        $sql = "
            SELECT s.*, 
                   p.name as project_name,
                   p.status as project_status
            FROM `" . DB_PREFIX . "pms_project_subscription` s
            LEFT JOIN `" . DB_PREFIX . "pms_project` p ON s.project_id = p.project_id
            WHERE 1=1
        ";
        
        if (!empty($data['filter_project'])) {
            $sql .= " AND p.name LIKE '%" . $this->db->escape($data['filter_project']) . "%'";
        }
        
        if (!empty($data['filter_status'])) {
            $sql .= " AND s.status = '" . $this->db->escape($data['filter_status']) . "'";
        }
        
        if (!empty($data['filter_override'])) {
            if ($data['filter_override'] == 'yes') {
                $sql .= " AND s.admin_override = 1";
            } else {
                $sql .= " AND s.admin_override = 0";
            }
        }
        
        if (!empty($data['filter_date_from'])) {
            $sql .= " AND DATE(s.start_date) >= '" . $this->db->escape($data['filter_date_from']) . "'";
        }
        
        if (!empty($data['filter_date_to'])) {
            $sql .= " AND DATE(s.end_date) <= '" . $this->db->escape($data['filter_date_to']) . "'";
        }
        
        $sort_data = ['p.name', 's.duration_months', 's.total_amount', 's.start_date', 's.end_date', 's.status'];
        
        if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
            $sql .= " ORDER BY " . $data['sort'];
        } else {
            $sql .= " ORDER BY s.created_at ";
        }
        
        if (isset($data['order']) && ($data['order'] == 'ASC')) {
            $sql .= " ASC";
        } else {
            $sql .= " DESC";
        }
        
        if (isset($data['start']) || isset($data['limit'])) {
            if ($data['start'] < 0) {
                $data['start'] = 0;
            }
            if ($data['limit'] < 1) {
                $data['limit'] = 20;
            }
            $sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
        }
        
        $query = $this->db->query($sql);
        
        return $query->rows;
    }

    /**
     * Get total subscriptions count
     */
    public function getTotalSubscriptions(array $data = []): int {
        $sql = "
            SELECT COUNT(*) as total
            FROM `" . DB_PREFIX . "pms_project_subscription` s
            LEFT JOIN `" . DB_PREFIX . "pms_project` p ON s.project_id = p.project_id
            WHERE 1=1
        ";
        
        if (!empty($data['filter_project'])) {
            $sql .= " AND p.name LIKE '%" . $this->db->escape($data['filter_project']) . "%'";
        }
        
        if (!empty($data['filter_status'])) {
            $sql .= " AND s.status = '" . $this->db->escape($data['filter_status']) . "'";
        }
        
        $query = $this->db->query($sql);
        
        return (int)$query->row['total'];
    }

    /**
     * Get single subscription by ID
     */
    public function getSubscription(int $subscription_id): array {
        $query = $this->db->query("
            SELECT s.*, p.name as project_name
            FROM `" . DB_PREFIX . "pms_project_subscription` s
            LEFT JOIN `" . DB_PREFIX . "pms_project` p ON s.project_id = p.project_id
            WHERE s.subscription_id = '" . (int)$subscription_id . "'
        ");
        
        return $query->row;
    }

    /**
     * Add admin override - EXTENDS the existing subscription
     */
    public function addAdminOverride(int $subscription_id, int $months, string $reason): void {
        // Get current subscription
        $subscription = $this->getSubscription($subscription_id);
        
        // Calculate new end date (extend from current end_date or today)
        $current_end = $subscription['end_date'];
        $today = date('Y-m-d');
        
        // If subscription is already expired, start from today
        if (strtotime($current_end) < strtotime($today)) {
            $new_end_date = date('Y-m-d', strtotime('+' . $months . ' months'));
        } else {
            // Extend from current end date
            $new_end_date = date('Y-m-d', strtotime($current_end . ' + ' . $months . ' months'));
        }
        
        $this->db->query("
            UPDATE `" . DB_PREFIX . "pms_project_subscription` 
            SET `admin_override` = 1,
                `override_until` = '" . $this->db->escape($new_end_date) . "',
                `override_reason` = '" . $this->db->escape($reason) . "',
                `end_date` = '" . $this->db->escape($new_end_date) . "',
                `status` = 'active'
            WHERE `subscription_id` = '" . (int)$subscription_id . "'
        ");
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

    /**
     * Remove admin override - Restores original subscription dates
     */
    public function removeAdminOverride(int $subscription_id): void {
        // Get the subscription
        $subscription = $this->getSubscription($subscription_id);
        
        // Calculate original end date (based on original duration)
        $original_end_date = date('Y-m-d', strtotime($subscription['start_date'] . ' + ' . $subscription['duration_months'] . ' months'));
        
        $this->db->query("
            UPDATE `" . DB_PREFIX . "pms_project_subscription` 
            SET `admin_override` = 0,
                `override_until` = NULL,
                `override_reason` = NULL,
                `end_date` = '" . $this->db->escape($original_end_date) . "',
                `status` = CASE 
                    WHEN '" . $this->db->escape($original_end_date) . "' < NOW() THEN 'expired' 
                    ELSE 'active' 
                END
            WHERE `subscription_id` = '" . (int)$subscription_id . "'
        ");
    }

    /**
     * Get all projects for dropdown
     */
    public function getAllProjects(): array {
        $query = $this->db->query("
            SELECT project_id, name 
            FROM `" . DB_PREFIX . "pms_project` 
            WHERE is_deleted = 0 
            ORDER BY name ASC
        ");
        
        return $query->rows;
    }
}
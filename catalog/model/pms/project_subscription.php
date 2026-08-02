<?php
namespace Opencart\Catalog\Model\Pms;

class ProjectSubscription extends \Opencart\System\Engine\Model {
    /**
     * Check if project subscription is active with caching
     */
    public function isSubscriptionActive(int $project_id): bool {
        $cache_key = 'sub_active_' . $project_id;
        
        // Check session cache
        if (isset($this->session->data['pms_model_cache'][$cache_key])) {
            $cached = $this->session->data['pms_model_cache'][$cache_key];
            if ($cached['expires'] > time()) {
                return $cached['status'];
            }
        }
        
        // Check for admin override
        $override = $this->db->query("
            SELECT COUNT(*) as total
            FROM `" . DB_PREFIX . "pms_project_subscription`
            WHERE project_id = '" . (int)$project_id . "'
            AND admin_override = 1
            AND override_until > NOW()
        ");

        if ($override->row['total'] > 0) {
            $status = true;
        } else {
            // Check normal subscription
            $query = $this->db->query("
                SELECT COUNT(*) as total
                FROM `" . DB_PREFIX . "pms_project_subscription`
                WHERE project_id = '" . (int)$project_id . "'
                AND status = 'active'
                AND end_date > NOW()
                AND admin_override = 0
            ");
            $status = $query->row['total'] > 0;
        }
        
        // Store in cache
        $this->session->data['pms_model_cache'][$cache_key] = [
            'status' => $status,
            'expires' => time() + 300 // 5 minutes
        ];
        
        return $status;
    }

    /**
     * Get project subscription settings (project-specific)
     */
    public function getProjectSubscriptionSettings(int $project_id): array {
        $cache_key = 'project_settings_' . $project_id;
        
        if (isset($this->session->data['pms_model_cache'][$cache_key])) {
            $cached = $this->session->data['pms_model_cache'][$cache_key];
            if ($cached['expires'] > time()) {
                return $cached['data'];
            }
        }
        
        $query = $this->db->query("
            SELECT 
                subscription_rate_per_apartment,
                subscription_discount_type,
                subscription_discount_value,
                subscription_discount_reason
            FROM `" . DB_PREFIX . "pms_project`
            WHERE project_id = '" . (int)$project_id . "'
        ");
        
        $data = $query->row;
        
        $this->session->data['pms_model_cache'][$cache_key] = [
            'data' => $data,
            'expires' => time() + 300
        ];
        
        return $data;
    }
    
    /**
     * Calculate subscription amount based on project settings
     */
    public function calculateSubscriptionAmount(int $project_id, int $duration_months): array {
        // Get project settings
        $settings = $this->getProjectSubscriptionSettings($project_id);
        $apartment_count = $this->getProjectApartmentCount($project_id);
        
        $rate = (float)$settings['subscription_rate_per_apartment'];
        $discount_type = $settings['subscription_discount_type'];
        $discount_value = (float)$settings['subscription_discount_value'];
        $discount_reason = $settings['subscription_discount_reason'];
        
        $base_amount = $apartment_count * $rate * $duration_months;
        
        // Calculate discount
        $discount_amount = 0;
        if ($discount_type != 'none' && $discount_value > 0) {
            if ($discount_type == 'percentage') {
                $discount_amount = ($base_amount * $discount_value) / 100;
                $discount_reason = $discount_value . '% discount applied';
            } elseif ($discount_type == 'fixed') {
                // Fixed discount per apartment per month
                $discount_amount = $apartment_count * $discount_value * $duration_months;
                $discount_reason = $discount_value . ' BDT discount per apartment applied';
            }
            
            // Ensure discount doesn't exceed base amount
            if ($discount_amount > $base_amount) {
                $discount_amount = $base_amount;
            }
        }
        
        $final_amount = $base_amount - $discount_amount;
        
        return [
            'base_amount' => $base_amount,
            'discount_amount' => $discount_amount,
            'discount_type' => $discount_type,
            'discount_value' => $discount_value,
            'discount_reason' => $discount_reason,
            'final_amount' => $final_amount,
            'rate_per_apartment' => $rate,
            'apartment_count' => $apartment_count,
            'duration_months' => $duration_months,
            'monthly_base' => $apartment_count * $rate,
            'monthly_after_discount' => $duration_months > 0 ? $final_amount / $duration_months : 0
        ];
    }

    /**
     * Activate subscription based on project settings
     */
    public function activateSubscription(int $project_id, int $duration_months): array {
        $result = ['success' => false, 'message' => ''];

        $apartment_count = $this->getProjectApartmentCount($project_id);

        if ($apartment_count == 0) {
            $result['message'] = 'No apartments found. Please add apartments first.';
            return $result;
        }

        $calculation = $this->calculateSubscriptionAmount($project_id, $duration_months);

        $start_date = date('Y-m-d');
        $end_date = date('Y-m-d', strtotime('+' . $duration_months . ' months'));

        // Check for existing active subscription
        $existing = $this->db->query("
            SELECT * FROM `" . DB_PREFIX . "pms_project_subscription`
            WHERE project_id = '" . (int)$project_id . "'
            AND status = 'active'
            AND admin_override = 0
            ORDER BY subscription_id DESC
            LIMIT 1
        ");

        if ($existing->num_rows > 0) {
            $current_end = $existing->row['end_date'];
            $end_date = date('Y-m-d', strtotime($current_end . ' + ' . $duration_months . ' months'));
            
            $this->db->query("
                UPDATE `" . DB_PREFIX . "pms_project_subscription` 
                SET `status` = 'superseded' 
                WHERE `subscription_id` = '" . (int)$existing->row['subscription_id'] . "'
            ");
        }

        // Insert new subscription
        $this->db->query("
            INSERT INTO `" . DB_PREFIX . "pms_project_subscription` 
            SET `project_id` = '" . (int)$project_id . "',
                `duration_months` = '" . (int)$duration_months . "',
                `apartment_count` = '" . (int)$apartment_count . "',
                `total_amount` = '" . (float)$calculation['final_amount'] . "',
                `discount_type` = '" . $this->db->escape($calculation['discount_type']) . "',
                `discount_value` = '" . (float)$calculation['discount_value'] . "',
                `discount_reason` = '" . $this->db->escape($calculation['discount_reason']) . "',
                `start_date` = '" . $this->db->escape($start_date) . "',
                `end_date` = '" . $this->db->escape($end_date) . "',
                `status` = 'active',
                `admin_override` = 0
        ");

        $result['success'] = true;
        $result['subscription_id'] = $this->db->getLastId();
        $result['calculation'] = $calculation;
        $result['end_date'] = $end_date;
        $result['apartment_count'] = $apartment_count;

        return $result;
    }

    /**
     * Get apartment count for a project
     */
    public function getProjectApartmentCount(int $project_id): int {
        $query = $this->db->query("
            SELECT COUNT(*) as total
            FROM `" . DB_PREFIX . "pms_apartment`
            WHERE project_id = '" . (int)$project_id . "'
            AND is_deleted = 0
            AND is_active = 1
        ");

        if((int)$query->row['total'] == 0) {
            $query_project = $this->db->query("
                SELECT total_shares as total
                FROM `" . DB_PREFIX . "pms_project`
                WHERE project_id = '" . (int)$project_id . "'
                AND is_deleted = 0
                AND is_active = 1
            ");
            return (int)$query_project->row['total'];
        } else {
            return (int)$query->row['total'];
        }
    }

    /**
     * Get subscription by project ID with caching
     */
    public function getSubscriptionByProject(int $project_id): array {
        $cache_key = 'sub_details_' . $project_id;
        
        if (isset($this->session->data['pms_model_cache'][$cache_key])) {
            $cached = $this->session->data['pms_model_cache'][$cache_key];
            if ($cached['expires'] > time()) {
                return $cached['data'];
            }
        }
        
        $query = $this->db->query("
            SELECT s.*, 
                p.name as project_name,
                p.subscription_rate_per_apartment,
                p.subscription_discount_type,
                p.subscription_discount_value,
                p.subscription_discount_reason
            FROM `" . DB_PREFIX . "pms_project_subscription` s
            LEFT JOIN `" . DB_PREFIX . "pms_project` p ON s.project_id = p.project_id
            WHERE s.project_id = '" . (int)$project_id . "'
            ORDER BY s.subscription_id DESC
            LIMIT 1
        ");

        $data = $query->row;
        
        $this->session->data['pms_model_cache'][$cache_key] = [
            'data' => $data,
            'expires' => time() + 300
        ];
        
        return $data;
    }

    /**
     * Check if project subscription is active - OPTIMIZED single query
     * @param int $project_id
     * @return bool
     */
    public function isSubscriptionActiveOptimized(int $project_id): bool {
        $cache_key = 'sub_active_' . $project_id;
        
        // Check session cache
        if (isset($this->session->data['pms_model_cache'][$cache_key])) {
            $cached = $this->session->data['pms_model_cache'][$cache_key];
            if ($cached['expires'] > time()) {
                return $cached['status'];
            }
        }
        
        // Check for admin override
        $override = $this->db->query("
            SELECT COUNT(*) as total
            FROM `" . DB_PREFIX . "pms_project_subscription`
            WHERE project_id = '" . (int)$project_id . "'
            AND admin_override = 1
            AND override_until > NOW()
        ");

        if ($override->row['total'] > 0) {
            $status = true;
        } else {
            // Check normal subscription
            $query = $this->db->query("
                SELECT COUNT(*) as total
                FROM `" . DB_PREFIX . "pms_project_subscription`
                WHERE project_id = '" . (int)$project_id . "'
                AND status = 'active'
                AND end_date > NOW()
                AND admin_override = 0
            ");
            $status = $query->row['total'] > 0;
        }
        
        // Store in cache
        $this->session->data['pms_model_cache'][$cache_key] = [
            'status' => $status,
            'expires' => time() + 300 // 5 minutes
        ];
        
        return $status;
    }

    /**
     * Get expiry days
     */
    public function getExpiryDays(int $project_id): int {
        // Check for admin override
        $override = $this->db->query("
            SELECT DATEDIFF(override_until, NOW()) as days
            FROM `" . DB_PREFIX . "pms_project_subscription`
            WHERE project_id = '" . (int)$project_id . "'
            AND admin_override = 1
            AND override_until > NOW()
            ORDER BY subscription_id DESC
            LIMIT 1
        ");

        if ($override->num_rows > 0 && $override->row['days'] > 0) {
            return (int)$override->row['days'];
        }

        // Check normal subscription
        $query = $this->db->query("
            SELECT DATEDIFF(end_date, NOW()) as days
            FROM `" . DB_PREFIX . "pms_project_subscription`
            WHERE project_id = '" . (int)$project_id . "'
            AND status = 'active'
            AND end_date > NOW()
            AND admin_override = 0
            ORDER BY subscription_id DESC
            LIMIT 1
        ");

        return (int)($query->row['days'] ?? 0);
    }

    /**
     * Get subscription calculation for display
     */
    public function getSubscriptionCalculationDisplay(int $project_id): array {
        $settings = $this->getProjectSubscriptionSettings($project_id);
        $apartment_count = $this->getProjectApartmentCount($project_id);
        
        $rate = (float)$settings['subscription_rate_per_apartment'];
        $discount_type = $settings['subscription_discount_type'];
        $discount_value = (float)$settings['subscription_discount_value'];
        $discount_reason = $settings['subscription_discount_reason'] ?? '';
        
        $base_monthly = $apartment_count * $rate;
        $discount_amount = 0;
        $discount_per_apartment = 0;
        $final_monthly = $base_monthly;
        
        if ($discount_type != 'none' && $discount_value > 0) {
            if ($discount_type == 'percentage') {
                $discount_amount = ($base_monthly * $discount_value) / 100;
                $discount_per_apartment = ($rate * $discount_value) / 100;
            } elseif ($discount_type == 'fixed') {
                $discount_amount = $apartment_count * $discount_value;
                $discount_per_apartment = $discount_value;
            }
            
            $final_monthly = $base_monthly - $discount_amount;
        }
        
        return [
            'apartment_count' => $apartment_count,
            'rate_per_apartment' => $rate,
            'base_monthly' => $base_monthly,
            'discount_amount' => $discount_amount,
            'discount_per_apartment' => $discount_per_apartment,
            'discount_reason' => $discount_reason,
            'discount_type' => $discount_type,
            'discount_value' => $discount_value,
            'final_monthly' => $final_monthly,
            'has_discount' => $discount_amount > 0,
            'effective_rate' => $final_monthly / $apartment_count
        ];
    }
}
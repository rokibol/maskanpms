<?php
namespace Opencart\Catalog\Model\Pms;

class ProjectSubscription extends \Opencart\System\Engine\Model {

    /**
     * Get subscription by project ID
     */
    public function getSubscriptionByProject(int $project_id): array {
        $query = $this->db->query("
            SELECT ps.*, sp.name as plan_name, sp.price_monthly, sp.price_yearly,
                   sp.max_projects, sp.max_shareholders, sp.max_apartments, sp.features
            FROM `" . DB_PREFIX . "pms_project_subscription` ps
            LEFT JOIN `" . DB_PREFIX . "pms_subscription_plan` sp ON ps.plan_id = sp.plan_id
            WHERE ps.project_id = '" . (int)$project_id . "'
            ORDER BY ps.subscription_id DESC
            LIMIT 1
        ");
        
        return $query->row;
    }

    /**
     * Activate subscription for a project
     */
    public function activateSubscription(int $project_id, int $plan_id, string $billing_type = 'monthly'): bool {
        $plan = $this->db->query("
            SELECT * FROM `" . DB_PREFIX . "pms_subscription_plan`
            WHERE plan_id = '" . (int)$plan_id . "'
        ");
        
        if (!$plan->num_rows) {
            return false;
        }
        
        $price = ($billing_type == 'yearly') ? $plan->row['price_yearly'] : $plan->row['price_monthly'];
        $start_date = date('Y-m-d');
        $end_date = ($billing_type == 'yearly') 
            ? date('Y-m-d', strtotime('+1 year')) 
            : date('Y-m-d', strtotime('+1 month'));
        
        // Deactivate old subscriptions
        $this->db->query("
            UPDATE `" . DB_PREFIX . "pms_project_subscription` 
            SET `status` = 'expired' 
            WHERE `project_id` = '" . (int)$project_id . "'
        ");
        
        // Insert new subscription
        $this->db->query("
            INSERT INTO `" . DB_PREFIX . "pms_project_subscription` 
            SET `project_id` = '" . (int)$project_id . "',
                `plan_id` = '" . (int)$plan_id . "',
                `status` = 'active',
                `start_date` = '" . $this->db->escape($start_date) . "',
                `end_date` = '" . $this->db->escape($end_date) . "',
                `next_payment_date` = '" . $this->db->escape($end_date) . "',
                `auto_renew` = 1
        ");
        
        return true;
    }

    /**
     * Check if project subscription is active
     */
    public function isSubscriptionActive(int $project_id): bool {
        $query = $this->db->query("
            SELECT COUNT(*) as total
            FROM `" . DB_PREFIX . "pms_project_subscription`
            WHERE project_id = '" . (int)$project_id . "'
            AND status = 'active'
            AND end_date > NOW()
        ");
        
        return $query->row['total'] > 0;
    }

    /**
     * Get available subscription plans
     */
    public function getAvailablePlans(): array {
        $query = $this->db->query("
            SELECT * 
            FROM `" . DB_PREFIX . "pms_subscription_plan` 
            WHERE is_active = 1 
            ORDER BY sort_order ASC
        ");
        
        return $query->rows;
    }

    /**
     * Get subscription expiry days
     */
    public function getExpiryDays(int $project_id): int {
        $query = $this->db->query("
            SELECT DATEDIFF(end_date, NOW()) as days
            FROM `" . DB_PREFIX . "pms_project_subscription`
            WHERE project_id = '" . (int)$project_id . "'
            AND status = 'active'
            ORDER BY subscription_id DESC
            LIMIT 1
        ");
        
        return (int)($query->row['days'] ?? 0);
    }
}
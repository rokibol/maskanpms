<?php
namespace Opencart\Admin\Model\Pms;

class ProjectSubscription extends \Opencart\System\Engine\Model {

    //private $monthly_rate = 1000; // 1000 BDT per month

    /**
     * Get monthly rate from admin settings
     */
    public function getMonthlyRate(): int {
        $query = $this->db->query("
            SELECT setting_value 
            FROM `" . DB_PREFIX . "pms_subscription_settings` 
            WHERE setting_key = 'monthly_rate'
        ");
        
        return (int)($query->row['setting_value'] ?? 1000);
    }

    /**
     * Activate or Extend subscription for a project
     */
    public function activateSubscription(int $project_id, int $duration_months): array {
        $result = ['success' => false, 'message' => ''];
        
        $monthly_rate = $this->getMonthlyRate();
        $total_amount = $duration_months * $monthly_rate;
        $start_date = date('Y-m-d');
        $new_end_date = '';
        
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
            // EXTEND: Add to existing end date
            $current_end = $existing->row['end_date'];
            $new_end_date = date('Y-m-d', strtotime($current_end . ' + ' . $duration_months . ' months'));
            
            // Create new subscription record (for history)
            $this->db->query("
                INSERT INTO `" . DB_PREFIX . "pms_project_subscription` 
                SET `project_id` = '" . (int)$project_id . "',
                    `duration_months` = '" . (int)$duration_months . "',
                    `total_amount` = '" . (float)$total_amount . "',
                    `start_date` = '" . $this->db->escape($start_date) . "',
                    `end_date` = '" . $this->db->escape($new_end_date) . "',
                    `status` = 'active',
                    `admin_override` = 0
            ");
            
            // Mark old subscription as 'superseded' (optional)
            $this->db->query("
                UPDATE `" . DB_PREFIX . "pms_project_subscription` 
                SET `status` = 'superseded' 
                WHERE `subscription_id` = '" . (int)$existing->row['subscription_id'] . "'
            ");
            
            $result['message'] = 'Subscription extended! New expiry: ' . date('d M Y', strtotime($new_end_date));
            
        } else {
            // NEW: Create first subscription
            $end_date = date('Y-m-d', strtotime('+' . $duration_months . ' months'));
            
            $this->db->query("
                INSERT INTO `" . DB_PREFIX . "pms_project_subscription` 
                SET `project_id` = '" . (int)$project_id . "',
                    `duration_months` = '" . (int)$duration_months . "',
                    `total_amount` = '" . (float)$total_amount . "',
                    `start_date` = '" . $this->db->escape($start_date) . "',
                    `end_date` = '" . $this->db->escape($end_date) . "',
                    `status` = 'active',
                    `admin_override` = 0
            ");
            
            $result['message'] = 'Subscription activated! Expires on: ' . date('d M Y', strtotime($end_date));
        }
        
        $result['success'] = true;
        $result['subscription_id'] = $this->db->getLastId();
        $result['total_amount'] = $total_amount;
        $result['end_date'] = $new_end_date;
        
        return $result;
    }

    /**
     * Get subscription by project ID
     */
    public function getSubscriptionByProject(int $project_id): array {
        $query = $this->db->query("
            SELECT * 
            FROM `" . DB_PREFIX . "pms_project_subscription`
            WHERE project_id = '" . (int)$project_id . "'
            AND status = 'active'
            ORDER BY subscription_id DESC
            LIMIT 1
        ");
        
        return $query->row;
    }

    /**
     * Check if project subscription is active (respects admin override)
     */
    public function isSubscriptionActive(int $project_id): bool {
        // First check for admin override (extends subscription)
        $override = $this->db->query("
            SELECT COUNT(*) as total
            FROM `" . DB_PREFIX . "pms_project_subscription`
            WHERE project_id = '" . (int)$project_id . "'
            AND admin_override = 1
            AND override_until > NOW()
        ");
        
        if ($override->row['total'] > 0) {
            return true;
        }
        
        // Check normal subscription
        $query = $this->db->query("
            SELECT COUNT(*) as total
            FROM `" . DB_PREFIX . "pms_project_subscription`
            WHERE project_id = '" . (int)$project_id . "'
            AND status = 'active'
            AND end_date > NOW()
            AND admin_override = 0
        ");
        
        return $query->row['total'] > 0;
    }

    /**
     * Get expiry days (shows override expiry if active)
     */
    public function getExpiryDays(int $project_id): int {
        // First check for admin override
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
     * Get subscription end date (shows override end if active)
     */
    public function getSubscriptionEndDate(int $project_id): string {
        // Check for admin override
        $override = $this->db->query("
            SELECT override_until as end_date
            FROM `" . DB_PREFIX . "pms_project_subscription`
            WHERE project_id = '" . (int)$project_id . "'
            AND admin_override = 1
            AND override_until > NOW()
            ORDER BY subscription_id DESC
            LIMIT 1
        ");
        
        if ($override->num_rows > 0) {
            return $override->row['end_date'];
        }
        
        // Check normal subscription
        $query = $this->db->query("
            SELECT end_date
            FROM `" . DB_PREFIX . "pms_project_subscription`
            WHERE project_id = '" . (int)$project_id . "'
            AND status = 'active'
            AND admin_override = 0
            ORDER BY subscription_id DESC
            LIMIT 1
        ");
        
        return $query->row['end_date'] ?? date('Y-m-d');
    }

    /**
     * Calculate amount for duration
     */
    public function calculateAmount(int $duration_months): float {
        return $duration_months * $this->getMonthlyRate();
    }

    /**
     * Get duration options
     */
    public function getDurationOptions(): array {
        return [
            1 => '1 Month',
            3 => '3 Months',
            6 => '6 Months',
            12 => '12 Months (1 Year)',
            24 => '24 Months (2 Years)',
            36 => '36 Months (3 Years)'
        ];
    }

    /**
     * Generate unique payment ID
     */
    public function generatePaymentUniqueId(): string {
        $prefix = 'SUB';
        $date = date('Ymd');
        $random = strtoupper(substr(uniqid(), -6));
        $unique_id = $prefix . $date . $random;
        
        // Check if exists
        $check = $this->db->query("
            SELECT COUNT(*) as total 
            FROM `" . DB_PREFIX . "pms_subscription_payment`
            WHERE payment_unique_id = '" . $this->db->escape($unique_id) . "'
        ");
        
        if ($check->row['total'] > 0) {
            return $this->generatePaymentUniqueId();
        }
        
        return $unique_id;
    }

    /**
     * Create payment request
     */
    public function createPaymentRequest(int $project_id, int $duration_months, float $total_amount): array {
        $unique_id = $this->generatePaymentUniqueId();
        $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        $this->db->query("
            INSERT INTO `" . DB_PREFIX . "pms_subscription_payment` 
            SET `project_id` = '" . (int)$project_id . "',
                `payment_unique_id` = '" . $this->db->escape($unique_id) . "',
                `duration_months` = '" . (int)$duration_months . "',
                `total_amount` = '" . (float)$total_amount . "',
                `status` = 'pending',
                `expires_at` = '" . $this->db->escape($expires_at) . "'
        ");
        
        return [
            'payment_request_id' => $this->db->getLastId(),
            'payment_unique_id' => $unique_id,
            'total_amount' => $total_amount,
            'duration_months' => $duration_months
        ];
    }

    /**
     * Get payment request by unique ID
     */
    public function getPaymentRequest(string $unique_id): array {
        $query = $this->db->query("
            SELECT * 
            FROM `" . DB_PREFIX . "pms_subscription_payment`
            WHERE payment_unique_id = '" . $this->db->escape($unique_id) . "'
            AND status = 'pending'
            AND expires_at > NOW()
        ");
        
        return $query->row;
    }

    /**
     * Update payment request after successful payment
     */
    public function completePaymentRequest(string $unique_id, string $transaction_id, string $payment_method, array $response_data): bool {
        $this->db->query("
            UPDATE `" . DB_PREFIX . "pms_subscription_payment` 
            SET `status` = 'completed',
                `transaction_id` = '" . $this->db->escape($transaction_id) . "',
                `payment_method` = '" . $this->db->escape($payment_method) . "',
                `response_data` = '" . $this->db->escape(json_encode($response_data)) . "',
                `completed_at` = NOW()
            WHERE `payment_unique_id` = '" . $this->db->escape($unique_id) . "'
        ");
        
        // Get payment details
        $payment = $this->db->query("
            SELECT * 
            FROM `" . DB_PREFIX . "pms_subscription_payment`
            WHERE payment_unique_id = '" . $this->db->escape($unique_id) . "'
        ");
        
        if ($payment->num_rows > 0) {
            // Activate subscription
            $result = $this->activateSubscription($payment->row['project_id'], $payment->row['duration_months']);
            /*
            $result['success'] = true;
            $result['subscription_id'] = $this->db->getLastId();
            $result['total_amount'] = $total_amount;
            $result['end_date'] = $new_end_date;
            */
            if($result['success']) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get payment history for a project
     */
    public function getPaymentHistory(int $project_id, int $limit = 10): array {
        $query = $this->db->query("
            SELECT * 
            FROM `" . DB_PREFIX . "pms_subscription_payment`
            WHERE project_id = '" . (int)$project_id . "'
            ORDER BY created_at DESC
            LIMIT " . (int)$limit . "
        ");
        
        return $query->rows;
    }

    /**
     * Check if project subscription is active - OPTIMIZED single query
     * @param int $project_id
     * @return bool
     */
    public function isSubscriptionActiveOptimized(int $project_id): bool {
        // Single query to check both override and normal subscription
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
        
        return (int)($query->row['is_active'] ?? 0) === 1;
    }

    /**
     * Get subscription with single optimized query
     */
    public function getSubscriptionByProjectOptimized(int $project_id): array {
        $query = $this->db->query("
            SELECT s.*, sp.name as plan_name, sp.price_monthly, sp.price_yearly
            FROM `" . DB_PREFIX . "pms_project_subscription` s
            LEFT JOIN `" . DB_PREFIX . "pms_subscription_plan` sp ON s.plan_id = sp.plan_id
            WHERE s.project_id = '" . (int)$project_id . "'
            ORDER BY s.subscription_id DESC
            LIMIT 1
        ");
        
        return $query->row;
    }
}
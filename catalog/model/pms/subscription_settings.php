<?php
namespace Opencart\Catalog\Model\Pms;

class SubscriptionSettings extends \Opencart\System\Engine\Model {

    private $default_settings = [
        'apartment_rate' => '100',
        'discount_percentage' => '0',
        'discount_fixed' => '0',
        'discount_min_apartments' => '10',
        'discount_type' => 'none',
        'trial_days' => '30'
    ];

    /**
     * Get setting value
     */
    public function getSetting(string $key): string {
        $query = $this->db->query("
            SELECT setting_value 
            FROM `" . DB_PREFIX . "pms_subscription_settings` 
            WHERE setting_key = '" . $this->db->escape($key) . "'
        ");
        
        return $query->row['setting_value'] ?? ($this->default_settings[$key] ?? '');
    }

    /**
     * Get all settings
     */
    public function getAllSettings(): array {
        $query = $this->db->query("
            SELECT setting_key, setting_value 
            FROM `" . DB_PREFIX . "pms_subscription_settings`
        ");
        
        $settings = [];
        foreach ($query->rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        // Merge with defaults
        foreach ($this->default_settings as $key => $value) {
            if (!isset($settings[$key])) {
                $settings[$key] = $value;
            }
        }
        
        return $settings;
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
     * Calculate subscription amount based on apartment count
     */
    public function calculateSubscriptionAmount(int $apartment_count, int $duration_months, array $discount = []): array {
        $rate = (float)$this->getSetting('apartment_rate');
        $base_amount = $apartment_count * $rate * $duration_months;
        
        $discount_type = $discount['type'] ?? $this->getSetting('discount_type');
        $discount_value = 0;
        $discount_reason = '';
        $discount_amount = 0;
        $final_amount = $base_amount;
        
        // Apply discount based on settings
        if ($discount_type != 'none') {
            if ($discount_type == 'percentage') {
                $discount_percentage = (float)($discount['value'] ?? $this->getSetting('discount_percentage'));
                $discount_amount = ($base_amount * $discount_percentage) / 100;
                $discount_value = $discount_percentage;
                $discount_reason = $discount_percentage . '% discount applied';
            } elseif ($discount_type == 'fixed') {
                $discount_fixed = (float)($discount['value'] ?? $this->getSetting('discount_fixed'));
                $discount_amount = min($discount_fixed, $base_amount);
                $discount_value = $discount_fixed;
                $discount_reason = 'Fixed discount of ৳' . $discount_fixed . ' applied';
            }
        }
        
        // Apply manual discount if provided
        if (!empty($discount['manual_type']) && !empty($discount['manual_value'])) {
            if ($discount['manual_type'] == 'percentage') {
                $manual_discount = ($base_amount * $discount['manual_value']) / 100;
                if ($manual_discount > $discount_amount) {
                    $discount_amount = $manual_discount;
                    $discount_value = $discount['manual_value'];
                    $discount_reason = 'Manual ' . $discount['manual_value'] . '% discount applied';
                }
            } elseif ($discount['manual_type'] == 'fixed') {
                if ($discount['manual_value'] > $discount_amount) {
                    $discount_amount = min($discount['manual_value'], $base_amount);
                    $discount_value = $discount['manual_value'];
                    $discount_reason = 'Manual fixed discount of ৳' . $discount['manual_value'] . ' applied';
                }
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
            'per_apartment_monthly' => $rate,
            'apartment_count' => $apartment_count,
            'duration_months' => $duration_months,
            'monthly_after_discount' => $duration_months > 0 ? $final_amount / $duration_months : 0
        ];
    }
}
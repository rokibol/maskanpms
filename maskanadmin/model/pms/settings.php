<?php
namespace Opencart\Admin\Model\Pms;

class Settings extends \Opencart\System\Engine\Model {

    /**
     * Get all settings
     */
    public function getSettings(): array {
        $query = $this->db->query("
            SELECT setting_key, setting_value 
            FROM `" . DB_PREFIX . "pms_settings`
            ORDER BY setting_key ASC
        ");
        
        $settings = [];
        foreach ($query->rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        return $settings;
    }

    /**
     * Get a single setting
     */
    public function getSetting(string $key): string {
        $query = $this->db->query("
            SELECT setting_value 
            FROM `" . DB_PREFIX . "pms_settings`
            WHERE setting_key = '" . $this->db->escape($key) . "'
        ");
        
        return $query->row['setting_value'] ?? '';
    }

    /**
     * Update a setting
     */
    public function updateSetting(string $key, string $value): void {
        $exists = $this->db->query("
            SELECT COUNT(*) as total 
            FROM `" . DB_PREFIX . "pms_settings` 
            WHERE setting_key = '" . $this->db->escape($key) . "'
        ");
        
        if ($exists->row['total'] > 0) {
            $this->db->query("
                UPDATE `" . DB_PREFIX . "pms_settings` 
                SET setting_value = '" . $this->db->escape($value) . "',
                    updated_at = NOW()
                WHERE setting_key = '" . $this->db->escape($key) . "'
            ");
        } else {
            $this->db->query("
                INSERT INTO `" . DB_PREFIX . "pms_settings` 
                SET setting_key = '" . $this->db->escape($key) . "',
                    setting_value = '" . $this->db->escape($value) . "'
            ");
        }
    }

    /**
     * Update multiple settings at once
     */
    public function updateSettings(array $data): void {
        foreach ($data as $key => $value) {
            $this->updateSetting($key, $value);
        }
    }

    /**
     * Clear cache for catalog settings
     */
    public function clearCache(): void {
        // This will be used by catalog model to clear cache
        // We'll handle it via session
    }
}
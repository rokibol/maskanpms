<?php
namespace Opencart\Admin\Model\Pms;

class Settings extends \Opencart\System\Engine\Model {

    /**
     * Get all settings
     */
    public function getSettings(): array {
        $query = $this->db->query("
            SELECT `key`, value 
            FROM `" . DB_PREFIX . "setting`
            ORDER BY `key` ASC
        ");
        
        $settings = [];
        foreach ($query->rows as $row) {
            $settings[$row['key']] = $row['value'];
        }
        
        return $settings;
    }

    /**
     * Get a single setting
     */
    public function getSetting(string $key): string {
        $query = $this->db->query("
            SELECT value 
            FROM `" . DB_PREFIX . "setting`
            WHERE key = '" . $this->db->escape($key) . "'
        ");
        
        return $query->row['value'] ?? '';
    }

    /**
     * Update a setting
     */
    public function updateSetting(string $key, string $value): void {
        $exists = $this->db->query("
            SELECT COUNT(*) as total 
            FROM `" . DB_PREFIX . "setting` 
            WHERE key = '" . $this->db->escape($key) . "'
        ");
        
        if ($exists->row['total'] > 0) {
            $this->db->query("
                UPDATE `" . DB_PREFIX . "setting` 
                SET value = '" . $this->db->escape($value) . "',
                    updated_at = NOW()
                WHERE key = '" . $this->db->escape($key) . "'
            ");
        } else {
            $this->db->query("
                INSERT INTO `" . DB_PREFIX . "setting` 
                SET key = '" . $this->db->escape($key) . "',
                    value = '" . $this->db->escape($value) . "'
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
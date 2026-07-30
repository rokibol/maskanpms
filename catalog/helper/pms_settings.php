<?php
namespace Opencart\Catalog\Helper;

class PmsSettings extends \Opencart\System\Engine\Controller {

    /**
     * Get a setting value
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
     * Get all settings
     */
    public function getSettings(): array {
        $query = $this->db->query("
            SELECT setting_key, setting_value 
            FROM `" . DB_PREFIX . "pms_settings`
        ");
        
        $settings = [];
        foreach ($query->rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        return $settings;
    }

    /**
     * Get manager group ID
     */
    public function getManagerGroupId(): int {
        return (int)$this->getSetting('manager_group_id');
    }

    /**
     * Get accountant group ID
     */
    public function getAccountantGroupId(): int {
        return (int)$this->getSetting('accountant_group_id');
    }

    /**
     * Get shareholder group ID
     */
    public function getShareholderGroupId(): int {
        return (int)$this->getSetting('shareholder_group_id');
    }
}
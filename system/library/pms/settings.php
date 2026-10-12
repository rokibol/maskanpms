<?php
namespace Opencart\System\Library\Pms;

class Settings {
    private $registry;
    private $db;
    private $session;
    private $settings_cache = [];
    private $cache_time = 3600; // 1 hour cache

    public function __construct($registry) {
        $this->registry = $registry;
        $this->db = $registry->get('db');
        $this->session = $registry->get('session');
    }

    /**
     * Get all settings with caching
     */
    public function getAll(): array {
        // Check if already loaded in memory
        if (!empty($this->settings_cache)) {
            return $this->settings_cache;
        }

        // Check session cache
        $cache_key = 'pms_settings_all';
        if (isset($this->session->data['pms_settings_cache'][$cache_key])) {
            $cached = $this->session->data['pms_settings_cache'][$cache_key];
            if ($cached['expires'] > time()) {
                $this->settings_cache = $cached['data'];
                return $this->settings_cache;
            }
        }

        // Query database (only once)
        $query = $this->db->query("
            SELECT `key`, value 
            FROM `" . DB_PREFIX . "setting`
        ");

        $settings = [];
        foreach ($query->rows as $row) {
            $settings[$row['key']] = $row['value'];
        }

        // Store in memory cache
        $this->settings_cache = $settings;

        // Store in session cache
        $this->session->data['pms_settings_cache'][$cache_key] = [
            'data' => $settings,
            'expires' => time() + $this->cache_time
        ];

        return $settings;
    }

    /**
     * Get a single setting value with caching
     */
    public function get(string $key): string {
        $settings = $this->getAll();
        return $settings[$key] ?? '';
    }

    /**
     * Get manager group ID
     */
    public function getManagerGroupId(): int {
        return (int)$this->get('manager_group_id');
    }

    /**
     * Get accountant group ID
     */
    public function getAccountantGroupId(): int {
        return (int)$this->get('accountant_group_id');
    }

    /**
     * Get shareholder group ID
     */
    public function getShareholderGroupId(): int {
        return (int)$this->get('shareholder_group_id');
    }

    /**
     * Clear settings cache
     */
    public function clearCache(): void {
        unset($this->session->data['pms_settings_cache']);
        $this->settings_cache = [];
    }
}
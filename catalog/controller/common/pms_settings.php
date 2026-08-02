<?php
namespace Opencart\Catalog\Controller\Common;

class PmsSettings extends \Opencart\System\Engine\Controller {

    private static $settings = null;

    /**
     * Get a setting value (static method for global access)
     */
    public static function get(string $key): string {
        // Check if already loaded
        if (self::$settings !== null) {
            return self::$settings[$key] ?? '';
        }

        // Load from cache or database
        $settings = self::loadSettings();
        return $settings[$key] ?? '';
    }

    /**
     * Load settings with caching
     */
    private static function loadSettings(): array {
        // Check session
        if (isset($_SESSION['pms_settings_cache'])) {
            $cached = $_SESSION['pms_settings_cache'];
            if ($cached['expires'] > time()) {
                self::$settings = $cached['data'];
                return self::$settings;
            }
        }

        // Load from database
        $registry = new \Opencart\System\Engine\Registry();
        $db = $registry->get('db');
        $query = $db->query("
            SELECT setting_key, setting_value 
            FROM `" . DB_PREFIX . "pms_settings`
        ");

        $settings = [];
        foreach ($query->rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        self::$settings = $settings;
        $_SESSION['pms_settings_cache'] = [
            'data' => $settings,
            'expires' => time() + 3600
        ];

        return $settings;
    }

    /**
     * Clear settings cache
     */
    public static function clearCache(): void {
        self::$settings = null;
        unset($_SESSION['pms_settings_cache']);
    }
}
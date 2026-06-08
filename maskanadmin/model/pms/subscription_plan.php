<?php
namespace Opencart\Admin\Model\Pms;

class SubscriptionPlan extends \Opencart\System\Engine\Model {

    /**
     * Add a new subscription plan
     */
    public function addPlan(array $data): int {
        $this->db->query("
            INSERT INTO `" . DB_PREFIX . "pms_subscription_plan` 
            SET `name` = '" . $this->db->escape($data['name']) . "',
                `slug` = '" . $this->db->escape($data['slug']) . "',
                `description` = '" . $this->db->escape($data['description']) . "',
                `price_monthly` = '" . (float)$data['price_monthly'] . "',
                `price_yearly` = '" . (float)$data['price_yearly'] . "',
                `max_projects` = '" . (int)$data['max_projects'] . "',
                `max_shareholders` = '" . (int)$data['max_shareholders'] . "',
                `max_apartments` = '" . (int)$data['max_apartments'] . "',
                `features` = '" . $this->db->escape($data['features']) . "',
                `is_active` = '" . (int)$data['is_active'] . "',
                `sort_order` = '" . (int)$data['sort_order'] . "'
        ");
        
        return $this->db->getLastId();
    }

    /**
     * Update subscription plan
     */
    public function updatePlan(int $plan_id, array $data): void {
        $this->db->query("
            UPDATE `" . DB_PREFIX . "pms_subscription_plan` 
            SET `name` = '" . $this->db->escape($data['name']) . "',
                `slug` = '" . $this->db->escape($data['slug']) . "',
                `description` = '" . $this->db->escape($data['description']) . "',
                `price_monthly` = '" . (float)$data['price_monthly'] . "',
                `price_yearly` = '" . (float)$data['price_yearly'] . "',
                `max_projects` = '" . (int)$data['max_projects'] . "',
                `max_shareholders` = '" . (int)$data['max_shareholders'] . "',
                `max_apartments` = '" . (int)$data['max_apartments'] . "',
                `features` = '" . $this->db->escape($data['features']) . "',
                `is_active` = '" . (int)$data['is_active'] . "',
                `sort_order` = '" . (int)$data['sort_order'] . "'
            WHERE `plan_id` = '" . (int)$plan_id . "'
        ");
    }

    /**
     * Delete subscription plan
     */
    public function deletePlan(int $plan_id): void {
        $this->db->query("
            DELETE FROM `" . DB_PREFIX . "pms_subscription_plan` 
            WHERE `plan_id` = '" . (int)$plan_id . "'
        ");
    }

    /**
     * Get subscription plan by ID
     */
    public function getPlan(int $plan_id): array {
        $query = $this->db->query("
            SELECT * 
            FROM `" . DB_PREFIX . "pms_subscription_plan` 
            WHERE `plan_id` = '" . (int)$plan_id . "'
        ");
        
        return $query->row;
    }

    /**
     * Get all subscription plans
     */
    public function getPlans(array $data = []): array {
        $sql = "
            SELECT * 
            FROM `" . DB_PREFIX . "pms_subscription_plan` 
            WHERE 1=1
        ";
        
        if (!empty($data['filter_name'])) {
            $sql .= " AND `name` LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
        }
        
        if (isset($data['filter_is_active']) && $data['filter_is_active'] !== '') {
            $sql .= " AND `is_active` = '" . (int)$data['filter_is_active'] . "'";
        }
        
        $sort_data = ['name', 'price_monthly', 'price_yearly', 'sort_order', 'is_active'];
        
        if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
            $sql .= " ORDER BY " . $data['sort'];
        } else {
            $sql .= " ORDER BY sort_order ASC";
        }
        
        if (isset($data['order']) && ($data['order'] == 'DESC')) {
            $sql .= " DESC";
        } else {
            $sql .= " ASC";
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
     * Get total subscription plans count
     */
    public function getTotalPlans(array $data = []): int {
        $sql = "
            SELECT COUNT(*) as total
            FROM `" . DB_PREFIX . "pms_subscription_plan` 
            WHERE 1=1
        ";
        
        if (!empty($data['filter_name'])) {
            $sql .= " AND `name` LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
        }
        
        if (isset($data['filter_is_active']) && $data['filter_is_active'] !== '') {
            $sql .= " AND `is_active` = '" . (int)$data['filter_is_active'] . "'";
        }
        
        $query = $this->db->query($sql);
        
        return (int)$query->row['total'];
    }
}
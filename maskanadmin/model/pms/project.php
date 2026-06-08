<?php
namespace Opencart\Admin\Model\Pms;

class Project extends \Opencart\System\Engine\Model {

    public function addProject(array $data): int {
        $this->db->query("
            INSERT INTO `" . DB_PREFIX . "pms_project` 
            SET 
                `name` = '" . $this->db->escape($data['name']) . "',
                `description` = '" . $this->db->escape($data['description']) . "',
                `address` = '" . $this->db->escape($data['address']) . "',
                `city` = '" . $this->db->escape($data['city']) . "',
                `postal_code` = '" . $this->db->escape($data['postal_code']) . "',
                `country` = '" . $this->db->escape($data['country']) . "',
                `contact_email` = '" . $this->db->escape($data['contact_email']) . "',
                `contact_phone` = '" . $this->db->escape($data['contact_phone']) . "',
                `theme_color` = '" . $this->db->escape($data['theme_color']) . "',
                `store_id` = '" . (int)$data['store_id'] . "',
                `status` = '" . $this->db->escape($data['status']) . "',
                `completion_percentage` = '" . (int)$data['completion_percentage'] . "',
                `total_shares` = '" . (int)$data['total_shares'] . "',
                `share_value` = '" . (float)$data['share_value'] . "',
                `registration_date` = '" . $this->db->escape($data['registration_date']) . "',
                `construction_start_date` = '" . $this->db->escape($data['construction_start_date']) . "',
                `construction_end_date` = '" . $this->db->escape($data['construction_end_date']) . "',
                `handover_date` = '" . $this->db->escape($data['handover_date']) . "',
                `is_active` = '" . (int)$data['is_active'] . "',
                `created_at` = NOW()
        ");

        return $this->db->getLastId();
    }

    public function editProject(int $project_id, array $data): void {
        $this->db->query("
            UPDATE `" . DB_PREFIX . "pms_project` 
            SET 
                `name` = '" . $this->db->escape($data['name']) . "',
                `description` = '" . $this->db->escape($data['description']) . "',
                `address` = '" . $this->db->escape($data['address']) . "',
                `city` = '" . $this->db->escape($data['city']) . "',
                `postal_code` = '" . $this->db->escape($data['postal_code']) . "',
                `country` = '" . $this->db->escape($data['country']) . "',
                `contact_email` = '" . $this->db->escape($data['contact_email']) . "',
                `contact_phone` = '" . $this->db->escape($data['contact_phone']) . "',
                `theme_color` = '" . $this->db->escape($data['theme_color']) . "',
                `store_id` = '" . (int)$data['store_id'] . "',
                `status` = '" . $this->db->escape($data['status']) . "',
                `completion_percentage` = '" . (int)$data['completion_percentage'] . "',
                `total_shares` = '" . (int)$data['total_shares'] . "',
                `share_value` = '" . (float)$data['share_value'] . "',
                `registration_date` = '" . $this->db->escape($data['registration_date']) . "',
                `construction_start_date` = '" . $this->db->escape($data['construction_start_date']) . "',
                `construction_end_date` = '" . $this->db->escape($data['construction_end_date']) . "',
                `handover_date` = '" . $this->db->escape($data['handover_date']) . "',
                `is_active` = '" . (int)$data['is_active'] . "',
                `updated_at` = NOW()
            WHERE `project_id` = '" . (int)$project_id . "'
        ");
    }

    public function deleteProject(int $project_id): void {
        // Soft delete - just mark as deleted
        $this->db->query("
            UPDATE `" . DB_PREFIX . "pms_project` 
            SET `is_deleted` = 1 
            WHERE `project_id` = '" . (int)$project_id . "'
        ");
    }

    public function getProject(int $project_id): array {
        $query = $this->db->query("
            SELECT * 
            FROM `" . DB_PREFIX . "pms_project` 
            WHERE `project_id` = '" . (int)$project_id . "'
            AND `is_deleted` = 0
        ");

        return $query->row;
    }

    public function getProjects(array $data = []): array {
        $sql = "
            SELECT * 
            FROM `" . DB_PREFIX . "pms_project` 
            WHERE `is_deleted` = 0
        ";

        // Filter by name
        if (!empty($data['filter_name'])) {
            $sql .= " AND `name` LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
        }

        // Filter by status
        if (!empty($data['filter_status'])) {
            $sql .= " AND `status` = '" . $this->db->escape($data['filter_status']) . "'";
        }

        // Filter by is_active
        if (isset($data['filter_is_active']) && $data['filter_is_active'] !== '') {
            $sql .= " AND `is_active` = '" . (int)$data['filter_is_active'] . "'";
        }

        $sort_data = [
            'name',
            'status',
            'store_id',
            'is_active',
            'created_at'
        ];

        if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
            $sql .= " ORDER BY `" . $data['sort'] . "`";
        } else {
            $sql .= " ORDER BY `name`";
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

    public function getTotalProjects(array $data = []): int {
        $sql = "
            SELECT COUNT(*) AS total 
            FROM `" . DB_PREFIX . "pms_project` 
            WHERE `is_deleted` = 0
        ";

        // Filter by name
        if (!empty($data['filter_name'])) {
            $sql .= " AND `name` LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
        }

        // Filter by status
        if (!empty($data['filter_status'])) {
            $sql .= " AND `status` = '" . $this->db->escape($data['filter_status']) . "'";
        }

        // Filter by is_active
        if (isset($data['filter_is_active']) && $data['filter_is_active'] !== '') {
            $sql .= " AND `is_active` = '" . (int)$data['filter_is_active'] . "'";
        }

        $query = $this->db->query($sql);

        return (int)$query->row['total'];
    }
}
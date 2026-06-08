<?php
namespace Opencart\Catalog\Model\Pms;

class Apartment extends \Opencart\System\Engine\Model {

    /**
     * Get apartment by ID
     * @param int $apartment_id
     * @return array
     */
    public function getApartment(int $apartment_id): array {
        $query = $this->db->query("
            SELECT a.*, 
                   CONCAT(c.firstname, ' ', c.lastname) as shareholder_name,
                   c.email as shareholder_email,
                   s.shareholder_code
            FROM `" . DB_PREFIX . "pms_apartment` a
            LEFT JOIN `" . DB_PREFIX . "shareholders` s ON a.shareholder_id = s.id
            LEFT JOIN `" . DB_PREFIX . "customer` c ON s.user_id = c.customer_id
            WHERE a.id = '" . (int)$apartment_id . "'
            AND a.is_deleted = 0
        ");
        
        return $query->row;
    }

    /**
     * Get all apartments for a project with filters
     * @param array $data
     * @return array
     */
    public function getApartments(array $data = []): array {
        $sql = "
            SELECT a.*, 
                   CONCAT(c.firstname, ' ', c.lastname) as shareholder_name,
                   s.shareholder_code,
                   CASE 
                       WHEN a.shareholder_id != 0 THEN 'Assigned'
                       ELSE 'Available'
                   END as assignment_status
            FROM `" . DB_PREFIX . "pms_apartment` a
            LEFT JOIN `" . DB_PREFIX . "shareholders` s ON a.shareholder_id = s.id
            LEFT JOIN `" . DB_PREFIX . "customer` c ON s.user_id = c.customer_id
            WHERE a.project_id = '" . (int)$data['project_id'] . "'
            AND a.is_deleted = 0
        ";
        
        // Filter by apartment number
        if (!empty($data['filter_apartment'])) {
            $sql .= " AND a.apartment_number LIKE '%" . $this->db->escape($data['filter_apartment']) . "%'";
        }
        
        // Filter by floor
        if (isset($data['filter_floor']) && $data['filter_floor'] !== '') {
            $sql .= " AND a.floor_number = '" . (int)$data['filter_floor'] . "'";
        }
        
        // Filter by assignment status
        if (isset($data['filter_status']) && $data['filter_status'] !== '') {
            if ($data['filter_status'] == 'assigned') {
                $sql .= " AND a.shareholder_id != 0";
            } elseif ($data['filter_status'] == 'available') {
                $sql .= " AND a.shareholder_id = 0";
            }
        }
        
        // Filter by active status
        if (isset($data['filter_is_active']) && $data['filter_is_active'] !== '') {
            $sql .= " AND a.is_active = '" . (int)$data['filter_is_active'] . "'";
        }
        
        // Filter by bedrooms
        if (isset($data['filter_bedrooms']) && $data['filter_bedrooms'] !== '') {
            $sql .= " AND a.bedrooms = '" . (int)$data['filter_bedrooms'] . "'";
        }
        
        // Filter by shareholder
        if (!empty($data['filter_shareholder'])) {
            $sql .= " AND (c.firstname LIKE '%" . $this->db->escape($data['filter_shareholder']) . "%' 
                        OR c.lastname LIKE '%" . $this->db->escape($data['filter_shareholder']) . "%')";
        }
        
        $sort_data = [
            'a.apartment_number',
            'a.floor_number',
            'a.area_sqft',
            'a.bedrooms',
            'a.fixed_maintenance_fee',
            'a.is_active',
            'assignment_status',
            'a.created_at'
        ];
        
        if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
            $sql .= " ORDER BY " . $data['sort'];
        } else {
            $sql .= " ORDER BY a.floor_number ASC, a.apartment_number ASC";
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
     * Get total apartments count for pagination
     * @param array $data
     * @return int
     */
    public function getTotalApartments(array $data = []): int {
        $sql = "
            SELECT COUNT(*) as total
            FROM `" . DB_PREFIX . "pms_apartment` a
            LEFT JOIN `" . DB_PREFIX . "shareholders` s ON a.shareholder_id = s.id
            LEFT JOIN `" . DB_PREFIX . "customer` c ON s.user_id = c.customer_id
            WHERE a.project_id = '" . (int)$data['project_id'] . "'
            AND a.is_deleted = 0
        ";
        
        if (!empty($data['filter_apartment'])) {
            $sql .= " AND a.apartment_number LIKE '%" . $this->db->escape($data['filter_apartment']) . "%'";
        }
        
        if (isset($data['filter_floor']) && $data['filter_floor'] !== '') {
            $sql .= " AND a.floor_number = '" . (int)$data['filter_floor'] . "'";
        }
        
        if (isset($data['filter_status']) && $data['filter_status'] !== '') {
            if ($data['filter_status'] == 'assigned') {
                $sql .= " AND a.shareholder_id IS NOT NULL";
            } elseif ($data['filter_status'] == 'available') {
                $sql .= " AND a.shareholder_id IS NULL";
            }
        }
        
        if (isset($data['filter_is_active']) && $data['filter_is_active'] !== '') {
            $sql .= " AND a.is_active = '" . (int)$data['filter_is_active'] . "'";
        }
        
        if (isset($data['filter_bedrooms']) && $data['filter_bedrooms'] !== '') {
            $sql .= " AND a.bedrooms = '" . (int)$data['filter_bedrooms'] . "'";
        }
        
        if (!empty($data['filter_shareholder'])) {
            $sql .= " AND (c.firstname LIKE '%" . $this->db->escape($data['filter_shareholder']) . "%' 
                        OR c.lastname LIKE '%" . $this->db->escape($data['filter_shareholder']) . "%')";
        }
        
        $query = $this->db->query($sql);
        
        return (int)$query->row['total'];
    }

    /**
     * Add new apartment
     * @param array $data
     * @return int
     */
    public function addApartment(array $data): int {
        $this->db->query("
            INSERT INTO `" . DB_PREFIX . "pms_apartment` 
            SET `project_id` = '" . (int)$data['project_id'] . "',
                `apartment_number` = '" . $this->db->escape($data['apartment_number']) . "',
                `floor_number` = '" . (int)$data['floor_number'] . "',
                `shareholder_id` = " . ($data['shareholder_id'] ? "'" . (int)$data['shareholder_id'] . "'" : "NULL") . ",
                `fixed_maintenance_fee` = '" . (float)$data['fixed_maintenance_fee'] . "',
                `is_active` = '" . (int)$data['is_active'] . "',
                `area_sqft` = '" . (float)$data['area_sqft'] . "',
                `bedrooms` = '" . (int)$data['bedrooms'] . "',
                `bathrooms` = '" . (int)$data['bathrooms'] . "',
                `balcony` = '" . (int)$data['balcony'] . "',
                `parking_slot` = '" . $this->db->escape($data['parking_slot']) . "',
                `notes` = '" . $this->db->escape($data['notes']) . "',
                `created_at` = NOW()
        ");
        
        return $this->db->getLastId();
    }

    /**
     * Update apartment
     * @param int $apartment_id
     * @param array $data
     */
    public function updateApartment(int $apartment_id, array $data): void {
        $this->db->query("
            UPDATE `" . DB_PREFIX . "pms_apartment` 
            SET `apartment_number` = '" . $this->db->escape($data['apartment_number']) . "',
                `floor_number` = '" . (int)$data['floor_number'] . "',
                `shareholder_id` = " . ($data['shareholder_id'] ? "'" . (int)$data['shareholder_id'] . "'" : "NULL") . ",
                `fixed_maintenance_fee` = '" . (float)$data['fixed_maintenance_fee'] . "',
                `is_active` = '" . (int)$data['is_active'] . "',
                `area_sqft` = '" . (float)$data['area_sqft'] . "',
                `bedrooms` = '" . (int)$data['bedrooms'] . "',
                `bathrooms` = '" . (int)$data['bathrooms'] . "',
                `balcony` = '" . (int)$data['balcony'] . "',
                `parking_slot` = '" . $this->db->escape($data['parking_slot']) . "',
                `notes` = '" . $this->db->escape($data['notes']) . "',
                `updated_at` = NOW()
            WHERE `id` = '" . (int)$apartment_id . "'
        ");
    }

    /**
     * Delete apartment (soft delete)
     * @param int $apartment_id
     */
    public function deleteApartment(int $apartment_id): void {
        $this->db->query("
            UPDATE `" . DB_PREFIX . "pms_apartment` 
            SET `is_deleted` = 1 
            WHERE `id` = '" . (int)$apartment_id . "'
        ");
    }

    /**
     * Assign apartment to shareholder
     * @param int $apartment_id
     * @param int $shareholder_id
     */
    public function assignShareholder(int $apartment_id, int $shareholder_id): void {
        $this->db->query("
            UPDATE `" . DB_PREFIX . "pms_apartment` 
            SET `shareholder_id` = '" . (int)$shareholder_id . "',
                `updated_at` = NOW()
            WHERE `id` = '" . (int)$apartment_id . "'
        ");
    }

    /**
     * Unassign apartment from shareholder
     * @param int $apartment_id
     */
    public function unassignShareholder(int $apartment_id): void {
        $this->db->query("
            UPDATE `" . DB_PREFIX . "pms_apartment` 
            SET `shareholder_id` = NULL,
                `updated_at` = NOW()
            WHERE `id` = '" . (int)$apartment_id . "'
        ");
    }

    /**
     * Get available floors for filter
     * @param int $project_id
     * @return array
     */
    public function getFloors(int $project_id): array {
        $query = $this->db->query("
            SELECT DISTINCT floor_number 
            FROM `" . DB_PREFIX . "pms_apartment`
            WHERE project_id = '" . (int)$project_id . "'
            AND is_deleted = 0
            ORDER BY floor_number ASC
        ");
        
        return $query->rows;
    }

    /**
     * Get bedroom options for filter
     * @return array
     */
    public function getBedroomOptions(): array {
        return [1, 2, 3, 4, 5];
    }

    /**
     * Get statistics for dashboard
     * @param int $project_id
     * @return array
     */
    public function getApartmentStats(int $project_id): array {
        $stats = [];
        
        // Total apartments
        $query = $this->db->query("
            SELECT total_shares as total
            FROM `" . DB_PREFIX . "pms_project`
            WHERE project_id = '" . (int)$project_id . "'
            AND is_deleted = 0
        ");
        $stats['total'] = (int)$query->row['total'];
        
        // Assigned apartments
        $query = $this->db->query("
            SELECT COUNT(*) as total
            FROM `" . DB_PREFIX . "pms_apartment`
            WHERE project_id = '" . (int)$project_id . "'
            AND shareholder_id != 0
            AND is_deleted = 0
        ");
        $stats['assigned'] = (int)$query->row['total'];
        
        // Available apartments
        $stats['available'] = $stats['total'] - $stats['assigned'];
        
        // Active apartments
        $query = $this->db->query("
            SELECT COUNT(*) as total
            FROM `" . DB_PREFIX . "pms_apartment`
            WHERE project_id = '" . (int)$project_id . "'
            AND is_active = 1
            AND is_deleted = 0
        ");
        $stats['active'] = (int)$query->row['total'];
        
        // Total maintenance fee collection per month
        $query = $this->db->query("
            SELECT SUM(fixed_maintenance_fee) as total
            FROM `" . DB_PREFIX . "pms_apartment`
            WHERE project_id = '" . (int)$project_id . "'
            AND shareholder_id IS NOT NULL
            AND is_active = 1
            AND is_deleted = 0
        ");
        $stats['monthly_maintenance'] = (float)($query->row['total'] ?? 0);
        
        return $stats;
    }
}
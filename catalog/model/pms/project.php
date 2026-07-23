<?php
namespace Opencart\Catalog\Model\Pms;

class Project extends \Opencart\System\Engine\Model {

    /**
     * Get project by customer ID
     * @param int $customer_id
     * @return array
     */
    public function getProjectByCustomerId(int $customer_id): array {
        $query = $this->db->query("
            SELECT p.* 
            FROM `" . DB_PREFIX . "pms_project` p
            INNER JOIN `" . DB_PREFIX . "customer` c ON c.project_id = p.project_id
            WHERE c.customer_id = '" . (int)$customer_id . "'
            AND p.is_deleted = 0
            AND p.is_active = 1
        ");
        
        return $query->row;
    }
    
    /**
     * Get project by ID
     * @param int $project_id
     * @return array
     */
    public function getProject(int $project_id): array {
        $query = $this->db->query("
            SELECT * 
            FROM `" . DB_PREFIX . "pms_project` 
            WHERE project_id = '" . (int)$project_id . "'
            AND is_deleted = 0
            AND is_active = 1
        ");
        
        return $query->row;
    }
    
    /**
     * Get project by slug (for URL routing)
     * @param string $slug
     * @return array
     */
    public function getProjectBySlug(string $slug): array {
        $query = $this->db->query("
            SELECT * 
            FROM `" . DB_PREFIX . "pms_project` 
            WHERE slug = '" . $this->db->escape($slug) . "'
            AND is_deleted = 0
            AND is_active = 1
        ");
        
        return $query->row;
    }
    
    /**
     * Check if customer has a project assigned
     * @param int $customer_id
     * @return bool
     */
    public function hasProject(int $customer_id): bool {
        $query = $this->db->query("
            SELECT COUNT(*) as total
            FROM `" . DB_PREFIX . "customer` c
            INNER JOIN `" . DB_PREFIX . "pms_project` p ON c.project_id = p.project_id
            WHERE c.customer_id = '" . (int)$customer_id . "'
            AND p.is_deleted = 0
            AND p.is_active = 1
        ");
        
        return $query->row['total'] > 0;
    }
    
    /**
     * Get all projects (for super admin or multi-project view)
     * @param array $data
     * @return array
     */
    public function getProjects(array $data = []): array {
        $sql = "
            SELECT * 
            FROM `" . DB_PREFIX . "pms_project` 
            WHERE is_deleted = 0 
            AND is_active = 1
        ";
        
        if (!empty($data['filter_name'])) {
            $sql .= " AND name LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
        }
        
        $sort_data = [
            'name',
            'created_at'
        ];
        
        if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
            $sql .= " ORDER BY " . $data['sort'];
        } else {
            $sql .= " ORDER BY name";
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
     * Get project settings (JSON decoded)
     * @param int $project_id
     * @return array
     */
    public function getProjectSettings(int $project_id): array {
        $project = $this->getProject($project_id);
        
        if (!empty($project['settings'])) {
            return json_decode($project['settings'], true);
        }
        
        return [];
    }
    
    /**
     * Get project statistics for dashboard
     * @param int $project_id
     * @return array
     */
    public function getProjectStats(int $project_id): array {
        $stats = [];
        
        // Total shareholders count
        $query = $this->db->query("
            SELECT COUNT(*) as total
            FROM `" . DB_PREFIX . "shareholder` s
            INNER JOIN `" . DB_PREFIX . "customer` c ON s.customer_id = c.customer_id
            WHERE s.project_id = '" . (int)$project_id . "'
            AND s.is_deleted = 0
        ");
        $stats['total_shareholders'] = $query->row['total'];
        
        // Total apartments
        $query = $this->db->query("
            SELECT COUNT(*) as total
            FROM `" . DB_PREFIX . "pms_apartment`
            WHERE project_id = '" . (int)$project_id . "'
            AND is_deleted = 0
        ");
        $stats['total_apartments'] = $query->row['total'];
        
        // Occupied apartments
        $query = $this->db->query("
            SELECT COUNT(*) as total
            FROM `" . DB_PREFIX . "pms_apartment`
            WHERE project_id = '" . (int)$project_id . "'
            AND shareholder_id IS NOT NULL
            AND is_deleted = 0
        ");
        $stats['occupied_apartments'] = $query->row['total'];
        
        // Total expenses
        $query = $this->db->query("
            SELECT SUM(amount) as total
            FROM `" . DB_PREFIX . "expenses`
            WHERE project_id = '" . (int)$project_id . "'
            AND is_deleted = 0
        ");
        $stats['total_expenses'] = $query->row['total'] ?? 0;
        
        // Total collections
        $query = $this->db->query("
            SELECT SUM(amount) as total
            FROM `" . DB_PREFIX . "pms_payment`
            WHERE project_id = '" . (int)$project_id . "'
            AND is_deleted = 0
        ");
        $stats['total_collections'] = $query->row['total'] ?? 0;
        
        // Outstanding dues
        $stats['outstanding_dues'] = $stats['total_expenses'] - $stats['total_collections'];
        
        // Collection percentage
        if ($stats['total_expenses'] > 0) {
            $stats['collection_percentage'] = round(($stats['total_collections'] / $stats['total_expenses']) * 100);
        } else {
            $stats['collection_percentage'] = 0;
        }
        
        return $stats;
    }

    /**
     * Update project status only
     * @param int $project_id
     * @param string $status
     * @return bool
     */
    public function updateProjectStatus(int $project_id, string $status): bool {
        $this->db->query("
            UPDATE `" . DB_PREFIX . "pms_project` 
            SET `status` = '" . $this->db->escape($status) . "',
                `updated_at` = NOW()
            WHERE `project_id` = '" . (int)$project_id . "'
        ");
        
        return true;
    }

    /**
     * Update project maintenance fee only
     * @param int $project_id
     * @param int $maintenance_fee
     * @return bool
     */
    public function updateMaintenanceFee(int $project_id, int $maintenance_fee): bool {
        $this->db->query("
            UPDATE `" . DB_PREFIX . "pms_project` 
            SET `fixed_maintenance_fee` = '" . $this->db->escape($maintenance_fee) . "',
                `updated_at` = NOW()
            WHERE `project_id` = '" . (int)$project_id . "'
        ");
        
        return true;
    }

    /**
     * Update project area square feet only
     * @param int $project_id
     * @param int $area_sq_ft
     * @return bool
     */
    public function updateAreaSqFt(int $project_id, int $area_sq_ft): bool {
        $this->db->query("
            UPDATE `" . DB_PREFIX . "pms_project` 
            SET `area_sq_ft` = '" . $this->db->escape($area_sq_ft) . "',
                `updated_at` = NOW()
            WHERE `project_id` = '" . (int)$project_id . "'
        ");
        
        return true;
    }

    /**
     * Get project statistics for manager view
     * @param int $project_id
     * @return array
     */
    public function getProjectStatisticsForManager(int $project_id): array {
        $stats = [];
        
        // Total shareholders
        $query = $this->db->query("
            SELECT COUNT(*) as total
            FROM `" . DB_PREFIX . "shareholders`
            WHERE project_id = '" . (int)$project_id . "'
            AND is_deleted = 0
        ");
        $stats['total_shareholders'] = (int)$query->row['total'];
        
        // Total apartments
        $query = $this->db->query("
            SELECT COUNT(*) as total,
                SUM(CASE WHEN shareholder_id IS NOT NULL THEN 1 ELSE 0 END) as assigned
            FROM `" . DB_PREFIX . "pms_apartment`
            WHERE project_id = '" . (int)$project_id . "'
            AND is_deleted = 0
        ");
        $stats['total_apartments'] = (int)$query->row['total'];
        $stats['assigned_apartments'] = (int)$query->row['assigned'];
        $stats['available_apartments'] = $stats['total_apartments'] - $stats['assigned_apartments'];
        
        // Total expenses
        $query = $this->db->query("
            SELECT COALESCE(SUM(total_amount), 0) as total
            FROM `" . DB_PREFIX . "expenses`
            WHERE project_id = '" . (int)$project_id . "'
            AND is_deleted = 0
        ");
        $stats['total_expenses'] = (float)$query->row['total'];
        
        // Total collections
        $query = $this->db->query("
            SELECT COALESCE(SUM(amount), 0) as total
            FROM `" . DB_PREFIX . "pms_payment`
            WHERE project_id = '" . (int)$project_id . "'
            AND is_deleted = 0
        ");
        $stats['total_collections'] = (float)$query->row['total'];
        
        // Outstanding dues
        $stats['outstanding_dues'] = $stats['total_expenses'] - $stats['total_collections'];
        
        // Collection percentage
        $stats['collection_percentage'] = $stats['total_expenses'] > 0 
            ? round(($stats['total_collections'] / $stats['total_expenses']) * 100, 2) 
            : 0;
        
        return $stats;
    }
}
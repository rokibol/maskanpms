<?php
namespace Opencart\Catalog\Model\Pms;

class Supplier extends \Opencart\System\Engine\Model {

    /**
     * Add a new supplier
     */
    public function addSupplier(array $data): int {
        $supplier_code = $this->generateSupplierCode();
        
        $this->db->query("
            INSERT INTO `" . DB_PREFIX . "pms_supplier` 
            SET `project_id` = '" . (int)$data['project_id'] . "',
                `supplier_code` = '" . $this->db->escape($supplier_code) . "',
                `supplier_name` = '" . $this->db->escape($data['supplier_name']) . "',
                `company_name` = '" . $this->db->escape($data['company_name']) . "',
                `contact_person` = '" . $this->db->escape($data['contact_person']) . "',
                `email` = '" . $this->db->escape($data['email']) . "',
                `phone` = '" . $this->db->escape($data['phone']) . "',
                `mobile` = '" . $this->db->escape($data['mobile']) . "',
                `address` = '" . $this->db->escape($data['address']) . "',
                `city` = '" . $this->db->escape($data['city']) . "',
                `postal_code` = '" . $this->db->escape($data['postal_code']) . "',
                `country` = '" . $this->db->escape($data['country']) . "',
                `tin_number` = '" . $this->db->escape($data['tin_number']) . "',
                `bin_number` = '" . $this->db->escape($data['bin_number']) . "',
                `bank_name` = '" . $this->db->escape($data['bank_name']) . "',
                `bank_account_no` = '" . $this->db->escape($data['bank_account_no']) . "',
                `payment_terms` = '" . $this->db->escape($data['payment_terms']) . "',
                `notes` = '" . $this->db->escape($data['notes']) . "',
                `status` = '" . $this->db->escape($data['status']) . "',
                `created_by` = '" . (int)$this->customer->getId() . "',
                `created_at` = NOW()
        ");
        
        return $this->db->getLastId();
    }
    
    /**
     * Update supplier
     */
    public function updateSupplier(int $supplier_id, array $data): void {
        $this->db->query("
            UPDATE `" . DB_PREFIX . "pms_supplier` 
            SET `supplier_name` = '" . $this->db->escape($data['supplier_name']) . "',
                `company_name` = '" . $this->db->escape($data['company_name']) . "',
                `contact_person` = '" . $this->db->escape($data['contact_person']) . "',
                `email` = '" . $this->db->escape($data['email']) . "',
                `phone` = '" . $this->db->escape($data['phone']) . "',
                `mobile` = '" . $this->db->escape($data['mobile']) . "',
                `address` = '" . $this->db->escape($data['address']) . "',
                `city` = '" . $this->db->escape($data['city']) . "',
                `postal_code` = '" . $this->db->escape($data['postal_code']) . "',
                `country` = '" . $this->db->escape($data['country']) . "',
                `tin_number` = '" . $this->db->escape($data['tin_number']) . "',
                `bin_number` = '" . $this->db->escape($data['bin_number']) . "',
                `bank_name` = '" . $this->db->escape($data['bank_name']) . "',
                `bank_account_no` = '" . $this->db->escape($data['bank_account_no']) . "',
                `payment_terms` = '" . $this->db->escape($data['payment_terms']) . "',
                `notes` = '" . $this->db->escape($data['notes']) . "',
                `status` = '" . $this->db->escape($data['status']) . "'
            WHERE `supplier_id` = '" . (int)$supplier_id . "'
        ");
    }
    
    /**
     * Get supplier by ID
     */
    public function getSupplier(int $supplier_id): array {
        $query = $this->db->query("
            SELECT * 
            FROM `" . DB_PREFIX . "pms_supplier` 
            WHERE supplier_id = '" . (int)$supplier_id . "'
            AND is_deleted = 0
        ");
        
        return $query->row;
    }
    
    /**
     * Get all suppliers for a project
     */
    public function getSuppliers(array $data = []): array {
        $sql = "
            SELECT * 
            FROM `" . DB_PREFIX . "pms_supplier` 
            WHERE project_id = '" . (int)$data['project_id'] . "'
            AND is_deleted = 0
        ";
        
        if (!empty($data['filter_name'])) {
            $sql .= " AND (supplier_name LIKE '%" . $this->db->escape($data['filter_name']) . "%' 
                        OR company_name LIKE '%" . $this->db->escape($data['filter_name']) . "%')";
        }
        
        if (!empty($data['filter_status'])) {
            $sql .= " AND status = '" . $this->db->escape($data['filter_status']) . "'";
        }
        
        $sort_data = ['supplier_name', 'company_name', 'status', 'created_at'];
        
        if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
            $sql .= " ORDER BY " . $data['sort'];
        } else {
            $sql .= " ORDER BY supplier_name";
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
     * Get total suppliers count
     */
    public function getTotalSuppliers(array $data = []): int {
        $sql = "
            SELECT COUNT(*) as total
            FROM `" . DB_PREFIX . "pms_supplier` 
            WHERE project_id = '" . (int)$data['project_id'] . "'
            AND is_deleted = 0
        ";
        
        if (!empty($data['filter_name'])) {
            $sql .= " AND (supplier_name LIKE '%" . $this->db->escape($data['filter_name']) . "%' 
                        OR company_name LIKE '%" . $this->db->escape($data['filter_name']) . "%')";
        }
        
        if (!empty($data['filter_status'])) {
            $sql .= " AND status = '" . $this->db->escape($data['filter_status']) . "'";
        }
        
        $query = $this->db->query($sql);
        
        return (int)$query->row['total'];
    }
    
    /**
     * Delete supplier (soft delete)
     */
    public function deleteSupplier(int $supplier_id): void {
        $this->db->query("
            UPDATE `" . DB_PREFIX . "pms_supplier` 
            SET `is_deleted` = 1 
            WHERE `supplier_id` = '" . (int)$supplier_id . "'
        ");
    }
    
    /**
     * Generate unique supplier code
     */
    private function generateSupplierCode(): string {
        $prefix = 'SUP';
        $year = date('Y');
        $random = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $code = $prefix . $year . $random;
        
        $check = $this->db->query("
            SELECT COUNT(*) as total 
            FROM `" . DB_PREFIX . "pms_supplier` 
            WHERE supplier_code = '" . $this->db->escape($code) . "'
        ");
        
        if ($check->row['total'] > 0) {
            return $this->generateSupplierCode();
        }
        
        return $code;
    }
    
    /**
     * Get supplier dropdown options
     */
    public function getSupplierOptions(int $project_id): array {
        $query = $this->db->query("
            SELECT supplier_id, supplier_name, company_name 
            FROM `" . DB_PREFIX . "pms_supplier` 
            WHERE project_id = '" . (int)$project_id . "'
            AND status = 'active'
            AND is_deleted = 0
            ORDER BY supplier_name ASC
        ");
        
        return $query->rows;
    }
}
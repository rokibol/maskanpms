<?php
namespace Opencart\Catalog\Model\Pms;

class Shareholder extends \Opencart\System\Engine\Model {

    /**
     * Get shareholder by ID
     */
    public function getShareholder(int $shareholder_id): array {
        $query = $this->db->query("
            SELECT s.*, 
                   c.firstname, c.lastname, c.email, c.status as user_status,
                   p.name as project_name
            FROM `" . DB_PREFIX . "shareholders` s
            INNER JOIN `" . DB_PREFIX . "customer` c ON s.user_id = c.customer_id
            INNER JOIN `" . DB_PREFIX . "pms_project` p ON s.project_id = p.project_id
            WHERE s.id = '" . (int)$shareholder_id . "'
            AND s.is_deleted = 0
        ");
        
        return $query->row;
    }
    
    /**
     * Get all shareholders for a project with filters
     */
    public function getShareholders(array $data = []): array {
        $sql = "
            SELECT s.*, 
                   c.firstname, c.lastname, c.email, c.status as user_status,
                   c.date_added as registered_date,
                   (s.ownership_units / p.total_shares * 100) as ownership_percentage
            FROM `" . DB_PREFIX . "shareholders` s
            INNER JOIN `" . DB_PREFIX . "customer` c ON s.user_id = c.customer_id
            INNER JOIN `" . DB_PREFIX . "pms_project` p ON s.project_id = p.project_id
            WHERE s.project_id = '" . (int)$data['project_id'] . "'
            AND s.is_deleted = 0
        ";
        
        // Filter by name/email
        if (!empty($data['filter_search'])) {
            $sql .= " AND (c.firstname LIKE '%" . $this->db->escape($data['filter_search']) . "%' 
                        OR c.lastname LIKE '%" . $this->db->escape($data['filter_search']) . "%' 
                        OR c.email LIKE '%" . $this->db->escape($data['filter_search']) . "%'
                        OR s.shareholder_code LIKE '%" . $this->db->escape($data['filter_search']) . "%')";
        }
        
        // Filter by status
        if (!empty($data['filter_status'])) {
            $sql .= " AND s.status = '" . $this->db->escape($data['filter_status']) . "'";
        }
        
        // Filter by user status (active/inactive)
        if (isset($data['filter_user_status']) && $data['filter_user_status'] !== '') {
            $sql .= " AND c.status = '" . (int)$data['filter_user_status'] . "'";
        }
        
        // Filter by ownership range
        if (!empty($data['filter_ownership_min'])) {
            $sql .= " HAVING ownership_percentage >= '" . (float)$data['filter_ownership_min'] . "'";
        }
        
        if (!empty($data['filter_ownership_max'])) {
            $sql .= " AND ownership_percentage <= '" . (float)$data['filter_ownership_max'] . "'";
        }
        
        $sort_data = [
            'c.firstname',
            'c.lastname',
            'c.email',
            's.ownership_units',
            'ownership_percentage',
            's.status',
            'c.status',
            's.created_at'
        ];
        
        if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
            $sql .= " ORDER BY " . $data['sort'];
        } else {
            $sql .= " ORDER BY c.firstname";
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
     * Get total shareholders count for pagination
     */
    public function getTotalShareholders(array $data = []): int {
        $sql = "
            SELECT COUNT(*) as total
            FROM `" . DB_PREFIX . "shareholders` s
            INNER JOIN `" . DB_PREFIX . "customer` c ON s.user_id = c.customer_id
            WHERE s.project_id = '" . (int)$data['project_id'] . "'
            AND s.is_deleted = 0
        ";
        
        if (!empty($data['filter_search'])) {
            $sql .= " AND (c.firstname LIKE '%" . $this->db->escape($data['filter_search']) . "%' 
                        OR c.lastname LIKE '%" . $this->db->escape($data['filter_search']) . "%' 
                        OR c.email LIKE '%" . $this->db->escape($data['filter_search']) . "%')";
        }
        
        if (!empty($data['filter_status'])) {
            $sql .= " AND s.status = '" . $this->db->escape($data['filter_status']) . "'";
        }
        
        if (isset($data['filter_user_status']) && $data['filter_user_status'] !== '') {
            $sql .= " AND c.status = '" . (int)$data['filter_user_status'] . "'";
        }
        
        $query = $this->db->query($sql);
        
        return (int)$query->row['total'];
    }
    
    /**
     * Add new shareholder (creates both customer and shareholder record)
     */
    public function addShareholder(array $data): int {
        // Start transaction
        $this->db->query("START TRANSACTION");
        
        try {
            // First, create customer account
            $customer_data = [
                'firstname'      => $data['firstname'],
                'lastname'       => $data['lastname'],
                'email'          => $data['email'],
                'telephone'      => $data['phone'],
                'password'       => password_hash($data['password'], PASSWORD_DEFAULT),
                'customer_group_id' => 3, //Share holder customer group id
                'status'         => 1,
                'newsletter'     => 0,
                'safe'           => 1
            ];
            
            // Insert customer
            $this->db->query("
                INSERT INTO `" . DB_PREFIX . "customer` 
                SET `firstname` = '" . $this->db->escape($customer_data['firstname']) . "',
                    `lastname` = '" . $this->db->escape($customer_data['lastname']) . "',
                    `email` = '" . $this->db->escape($customer_data['email']) . "',
                    `telephone` = '" . $this->db->escape($customer_data['telephone']) . "',
                    `password` = '" . $this->db->escape($customer_data['password']) . "',
                    `customer_group_id` = '" . (int)$customer_data['customer_group_id'] . "',
                    `status` = '" . (int)$customer_data['status'] . "',
                    `newsletter` = '" . (int)$customer_data['newsletter'] . "',
                    `safe` = '" . (int)$customer_data['safe'] . "',
                    `date_added` = NOW()
            ");
            
            $customer_id = $this->db->getLastId();
            
            // Generate unique shareholder code
            $shareholder_code = $this->generateShareholderCode();
            
            // Insert shareholder record
            $this->db->query("
                INSERT INTO `" . DB_PREFIX . "shareholders` 
                SET `shareholder_code` = '" . $this->db->escape($shareholder_code) . "',
                    `user_id` = '" . (int)$customer_id . "',
                    `project_id` = '" . (int)$data['project_id'] . "',
                    `ownership_units` = '" . (int)$data['ownership_units'] . "',
                    `phone` = '" . $this->db->escape($data['phone']) . "',
                    `address` = '" . $this->db->escape($data['address']) . "',
                    `nominee_name` = '" . $this->db->escape($data['nominee_name']) . "',
                    `nominee_relation` = '" . $this->db->escape($data['nominee_relation']) . "',
                    `nominee_phone` = '" . $this->db->escape($data['nominee_phone']) . "',
                    `bank_account_no` = '" . $this->db->escape($data['bank_account_no']) . "',
                    `bank_name` = '" . $this->db->escape($data['bank_name']) . "',
                    `tin_number` = '" . $this->db->escape($data['tin_number']) . "',
                    `notes` = '" . $this->db->escape($data['notes']) . "',
                    `status` = 'active',
                    `created_at` = NOW()
            ");
            
            $shareholder_id = $this->db->getLastId();
            
            // Update customer with project_id
            $this->db->query("
                UPDATE `" . DB_PREFIX . "customer` 
                SET `project_id` = '" . (int)$data['project_id'] . "'
                WHERE `customer_id` = '" . (int)$customer_id . "'
            ");
            
            $this->db->query("COMMIT");
            
            return $shareholder_id;
            
        } catch (\Exception $e) {
            $this->db->query("ROLLBACK");
            throw $e;
        }
    }

    /**
     * Update shareholder information
     */
    public function updateShareholder(int $shareholder_id, array $data): void {
        // Update shareholder record
        $this->db->query("
            UPDATE `" . DB_PREFIX . "shareholders` 
            SET `ownership_units` = '" . (int)$data['ownership_units'] . "',
                `phone` = '" . $this->db->escape($data['phone']) . "',
                `address` = '" . $this->db->escape($data['address']) . "',
                `nominee_name` = '" . $this->db->escape($data['nominee_name']) . "',
                `nominee_relation` = '" . $this->db->escape($data['nominee_relation']) . "',
                `nominee_phone` = '" . $this->db->escape($data['nominee_phone']) . "',
                `bank_account_no` = '" . $this->db->escape($data['bank_account_no']) . "',
                `bank_name` = '" . $this->db->escape($data['bank_name']) . "',
                `tin_number` = '" . $this->db->escape($data['tin_number']) . "',
                `notes` = '" . $this->db->escape($data['notes']) . "'
            WHERE `id` = '" . (int)$shareholder_id . "'
        ");
        
        // Update customer information if needed
        if (!empty($data['firstname']) && !empty($data['lastname'])) {
            // Get user_id from shareholder
            $shareholder = $this->getShareholder($shareholder_id);
            
            if ($shareholder) {
                $this->db->query("
                    UPDATE `" . DB_PREFIX . "customer` 
                    SET `firstname` = '" . $this->db->escape($data['firstname']) . "',
                        `lastname` = '" . $this->db->escape($data['lastname']) . "',
                        `telephone` = '" . $this->db->escape($data['phone']) . "'
                    WHERE `customer_id` = '" . (int)$shareholder['user_id'] . "'
                ");
            }
        }
    }
    
    /**
     * Update shareholder status
     */
    public function updateShareholderStatus(int $shareholder_id, string $status): void {
        $this->db->query("
            UPDATE `" . DB_PREFIX . "shareholders` 
            SET `status` = '" . $this->db->escape($status) . "'
            WHERE `id` = '" . (int)$shareholder_id . "'
        ");
    }
    
    /**
     * Update customer login status (enable/disable)
     */
    public function updateCustomerStatus(int $shareholder_id, int $status): void {
        $shareholder = $this->getShareholder($shareholder_id);
        
        if ($shareholder) {
            $this->db->query("
                UPDATE `" . DB_PREFIX . "customer` 
                SET `status` = '" . (int)$status . "'
                WHERE `customer_id` = '" . (int)$shareholder['user_id'] . "'
            ");
        }
    }
    
    /**
     * Soft delete shareholder
     */
    public function deleteShareholder(int $shareholder_id): void {
        $shareholder = $this->getShareholder($shareholder_id);
        
        if ($shareholder) {
            // Soft delete shareholder
            $this->db->query("
                UPDATE `" . DB_PREFIX . "shareholders` 
                SET `is_deleted` = 1 
                WHERE `id` = '" . (int)$shareholder_id . "'
            ");
            
            // Disable customer account but don't delete
            $this->db->query("
                UPDATE `" . DB_PREFIX . "customer` 
                SET `status` = 0,
                    `project_id` = NULL
                WHERE `customer_id` = '" . (int)$shareholder['user_id'] . "'
            ");
        }
    }
    
    /**
     * Generate unique shareholder code
     */
    private function generateShareholderCode(): string {
        $prefix = 'SH';
        $year = date('Y');
        $random = str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
        $code = $prefix . $year . $random;
        
        // Check if code exists
        $check = $this->db->query("
            SELECT COUNT(*) as total 
            FROM `" . DB_PREFIX . "shareholders` 
            WHERE shareholder_code = '" . $this->db->escape($code) . "'
        ");
        
        if ($check->row['total'] > 0) {
            return $this->generateShareholderCode();
        }
        
        return $code;
    }
    
    /**
     * Get all possible statuses for filter
     */
    public function getStatuses(): array {
        return [
            'active'    => 'Active',
            'inactive'  => 'Inactive',
            'suspended' => 'Suspended'
        ];
    }

    /**
     * Get total due amount for a shareholder
     * Calculates: Total allocated expenses + Bills - Total allocated payments
     * @param int $shareholder_id
     * @return float
     */
    public function getTotalDue(int $shareholder_id): float {
        // Get total allocated payments
        $payment_query = $this->db->query("
            SELECT COALESCE(SUM(pa.allocated_amount), 0) as total_allocated
            FROM `" . DB_PREFIX . "payment_allocation` pa
            WHERE pa.shareholder_id = '" . (int)$shareholder_id . "'
            AND pa.is_deleted = 0
        ");
        
        $total_allocated = (float)$payment_query->row['total_allocated'];
        
        // Get credit balance
        $total_paid = $this->getTotalPaid($shareholder_id);

        // Calculate net due
        $total_due = $total_allocated - $total_paid;

        return max(0, $total_due);
    }

    public function getTotalPaid(int $shareholder_id) {
        // Get total allocated payments
        $payment_query = $this->db->query("
            SELECT COALESCE(SUM(pa.amount), 0) as total_paid
            FROM `" . DB_PREFIX . "pms_payment` pa
            WHERE pa.shareholder_id = '" . (int)$shareholder_id . "'
            AND pa.is_deleted = 0
        ");
        
        $total_paid = (float)$payment_query->row['total_paid'];

        return $total_paid;
    }

    public function getCreditBalance(int $shareholder_id) {
        // Get total allocated payments
        $total_allocated = $this->getTotalAllocatedPayment($shareholder_id);

        $total_paid = $this->getTotalPaid($shareholder_id);

        $credit_balance = $total_paid - $total_allocated;

        return max(0, $credit_balance);
    }

    /**
     * Get total allocated payments for a shareholder
     * @param int $shareholder_id
     * @return float
     */
    public function getTotalAllocatedPayment(int $shareholder_id): float {
        $query = $this->db->query("
            SELECT COALESCE(SUM(pa.allocated_amount), 0) as total_allocated
            FROM `" . DB_PREFIX . "payment_allocation` pa
            WHERE pa.shareholder_id = '" . (int)$shareholder_id . "'
            AND pa.is_deleted = 0
        ");
        
        return (float)$query->row['total_allocated'];
    }

    /**
     * Get total allocated payments for a shareholder
     * @param int $project_id
     * @return float
     */
    public function getProjectTotalAllocatedPayment(int $project_id): float {
        $query = $this->db->query("
            SELECT COALESCE(SUM(pa.allocated_amount), 0) as total_allocated
            FROM `" . DB_PREFIX . "payment_allocation` pa
            WHERE pa.project_id = '" . (int)$project_id . "'
            AND pa.is_deleted = 0
        ");
        
        return (float)$query->row['total_allocated'];
    }

    /**
     * Get all payment allocations for a shareholder
     * @param int $shareholder_id
     * @return array
     */
    public function getPaymentAllocations(int $shareholder_id): array {
        $query = $this->db->query("
            SELECT pa.*, 
                pp.name as phase_name,
                CONCAT(c.firstname, ' ', c.lastname) as created_by_name
            FROM `" . DB_PREFIX . "payment_allocation` pa
            LEFT JOIN `" . DB_PREFIX . "payment_phase` pp ON pa.payment_phase_id = pp.phase_id
            LEFT JOIN `" . DB_PREFIX . "customer` c ON pa.created_by = c.customer_id
            WHERE pa.shareholder_id = '" . (int)$shareholder_id . "'
            AND pa.is_deleted = 0
            ORDER BY pa.created_at DESC
        ");
        
        return $query->rows;
    }

    /**
     * Get all payment records for a shareholder
     * @param int $shareholder_id
     * @return array
     */
    public function getPaymentRecords(int $shareholder_id): array {
        $query = $this->db->query("
            SELECT pa.*, 
                pp.name as phase_name,
                CONCAT(c.firstname, ' ', c.lastname) as created_by_name
            FROM `" . DB_PREFIX . "pms_payment` pa
            LEFT JOIN `" . DB_PREFIX . "payment_phase` pp ON pa.payment_phase_id = pp.phase_id
            LEFT JOIN `" . DB_PREFIX . "customer` c ON pa.recorded_by = c.customer_id
            WHERE pa.shareholder_id = '" . (int)$shareholder_id . "'
            AND pa.is_deleted = 0
            ORDER BY pa.created_at DESC
        ");
        
        return $query->rows;
    }

    public function getPaymentPhases(): array {
        $query = $this->db->query("
            SELECT * FROM `" . DB_PREFIX . "payment_phase`
        ");
        
        return $query->rows;
    }

    /**
     * Add payment allocation
     * @param array $data
     * @return int
     */
    public function addPaymentAllocation(array $data): int {
        $this->db->query("
            INSERT INTO `" . DB_PREFIX . "payment_allocation` 
            SET `shareholder_id` = '" . (int)$data['shareholder_id'] . "',
                `project_id` = '" . (int)$data['project_id'] . "',
                `payment_phase_id` = '" . (int)$data['payment_phase_id'] . "',
                `allocated_amount` = '" . (float)$data['allocated_amount'] . "',
                `notes` = '" . $this->db->escape($data['notes']) . "',
                `created_by` = '" . (int)$this->customer->getId() . "',
                `created_at` = NOW()
        ");
        
        return $this->db->getLastId();
    }

    /**
     * Update payment allocation
     * @param int $allocation_id
     * @param array $data
     */
    public function updatePaymentAllocation(int $allocation_id, array $data): void {
        $this->db->query("
            UPDATE `" . DB_PREFIX . "payment_allocation` 
            SET `payment_phase_id` = '" . (int)$data['payment_phase_id'] . "',
                `allocated_amount` = '" . (float)$data['allocated_amount'] . "',
                `notes` = '" . $this->db->escape($data['notes']) . "'
            WHERE `id` = '" . (int)$allocation_id . "'
        ");
    }

    /**
     * Delete payment allocation (soft delete)
     * @param int $allocation_id
     */
    public function deletePaymentAllocation(int $allocation_id): void {
        $this->db->query("
            UPDATE `" . DB_PREFIX . "payment_allocation` 
            SET `is_deleted` = 1 
            WHERE `id` = '" . (int)$allocation_id . "'
        ");
    }

    /**
     * Get payment summary with allocation breakdown
     * @param int $shareholder_id
     * @return array
     */
    public function getPaymentSummary(int $shareholder_id): array {
        $total_due = $this->getTotalDue($shareholder_id);
        $total_allocated = $this->getTotalAllocatedPayment($shareholder_id);
        $credit_balance = $this->getCreditBalance($shareholder_id);
        $total_paid = $this->getTotalPaid($shareholder_id);
        
        // Get allocation breakdown by phase
        $phase_breakdown = $this->db->query("
            SELECT pp.phase_id, pp.name as phase_name, COALESCE(SUM(pa.allocated_amount), 0) as total
            FROM `" . DB_PREFIX . "payment_allocation` pa
            LEFT JOIN `" . DB_PREFIX . "payment_phase` pp ON pa.payment_phase_id = pp.phase_id
            WHERE pa.shareholder_id = '" . (int)$shareholder_id . "'
            AND pa.is_deleted = 0
            GROUP BY pp.phase_id, pp.name
        ");
        
        return [
            'total_due' => $total_due,
            'total_allocated' => $total_allocated,
            'total_paid' => $total_paid,
            'credit_balance' => $credit_balance,
            'net_due' => 0,
            'over_allocated' => max(0, $total_allocated + $credit_balance - $total_due),
            'phase_breakdown' => $phase_breakdown->rows
        ];
    }

    /**
     * Get shareholder by customer ID
     * @param int $customer_id
     * @return array
     */
    public function getShareholderByCustomerId(int $customer_id): array {
        $query = $this->db->query("
            SELECT s.*, 
                   p.name as project_name,
                   p.total_shares as project_total_shares,
                   p.theme_color,
                   p.status as project_status,
                   c.firstname,
                   c.lastname,
                   CONCAT(c.firstname, ' ', c.lastname) as customer_name,
                   c.email as customer_email
            FROM `" . DB_PREFIX . "shareholders` s
            INNER JOIN `" . DB_PREFIX . "pms_project` p ON s.project_id = p.project_id
            INNER JOIN `" . DB_PREFIX . "customer` c ON s.user_id = c.customer_id
            WHERE s.user_id = '" . (int)$customer_id . "'
            AND s.is_deleted = 0
        ");
        
        return $query->row;
    }

    /**
     * Get ownership percentage for a shareholder
     * @param int $shareholder_id
     * @return float
     */
    public function getOwnershipPercentage(int $shareholder_id): float {
        $shareholder = $this->getShareholder($shareholder_id);
        
        $query = $this->db->query("
            SELECT * 
            FROM `" . DB_PREFIX . "pms_project` 
            WHERE project_id = '" . (int)$shareholder['project_id'] . "'
            AND is_deleted = 0
            AND is_active = 1
        ");
        
        $project = $query->row;

        if (!empty($shareholder) && $project['total_shares'] > 0) {
            return ($shareholder['ownership_units'] / $project['total_shares']) * 100;
        }
        
        return 0;
    }

    /**
     * Get apartments owned by shareholder
     * @param int $shareholder_id
     * @return array
     */
    public function getShareholderApartments(int $project_id, int $shareholder_id): array {
        $query = $this->db->query("
            SELECT * 
            FROM `" . DB_PREFIX . "pms_apartment`
            WHERE project_id = '".(int)$project_id."' AND shareholder_id = '" . (int)$shareholder_id . "'
            AND is_deleted = 0
            AND is_active = 1
            ORDER BY floor_number ASC, apartment_number ASC
        ");
        
        return $query->rows;
    }

    /**
     * Bulk allocate payment to all shareholders
     * @param array $data
     * @return array
     */
    public function bulkAllocatePayment(array $data): array {
        $result = ['success' => true, 'allocated' => 0, 'errors' => []];
        
        $project_id = (int)$data['project_id'];
        $payment_phase_id = (int)$data['payment_phase_id'];
        $allocated_amount = (float)$data['allocated_amount'];
        $notes = $data['notes'] ?? '';
        
        // Get all active shareholders for this project
        $shareholders = $this->db->query("
            SELECT s.id, s.project_id, 
                s.ownership_units, p.total_shares,
                CONCAT(c.firstname, ' ', c.lastname) as shareholder_name
            FROM `" . DB_PREFIX . "shareholders` s
            INNER JOIN `" . DB_PREFIX . "pms_project` p ON s.project_id = p.project_id
            INNER JOIN `" . DB_PREFIX . "customer` c ON s.user_id = c.customer_id
            WHERE s.project_id = '" . (int)$project_id . "'
            AND s.is_deleted = 0
            AND s.status = 'active'
        ");
        
        if ($shareholders->num_rows == 0) {
            $result['success'] = false;
            $result['errors'][] = 'No active shareholders found for this project.';
            return $result;
        }
        
        $total_shareholders = $shareholders->num_rows;
        $per_shareholder_amount = $allocated_amount / $total_shareholders;
        
        // Start transaction
        $this->db->query("START TRANSACTION");
        
        try {
            foreach ($shareholders->rows as $shareholder) {
                // Calculate individual allocation amount
                $individual_amount = $per_shareholder_amount;
                
                // Insert payment allocation record for each shareholder
                $this->db->query("
                    INSERT INTO `" . DB_PREFIX . "payment_allocation` 
                    SET `shareholder_id` = '" . (int)$shareholder['id'] . "',
                        `project_id` = '" . (int)$project_id . "',
                        `payment_phase_id` = '" . (int)$payment_phase_id . "',
                        `allocated_amount` = '" . (float)$individual_amount . "',
                        `notes` = '" . $this->db->escape($notes) . " (Bulk Allocation - All shareholders)',
                        `created_by` = '" . (int)$this->customer->getId() . "',
                        `created_at` = NOW()
                ");
                
                // Also update shareholder's credit balance if this is a payment allocation
                $this->db->query("
                    UPDATE `" . DB_PREFIX . "shareholders` 
                    SET `current_credit_balance` = `current_credit_balance` + '" . (float)$individual_amount . "'
                    WHERE `shareholder_id` = '" . (int)$shareholder['id'] . "'
                ");
                
                $result['allocated']++;
            }
            
            $this->db->query("COMMIT");
            
        } catch (\Exception $e) {
            $this->db->query("ROLLBACK");
            $result['success'] = false;
            $result['errors'][] = $e->getMessage();
        }
        
        return $result;
    }

    /**
     * Bulk allocate payment by percentage to all shareholders
     * @param array $data
     * @return array
     */
    public function bulkAllocatePaymentByPercentage(array $data): array {
        $result = ['success' => true, 'allocated' => [], 'errors' => []];
        
        $project_id = (int)$data['project_id'];
        $payment_phase_id = (int)$data['payment_phase_id'];
        $total_amount = (float)$data['total_amount'];
        $notes = $data['notes'] ?? '';
        
        // Get all active shareholders with their ownership percentage
        $shareholders = $this->db->query("
            SELECT s.id, s.project_id, s.ownership_units, p.total_shares,
                (s.ownership_units / p.total_shares * 100) as ownership_percentage,
                CONCAT(c.firstname, ' ', c.lastname) as shareholder_name
            FROM `" . DB_PREFIX . "shareholders` s
            INNER JOIN `" . DB_PREFIX . "pms_project` p ON s.project_id = p.project_id
            INNER JOIN `" . DB_PREFIX . "customer` c ON s.user_id = c.customer_id
            WHERE s.project_id = '" . (int)$project_id . "'
            AND s.is_deleted = 0
            AND s.status = 'active'
        ");
        
        if ($shareholders->num_rows == 0) {
            $result['success'] = false;
            $result['errors'][] = 'No active shareholders found for this project.';
            return $result;
        }
        
        // Start transaction
        $this->db->query("START TRANSACTION");
        
        try {
            foreach ($shareholders->rows as $shareholder) {
                // Calculate amount based on ownership percentage
                $individual_amount = $total_amount * ($shareholder['ownership_percentage'] / 100);
                
                if ($individual_amount > 0) {
                    // Insert payment allocation record for each shareholder
                    $this->db->query("
                        INSERT INTO `" . DB_PREFIX . "payment_allocation` 
                        SET `shareholder_id` = '" . (int)$shareholder['id'] . "',
                            `project_id` = '" . (int)$project_id . "',
                            `payment_phase_id` = '" . (int)$payment_phase_id . "',
                            `allocated_amount` = '" . (float)$individual_amount . "',
                            `notes` = '" . $this->db->escape($notes) . " (Bulk Allocation - " . round($shareholder['ownership_percentage'], 2) . "%)',
                            `created_by` = '" . (int)$this->customer->getId() . "',
                            `created_at` = NOW()
                    ");
                    
                    // Update shareholder's credit balance
                    $this->db->query("
                        UPDATE `" . DB_PREFIX . "shareholders` 
                        SET `current_credit_balance` = `current_credit_balance` + '" . (float)$individual_amount . "'
                        WHERE `shareholder_id` = '" . (int)$shareholder['id'] . "'
                    ");
                    
                    $result['allocated'][] = [
                        'shareholder_name' => $shareholder['shareholder_name'],
                        'percentage' => round($shareholder['ownership_percentage'], 2),
                        'amount' => $individual_amount
                    ];
                }
            }
            
            $this->db->query("COMMIT");
            
        } catch (\Exception $e) {
            $this->db->query("ROLLBACK");
            $result['success'] = false;
            $result['errors'][] = $e->getMessage();
        }
        
        return $result;
    }

    /**
     * Get allocation summary for a project
     * @param int $project_id
     * @return array
     */
    public function getAllocationSummary(int $project_id): array {
        $summary = [];
        
        // Total allocated amount
        $query = $this->db->query("
            SELECT COALESCE(SUM(allocated_amount), 0) as total
            FROM `" . DB_PREFIX . "payment_allocation`
            WHERE project_id = '" . (int)$project_id . "'
            AND is_deleted = 0
        ");
        $summary['total_allocated'] = (float)$query->row['total'];
        
        // Total shareholders count
        $query = $this->db->query("
            SELECT COUNT(*) as total
            FROM `" . DB_PREFIX . "shareholders`
            WHERE project_id = '" . (int)$project_id . "'
            AND is_deleted = 0
            AND status = 'active'
        ");
        $summary['total_shareholders'] = (int)$query->row['total'];
        
        // Last allocation date
        $query = $this->db->query("
            SELECT MAX(created_at) as last_allocation
            FROM `" . DB_PREFIX . "payment_allocation`
            WHERE project_id = '" . (int)$project_id . "'
            AND is_deleted = 0
        ");
        $summary['last_allocation'] = $query->row['last_allocation'] 
            ? date('d M Y H:i', strtotime($query->row['last_allocation'])) 
            : 'No allocations yet';
        
        // Allocations by phase
        $query = $this->db->query("
            SELECT pp.name as phase_name, COALESCE(SUM(pa.allocated_amount), 0) as total
            FROM `" . DB_PREFIX . "payment_allocation` pa
            LEFT JOIN `" . DB_PREFIX . "payment_phase` pp ON pa.payment_phase_id = pp.phase_id
            WHERE pa.project_id = '" . (int)$project_id . "'
            AND pa.is_deleted = 0
            GROUP BY pp.phase_id, pp.name
        ");
        $summary['allocations_by_phase'] = $query->rows;
        
        return $summary;
    }

    /**
     * Proportional bulk allocation based on a reference shareholder's amount
     * @param array $data
     * @return array
     */
    public function proportionalAllocation(array $data): array {
        $result = ['success' => true, 'allocated' => 0, 'total_amount' => 0, 'errors' => []];
        
        $project_id = (int)$data['project_id'];
        $reference_shareholder_id = (int)$data['reference_shareholder_id'];
        $reference_amount = (float)$data['reference_amount'];
        $payment_phase_id = (int)$data['payment_phase_id'];
        $notes = $data['notes'] ?? '';
        
        // Get the reference shareholder details
        $reference = $this->db->query("
            SELECT s.id, s.ownership_units, p.total_shares,
                (s.ownership_units / p.total_shares * 100) as ownership_percentage
            FROM `" . DB_PREFIX . "shareholders` s
            INNER JOIN `" . DB_PREFIX . "pms_project` p ON s.project_id = p.project_id
            WHERE s.id = '" . (int)$reference_shareholder_id . "'
            AND s.project_id = '" . (int)$project_id . "'
            AND s.is_deleted = 0
        ");
        
        if ($reference->num_rows == 0) {
            $result['success'] = false;
            $result['errors'][] = 'Reference shareholder not found.';
            return $result;
        }
        
        $reference_percentage = (float)$reference->row['ownership_percentage'];
        
        if ($reference_percentage <= 0) {
            $result['success'] = false;
            $result['errors'][] = 'Reference shareholder has 0% ownership.';
            return $result;
        }
        
        // Calculate total amount based on reference shareholder's amount and percentage
        // If reference shareholder has X% and gets Y amount, then:
        // Total Amount = (Y / X) * 100
        $total_amount = ($reference_amount / $reference_percentage) * 100;
        
        // Get all active shareholders for this project
        $shareholders = $this->db->query("
            SELECT s.id, s.project_id, 
                s.ownership_units, p.total_shares,
                (s.ownership_units / p.total_shares * 100) as ownership_percentage,
                CONCAT(c.firstname, ' ', c.lastname) as shareholder_name
            FROM `" . DB_PREFIX . "shareholders` s
            INNER JOIN `" . DB_PREFIX . "pms_project` p ON s.project_id = p.project_id
            INNER JOIN `" . DB_PREFIX . "customer` c ON s.user_id = c.customer_id
            WHERE s.project_id = '" . (int)$project_id . "'
            AND s.is_deleted = 0
            AND s.status = 'active'
        ");
        
        if ($shareholders->num_rows == 0) {
            $result['success'] = false;
            $result['errors'][] = 'No active shareholders found for this project.';
            return $result;
        }
        
        // Start transaction
        $this->db->query("START TRANSACTION");
        
        try {
            $result['allocations'] = [];
            
            foreach ($shareholders->rows as $shareholder) {
                // Calculate individual amount based on ownership percentage
                $individual_amount = $total_amount * ($shareholder['ownership_percentage'] / 100);
                
                if ($individual_amount > 0) {
                    // Insert payment allocation record for each shareholder
                    $this->db->query("
                        INSERT INTO `" . DB_PREFIX . "payment_allocation` 
                        SET `shareholder_id` = '" . (int)$shareholder['id'] . "',
                            `project_id` = '" . (int)$project_id . "',
                            `payment_phase_id` = '" . (int)$payment_phase_id . "',
                            `allocated_amount` = '" . (float)$individual_amount . "',
                            `notes` = '" . $this->db->escape($notes) . " (Proportional allocation based on " . $shareholder['shareholder_name'] . " receiving " . $reference_amount . ")',
                            `created_by` = '" . (int)$this->customer->getId() . "',
                            `created_at` = NOW()
                    ");
                    
                    // Update shareholder's credit balance
                    $this->db->query("
                        UPDATE `" . DB_PREFIX . "shareholders` 
                        SET `current_credit_balance` = `current_credit_balance` + '" . (float)$individual_amount . "'
                        WHERE `shareholder_id` = '" . (int)$shareholder['id'] . "'
                    ");
                    
                    $result['allocations'][] = [
                        'shareholder_name' => $shareholder['shareholder_name'],
                        'percentage' => round($shareholder['ownership_percentage'], 2),
                        'amount' => $individual_amount
                    ];
                    
                    $result['allocated']++;
                }
            }
            
            $result['total_amount'] = $total_amount;
            $result['reference_shareholder'] = $reference->row['shareholder_id'];
            $result['reference_percentage'] = $reference_percentage;
            
            $this->db->query("COMMIT");
            
        } catch (\Exception $e) {
            $this->db->query("ROLLBACK");
            $result['success'] = false;
            $result['errors'][] = $e->getMessage();
        }
        
        return $result;
    }

    /**
     * Bulk allocate payment based on per-unit/percentage rate
     * @param array $data
     * @return array
     */
    public function bulkAllocateByPerUnitRate(array $data): array {
        $result = ['success' => true, 'allocated' => 0, 'allocations' => [], 'errors' => []];
        
        $project_id = (int)$data['project_id'];
        $payment_phase_id = (int)$data['payment_phase_id'];
        $rate_per_unit = (float)$data['rate_per_unit']; // Amount per 1% or per share
        $calculation_type = $data['calculation_type'] ?? 'percentage'; // 'percentage' or 'units'
        $notes = $data['notes'] ?? '';
        
        // Get all active shareholders with their details
        if ($calculation_type == 'percentage') {
            // Calculate based on ownership percentage
            $shareholders = $this->db->query("
                SELECT s.id, s.project_id, 
                    s.ownership_units, p.total_shares,
                    (s.ownership_units / p.total_shares * 100) as ownership_percentage,
                    CONCAT(c.firstname, ' ', c.lastname) as shareholder_name
                FROM `" . DB_PREFIX . "shareholders` s
                INNER JOIN `" . DB_PREFIX . "pms_project` p ON s.project_id = p.project_id
                INNER JOIN `" . DB_PREFIX . "customer` c ON s.user_id = c.customer_id
                WHERE s.project_id = '" . (int)$project_id . "'
                AND s.is_deleted = 0
                AND s.status = 'active'
            ");
            
            if ($shareholders->num_rows == 0) {
                $result['success'] = false;
                $result['errors'][] = 'No active shareholders found for this project.';
                return $result;
            }
            
            // Start transaction
            $this->db->query("START TRANSACTION");
            
            try {
                $total_allocated = 0;
                
                foreach ($shareholders->rows as $shareholder) {
                    // Calculate amount: rate_per_unit × ownership_percentage
                    $individual_amount = $rate_per_unit * $shareholder['ownership_percentage'];
                    
                    if ($individual_amount > 0) {
                        // Insert payment allocation record
                        $this->db->query("
                            INSERT INTO `" . DB_PREFIX . "payment_allocation` 
                            SET `shareholder_id` = '" . (int)$shareholder['id'] . "',
                                `project_id` = '" . (int)$project_id . "',
                                `payment_phase_id` = '" . (int)$payment_phase_id . "',
                                `allocated_amount` = '" . (float)$individual_amount . "',
                                `notes` = '" . $this->db->escape($notes) . " (Rate: " . $rate_per_unit . " per 1% | " . round($shareholder['ownership_percentage'], 2) . "%)',
                                `created_by` = '" . (int)$this->customer->getId() . "',
                                `created_at` = NOW()
                        ");
                        
                        // Update shareholder's credit balance
                        $this->db->query("
                            UPDATE `" . DB_PREFIX . "shareholders` 
                            SET `current_credit_balance` = `current_credit_balance` + '" . (float)$individual_amount . "'
                            WHERE `shareholder_id` = '" . (int)$shareholder['id'] . "'
                        ");
                        
                        $result['allocations'][] = [
                            'shareholder_name' => $shareholder['shareholder_name'],
                            'percentage' => round($shareholder['ownership_percentage'], 2),
                            'amount' => $individual_amount
                        ];
                        
                        $total_allocated += $individual_amount;
                        $result['allocated']++;
                    }
                }
                
                $result['total_allocated'] = $total_allocated;
                $result['rate_per_unit'] = $rate_per_unit;
                $result['calculation_type'] = 'percentage';
                
                $this->db->query("COMMIT");
                
            } catch (\Exception $e) {
                $this->db->query("ROLLBACK");
                $result['success'] = false;
                $result['errors'][] = $e->getMessage();
            }
            
        } else {
            // Calculate based on ownership units (shares)
            $shareholders = $this->db->query("
                SELECT s.id, s.project_id, s.ownership_units,
                    CONCAT(c.firstname, ' ', c.lastname) as shareholder_name
                FROM `" . DB_PREFIX . "shareholders` s
                INNER JOIN `" . DB_PREFIX . "customer` c ON s.user_id = c.customer_id
                WHERE s.project_id = '" . (int)$project_id . "'
                AND s.is_deleted = 0
                AND s.status = 'active'
            ");
            
            if ($shareholders->num_rows == 0) {
                $result['success'] = false;
                $result['errors'][] = 'No active shareholders found for this project.';
                return $result;
            }
            
            // Start transaction
            $this->db->query("START TRANSACTION");
            
            try {
                $total_allocated = 0;
                
                foreach ($shareholders->rows as $shareholder) {
                    // Calculate amount: rate_per_unit × ownership_units
                    $individual_amount = $rate_per_unit * $shareholder['ownership_units'];
                    
                    if ($individual_amount > 0) {
                        // Insert payment allocation record
                        $this->db->query("
                            INSERT INTO `" . DB_PREFIX . "payment_allocation` 
                            SET `shareholder_id` = '" . (int)$shareholder['id'] . "',
                                `project_id` = '" . (int)$project_id . "',
                                `payment_phase_id` = '" . (int)$payment_phase_id . "',
                                `allocated_amount` = '" . (float)$individual_amount . "',
                                `notes` = '" . $this->db->escape($notes) . " (Rate: " . $rate_per_unit . " per share | " . $shareholder['ownership_units'] . " units)',
                                `created_by` = '" . (int)$this->customer->getId() . "',
                                `created_at` = NOW()
                        ");
                        
                        // Update shareholder's credit balance
                        $this->db->query("
                            UPDATE `" . DB_PREFIX . "shareholders` 
                            SET `current_credit_balance` = `current_credit_balance` + '" . (float)$individual_amount . "'
                            WHERE `id` = '" . (int)$shareholder['id'] . "'
                        ");
                        
                        $result['allocations'][] = [
                            'shareholder_name' => $shareholder['shareholder_name'],
                            'units' => $shareholder['ownership_units'],
                            'amount' => $individual_amount
                        ];
                        
                        $total_allocated += $individual_amount;
                        $result['allocated']++;
                    }
                }
                
                $result['total_allocated'] = $total_allocated;
                $result['rate_per_unit'] = $rate_per_unit;
                $result['calculation_type'] = 'units';
                
                $this->db->query("COMMIT");
                
            } catch (\Exception $e) {
                $this->db->query("ROLLBACK");
                $result['success'] = false;
                $result['errors'][] = $e->getMessage();
            }
        }
        
        return $result;
    }

    /**
     * Get all payment allocations for a shareholder
     * @param int $shareholder_id
     * @param array $data
     * @return array
     */
    public function getMyPaymentAllocations(int $shareholder_id, array $data = []): array {
        $sql = "
            SELECT pa.*, 
                pp.name as phase_name,
                pp.slug as phase_slug,
                CONCAT(c.firstname, ' ', c.lastname) as created_by_name
            FROM `" . DB_PREFIX . "payment_allocation` pa
            LEFT JOIN `" . DB_PREFIX . "payment_phase` pp ON pa.payment_phase_id = pp.phase_id
            LEFT JOIN `" . DB_PREFIX . "customer` c ON pa.created_by = c.customer_id
            WHERE pa.shareholder_id = '" . (int)$shareholder_id . "'
            AND pa.is_deleted = 0
        ";
        
        if (!empty($data['filter_phase'])) {
            $sql .= " AND pa.payment_phase_id = '" . (int)$data['filter_phase'] . "'";
        }
        
        if (!empty($data['filter_year'])) {
            $sql .= " AND YEAR(pa.created_at) = '" . (int)$data['filter_year'] . "'";
        }
        
        if (!empty($data['filter_month'])) {
            $sql .= " AND MONTH(pa.created_at) = '" . (int)$data['filter_month'] . "'";
        }
        
        $sql .= " ORDER BY pa.created_at DESC";
        
        $query = $this->db->query($sql);
        
        return $query->rows;
    }

    /**
     * Get payment allocation summary for a shareholder
     * @param int $shareholder_id
     * @return array
     */
    public function getMyPaymentAllocationSummary(int $shareholder_id): array {
        $summary = [];
        
        // Total allocated amount
        $query = $this->db->query("
            SELECT COALESCE(SUM(allocated_amount), 0) as total
            FROM `" . DB_PREFIX . "payment_allocation`
            WHERE shareholder_id = '" . (int)$shareholder_id . "'
            AND is_deleted = 0
        ");
        $summary['total_allocated'] = (float)$query->row['total'];
        
        // Total allocations count
        $query = $this->db->query("
            SELECT COUNT(*) as total
            FROM `" . DB_PREFIX . "payment_allocation`
            WHERE shareholder_id = '" . (int)$shareholder_id . "'
            AND is_deleted = 0
        ");
        $summary['total_allocations'] = (int)$query->row['total'];
        
        // Last allocation date
        $query = $this->db->query("
            SELECT MAX(created_at) as last_allocation
            FROM `" . DB_PREFIX . "payment_allocation`
            WHERE shareholder_id = '" . (int)$shareholder_id . "'
            AND is_deleted = 0
        ");
        $summary['last_allocation'] = $query->row['last_allocation'] 
            ? date('d M Y', strtotime($query->row['last_allocation'])) 
            : 'No allocations yet';
        
        // Allocations by phase
        $query = $this->db->query("
            SELECT pp.name as phase_name, 
                pp.phase_id,
                COALESCE(SUM(pa.allocated_amount), 0) as total,
                COUNT(pa.allocation_id) as count
            FROM `" . DB_PREFIX . "payment_allocation` pa
            LEFT JOIN `" . DB_PREFIX . "payment_phase` pp ON pa.payment_phase_id = pp.phase_id
            WHERE pa.shareholder_id = '" . (int)$shareholder_id . "'
            AND pa.is_deleted = 0
            GROUP BY pp.phase_id, pp.name
            ORDER BY pp.sort_order ASC
        ");
        $summary['allocations_by_phase'] = $query->rows;
        
        // Allocations by year
        $query = $this->db->query("
            SELECT YEAR(created_at) as year, 
                COALESCE(SUM(allocated_amount), 0) as total,
                COUNT(*) as count
            FROM `" . DB_PREFIX . "payment_allocation`
            WHERE shareholder_id = '" . (int)$shareholder_id . "'
            AND is_deleted = 0
            GROUP BY YEAR(created_at)
            ORDER BY year DESC
        ");
        $summary['allocations_by_year'] = $query->rows;
        
        return $summary;
    }

    /**
     * Get available years for payment allocation filter
     * @param int $shareholder_id
     * @return array
     */
    public function getAvailableAllocationYears(int $shareholder_id): array {
        $query = $this->db->query("
            SELECT DISTINCT YEAR(created_at) as year
            FROM `" . DB_PREFIX . "payment_allocation`
            WHERE shareholder_id = '" . (int)$shareholder_id . "'
            AND is_deleted = 0
            ORDER BY year DESC
        ");
        
        return $query->rows;
    }

    /**
     * Transfer shares from one shareholder to another
     * @param array $data
     * @return array
     */
    public function transferShares(array $data): array {
        $result = ['success' => true, 'errors' => []];
        
        $from_shareholder_id = (int)$data['from_shareholder_id'];
        $to_shareholder_id = (int)$data['to_shareholder_id'];
        $units_to_transfer = (int)$data['units_to_transfer'];
        $transfer_date = $data['transfer_date'];
        $reference_number = $data['reference_number'] ?? '';
        $notes = $data['notes'] ?? '';
        
        // Start transaction
        $this->db->query("START TRANSACTION");
        
        try {
            // Get from shareholder details
            $from = $this->db->query("
                SELECT s.*, p.total_shares
                FROM `" . DB_PREFIX . "shareholders` s
                INNER JOIN `" . DB_PREFIX . "pms_project` p ON s.project_id = p.project_id
                WHERE s.id = '" . (int)$from_shareholder_id . "'
                AND s.is_deleted = 0
            ");
            
            if ($from->num_rows == 0) {
                throw new \Exception('Source shareholder not found');
            }
            
            // Get to shareholder details
            $to = $this->db->query("
                SELECT s.*, p.total_shares
                FROM `" . DB_PREFIX . "shareholders` s
                INNER JOIN `" . DB_PREFIX . "pms_project` p ON s.project_id = p.project_id
                WHERE s.id = '" . (int)$to_shareholder_id . "'
                AND s.is_deleted = 0
            ");
            
            if ($to->num_rows == 0) {
                throw new \Exception('Destination shareholder not found');
            }
            
            // Check if from shareholder has enough units
            if ($from->row['ownership_units'] < $units_to_transfer) {
                throw new \Exception('Insufficient shares to transfer. Available: ' . $from->row['ownership_units']);
            }
            
            // Calculate percentage
            $total_shares = $from->row['total_shares'];
            $percentage_transferred = ($units_to_transfer / $total_shares) * 100;
            
            // Get unpaid dues of from shareholder (percentage of total due)
            $total_due = $this->getTotalDue($from_shareholder_id);
            $unpaid_dues_transferred = ($total_due * $percentage_transferred) / 100;
            
            // Update from shareholder (reduce units)
            $this->db->query("
                UPDATE `" . DB_PREFIX . "shareholders` 
                SET `ownership_units` = `ownership_units` - '" . (int)$units_to_transfer . "',
                    `updated_at` = NOW()
                WHERE `id` = '" . (int)$from_shareholder_id . "'
            ");
            
            // Update to shareholder (increase units)
            $this->db->query("
                UPDATE `" . DB_PREFIX . "shareholders` 
                SET `ownership_units` = `ownership_units` + '" . (int)$units_to_transfer . "',
                    `updated_at` = NOW()
                WHERE `id` = '" . (int)$to_shareholder_id . "'
            ");
            
            // Transfer unpaid dues (adjust credit balance)
            // Reduce from shareholder's credit balance proportionally
            $from_credit = (float)$from->row['current_credit_balance'];
            $credit_to_transfer = ($from_credit * $percentage_transferred) / 100;
            
            if ($credit_to_transfer > 0) {
                $this->db->query("
                    UPDATE `" . DB_PREFIX . "shareholders` 
                    SET `current_credit_balance` = `current_credit_balance` - '" . (float)$credit_to_transfer . "'
                    WHERE `id` = '" . (int)$from_shareholder_id . "'
                ");
                
                $this->db->query("
                    UPDATE `" . DB_PREFIX . "shareholders` 
                    SET `current_credit_balance` = `current_credit_balance` + '" . (float)$credit_to_transfer . "'
                    WHERE `id` = '" . (int)$to_shareholder_id . "'
                ");
            }
            
            // Record transfer history
            $this->db->query("
                INSERT INTO `" . DB_PREFIX . "pms_share_transfer` 
                SET `project_id` = '" . (int)$from->row['project_id'] . "',
                    `from_shareholder_id` = '" . (int)$from_shareholder_id . "',
                    `to_shareholder_id` = '" . (int)$to_shareholder_id . "',
                    `units_transferred` = '" . (int)$units_to_transfer . "',
                    `percentage_transferred` = '" . (float)$percentage_transferred . "',
                    `unpaid_dues_transferred` = '" . (float)$unpaid_dues_transferred . "',
                    `transfer_date` = '" . $this->db->escape($transfer_date) . "',
                    `reference_number` = '" . $this->db->escape($reference_number) . "',
                    `notes` = '" . $this->db->escape($notes) . "',
                    `created_by` = '" . (int)$this->customer->getId() . "',
                    `created_at` = NOW()
            ");
            
            $this->db->query("COMMIT");
            
            $result['transfer_id'] = $this->db->getLastId();
            $result['from_new_units'] = $from->row['ownership_units'] - $units_to_transfer;
            $result['to_new_units'] = $to->row['ownership_units'] + $units_to_transfer;
            
        } catch (\Exception $e) {
            $this->db->query("ROLLBACK");
            $result['success'] = false;
            $result['errors'][] = $e->getMessage();
        }
        
        return $result;
    }

    /**
     * Get share transfer history for a project
     * @param int $project_id
     * @param array $data
     * @return array
     */
    public function getTransferHistory(int $project_id, array $data = []): array {
        $sql = "
            SELECT t.*,
                CONCAT(cf.firstname, ' ', cf.lastname) as from_name,
                cf.email as from_email,
                cf.telephone as from_phone,
                sfc.shareholder_code as from_code,
                CONCAT(ct.firstname, ' ', ct.lastname) as to_name,
                ct.email as to_email,
                ct.telephone as to_phone,
                stc.shareholder_code as to_code,
                CONCAT(cu.firstname, ' ', cu.lastname) as created_by_name
            FROM `" . DB_PREFIX . "pms_share_transfer` t
            LEFT JOIN `" . DB_PREFIX . "shareholders` sfc ON t.from_shareholder_id = sfc.id
            LEFT JOIN `" . DB_PREFIX . "customer` cf ON sfc.user_id = cf.customer_id
            LEFT JOIN `" . DB_PREFIX . "shareholders` stc ON t.to_shareholder_id = stc.id
            LEFT JOIN `" . DB_PREFIX . "customer` ct ON stc.user_id = ct.customer_id
            LEFT JOIN `" . DB_PREFIX . "customer` cu ON t.created_by = cu.customer_id
            WHERE t.project_id = '" . (int)$project_id . "'
            AND t.is_deleted = 0
        ";
        
        if (!empty($data['filter_from'])) {
            $sql .= " AND (cf.firstname LIKE '%" . $this->db->escape($data['filter_from']) . "%' 
                        OR cf.lastname LIKE '%" . $this->db->escape($data['filter_from']) . "%')";
        }
        
        if (!empty($data['filter_to'])) {
            $sql .= " AND (ct.firstname LIKE '%" . $this->db->escape($data['filter_to']) . "%' 
                        OR ct.lastname LIKE '%" . $this->db->escape($data['filter_to']) . "%')";
        }
        
        if (!empty($data['filter_date_from'])) {
            $sql .= " AND DATE(t.transfer_date) >= '" . $this->db->escape($data['filter_date_from']) . "'";
        }
        
        if (!empty($data['filter_date_to'])) {
            $sql .= " AND DATE(t.transfer_date) <= '" . $this->db->escape($data['filter_date_to']) . "'";
        }
        
        $sql .= " ORDER BY t.transfer_date DESC, t.created_at DESC";
        
        $query = $this->db->query($sql);
        
        return $query->rows;
    }

    /**
     * Get shareholders for transfer dropdown (excluding a specific shareholder)
     * @param int $project_id
     * @param int $exclude_id
     * @return array
     */
    public function getShareholdersForTransfer(int $project_id, int $exclude_id = 0): array {
        $sql = "
            SELECT s.id, s.ownership_units,
                CONCAT(c.firstname, ' ', c.lastname) as name,
                s.shareholder_code,
                c.email
            FROM `" . DB_PREFIX . "shareholders` s
            INNER JOIN `" . DB_PREFIX . "customer` c ON s.user_id = c.customer_id
            WHERE s.project_id = '" . (int)$project_id . "'
            AND s.is_deleted = 0
            AND s.status = 'active'
        ";
        
        if ($exclude_id > 0) {
            $sql .= " AND s.id != '" . (int)$exclude_id . "'";
        }
        
        $sql .= " ORDER BY c.firstname ASC";
        
        $query = $this->db->query($sql);
        
        return $query->rows;
    }

    /**
     * Get all payment allocations for a project with filters
     * @param array $data
     * @return array
     */
    public function getAllPaymentAllocations(array $data = []): array {
        $sql = "
            SELECT pa.*, 
                pp.name as phase_name,
                CONCAT(c.firstname, ' ', c.lastname) as shareholder_name,
                s.shareholder_code,
                s.ownership_units,
                (s.ownership_units / p.total_shares * 100) as ownership_percentage,
                CONCAT(u.firstname, ' ', u.lastname) as created_by_name
            FROM `" . DB_PREFIX . "payment_allocation` pa
            LEFT JOIN `" . DB_PREFIX . "payment_phase` pp ON pa.payment_phase_id = pp.phase_id
            LEFT JOIN `" . DB_PREFIX . "shareholders` s ON pa.shareholder_id = s.id
            LEFT JOIN `" . DB_PREFIX . "pms_project` p ON pa.project_id = p.project_id
            LEFT JOIN `" . DB_PREFIX . "customer` c ON s.user_id = c.customer_id
            LEFT JOIN `" . DB_PREFIX . "customer` u ON pa.created_by = u.customer_id
            WHERE pa.project_id = '" . (int)$data['project_id'] . "'
            AND pa.is_deleted = 0
        ";
        
        // Filter by shareholder
        if (!empty($data['filter_shareholder'])) {
            $sql .= " AND (c.firstname LIKE '%" . $this->db->escape($data['filter_shareholder']) . "%' 
                        OR c.lastname LIKE '%" . $this->db->escape($data['filter_shareholder']) . "%')";
        }
        
        // Filter by payment phase
        if (!empty($data['filter_phase'])) {
            $sql .= " AND pa.payment_phase_id = '" . (int)$data['filter_phase'] . "'";
        }
        
        // Filter by date range
        if (!empty($data['filter_date_from'])) {
            $sql .= " AND DATE(pa.created_at) >= '" . $this->db->escape($data['filter_date_from']) . "'";
        }
        
        if (!empty($data['filter_date_to'])) {
            $sql .= " AND DATE(pa.created_at) <= '" . $this->db->escape($data['filter_date_to']) . "'";
        }
        
        // Filter by amount range
        if (!empty($data['filter_amount_min'])) {
            $sql .= " AND pa.allocated_amount >= '" . (float)$data['filter_amount_min'] . "'";
        }
        
        if (!empty($data['filter_amount_max'])) {
            $sql .= " AND pa.allocated_amount <= '" . (float)$data['filter_amount_max'] . "'";
        }
        
        $sort_data = [
            'pa.created_at',
            'pa.allocated_amount',
            'shareholder_name',
            'phase_name'
        ];
        
        if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
            $sql .= " ORDER BY " . $data['sort'];
        } else {
            $sql .= " ORDER BY pa.created_at DESC";
        }
        
        if (isset($data['order']) && ($data['order'] == 'ASC')) {
            $sql .= " ASC";
        } else {
            $sql .= " DESC";
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
     * Get total payment allocations count for pagination
     * @param array $data
     * @return int
     */
    public function getTotalPaymentAllocations(array $data = []): int {
        $sql = "
            SELECT COUNT(*) as total
            FROM `" . DB_PREFIX . "payment_allocation` pa
            LEFT JOIN `" . DB_PREFIX . "shareholders` s ON pa.shareholder_id = s.id
            LEFT JOIN `" . DB_PREFIX . "customer` c ON s.user_id = c.customer_id
            WHERE pa.project_id = '" . (int)$data['project_id'] . "'
            AND pa.is_deleted = 0
        ";
        
        if (!empty($data['filter_shareholder'])) {
            $sql .= " AND (c.firstname LIKE '%" . $this->db->escape($data['filter_shareholder']) . "%' 
                        OR c.lastname LIKE '%" . $this->db->escape($data['filter_shareholder']) . "%')";
        }
        
        if (!empty($data['filter_phase'])) {
            $sql .= " AND pa.payment_phase_id = '" . (int)$data['filter_phase'] . "'";
        }
        
        if (!empty($data['filter_date_from'])) {
            $sql .= " AND DATE(pa.created_at) >= '" . $this->db->escape($data['filter_date_from']) . "'";
        }
        
        if (!empty($data['filter_date_to'])) {
            $sql .= " AND DATE(pa.created_at) <= '" . $this->db->escape($data['filter_date_to']) . "'";
        }
        
        if (!empty($data['filter_amount_min'])) {
            $sql .= " AND pa.allocated_amount >= '" . (float)$data['filter_amount_min'] . "'";
        }
        
        if (!empty($data['filter_amount_max'])) {
            $sql .= " AND pa.allocated_amount <= '" . (float)$data['filter_amount_max'] . "'";
        }
        
        $query = $this->db->query($sql);
        
        return (int)$query->row['total'];
    }

    /**
     * Get payment allocation summary for a project
     * @param int $project_id
     * @return array
     */
    public function getPaymentAllocationSummary(int $project_id): array {
        $summary = [];
        
        // Total allocated amount
        $query = $this->db->query("
            SELECT COALESCE(SUM(allocated_amount), 0) as total
            FROM `" . DB_PREFIX . "payment_allocation`
            WHERE project_id = '" . (int)$project_id . "'
            AND is_deleted = 0
        ");
        $summary['total_allocated'] = (float)$query->row['total'];
        
        // Total number of allocations
        $query = $this->db->query("
            SELECT COUNT(*) as total
            FROM `" . DB_PREFIX . "payment_allocation`
            WHERE project_id = '" . (int)$project_id . "'
            AND is_deleted = 0
        ");
        $summary['total_allocations'] = (int)$query->row['total'];
        
        // Allocations by phase
        $query = $this->db->query("
            SELECT pp.name as phase_name, 
                COALESCE(SUM(pa.allocated_amount), 0) as total,
                COUNT(pa.allocation_id) as count
            FROM `" . DB_PREFIX . "payment_allocation` pa
            LEFT JOIN `" . DB_PREFIX . "payment_phase` pp ON pa.payment_phase_id = pp.phase_id
            WHERE pa.project_id = '" . (int)$project_id . "'
            AND pa.is_deleted = 0
            GROUP BY pp.phase_id, pp.name
            ORDER BY total DESC
        ");
        $summary['by_phase'] = $query->rows;
        
        // Top shareholders by allocation
        $query = $this->db->query("
            SELECT CONCAT(c.firstname, ' ', c.lastname) as shareholder_name,
                s.shareholder_code,
                COALESCE(SUM(pa.allocated_amount), 0) as total
            FROM `" . DB_PREFIX . "payment_allocation` pa
            LEFT JOIN `" . DB_PREFIX . "shareholders` s ON pa.shareholder_id = s.id
            LEFT JOIN `" . DB_PREFIX . "customer` c ON s.user_id = c.customer_id
            WHERE pa.project_id = '" . (int)$project_id . "'
            AND pa.is_deleted = 0
            GROUP BY pa.shareholder_id
            ORDER BY total DESC
            LIMIT 5
        ");
        $summary['top_shareholders'] = $query->rows;
        
        // Last allocation date
        $query = $this->db->query("
            SELECT MAX(created_at) as last_allocation
            FROM `" . DB_PREFIX . "payment_allocation`
            WHERE project_id = '" . (int)$project_id . "'
            AND is_deleted = 0
        ");
        $summary['last_allocation'] = $query->row['last_allocation'] 
            ? date('d M Y', strtotime($query->row['last_allocation'])) 
            : 'No allocations';
        
        return $summary;
    }

    /**
     * Get top shareholders by different criteria
     * @param int $project_id
     * @param string $criteria - 'ownership', 'paid', 'due', 'allocated'
     * @param int $limit
     * @return array
     */
    public function getTopShareholders(int $project_id, string $criteria = 'ownership', int $limit = 5): array {
        $sql = "";
        
        switch ($criteria) {
            case 'ownership':
                // Top by ownership percentage
                $sql = "
                    SELECT 
                        s.id,
                        CONCAT(c.firstname, ' ', c.lastname) as name,
                        s.shareholder_code,
                        s.ownership_units,
                        p.total_shares,
                        (s.ownership_units / p.total_shares * 100) as percentage,
                        COALESCE((SELECT SUM(amount) FROM `" . DB_PREFIX . "pms_payment` WHERE shareholder_id = s.id AND is_deleted = 0), 0) as total_paid,
                        COALESCE((SELECT SUM(due_amount) FROM `" . DB_PREFIX . "expenses_allocation` WHERE shareholder_id = s.id AND is_paid = 0), 0) as total_due
                    FROM `" . DB_PREFIX . "shareholders` s
                    INNER JOIN `" . DB_PREFIX . "pms_project` p ON s.project_id = p.project_id
                    INNER JOIN `" . DB_PREFIX . "customer` c ON s.user_id = c.customer_id
                    WHERE s.project_id = '" . (int)$project_id . "'
                    AND s.is_deleted = 0
                    AND s.status = 'active'
                    ORDER BY percentage DESC
                    LIMIT " . (int)$limit . "
                ";
                break;
                
            case 'paid':
                // Top by total paid amount
                $sql = "
                    SELECT 
                        s.id,
                        CONCAT(c.firstname, ' ', c.lastname) as name,
                        s.shareholder_code,
                        s.ownership_units,
                        (s.ownership_units / p.total_shares * 100) as percentage,
                        COALESCE(SUM(pay.amount), 0) as total_paid,
                        COALESCE((SELECT SUM(due_amount) FROM `" . DB_PREFIX . "expenses_allocation` WHERE shareholder_id = s.id AND is_paid = 0), 0) as total_due
                    FROM `" . DB_PREFIX . "shareholders` s
                    INNER JOIN `" . DB_PREFIX . "pms_project` p ON s.project_id = p.project_id
                    INNER JOIN `" . DB_PREFIX . "customer` c ON s.user_id = c.customer_id
                    LEFT JOIN `" . DB_PREFIX . "pms_payment` pay ON s.id = pay.shareholder_id AND pay.is_deleted = 0
                    WHERE s.project_id = '" . (int)$project_id . "'
                    AND s.is_deleted = 0
                    AND s.status = 'active'
                    GROUP BY s.id
                    ORDER BY total_paid DESC
                    LIMIT " . (int)$limit . "
                ";
                break;
                
            case 'due':
                // Top by outstanding due amount
                $sql = "
                    SELECT 
                        s.id,
                        CONCAT(c.firstname, ' ', c.lastname) as name,
                        s.shareholder_code,
                        s.ownership_units,
                        (s.ownership_units / p.total_shares * 100) as percentage,
                        COALESCE(SUM(pay.amount), 0) as total_paid,
                        COALESCE(SUM(ea.due_amount), 0) as total_due
                    FROM `" . DB_PREFIX . "shareholders` s
                    INNER JOIN `" . DB_PREFIX . "pms_project` p ON s.project_id = p.project_id
                    INNER JOIN `" . DB_PREFIX . "customer` c ON s.user_id = c.customer_id
                    LEFT JOIN `" . DB_PREFIX . "pms_payment` pay ON s.id = pay.shareholder_id AND pay.is_deleted = 0
                    LEFT JOIN `" . DB_PREFIX . "expenses_allocation` ea ON s.id = ea.shareholder_id AND ea.is_paid = 0
                    WHERE s.project_id = '" . (int)$project_id . "'
                    AND s.is_deleted = 0
                    AND s.status = 'active'
                    GROUP BY s.id
                    ORDER BY total_due DESC
                    LIMIT " . (int)$limit . "
                ";
                break;
                
            case 'allocated':
                // Top by payment allocation received
                $sql = "
                    SELECT 
                        s.id,
                        CONCAT(c.firstname, ' ', c.lastname) as name,
                        s.shareholder_code,
                        s.ownership_units,
                        (s.ownership_units / p.total_shares * 100) as percentage,
                        COALESCE(SUM(pa.allocated_amount), 0) as total_allocated,
                        COALESCE((SELECT SUM(amount) FROM `" . DB_PREFIX . "pms_payment` WHERE shareholder_id = s.id AND is_deleted = 0), 0) as total_paid
                    FROM `" . DB_PREFIX . "shareholders` s
                    INNER JOIN `" . DB_PREFIX . "pms_project` p ON s.project_id = p.project_id
                    INNER JOIN `" . DB_PREFIX . "customer` c ON s.user_id = c.customer_id
                    LEFT JOIN `" . DB_PREFIX . "payment_allocation` pa ON s.id = pa.shareholder_id AND pa.is_deleted = 0
                    WHERE s.project_id = '" . (int)$project_id . "'
                    AND s.is_deleted = 0
                    AND s.status = 'active'
                    GROUP BY s.id
                    ORDER BY total_allocated DESC
                    LIMIT " . (int)$limit . "
                ";
                break;
                
            default:
                // Default to ownership
                return $this->getTopShareholders($project_id, 'ownership', $limit);
        }
        
        $query = $this->db->query($sql);
        
        // Calculate percentage of total for each shareholder
        $total_ownership = 0;
        foreach ($query->rows as $row) {
            $total_ownership += $row['percentage'];
        }
        
        $result = [];
        foreach ($query->rows as $row) {
            $row['percentage_of_total'] = $total_ownership > 0 ? round(($row['percentage'] / $total_ownership) * 100, 2) : 0;
            $result[] = $row;
        }
        
        return $result;
    }

    /**
     * Get top shareholders summary with multiple metrics
     * @param int $project_id
     * @param int $limit
     * @return array
     */
    public function getTopShareholdersSummary(int $project_id, int $limit = 5): array {
        $query = $this->db->query("
            SELECT 
                s.id,
                CONCAT(c.firstname, ' ', c.lastname) as name,
                s.shareholder_code,
                s.ownership_units,
                (s.ownership_units / p.total_shares * 100) as ownership_percentage,
                COALESCE((SELECT SUM(amount) FROM `" . DB_PREFIX . "pms_payment` WHERE shareholder_id = s.id AND is_deleted = 0), 0) as total_paid,
                COALESCE((SELECT SUM(due_amount) FROM `" . DB_PREFIX . "expenses_allocation` WHERE shareholder_id = s.id AND is_paid = 0), 0) as total_due,
                COALESCE((SELECT SUM(allocated_amount) FROM `" . DB_PREFIX . "payment_allocation` WHERE shareholder_id = s.id AND is_deleted = 0), 0) as total_allocated,
                (SELECT COUNT(*) FROM `" . DB_PREFIX . "pms_payment` WHERE shareholder_id = s.id AND is_deleted = 0) as payment_count
            FROM `" . DB_PREFIX . "shareholders` s
            INNER JOIN `" . DB_PREFIX . "pms_project` p ON s.project_id = p.project_id
            INNER JOIN `" . DB_PREFIX . "customer` c ON s.user_id = c.customer_id
            WHERE s.project_id = '" . (int)$project_id . "'
            AND s.is_deleted = 0
            AND s.status = 'active'
            ORDER BY ownership_percentage DESC
            LIMIT " . (int)$limit . "
        ");
        
        return $query->rows;
    }

    /**
     * Get unpaid bills summary for a shareholder
     * @param int $shareholder_id
     * @return array
     */
    public function getUnpaidBillsSummary(int $shareholder_id): array {
        $summary = [];
        
        // Get all unpaid bills grouped by type
        $query = $this->db->query("
            SELECT 
                bt.name as bill_type,
                bt.bill_type_id,
                COUNT(b.bill_id) as bill_count,
                COALESCE(SUM(b.amount), 0) as total_amount,
                MIN(b.bill_month) as oldest_month,
                MIN(b.due_date) as earliest_due_date
            FROM `" . DB_PREFIX . "pms_monthly_bill` b
            LEFT JOIN `" . DB_PREFIX . "pms_bill_type` bt ON b.bill_type_id = bt.bill_type_id
            WHERE b.shareholder_id = '" . (int)$shareholder_id . "'
            AND b.is_paid = 0
            AND b.is_deleted = 0
            GROUP BY bt.bill_type_id, bt.name
            ORDER BY bt.sort_order ASC
        ");
        
        $summary['by_type'] = $query->rows;
        
        // Get total unpaid amount
        $query = $this->db->query("
            SELECT COALESCE(SUM(amount), 0) as total
            FROM `" . DB_PREFIX . "pms_monthly_bill`
            WHERE shareholder_id = '" . (int)$shareholder_id . "'
            AND is_paid = 0
            AND is_deleted = 0
        ");
        $summary['total_unpaid'] = (float)$query->row['total'];
        
        // Get unpaid bills count
        $query = $this->db->query("
            SELECT COUNT(*) as total
            FROM `" . DB_PREFIX . "pms_monthly_bill`
            WHERE shareholder_id = '" . (int)$shareholder_id . "'
            AND is_paid = 0
            AND is_deleted = 0
        ");
        $summary['unpaid_count'] = (int)$query->row['total'];
        
        // Get oldest unpaid bill due date
        $query = $this->db->query("
            SELECT MIN(due_date) as earliest_due_date,
                MIN(bill_month) as oldest_month
            FROM `" . DB_PREFIX . "pms_monthly_bill`
            WHERE shareholder_id = '" . (int)$shareholder_id . "'
            AND is_paid = 0
            AND is_deleted = 0
        ");
        $summary['earliest_due_date'] = $query->row['earliest_due_date'] 
            ? date('d M Y', strtotime($query->row['earliest_due_date'])) 
            : 'No due date';
        $summary['oldest_month'] = $query->row['oldest_month']
            ? date('M Y', strtotime($query->row['oldest_month']))
            : 'No bills';
        
        // Get unpaid bills by month (grouped)
        $query = $this->db->query("
            SELECT 
                DATE_FORMAT(b.bill_month, '%Y-%m') as month_key,
                DATE_FORMAT(b.bill_month, '%M %Y') as month_name,
                COUNT(b.bill_id) as bill_count,
                COALESCE(SUM(b.amount), 0) as total_amount
            FROM `" . DB_PREFIX . "pms_monthly_bill` b
            WHERE b.shareholder_id = '" . (int)$shareholder_id . "'
            AND b.is_paid = 0
            AND b.is_deleted = 0
            GROUP BY DATE_FORMAT(b.bill_month, '%Y-%m')
            ORDER BY b.bill_month ASC
        ");
        $summary['by_month'] = $query->rows;
        
        return $summary;
    }

    /**
     * Get total unpaid bills amount for a shareholder
     * @param int $shareholder_id
     * @return float
     */
    public function getTotalUnpaidBillsAmount(int $shareholder_id): float {
        $query = $this->db->query("
            SELECT COALESCE(SUM(amount), 0) as total
            FROM `" . DB_PREFIX . "pms_monthly_bill`
            WHERE shareholder_id = '" . (int)$shareholder_id . "'
            AND is_paid = 0
            AND is_deleted = 0
        ");
        
        return (float)$query->row['total'];
    }

    /**
     * Get unpaid bills count for a shareholder
     * @param int $shareholder_id
     * @return int
     */
    public function getUnpaidBillsCount(int $shareholder_id): int {
        $query = $this->db->query("
            SELECT COUNT(*) as total
            FROM `" . DB_PREFIX . "pms_monthly_bill`
            WHERE shareholder_id = '" . (int)$shareholder_id . "'
            AND is_paid = 0
            AND is_deleted = 0
        ");
        
        return (int)$query->row['total'];
    }

    /**
     * Get recent transactions for a shareholder
     * @param int $shareholder_id
     * @param int $limit
     * @return array
     */
    public function getRecentTransactions(int $shareholder_id, int $limit = 10): array {
        $transactions = [];
        
        // Get expense allocations (debts)
        $expenses = $this->db->query("
            SELECT 
                'expense' as transaction_type,
                e.expense_date as transaction_date,
                CONCAT('Expense: ', e.description) as description,
                ea.due_amount as amount,
                'debit' as type,
                ec.category_name,
                CASE WHEN ea.is_paid = 1 THEN 'paid' ELSE 'pending' END as status
            FROM `" . DB_PREFIX . "expenses_allocation` ea
            INNER JOIN `" . DB_PREFIX . "expenses` e ON ea.expense_id = e.expense_id
            LEFT JOIN `" . DB_PREFIX . "expense_categories` ec ON e.category_id = ec.id
            WHERE ea.shareholder_id = '" . (int)$shareholder_id . "'
            AND ea.is_deleted = 0
        ");
        
        foreach ($expenses->rows as $expense) {
            $transactions[] = $expense;
        }
        
        // Get payments (credits)
        $payments = $this->db->query("
            SELECT 
                'payment' as transaction_type,
                p.payment_date as transaction_date,
                CONCAT('Payment - ', pp.name) as description,
                p.amount as amount,
                'credit' as type,
                pp.name as phase_name,
                'completed' as status
            FROM `" . DB_PREFIX . "pms_payment` p
            LEFT JOIN `" . DB_PREFIX . "payment_phase` pp ON p.payment_phase_id = pp.phase_id
            WHERE p.shareholder_id = '" . (int)$shareholder_id . "'
            AND p.is_deleted = 0
        ");
        
        foreach ($payments->rows as $payment) {
            $transactions[] = $payment;
        }
        
        // Get maintenance bills (debits)
        $bills = $this->db->query("
            SELECT 
                'bill' as transaction_type,
                b.bill_month as transaction_date,
                CONCAT(bt.name, ' - ', DATE_FORMAT(b.bill_month, '%M %Y')) as description,
                b.amount as amount,
                'debit' as type,
                CASE WHEN b.is_paid = 1 THEN 'paid' ELSE 'pending' END as status
            FROM `" . DB_PREFIX . "pms_monthly_bill` b 
            LEFT JOIN `" . DB_PREFIX . "pms_bill_type` bt ON bt.bill_type_id = b.bill_type_id 
            WHERE b.shareholder_id = '" . (int)$shareholder_id . "'
            AND b.is_deleted = 0
        ");
        
        foreach ($bills->rows as $bill) {
            $transactions[] = $bill;
        }
        
        // Get payment allocations (credits)
        $allocations = $this->db->query("
            SELECT 
                'allocation' as transaction_type,
                pa.created_at as transaction_date,
                CONCAT('Allocation - ', pp.name) as description,
                pa.allocated_amount as amount,
                'credit' as type,
                pp.name as phase_name,
                'completed' as status
            FROM `" . DB_PREFIX . "payment_allocation` pa
            LEFT JOIN `" . DB_PREFIX . "payment_phase` pp ON pa.payment_phase_id = pp.phase_id
            WHERE pa.shareholder_id = '" . (int)$shareholder_id . "'
            AND pa.is_deleted = 0
        ");
        
        foreach ($allocations->rows as $allocation) {
            $transactions[] = $allocation;
        }
        
        // Sort by date (most recent first)
        usort($transactions, function($a, $b) {
            return strtotime($b['transaction_date']) - strtotime($a['transaction_date']);
        });
        
        // Limit results
        $transactions = array_slice($transactions, 0, $limit);
        
        // Format dates
        foreach ($transactions as &$transaction) {
            $transaction['formatted_date'] = date('d M Y', strtotime($transaction['transaction_date']));
            $transaction['formatted_amount'] = number_format($transaction['amount'], 2);
            $transaction['amount_class'] = ($transaction['type'] == 'debit') ? 'text-danger' : 'text-success';
            $transaction['amount_sign'] = ($transaction['type'] == 'debit') ? '-' : '+';
        }
        
        return $transactions;
    }

    /**
     * Get recent transactions with pagination
     * @param int $shareholder_id
     * @param array $data
     * @return array
     */
    public function getFilteredTransactions(int $shareholder_id, array $data = []): array {
        $transactions = $this->getRecentTransactions($shareholder_id, 100); // Get more for filtering
        
        // Apply filters
        if (!empty($data['filter_type'])) {
            $transactions = array_filter($transactions, function($t) use ($data) {
                return $t['transaction_type'] == $data['filter_type'];
            });
        }
        
        if (!empty($data['filter_date_from'])) {
            $transactions = array_filter($transactions, function($t) use ($data) {
                return strtotime($t['transaction_date']) >= strtotime($data['filter_date_from']);
            });
        }
        
        if (!empty($data['filter_date_to'])) {
            $transactions = array_filter($transactions, function($t) use ($data) {
                return strtotime($t['transaction_date']) <= strtotime($data['filter_date_to']);
            });
        }
        
        // Pagination
        $page = isset($data['page']) ? (int)$data['page'] : 1;
        $limit = isset($data['limit']) ? (int)$data['limit'] : 20;
        $start = ($page - 1) * $limit;
        
        $total = count($transactions);
        $transactions = array_slice($transactions, $start, $limit);
        
        return [
            'transactions' => $transactions,
            'total' => $total,
            'start' => $start + 1,
            'end' => min($start + $limit, $total)
        ];
    }
}
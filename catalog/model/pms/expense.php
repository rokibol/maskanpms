<?php
namespace Opencart\Catalog\Model\Pms;

class Expense extends \Opencart\System\Engine\Model {

    /**
     * Add a new expense
     * @param array $data
     * @return int
     */
    public function addExpense(array $data): int {
        // Handle document path
        $document_path = '';
        if (!empty($data['document_path'])) {
            $document_path = $data['document_path'];
        }
        
        $this->db->query("
            INSERT INTO `" . DB_PREFIX . "expenses` 
            SET `project_id` = '" . (int)$data['project_id'] . "',
                `invoice_number` = '" . $this->db->escape($data['invoice_number']) . "',
                `expense_date` = '" . $this->db->escape($data['expense_date']) . "',
                `category_id` = '" . (int)$data['category_id'] . "',
                `description` = '" . $this->db->escape($data['description']) . "',
                `total_amount` = '" . (float)$data['total_amount'] . "',
                `document_path` = '" . $this->db->escape($document_path) . "',
                `created_by` = '" . (int)$this->customer->getId() . "',
                `created_at` = NOW()
        ");
        
        $expense_id = $this->db->getLastId();
        
        // Auto-allocate expense to all shareholders
        //$this->allocateExpenseToShareholders($expense_id, $data['project_id'], $data['total_amount']);
        
        return $expense_id;
    }

    /**
     * Update an expense
     * @param int $expense_id
     * @param array $data
     */
    public function updateExpense(int $expense_id, array $data): void {
        // Handle document path from OpenCart upload system
        $document_path = '';
        if (!empty($data['document_path'])) {
            $document_path = $data['document_path'];
        }
        
        $this->db->query("
            UPDATE `" . DB_PREFIX . "expenses` 
            SET `invoice_number` = '" . $this->db->escape($data['invoice_number']) . "',
                `expense_date` = '" . $this->db->escape($data['expense_date']) . "',
                `category_id` = '" . (int)$data['category_id'] . "',
                `description` = '" . $this->db->escape($data['description']) . "',
                `total_amount` = '" . (float)$data['total_amount'] . "',
                `document_path` = '" . $this->db->escape($document_path) . "',
                `updated_at` = NOW()
            WHERE `expense_id` = '" . (int)$expense_id . "'
        ");
        
        // Update allocations if amount changed
        //$this->updateExpenseAllocations($expense_id, $data['project_id'], $data['total_amount']);
    }
    
    /**
     * Get expense by ID with full document URL
     * @param int $expense_id
     * @return array
     */
    public function getExpense(int $expense_id): array {
        $query = $this->db->query("
            SELECT e.*, 
                ec.category_name,
                CONCAT(c.firstname, ' ', c.lastname) as created_by_name,
                p.name as project_name
            FROM `" . DB_PREFIX . "expenses` e
            LEFT JOIN `" . DB_PREFIX . "expense_categories` ec ON e.category_id = ec.id
            LEFT JOIN `" . DB_PREFIX . "customer` c ON e.created_by = c.customer_id
            LEFT JOIN `" . DB_PREFIX . "pms_project` p ON e.project_id = p.project_id
            WHERE e.expense_id = '" . (int)$expense_id . "'
            AND e.is_deleted = 0
        ");
        
        $row = $query->row;
        
        // Add full document URL for download
        if (!empty($row['document_path'])) {
            $row['document_url'] = HTTP_SERVER . $row['document_path'];
        } else {
            $row['document_url'] = '';
        }
        
        return $row;
    }
    
    /**
     * Get all expenses for a project with filters
     * @param array $data
     * @return array
     */
    public function getExpenses(array $data = []): array {
        $sql = "
            SELECT e.*, 
                   ec.category_name,
                   CONCAT(c.firstname, ' ', c.lastname) as created_by_name,
                   (SELECT COUNT(*) FROM `" . DB_PREFIX . "expenses_allocation` ea WHERE ea.expense_id = e.expense_id AND ea.is_paid = 0) as unpaid_count
            FROM `" . DB_PREFIX . "expenses` e
            LEFT JOIN `" . DB_PREFIX . "expense_categories` ec ON e.category_id = ec.id
            LEFT JOIN `" . DB_PREFIX . "customer` c ON e.created_by = c.customer_id
            WHERE e.project_id = '" . (int)$data['project_id'] . "'
            AND e.is_deleted = 0
        ";
        
        // Filter by invoice number
        if (!empty($data['filter_invoice'])) {
            $sql .= " AND e.invoice_number LIKE '%" . $this->db->escape($data['filter_invoice']) . "%'";
        }
        
        // Filter by category
        if (!empty($data['filter_category'])) {
            $sql .= " AND e.category_id = '" . (int)$data['filter_category'] . "'";
        }
        
        // Filter by date range
        if (!empty($data['filter_date_from'])) {
            $sql .= " AND DATE(e.expense_date) >= '" . $this->db->escape($data['filter_date_from']) . "'";
        }
        
        if (!empty($data['filter_date_to'])) {
            $sql .= " AND DATE(e.expense_date) <= '" . $this->db->escape($data['filter_date_to']) . "'";
        }
        
        // Filter by amount range
        if (!empty($data['filter_amount_min'])) {
            $sql .= " AND e.total_amount >= '" . (float)$data['filter_amount_min'] . "'";
        }
        
        if (!empty($data['filter_amount_max'])) {
            $sql .= " AND e.total_amount <= '" . (float)$data['filter_amount_max'] . "'";
        }
        
        $sort_data = [
            'e.expense_date',
            'e.invoice_number',
            'category_name',
            'e.total_amount',
            'e.created_at'
        ];
        
        if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
            $sql .= " ORDER BY " . $data['sort'];
        } else {
            $sql .= " ORDER BY e.expense_date";
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
     * Get total expenses count for pagination
     * @param array $data
     * @return int
     */
    public function getTotalExpenses(array $data = []): int {
        $sql = "
            SELECT COUNT(*) as total
            FROM `" . DB_PREFIX . "expenses` e
            WHERE e.project_id = '" . (int)$data['project_id'] . "'
            AND e.is_deleted = 0
        ";
        
        if (!empty($data['filter_invoice'])) {
            $sql .= " AND e.invoice_number LIKE '%" . $this->db->escape($data['filter_invoice']) . "%'";
        }
        
        if (!empty($data['filter_category'])) {
            $sql .= " AND e.category_id = '" . (int)$data['filter_category'] . "'";
        }
        
        if (!empty($data['filter_date_from'])) {
            $sql .= " AND DATE(e.expense_date) >= '" . $this->db->escape($data['filter_date_from']) . "'";
        }
        
        if (!empty($data['filter_date_to'])) {
            $sql .= " AND DATE(e.expense_date) <= '" . $this->db->escape($data['filter_date_to']) . "'";
        }
        
        if (!empty($data['filter_amount_min'])) {
            $sql .= " AND e.total_amount >= '" . (float)$data['filter_amount_min'] . "'";
        }
        
        if (!empty($data['filter_amount_max'])) {
            $sql .= " AND e.total_amount <= '" . (float)$data['filter_amount_max'] . "'";
        }
        
        $query = $this->db->query($sql);
        
        return (int)$query->row['total'];
    }
    
    /**
     * Auto-allocate expense to all shareholders
     * @param int $expense_id
     * @param int $project_id
     * @param float $total_amount
     */
    private function allocateExpenseToShareholders(int $expense_id, int $project_id, float $total_amount): void {
        // Get all active shareholders for this project
        $shareholders = $this->db->query("
            SELECT s.id, s.ownership_units, p.total_shares
            FROM `" . DB_PREFIX . "shareholders` s
            JOIN `" . DB_PREFIX . "pms_project` p ON s.project_id = p.project_id
            WHERE s.project_id = '" . (int)$project_id . "'
            AND s.is_deleted = 0
            AND s.status = 'active'
        ");
        
        foreach ($shareholders->rows as $shareholder) {
            $percentage = ($shareholder['ownership_units'] / $shareholder['total_shares']) * 100;
            $due_amount = $total_amount * ($percentage / 100);
            
            $this->db->query("
                INSERT INTO `" . DB_PREFIX . "expenses_allocation` 
                SET `expense_id` = '" . (int)$expense_id . "',
                    `shareholder_id` = '" . (int)$shareholder['id'] . "',
                    `due_amount` = '" . (float)$due_amount . "',
                    `is_paid` = 0
            ");
        }
    }
    
    /**
     * Update expense allocations when expense is edited
     * @param int $expense_id
     * @param int $project_id
     * @param float $new_amount
     */
    private function updateExpenseAllocations(int $expense_id, int $project_id, float $new_amount): void {
        // Get existing allocations
        $existing = $this->db->query("
            SELECT shareholder_id, due_amount
            FROM `" . DB_PREFIX . "expenses_allocation`
            WHERE expense_id = '" . (int)$expense_id . "'
        ");
        
        // Get all shareholders
        $shareholders = $this->db->query("
            SELECT s.id, s.ownership_units, p.total_shares
            FROM `" . DB_PREFIX . "shareholders` s
            JOIN `" . DB_PREFIX . "pms_project` p ON s.project_id = p.project_id
            WHERE s.project_id = '" . (int)$project_id . "'
            AND s.is_deleted = 0
            AND s.status = 'active'
        ");
        
        // Delete existing allocations that are not paid
        $this->db->query("
            DELETE FROM `" . DB_PREFIX . "expenses_allocation`
            WHERE expense_id = '" . (int)$expense_id . "'
            AND is_paid = 0
        ");
        
        // Re-allocate
        foreach ($shareholders->rows as $shareholder) {
            $percentage = ($shareholder['ownership_units'] / $shareholder['total_shares']) * 100;
            $due_amount = $new_amount * ($percentage / 100);
            
            $this->db->query("
                INSERT INTO `" . DB_PREFIX . "expenses_allocation` 
                SET `expense_id` = '" . (int)$expense_id . "',
                    `shareholder_id` = '" . (int)$shareholder['id'] . "',
                    `due_amount` = '" . (float)$due_amount . "',
                    `is_paid` = 0
            ");
        }
    }
    
    /**
     * Delete expense (soft delete)
     * @param int $expense_id
     */
    public function deleteExpense(int $expense_id): void {
        $this->db->query("
            UPDATE `" . DB_PREFIX . "expenses` 
            SET `is_deleted` = 1 
            WHERE `expense_id` = '" . (int)$expense_id . "'
        ");
        
        // Also soft delete allocations
        $this->db->query("
            UPDATE `" . DB_PREFIX . "expenses_allocation` 
            SET `is_deleted` = 1 
            WHERE `expense_id` = '" . (int)$expense_id . "'
        ");
    }
    
    /**
     * Get expense statistics for dashboard
     * @param int $project_id
     * @return array
     */
    public function getExpenseStats(int $project_id): array {
        $stats = [];
        
        // Total expenses
        $query = $this->db->query("
            SELECT COALESCE(SUM(total_amount), 0) as total
            FROM `" . DB_PREFIX . "expenses`
            WHERE project_id = '" . (int)$project_id . "'
            AND is_deleted = 0
        ");
        $stats['total'] = (float)$query->row['total'];
        
        // This month's expenses
        $query = $this->db->query("
            SELECT COALESCE(SUM(total_amount), 0) as total
            FROM `" . DB_PREFIX . "expenses`
            WHERE project_id = '" . (int)$project_id . "'
            AND MONTH(expense_date) = MONTH(CURDATE())
            AND YEAR(expense_date) = YEAR(CURDATE())
            AND is_deleted = 0
        ");
        $stats['month'] = (float)$query->row['total'];
        
        // This week's expenses
        $query = $this->db->query("
            SELECT COALESCE(SUM(total_amount), 0) as total
            FROM `" . DB_PREFIX . "expenses`
            WHERE project_id = '" . (int)$project_id . "'
            AND YEARWEEK(expense_date) = YEARWEEK(CURDATE())
            AND is_deleted = 0
        ");
        $stats['week'] = (float)$query->row['total'];
        
        // Today's expenses
        $query = $this->db->query("
            SELECT COALESCE(SUM(total_amount), 0) as total
            FROM `" . DB_PREFIX . "expenses`
            WHERE project_id = '" . (int)$project_id . "'
            AND DATE(expense_date) = CURDATE()
            AND is_deleted = 0
        ");
        $stats['today'] = (float)$query->row['total'];
        
        // Category breakdown
        $query = $this->db->query("
            SELECT ec.category_name, COALESCE(SUM(e.total_amount), 0) as total
            FROM `" . DB_PREFIX . "expenses` e
            LEFT JOIN `" . DB_PREFIX . "expense_categories` ec ON e.category_id = ec.id
            WHERE e.project_id = '" . (int)$project_id . "'
            AND e.is_deleted = 0
            GROUP BY ec.id, ec.category_name
            ORDER BY total DESC
            LIMIT 5
        ");
        $stats['categories'] = $query->rows;
        
        return $stats;
    }

    public function getTotalExpenseSum(int $project_id) {
        $sql = "
            SELECT SUM(total) AS total FROM (
                SELECT COALESCE(SUM(total_amount), 0) as total
                            FROM `" . DB_PREFIX . "expenses`
                            WHERE project_id = '" . (int)$project_id . "'
                            AND is_deleted = 0
                UNION ALL 
                SELECT SUM(total_amount) as total FROM `" . DB_PREFIX . "pms_expense_invoice` WHERE project_id = " . (int)$project_id . " AND is_deleted = 0
            ) AS total_expenses
        ";

        $query = $this->db->query($sql);

        return (float)$query->row['total'];
    }
    
    /**
     * Upload document for expense
     * @param array $file
     * @return string
     */
    public function uploadDocument(array $file): string {
        $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($extension, $allowed_extensions)) {
            throw new \Exception('Invalid file type. Allowed: PDF, JPG, PNG, DOC');
        }
        
        if ($file['size'] > 5242880) { // 5MB
            throw new \Exception('File size too large. Maximum 5MB allowed.');
        }
        
        $upload_dir = 'image/pms/expenses/' . date('Y/m/');
        
        if (!is_dir(DIR_IMAGE . 'pms/expenses/' . date('Y/m/'))) {
            mkdir(DIR_IMAGE . 'pms/expenses/' . date('Y/m/'), 0777, true);
        }
        
        $filename = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file['name']);
        $filepath = $upload_dir . $filename;

        
        
        move_uploaded_file($file['tmp_name'], DIR_IMAGE . 'pms/expenses/' . date('Y/m/') . $filename);
        
        return $filepath;
    }

    public function getExpenseBreakdownByCategory(int $project_id) {
        $sql = "
            SELECT
                c.category_name,
                COUNT(*) AS total_expenses,
                SUM(e.total_amount) AS total_amount,
                ROUND(
                    SUM(e.total_amount) * 100.0 /
                    (SELECT SUM(total_amount)
                    FROM " . DB_PREFIX . "expenses
                    WHERE is_deleted = 0),
                    2
                ) AS percentage
            FROM " . DB_PREFIX . "expenses e
            LEFT JOIN " . DB_PREFIX . "expense_categories c
                ON c.id = e.category_id
            WHERE e.is_deleted = 0 AND e.project_id = " . $project_id . "
            GROUP BY e.category_id, c.category_name
            ORDER BY total_amount DESC
        ";

        $query = $this->db->query($sql);

        return $query->rows;
    }
}
<?php
namespace Opencart\Catalog\Model\Pms;

class ExpenseInvoice extends \Opencart\System\Engine\Model {

    /**
     * Add a new expense invoice with items
     */
    public function addInvoice(array $data): int {
        // Start transaction
        $this->db->query("START TRANSACTION");
        
        try {
            // Insert invoice
            $this->db->query("
                INSERT INTO `" . DB_PREFIX . "pms_expense_invoice` 
                SET `project_id` = '" . (int)$data['project_id'] . "',
                    `supplier_id` = '" . (int)$data['supplier_id'] . "',
                    `invoice_number` = '" . $this->db->escape($data['invoice_number']) . "',
                    `invoice_date` = '" . $this->db->escape($data['invoice_date']) . "',
                    `due_date` = '" . $this->db->escape($data['due_date']) . "',
                    `sub_total` = '" . (float)$data['sub_total'] . "',
                    `tax_amount` = '" . (float)$data['tax_amount'] . "',
                    `discount_amount` = '" . (float)$data['discount_amount'] . "',
                    `total_amount` = '" . (float)$data['total_amount'] . "',
                    `currency` = '" . $this->db->escape($data['currency']) . "',
                    `invoice_document` = '" . $this->db->escape($data['invoice_document']) . "',
                    `notes` = '" . $this->db->escape($data['notes']) . "',
                    `created_by` = '" . (int)$this->customer->getId() . "',
                    `created_at` = NOW()
            ");
            
            $invoice_id = $this->db->getLastId();
            
            // Insert invoice items
            if (isset($data['items']) && !empty($data['items'])) {
                foreach ($data['items'] as $item) {
                    $this->db->query("
                        INSERT INTO `" . DB_PREFIX . "pms_expense_invoice_item` 
                        SET `invoice_id` = '" . (int)$invoice_id . "',
                            `expense_category_id` = '" . (int)$item['category_id'] . "',
                            `item_description` = '" . $this->db->escape($item['description']) . "',
                            `quantity` = '" . (float)$item['quantity'] . "',
                            `unit_price` = '" . (float)$item['unit_price'] . "',
                            `total_price` = '" . (float)$item['total_price'] . "',
                            `notes` = '" . $this->db->escape($item['notes']) . "'
                    ");
                    
                    // Create expense and allocation
                    //$this->createExpenseFromInvoiceItem($invoice_id, $item);
                }
            }
            
            $this->db->query("COMMIT");
            
            return $invoice_id;
            
        } catch (\Exception $e) {
            $this->db->query("ROLLBACK");
            throw $e;
        }
    }

    /**
     * Update expense invoice
     */
    public function updateInvoice(int $invoice_id, array $data): void {
        // Start transaction
        $this->db->query("START TRANSACTION");
        
        try {
            // Update invoice
            $this->db->query("
                UPDATE `" . DB_PREFIX . "pms_expense_invoice` 
                SET `supplier_id` = '" . (int)$data['supplier_id'] . "',
                    `invoice_number` = '" . $this->db->escape($data['invoice_number']) . "',
                    `invoice_date` = '" . $this->db->escape($data['invoice_date']) . "',
                    `due_date` = '" . $this->db->escape($data['due_date']) . "',
                    `sub_total` = '" . (float)$data['sub_total'] . "',
                    `tax_amount` = '" . (float)$data['tax_amount'] . "',
                    `discount_amount` = '" . (float)$data['discount_amount'] . "',
                    `total_amount` = '" . (float)$data['total_amount'] . "',
                    `currency` = '" . $this->db->escape($data['currency']) . "',
                    `invoice_document` = '" . $this->db->escape($data['invoice_document']) . "',
                    `notes` = '" . $this->db->escape($data['notes']) . "',
                    `updated_at` = NOW()
                WHERE `invoice_id` = '" . (int)$invoice_id . "'
            ");
            
            // Delete existing items (soft delete)
            $this->db->query("
                UPDATE `" . DB_PREFIX . "pms_expense_invoice_item` 
                SET `is_deleted` = 1 
                WHERE `invoice_id` = '" . (int)$invoice_id . "'
            ");
            
            // Delete associated expense allocations
            $this->db->query("
                UPDATE `" . DB_PREFIX . "pms_expense_allocation` 
                SET `is_deleted` = 1 
                WHERE `expense_id` IN (
                    SELECT expense_id FROM `" . DB_PREFIX . "pms_expense` 
                    WHERE invoice_id = '" . (int)$invoice_id . "'
                )
            ");
            
            // Insert new items
            if (isset($data['items']) && !empty($data['items'])) {
                foreach ($data['items'] as $item) {
                    $this->db->query("
                        INSERT INTO `" . DB_PREFIX . "pms_expense_invoice_item` 
                        SET `invoice_id` = '" . (int)$invoice_id . "',
                            `expense_category_id` = '" . (int)$item['category_id'] . "',
                            `item_description` = '" . $this->db->escape($item['description']) . "',
                            `quantity` = '" . (float)$item['quantity'] . "',
                            `unit_price` = '" . (float)$item['unit_price'] . "',
                            `total_price` = '" . (float)$item['total_price'] . "',
                            `notes` = '" . $this->db->escape($item['notes']) . "'
                    ");
                    
                    //$this->createExpenseFromInvoiceItem($invoice_id, $item);
                }
            }
            
            $this->db->query("COMMIT");
            
        } catch (\Exception $e) {
            $this->db->query("ROLLBACK");
            throw $e;
        }
    }
    
    /**
     * Create expense and allocation from invoice item
     */
    private function createExpenseFromInvoiceItem(int $invoice_id, array $item): void {
        // Get project_id from invoice
        $invoice = $this->getInvoice($invoice_id);
        
        if ($invoice) {
            // Create expense record
            $this->db->query("
                INSERT INTO `" . DB_PREFIX . "pms_expense` 
                SET `project_id` = '" . (int)$invoice['project_id'] . "',
                    `invoice_id` = '" . (int)$invoice_id . "',
                    `invoice_number` = '" . $this->db->escape($invoice['invoice_number']) . "',
                    `expense_date` = '" . $this->db->escape($invoice['invoice_date']) . "',
                    `category_id` = '" . (int)$item['category_id'] . "',
                    `description` = '" . $this->db->escape($item['description']) . "',
                    `total_amount` = '" . (float)$item['total_price'] . "',
                    `created_by` = '" . (int)$this->customer->getId() . "',
                    `created_at` = NOW()
            ");
            
            $expense_id = $this->db->getLastId();
            
            // Auto-allocate to shareholders
            //$this->allocateExpenseToShareholders($expense_id, $invoice['project_id'], $item['total_price']);
        }
    }
    
    /**
     * Auto-allocate expense to shareholders
     */
    private function allocateExpenseToShareholders(int $expense_id, int $project_id, float $amount): void {
        $shareholders = $this->db->query("
            SELECT s.shareholder_id, s.ownership_units, p.total_shares
            FROM `" . DB_PREFIX . "pms_shareholder` s
            JOIN `" . DB_PREFIX . "pms_project` p ON s.project_id = p.project_id
            WHERE s.project_id = '" . (int)$project_id . "'
            AND s.is_deleted = 0
            AND s.status = 'active'
        ");
        
        foreach ($shareholders->rows as $shareholder) {
            $percentage = ($shareholder['ownership_units'] / $shareholder['total_shares']) * 100;
            $due_amount = $amount * ($percentage / 100);
            
            $this->db->query("
                INSERT INTO `" . DB_PREFIX . "pms_expense_allocation` 
                SET `expense_id` = '" . (int)$expense_id . "',
                    `shareholder_id` = '" . (int)$shareholder['shareholder_id'] . "',
                    `due_amount` = '" . (float)$due_amount . "',
                    `is_paid` = 0
            ");
        }
    }
    
    /**
     * Get invoice by ID
     */
    public function getInvoice(int $invoice_id): array {
        $query = $this->db->query("
            SELECT i.*, 
                s.supplier_name, s.company_name, s.supplier_code,
                CONCAT(c.firstname, ' ', c.lastname) as created_by_name
            FROM `" . DB_PREFIX . "pms_expense_invoice` i
            LEFT JOIN `" . DB_PREFIX . "pms_supplier` s ON i.supplier_id = s.supplier_id
            LEFT JOIN `" . DB_PREFIX . "customer` c ON i.created_by = c.customer_id
            WHERE i.invoice_id = '" . (int)$invoice_id . "'
            AND i.is_deleted = 0
        ");
        
        return $query->row;
    }
    
    /**
     * Get invoice items
     */
    public function getInvoiceItems(int $invoice_id): array {
        $query = $this->db->query("
            SELECT ii.*, ec.category_name
            FROM `" . DB_PREFIX . "pms_expense_invoice_item` ii
            LEFT JOIN `" . DB_PREFIX . "expense_categories` ec ON ii.expense_category_id = ec.id
            WHERE ii.invoice_id = '" . (int)$invoice_id . "'
            AND ii.is_deleted = 0
        ");
        
        return $query->rows;
    }
    
    /**
     * Get all invoices for a project
     */
    public function getInvoices(array $data = []): array {
        $sql = "
            SELECT i.*, 
                   s.supplier_name, s.supplier_code,
                   (SELECT COUNT(*) FROM `" . DB_PREFIX . "pms_expense_invoice_payment` ip WHERE ip.invoice_id = i.invoice_id AND ip.is_deleted = 0) as payment_count,
                   (SELECT COALESCE(SUM(amount), 0) FROM `" . DB_PREFIX . "pms_expense_invoice_payment` ip WHERE ip.invoice_id = i.invoice_id AND ip.is_deleted = 0) as paid_amount
            FROM `" . DB_PREFIX . "pms_expense_invoice` i
            LEFT JOIN `" . DB_PREFIX . "pms_supplier` s ON i.supplier_id = s.supplier_id
            WHERE i.project_id = '" . (int)$data['project_id'] . "'
            AND i.is_deleted = 0
        ";
        
        if (!empty($data['filter_invoice_no'])) {
            $sql .= " AND i.invoice_number LIKE '%" . $this->db->escape($data['filter_invoice_no']) . "%'";
        }
        
        if (!empty($data['filter_supplier'])) {
            $sql .= " AND i.supplier_id = '" . (int)$data['filter_supplier'] . "'";
        }
        
        if (!empty($data['filter_payment_status'])) {
            $sql .= " AND i.payment_status = '" . $this->db->escape($data['filter_payment_status']) . "'";
        }
        
        if (!empty($data['filter_date_from'])) {
            $sql .= " AND DATE(i.invoice_date) >= '" . $this->db->escape($data['filter_date_from']) . "'";
        }
        
        if (!empty($data['filter_date_to'])) {
            $sql .= " AND DATE(i.invoice_date) <= '" . $this->db->escape($data['filter_date_to']) . "'";
        }
        
        $sort_data = ['i.invoice_date', 'i.invoice_number', 's.supplier_name', 'i.total_amount', 'i.payment_status'];
        
        if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
            $sql .= " ORDER BY " . $data['sort'];
        } else {
            $sql .= " ORDER BY i.invoice_date";
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
     * Get total invoices count
     */
    public function getTotalInvoices(array $data = []): int {
        $sql = "
            SELECT COUNT(*) as total
            FROM `" . DB_PREFIX . "pms_expense_invoice` i
            WHERE i.project_id = '" . (int)$data['project_id'] . "'
            AND i.is_deleted = 0
        ";
        
        if (!empty($data['filter_invoice_no'])) {
            $sql .= " AND i.invoice_number LIKE '%" . $this->db->escape($data['filter_invoice_no']) . "%'";
        }
        
        if (!empty($data['filter_supplier'])) {
            $sql .= " AND i.supplier_id = '" . (int)$data['filter_supplier'] . "'";
        }
        
        if (!empty($data['filter_payment_status'])) {
            $sql .= " AND i.payment_status = '" . $this->db->escape($data['filter_payment_status']) . "'";
        }
        
        if (!empty($data['filter_date_from'])) {
            $sql .= " AND DATE(i.invoice_date) >= '" . $this->db->escape($data['filter_date_from']) . "'";
        }
        
        if (!empty($data['filter_date_to'])) {
            $sql .= " AND DATE(i.invoice_date) <= '" . $this->db->escape($data['filter_date_to']) . "'";
        }
        
        $query = $this->db->query($sql);
        
        return (int)$query->row['total'];
    }
    
    /**
     * Record invoice payment
     */
    public function addPayment(array $data): int {
        $this->db->query("
            INSERT INTO `" . DB_PREFIX . "pms_expense_invoice_payment` 
            SET `invoice_id` = '" . (int)$data['invoice_id'] . "',
                `amount` = '" . (float)$data['amount'] . "',
                `payment_date` = '" . $this->db->escape($data['payment_date']) . "',
                `payment_method` = '" . $this->db->escape($data['payment_method']) . "',
                `reference_number` = '" . $this->db->escape($data['reference_number']) . "',
                `notes` = '" . $this->db->escape($data['notes']) . "',
                `recorded_by` = '" . (int)$this->customer->getId() . "',
                `created_at` = NOW()
        ");
        
        $payment_id = $this->db->getLastId();
        
        // Update invoice payment status
        $this->updateInvoicePaymentStatus($data['invoice_id']);
        
        return $payment_id;
    }
    
    /**
     * Get total paid amount for invoice
     */
    public function getTotalPaidAmount(int $invoice_id): float {
        $query = $this->db->query("
            SELECT COALESCE(SUM(amount), 0) as total
            FROM `" . DB_PREFIX . "pms_expense_invoice_payment`
            WHERE invoice_id = '" . (int)$invoice_id . "'
            AND is_deleted = 0
        ");
        
        return (float)$query->row['total'];
    }
    
    /**
     * Get invoice payments
     */
    public function getPayments(int $invoice_id): array {
        $query = $this->db->query("
            SELECT ip.*, CONCAT(c.firstname, ' ', c.lastname) as recorded_by_name
            FROM `" . DB_PREFIX . "pms_expense_invoice_payment` ip
            LEFT JOIN `" . DB_PREFIX . "customer` c ON ip.recorded_by = c.customer_id
            WHERE ip.invoice_id = '" . (int)$invoice_id . "'
            AND ip.is_deleted = 0
            ORDER BY ip.payment_date DESC
        ");
        
        return $query->rows;
    }
    
    /**
     * Delete invoice (soft delete)
     */
    public function deleteInvoice(int $invoice_id): void {
        $this->db->query("
            UPDATE `" . DB_PREFIX . "pms_expense_invoice` 
            SET `is_deleted` = 1 
            WHERE `invoice_id` = '" . (int)$invoice_id . "'
        ");
    }
    
    /**
     * Get invoice statistics for dashboard
     */
    public function getInvoiceStats(int $project_id): array {
        $stats = [];
        
        // Total invoices amount
        $query = $this->db->query("
            SELECT COALESCE(SUM(total_amount), 0) as total
            FROM `" . DB_PREFIX . "pms_expense_invoice`
            WHERE project_id = '" . (int)$project_id . "'
            AND is_deleted = 0
        ");
        $stats['total_amount'] = (float)$query->row['total'];
        
        // Paid amount
        $query = $this->db->query("
            SELECT COALESCE(SUM(total_amount), 0) as total
            FROM `" . DB_PREFIX . "pms_expense_invoice`
            WHERE project_id = '" . (int)$project_id . "'
            AND payment_status = 'paid'
            AND is_deleted = 0
        ");
        $stats['paid_amount'] = (float)$query->row['total'];
        
        // Unpaid amount
        $query = $this->db->query("
            SELECT COALESCE(SUM(total_amount), 0) as total
            FROM `" . DB_PREFIX . "pms_expense_invoice`
            WHERE project_id = '" . (int)$project_id . "'
            AND payment_status IN ('unpaid', 'partial', 'overdue')
            AND is_deleted = 0
        ");
        $stats['unpaid_amount'] = (float)$query->row['total'];
        
        // Invoice counts by status
        $query = $this->db->query("
            SELECT payment_status, COUNT(*) as count
            FROM `" . DB_PREFIX . "pms_expense_invoice`
            WHERE project_id = '" . (int)$project_id . "'
            AND is_deleted = 0
            GROUP BY payment_status
        ");
        
        $stats['status_counts'] = [];
        foreach ($query->rows as $row) {
            $stats['status_counts'][$row['payment_status']] = (int)$row['count'];
        }
        
        return $stats;
    }

    /**
     * Add payment to invoice
     * @param array $data
     * @return int
     */
    public function addInvoicePayment(array $data): int {
        $this->db->query("
            INSERT INTO `" . DB_PREFIX . "pms_expense_invoice_payment` 
            SET `invoice_id` = '" . (int)$data['invoice_id'] . "',
                `amount` = '" . (float)$data['amount'] . "',
                `payment_date` = '" . $this->db->escape($data['payment_date']) . "',
                `payment_method` = '" . $this->db->escape($data['payment_method']) . "',
                `reference_number` = '" . $this->db->escape($data['reference_number']) . "',
                `notes` = '" . $this->db->escape($data['notes']) . "',
                `recorded_by` = '" . (int)$this->customer->getId() . "',
                `created_at` = NOW()
        ");
        
        $payment_id = $this->db->getLastId();
        
        // Update invoice payment status
        $this->updateInvoicePaymentStatus($data['invoice_id']);
        
        return $payment_id;
    }

    /**
     * Get all payments for an invoice
     * @param int $invoice_id
     * @return array
     */
    public function getInvoicePayments(int $invoice_id): array {
        $query = $this->db->query("
            SELECT ip.*, 
                CONCAT(c.firstname, ' ', c.lastname) as recorded_by_name
            FROM `" . DB_PREFIX . "pms_expense_invoice_payment` ip
            LEFT JOIN `" . DB_PREFIX . "customer` c ON ip.recorded_by = c.customer_id
            WHERE ip.invoice_id = '" . (int)$invoice_id . "'
            AND ip.is_deleted = 0
            ORDER BY ip.payment_date DESC, ip.created_at DESC
        ");
        
        return $query->rows;
    }

    /**
     * Get total paid amount for an invoice
     * @param int $invoice_id
     * @return float
     */
    public function getTotalPaidForInvoice(int $invoice_id): float {
        $query = $this->db->query("
            SELECT COALESCE(SUM(amount), 0) as total
            FROM `" . DB_PREFIX . "pms_expense_invoice_payment`
            WHERE invoice_id = '" . (int)$invoice_id . "'
            AND is_deleted = 0
        ");
        
        return (float)$query->row['total'];
    }

    /**
     * Update invoice payment status based on total payments
     * @param int $invoice_id
     */
    private function updateInvoicePaymentStatus(int $invoice_id): void {
        $invoice = $this->getInvoice($invoice_id);
        $total_paid = $this->getTotalPaidForInvoice($invoice_id);
        $total_amount = $invoice['total_amount'];
        
        if ($total_paid >= $total_amount) {
            $status = 'paid';
        } elseif ($total_paid > 0) {
            $status = 'partial';
        } else {
            $status = 'unpaid';
        }
        
        // Check if overdue (due date passed and not fully paid)
        if ($status != 'paid' && strtotime($invoice['due_date']) < time()) {
            $status = 'overdue';
        }
        
        $this->db->query("
            UPDATE `" . DB_PREFIX . "pms_expense_invoice` 
            SET `payment_status` = '" . $this->db->escape($status) . "',
                `payment_date` = " . ($status == 'paid' ? "NOW()" : "NULL") . "
            WHERE `invoice_id` = '" . (int)$invoice_id . "'
        ");
    }

    /**
     * Delete invoice payment (soft delete)
     * @param int $payment_id
     */
    public function deleteInvoicePayment(int $payment_id): void {
        // Get invoice_id before deleting
        $query = $this->db->query("
            SELECT invoice_id 
            FROM `" . DB_PREFIX . "pms_expense_invoice_payment`
            WHERE payment_id = '" . (int)$payment_id . "'
        ");
        
        $invoice_id = $query->row['invoice_id'] ?? 0;
        
        $this->db->query("
            UPDATE `" . DB_PREFIX . "pms_expense_invoice_payment` 
            SET `is_deleted` = 1 
            WHERE `payment_id` = '" . (int)$payment_id . "'
        ");
        
        // Update invoice payment status after deletion
        if ($invoice_id) {
            $this->updateInvoicePaymentStatus($invoice_id);
        }
    }

    /**
     * Get payment methods for dropdown
     * @return array
     */
    public function getPaymentMethods(): array {
        return [
            'bkash' => 'bKash',
            'nagad' => 'Nagad',
            'rocket' => 'Rocket',
            'bank' => 'Bank Transfer',
            'cash' => 'Cash',
            'cheque' => 'Cheque'
        ];
    }
}
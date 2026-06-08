<?php
namespace Opencart\Catalog\Model\Pms;

class Bill extends \Opencart\System\Engine\Model {

    /**
     * Get all bill types
     * @return array
     */
    public function getBillTypes(): array {
        $query = $this->db->query("
            SELECT * 
            FROM `" . DB_PREFIX . "pms_bill_type` 
            WHERE is_active = 1 
            ORDER BY sort_order ASC
        ");
        
        return $query->rows;
    }

    /**
     * Get bill type by ID
     * @param int $bill_type_id
     * @return array
     */
    public function getBillType(int $bill_type_id): array {
        $query = $this->db->query("
            SELECT * 
            FROM `" . DB_PREFIX . "pms_bill_type` 
            WHERE bill_type_id = '" . (int)$bill_type_id . "'
        ");
        
        return $query->row;
    }

    /**
     * Generate monthly bills for all apartments
     * @param array $data
     * @return array
     */
    public function generateMonthlyBills(array $data): array {
        $result = ['success' => true, 'generated' => 0, 'errors' => []];
        
        $project_id = (int)$data['project_id'];
        $bill_month = $data['bill_month'];
        $bill_type_id = (int)$data['bill_type_id'];
        $total_amount = (float)$data['total_amount'];
        
        // Check if bills already generated for this month and type
        $check = $this->db->query("
            SELECT COUNT(*) as total
            FROM `" . DB_PREFIX . "pms_bill_summary`
            WHERE project_id = '" . (int)$project_id . "'
            AND bill_month = '" . $this->db->escape($bill_month) . "'
            AND bill_type_id = '" . (int)$bill_type_id . "'
        ");
        
        if ($check->row['total'] > 0) {
            $result['success'] = false;
            $result['errors'][] = 'Bills already generated for this month and bill type.';
            return $result;
        }
        
        // Get all active apartments with shareholders
        $apartments = $this->db->query("
            SELECT a.id, a.apartment_number, a.shareholder_id,
                   CONCAT(c.firstname, ' ', c.lastname) as shareholder_name
            FROM `" . DB_PREFIX . "pms_apartment` a
            LEFT JOIN `" . DB_PREFIX . "shareholders` s ON a.shareholder_id = s.id
            LEFT JOIN `" . DB_PREFIX . "customer` c ON s.user_id = c.customer_id
            WHERE a.project_id = '" . (int)$project_id . "'
            AND a.is_active = 1
            AND a.is_deleted = 0
            AND a.shareholder_id IS NOT NULL
        ");
        
        $total_apartments = $apartments->num_rows;
        
        if ($total_apartments == 0) {
            $result['success'] = false;
            $result['errors'][] = 'No active apartments found with assigned shareholders.';
            return $result;
        }
        
        // Calculate equal share
        $equal_share = $total_amount / $total_apartments;
        
        // Start transaction
        $this->db->query("START TRANSACTION");
        
        try {
            // Create bill summary
            $this->db->query("
                INSERT INTO `" . DB_PREFIX . "pms_bill_summary` 
                SET `project_id` = '" . (int)$project_id . "',
                    `bill_month` = '" . $this->db->escape($bill_month) . "',
                    `bill_type_id` = '" . (int)$bill_type_id . "',
                    `total_amount` = '" . (float)$total_amount . "',
                    `equal_share` = '" . (float)$equal_share . "',
                    `total_apartments` = '" . (int)$total_apartments . "',
                    `generated_by` = '" . (int)$this->customer->getId() . "'
            ");
            
            // Create individual bills for each apartment
            foreach ($apartments->rows as $apartment) {
                $due_date = date('Y-m-d', strtotime($bill_month . ' + 15 days'));
                
                $this->db->query("
                    INSERT INTO `" . DB_PREFIX . "pms_monthly_bill` 
                    SET `project_id` = '" . (int)$project_id . "',
                        `apartment_id` = '" . (int)$apartment['id'] . "',
                        `shareholder_id` = '" . (int)$apartment['shareholder_id'] . "',
                        `bill_month` = '" . $this->db->escape($bill_month) . "',
                        `bill_type_id` = '" . (int)$bill_type_id . "',
                        `amount` = '" . (float)$equal_share . "',
                        `due_date` = '" . $this->db->escape($due_date) . "',
                        `created_by` = '" . (int)$this->customer->getId() . "'
                ");
                
                $result['generated']++;
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
     * Generate all bills at once (multiple types)
     * @param array $data
     * @return array
     */
    public function generateAllBills(array $data): array {
        $result = ['success' => true, 'generated' => [], 'errors' => []];
        
        $bills_data = $data['bills']; // Array of [bill_type_id => amount]
        $bill_month = $data['bill_month'];
        $project_id = (int)$data['project_id'];
        
        foreach ($bills_data as $bill_type_id => $amount) {
            if ($amount > 0) {
                $bill_result = $this->generateMonthlyBills([
                    'project_id' => $project_id,
                    'bill_month' => $bill_month,
                    'bill_type_id' => $bill_type_id,
                    'total_amount' => $amount
                ]);
                
                if ($bill_result['success']) {
                    $bill_type = $this->getBillType($bill_type_id);
                    $result['generated'][] = [
                        'bill_type' => $bill_type['name'],
                        'count' => $bill_result['generated']
                    ];
                } else {
                    $result['errors'][] = $bill_result['errors'];
                }
            }
        }
        
        return $result;
    }

    /**
     * Get bills for a shareholder
     * @param int $shareholder_id
     * @param array $data
     * @return array
     */
    public function getShareholderBills(int $shareholder_id, array $data = []): array {
        $sql = "
            SELECT b.*, 
                   bt.name as bill_type_name,
                   a.apartment_number,
                   a.floor_number
            FROM `" . DB_PREFIX . "pms_monthly_bill` b
            LEFT JOIN `" . DB_PREFIX . "pms_bill_type` bt ON b.bill_type_id = bt.bill_type_id
            LEFT JOIN `" . DB_PREFIX . "pms_apartment` a ON b.apartment_id = a.id
            WHERE b.shareholder_id = '" . (int)$shareholder_id . "'
            AND b.is_deleted = 0
        ";
        
        if (!empty($data['filter_status'])) {
            if ($data['filter_status'] == 'paid') {
                $sql .= " AND b.is_paid = 1";
            } elseif ($data['filter_status'] == 'unpaid') {
                $sql .= " AND b.is_paid = 0";
            }
        }
        
        if (!empty($data['filter_month'])) {
            $sql .= " AND DATE_FORMAT(b.bill_month, '%Y-%m') = '" . $this->db->escape($data['filter_month']) . "'";
        }
        
        if (!empty($data['filter_bill_type'])) {
            $sql .= " AND b.bill_type_id = '" . (int)$data['filter_bill_type'] . "'";
        }
        
        $sql .= " ORDER BY b.bill_month DESC, bt.sort_order ASC";
        
        $query = $this->db->query($sql);
        
        return $query->rows;
    }

    /**
     * Get bills for an apartment
     * @param int $apartment_id
     * @param array $data
     * @return array
     */
    public function getApartmentBills(int $apartment_id, array $data = []): array {
        $sql = "
            SELECT b.*, 
                   bt.name as bill_type_name
            FROM `" . DB_PREFIX . "pms_monthly_bill` b
            LEFT JOIN `" . DB_PREFIX . "pms_bill_type` bt ON b.bill_type_id = bt.bill_type_id
            WHERE b.apartment_id = '" . (int)$apartment_id . "'
            AND b.is_deleted = 0
        ";
        
        if (!empty($data['filter_status'])) {
            if ($data['filter_status'] == 'paid') {
                $sql .= " AND b.is_paid = 1";
            } elseif ($data['filter_status'] == 'unpaid') {
                $sql .= " AND b.is_paid = 0";
            }
        }
        
        $sql .= " ORDER BY b.bill_month DESC, bt.sort_order ASC";
        
        $query = $this->db->query($sql);
        
        return $query->rows;
    }

    /**
     * Get all bills for a project (manager view)
     * @param array $data
     * @return array
     */
    public function getProjectBills(array $data = []): array {
        $sql = "
            SELECT b.*, 
                   bt.name as bill_type_name,
                   a.apartment_number,
                   a.floor_number,
                   CONCAT(c.firstname, ' ', c.lastname) as shareholder_name,
                   s.shareholder_code
            FROM `" . DB_PREFIX . "pms_monthly_bill` b
            LEFT JOIN `" . DB_PREFIX . "pms_bill_type` bt ON b.bill_type_id = bt.bill_type_id
            LEFT JOIN `" . DB_PREFIX . "pms_apartment` a ON b.apartment_id = a.id
            LEFT JOIN `" . DB_PREFIX . "shareholders` s ON b.shareholder_id = s.id
            LEFT JOIN `" . DB_PREFIX . "customer` c ON s.user_id = c.customer_id
            WHERE b.project_id = '" . (int)$data['project_id'] . "'
            AND b.is_deleted = 0
        ";
        
        if (!empty($data['filter_month'])) {
            $sql .= " AND DATE_FORMAT(b.bill_month, '%Y-%m') = '" . $this->db->escape($data['filter_month']) . "'";
        }
        
        if (!empty($data['filter_bill_type'])) {
            $sql .= " AND b.bill_type_id = '" . (int)$data['filter_bill_type'] . "'";
        }
        
        if (!empty($data['filter_status'])) {
            if ($data['filter_status'] == 'paid') {
                $sql .= " AND b.is_paid = 1";
            } elseif ($data['filter_status'] == 'unpaid') {
                $sql .= " AND b.is_paid = 0";
            }
        }
        
        if (!empty($data['filter_apartment'])) {
            $sql .= " AND a.apartment_number LIKE '%" . $this->db->escape($data['filter_apartment']) . "%'";
        }
        
        $sort_data = [
            'b.bill_month',
            'a.apartment_number',
            'bt.sort_order',
            'b.amount',
            'b.is_paid'
        ];
        
        if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
            $sql .= " ORDER BY " . $data['sort'];
        } else {
            $sql .= " ORDER BY b.bill_month DESC, a.apartment_number ASC";
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
     * Get total bills count for pagination
     * @param array $data
     * @return int
     */
    public function getTotalProjectBills(array $data = []): int {
        $sql = "
            SELECT COUNT(*) as total
            FROM `" . DB_PREFIX . "pms_monthly_bill` b
            LEFT JOIN `" . DB_PREFIX . "pms_apartment` a ON b.apartment_id = a.id
            WHERE b.project_id = '" . (int)$data['project_id'] . "'
            AND b.is_deleted = 0
        ";
        
        if (!empty($data['filter_month'])) {
            $sql .= " AND DATE_FORMAT(b.bill_month, '%Y-%m') = '" . $this->db->escape($data['filter_month']) . "'";
        }
        
        if (!empty($data['filter_bill_type'])) {
            $sql .= " AND b.bill_type_id = '" . (int)$data['filter_bill_type'] . "'";
        }
        
        if (!empty($data['filter_status'])) {
            if ($data['filter_status'] == 'paid') {
                $sql .= " AND b.is_paid = 1";
            } elseif ($data['filter_status'] == 'unpaid') {
                $sql .= " AND b.is_paid = 0";
            }
        }
        
        if (!empty($data['filter_apartment'])) {
            $sql .= " AND a.apartment_number LIKE '%" . $this->db->escape($data['filter_apartment']) . "%'";
        }
        
        $query = $this->db->query($sql);
        
        return (int)$query->row['total'];
    }

    /**
     * Get bill summary by month
     * @param int $project_id
     * @param string $month
     * @return array
     */
    public function getBillSummaryByMonth(int $project_id, string $month): array {
        $query = $this->db->query("
            SELECT bs.*, bt.name as bill_type_name
            FROM `" . DB_PREFIX . "pms_bill_summary` bs
            LEFT JOIN `" . DB_PREFIX . "pms_bill_type` bt ON bs.bill_type_id = bt.bill_type_id
            WHERE bs.project_id = '" . (int)$project_id . "'
            AND DATE_FORMAT(bs.bill_month, '%Y-%m') = '" . $this->db->escape($month) . "'
            ORDER BY bt.sort_order ASC
        ");
        
        return $query->rows;
    }

    /**
     * Get collection summary for a month
     * @param int $project_id
     * @param string $month
     * @return array
     */
    public function getCollectionSummary(int $project_id, string $month): array {
        $query = $this->db->query("
            SELECT 
                bt.bill_type_id,
                bt.name as bill_type_name,
                COUNT(b.bill_id) as total_bills,
                SUM(b.amount) as total_amount,
                SUM(CASE WHEN b.is_paid = 1 THEN b.amount ELSE 0 END) as collected_amount,
                SUM(CASE WHEN b.is_paid = 0 THEN b.amount ELSE 0 END) as pending_amount,
                COUNT(CASE WHEN b.is_paid = 1 THEN 1 END) as paid_count,
                COUNT(CASE WHEN b.is_paid = 0 THEN 1 END) as pending_count
            FROM `" . DB_PREFIX . "pms_monthly_bill` b
            LEFT JOIN `" . DB_PREFIX . "pms_bill_type` bt ON b.bill_type_id = bt.bill_type_id
            WHERE b.project_id = '" . (int)$project_id . "'
            AND DATE_FORMAT(b.bill_month, '%Y-%m') = '" . $this->db->escape($month) . "'
            AND b.is_deleted = 0
            GROUP BY bt.bill_type_id, bt.name
            ORDER BY bt.sort_order ASC
        ");
        
        return $query->rows;
    }

    /**
     * Pay a single bill
     * @param int $bill_id
     * @param int $payment_id
     * @return bool
     */
    public function payBill(int $bill_id, int $payment_id): bool {
        $this->db->query("
            UPDATE `" . DB_PREFIX . "pms_monthly_bill` 
            SET `is_paid` = 1,
                `payment_id` = '" . (int)$payment_id . "',
                `paid_date` = NOW()
            WHERE `bill_id` = '" . (int)$bill_id . "'
        ");
        
        return true;
    }

    /**
     * Pay multiple bills at once
     * @param array $bill_ids
     * @param int $payment_id
     * @return int
     */
    public function payMultipleBills(array $bill_ids, int $payment_id): int {
        $count = 0;
        
        foreach ($bill_ids as $bill_id) {
            $this->payBill($bill_id, $payment_id);
            $count++;
        }
        
        return $count;
    }

    /**
     * Get unpaid bills for a shareholder
     * @param int $shareholder_id
     * @return array
     */
    public function getUnpaidBills(int $shareholder_id): array {
        $query = $this->db->query("
            SELECT b.*, bt.name as bill_type_name, a.apartment_number
            FROM `" . DB_PREFIX . "pms_monthly_bill` b
            LEFT JOIN `" . DB_PREFIX . "pms_bill_type` bt ON b.bill_type_id = bt.bill_type_id
            LEFT JOIN `" . DB_PREFIX . "pms_apartment` a ON b.apartment_id = a.id
            WHERE b.shareholder_id = '" . (int)$shareholder_id . "'
            AND b.is_paid = 0
            AND b.is_deleted = 0
            ORDER BY b.bill_month ASC, bt.sort_order ASC
        ");
        
        return $query->rows;
    }

    /**
     * Get total due for a shareholder (bills only)
     * @param int $shareholder_id
     * @return float
     */
    public function getTotalBillDue(int $shareholder_id): float {
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
     * Get available months for filtering
     * @param int $project_id
     * @return array
     */
    public function getAvailableMonths(int $project_id): array {
        $query = $this->db->query("
            SELECT DISTINCT DATE_FORMAT(bill_month, '%Y-%m') as month,
                   DATE_FORMAT(bill_month, '%M %Y') as month_name
            FROM `" . DB_PREFIX . "pms_monthly_bill`
            WHERE project_id = '" . (int)$project_id . "'
            AND is_deleted = 0
            ORDER BY bill_month DESC
        ");
        
        return $query->rows;
    }

    /**
     * Mark a single bill as paid
     * @param int $bill_id
     * @param array $payment_data
     * @return bool
     */
    public function markBillAsPaid(int $bill_id, array $payment_data): bool {
        $this->db->query("
            UPDATE `" . DB_PREFIX . "pms_monthly_bill` 
            SET `is_paid` = 1,
                `payment_id` = '" . (int)$payment_data['payment_id'] . "',
                `paid_date` = NOW(),
                `updated_at` = NOW()
            WHERE `bill_id` = '" . (int)$bill_id . "'
        ");
        
        return true;
    }

    /**
     * Mark multiple bills as paid
     * @param array $bill_ids
     * @param array $payment_data
     * @return int
     */
    public function markBillsAsPaid(array $bill_ids, array $payment_data): int {
        $count = 0;
        
        foreach ($bill_ids as $bill_id) {
            $this->markBillAsPaid($bill_id, $payment_data);
            $count++;
        }
        
        return $count;
    }

    /**
     * Get unpaid bills for a specific shareholder
     * @param int $shareholder_id
     * @param int $limit
     * @return array
     */
    public function getUnpaidBillsForShareholder(int $shareholder_id, int $limit = 50): array {
        $query = $this->db->query("
            SELECT b.*, 
                bt.name as bill_type_name,
                a.apartment_number,
                a.floor_number
            FROM `" . DB_PREFIX . "pms_monthly_bill` b
            LEFT JOIN `" . DB_PREFIX . "pms_bill_type` bt ON b.bill_type_id = bt.bill_type_id
            LEFT JOIN `" . DB_PREFIX . "pms_apartment` a ON b.apartment_id = a.apartment_id
            WHERE b.shareholder_id = '" . (int)$shareholder_id . "'
            AND b.is_paid = 0
            AND b.is_deleted = 0
            ORDER BY b.bill_month ASC, bt.sort_order ASC
            LIMIT " . (int)$limit . "
        ");
        
        return $query->rows;
    }

    /**
     * Get all unpaid bills for a project (for accountant)
     * @param int $project_id
     * @param array $data
     * @return array
     */
    public function getUnpaidBillsForProject(int $project_id, array $data = []): array {
        $sql = "
            SELECT b.*, 
                bt.name as bill_type_name,
                a.apartment_number,
                a.floor_number,
                CONCAT(c.firstname, ' ', c.lastname) as shareholder_name,
                s.shareholder_code
            FROM `" . DB_PREFIX . "pms_monthly_bill` b
            LEFT JOIN `" . DB_PREFIX . "pms_bill_type` bt ON b.bill_type_id = bt.bill_type_id
            LEFT JOIN `" . DB_PREFIX . "pms_apartment` a ON b.apartment_id = a.id
            LEFT JOIN `" . DB_PREFIX . "shareholders` s ON b.shareholder_id = s.id
            LEFT JOIN `" . DB_PREFIX . "customer` c ON s.user_id = c.customer_id
            WHERE b.project_id = '" . (int)$project_id . "'
            AND b.is_paid = 0
            AND b.is_deleted = 0
        ";
        
        if (!empty($data['filter_shareholder'])) {
            $sql .= " AND (c.firstname LIKE '%" . $this->db->escape($data['filter_shareholder']) . "%' 
                        OR c.lastname LIKE '%" . $this->db->escape($data['filter_shareholder']) . "%')";
        }
        
        if (!empty($data['filter_month'])) {
            $sql .= " AND DATE_FORMAT(b.bill_month, '%Y-%m') = '" . $this->db->escape($data['filter_month']) . "'";
        }
        
        if (!empty($data['filter_bill_type'])) {
            $sql .= " AND b.bill_type_id = '" . (int)$data['filter_bill_type'] . "'";
        }
        
        if (!empty($data['filter_apartment'])) {
            $sql .= " AND a.apartment_number LIKE '%" . $this->db->escape($data['filter_apartment']) . "%'";
        }
        
        $sql .= " ORDER BY b.bill_month ASC, a.apartment_number ASC";
        
        $query = $this->db->query($sql);
        
        return $query->rows;
    }

    /**
     * Get total unpaid bills count
     * @param int $project_id
     * @return int
     */
    public function getTotalUnpaidBillsCount(int $project_id): int {
        $query = $this->db->query("
            SELECT COUNT(*) as total
            FROM `" . DB_PREFIX . "pms_monthly_bill`
            WHERE project_id = '" . (int)$project_id . "'
            AND is_paid = 0
            AND is_deleted = 0
        ");
        
        return (int)$query->row['total'];
    }

    /**
     * Get total unpaid amount for a project
     * @param int $project_id
     * @return float
     */
    public function getTotalUnpaidAmount(int $project_id): float {
        $query = $this->db->query("
            SELECT COALESCE(SUM(amount), 0) as total
            FROM `" . DB_PREFIX . "pms_monthly_bill`
            WHERE project_id = '" . (int)$project_id . "'
            AND is_paid = 0
            AND is_deleted = 0
        ");
        
        return (float)$query->row['total'];
    }

    /**
     * Get payment statistics for bills
     * @param int $project_id
     * @return array
     */
    public function getBillPaymentStats(int $project_id): array {
        $stats = [];
        
        // Total bills amount
        $query = $this->db->query("
            SELECT COALESCE(SUM(amount), 0) as total
            FROM `" . DB_PREFIX . "pms_monthly_bill`
            WHERE project_id = '" . (int)$project_id . "'
            AND is_deleted = 0
        ");
        $stats['total_bills'] = (float)$query->row['total'];
        
        // Paid amount
        $query = $this->db->query("
            SELECT COALESCE(SUM(amount), 0) as total
            FROM `" . DB_PREFIX . "pms_monthly_bill`
            WHERE project_id = '" . (int)$project_id . "'
            AND is_paid = 1
            AND is_deleted = 0
        ");
        $stats['paid_amount'] = (float)$query->row['total'];
        
        // Unpaid amount
        $stats['unpaid_amount'] = $stats['total_bills'] - $stats['paid_amount'];
        
        // Paid percentage
        $stats['paid_percentage'] = $stats['total_bills'] > 0 ? round(($stats['paid_amount'] / $stats['total_bills']) * 100, 2) : 0;
        
        // Total paid bills count
        $query = $this->db->query("
            SELECT COUNT(*) as total
            FROM `" . DB_PREFIX . "pms_monthly_bill`
            WHERE project_id = '" . (int)$project_id . "'
            AND is_paid = 1
            AND is_deleted = 0
        ");
        $stats['paid_count'] = (int)$query->row['total'];
        
        // Total unpaid bills count
        $stats['unpaid_count'] = $this->getTotalUnpaidBillsCount($project_id);
        
        return $stats;
    }

    /**
     * Get bills for logged-in shareholder
     * @param int $shareholder_id
     * @param array $data
     * @return array
     */
    public function getMyBills(int $shareholder_id, array $data = []): array {
        $sql = "
            SELECT b.*, 
                bt.name as bill_type_name,
                bt.slug as bill_type_slug,
                a.apartment_number,
                a.floor_number,
                a.area_sqft,
                CONCAT(c.firstname, ' ', c.lastname) as shareholder_name,
                s.shareholder_code
            FROM `" . DB_PREFIX . "pms_monthly_bill` b
            LEFT JOIN `" . DB_PREFIX . "pms_bill_type` bt ON b.bill_type_id = bt.bill_type_id
            LEFT JOIN `" . DB_PREFIX . "pms_apartment` a ON b.apartment_id = a.id
            LEFT JOIN `" . DB_PREFIX . "shareholders` s ON b.shareholder_id = s.id
            LEFT JOIN `" . DB_PREFIX . "customer` c ON s.user_id = c.customer_id
            WHERE b.shareholder_id = '" . (int)$shareholder_id . "'
            AND b.is_deleted = 0
        ";
        
        if (!empty($data['filter_year'])) {
            $sql .= " AND YEAR(b.bill_month) = '" . (int)$data['filter_year'] . "'";
        }
        
        if (!empty($data['filter_status'])) {
            if ($data['filter_status'] == 'paid') {
                $sql .= " AND b.is_paid = 1";
            } elseif ($data['filter_status'] == 'unpaid') {
                $sql .= " AND b.is_paid = 0";
            }
        }
        
        $sql .= " ORDER BY b.bill_month DESC, bt.sort_order ASC";
        
        $query = $this->db->query($sql);
        
        return $query->rows;
    }

    /**
     * Get bill summary for shareholder dashboard
     * @param int $shareholder_id
     * @return array
     */
    public function getMyBillSummary(int $shareholder_id): array {
        $summary = [];
        
        // Total unpaid amount
        $query = $this->db->query("
            SELECT COALESCE(SUM(amount), 0) as total
            FROM `" . DB_PREFIX . "pms_monthly_bill`
            WHERE shareholder_id = '" . (int)$shareholder_id . "'
            AND is_paid = 0
            AND is_deleted = 0
        ");
        $summary['total_unpaid'] = (float)$query->row['total'];
        
        // Total paid amount
        $query = $this->db->query("
            SELECT COALESCE(SUM(amount), 0) as total
            FROM `" . DB_PREFIX . "pms_monthly_bill`
            WHERE shareholder_id = '" . (int)$shareholder_id . "'
            AND is_paid = 1
            AND is_deleted = 0
        ");
        $summary['total_paid'] = (float)$query->row['total'];
        
        // Total bills count
        $query = $this->db->query("
            SELECT COUNT(*) as total,
                SUM(CASE WHEN is_paid = 1 THEN 1 ELSE 0 END) as paid_count,
                SUM(CASE WHEN is_paid = 0 THEN 1 ELSE 0 END) as unpaid_count
            FROM `" . DB_PREFIX . "pms_monthly_bill`
            WHERE shareholder_id = '" . (int)$shareholder_id . "'
            AND is_deleted = 0
        ");
        $summary['total_bills'] = (int)$query->row['total'];
        $summary['paid_count'] = (int)$query->row['paid_count'];
        $summary['unpaid_count'] = (int)$query->row['unpaid_count'];
        
        // Get months with unpaid bills
        $query = $this->db->query("
            SELECT DISTINCT DATE_FORMAT(bill_month, '%Y-%m') as month,
                DATE_FORMAT(bill_month, '%M %Y') as month_name
            FROM `" . DB_PREFIX . "pms_monthly_bill`
            WHERE shareholder_id = '" . (int)$shareholder_id . "'
            AND is_paid = 0
            AND is_deleted = 0
            ORDER BY bill_month ASC
        ");
        $summary['unpaid_months'] = $query->rows;
        
        return $summary;
    }

    /**
     * Get available years for filtering
     * @param int $shareholder_id
     * @return array
     */
    public function getAvailableYearsForShareholder(int $shareholder_id): array {
        $query = $this->db->query("
            SELECT DISTINCT YEAR(bill_month) as year
            FROM `" . DB_PREFIX . "pms_monthly_bill`
            WHERE shareholder_id = '" . (int)$shareholder_id . "'
            AND is_deleted = 0
            ORDER BY year DESC
        ");
        
        return $query->rows;
    }

    /**
     * Get total unpaid bills count for a specific shareholder
     * @param int $shareholder_id
     * @return int
     */
    public function getTotalUnpaidBillsCountForShareholder(int $shareholder_id): int {
        $query = $this->db->query("
            SELECT COUNT(*) as total
            FROM `" . DB_PREFIX . "pms_monthly_bill`
            WHERE shareholder_id = '" . (int)$shareholder_id . "'
            AND is_paid = 0
            AND is_deleted = 0
        ");
        
        return (int)$query->row['total'];
    }
}
<?php
namespace Opencart\Catalog\Model\Pms;

class Payment extends \Opencart\System\Engine\Model {

    /**
     * Add a new payment
     * @param array $data
     * @return int
     */
    public function addPayment(array $data): int {
        $this->db->query("
            INSERT INTO `" . DB_PREFIX . "pms_payment` 
            SET `project_id` = '" . (int)$data['project_id'] . "',
                `shareholder_id` = '" . (int)$data['shareholder_id'] . "',
                `amount` = '" . (float)$data['amount'] . "',
                `payment_date` = '" . $this->db->escape($data['payment_date']) . "',
                `payment_method` = '" . $this->db->escape($data['payment_method']) . "',
                `reference_number` = '" . $this->db->escape($data['reference_number']) . "',
                `payment_phase_id` = '" . (int)$data['payment_phase_id'] . "',
                `recorded_by` = '" . (int)$this->customer->getId() . "',
                `created_at` = NOW()
        ");
        
        $payment_id = $this->db->getLastId();
        
        // Create payment allocation record
        /*if ($payment_id) {
            $this->db->query("
                INSERT INTO `" . DB_PREFIX . "payment_allocation` 
                SET `shareholder_id` = '" . (int)$data['shareholder_id'] . "',
                    `project_id` = '" . (int)$data['project_id'] . "',
                    `payment_phase_id` = '" . (int)$data['payment_phase_id'] . "',
                    `allocated_amount` = '" . (float)$data['amount'] . "',
                    `notes` = 'Payment received and allocated',
                    `created_by` = '" . (int)$this->customer->getId() . "',
                    `created_at` = NOW()
            ");
        }*/
        
        return $payment_id;
    }

    /**
     * Get all payments for a project with filters
     * @param array $data
     * @return array
     */
    public function getPayments(array $data = []): array {
        $sql = "
            SELECT p.*, 
                   CONCAT(c.firstname, ' ', c.lastname) as shareholder_name,
                   c.email as shareholder_email,
                   s.shareholder_code,
                   pp.name as phase_name,
                   CONCAT(ru.firstname, ' ', ru.lastname) as recorded_by_name
            FROM `" . DB_PREFIX . "pms_payment` p
            LEFT JOIN `" . DB_PREFIX . "shareholders` s ON p.shareholder_id = s.id
            LEFT JOIN `" . DB_PREFIX . "customer` c ON s.user_id = c.customer_id
            LEFT JOIN `" . DB_PREFIX . "payment_phase` pp ON p.payment_phase_id = pp.phase_id
            LEFT JOIN `" . DB_PREFIX . "customer` ru ON p.recorded_by = ru.customer_id
            WHERE p.project_id = '" . (int)$data['project_id'] . "'
            AND p.is_deleted = 0
        ";
        
        if (!empty($data['filter_shareholder'])) {
            $sql .= " AND (c.firstname LIKE '%" . $this->db->escape($data['filter_shareholder']) . "%' 
                        OR c.lastname LIKE '%" . $this->db->escape($data['filter_shareholder']) . "%')";
        }
        
        if (!empty($data['filter_phase'])) {
            $sql .= " AND p.payment_phase_id = '" . (int)$data['filter_phase'] . "'";
        }
        
        if (!empty($data['filter_method'])) {
            $sql .= " AND p.payment_method = '" . $this->db->escape($data['filter_method']) . "'";
        }
        
        if (isset($data['filter_date_from']) && $data['filter_date_from']) {
            $sql .= " AND DATE(p.payment_date) >= '" . $this->db->escape($data['filter_date_from']) . "'";
        }
        
        if (isset($data['filter_date_to']) && $data['filter_date_to']) {
            $sql .= " AND DATE(p.payment_date) <= '" . $this->db->escape($data['filter_date_to']) . "'";
        }
        
        $sort_data = [
            'p.payment_date',
            'p.amount',
            'p.payment_method',
            'shareholder_name',
            'phase_name',
            'p.created_at'
        ];
        
        if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
            $sql .= " ORDER BY " . $data['sort'];
        } else {
            $sql .= " ORDER BY p.payment_date DESC";
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
     * Get total payments count for pagination
     * @param array $data
     * @return int
     */
    public function getTotalPayments(array $data = []): int {
        $sql = "
            SELECT COUNT(*) as total
            FROM `" . DB_PREFIX . "pms_payment` p
            LEFT JOIN `" . DB_PREFIX . "shareholders` s ON p.shareholder_id = s.id
            LEFT JOIN `" . DB_PREFIX . "customer` c ON s.user_id = c.customer_id
            WHERE p.project_id = '" . (int)$data['project_id'] . "'
            AND p.is_deleted = 0
        ";
        
        if (!empty($data['filter_shareholder'])) {
            $sql .= " AND (c.firstname LIKE '%" . $this->db->escape($data['filter_shareholder']) . "%' 
                        OR c.lastname LIKE '%" . $this->db->escape($data['filter_shareholder']) . "%')";
        }
        
        if (!empty($data['filter_phase'])) {
            $sql .= " AND p.payment_phase_id = '" . (int)$data['filter_phase'] . "'";
        }
        
        if (isset($data['filter_date_from']) && $data['filter_date_from']) {
            $sql .= " AND DATE(p.payment_date) >= '" . $this->db->escape($data['filter_date_from']) . "'";
        }
        
        if (isset($data['filter_date_to']) && $data['filter_date_to']) {
            $sql .= " AND DATE(p.payment_date) <= '" . $this->db->escape($data['filter_date_to']) . "'";
        }
        
        $query = $this->db->query($sql);
        
        return (int)$query->row['total'];
    }

    /**
     * Get payment by ID
     * @param int $payment_id
     * @return array
     */
    public function getPayment(int $payment_id): array {
        $query = $this->db->query("
            SELECT p.*, 
                   CONCAT(c.firstname, ' ', c.lastname) as shareholder_name,
                   c.email as shareholder_email,
                   s.shareholder_code,
                   pp.name as phase_name,
                   CONCAT(ru.firstname, ' ', ru.lastname) as recorded_by_name
            FROM `" . DB_PREFIX . "pms_payment` p
            LEFT JOIN `" . DB_PREFIX . "shareholders` s ON p.shareholder_id = s.id
            LEFT JOIN `" . DB_PREFIX . "customer` c ON s.user_id = c.customer_id
            LEFT JOIN `" . DB_PREFIX . "payment_phase` pp ON p.payment_phase_id = pp.phase_id
            LEFT JOIN `" . DB_PREFIX . "customer` ru ON p.recorded_by = ru.customer_id
            WHERE p.id = '" . (int)$payment_id . "'
            AND p.is_deleted = 0
        ");
        
        return $query->row;
    }

    /**
     * Delete payment (soft delete)
     * @param int $payment_id
     */
    public function deletePayment(int $payment_id): void {
        // First, delete associated payment allocation
        $this->db->query("
            UPDATE `" . DB_PREFIX . "payment_allocation` 
            SET `is_deleted` = 1 
            WHERE `shareholder_id` = (SELECT shareholder_id FROM `" . DB_PREFIX . "pms_payment` WHERE payment_id = '" . (int)$payment_id . "')
            AND `created_at` = (SELECT created_at FROM `" . DB_PREFIX . "pms_payment` WHERE payment_id = '" . (int)$payment_id . "')
        ");
        
        // Then soft delete the payment
        $this->db->query("
            UPDATE `" . DB_PREFIX . "pms_payment` 
            SET `is_deleted` = 1 
            WHERE `payment_id` = '" . (int)$payment_id . "'
        ");
    }

    /**
     * Get payment methods for dropdown
     * @return array
     */
    public function getPaymentMethods(): array {
        return [
            'bkash' => 'bKash',
            'nagad' => 'Nagad',
            'rocket' => 'Rocket (DBBL)',
            'bank' => 'Bank Transfer',
            'cash' => 'Cash',
            'check' => 'Cheque'
        ];
    }

    /**
     * Get payment statistics for dashboard
     * @param int $project_id
     * @return array
     */
    public function getPaymentStats(int $project_id): array {
        $stats = [];
        
        // Today's collection
        $query = $this->db->query("
            SELECT COALESCE(SUM(amount), 0) as total
            FROM `" . DB_PREFIX . "pms_payment`
            WHERE project_id = '" . (int)$project_id . "'
            AND DATE(payment_date) = CURDATE()
            AND is_deleted = 0
        ");
        $stats['today'] = (float)$query->row['total'];
        
        // This week's collection
        $query = $this->db->query("
            SELECT COALESCE(SUM(amount), 0) as total
            FROM `" . DB_PREFIX . "pms_payment`
            WHERE project_id = '" . (int)$project_id . "'
            AND YEARWEEK(payment_date) = YEARWEEK(CURDATE())
            AND is_deleted = 0
        ");
        $stats['week'] = (float)$query->row['total'];
        
        // This month's collection
        $query = $this->db->query("
            SELECT COALESCE(SUM(amount), 0) as total
            FROM `" . DB_PREFIX . "pms_payment`
            WHERE project_id = '" . (int)$project_id . "'
            AND MONTH(payment_date) = MONTH(CURDATE())
            AND YEAR(payment_date) = YEAR(CURDATE())
            AND is_deleted = 0
        ");
        $stats['month'] = (float)$query->row['total'];
        
        // Total collection all time
        $stats['total'] = $this->getProjectTotalPayment($project_id);
        
        return $stats;
    }

    public function getProjectTotalPayment(int $project_id): int {
        // Total collection all time
        $query = $this->db->query("
            SELECT COALESCE(SUM(amount), 0) as total
            FROM `" . DB_PREFIX . "pms_payment`
            WHERE project_id = '" . (int)$project_id . "'
            AND is_deleted = 0
        ");
        
        return (int)$query->row['total'];
    }

    /**
     * Get payment phases for dropdown
     * @return array
     */
    public function getPaymentPhases(): array {
        $query = $this->db->query("
            SELECT * 
            FROM `" . DB_PREFIX . "payment_phase` 
            WHERE is_active = 1 
            ORDER BY sort_order ASC
        ");
        
        return $query->rows;
    }

    /**
     * Get payment history for a shareholder
     * @param int $shareholder_id
     * @param int $limit
     * @return array
     */
    public function getPaymentHistory(int $shareholder_id, int $limit = 50): array {
        $query = $this->db->query("
            SELECT p.*, 
                pp.name as phase_name,
                pp.slug as phase_slug,
                CONCAT(u.firstname, ' ', u.lastname) as recorded_by_name
            FROM `" . DB_PREFIX . "pms_payment` p
            LEFT JOIN `" . DB_PREFIX . "payment_phase` pp ON p.payment_phase_id = pp.phase_id
            LEFT JOIN `" . DB_PREFIX . "customer` u ON p.recorded_by = u.customer_id
            WHERE p.shareholder_id = '" . (int)$shareholder_id . "'
            AND p.is_deleted = 0
            ORDER BY p.payment_date DESC
            LIMIT " . (int)$limit . "
        ");
        
        return $query->rows;
    }

    /**
     * Get payment history with filters for a shareholder
     * @param int $shareholder_id
     * @param array $data
     * @return array
     */
    public function getFilteredPaymentHistory(int $shareholder_id, array $data = []): array {
        $sql = "
            SELECT p.*, 
                pp.name as phase_name,
                pp.slug as phase_slug,
                CONCAT(u.firstname, ' ', u.lastname) as recorded_by_name
            FROM `" . DB_PREFIX . "pms_payment` p
            LEFT JOIN `" . DB_PREFIX . "payment_phase` pp ON p.payment_phase_id = pp.phase_id
            LEFT JOIN `" . DB_PREFIX . "customer` u ON p.recorded_by = u.customer_id
            WHERE p.shareholder_id = '" . (int)$shareholder_id . "'
            AND p.is_deleted = 0
        ";
        
        if (!empty($data['filter_year'])) {
            $sql .= " AND YEAR(p.payment_date) = '" . (int)$data['filter_year'] . "'";
        }
        
        if (!empty($data['filter_month'])) {
            $sql .= " AND MONTH(p.payment_date) = '" . (int)$data['filter_month'] . "'";
        }
        
        if (!empty($data['filter_phase'])) {
            $sql .= " AND p.payment_phase_id = '" . (int)$data['filter_phase'] . "'";
        }
        
        $sql .= " ORDER BY p.payment_date DESC";
        
        if (isset($data['limit']) && $data['limit'] > 0) {
            $sql .= " LIMIT " . (int)$data['limit'];
        }
        
        $query = $this->db->query($sql);
        
        return $query->rows;
    }

    /**
     * Get total paid amount for a shareholder
     * @param int $shareholder_id
     * @return float
     */
    public function getTotalPaidAmount(int $shareholder_id): float {
        $query = $this->db->query("
            SELECT COALESCE(SUM(amount), 0) as total
            FROM `" . DB_PREFIX . "pms_payment`
            WHERE shareholder_id = '" . (int)$shareholder_id . "'
            AND is_deleted = 0
        ");
        
        return (float)$query->row['total'];
    }

    /**
     * Get payment summary for a shareholder
     * @param int $shareholder_id
     * @return array
     */
    public function getPaymentSummaryForShareholder(int $shareholder_id): array {
        $summary = [];
        
        // Total paid
        $summary['total_paid'] = $this->getTotalPaidAmount($shareholder_id);

        // Payment count
        $query = $this->db->query("
            SELECT COUNT(*) as total
            FROM `" . DB_PREFIX . "pms_payment`
            WHERE shareholder_id = '" . (int)$shareholder_id . "'
            AND is_deleted = 0
        ");
        $summary['total_payments'] = (int)$query->row['total'];
        
        // Last payment date
        $query = $this->db->query("
            SELECT MAX(payment_date) as last_payment
            FROM `" . DB_PREFIX . "pms_payment`
            WHERE shareholder_id = '" . (int)$shareholder_id . "'
            AND is_deleted = 0
        ");
        $summary['last_payment_date'] = $query->row['last_payment'] ? date('d M Y', strtotime($query->row['last_payment'])) : 'No payments yet';
        
        // Payment by phase
        $query = $this->db->query("
            SELECT pp.name as phase_name, COALESCE(SUM(p.amount), 0) as total
            FROM `" . DB_PREFIX . "pms_payment` p
            LEFT JOIN `" . DB_PREFIX . "payment_phase` pp ON p.payment_phase_id = pp.phase_id
            WHERE p.shareholder_id = '" . (int)$shareholder_id . "'
            AND p.is_deleted = 0
            GROUP BY pp.phase_id, pp.name
        ");
        $summary['payment_by_phase'] = $query->rows;

        
        
        return $summary;
    }

    /**
	 * Get recent payments for manager dashboard
	 * @param int $project_id
	 * @param int $limit
	 * @return array
	 */
	public function getRecentPayments(int $project_id, int $limit = 5): array {
		$query = $this->db->query("
			SELECT 
				p.id,
				p.amount,
				p.payment_date,
				p.payment_method,
				p.reference_number,
				pp.name as phase_name,
				CONCAT(c.firstname, ' ', c.lastname) as shareholder_name,
				s.shareholder_code
			FROM `" . DB_PREFIX . "pms_payment` p
			LEFT JOIN `" . DB_PREFIX . "shareholders` s ON p.shareholder_id = s.id
			LEFT JOIN `" . DB_PREFIX . "customer` c ON s.user_id = c.customer_id
			LEFT JOIN `" . DB_PREFIX . "payment_phase` pp ON p.payment_phase_id = pp.phase_id
			WHERE p.project_id = '" . (int)$project_id . "'
			AND p.is_deleted = 0
			ORDER BY p.payment_date DESC, p.created_at DESC
			LIMIT " . (int)$limit . "
		");
		
		$payments = [];
		foreach ($query->rows as $row) {
			$payments[] = [
				'payment_id'        => $row['id'],
				'shareholder_name'  => $row['shareholder_name'],
				'shareholder_code'  => $row['shareholder_code'],
				'amount'            => $row['amount'],
				'payment_date'      => date('d M Y', strtotime($row['payment_date'])),
				'payment_method'    => ucfirst($row['payment_method']),
				'reference_number'  => $row['reference_number'] ?: '-',
				'phase_name'        => $row['phase_name'] ?: 'N/A'
			];
		}
		
		return $payments;
	}
}
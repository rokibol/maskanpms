<?php
namespace Opencart\Catalog\Model\Pms;

class SubscriptionPaymentRequest extends \Opencart\System\Engine\Model {

    public function addRequest(array $data): int {
        $this->db->query("
            INSERT INTO `" . DB_PREFIX . "pms_subscription_payment_request` 
            SET `project_id` = '" . (int)$data['project_id'] . "',
                `duration_months` = '" . (int)$data['duration_months'] . "',
                `amount` = '" . (float)$data['amount'] . "',
                `bkash_number` = '" . $this->db->escape($data['bkash_number']) . "',
                `transaction_id` = '" . $this->db->escape($data['transaction_id']) . "',
                `status` = 'pending',
                `submitted_by` = '" . (int)$data['submitted_by'] . "',
                `submitted_at` = NOW()
        ");

        return $this->db->getLastId();
    }

    public function getRequestByTransactionId(string $transaction_id): array {
        $query = $this->db->query("
            SELECT * 
            FROM `" . DB_PREFIX . "pms_subscription_payment_request`
            WHERE transaction_id = '" . $this->db->escape($transaction_id) . "'
            AND is_deleted = 0
        ");

        return $query->row;
    }

    public function getRequests(array $data = []): array {
        $sql = "
            SELECT r.*, 
                   p.name as project_name,
                   CONCAT(c.firstname, ' ', c.lastname) as submitted_by_name,
                   CONCAT(ac.firstname, ' ', ac.lastname) as approved_by_name
            FROM `" . DB_PREFIX . "pms_subscription_payment_request` r
            LEFT JOIN `" . DB_PREFIX . "pms_project` p ON r.project_id = p.project_id
            LEFT JOIN `" . DB_PREFIX . "customer` c ON r.submitted_by = c.customer_id
            LEFT JOIN `" . DB_PREFIX . "customer` ac ON r.approved_by = ac.customer_id
            WHERE r.is_deleted = 0
        ";

        if (!empty($data['filter_project'])) {
            $sql .= " AND p.name LIKE '%" . $this->db->escape($data['filter_project']) . "%'";
        }

        if (!empty($data['filter_bkash_number'])) {
            $sql .= " AND r.bkash_number LIKE '%" . $this->db->escape($data['filter_bkash_number']) . "%'";
        }

        if (!empty($data['filter_transaction_id'])) {
            $sql .= " AND r.transaction_id LIKE '%" . $this->db->escape($data['filter_transaction_id']) . "%'";
        }

        if (!empty($data['filter_amount_min'])) {
            $sql .= " AND r.amount >= '" . (float)$data['filter_amount_min'] . "'";
        }

        if (!empty($data['filter_amount_max'])) {
            $sql .= " AND r.amount <= '" . (float)$data['filter_amount_max'] . "'";
        }

        if (!empty($data['filter_status'])) {
            $sql .= " AND r.status = '" . $this->db->escape($data['filter_status']) . "'";
        }

        if (!empty($data['filter_date_from'])) {
            $sql .= " AND DATE(r.submitted_at) >= '" . $this->db->escape($data['filter_date_from']) . "'";
        }

        if (!empty($data['filter_date_to'])) {
            $sql .= " AND DATE(r.submitted_at) <= '" . $this->db->escape($data['filter_date_to']) . "'";
        }

        $sort_data = ['r.request_id', 'p.name', 'r.bkash_number', 'r.transaction_id', 'r.amount', 'r.status', 'r.submitted_at'];
        
        if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
            $sql .= " ORDER BY " . $data['sort'];
        } else {
            $sql .= " ORDER BY r.submitted_at DESC";
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

    public function getTotalRequests(array $data = []): int {
        $sql = "
            SELECT COUNT(*) as total
            FROM `" . DB_PREFIX . "pms_subscription_payment_request` r
            LEFT JOIN `" . DB_PREFIX . "pms_project` p ON r.project_id = p.project_id
            WHERE r.is_deleted = 0
        ";

        if (!empty($data['filter_project'])) {
            $sql .= " AND p.name LIKE '%" . $this->db->escape($data['filter_project']) . "%'";
        }

        if (!empty($data['filter_bkash_number'])) {
            $sql .= " AND r.bkash_number LIKE '%" . $this->db->escape($data['filter_bkash_number']) . "%'";
        }

        if (!empty($data['filter_transaction_id'])) {
            $sql .= " AND r.transaction_id LIKE '%" . $this->db->escape($data['filter_transaction_id']) . "%'";
        }

        if (!empty($data['filter_amount_min'])) {
            $sql .= " AND r.amount >= '" . (float)$data['filter_amount_min'] . "'";
        }

        if (!empty($data['filter_amount_max'])) {
            $sql .= " AND r.amount <= '" . (float)$data['filter_amount_max'] . "'";
        }

        if (!empty($data['filter_status'])) {
            $sql .= " AND r.status = '" . $this->db->escape($data['filter_status']) . "'";
        }

        if (!empty($data['filter_date_from'])) {
            $sql .= " AND DATE(r.submitted_at) >= '" . $this->db->escape($data['filter_date_from']) . "'";
        }

        if (!empty($data['filter_date_to'])) {
            $sql .= " AND DATE(r.submitted_at) <= '" . $this->db->escape($data['filter_date_to']) . "'";
        }

        $query = $this->db->query($sql);

        return (int)$query->row['total'];
    }

    public function approveRequest(int $request_id, int $admin_id): bool {
        // Start transaction
        $this->db->query("START TRANSACTION");

        try {
            // Get request details
            $request = $this->db->query("
                SELECT * 
                FROM `" . DB_PREFIX . "pms_subscription_payment_request`
                WHERE request_id = '" . (int)$request_id . "'
                AND status = 'pending'
            ");

            if (!$request->num_rows) {
                throw new \Exception('Request not found or already processed');
            }

            // Update request status
            $this->db->query("
                UPDATE `" . DB_PREFIX . "pms_subscription_payment_request` 
                SET `status` = 'approved',
                    `approved_by` = '" . (int)$admin_id . "',
                    `approved_at` = NOW()
                WHERE `request_id` = '" . (int)$request_id . "'
            ");

            // Activate subscription
            $this->load->model('pms/project_subscription');
            $this->model_pms_project_subscription->activateSubscription(
                $request->row['project_id'],
                $request->row['duration_months']
            );

            $this->db->query("COMMIT");
            return true;

        } catch (\Exception $e) {
            $this->db->query("ROLLBACK");
            throw $e;
        }
    }

    public function rejectRequest(int $request_id, string $reason = ''): bool {
        $this->db->query("
            UPDATE `" . DB_PREFIX . "pms_subscription_payment_request` 
            SET `status` = 'rejected',
                `admin_notes` = '" . $this->db->escape($reason) . "'
            WHERE `request_id` = '" . (int)$request_id . "'
        ");

        return true;
    }

    public function deleteRequest(int $request_id): bool {
        $this->db->query("
            UPDATE `" . DB_PREFIX . "pms_subscription_payment_request` 
            SET `is_deleted` = 1 
            WHERE `request_id` = '" . (int)$request_id . "'
        ");

        return true;
    }

    public function getRequest(int $request_id): array {
        $query = $this->db->query("
            SELECT r.*, 
                   p.name as project_name,
                   CONCAT(c.firstname, ' ', c.lastname) as submitted_by_name
            FROM `" . DB_PREFIX . "pms_subscription_payment_request` r
            LEFT JOIN `" . DB_PREFIX . "pms_project` p ON r.project_id = p.project_id
            LEFT JOIN `" . DB_PREFIX . "customer` c ON r.submitted_by = c.customer_id
            WHERE r.request_id = '" . (int)$request_id . "'
            AND r.is_deleted = 0
        ");

        return $query->row;
    }

    public function getPendingCount(): int {
        $query = $this->db->query("
            SELECT COUNT(*) as total
            FROM `" . DB_PREFIX . "pms_subscription_payment_request`
            WHERE status = 'pending'
            AND is_deleted = 0
        ");

        return (int)$query->row['total'];
    }
}
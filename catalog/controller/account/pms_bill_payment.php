<?php
namespace Opencart\Catalog\Controller\Account;

class PmsBillPayment extends \Opencart\System\Engine\Controller {

    public function index(): void {
        if (!$this->customer->isLogged()) {
            $this->response->redirect($this->url->link('account/login', 'language=' . $this->config->get('config_language')));
        }
        
        $this->load->language('pms/bill_payment');
        $this->document->setTitle($this->language->get('heading_title'));
        
        // Get shareholder info
        $this->load->model('pms/shareholder');
        $shareholder = $this->model_pms_shareholder->getShareholderByCustomerId($this->customer->getId());
        
        if (!$shareholder) {
            $this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language')));
        }
        
        // Get selected bills from session or URL
        $selected_bills = $this->session->data['selected_bills'] ?? [];
        $bill_ids = isset($this->request->get['bill_ids']) ? explode(',', $this->request->get['bill_ids']) : $selected_bills;
        
        if (empty($bill_ids)) {
            $this->response->redirect($this->url->link('account/pms_my_bills', 'language=' . $this->config->get('config_language')));
        }
        
        // Get bill details
        $bills = $this->db->query("
            SELECT b.*, bt.name as bill_type_name, a.apartment_number
            FROM `" . DB_PREFIX . "pms_monthly_bill` b
            LEFT JOIN `" . DB_PREFIX . "pms_bill_type` bt ON b.bill_type_id = bt.bill_type_id
            LEFT JOIN `" . DB_PREFIX . "pms_apartment` a ON b.apartment_id = a.id
            WHERE b.bill_id IN (" . implode(',', array_map('intval', $bill_ids)) . ")
            AND b.shareholder_id = '" . (int)$shareholder['id'] . "'
            AND b.is_paid = 0
        ");
        
        if ($bills->num_rows == 0) {
            $this->response->redirect($this->url->link('account/pms_my_bills', 'language=' . $this->config->get('config_language')));
        }
        
        $data['bills'] = $bills->rows;
        $data['total_amount'] = 0;
        foreach ($bills->rows as $bill) {
            $data['total_amount'] += $bill['amount'];
        }
        
        // Store selected bills in session
        $this->session->data['selected_bills'] = $bill_ids;
        
        $data['action'] = $this->url->link('account/pms_bill_payment.process', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token'], true);
        $data['back'] = $this->url->link('account/pms_my_bills', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token'], true);
        
        // Get payment methods
        $this->load->model('pms/payment');
        $data['payment_methods'] = $this->model_pms_payment->getPaymentMethods();
        
        $data['customer_token'] = $this->session->data['customer_token'];
        
        $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
        
        $this->response->setOutput($this->load->view('account/pms_bill_payment', $data));
    }
    
    public function process(): void {
        $this->load->language('pms/bill_payment');
        
        $json = [];
        
        if (!$this->customer->isLogged()) {
            $json['error'] = $this->language->get('error_permission');
        }
        
        if (!$json) {
            $this->load->model('pms/shareholder');
            $shareholder = $this->model_pms_shareholder->getShareholderByCustomerId($this->customer->getId());
            
            if ($shareholder) {
                $bill_ids = $this->session->data['selected_bills'] ?? [];
                $payment_method = $this->request->post['payment_method'];
                $reference_number = $this->request->post['reference_number'] ?? '';
                $amount = (float)$this->request->post['amount'];
                
                if (empty($bill_ids)) {
                    $json['error'] = 'No bills selected';
                }
                
                if (empty($payment_method)) {
                    $json['error'] = 'Please select payment method';
                }
                
                if ($amount <= 0) {
                    $json['error'] = 'Invalid payment amount';
                }
                
                if (!$json) {
                    // Get bill details
                    $bills = $this->db->query("
                        SELECT b.*, s.project_id
                        FROM `" . DB_PREFIX . "pms_monthly_bill` b
                        LEFT JOIN `" . DB_PREFIX . "shareholders` s ON b.shareholder_id = s.id
                        WHERE b.bill_id IN (" . implode(',', array_map('intval', $bill_ids)) . ")
                        AND b.shareholder_id = '" . (int)$shareholder['id'] . "'
                        AND b.is_paid = 0
                    ");
                    
                    if ($bills->num_rows > 0) {
                        $project_id = $bills->row['project_id'];
                        $total_due = 0;
                        foreach ($bills->rows as $bill) {
                            $total_due += $bill['amount'];
                        }
                        
                        // Create payment record
                        $this->db->query("
                            INSERT INTO `" . DB_PREFIX . "pms_payment` 
                            SET `project_id` = '" . (int)$project_id . "',
                                `shareholder_id` = '" . (int)$shareholder['id'] . "',
                                `amount` = '" . (float)$amount . "',
                                `payment_date` = NOW(),
                                `payment_method` = '" . $this->db->escape($payment_method) . "',
                                `reference_number` = '" . $this->db->escape($reference_number) . "',
                                `payment_phase_id` = 2,
                                `recorded_by` = '" . (int)$this->customer->getId() . "'
                        ");
                        
                        $payment_id = $this->db->getLastId();
                        
                        // Allocate payment to bills
                        $remaining = $amount;
                        foreach ($bills->rows as $bill) {
                            if ($remaining <= 0) break;
                            
                            $allocated = min($remaining, $bill['amount']);
                            
                            // Insert bill payment record
                            $this->db->query("
                                INSERT INTO `" . DB_PREFIX . "pms_bill_payment` 
                                SET `payment_id` = '" . (int)$payment_id . "',
                                    `bill_id` = '" . (int)$bill['bill_id'] . "',
                                    `shareholder_id` = '" . (int)$shareholder['id'] . "',
                                    `allocated_amount` = '" . (float)$allocated . "'
                            ");
                            
                            // Update bill
                            $new_paid = $bill['paid_amount'] + $allocated;
                            $is_fully_paid = ($new_paid >= $bill['amount']);
                            
                            $this->db->query("
                                UPDATE `" . DB_PREFIX . "pms_monthly_bill` 
                                SET `paid_amount` = '" . (float)$new_paid . "',
                                    `is_paid` = '" . (int)$is_fully_paid . "',
                                    `partial_paid` = '" . (int)(!$is_fully_paid && $new_paid > 0) . "',
                                    `payment_id` = CASE WHEN `payment_id` IS NULL THEN '" . (int)$payment_id . "' ELSE `payment_id` END
                                WHERE `bill_id` = '" . (int)$bill['bill_id'] . "'
                            ");
                            
                            $remaining -= $allocated;
                        }
                        
                        // If overpaid, add to credit balance
                        if ($remaining > 0) {
                            $this->db->query("
                                UPDATE `" . DB_PREFIX . "shareholders` 
                                SET `current_credit_balance` = `current_credit_balance` + '" . (float)$remaining . "'
                                WHERE `shareholder_id` = '" . (int)$shareholder['shareholder_id'] . "'
                            ");
                        }
                        
                        // Clear session
                        unset($this->session->data['selected_bills']);
                        
                        $json['success'] = 'Payment successful!';
                        $json['redirect'] = $this->url->link('account/pms_my_bills', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token'], true);
                    } else {
                        $json['error'] = 'No valid bills found';
                    }
                }
            } else {
                $json['error'] = 'Shareholder not found';
            }
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    
    public function getBillDetails(): void {
        $json = [];
        
        $bill_ids = isset($this->request->get['bill_ids']) ? explode(',', $this->request->get['bill_ids']) : [];
        
        if (!empty($bill_ids)) {
            $bills = $this->db->query("
                SELECT b.*, bt.name as bill_type_name
                FROM `" . DB_PREFIX . "pms_monthly_bill` b
                LEFT JOIN `" . DB_PREFIX . "pms_bill_type` bt ON b.bill_type_id = bt.bill_type_id
                WHERE b.bill_id IN (" . implode(',', array_map('intval', $bill_ids)) . ")
            ");
            
            $total = 0;
            foreach ($bills->rows as $bill) {
                $total += $bill['amount'];
            }
            
            $json['total'] = $total;
            $json['bills'] = $bills->rows;
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
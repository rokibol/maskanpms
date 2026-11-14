<?php
namespace Opencart\Catalog\Controller\Account;

class PmsMyBills extends \Opencart\System\Engine\Controller {

    public function index(): void {
        // Check if logged in
        if (!$this->customer->isLogged()) {
            $this->session->data['redirect'] = $this->url->link('account/pms_my_bills', 'language=' . $this->config->get('config_language'));
            $this->response->redirect($this->url->link('account/login', 'language=' . $this->config->get('config_language')));
        }
        
        // Check if user is Shareholder
        /*$customer_group_id = $this->customer->getGroupId();
        $shareholder_group_id = (int)$this->config->get('config_pms_shareholder_group_id');
        
        if ($customer_group_id != $shareholder_group_id) {
            $this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']));
        }*/
        
        $this->load->language('pms/my_bills');
        
        $this->document->setTitle($this->language->get('heading_title'));
        
        // Get shareholder info
        $this->load->model('pms/shareholder');
        $shareholder = $this->model_pms_shareholder->getShareholderByCustomerId($this->customer->getId());
        
        if (!$shareholder) {
            $this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']));
        }
        
        $data['shareholder_id'] = $shareholder['id'];
        $data['shareholder_name'] = $shareholder['firstname'] . ' ' . $shareholder['lastname'];
        $data['shareholder_code'] = $shareholder['shareholder_code'];
        
        // Get project info
        $this->load->model('pms/project');
        $project = $this->model_pms_project->getProject($shareholder['project_id']);
        $data['project_name'] = $project['name'];
        $data['theme_color'] = $project['theme_color'];
        
        // Get apartments owned
        $data['apartments'] = $this->model_pms_shareholder->getShareholderApartments($shareholder['project_id'], $shareholder['id']);
        
        // Filter parameters
        /*$filter_year = isset($this->request->get['filter_year']) ? $this->request->get['filter_year'] : '';
        $filter_status = isset($this->request->get['filter_status']) ? $this->request->get['filter_status'] : '';*/
        
        $this->load->model('pms/bill');
        
        // Get bill summary
        $data['bill_summary'] = $this->model_pms_bill->getMyBillSummary($shareholder['id']);
        
        // Get available years for filter
        $data['available_years'] = $this->model_pms_bill->getAvailableYearsForShareholder($shareholder['id']);
        
        // Get bills
        /*$filter_data = [
            'filter_year' => $filter_year,
            'filter_status' => $filter_status
        ];
        
        $data['bills'] = $this->model_pms_bill->getMyBills($shareholder['id'], $filter_data);*/
        
        // Filter values
        //$data['filter_year'] = $filter_year;
        //$data['filter_status'] = $filter_status;

        $data['language'] = $this->config->get('config_language');
        $data['customer_token'] = $this->session->data['customer_token'];
        
        // Status options
        $data['status_options'] = [
            '' => 'All Bills',
            'unpaid' => 'Unpaid Bills',
            'paid' => 'Paid Bills'
        ];
        
        // Get payment methods for online payment
        $this->load->model('pms/payment');
        $data['payment_methods'] = $this->model_pms_payment->getPaymentMethods();
        
        // URLs
        $data['pay_url'] = $this->url->link('account/pms_my_bills.pay', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        $data['payment_history_url'] = $this->url->link('account/pms_my_bills.paymentHistory', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        $data['back'] = $this->url->link('account/shareholder', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        
        $data['customer_token'] = $this->session->data['customer_token'];
        
        $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
        
        $this->response->setOutput($this->load->view('account/pms_my_bills', $data));
    }

    /**
     * AJAX: Load shareholder bills HTML (grouped by month)
     */
    public function loadList(): void {
        if (!$this->customer->isLogged()) {
            return;
        }

        $this->load->language('pms/my_bills');
        $this->load->model('pms/shareholder');
        $this->load->model('pms/bill');

        $shareholder = $this->model_pms_shareholder->getShareholderByCustomerId($this->customer->getId());
        if (!$shareholder) {
            return;
        }

        $filter_year   = isset($this->request->get['filter_year']) ? $this->request->get['filter_year'] : '';
        $filter_status = isset($this->request->get['filter_status']) ? $this->request->get['filter_status'] : '';

        $bills = $this->model_pms_bill->getMyBills($shareholder['id'], [
            'filter_year'   => $filter_year,
            'filter_status' => $filter_status
        ]);

        if (!$bills) {
            $this->response->setOutput(
                '<div class="text-center text-muted py-5">' .
                    '<i class="fas fa-regular fa-file-invoice fa-3x mb-3 d-block"></i>' .
                    '<h5>No bills found</h5>' .
                    '<p>You have no bills for the selected filters.</p>' .
                '</div>'
            );
            return;
        }

        // Group bills by month
        $grouped = [];
        foreach ($bills as $bill) {
            $key = date('F Y', strtotime($bill['bill_month']));
            $grouped[$key][] = $bill;
        }

        $html = '';
        foreach ($grouped as $month => $month_bills) {
            // Header status: use first bill's status for the group badge, but show both later
            $any_paid   = false;
            $any_unpaid = false;
            $group_total = 0;
            foreach ($month_bills as $b) {
                $group_total += $b['amount'];
                if ($b['is_paid']) $any_paid = true;
                else $any_unpaid = true;
            }

            $status_label = ($any_paid && $any_unpaid) ? 'MIXED' : ($any_unpaid ? 'UNPAID' : 'PAID');
            $status_class = ($any_paid && $any_unpaid) ? 'unpaid' : ($any_unpaid ? 'unpaid' : 'paid');

            $html .= '<div class="bill-card">';
            $html .= '<div class="bill-header">';
            $html .= '<span class="bill-month"><i class="fas fa-regular fa-calendar"></i> ' . htmlspecialchars($month) . '</span>';
            $html .= '<span class="bill-status ' . $status_class . '">' . $status_label . '</span>';
            $html .= '</div>';
            $html .= '<div class="bill-body">';

            foreach ($month_bills as $bill) {
                $html .= '<div class="bill-item">';
                $html .= '<div class="form-check">';
                $html .= '<input type="checkbox" class="form-check-input bill-selector" ' .
                         'data-bill-id="' . (int)$bill['bill_id'] . '" ' .
                         'data-amount="' . (float)$bill['amount'] . '" ' .
                         'id="bill_' . (int)$bill['bill_id'] . '">';
                $html .= '<label class="form-check-label" for="bill_' . (int)$bill['bill_id'] . '">';
                $html .= '<strong>' . htmlspecialchars($bill['bill_type_name']) . '</strong>';
                if (!empty($bill['apartment_number'])) {
                    $html .= ' <small class="text-muted">(' . htmlspecialchars($bill['apartment_number']) . ')</small>';
                }
                $html .= '</label>';
                $html .= '</div>';
                $html .= '<div class="bill-amount ' . ($bill['is_paid'] ? 'paid' : 'unpaid') . '">';
                $html .= '৳ ' . number_format($bill['amount'], 2);
                $html .= '</div>';
                $html .= '</div>';
            }

            $html .= '</div>';
            $html .= '<div class="bill-footer">';
            $html .= '<div>';
            $html .= '<input type="checkbox" id="select-all-bills-' . md5($month) . '" class="form-check-input me-2 month-select-all">';
            $html .= '<label for="select-all-bills-' . md5($month) . '">Select All</label>';
            $html .= '</div>';
            $html .= '<div class="d-flex align-items-center gap-2 flex-wrap">';
            $html .= '<span class="selected-total">Total: ৳ 0.00</span>';
            $html .= '<button class="btn-pay pay-selected" disabled>';
            $html .= '<i class="fa-regular fa-credit-card"></i> Pay Selected</button>';
            $html .= '</div>';
            $html .= '</div>';
            $html .= '</div>';
        }

        $this->response->setOutput($html);
    }
    
    public function pay(): void {
        $this->load->language('pms/my_bills');
        
        $json = [];
        
        if (!$this->customer->isLogged()) {
            $json['error'] = 'Please login first';
        }
        
        if (!$json) {
            $bill_id = (int)$this->request->post['bill_id'];
            
            $this->load->model('pms/bill');
            $this->load->model('pms/shareholder');
            $this->load->model('pms/payment');
            $this->load->model('pms/project');
            
            // Get bill details
            $bill_info = $this->db->query("
                SELECT b.*, s.project_id
                FROM `" . DB_PREFIX . "pms_monthly_bill` b
                LEFT JOIN `" . DB_PREFIX . "shareholders` s ON b.shareholder_id = s.id
                WHERE b.bill_id = '" . (int)$bill_id . "'
                AND b.is_paid = 0
                AND b.is_deleted = 0
            ");
            
            if ($bill_info->num_rows) {
                foreach($bill_info->rows as $bill_row) {
                    $bill = $bill_row;
                    
                    // Create payment record
                    $payment_data = [
                        'project_id'        => $bill['project_id'],
                        'shareholder_id'    => $bill['shareholder_id'],
                        'amount'            => $bill['amount'],
                        'payment_date'      => date('Y-m-d'),
                        'payment_method'    => $this->request->post['payment_method'],
                        'reference_number'  => $this->request->post['reference_number'] ?? '',
                        'payment_phase_id'  => 2 // Maintenance payment phase
                    ];
                    
                    $payment_id = $this->model_pms_payment->addPayment($payment_data);
                    
                    // Mark bill as paid
                    $this->model_pms_bill->markBillAsPaid($bill['bill_id'], ['payment_id' => $payment_id]);
                    
                    // Create payment allocation
                    /*$this->db->query("
                        INSERT INTO `" . DB_PREFIX . "pms_payment_allocation` 
                        SET `shareholder_id` = '" . (int)$bill['shareholder_id'] . "',
                            `project_id` = '" . (int)$bill['project_id'] . "',
                            `payment_phase_id` = 2,
                            `allocated_amount` = '" . (float)$bill['amount'] . "',
                            `notes` = 'Bill payment - Bill #" . $bill['bill_id'] . "',
                            `created_by` = '" . (int)$this->customer->getId() . "',
                            `created_at` = NOW()
                    ");*/
                }
                $json['success'] = $this->language->get('text_payment_success');
                $json['redirect'] = $this->url->link('account/pms_my_bills', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
            } else {
                $json['error'] = 'Bill not found or already paid';
            }
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    
    public function paymentHistory(): void {
        if (!$this->customer->isLogged()) {
            $this->response->redirect($this->url->link('account/login', 'language=' . $this->config->get('config_language')));
        }
        
        $this->load->language('pms/my_bills');
        
        $this->document->setTitle($this->language->get('heading_payment_history'));
        
        // Get shareholder info
        $this->load->model('pms/shareholder');
        $shareholder = $this->model_pms_shareholder->getShareholderByCustomerId($this->customer->getId());

        $total_due = $this->model_pms_shareholder->getTotalDue($shareholder['id']);
        
        if (!$shareholder) {
            $this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']));
        }
        
        // Get payment history
        $this->load->model('pms/payment');
        
        //$data['payments'] = $this->model_pms_payment->getPaymentHistory($shareholder['id'], 50);
        
        $data['payment_summary'] = $this->model_pms_payment->getPaymentSummaryForShareholder($shareholder['id']);

        $data['payment_summary'] = array_merge($data['payment_summary'], array('total_due' => $total_due));
        
        $data['back'] = $this->url->link('account/pms_my_bills', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        
        $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
        
        $this->response->setOutput($this->load->view('account/pms_payment_history', $data));
    }

    /**
     * AJAX: Load payment history table rows
     */
    public function loadPaymentHistory(): void {
        if (!$this->customer->isLogged()) {
            return;
        }

        $this->load->language('pms/my_bills');
        $this->load->model('pms/shareholder');
        $this->load->model('pms/payment');

        $shareholder = $this->model_pms_shareholder->getShareholderByCustomerId($this->customer->getId());
        if (!$shareholder) {
            return;
        }

        $filter_year  = isset($this->request->get['filter_year']) ? $this->request->get['filter_year'] : '';
        $filter_month = isset($this->request->get['filter_month']) ? $this->request->get['filter_month'] : '';

        // Get payment history (you can pass filters to your model if it supports)
        $payments = $this->model_pms_payment->getPaymentHistory($shareholder['id'], 50);

        // Apply year/month filters (since model doesn't support filters here)
        if ($filter_year || $filter_month) {
            $filtered = [];
            foreach ($payments as $p) {
                $paid_ts = strtotime($p['payment_date']);
                if ($filter_year && date('Y', $paid_ts) != $filter_year) continue;
                if ($filter_month && date('n', $paid_ts) != (int)$filter_month) continue;
                $filtered[] = $p;
            }
            $payments = $filtered;
        }

        if (!$payments) {
            $this->response->setOutput(
                '<tr><td colspan="6" class="text-center text-muted py-5">' .
                    '<i class="fas fa-regular fa-credit-card fa-2x mb-2 d-block"></i>' .
                    '<p class="mb-0">No payment history found.</p>' .
                '</td></tr>'
            );
            return;
        }

        $html = '';
        $total_paid = 0;
        foreach ($payments as $payment) {
            $total_paid += (float)$payment['amount'];
            $html .= '<tr>';
            $html .= '<td>' . date('d M Y', strtotime($payment['payment_date'])) . '</td>';
            $html .= '<td>' . htmlspecialchars($payment['phase_name']) . ' Payment</td>';
            $html .= '<td class="text-success">+ ৳ ' . number_format($payment['amount'], 2) . '</td>';
            $html .= '<td><span class="payment-method">' . ucfirst($payment['payment_method']) . '</span></td>';
            $html .= '<td class="reference-number">' . (!empty($payment['reference_number']) ? htmlspecialchars($payment['reference_number']) : '-') . '</td>';
            $html .= '<td><span class="badge bg-success">Completed</span></td>';
            $html .= '</tr>';
        }

        // Append footer row (total) — we'll render separately in twig but easier to append
        $html .= '<tr class="table-total-row"><td colspan="2" class="text-end fw-bold">Total:</td><td class="text-success fw-bold">৳ ' . number_format($total_paid, 2) . '</td><td colspan="3"></td></tr>';

        $this->response->setOutput($html);
    }
}
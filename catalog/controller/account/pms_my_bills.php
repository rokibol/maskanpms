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
        $filter_year = isset($this->request->get['filter_year']) ? $this->request->get['filter_year'] : '';
        $filter_status = isset($this->request->get['filter_status']) ? $this->request->get['filter_status'] : '';
        
        $this->load->model('pms/bill');
        
        // Get bill summary
        $data['bill_summary'] = $this->model_pms_bill->getMyBillSummary($shareholder['id']);
        
        // Get available years for filter
        $data['available_years'] = $this->model_pms_bill->getAvailableYearsForShareholder($shareholder['id']);
        
        // Get bills
        $filter_data = [
            'filter_year' => $filter_year,
            'filter_status' => $filter_status
        ];
        
        $data['bills'] = $this->model_pms_bill->getMyBills($shareholder['id'], $filter_data);
        
        // Filter values
        $data['filter_year'] = $filter_year;
        $data['filter_status'] = $filter_status;

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
        $data['payments'] = $this->model_pms_payment->getPaymentHistory($shareholder['id'], 50);
        
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
}
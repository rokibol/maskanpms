<?php
namespace Opencart\Catalog\Controller\Account;

class PmsBills extends \Opencart\System\Engine\Controller {

    public function index(): void {
        // Check if logged in
        if (!$this->customer->isLogged()) {
            $this->session->data['redirect'] = $this->url->link('account/pms_bills', 'language=' . $this->config->get('config_language'));
            $this->response->redirect($this->url->link('account/login', 'language=' . $this->config->get('config_language')));
        }
        
        // Check if user is Accountant or Manager
        /*$customer_group_id = $this->customer->getGroupId();
        $accountant_group_id = (int)$this->config->get('config_pms_accountant_group_id');
        $manager_group_id = (int)$this->config->get('config_pms_manager_group_id');
        
        if ($customer_group_id != $accountant_group_id && $customer_group_id != $manager_group_id) {
            $this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language')));
        }*/
        
        $this->load->language('pms/bills');
        
        $this->document->setTitle($this->language->get('heading_title'));
        
        // Get current project
        $this->load->model('pms/project');
        $project = $this->customer->getProject();

        if (!$project) {
            $this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']));
        }
        
        $data['project_id'] = $project['project_id'];
        $data['project_name'] = $project['name'];
        
        // Filter parameters
        $filter_month = isset($this->request->get['filter_month']) ? $this->request->get['filter_month'] : '';
        $filter_bill_type = isset($this->request->get['filter_bill_type']) ? $this->request->get['filter_bill_type'] : '';
        $filter_status = isset($this->request->get['filter_status']) ? $this->request->get['filter_status'] : '';
        $filter_apartment = isset($this->request->get['filter_apartment']) ? $this->request->get['filter_apartment'] : '';
        
        $sort = isset($this->request->get['sort']) ? $this->request->get['sort'] : 'b.bill_month';
        $order = isset($this->request->get['order']) ? $this->request->get['order'] : 'DESC';
        $page = isset($this->request->get['page']) ? (int)$this->request->get['page'] : 1;
        
        // Build URL for pagination
        $url = '';
        if ($filter_month) $url .= '&filter_month=' . $filter_month;
        if ($filter_bill_type) $url .= '&filter_bill_type=' . $filter_bill_type;
        if ($filter_status) $url .= '&filter_status=' . $filter_status;
        if ($filter_apartment) $url .= '&filter_apartment=' . urlencode($filter_apartment);
        if ($sort) $url .= '&sort=' . $sort;
        if ($order) $url .= '&order=' . $order;

        $url .= '&customer_token=' . $this->session->data['customer_token'];
        
        $limit = $this->config->get('config_pagination_admin');
        
        $filter_data = [
            'project_id'        => $project['project_id'],
            'filter_month'      => $filter_month,
            'filter_bill_type'  => $filter_bill_type,
            'filter_status'     => $filter_status,
            'filter_apartment'  => $filter_apartment,
            'sort'              => $sort,
            'order'             => $order,
            'start'             => ($page - 1) * $limit,
            'limit'             => $limit
        ];
        
        $this->load->model('pms/bill');
        
        $bill_total = $this->model_pms_bill->getTotalProjectBills($filter_data);
        $results = $this->model_pms_bill->getProjectBills($filter_data);
        
        // Get bill types for filter
        $data['bill_types'] = $this->model_pms_bill->getBillTypes();
        
        // Get available months
        $data['available_months'] = $this->model_pms_bill->getAvailableMonths($project['project_id']);
        
        $data['bills'] = [];
        
        foreach ($results as $result) {
            $status_badge = $result['is_paid'] ? 
                '<span class="badge bg-success"><i class="fa-regular fa-circle-check"></i> Paid</span>' : 
                '<span class="badge bg-warning"><i class="fa-regular fa-clock"></i> Unpaid</span>';
            
            $data['bills'][] = [
                'bill_id'           => $result['bill_id'],
                'apartment_number'  => $result['apartment_number'],
                'floor_number'      => $result['floor_number'],
                'shareholder_name'  => $result['shareholder_name'],
                'shareholder_code'  => $result['shareholder_code'],
                'bill_month'        => date('M Y', strtotime($result['bill_month'])),
                'bill_month_raw'    => $result['bill_month'],
                'bill_type'         => $result['bill_type_name'],
                'amount'            => $result['amount'],
                'is_paid'           => $result['is_paid'],
                'status_badge'      => $status_badge,
                'paid_date'         => $result['paid_date'] ? date('d M Y', strtotime($result['paid_date'])) : '-',
                'due_date'          => $result['due_date'] ? date('d M Y', strtotime($result['due_date'])) : '-',
                'view'              => $this->url->link('account/pms_bills.view', 'language=' . $this->config->get('config_language') . '&bill_id=' . $result['bill_id'] . $url)
            ];
        }

        // Get collection summary for the selected month
        if ($filter_month) {
            $data['collection_summary'] = $this->model_pms_bill->getCollectionSummary($project['project_id'], $filter_month);
            $data['bill_summary'] = $this->model_pms_bill->getBillSummaryByMonth($project['project_id'], $filter_month);
        } else {
            $data['collection_summary'] = [];
            $data['bill_summary'] = [];
        }
        
        // Pagination
        $data['pagination'] = $this->load->controller('common/pagination', [
			'total' => $bill_total,
			'page'  => $page,
			'limit' => $this->config->get('config_pagination_admin'),
			'url'   => $this->url->link('account/pms_bills', $url . '&page={page}')
		]);

		$data['results'] = sprintf($this->language->get('text_pagination'), ($bill_total) ? (($page - 1) * $this->config->get('config_pagination_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_pagination_admin')) > ($bill_total - $this->config->get('config_pagination_admin'))) ? $bill_total : ((($page - 1) * $this->config->get('config_pagination_admin')) + $this->config->get('config_pagination_admin')), $bill_total, ceil($bill_total / $this->config->get('config_pagination_admin')));
        
        // Sort links
        $data['sort_month'] = $this->url->link('account/pms_bills', 'language=' . $this->config->get('config_language') . '&sort=b.bill_month' . $url);
        $data['sort_apartment'] = $this->url->link('account/pms_bills', 'language=' . $this->config->get('config_language') . '&sort=a.apartment_number' . $url);
        $data['sort_amount'] = $this->url->link('account/pms_bills', 'language=' . $this->config->get('config_language') . '&sort=b.amount' . $url);
        $data['sort_status'] = $this->url->link('account/pms_bills', 'language=' . $this->config->get('config_language') . '&sort=b.is_paid' . $url);
        
        // Filter values
        $data['filter_month'] = $filter_month;
        $data['filter_bill_type'] = $filter_bill_type;
        $data['filter_status'] = $filter_status;
        $data['filter_apartment'] = $filter_apartment;
        
        // Status options
        $data['status_options'] = [
            '' => 'All Status',
            'paid' => 'Paid',
            'unpaid' => 'Unpaid'
        ];
        
        // Sort and order
        $data['sort'] = $sort;
        $data['order'] = $order;
        $data['customer_token'] = $this->session->data['customer_token'];
        $data['language'] = $this->config->get('config_language');

        // URLs
        $data['generate'] = $this->url->link('account/pms_bills.generate', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        $data['back'] = $this->url->link('account/accountant', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        
        // Template parts
        $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

        $this->response->setOutput($this->load->view('account/pms_bills_list', $data));
    }
    
    public function generate(): void {
        if (!$this->customer->isLogged()) {
            $this->response->redirect($this->url->link('account/login', 'language=' . $this->config->get('config_language')));
        }
        
        $this->load->language('pms/bills');
        
        $this->document->setTitle($this->language->get('heading_generate'));
        
        // Get current project
        $this->load->model('pms/project');
        $project = $this->customer->getProject();
        
        if (!$project) {
            $this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']));
        }
        
        $data['project_id'] = $project['project_id'];
        $data['project_name'] = $project['name'];
        
        // Load models
        $this->load->model('pms/bill');
        $this->load->model('pms/apartment');
        
        // Get bill types
        $data['bill_types'] = $this->model_pms_bill->getBillTypes();
        
        // Get apartment stats for info
        $data['apartment_stats'] = $this->model_pms_apartment->getApartmentStats($project['project_id']);
        
        // Get last month's bills for reference
        $last_month = date('Y-m', strtotime('first day of last month'));
        $data['last_month_bills'] = $this->model_pms_bill->getBillSummaryByMonth($project['project_id'], $last_month);
        
        // Form action
        $data['action'] = $this->url->link('account/pms_bills.saveBills', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        $data['back'] = $this->url->link('account/pms_bills', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        
        // Set default month to next month if not set
        $data['bill_month'] = date('Y-m-d', strtotime('first day of next month'));
        
        $data['user_token'] = $this->session->data['user_token'];
        
        $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
        
        $this->response->setOutput($this->load->view('account/pms_bills_generate', $data));
    }
    
    public function saveBills(): void {
        $this->load->language('pms/bills');
        
        $json = [];
        
        // Check permission
        /*$customer_group_id = $this->customer->getGroupId();
        $accountant_group_id = (int)$this->config->get('config_pms_accountant_group_id');
        $manager_group_id = (int)$this->config->get('config_pms_manager_group_id');
        
        if ($customer_group_id != $accountant_group_id && $customer_group_id != $manager_group_id) {
            $json['error'] = $this->language->get('error_permission');
        }*/
        
        if (!$json) {
            $this->load->model('pms/bill');
            $this->load->model('pms/project');
            
            $project = $this->customer->getProject();
            
            $bills_data = [];
            foreach ($this->request->post['bill_types'] as $bill_type_id => $amount) {
                if (!empty($amount) && $amount > 0) {
                    $bills_data[$bill_type_id] = (float)$amount;
                }
            }
            
            if (empty($bills_data)) {
                $json['error'] = $this->language->get('error_no_bills');
            } else {
                $result = $this->model_pms_bill->generateAllBills([
                    'project_id' => $project['project_id'],
                    'bill_month' => $this->request->post['bill_month'],
                    'bills' => $bills_data
                ]);
                
                if ($result['success']) {
                    $json['success'] = $this->language->get('text_generate_success');
                    $json['generated'] = $result['generated'];
                    $json['redirect'] = $this->url->link('account/pms_bills', 'language=' . $this->config->get('config_language') . '&filter_month=' . date('Y-m', strtotime($this->request->post['bill_month'])) . '&customer_token=' . $this->session->data['customer_token']);
                } else {
                    $json['error'] = implode(', ', $result['errors']);
                }
            }
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    
    public function view(): void {
        if (!$this->customer->isLogged()) {
            $this->response->redirect($this->url->link('account/login', 'language=' . $this->config->get('config_language')));
        }
        
        $this->load->language('pms/bills');
        
        $this->document->setTitle($this->language->get('heading_view'));
        
        $bill_id = isset($this->request->get['bill_id']) ? (int)$this->request->get['bill_id'] : 0;
        
        $this->load->model('pms/bill');
        
        // Get bill details
        $bill_info = $this->db->query("
            SELECT b.*, 
                   bt.name as bill_type_name,
                   a.apartment_number,
                   a.floor_number,
                   a.area_sqft,
                   CONCAT(c.firstname, ' ', c.lastname) as shareholder_name,
                   s.shareholder_code,
                   c.email as shareholder_email,
                   c.telephone as shareholder_phone
            FROM `" . DB_PREFIX . "pms_monthly_bill` b
            LEFT JOIN `" . DB_PREFIX . "pms_bill_type` bt ON b.bill_type_id = bt.bill_type_id
            LEFT JOIN `" . DB_PREFIX . "pms_apartment` a ON b.apartment_id = a.id
            LEFT JOIN `" . DB_PREFIX . "shareholders` s ON b.shareholder_id = s.id
            LEFT JOIN `" . DB_PREFIX . "customer` c ON s.user_id = c.customer_id
            WHERE b.bill_id = '" . (int)$bill_id . "'
            AND b.is_deleted = 0
        ");
        
        if (!$bill_info->num_rows) {
            $this->response->redirect($this->url->link('account/pms_bills', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']));
        }
        
        $bill = $bill_info->row;
        
        // Check if payment exists
        $payment_info = null;
        if ($bill['payment_id']) {
            $payment_query = $this->db->query("
                SELECT * 
                FROM `" . DB_PREFIX . "pms_payment`
                WHERE id = '" . (int)$bill['payment_id'] . "'
            ");
            $payment_info = $payment_query->row;
        }
        
        $data['bill'] = [
            'bill_id'           => $bill['bill_id'],
            'bill_month'        => date('F Y', strtotime($bill['bill_month'])),
            'bill_type'         => $bill['bill_type_name'],
            'amount'            => number_format($bill['amount'], 2),
            'is_paid'           => $bill['is_paid'],
            'paid_date'         => $bill['paid_date'] ? date('d F Y', strtotime($bill['paid_date'])) : '-',
            'due_date'          => $bill['due_date'] ? date('d F Y', strtotime($bill['due_date'])) : '-',
            'notes'             => nl2br($bill['notes'] ?? '') ?: '-',
            'apartment_number'  => $bill['apartment_number'],
            'floor_number'      => $bill['floor_number'],
            'area_sqft'         => $bill['area_sqft'],
            'shareholder_name'  => $bill['shareholder_name'],
            'shareholder_code'  => $bill['shareholder_code'],
            'shareholder_email' => $bill['shareholder_email'],
            'shareholder_phone' => $bill['shareholder_phone'],
            'payment_method'    => $payment_info ? ucfirst($payment_info['payment_method']) : '-',
            'payment_reference' => $payment_info ? $payment_info['reference_number'] : '-',
            'payment_date'      => $payment_info ? date('d F Y', strtotime($payment_info['payment_date'])) : '-'
        ];
        
        $url = $this->buildUrl();
        $data['back'] = $this->url->link('account/pms_bills', 'language=' . $this->config->get('config_language') . $url . '&customer_token=' . $this->session->data['customer_token']);
        
        $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
        
        $this->response->setOutput($this->load->view('account/pms_bill_view', $data));
    }
    
    private function buildUrl(): string {
        $url = '';
        
        if (isset($this->request->get['filter_month'])) {
            $url .= '&filter_month=' . $this->request->get['filter_month'];
        }
        if (isset($this->request->get['filter_bill_type'])) {
            $url .= '&filter_bill_type=' . $this->request->get['filter_bill_type'];
        }
        if (isset($this->request->get['filter_status'])) {
            $url .= '&filter_status=' . $this->request->get['filter_status'];
        }
        if (isset($this->request->get['filter_apartment'])) {
            $url .= '&filter_apartment=' . urlencode($this->request->get['filter_apartment']);
        }
        if (isset($this->request->get['sort'])) {
            $url .= '&sort=' . $this->request->get['sort'];
        }
        if (isset($this->request->get['order'])) {
            $url .= '&order=' . $this->request->get['order'];
        }
        if (isset($this->request->get['page'])) {
            $url .= '&page=' . $this->request->get['page'];
        }
        
        return $url;
    }

    /**
     * Mark bill as paid page
     */
    public function markPaid(): void {
        if (!$this->customer->isLogged()) {
            $this->response->redirect($this->url->link('account/login', 'language=' . $this->config->get('config_language')));
        }
        
        $this->load->language('pms/bills');
        
        $this->document->setTitle($this->language->get('heading_mark_paid'));
        
        // Get current project
        $this->load->model('pms/project');
        $project = $this->customer->getProject();
        
        if (!$project) {
            $this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']));
        }
        
        $bill_id = isset($this->request->get['bill_id']) ? (int)$this->request->get['bill_id'] : 0;
        
        $this->load->model('pms/bill');
        
        // Get bill details
        $bill_info = $this->db->query("
            SELECT b.*, 
                bt.name as bill_type_name,
                a.apartment_number,
                CONCAT(c.firstname, ' ', c.lastname) as shareholder_name,
                s.shareholder_code
            FROM `" . DB_PREFIX . "pms_monthly_bill` b
            LEFT JOIN `" . DB_PREFIX . "pms_bill_type` bt ON b.bill_type_id = bt.bill_type_id
            LEFT JOIN `" . DB_PREFIX . "pms_apartment` a ON b.apartment_id = a.id
            LEFT JOIN `" . DB_PREFIX . "shareholders` s ON b.shareholder_id = s.id
            LEFT JOIN `" . DB_PREFIX . "customer` c ON s.user_id = c.customer_id
            WHERE b.bill_id = '" . (int)$bill_id . "'
            AND b.project_id = '" . (int)$project['project_id'] . "'
            AND b.is_paid = 0
            AND b.is_deleted = 0
        ");
        
        if (!$bill_info->num_rows) {
            $this->response->redirect($this->url->link('account/pms_bills', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']));
        }
        
        $bill = $bill_info->row;
        
        $data['bill'] = [
            'bill_id'           => $bill['bill_id'],
            'bill_month'        => date('F Y', strtotime($bill['bill_month'])),
            'bill_type'         => $bill['bill_type_name'],
            'amount'            => number_format($bill['amount'], 2),
            'apartment_number'  => $bill['apartment_number'],
            'shareholder_name'  => $bill['shareholder_name'],
            'shareholder_code'  => $bill['shareholder_code']
        ];
        
        // Get payment methods
        $this->load->model('pms/payment');
        $data['payment_methods'] = $this->model_pms_payment->getPaymentMethods();
        
        // Form action
        $data['action'] = $this->url->link('account/pms_bills.savePayment', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        $data['back'] = $this->url->link('account/pms_bills', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        
        $data['language'] = $this->config->get('config_language');
        $data['customer_token'] = $this->session->data['customer_token'];
        
        $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
        
        $this->response->setOutput($this->load->view('account/pms_bill_mark_paid', $data));
    }

    /**
     * Save payment and mark bill as paid
     */
    public function savePayment(): void {
        $this->load->language('pms/bills');
        
        $json = [];
        
        // Check permission
        $customer_group_id = $this->customer->getGroupId();
        $accountant_group_id = (int)$this->config->get('config_pms_accountant_group_id');
        $manager_group_id = (int)$this->config->get('config_pms_manager_group_id');
        
        /*if ($customer_group_id != $accountant_group_id && $customer_group_id != $manager_group_id) {
            $json['error'] = $this->language->get('error_permission');
        }*/
        
        // Validation
        if (!$json) {
            if (empty($this->request->post['bill_id'])) {
                $json['error'] = 'Invalid bill ID';
            }
            
            /*if (empty($this->request->post['payment_method'])) {
                $json['error']['payment_method'] = $this->language->get('error_payment_method');
            }
            
            if (empty($this->request->post['payment_date'])) {
                $json['error']['payment_date'] = $this->language->get('error_payment_date');
            }
            
            if (empty($this->request->post['amount']) || $this->request->post['amount'] <= 0) {
                $json['error']['amount'] = $this->language->get('error_amount');
            }*/
        }
        
        if (!$json) {
            $this->load->model('pms/bill');
            $this->load->model('pms/payment');
            $this->load->model('pms/project');
            
            $project = $this->customer->getProject();
            
            // Get bill to verify amount
            $bill_info = $this->db->query("
                SELECT * 
                FROM `" . DB_PREFIX . "pms_monthly_bill`
                WHERE bill_id = '" . (int)$this->request->post['bill_id'] . "'
                AND project_id = '" . (int)$project['project_id'] . "'
                AND is_paid = 0
            ");
            
            if ($bill_info->num_rows) {
                $bill = $bill_info->row;
                
                // Create payment record
                $payment_data = [
                    'project_id'        => $project['project_id'],
                    'shareholder_id'    => $bill['shareholder_id'],
                    'amount'            => $this->request->post['amount'],
                    'payment_date'      => $this->request->post['payment_date'],
                    'payment_method'    => $this->request->post['payment_method'],
                    'reference_number'  => $this->request->post['reference_number'],
                    'payment_phase_id'  => 2 // Maintenance payment phase
                ];
                
                $payment_id = $this->model_pms_payment->addPayment($payment_data);
                
                // Mark bill as paid
                $this->model_pms_bill->markBillAsPaid($bill['bill_id'], ['payment_id' => $payment_id]);
                
                // Also create payment allocation record
                /*$this->db->query("
                    INSERT INTO `" . DB_PREFIX . "payment_allocation` 
                    SET `shareholder_id` = '" . (int)$bill['shareholder_id'] . "',
                        `project_id` = '" . (int)$project['project_id'] . "',
                        `payment_phase_id` = 2,
                        `allocated_amount` = '" . (float)$this->request->post['amount'] . "',
                        `notes` = 'Monthly bill payment - ' . DATE_FORMAT(bill_month, '%M %Y'),
                        `created_by` = '" . (int)$this->customer->getId() . "',
                        `created_at` = NOW()
                ");*/
                
                $json['success'] = $this->language->get('text_payment_success');
                $json['redirect'] = $this->url->link('account/pms_bills', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
            } else {
                $json['error'] = 'Bill not found or already paid';
            }
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Bulk mark bills as paid
     */
    public function bulkMarkPaid(): void {
        $this->load->language('pms/bills');
        
        $json = [];
        
        /*$customer_group_id = $this->customer->getGroupId();
        $accountant_group_id = (int)$this->config->get('config_pms_accountant_group_id');
        $manager_group_id = (int)$this->config->get('config_pms_manager_group_id');
        
        if ($customer_group_id != $accountant_group_id && $customer_group_id != $manager_group_id) {
            $json['error'] = $this->language->get('error_permission');
        }*/
        
        if (!$json) {
            $this->load->model('pms/bill');
            $this->load->model('pms/payment');
            $this->load->model('pms/project');
            
            $project = $this->customer->getProject();
            
            $bill_ids = $this->request->post['bill_ids'];
            $payment_method = $this->request->post['payment_method'];
            $payment_date = $this->request->post['payment_date'];
            $reference_number = $this->request->post['reference_number'];
            
            if (empty($bill_ids)) {
                $json['error'] = 'No bills selected';
            }
            
            if (!$json) {
                $total_amount = 0;
                $first_bill = null;
                
                // Get all selected bills
                $bill_ids_str = implode(',', array_map('intval', $bill_ids));
                $bills = $this->db->query("
                    SELECT * 
                    FROM `" . DB_PREFIX . "pms_monthly_bill`
                    WHERE bill_id IN (" . $bill_ids_str . ")
                    AND project_id = '" . (int)$project['project_id'] . "'
                    AND is_paid = 0
                ");
                
                if ($bills->num_rows > 0) {
                    foreach ($bills->rows as $bill) {
                        $total_amount += $bill['amount'];
                        if (!$first_bill) {
                            $first_bill = $bill;
                        }
                    }
                    
                    // Create single payment for all selected bills
                    $payment_data = [
                        'project_id'        => $project['project_id'],
                        'shareholder_id'    => $first_bill['shareholder_id'],
                        'amount'            => $total_amount,
                        'payment_date'      => $payment_date,
                        'payment_method'    => $payment_method,
                        'reference_number'  => $reference_number,
                        'payment_phase_id'  => 2
                    ];
                    
                    $payment_id = $this->model_pms_payment->addPayment($payment_data);
                    
                    // Mark all bills as paid
                    $this->model_pms_bill->markBillsAsPaid($bill_ids, ['payment_id' => $payment_id]);
                    
                    $json['success'] = $this->language->get('text_bulk_payment_success');
                    $json['redirect'] = $this->url->link('account/pms_bills', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
                } else {
                    $json['error'] = 'No valid bills selected';
                }
            }
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Unpaid bills list (for accountant to see what needs payment)
     */
    public function unpaid(): void {
        if (!$this->customer->isLogged()) {
            $this->response->redirect($this->url->link('account/login', 'language=' . $this->config->get('config_language')));
        }
        
        $this->load->language('pms/bills');
        
        $this->document->setTitle($this->language->get('heading_unpaid'));
        
        // Get current project
        $this->load->model('pms/project');
        $project = $this->customer->getProject();
        
        if (!$project) {
            $this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']));
        }
        
        $data['project_id'] = $project['project_id'];
        
        // Filter parameters
        $filter_shareholder = isset($this->request->get['filter_shareholder']) ? $this->request->get['filter_shareholder'] : '';
        $filter_month = isset($this->request->get['filter_month']) ? $this->request->get['filter_month'] : '';
        $filter_bill_type = isset($this->request->get['filter_bill_type']) ? $this->request->get['filter_bill_type'] : '';
        $filter_apartment = isset($this->request->get['filter_apartment']) ? $this->request->get['filter_apartment'] : '';
        
        $filter_data = [
            'filter_shareholder' => $filter_shareholder,
            'filter_month'       => $filter_month,
            'filter_bill_type'   => $filter_bill_type,
            'filter_apartment'   => $filter_apartment
        ];
        
        $this->load->model('pms/bill');
        
        $data['unpaid_bills'] = $this->model_pms_bill->getUnpaidBillsForProject($project['project_id'], $filter_data);
        $data['bill_types'] = $this->model_pms_bill->getBillTypes();
        $data['available_months'] = $this->model_pms_bill->getAvailableMonths($project['project_id']);
        $data['total_unpaid_amount'] = $this->model_pms_bill->getTotalUnpaidAmount($project['project_id']);
        $data['payment_stats'] = $this->model_pms_bill->getBillPaymentStats($project['project_id']);
        
        // Filter values
        $data['filter_shareholder'] = $filter_shareholder;
        $data['filter_month'] = $filter_month;
        $data['filter_bill_type'] = $filter_bill_type;
        $data['filter_apartment'] = $filter_apartment;

        $data['language'] = $this->config->get('config_language');
        $data['customer_token'] = $this->session->data['customer_token'];
        
        // URLs
        $data['mark_paid_url'] = $this->url->link('account/pms_bills.markPaid', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        $data['bulk_mark_paid_url'] = $this->url->link('account/pms_bills.bulkMarkPaid', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        $data['back'] = $this->url->link('account/pms_bills', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        
        $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
        
        $this->response->setOutput($this->load->view('account/pms_bills_unpaid', $data));
    }
}
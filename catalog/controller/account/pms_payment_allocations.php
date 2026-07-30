<?php
namespace Opencart\Catalog\Controller\Account;

class PmsPaymentAllocations extends \Opencart\System\Engine\Controller {

    public function index(): void {
        if (!$this->customer->isLogged()) {
            $this->response->redirect($this->url->link('account/login', 'language=' . $this->config->get('config_language')));
        }
        
        // Check if user is Manager
        /*$customer_group_id = $this->customer->getGroupId();
        $manager_group_id = (int)$this->config->get('config_pms_manager_group_id');
        
        if ($customer_group_id != $manager_group_id) {
            $this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']));
        }*/
        
        $this->load->language('pms/payment_allocations');
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
        $filter_shareholder = isset($this->request->get['filter_shareholder']) ? $this->request->get['filter_shareholder'] : '';
        $filter_phase = isset($this->request->get['filter_phase']) ? $this->request->get['filter_phase'] : '';
        $filter_date_from = isset($this->request->get['filter_date_from']) ? $this->request->get['filter_date_from'] : '';
        $filter_date_to = isset($this->request->get['filter_date_to']) ? $this->request->get['filter_date_to'] : '';
        $filter_amount_min = isset($this->request->get['filter_amount_min']) ? $this->request->get['filter_amount_min'] : '';
        $filter_amount_max = isset($this->request->get['filter_amount_max']) ? $this->request->get['filter_amount_max'] : '';
        
        $sort = isset($this->request->get['sort']) ? $this->request->get['sort'] : 'pa.created_at';
        $order = isset($this->request->get['order']) ? $this->request->get['order'] : 'DESC';
        $page = isset($this->request->get['page']) ? (int)$this->request->get['page'] : 1;
        
        $this->load->model('pms/shareholder');
        $this->load->model('pms/payment');
        
        // Build URL for pagination
        $url = '';
        if ($filter_shareholder) $url .= '&filter_shareholder=' . urlencode($filter_shareholder);
        if ($filter_phase) $url .= '&filter_phase=' . $filter_phase;
        if ($filter_date_from) $url .= '&filter_date_from=' . $filter_date_from;
        if ($filter_date_to) $url .= '&filter_date_to=' . $filter_date_to;
        if ($filter_amount_min) $url .= '&filter_amount_min=' . $filter_amount_min;
        if ($filter_amount_max) $url .= '&filter_amount_max=' . $filter_amount_max;
        if ($sort) $url .= '&sort=' . $sort;
        if ($order) $url .= '&order=' . $order;
        
        $limit = $this->config->get('config_pagination_admin');
        
        $filter_data = [
            'project_id'          => $project['project_id'],
            'filter_shareholder'  => $filter_shareholder,
            'filter_phase'        => $filter_phase,
            'filter_date_from'    => $filter_date_from,
            'filter_date_to'      => $filter_date_to,
            'filter_amount_min'   => $filter_amount_min,
            'filter_amount_max'   => $filter_amount_max,
            'sort'                => $sort,
            'order'               => $order,
            'start'               => ($page - 1) * $limit,
            'limit'               => $limit
        ];
        
        $allocation_total = $this->model_pms_shareholder->getTotalPaymentAllocations($filter_data);
        $allocations = $this->model_pms_shareholder->getAllPaymentAllocations($filter_data);
        
        // Get summary
        $data['summary'] = $this->model_pms_shareholder->getPaymentAllocationSummary($project['project_id']);
        
        // Get payment phases for filter
        $data['payment_phases'] = $this->model_pms_payment->getPaymentPhases();

        $data['language'] = $this->config->get('config_language');

        $data['customer_token'] = $this->session->data['customer_token'];
        
        $data['allocations'] = [];
        foreach ($allocations as $allocation) {
            $data['allocations'][] = [
                'allocation_id'      => $allocation['allocation_id'],
                'shareholder_name'   => $allocation['shareholder_name'],
                'shareholder_code'   => $allocation['shareholder_code'],
                'ownership_units'    => $allocation['ownership_units'],
                'ownership_percentage' => round($allocation['ownership_percentage'], 2),
                'phase_name'         => $allocation['phase_name'],
                'allocated_amount'   => $allocation['allocated_amount'],
                'notes'              => $allocation['notes'],
                'created_by'         => $allocation['created_by_name'],
                'created_at'         => date('d M Y h:i A', strtotime($allocation['created_at'])),
                'date_only'          => date('d M Y', strtotime($allocation['created_at']))
            ];
        }
        
        // Pagination
        $data['pagination'] = $this->load->controller('common/pagination', [
			'total' => $allocation_total,
			'page'  => $page,
			'limit' => $this->config->get('config_pagination_admin'),
			'url'   => $this->url->link('account/pms_payment_allocations', $url .  'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token'] . '&page={page}')
		]);

		$data['results'] = sprintf($this->language->get('text_pagination'), ($allocation_total) ? (($page - 1) * $this->config->get('config_pagination_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_pagination_admin')) > ($allocation_total - $this->config->get('config_pagination_admin'))) ? $allocation_total : ((($page - 1) * $this->config->get('config_pagination_admin')) + $this->config->get('config_pagination_admin')), $allocation_total, ceil($allocation_total / $this->config->get('config_pagination_admin')));
        
        // Sort links
        $data['sort_date'] = $this->url->link('account/pms_payment_allocations', 'language=' . $this->config->get('config_language') . '&sort=pa.created_at' . $url . '&customer_token=' . $this->session->data['customer_token']);
        $data['sort_amount'] = $this->url->link('account/pms_payment_allocations', 'language=' . $this->config->get('config_language') . '&sort=pa.allocated_amount' . $url . '&customer_token=' . $this->session->data['customer_token']);
        $data['sort_shareholder'] = $this->url->link('account/pms_payment_allocations', 'language=' . $this->config->get('config_language') . '&sort=shareholder_name' . $url . '&customer_token=' . $this->session->data['customer_token']);
        $data['sort_phase'] = $this->url->link('account/pms_payment_allocations', 'language=' . $this->config->get('config_language') . '&sort=phase_name' . $url . '&customer_token=' . $this->session->data['customer_token']);
        
        // Bulk action URLs
        $data['bulk_action_url'] = $this->url->link('account/pms_payment_allocations.bulkAction', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        
        $data['bulk_allocation'] = $this->url->link('account/pms_payment_allocations.bulkAllocation', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        $data['bulk_allocation_rate'] = $this->url->link('account/pms_payment_allocations.bulkAllocationByRate', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);

        // Filter values
        $data['filter_shareholder'] = $filter_shareholder;
        $data['filter_phase'] = $filter_phase;
        $data['filter_date_from'] = $filter_date_from;
        $data['filter_date_to'] = $filter_date_to;
        $data['filter_amount_min'] = $filter_amount_min;
        $data['filter_amount_max'] = $filter_amount_max;
        
        $data['sort'] = $sort;
        $data['order'] = $order;
        
        $data['back'] = $this->url->link('account/manager', 'language=' . $this->config->get('config_language'). '&customer_token=' . $this->session->data['customer_token']);
        
        $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
        
        $this->response->setOutput($this->load->view('account/pms_payment_allocations', $data));
    }

    public function bulkAction(): void {
        $this->load->language('pms/shareholder');
        
        $json = [];
        
        $customer_group_id = $this->customer->getGroupId();
        $manager_group_id = (int)$this->config->get('config_pms_manager_group_id');
        
        if ($customer_group_id != $manager_group_id) {
            $json['error'] = $this->language->get('error_permission');
        }
        
        if (!$json) {
            $this->load->model('pms/shareholder');
            
            $action = $this->request->post['action'];
            $selected = $this->request->post['selected'];
            
            if (!empty($selected) && is_array($selected)) {
                foreach ($selected as $id) {
                    switch ($action) {
                        case 'activate':
                            $this->model_pms_shareholder->updateShareholderStatus($id, 'active');
                            $this->model_pms_shareholder->updateCustomerStatus($id, 1);
                            break;
                        case 'deactivate':
                            $this->model_pms_shareholder->updateShareholderStatus($id, 'inactive');
                            $this->model_pms_shareholder->updateCustomerStatus($id, 0);
                            break;
                        case 'suspend':
                            $this->model_pms_shareholder->updateShareholderStatus($id, 'suspended');
                            $this->model_pms_shareholder->updateCustomerStatus($id, 0);
                            break;
                        case 'delete':
                            $this->model_pms_shareholder->deleteShareholder($id);
                            break;
                    }
                }
                $json['success'] = $this->language->get('text_bulk_success');
            } else {
                $json['error'] = $this->language->get('error_no_selection');
            }
            
            $json['redirect'] = $this->url->link('account/pms_payment_allocations', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Bulk Payment Allocation page
     */
    public function bulkAllocation(): void {
        if (!$this->customer->isLogged()) {
            $this->response->redirect($this->url->link('account/login', 'language=' . $this->config->get('config_language')));
        }
        
        $this->load->language('pms/shareholder');
        $this->load->language('pms/payment_allocation');
        
        $this->document->setTitle($this->language->get('heading_bulk_allocation'));
        
        // Get current project
        $this->load->model('pms/project');
        $project = $this->customer->getProject();
        
        if (!$project) {
            $this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']));
        }
        
        $data['project_id'] = $project['project_id'];
        $data['project_name'] = $project['name'];
        $data['total_shares'] = $project['total_shares'];
        
        // Load models
        $this->load->model('pms/shareholder');
        $this->load->model('pms/payment');
        
        // Get allocation summary
        $data['allocation_summary'] = $this->model_pms_shareholder->getAllocationSummary($project['project_id']);
        
        // Get payment phases
        $data['payment_phases'] = $this->model_pms_payment->getPaymentPhases();
        
        // Get active shareholders count
        $shareholders = $this->model_pms_shareholder->getShareholders(['project_id' => $project['project_id']]);
        $data['shareholder_count'] = count($shareholders);
        
        // Calculate total ownership
        $total_ownership = 0;
        foreach ($shareholders as $sh) {
            $total_ownership += $sh['ownership_units'];
        }
        $data['total_ownership'] = $total_ownership;
        
        // Form action
        $data['action_equal'] = $this->url->link('account/pms_shareholders.saveBulkAllocationEqual', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        $data['action_percentage'] = $this->url->link('account/pms_shareholders.saveBulkAllocationPercentage', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        $data['back'] = $this->url->link('account/pms_shareholders', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        
        $data['customer_token'] = $this->session->data['customer_token'];
        
        $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
        
        $this->response->setOutput($this->load->view('account/pms_shareholder_bulk_allocation', $data));
    }

    /**
     * Save bulk allocation (Equal distribution)
     */
    public function saveBulkAllocationEqual(): void {
        $this->load->language('pms/payment_allocation');
        
        $json = [];
        
        // Check permission
        $customer_group_id = $this->customer->getGroupId();
        $manager_group_id = (int)$this->config->get('config_pms_manager_group_id');
        
        if ($customer_group_id != $manager_group_id) {
            $json['error'] = $this->language->get('error_permission');
        }
        
        if (!$json) {
            $this->load->model('pms/shareholder');
            $this->load->model('pms/project');
            
            $project = $this->customer->getProject();
            
            $allocation_data = [
                'project_id'        => $project['project_id'],
                'payment_phase_id'  => (int)$this->request->post['payment_phase_id'],
                'allocated_amount'  => (float)$this->request->post['allocated_amount'],
                'notes'             => $this->request->post['notes'] ?? ''
            ];
            
            $result = $this->model_pms_shareholder->bulkAllocatePayment($allocation_data);
            
            if ($result['success']) {
                $json['success'] = $this->language->get('text_bulk_allocation_success');
                $json['allocated_count'] = $result['allocated'];
                $json['redirect'] = $this->url->link('account/pms_payment_allocation.index', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
            } else {
                $json['error'] = implode(', ', $result['errors']);
            }
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Save bulk allocation (Percentage based)
     */
    public function saveBulkAllocationPercentage(): void {
        $this->load->language('pms/payment_allocation');
        
        $json = [];
        
        // Check permission
        $customer_group_id = $this->customer->getGroupId();
        $manager_group_id = (int)$this->config->get('config_pms_manager_group_id');
        
        if ($customer_group_id != $manager_group_id) {
            $json['error'] = $this->language->get('error_permission');
        }
        
        if (!$json) {
            $this->load->model('pms/shareholder');
            $this->load->model('pms/project');
            
            $project = $this->customer->getProject();
            
            $allocation_data = [
                'project_id'        => $project['project_id'],
                'payment_phase_id'  => (int)$this->request->post['payment_phase_id'],
                'total_amount'      => (float)$this->request->post['total_amount'],
                'notes'             => $this->request->post['notes'] ?? ''
            ];
            
            $result = $this->model_pms_shareholder->bulkAllocatePaymentByPercentage($allocation_data);
            
            if ($result['success']) {
                $json['success'] = $this->language->get('text_bulk_allocation_success');
                $json['allocated'] = $result['allocated'];
                $json['redirect'] = $this->url->link('account/pms_payment_allocations.index', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
            } else {
                $json['error'] = implode(', ', $result['errors']);
            }
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Get shareholders preview for bulk allocation
     */
    public function getShareholdersPreview(): void {
        $json = [];
        
        $this->load->model('pms/project');
        $this->load->model('pms/shareholder');
        
        $project = $this->customer->getProject();
        
        if ($project) {
            $shareholders = $this->model_pms_shareholder->getShareholders(['project_id' => $project['project_id']]);
            $total_amount = (float)$this->request->get['total_amount'];
            
            $json['success'] = true;
            $json['shareholders'] = [];
            
            foreach ($shareholders as $shareholder) {
                $amount = $total_amount * ($shareholder['ownership_percentage'] / 100);
                $json['shareholders'][] = [
                    'name' => $shareholder['firstname'] . ' ' . $shareholder['lastname'],
                    'percentage' => round($shareholder['ownership_percentage'], 2),
                    'amount' => $amount
                ];
            }
        } else {
            $json['success'] = false;
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Proportional Bulk Allocation page
     */
    public function proportionalAllocation(): void {
        if (!$this->customer->isLogged()) {
            $this->response->redirect($this->url->link('account/login', 'language=' . $this->config->get('config_language')));
        }
        
        $this->load->language('pms/shareholder');
        $this->load->language('pms/payment_allocation');
        
        $this->document->setTitle($this->language->get('heading_proportional_allocation'));
        
        // Get current project
        $this->load->model('pms/project');
        $project = $this->customer->getProject();
        
        if (!$project) {
            $this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']));
        }
        
        $data['project_id'] = $project['project_id'];
        $data['project_name'] = $project['name'];
        $data['total_shares'] = $project['total_shares'];
        
        // Load models
        $this->load->model('pms/shareholder');
        $this->load->model('pms/payment');
        
        // Get all shareholders for dropdown
        $shareholders = $this->model_pms_shareholder->getShareholders(['project_id' => $project['project_id']]);
        $data['shareholders'] = $shareholders;
        
        // Calculate total ownership
        $total_ownership = 0;
        foreach ($shareholders as $sh) {
            $total_ownership += $sh['ownership_units'];
        }
        $data['total_ownership'] = $total_ownership;
        
        // Get payment phases
        $data['payment_phases'] = $this->model_pms_payment->getPaymentPhases();
        
        // Form action
        $data['action'] = $this->url->link('account/pms_payment_allocations.saveProportionalAllocation', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        $data['back'] = $this->url->link('account/pms_payment_allocations', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        
        $data['customer_token'] = $this->session->data['customer_token'];
        
        $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
        
        $this->response->setOutput($this->load->view('account/pms_shareholder_proportional_allocation', $data));
    }

    /**
     * Save proportional allocation
     */
    public function saveProportionalAllocation(): void {
        $this->load->language('pms/payment_allocation');
        
        $json = [];
        
        // Check permission
        $customer_group_id = $this->customer->getGroupId();
        $manager_group_id = (int)$this->config->get('config_pms_manager_group_id');
        
        if ($customer_group_id != $manager_group_id) {
            $json['error'] = $this->language->get('error_permission');
        }
        
        if (!$json) {
            $this->load->model('pms/shareholder');
            $this->load->model('pms/project');
            
            $project = $this->customer->getProject();
            
            $allocation_data = [
                'project_id'                 => $project['project_id'],
                'reference_shareholder_id'   => (int)$this->request->post['reference_shareholder_id'],
                'reference_amount'           => (float)$this->request->post['reference_amount'],
                'payment_phase_id'           => (int)$this->request->post['payment_phase_id'],
                'notes'                      => $this->request->post['notes'] ?? ''
            ];
            
            $result = $this->model_pms_shareholder->proportionalAllocation($allocation_data);
            
            if ($result['success']) {
                $json['success'] = $this->language->get('text_proportional_allocation_success');
                $json['allocated_count'] = $result['allocated'];
                $json['total_amount'] = $result['total_amount'];
                $json['redirect'] = $this->url->link('account/pms_payment_allocations.paymentAllocation', 'language=' . $this->config->get('config_language') . '&customer_token='. $this->session->data['customer_token']);
            } else {
                $json['error'] = implode(', ', $result['errors']);
            }
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Get preview for proportional allocation
     */
    public function getProportionalPreview(): void {
        $json = [];
        
        $this->load->model('pms/project');
        $this->load->model('pms/shareholder');
        
        $project = $this->customer->getProject();
        
        if ($project) {
            $reference_shareholder_id = (int)$this->request->get['reference_shareholder_id'];
            $reference_amount = (float)$this->request->get['reference_amount'];
            
            // Get reference shareholder percentage
            $reference = $this->db->query("
                SELECT s.id, 
                    (s.ownership_units / p.total_shares * 100) as ownership_percentage,
                    CONCAT(c.firstname, ' ', c.lastname) as shareholder_name
                FROM `" . DB_PREFIX . "shareholders` s
                INNER JOIN `" . DB_PREFIX . "pms_project` p ON s.project_id = p.project_id
                INNER JOIN `" . DB_PREFIX . "customer` c ON s.user_id = c.customer_id
                WHERE s.id = '" . (int)$reference_shareholder_id . "'
                AND s.project_id = '" . (int)$project['project_id'] . "'
            ");
            
            if ($reference->num_rows > 0) {
                $reference_percentage = (float)$reference->row['ownership_percentage'];
                $total_amount = ($reference_amount / $reference_percentage) * 100;
                
                // Get all shareholders
                $shareholders = $this->model_pms_shareholder->getShareholders(['project_id' => $project['project_id']]);
                
                $json['success'] = true;
                $json['total_amount'] = $total_amount;
                $json['reference_shareholder'] = $reference->row['shareholder_name'];
                $json['reference_percentage'] = round($reference_percentage, 2);
                $json['reference_amount'] = $reference_amount;
                $json['shareholders'] = [];
                
                foreach ($shareholders as $shareholder) {
                    $amount = $total_amount * ($shareholder['ownership_percentage'] / 100);
                    $json['shareholders'][] = [
                        'name' => $shareholder['firstname'] . ' ' . $shareholder['lastname'],
                        'percentage' => round($shareholder['ownership_percentage'], 2),
                        'amount' => $amount
                    ];
                }
            } else {
                $json['success'] = false;
                $json['error'] = 'Reference shareholder not found';
            }
        } else {
            $json['success'] = false;
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Bulk Allocation by Rate per Percentage
     */
    public function bulkAllocationByRate(): void {
        if (!$this->customer->isLogged()) {
            $this->response->redirect($this->url->link('account/login', 'language=' . $this->config->get('config_language')));
        }
        
        $this->load->language('pms/shareholder');
        $this->load->language('pms/payment_allocation');
        
        $this->document->setTitle($this->language->get('heading_bulk_allocation_rate'));
        
        // Get current project
        $this->load->model('pms/project');
        $project = $this->customer->getProject();
        
        if (!$project) {
            $this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']));
        }
        
        $data['project_id'] = $project['project_id'];
        $data['project_name'] = $project['name'];
        $data['total_shares'] = $project['total_shares'];
        
        // Load models
        $this->load->model('pms/shareholder');
        $this->load->model('pms/payment');
        
        // Get all shareholders for display
        $shareholders = $this->model_pms_shareholder->getShareholders(['project_id' => $project['project_id']]);
        $data['shareholders'] = $shareholders;
        
        // Get payment phases
        $data['payment_phases'] = $this->model_pms_payment->getPaymentPhases();
        
        // Form action
        $data['action'] = $this->url->link('account/pms_payment_allocations.saveBulkAllocationByRate', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        $data['back'] = $this->url->link('account/pms_payment_allocations', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        
        $data['customer_token'] = $this->session->data['customer_token'];
        
        $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
        
        $this->response->setOutput($this->load->view('account/pms_shareholder_bulk_allocation_rate', $data));
    }

    /**
     * Save bulk allocation by rate
     */
    public function saveBulkAllocationByRate(): void {
        $this->load->language('pms/payment_allocation');
        
        $json = [];
        
        // Check permission
        /*$customer_group_id = $this->customer->getGroupId();
        $manager_group_id = (int)$this->config->get('config_pms_manager_group_id');
        
        if ($customer_group_id != $manager_group_id) {
            $json['error'] = $this->language->get('error_permission');
        }*/
        
        if (!$json) {
            $this->load->model('pms/shareholder');
            $this->load->model('pms/project');
            
            $project = $this->customer->getProject();
            
            $allocation_data = [
                'project_id'        => $project['project_id'],
                'payment_phase_id'  => (int)$this->request->post['payment_phase_id'],
                'rate_per_unit'     => (float)$this->request->post['rate_per_unit'],
                'calculation_type'  => $this->request->post['calculation_type'],
                'notes'             => $this->request->post['notes'] ?? ''
            ];
            
            $result = $this->model_pms_shareholder->bulkAllocateByPerUnitRate($allocation_data);
            
            if ($result['success']) {
                $json['success'] = $this->language->get('text_bulk_allocation_success');
                $json['allocated_count'] = $result['allocated'];
                $json['total_allocated'] = $result['total_allocated'];
                $json['redirect'] = $this->url->link('account/pms_payment_allocations', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
            } else {
                $json['error'] = implode(', ', $result['errors']);
            }
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Get preview for rate-based allocation
     */
    public function getRateAllocationPreview(): void {
        $json = [];
        
        $this->load->model('pms/project');
        $this->load->model('pms/shareholder');
        
        $project = $this->customer->getProject();
        
        if ($project) {
            $calculation_type = $this->request->get['calculation_type'];
            $rate_per_unit = (float)$this->request->get['rate_per_unit'];
            
            if ($calculation_type == 'percentage') {
                // Get shareholders with percentage
                $shareholders = $this->db->query("
                    SELECT s.id, 
                        (s.ownership_units / p.total_shares * 100) as ownership_percentage,
                        CONCAT(c.firstname, ' ', c.lastname) as shareholder_name
                    FROM `" . DB_PREFIX . "shareholders` s
                    INNER JOIN `" . DB_PREFIX . "pms_project` p ON s.project_id = p.project_id
                    INNER JOIN `" . DB_PREFIX . "customer` c ON s.user_id = c.customer_id
                    WHERE s.project_id = '" . (int)$project['project_id'] . "'
                    AND s.is_deleted = 0
                    AND s.status = 'active'
                ");
                
                $json['success'] = true;
                $json['calculation_type'] = 'percentage';
                $json['rate_per_unit'] = $rate_per_unit;
                $json['shareholders'] = [];
                $total_amount = 0;
                
                foreach ($shareholders->rows as $shareholder) {
                    $amount = $rate_per_unit * $shareholder['ownership_percentage'];
                    $total_amount += $amount;
                    $json['shareholders'][] = [
                        'name' => $shareholder['shareholder_name'],
                        'value' => round($shareholder['ownership_percentage'], 2),
                        'value_type' => '%',
                        'amount' => $amount
                    ];
                }
                $json['total_amount'] = $total_amount;
                
            } else {
                // Get shareholders with units
                $shareholders = $this->db->query("
                    SELECT s.id, s.ownership_units,
                        CONCAT(c.firstname, ' ', c.lastname) as shareholder_name
                    FROM `" . DB_PREFIX . "shareholders` s
                    INNER JOIN `" . DB_PREFIX . "customer` c ON s.user_id = c.customer_id
                    WHERE s.project_id = '" . (int)$project['project_id'] . "'
                    AND s.is_deleted = 0
                    AND s.status = 'active'
                ");
                
                $json['success'] = true;
                $json['calculation_type'] = 'units';
                $json['rate_per_unit'] = $rate_per_unit;
                $json['shareholders'] = [];
                $total_amount = 0;
                
                foreach ($shareholders->rows as $shareholder) {
                    $amount = $rate_per_unit * $shareholder['ownership_units'];
                    $total_amount += $amount;
                    $json['shareholders'][] = [
                        'name' => $shareholder['shareholder_name'],
                        'value' => $shareholder['ownership_units'],
                        'value_type' => 'shares',
                        'amount' => $amount
                    ];
                }
                $json['total_amount'] = $total_amount;
            }
        } else {
            $json['success'] = false;
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
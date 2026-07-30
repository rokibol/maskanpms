<?php
namespace Opencart\Catalog\Controller\Account;

class PmsShareholders extends \Opencart\System\Engine\Controller {

    public function index(): void {
        // Check if logged in
        if (!$this->customer->isLogged()) {
            $this->session->data['redirect'] = $this->url->link('account/pms_shareholders', 'language=' . $this->config->get('config_language'));
            $this->response->redirect($this->url->link('account/login', 'language=' . $this->config->get('config_language')));
        }
        
        // Check if user is Manager
        /*$customer_group_id = $this->customer->getGroupId();
        $manager_group_id = (int)$this->config->get('config_pms_manager_group_id');
        
        if ($customer_group_id != $manager_group_id) {
            $this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']));
        }*/
        
        $this->load->language('pms/shareholder');
        
        $this->document->setTitle($this->language->get('heading_title'));
        
        // Get current project
        $this->load->model('pms/project');
        
        $project = $this->customer->getProject();
        
        if (!$project) {
            $this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']));
        }
        
        $data['project_id'] = $project['project_id'];
        $data['project_name'] = $project['name'];
        $data['total_shares'] = $project['total_shares'];
        
        // Set up pagination and filters
        $url = '';
        
        if (isset($this->request->get['filter_search'])) {
            $url .= '&filter_search=' . urlencode(html_entity_decode($this->request->get['filter_search'], ENT_QUOTES, 'UTF-8'));
        }
        
        if (isset($this->request->get['filter_status'])) {
            $url .= '&filter_status=' . $this->request->get['filter_status'];
        }
        
        if (isset($this->request->get['filter_user_status'])) {
            $url .= '&filter_user_status=' . $this->request->get['filter_user_status'];
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
        
        // Get filter values
        $filter_search = isset($this->request->get['filter_search']) ? $this->request->get['filter_search'] : '';
        $filter_status = isset($this->request->get['filter_status']) ? $this->request->get['filter_status'] : '';
        $filter_user_status = isset($this->request->get['filter_user_status']) ? $this->request->get['filter_user_status'] : '';
        
        $sort = isset($this->request->get['sort']) ? $this->request->get['sort'] : 'c.firstname';
        $order = isset($this->request->get['order']) ? $this->request->get['order'] : 'ASC';
        $page = isset($this->request->get['page']) ? (int)$this->request->get['page'] : 1;
        
        $limit = $this->config->get('config_pagination_admin');
        
        $filter_data = [
            'project_id'          => $project['project_id'],
            'filter_search'       => $filter_search,
            'filter_status'       => $filter_status,
            'filter_user_status'  => $filter_user_status,
            'sort'                => $sort,
            'order'               => $order,
            'start'               => ($page - 1) * $limit,
            'limit'               => $limit
        ];
        
        $this->load->model('pms/shareholder');
        
        $shareholder_total = $this->model_pms_shareholder->getTotalShareholders($filter_data);
        $shareholder_total_allocated_unit = $this->model_pms_shareholder->getTotalUnitAllocated($filter_data);
        $results = $this->model_pms_shareholder->getShareholders($filter_data);

        $data['shareholder_total_allocated_unit'] = $shareholder_total_allocated_unit;
        
        $data['shareholders'] = [];
        
        foreach ($results as $result) {
            $due_amount = $this->getShareholderDue($result['id']);
            
            // Status badge class
            $status_class = '';
            switch ($result['status']) {
                case 'active':
                    $status_class = 'success';
                    break;
                case 'inactive':
                    $status_class = 'warning';
                    break;
                case 'suspended':
                    $status_class = 'danger';
                    break;
                default:
                    $status_class = 'secondary';
            }
            
            $user_status_class = $result['user_status'] ? 'success' : 'danger';
            $user_status_text = $result['user_status'] ? 'Enabled' : 'Disabled';
            
            $data['shareholders'][] = [
                'id'        => $result['id'],
                'shareholder_code'      => $result['shareholder_code'],
                'name'                  => $result['firstname'] . ' ' . $result['lastname'],
                'firstname'             => $result['firstname'],
                'lastname'              => $result['lastname'],
                'email'                 => $result['email'],
                'phone'                 => $result['phone'],
                'ownership_units'       => $result['ownership_units'],
                'ownership_percentage'  => round($result['ownership_percentage'], 2),
                'status'                => $result['status'],
                'status_class'          => $status_class,
                'user_status'           => $result['user_status'],
                'user_status_class'     => $user_status_class,
                'user_status_text'      => $user_status_text,
                'due_amount'            => $due_amount,
                'address'               => $result['address'],
                'nominee_name'          => $result['nominee_name'],
                'nominee_relation'      => $result['nominee_relation'],
                'nominee_phone'         => $result['nominee_phone'],
                'registered_date'       => date($this->language->get('date_format_short'), strtotime($result['registered_date'])),
                'edit'                  => $this->url->link('account/pms_shareholders.edit', 'language=' . $this->config->get('config_language') . '&id=' . $result['id'] . $url . '&customer_token=' . $this->session->data['customer_token']),
                'view'                  => $this->url->link('account/pms_shareholders.view', 'language=' . $this->config->get('config_language') . '&id=' . $result['id'] . $url . '&customer_token=' . $this->session->data['customer_token']),
                'payment_allocation'    => $this->url->link('account/pms_shareholders.paymentAllocation', 'language=' . $this->config->get('config_language') . '&shareholder_id=' . $result['id'] . $url . '&customer_token=' . $this->session->data['customer_token']),
                'delete'                => $this->url->link('account/pms_shareholders.delete', 'language=' . $this->config->get('config_language') . '&id=' . $result['id'] . $url . '&customer_token=' . $this->session->data['customer_token'])
            ];
        }
        
        // Pagination
        $data['pagination'] = $this->load->controller('common/pagination', [
			'total' => $shareholder_total,
			'page'  => $page,
			'limit' => $this->config->get('config_pagination_admin'),
			'url'   => $this->url->link('account/pms_shareholders', 'language=' . $this->config->get('config_language') . $url .  '&customer_token=' . $this->session->data['customer_token'] . '&page={page}')
		]);

		$data['results'] = sprintf($this->language->get('text_pagination'), ($shareholder_total) ? (($page - 1) * $this->config->get('config_pagination_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_pagination_admin')) > ($shareholder_total - $this->config->get('config_pagination_admin'))) ? $shareholder_total : ((($page - 1) * $this->config->get('config_pagination_admin')) + $this->config->get('config_pagination_admin')), $shareholder_total, ceil($shareholder_total / $this->config->get('config_pagination_admin')));
        
        // Sort links
        $data['sort_name'] = $this->url->link('account/pms_shareholders', 'language=' . $this->config->get('config_language') . '&sort=c.firstname' . $url . '&customer_token=' . $this->session->data['customer_token']);
        $data['sort_email'] = $this->url->link('account/pms_shareholders', 'language=' . $this->config->get('config_language') . '&sort=c.email' . $url . '&customer_token=' . $this->session->data['customer_token']);
        $data['sort_units'] = $this->url->link('account/pms_shareholders', 'language=' . $this->config->get('config_language') . '&sort=s.ownership_units' . $url . '&customer_token=' . $this->session->data['customer_token']);
        $data['sort_percentage'] = $this->url->link('account/pms_shareholders', 'language=' . $this->config->get('config_language') . '&sort=ownership_percentage' . $url . '&customer_token=' . $this->session->data['customer_token']);
        $data['sort_status'] = $this->url->link('account/pms_shareholders', 'language=' . $this->config->get('config_language') . '&sort=s.status' . $url . '&customer_token=' . $this->session->data['customer_token']);
        $data['sort_date'] = $this->url->link('account/pms_shareholders', 'language=' . $this->config->get('config_language') . '&sort=s.created_at' . $url . '&customer_token=' . $this->session->data['customer_token']);
        
        // Filter values
        $data['filter_search'] = $filter_search;
        $data['filter_status'] = $filter_status;
        $data['filter_user_status'] = $filter_user_status;
        
        // Status options for filter
        $data['statuses'] = $this->model_pms_shareholder->getStatuses();
        $data['user_statuses'] = [
            '1' => 'Enabled',
            '0' => 'Disabled'
        ];
        
        // Sort and order
        $data['sort'] = $sort;
        $data['order'] = $order;
        
        $data['language'] = $this->config->get('config_language');
        $data['customer_token'] = $this->session->data['customer_token'];
        
        // URLs
        $data['add'] = $this->url->link('account/pms_shareholders.add', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        $data['back'] = $this->url->link('account/manager', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        
        $data['proportional_allocation'] = $this->url->link('account/pms_shareholders.proportionalAllocation', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        
        // Template parts
        $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
        
        $this->response->setOutput($this->load->view('account/pms_shareholders_list', $data));
    }
    
    private function getShareholderDue(int $id): float {
        $this->load->model('pms/shareholder');
        return $this->model_pms_shareholder->getTotalDue($id);
    }
    
    public function add(): void {
        if (!$this->customer->isLogged()) {
            $this->response->redirect($this->url->link('account/login', 'language=' . $this->config->get('config_language')));
        }
        
        $this->load->language('pms/shareholder');
        
        $this->document->setTitle($this->language->get('heading_add'));
        
        // Get current project
        $this->load->model('pms/project');

        $project = $this->customer->getProject();
        
        if (!$project) {
            $this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']));
        }
        
        $data['project_id'] = $project['project_id'];
        $data['project_name'] = $project['name'];
        $data['total_shares'] = $project['total_shares'];

        $data['text_form'] = $this->language->get("text_add");
        
        // Form action
        $data['action'] = $this->url->link('account/pms_shareholders.save', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        $data['back'] = $this->url->link('account/pms_shareholders', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        
        // Set form values
        if (isset($this->request->post['firstname'])) {
            $data['firstname'] = $this->request->post['firstname'];
        } else {
            $data['firstname'] = '';
        }
        
        if (isset($this->request->post['lastname'])) {
            $data['lastname'] = $this->request->post['lastname'];
        } else {
            $data['lastname'] = '';
        }
        
        if (isset($this->request->post['email'])) {
            $data['email'] = $this->request->post['email'];
        } else {
            $data['email'] = '';
        }
        
        if (isset($this->request->post['phone'])) {
            $data['phone'] = $this->request->post['phone'];
        } else {
            $data['phone'] = '';
        }
        
        if (isset($this->request->post['password'])) {
            $data['password'] = $this->request->post['password'];
        } else {
            $data['password'] = '';
        }
        
        if (isset($this->request->post['ownership_units'])) {
            $data['ownership_units'] = $this->request->post['ownership_units'];
        } else {
            $data['ownership_units'] = '';
        }
        
        if (isset($this->request->post['address'])) {
            $data['address'] = $this->request->post['address'];
        } else {
            $data['address'] = '';
        }
        
        if (isset($this->request->post['nominee_name'])) {
            $data['nominee_name'] = $this->request->post['nominee_name'];
        } else {
            $data['nominee_name'] = '';
        }
        
        if (isset($this->request->post['nominee_relation'])) {
            $data['nominee_relation'] = $this->request->post['nominee_relation'];
        } else {
            $data['nominee_relation'] = '';
        }
        
        if (isset($this->request->post['nominee_phone'])) {
            $data['nominee_phone'] = $this->request->post['nominee_phone'];
        } else {
            $data['nominee_phone'] = '';
        }
        
        if (isset($this->request->post['bank_account_no'])) {
            $data['bank_account_no'] = $this->request->post['bank_account_no'];
        } else {
            $data['bank_account_no'] = '';
        }
        
        if (isset($this->request->post['bank_name'])) {
            $data['bank_name'] = $this->request->post['bank_name'];
        } else {
            $data['bank_name'] = '';
        }
        
        if (isset($this->request->post['tin_number'])) {
            $data['tin_number'] = $this->request->post['tin_number'];
        } else {
            $data['tin_number'] = '';
        }
        
        if (isset($this->request->post['notes'])) {
            $data['notes'] = $this->request->post['notes'];
        } else {
            $data['notes'] = '';
        }
        
        $data['customer_token'] = $this->session->data['customer_token'];
        
        $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
        
        $this->response->setOutput($this->load->view('account/pms_shareholder_form', $data));
    }
    
    public function edit(): void {
        if (!$this->customer->isLogged()) {
            $this->response->redirect($this->url->link('account/login', 'language=' . $this->config->get('config_language')));
        }
        
        $this->load->language('pms/shareholder');
        
        $this->document->setTitle($this->language->get('heading_edit'));
        
        // Get current project
        $this->load->model('pms/project');
        $project = $this->customer->getProject();
        
        if (!$project) {
            $this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']));
        }
        
        $this->load->model('pms/shareholder');
        
        $id = isset($this->request->get['id']) ? (int)$this->request->get['id'] : 0;
        $shareholder_info = $this->model_pms_shareholder->getShareholder($id);
        
        if (!$shareholder_info) {
            $this->response->redirect($this->url->link('account/pms_shareholders', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']));
        }
        
        $data['id'] = $id;
        $data['project_id'] = $project['project_id'];
        $data['project_name'] = $project['name'];
        $data['total_shares'] = $project['total_shares'];

        $data['text_form'] = $this->language->get("text_edit");
        
        // Form action
        $data['action'] = $this->url->link('account/pms_shareholders.save', 'language=' . $this->config->get('config_language') . '&id=' . $id . '&customer_token=' . $this->session->data['customer_token']);
        $data['back'] = $this->url->link('account/pms_shareholders', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        
        // Set form values from database
        if (isset($this->request->post['firstname'])) {
            $data['firstname'] = $this->request->post['firstname'];
        } elseif (!empty($shareholder_info)) {
            $data['firstname'] = $shareholder_info['firstname'];
        } else {
            $data['firstname'] = '';
        }
        
        if (isset($this->request->post['lastname'])) {
            $data['lastname'] = $this->request->post['lastname'];
        } elseif (!empty($shareholder_info)) {
            $data['lastname'] = $shareholder_info['lastname'];
        } else {
            $data['lastname'] = '';
        }
        
        if (isset($this->request->post['email'])) {
            $data['email'] = $this->request->post['email'];
        } elseif (!empty($shareholder_info)) {
            $data['email'] = $shareholder_info['email'];
        } else {
            $data['email'] = '';
        }
        
        if (isset($this->request->post['phone'])) {
            $data['phone'] = $this->request->post['phone'];
        } elseif (!empty($shareholder_info)) {
            $data['phone'] = $shareholder_info['phone'];
        } else {
            $data['phone'] = '';
        }
        
        // Password field (leave empty for edit, only update if filled)
        if (isset($this->request->post['password'])) {
            $data['password'] = $this->request->post['password'];
        } else {
            $data['password'] = '';
        }
        
        if (isset($this->request->post['ownership_units'])) {
            $data['ownership_units'] = $this->request->post['ownership_units'];
        } elseif (!empty($shareholder_info)) {
            $data['ownership_units'] = $shareholder_info['ownership_units'];
        } else {
            $data['ownership_units'] = '';
        }
        
        if (isset($this->request->post['address'])) {
            $data['address'] = $this->request->post['address'];
        } elseif (!empty($shareholder_info)) {
            $data['address'] = $shareholder_info['address'];
        } else {
            $data['address'] = '';
        }
        
        if (isset($this->request->post['nominee_name'])) {
            $data['nominee_name'] = $this->request->post['nominee_name'];
        } elseif (!empty($shareholder_info)) {
            $data['nominee_name'] = $shareholder_info['nominee_name'];
        } else {
            $data['nominee_name'] = '';
        }
        
        if (isset($this->request->post['nominee_relation'])) {
            $data['nominee_relation'] = $this->request->post['nominee_relation'];
        } elseif (!empty($shareholder_info)) {
            $data['nominee_relation'] = $shareholder_info['nominee_relation'];
        } else {
            $data['nominee_relation'] = '';
        }
        
        if (isset($this->request->post['nominee_phone'])) {
            $data['nominee_phone'] = $this->request->post['nominee_phone'];
        } elseif (!empty($shareholder_info)) {
            $data['nominee_phone'] = $shareholder_info['nominee_phone'];
        } else {
            $data['nominee_phone'] = '';
        }
        
        if (isset($this->request->post['bank_account_no'])) {
            $data['bank_account_no'] = $this->request->post['bank_account_no'];
        } elseif (!empty($shareholder_info)) {
            $data['bank_account_no'] = $shareholder_info['bank_account_no'];
        } else {
            $data['bank_account_no'] = '';
        }
        
        if (isset($this->request->post['bank_name'])) {
            $data['bank_name'] = $this->request->post['bank_name'];
        } elseif (!empty($shareholder_info)) {
            $data['bank_name'] = $shareholder_info['bank_name'];
        } else {
            $data['bank_name'] = '';
        }
        
        if (isset($this->request->post['tin_number'])) {
            $data['tin_number'] = $this->request->post['tin_number'];
        } elseif (!empty($shareholder_info)) {
            $data['tin_number'] = $shareholder_info['tin_number'];
        } else {
            $data['tin_number'] = '';
        }
        
        if (isset($this->request->post['notes'])) {
            $data['notes'] = $this->request->post['notes'];
        } elseif (!empty($shareholder_info)) {
            $data['notes'] = $shareholder_info['notes'];
        } else {
            $data['notes'] = '';
        }
        
        $data['customer_token'] = $this->session->data['customer_token'];
        
        $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
        
        $this->response->setOutput($this->load->view('account/pms_shareholder_form', $data));
    }
    
    public function view(): void {
        if (!$this->customer->isLogged()) {
            $this->response->redirect($this->url->link('account/login', 'language=' . $this->config->get('config_language')));
        }
        
        $this->load->language('pms/shareholder');
        
        $this->document->setTitle($this->language->get('heading_view'));
        
        $this->load->model('pms/shareholder');
        
        $id = isset($this->request->get['id']) ? (int)$this->request->get['id'] : 0;
        $shareholder_info = $this->model_pms_shareholder->getShareholder($id);
        
        if (!$shareholder_info) {
            $this->response->redirect($this->url->link('account/pms_shareholders', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']));
        }
        
        $data['shareholder'] = [
            'shareholder_code'      => $shareholder_info['shareholder_code'],
            'name'                  => $shareholder_info['firstname'] . ' ' . $shareholder_info['lastname'],
            'email'                 => $shareholder_info['email'],
            'phone'                 => $shareholder_info['phone'],
            'ownership_units'       => $shareholder_info['ownership_units'],
            'ownership_percentage'  => round(($shareholder_info['ownership_units'] / 30) * 100, 2),
            'status'                => $shareholder_info['status'],
            'address'               => $shareholder_info['address'],
            'nominee_name'          => $shareholder_info['nominee_name'],
            'nominee_relation'      => $shareholder_info['nominee_relation'],
            'nominee_phone'         => $shareholder_info['nominee_phone'],
            'bank_account_no'       => $shareholder_info['bank_account_no'],
            'bank_name'             => $shareholder_info['bank_name'],
            'tin_number'            => $shareholder_info['tin_number'],
            'notes'                 => $shareholder_info['notes'],
            'due_amount'            => $this->model_pms_shareholder->getTotalDue($id),
            'credit_balance'        => $shareholder_info['current_credit_balance'],
            'registered_date'       => date($this->language->get('date_format_short'), strtotime($shareholder_info['registered_date']))
        ];

        $data['payment_allocations'] = $this->model_pms_shareholder->getPaymentAllocations($this->request->get['id']);
        $data['total_allocated'] = $this->model_pms_shareholder->getTotalAllocatedPayment($this->request->get['id']);
        $data['payment_allocation_link'] = $this->url->link('account/pms_shareholders.paymentAllocation', 'language=' . $this->config->get('config_language') . '&shareholder_id=' . $this->request->get['id'] . '&customer_token=' . $this->session->data['customer_token']);
        
        $data['back'] = $this->url->link('account/pms_shareholders', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        $data['edit'] = $this->url->link('account/pms_shareholders.edit', 'language=' . $this->config->get('config_language') . '&id=' . $id . '&customer_token=' . $this->session->data['customer_token']);
        
        $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
        
        $this->response->setOutput($this->load->view('account/pms_shareholder_view', $data));
    }
    
    public function save(): void {
        $this->load->language('pms/shareholder');
        
        $json = [];
        
        // Check permission
        /*$customer_group_id = $this->customer->getGroupId();
        $manager_group_id = (int)$this->config->get('config_pms_manager_group_id');
        
        if ($customer_group_id != $manager_group_id) {
            $json['error'] = $this->language->get('error_permission');
        }*/
        
        // Validation
        if (!$json) {
            if (empty($this->request->post['firstname']) || !oc_validate_length((string)$this->request->post['firstname'], 1, 32)) {
                $json['error']['firstname'] = $this->language->get('error_firstname');
            }
            
            if (empty($this->request->post['lastname']) || !oc_validate_length((string)$this->request->post['lastname'], 1, 32)) {
                $json['error']['lastname'] = $this->language->get('error_lastname');
            }
            
            if (empty($this->request->post['email']) ||  !oc_validate_length((string)$this->request->post['email'], 1, 96) || !filter_var($this->request->post['email'], FILTER_VALIDATE_EMAIL)) {
                $json['error']['email'] = $this->language->get('error_email');
            }
            
            if (empty($this->request->post['ownership_units']) || $this->request->post['ownership_units'] < 1) {
                $json['error']['ownership_units'] = $this->language->get('error_ownership_units');
            }
            
            // Check if email already exists in customer table
            $email_check = $this->db->query("
                SELECT COUNT(*) as total 
                FROM `" . DB_PREFIX . "customer` 
                WHERE email = '" . $this->db->escape($this->request->post['email']) . "'
            ");
            
            if ($email_check->row['total'] > 0 && !isset($this->request->get['id'])) {
                $json['error']['email'] = $this->language->get('error_email_exists');
            }
            
            // Password validation for new shareholder
            if (!isset($this->request->get['id'])) {
                if (empty($this->request->post['password']) || oc_validate_length((string)$this->request->post['password'], 1, 4)) {
                    $json['error']['password'] = $this->language->get('error_password');
                }
            }
        }

        if (!$json) {
            $this->load->model('pms/shareholder');
            
            if (isset($this->request->get['id'])) {
                $this->model_pms_shareholder->updateShareholder($this->request->get['id'], $this->request->post);
                
                // Update password if provided
                if (!empty($this->request->post['password'])) {
                    $shareholder = $this->model_pms_shareholder->getShareholder($this->request->get['id']);
                    $this->db->query("
                        UPDATE `" . DB_PREFIX . "customer` 
                        SET password = '" . $this->db->escape(password_hash($this->request->post['password'], PASSWORD_DEFAULT)) . "'
                        WHERE customer_id = '" . (int)$shareholder['user_id'] . "'
                    ");
                }
                
                $json['success'] = $this->language->get('text_update_success');
            } else {
                $this->model_pms_shareholder->addShareholder($this->request->post);
                $json['success'] = $this->language->get('text_add_success');
            }
            
            $json['redirect'] = $this->url->link('account/pms_shareholders', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    
    public function delete(): void {
        $this->load->language('pms/shareholder');
        
        $json = [];
        
        $customer_group_id = $this->customer->getGroupId();
        $manager_group_id = (int)$this->config->get('config_pms_manager_group_id');
        
        if ($customer_group_id != $manager_group_id) {
            $json['error'] = $this->language->get('error_permission');
        }
        
        if (!$json) {
            $this->load->model('pms/shareholder');
            
            if (isset($this->request->get['id'])) {
                $this->model_pms_shareholder->deleteShareholder($this->request->get['id']);
            }
            
            $json['success'] = $this->language->get('text_delete_success');
            $json['redirect'] = $this->url->link('account/pms_shareholders', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    
    public function statusToggle(): void {
        $this->load->language('pms/shareholder');
        
        $json = [];
        
        $this->load->model('pms/shareholder');
        
        $id = isset($this->request->post['id']) ? (int)$this->request->post['id'] : 0;
        $current_status = $this->request->post['current_status'];
        
        $new_status = ($current_status == 'active') ? 'inactive' : 'active';
        
        $this->model_pms_shareholder->updateShareholderStatus($id, $new_status);
        
        // Also update customer login status
        $new_customer_status = ($new_status == 'active') ? 1 : 0;
        $this->model_pms_shareholder->updateCustomerStatus($id, $new_customer_status);
        
        $json['success'] = true;
        $json['new_status'] = $new_status;
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Payment Allocation page for a specific shareholder
     */
    public function paymentAllocation(): void {
        if (!$this->customer->isLogged()) {
            $this->response->redirect($this->url->link('account/login', 'language=' . $this->config->get('config_language')));
        }
        
        $this->load->language('pms/shareholder');
        $this->load->language('pms/payment_allocation');
        
        $this->document->setTitle($this->language->get('heading_payment_allocation'));
        
        $shareholder_id = isset($this->request->get['shareholder_id']) ? (int)$this->request->get['shareholder_id'] : 0;
        
        $this->load->model('pms/shareholder');
        $this->load->model('pms/project');
        
        $shareholder_info = $this->model_pms_shareholder->getShareholder($shareholder_id);
        
        if (!$shareholder_info) {
            $this->response->redirect($this->url->link('account/pms_shareholders', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']));
        }

        $project_total_shares = $this->customer->getProject()['total_shares'];
        if($project_total_shares <= 0) {
            $project_total_shares = 1;
        }
        
        $data['shareholder'] = [
            'shareholder_id' => $shareholder_info['id'],
            'name' => $shareholder_info['firstname'] . ' ' . $shareholder_info['lastname'],
            'email' => $shareholder_info['email'],
            'shareholder_code' => $shareholder_info['shareholder_code'],
            'ownership_units' => $shareholder_info['ownership_units'],
            'ownership_percentage' => round(($shareholder_info['ownership_units'] / $project_total_shares) * 100, 2),
            'credit_balance' => $shareholder_info['current_credit_balance']
        ];
        
        // Get payment summary
        $data['payment_summary'] = $this->model_pms_shareholder->getPaymentSummary($shareholder_id);
        
        // Get all payment allocations for this shareholder
        $data['allocations'] = $this->model_pms_shareholder->getPaymentAllocations($shareholder_id);

        // Get discounted allocations for this shareholder
        $data['discounted_allocations'] = $this->db->query("
            SELECT pa.*, pp.name as phase_name
            FROM `" . DB_PREFIX . "payment_allocation` pa
            LEFT JOIN `" . DB_PREFIX . "payment_phase` pp ON pa.payment_phase_id = pp.phase_id
            WHERE pa.shareholder_id = '" . (int)$shareholder_info['id'] . "'
            AND pa.is_discounted = 1
            AND pa.is_deleted = 0
            ORDER BY pa.created_at DESC
        ")->rows;

        // Get payment phases for dropdown
        $data['payments_record'] = $this->model_pms_shareholder->getPaymentRecords($shareholder_id);
        
        // Get payment phases for dropdown
        $data['payment_phases'] = $this->model_pms_shareholder->getPaymentPhases();
        
        $data['action'] = $this->url->link('account/pms_shareholders.savePaymentAllocation', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        $data['delete_allocation'] = $this->url->link('account/pms_shareholders.deletePaymentAllocation', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        $data['back'] = $this->url->link('account/pms_shareholders', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        $data['view_link'] = $this->url->link('account/pms_shareholders.view', 'language=' . $this->config->get('config_language') . '&id=' . $shareholder_id . '&customer_token=' . $this->session->data['customer_token']);
        
        $data['customer_token'] = $this->session->data['customer_token'];
        $data['language'] = $this->config->get('config_language');
        
        $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
        
        $this->response->setOutput($this->load->view('account/pms_payment_allocation', $data));
    }

    /**
     * Save payment allocation (AJAX)
     */
    public function savePaymentAllocation(): void {
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
            
            $shareholder_id = (int)$this->request->post['shareholder_id'];
            $project_info = $this->customer->getProject();
            $project_id = $project_info['project_id'];
            $payment_phase_id = (int)$this->request->post['payment_phase_id'];
            $allocated_amount = (float)$this->request->post['allocated_amount'];
            $notes = $this->request->post['notes'];
            
            // Validation
            if ($allocated_amount <= 0) {
                $json['error'] = 'Allocated amount must be greater than 0';
            }
            
            if (!$json) {
                try {
                    $allocation_data = [
                        'shareholder_id' => $shareholder_id,
                        'project_id' => $project_id,
                        'payment_phase_id' => $payment_phase_id,
                        'allocated_amount' => $allocated_amount,
                        'notes' => $notes
                    ];
                    
                    $this->model_pms_shareholder->addPaymentAllocation($allocation_data);
                    
                    $json['success'] = $this->language->get('text_allocation_success');
                    $json['redirect'] = $this->url->link('account/pms_shareholders.paymentAllocation', 'language=' . $this->config->get('config_language') . '&shareholder_id=' . $shareholder_id . '&customer_token=' . $this->session->data['customer_token']);
                    
                } catch (\Exception $e) {
                    $json['error'] = $e->getMessage();
                }
            }
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Delete payment allocation (AJAX)
     */
    public function deletePaymentAllocation(): void {
        $this->load->language('pms/payment_allocation');
        
        $json = [];
        
        $customer_group_id = $this->customer->getGroupId();
        $manager_group_id = (int)$this->config->get('config_pms_manager_group_id');
        
        if ($customer_group_id != $manager_group_id) {
            $json['error'] = $this->language->get('error_permission');
        }
        
        if (!$json) {
            $this->load->model('pms/shareholder');
            
            $allocation_id = isset($this->request->get['allocation_id']) ? (int)$this->request->get['allocation_id'] : 0;
            
            if ($allocation_id) {
                $this->model_pms_shareholder->deletePaymentAllocation($allocation_id);
                $json['success'] = $this->language->get('text_delete_success');
            } else {
                $json['error'] = 'Invalid allocation ID';
            }
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Discount Allocation Form
     */
    public function discountAllocation(): void {
        if (!$this->customer->isLogged()) {
            $this->response->redirect($this->url->link('account/login', 'language=' . $this->config->get('config_language')));
        }
        
        // Check if user is Manager
        $customer_group_id = $this->customer->getGroupId();
        $manager_group_id = (int)$this->config->get('config_pms_manager_group_id');
        
        if ($customer_group_id != $manager_group_id) {
            $this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']));
        }
        
        $this->load->language('pms/discount_allocation');
        
        $this->document->setTitle($this->language->get('heading_discount_allocation'));
        
        // Get current project
        $this->load->model('pms/project');
        $project = $this->model_pms_project->getProjectByCustomerId($this->customer->getId());
        
        if (!$project) {
            $this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']));
        }
        
        $data['project_id'] = $project['project_id'];
        $data['project_name'] = $project['name'];
        
        // Get shareholders
        $this->load->model('pms/shareholder');
        $shareholders = $this->model_pms_shareholder->getShareholders(['project_id' => $project['project_id']]);
        $data['shareholders'] = $shareholders;
        
        // Get payment phases
        $this->load->model('pms/payment');
        $data['payment_phases'] = $this->model_pms_payment->getPaymentPhases();
        
        // Base amount (Land Purchase phase amount)
        // You can set this dynamically or from a setting
        $data['base_amount'] = 1050000; // 10,50,000 BDT
        
        // Get recent discounted allocations
        $data['discounted_allocations'] = $this->model_pms_shareholder->getDiscountedAllocations($project['project_id']);
        
        // Form action
        $data['action'] = $this->url->link('account/pms_shareholders.saveDiscountAllocation', 'language=' . $this->config->get('config_language'));
        $data['back'] = $this->url->link('account/pms_shareholders', 'language=' . $this->config->get('config_language'));
        
        $data['user_token'] = $this->session->data['user_token'];
        
        $data['header'] = $this->load->controller('common/header');
        $data['footer'] = $this->load->controller('common/footer');
        
        $this->response->setOutput($this->load->view('account/pms_discount_allocation', $data));
    }

    /**
     * Save Discount Allocation
     */
    public function saveDiscountAllocation(): void {
        $this->load->language('pms/discount_allocation');
        
        $json = [];
        
        $customer_group_id = $this->customer->getGroupId();
        $manager_group_id = (int)$this->config->get('config_pms_manager_group_id');
        
        if ($customer_group_id != $manager_group_id) {
            $json['error'] = $this->language->get('error_permission');
        }
        
        if (!$json) {
            $this->load->model('pms/shareholder');
            $this->load->model('pms/project');
            
            $project = $this->model_pms_project->getProjectByCustomerId($this->customer->getId());
            
            $allocation_data = [
                'shareholder_id'     => (int)$this->request->post['shareholder_id'],
                'project_id'         => $project['project_id'],
                'payment_phase_id'   => (int)$this->request->post['payment_phase_id'],
                'allocated_amount'   => (float)$this->request->post['original_amount'],
                'discount_type'      => $this->request->post['discount_type'],
                'discount_value'     => (float)$this->request->post['discount_value'],
                'discount_reason'    => $this->request->post['discount_reason'],
                'notes'              => 'Discounted allocation'
            ];
            
            $allocation_id = $this->model_pms_shareholder->addAllocationWithDiscount($allocation_data);
            
            if ($allocation_id) {
                $json['success'] = $this->language->get('text_discount_success');
                $json['redirect'] = $this->url->link('account/pms_payment_allocations', 'language=' . $this->config->get('config_language'));
            } else {
                $json['error'] = 'Failed to create allocation';
            }
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Apply discount to allocation (from paymentAllocation page)
     */
    public function applyDiscountToAllocation(): void {
        $this->load->language('pms/payment_allocation');
        
        $json = [];
        
        // Check permission
        $customer_group_id = $this->customer->getGroupId();
        $manager_group_id = (int)$this->config->get('config_pms_manager_group_id');
        
        /*if ($customer_group_id != $manager_group_id) {
            $json['error'] = $this->language->get('error_permission');
        }*/
        
        if (!$json) {
            $allocation_id = (int)$this->request->post['allocation_id'];
            $discount_type = $this->request->post['discount_type'];
            $discount_value = (float)$this->request->post['discount_value'];
            $discount_reason = $this->request->post['discount_reason'];
            
            // Get current allocation
            $current = $this->db->query("
                SELECT * FROM `" . DB_PREFIX . "payment_allocation`
                WHERE allocation_id = '" . (int)$allocation_id . "'
            ");
            
            if ($current->num_rows > 0) {
                $original_amount = (float)$current->row['allocated_amount'];
                $discount_amount = 0;
                
                if ($discount_type == 'percentage') {
                    $discount_amount = ($original_amount * $discount_value) / 100;
                } else {
                    $discount_amount = $discount_value;
                }
                
                if ($discount_amount > $original_amount) {
                    $discount_amount = $original_amount;
                }
                
                $final_amount = $original_amount - $discount_amount;
                
                // Update allocation with discount
                $this->db->query("
                    UPDATE `" . DB_PREFIX . "payment_allocation` 
                    SET `allocated_amount` = '" . (float)$final_amount . "',
                        `original_amount` = '" . (float)$original_amount . "',
                        `discount_amount` = '" . (float)$discount_amount . "',
                        `discount_type` = '" . $this->db->escape($discount_type) . "',
                        `discount_reason` = '" . $this->db->escape($discount_reason) . "',
                        `is_discounted` = 1
                    WHERE `allocation_id` = '" . (int)$allocation_id . "'
                ");
                
                // Update shareholder credit balance
                $this->db->query("
                    UPDATE `" . DB_PREFIX . "shareholders` 
                    SET `current_credit_balance` = `current_credit_balance` - '" . (float)$discount_amount . "'
                    WHERE `id` = '" . (int)$current->row['shareholder_id'] . "'
                ");
                
                $json['success'] = true;
                $json['message'] = 'Discount applied successfully!';
            } else {
                $json['error'] = 'Allocation not found';
            }
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Delete discount from allocation
     */
    public function deleteDiscountFromAllocation(): void {
        $this->load->language('pms/payment_allocation');
        
        $json = [];
        
        // Check permission
        $customer_group_id = $this->customer->getGroupId();
        $manager_group_id = (int)$this->config->get('config_pms_manager_group_id');
        
        /*if ($customer_group_id != $manager_group_id) {
            $json['error'] = $this->language->get('error_permission');
        }*/
        
        if (!$json) {
            $allocation_id = (int)$this->request->get['allocation_id'];
            
            // Get current allocation
            $current = $this->db->query("
                SELECT * FROM `" . DB_PREFIX . "payment_allocation`
                WHERE allocation_id = '" . (int)$allocation_id . "'
            ");
            
            if ($current->num_rows > 0) {
                $discount_amount = (float)$current->row['discount_amount'];
                $original_amount = (float)$current->row['original_amount'];
                
                // Restore original amount
                $this->db->query("
                    UPDATE `" . DB_PREFIX . "payment_allocation` 
                    SET `allocated_amount` = '" . (float)$original_amount . "',
                        `original_amount` = NULL,
                        `discount_amount` = 0,
                        `discount_type` = NULL,
                        `discount_reason` = NULL,
                        `is_discounted` = 0
                    WHERE `allocation_id` = '" . (int)$allocation_id . "'
                ");
                
                // Restore shareholder credit balance
                $this->db->query("
                    UPDATE `" . DB_PREFIX . "shareholders` 
                    SET `current_credit_balance` = `current_credit_balance` + '" . (float)$discount_amount . "'
                    WHERE `id` = '" . (int)$current->row['shareholder_id'] . "'
                ");
                
                $json['success'] = true;
                $json['message'] = 'Discount removed successfully!';
            } else {
                $json['error'] = 'Allocation not found';
            }
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
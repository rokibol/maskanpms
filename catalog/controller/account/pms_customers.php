<?php
namespace Opencart\Catalog\Controller\Account;

class PmsCustomers extends \Opencart\System\Engine\Controller {

    public function index(): void {
        // Check if logged in
        if (!$this->customer->isLogged()) {
            $this->session->data['redirect'] = $this->url->link('account/pms_customers', 'language=' . $this->config->get('config_language'));
            $this->response->redirect($this->url->link('account/login', 'language=' . $this->config->get('config_language')));
        }
        
        // Check if user is Manager
        /*$customer_group_id = $this->customer->getGroupId();
        $manager_group_id = (int)$this->config->get('config_pms_manager_group_id');
        
        if ($customer_group_id != $manager_group_id) {
            $this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language')));
        }*/
        
        $this->load->language('pms/customers');
        
        $this->document->setTitle($this->language->get('heading_title'));
        
        // Get current project
        $this->load->model('pms/project');
        $project = $this->customer->getProject();
        
        if (!$project) {
            $this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language')));
        }
        
        $data['project_id'] = $project['project_id'];
        $data['project_name'] = $project['name'];
        
        // Set up pagination and filters
        $url = '';
        
        if (isset($this->request->get['filter_search'])) {
            $url .= '&filter_search=' . urlencode(html_entity_decode($this->request->get['filter_search'], ENT_QUOTES, 'UTF-8'));
        }
        
        if (isset($this->request->get['filter_status'])) {
            $url .= '&filter_status=' . $this->request->get['filter_status'];
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
        
        $sort = isset($this->request->get['sort']) ? $this->request->get['sort'] : 'c.firstname';
        $order = isset($this->request->get['order']) ? $this->request->get['order'] : 'ASC';
        $page = isset($this->request->get['page']) ? (int)$this->request->get['page'] : 1;
        
        $limit = $this->config->get('config_pagination_admin');
        
        $this->load->model('account/customer');
        
        // Get customers for this project with customer_group_id = 2 (Accountant)
        $filter_data = [
            'filter_project_id' => $project['project_id'],
            'filter_customer_group_id' => 2,
            'filter_search' => $filter_search,
            'filter_status' => $filter_status,
            'sort' => $sort,
            'order' => $order,
            'start' => ($page - 1) * $limit,
            'limit' => $limit
        ];
        
        $customer_total = $this->model_account_customer->getTotalCustomersByProject($filter_data);
        $results = $this->model_account_customer->getCustomersByProjectFiltered($filter_data);
        
        $data['customers'] = [];
        
        foreach ($results as $result) {
            $status_badge = $result['status'] ? '<span class="badge bg-success">Enabled</span>' : '<span class="badge bg-danger">Disabled</span>';
            
            $data['customers'][] = [
                'customer_id'   => $result['customer_id'],
                'name'          => $result['firstname'] . ' ' . $result['lastname'],
                'email'         => $result['email'],
                'telephone'     => $result['telephone'],
                'status'        => $result['status'],
                'status_badge'  => $status_badge,
                'date_added'    => date($this->language->get('date_format_short'), strtotime($result['date_added'])),
                'edit'          => $this->url->link('account/pms_customers.edit', 'language=' . $this->config->get('config_language') . '&customer_id=' . $result['customer_id'] . $url . '&customer_token=' . $this->session->data['customer_token']),
                'view'          => $this->url->link('account/pms_customers.view', 'language=' . $this->config->get('config_language') . '&customer_id=' . $result['customer_id'] . $url . '&customer_token=' . $this->session->data['customer_token']),
                'delete'        => $this->url->link('account/pms_customers.delete', 'language=' . $this->config->get('config_language') . '&customer_id=' . $result['customer_id'] . $url . '&customer_token=' . $this->session->data['customer_token'])
            ];
        }
        
        // Pagination
        /*$pagination = new \Opencart\System\Library\Pagination();
        $pagination->total = $customer_total;
        $pagination->page = $page;
        $pagination->limit = $limit;
        $pagination->url = $this->url->link('account/pms_customers', 'language=' . $this->config->get('config_language') . $url . '&page={page}');
        
        $data['pagination'] = $pagination->render();
        $data['results'] = sprintf($this->language->get('text_pagination'), ($customer_total) ? (($page - 1) * $limit) + 1 : 0, ((($page - 1) * $limit) > ($customer_total - $limit)) ? $customer_total : ((($page - 1) * $limit) + $limit), $customer_total, ceil($customer_total / $limit));
        */

        $data['pagination'] = $this->load->controller('common/pagination', [
			'total' => $customer_total,
			'page'  => $page,
			'limit' => $this->config->get('config_pagination_admin'),
			'url'   => $this->url->link('account/pms_shareholders.list', 'customer_token=' . $this->session->data['customer_token'] . $url .  '&customer_token=' . $this->session->data['customer_token'] . '&page={page}')
		]);

		$data['results'] = sprintf($this->language->get('text_pagination'), ($customer_total) ? (($page - 1) * $this->config->get('config_pagination_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_pagination_admin')) > ($customer_total - $this->config->get('config_pagination_admin'))) ? $customer_total : ((($page - 1) * $this->config->get('config_pagination_admin')) + $this->config->get('config_pagination_admin')), $customer_total, ceil($customer_total / $this->config->get('config_pagination_admin')));

        // Sort links
        $data['sort_name'] = $this->url->link('account/pms_customers', 'language=' . $this->config->get('config_language') . '&sort=c.firstname' . $url . '&customer_token=' . $this->session->data['customer_token']);
        $data['sort_email'] = $this->url->link('account/pms_customers', 'language=' . $this->config->get('config_language') . '&sort=c.email' . $url . '&customer_token=' . $this->session->data['customer_token']);
        $data['sort_status'] = $this->url->link('account/pms_customers', 'language=' . $this->config->get('config_language') . '&sort=c.status' . $url . '&customer_token=' . $this->session->data['customer_token']);
        $data['sort_date'] = $this->url->link('account/pms_customers', 'language=' . $this->config->get('config_language') . '&sort=c.date_added' . $url . '&customer_token=' . $this->session->data['customer_token']);
        
        // Filter values
        $data['filter_search'] = $filter_search;
        $data['filter_status'] = $filter_status;
        
        // Status options for filter
        $data['statuses'] = [
            ''  => 'All Status',
            '1' => 'Enabled',
            '0' => 'Disabled'
        ];
        
        // Sort and order
        $data['sort'] = $sort;
        $data['order'] = $order;
        
        // URLs
        $data['add'] = $this->url->link('account/pms_customers.add', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        $data['back'] = $this->url->link('account/manager', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        
        // Template parts
        $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
        
        $this->response->setOutput($this->load->view('account/pms_customers_list', $data));
    }
    
    public function add(): void {
        if (!$this->customer->isLogged()) {
            $this->response->redirect($this->url->link('account/login', 'language=' . $this->config->get('config_language')));
        }
        
        $this->load->language('pms/customers');
        
        $this->document->setTitle($this->language->get('heading_add'));
        
        // Get current project
        $this->load->model('pms/project');
        $project = $this->customer->getProject();
        
        if (!$project) {
            $this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language')));
        }
        
        $data['project_id'] = $project['project_id'];
        $data['project_name'] = $project['name'];
        
        // Form action
        $data['action'] = $this->url->link('account/pms_customers.save', 'language=' . $this->config->get('config_language'));
        $data['back'] = $this->url->link('account/pms_customers', 'language=' . $this->config->get('config_language'));
        
        // Set form values
        $fields = ['firstname', 'lastname', 'email', 'telephone', 'password', 'confirm'];
        foreach ($fields as $field) {
            if (isset($this->request->post[$field])) {
                $data[$field] = $this->request->post[$field];
            } else {
                $data[$field] = '';
            }
        }
        
        $data['customer_token'] = $this->session->data['customer_token'];
        
        $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
        
        $this->response->setOutput($this->load->view('account/pms_customer_form', $data));
    }
    
    public function edit(): void {
        if (!$this->customer->isLogged()) {
            $this->response->redirect($this->url->link('account/login', 'language=' . $this->config->get('config_language')));
        }
        
        $this->load->language('pms/customers');
        
        $this->document->setTitle($this->language->get('heading_edit'));
        
        $customer_id = isset($this->request->get['customer_id']) ? (int)$this->request->get['customer_id'] : 0;
        
        $this->load->model('account/customer');
        $customer_info = $this->model_account_customer->getCustomer($customer_id);
        
        if (!$customer_info) {
            $this->response->redirect($this->url->link('account/pms_customers', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']));
        }
        
        $data['customer_id'] = $customer_id;
        
        // Get current project
        $this->load->model('pms/project');
        $project = $this->customer->getProject();
        
        if (!$project) {
            $this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']));
        }
        
        $data['project_id'] = $project['project_id'];
        $data['project_name'] = $project['name'];
        
        // Form action
        $data['action'] = $this->url->link('account/pms_customers.save', 'language=' . $this->config->get('config_language') . '&customer_id=' . $customer_id . '&customer_token=' . $this->session->data['customer_token']);
        $data['back'] = $this->url->link('account/pms_customers', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        
        // Set form values from database
        if (isset($this->request->post['firstname'])) {
            $data['firstname'] = $this->request->post['firstname'];
        } else {
            $data['firstname'] = $customer_info['firstname'];
        }
        
        if (isset($this->request->post['lastname'])) {
            $data['lastname'] = $this->request->post['lastname'];
        } else {
            $data['lastname'] = $customer_info['lastname'];
        }
        
        if (isset($this->request->post['email'])) {
            $data['email'] = $this->request->post['email'];
        } else {
            $data['email'] = $customer_info['email'];
        }
        
        if (isset($this->request->post['telephone'])) {
            $data['telephone'] = $this->request->post['telephone'];
        } else {
            $data['telephone'] = $customer_info['telephone'];
        }
        
        $data['password'] = '';
        $data['confirm'] = '';
        
        $data['customer_token'] = $this->session->data['customer_token'];
        
        $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
        
        $this->response->setOutput($this->load->view('account/pms_customer_form', $data));
    }
    
    public function view(): void {
        if (!$this->customer->isLogged()) {
            $this->response->redirect($this->url->link('account/login', 'language=' . $this->config->get('config_language')));
        }
        
        $this->load->language('pms/customers');
        
        $this->document->setTitle($this->language->get('heading_view'));
        
        $customer_id = isset($this->request->get['customer_id']) ? (int)$this->request->get['customer_id'] : 0;
        
        $this->load->model('account/customer');
        $customer_info = $this->model_account_customer->getCustomer($customer_id);
        
        if (!$customer_info) {
            $this->response->redirect($this->url->link('account/pms_customers', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']));
        }
        
        // Get customer group name
        $this->load->model('account/customer_group');
        $customer_group = $this->model_account_customer_group->getCustomerGroup($customer_info['customer_group_id']);
        
        $data['customer'] = [
            'customer_id'   => $customer_info['customer_id'],
            'name'          => $customer_info['firstname'] . ' ' . $customer_info['lastname'],
            'firstname'     => $customer_info['firstname'],
            'lastname'      => $customer_info['lastname'],
            'email'         => $customer_info['email'],
            'telephone'     => $customer_info['telephone'],
            'customer_group'=> $customer_group ? $customer_group['name'] : '',
            'status'        => $customer_info['status'] ? 'Enabled' : 'Disabled',
            'status_badge'  => $customer_info['status'] ? '<span class="badge bg-success">Enabled</span>' : '<span class="badge bg-danger">Disabled</span>',
            'date_added'    => date($this->language->get('date_format_short'), strtotime($customer_info['date_added'])),
            'ip'            => $customer_info['ip']
        ];
        
        $data['back'] = $this->url->link('account/pms_customers', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        $data['edit'] = $this->url->link('account/pms_customers.edit', 'language=' . $this->config->get('config_language') . '&customer_id=' . $customer_id . '&customer_token=' . $this->session->data['customer_token']);
        
        $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
        
        $this->response->setOutput($this->load->view('account/pms_customer_view', $data));
    }
    
    public function save(): void {
        $this->load->language('pms/customers');
        
        $json = [];
        
        // Check permission
        /*$customer_group_id = $this->customer->getGroupId();
        $manager_group_id = (int)$this->config->get('config_pms_manager_group_id');
        
        if ($customer_group_id != $manager_group_id) {
            $json['error'] = $this->language->get('error_permission');
        }*/
        
        // Validation
        if (!$json) {
            $this->load->model('account/customer');
            
            // Get current project
            $this->load->model('pms/project');
            $project = $this->customer->getProject();
            
            $project_id = $project['project_id'];
            
            if (empty($this->request->post['firstname']) || !oc_validate_length((string)$this->request->post['firstname'], 1, 32)) {
                $json['error']['firstname'] = $this->language->get('error_firstname');
            }
            
            if (empty($this->request->post['lastname']) || !oc_validate_length((string)$this->request->post['lastname'], 1, 32)) {
                $json['error']['lastname'] = $this->language->get('error_lastname');
            }
            
            if (empty($this->request->post['email']) || !oc_validate_length((string)$this->request->post['email'], 1, 32) || !filter_var($this->request->post['email'], FILTER_VALIDATE_EMAIL)) {
                $json['error']['email'] = $this->language->get('error_email');
            }
            
            // Check if email already exists
            $email_check = $this->db->query("
                SELECT COUNT(*) as total 
                FROM `" . DB_PREFIX . "customer` 
                WHERE email = '" . $this->db->escape($this->request->post['email']) . "'
                AND customer_id != '" . (isset($this->request->get['customer_id']) ? (int)$this->request->get['customer_id'] : 0) . "'
            ");
            
            if ($email_check->row['total'] > 0) {
                $json['error']['email'] = $this->language->get('error_email_exists');
            }
            
            // Password validation for new customer
            if (!isset($this->request->get['customer_id'])) {
                if (empty($this->request->post['password']) || oc_validate_length((string)$this->request->post['firstname'], 1, 4)) {
                    $json['error']['password'] = $this->language->get('error_password');
                }
                
                if ($this->request->post['password'] != $this->request->post['confirm']) {
                    $json['error']['confirm'] = $this->language->get('error_confirm');
                }
            } else {
                if (!empty($this->request->post['password'])) {
                    if ( oc_validate_length((string)$this->request->post['firstname'], 1, 4)) {
                        $json['error']['password'] = $this->language->get('error_password');
                    }
                    
                    if ($this->request->post['password'] != $this->request->post['confirm']) {
                        $json['error']['confirm'] = $this->language->get('error_confirm');
                    }
                }
            }
        }
        
        if (!$json) {
            $customer_data = [
                'firstname' => $this->request->post['firstname'],
                'lastname'  => $this->request->post['lastname'],
                'email'     => $this->request->post['email'],
                'telephone' => $this->request->post['telephone'],
                'customer_group_id' => 2, // Accountant group
                'status'    => 1,
                'approved'  => 1,
                'project_id' => $project_id
            ];
            
            if (!empty($this->request->post['password'])) {
                $customer_data['password'] = $this->request->post['password'];
            }
            
            if (isset($this->request->get['customer_id'])) {
                // Update existing customer
                $customer_id = (int)$this->request->get['customer_id'];
                $this->model_account_customer->editCustomer($customer_id, $customer_data);
                $json['success'] = $this->language->get('text_update_success');
            } else {
                // Add new customer
                $customer_id = $this->model_account_customer->addCustomer($customer_data);
                $json['success'] = $this->language->get('text_add_success');
            }
            
            $json['redirect'] = $this->url->link('account/pms_customers', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    
    public function delete(): void {
        $this->load->language('pms/customers');
        
        $json = [];
        
        $customer_group_id = $this->customer->getGroupId();
        $manager_group_id = (int)$this->config->get('config_pms_manager_group_id');
        
        if ($customer_group_id != $manager_group_id) {
            $json['error'] = $this->language->get('error_permission');
        }
        
        if (!$json) {
            $this->load->model('account/customer');
            
            if (isset($this->request->get['customer_id'])) {
                $customer_id = (int)$this->request->get['customer_id'];
                // Soft delete - set status to 0 and remove project assignment
                $this->db->query("
                    UPDATE `" . DB_PREFIX . "customer` 
                    SET `status` = 0,
                        `project_id` = NULL,
                        `is_deleted` = 1
                    WHERE `customer_id` = '" . (int)$customer_id . "'
                ");
                $json['success'] = $this->language->get('text_delete_success');
            } else {
                $json['error'] = 'Invalid customer ID';
            }
            
            $json['redirect'] = $this->url->link('account/pms_customers', 'language=' . $this->config->get('config_language'));
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
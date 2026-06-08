<?php
namespace Opencart\Catalog\Controller\Account;

class PmsSuppliers extends \Opencart\System\Engine\Controller {

    public function index(): void {
        if (!$this->customer->isLogged()) {
            $this->response->redirect($this->url->link('account/login', 'language=' . $this->config->get('config_language')));
        }
        
        $this->load->language('pms/suppliers');
        $this->document->setTitle($this->language->get('heading_title'));
        
        // Get current project
        $this->load->model('pms/project');
        $project = $this->model_pms_project->getProjectByCustomerId($this->customer->getId());
        
        if (!$project) {
            $this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']));
        }
        
        // Filter parameters
        $filter_name = isset($this->request->get['filter_name']) ? $this->request->get['filter_name'] : '';
        $filter_status = isset($this->request->get['filter_status']) ? $this->request->get['filter_status'] : '';
        $sort = isset($this->request->get['sort']) ? $this->request->get['sort'] : 'supplier_name';
        $order = isset($this->request->get['order']) ? $this->request->get['order'] : 'ASC';
        $page = isset($this->request->get['page']) ? (int)$this->request->get['page'] : 1;
        
        $this->load->model('pms/supplier');
        
        $filter_data = [
            'project_id'    => $project['project_id'],
            'filter_name'   => $filter_name,
            'filter_status' => $filter_status,
            'sort'          => $sort,
            'order'         => $order,
            'start'         => ($page - 1) * 20,
            'limit'         => 20
        ];
        
        $supplier_total = $this->model_pms_supplier->getTotalSuppliers($filter_data);
        $suppliers = $this->model_pms_supplier->getSuppliers($filter_data);
        
        $data['suppliers'] = [];
        foreach ($suppliers as $supplier) {
            $data['suppliers'][] = [
                'supplier_id'    => $supplier['supplier_id'],
                'supplier_code'  => $supplier['supplier_code'],
                'supplier_name'  => $supplier['supplier_name'],
                'company_name'   => $supplier['company_name'],
                'contact_person' => $supplier['contact_person'],
                'phone'          => $supplier['phone'],
                'email'          => $supplier['email'],
                'status'         => $supplier['status'],
                'edit'           => $this->url->link('account/pms_suppliers.edit', 'language=' . $this->config->get('config_language') . '&supplier_id=' . $supplier['supplier_id'] . '&customer_token=' . $this->session->data['customer_token']),
                'delete'         => $this->url->link('account/pms_suppliers.delete', 'language=' . $this->config->get('config_language') . '&supplier_id=' . $supplier['supplier_id'] . '&customer_token=' . $this->session->data['customer_token'])
            ];
        }
        
        // Pagination
        $data['pagination'] = $this->load->controller('common/pagination', [
			'total' => $supplier_total,
			'page'  => $page,
			'limit' => $this->config->get('config_pagination_admin'),
			'url'   => $this->url->link('account/pms_suppliers', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token'] . '&page={page}')
		]);

		$data['results'] = sprintf($this->language->get('text_pagination'), ($supplier_total) ? (($page - 1) * $this->config->get('config_pagination_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_pagination_admin')) > ($supplier_total - $this->config->get('config_pagination_admin'))) ? $supplier_total : ((($page - 1) * $this->config->get('config_pagination_admin')) + $this->config->get('config_pagination_admin')), $supplier_total, ceil($supplier_total / $this->config->get('config_pagination_admin')));
        
        $data['language'] = $this->config->get('config_language');
        $data['customer_token'] = $this->session->data['customer_token'];
        
        $data['add'] = $this->url->link('account/pms_suppliers.add', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        $data['back'] = $this->url->link('account/pms_accountant_dashboard', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        
        $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
        
        $this->response->setOutput($this->load->view('account/pms_suppliers_list', $data));
    }
    
    public function add(): void {
        $this->form();
    }
    
    public function edit(): void {
        $this->form();
    }
    
    private function form(): void {
        $this->load->language('pms/suppliers');
        
        if (isset($this->request->get['supplier_id'])) {
            $this->document->setTitle($this->language->get('heading_edit'));
            $data['text_form'] = $this->language->get('heading_edit');
        } else {
            $this->document->setTitle($this->language->get('heading_add'));
            $data['text_form'] = $this->language->get('heading_add');
        }
        
        // Get current project
        $this->load->model('pms/project');
        $project = $this->model_pms_project->getProjectByCustomerId($this->customer->getId());
        
        if (isset($this->request->get['supplier_id'])) {
            $this->load->model('pms/supplier');
            $supplier_info = $this->model_pms_supplier->getSupplier($this->request->get['supplier_id']);
        }
        
        $fields = ['supplier_name', 'company_name', 'contact_person', 'email', 'phone', 'mobile', 
                   'address', 'city', 'postal_code', 'country', 'tin_number', 'bin_number', 
                   'bank_name', 'bank_account_no', 'payment_terms', 'notes', 'status'];
        
        foreach ($fields as $field) {
            if (isset($this->request->post[$field])) {
                $data[$field] = $this->request->post[$field];
            } elseif (isset($supplier_info)) {
                $data[$field] = $supplier_info[$field];
            } else {
                $data[$field] = ($field == 'status') ? 'active' : '';
            }
        }
        
        $data['action'] = $this->url->link('account/pms_suppliers.save', 'language=' . $this->config->get('config_language') . (isset($this->request->get['supplier_id']) ? '&supplier_id=' . $this->request->get['supplier_id'] : ''));
        $data['back'] = $this->url->link('account/pms_suppliers', 'language=' . $this->config->get('config_language'));
        
        $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
        
        $this->response->setOutput($this->load->view('account/pms_supplier_form', $data));
    }
    
    public function save(): void {
        $this->load->language('pms/suppliers');
        
        $json = [];
        
        if (!$this->customer->isLogged()) {
            $json['error'] = $this->language->get('error_permission');
        }
        
        if (!$json) {
            $this->load->model('pms/project');
            $project = $this->model_pms_project->getProjectByCustomerId($this->customer->getId());
            
            $this->load->model('pms/supplier');
            
            $data = $this->request->post;
            $data['project_id'] = $project['project_id'];
            
            if (isset($this->request->get['supplier_id'])) {
                $this->model_pms_supplier->updateSupplier($this->request->get['supplier_id'], $data);
                $json['success'] = $this->language->get('text_update_success');
            } else {
                $this->model_pms_supplier->addSupplier($data);
                $json['success'] = $this->language->get('text_add_success');
            }
            
            $json['redirect'] = $this->url->link('account/pms_suppliers', 'language=' . $this->config->get('config_language'));
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    
    public function delete(): void {
        $this->load->language('pms/suppliers');
        
        $json = [];
        
        if (!$this->customer->isLogged()) {
            $json['error'] = $this->language->get('error_permission');
        }
        
        if (!$json) {
            $this->load->model('pms/supplier');
            
            if (isset($this->request->get['supplier_id'])) {
                $this->model_pms_supplier->deleteSupplier($this->request->get['supplier_id']);
                $json['success'] = $this->language->get('text_delete_success');
            }
            
            $json['redirect'] = $this->url->link('account/pms_suppliers', 'language=' . $this->config->get('config_language'));
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
<?php
namespace Opencart\Catalog\Controller\Account;

class PmsApartments extends \Opencart\System\Engine\Controller {

    public function index(): void {
        // Check if logged in
        if (!$this->customer->isLogged()) {
            $this->session->data['redirect'] = $this->url->link('account/pms_apartments', 'language=' . $this->config->get('config_language'));
            $this->response->redirect($this->url->link('account/login', 'language=' . $this->config->get('config_language')));
        }
        
        // Check if user is Manager
        /*$customer_group_id = $this->customer->getGroupId();
        $manager_group_id = (int)$this->config->get('config_pms_manager_group_id');
        
        if ($customer_group_id != $manager_group_id) {
            $this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language')));
        }*/
        
        $this->load->language('pms/apartments');
        
        $this->document->setTitle($this->language->get('heading_title'));
        
        // Get current project
        $this->load->model('pms/project');
        $project = $this->customer->getProject();
        
        if (!$project) {
            $this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']));
        }
        
        $data['project_id'] = $project['project_id'];
        $data['project_name'] = $project['name'];
        
        // Set up pagination and filters
        $url = '';
        
        $filter_apartment = isset($this->request->get['filter_apartment']) ? $this->request->get['filter_apartment'] : '';
        $filter_floor = isset($this->request->get['filter_floor']) ? $this->request->get['filter_floor'] : '';
        $filter_status = isset($this->request->get['filter_status']) ? $this->request->get['filter_status'] : '';
        $filter_is_active = isset($this->request->get['filter_is_active']) ? $this->request->get['filter_is_active'] : '';
        $filter_bedrooms = isset($this->request->get['filter_bedrooms']) ? $this->request->get['filter_bedrooms'] : '';
        $filter_shareholder = isset($this->request->get['filter_shareholder']) ? $this->request->get['filter_shareholder'] : '';
        
        $sort = isset($this->request->get['sort']) ? $this->request->get['sort'] : 'a.floor_number';
        $order = isset($this->request->get['order']) ? $this->request->get['order'] : 'ASC';
        $page = isset($this->request->get['page']) ? (int)$this->request->get['page'] : 1;
        
        // Build URL for pagination
        if ($filter_apartment) $url .= '&filter_apartment=' . urlencode($filter_apartment);
        if ($filter_floor !== '') $url .= '&filter_floor=' . $filter_floor;
        if ($filter_status) $url .= '&filter_status=' . $filter_status;
        if ($filter_is_active !== '') $url .= '&filter_is_active=' . $filter_is_active;
        if ($filter_bedrooms !== '') $url .= '&filter_bedrooms=' . $filter_bedrooms;
        if ($filter_shareholder) $url .= '&filter_shareholder=' . urlencode($filter_shareholder);
        if ($sort) $url .= '&sort=' . $sort;
        if ($order) $url .= '&order=' . $order;
        
        $limit = $this->config->get('config_pagination_admin');
        
        $filter_data = [
            'project_id'          => $project['project_id'],
            'filter_apartment'    => $filter_apartment,
            'filter_floor'        => $filter_floor,
            'filter_status'       => $filter_status,
            'filter_is_active'    => $filter_is_active,
            'filter_bedrooms'     => $filter_bedrooms,
            'filter_shareholder'  => $filter_shareholder,
            'sort'                => $sort,
            'order'               => $order,
            'start'               => ($page - 1) * $limit,
            'limit'               => $limit
        ];
        
        $this->load->model('pms/apartment');
        $this->load->model('pms/shareholder');
        
        $apartment_total = $this->model_pms_apartment->getTotalApartments($filter_data);
        $results = $this->model_pms_apartment->getApartments($filter_data);
        
        $data['apartments'] = [];
        
        foreach ($results as $result) {
            $status_badge = '';
            if ($result['id'] && $result['shareholder_id'] != 0) {
                $status_badge = '<span class="badge bg-success">Assigned</span>';
            } else {
                $status_badge = '<span class="badge bg-secondary">Available</span>';
            }
            
            $active_badge = $result['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>';
            
            $data['apartments'][] = [
                'apartment_id'          => $result['id'],
                'apartment_number'      => $result['apartment_number'],
                'floor_number'          => $result['floor_number'],
                'shareholder_id'        => $result['id'],
                'shareholder_name'      => $result['shareholder_name'] ?: '-',
                'shareholder_code'      => $result['shareholder_code'] ?: '-',
                'fixed_maintenance_fee' => $result['fixed_maintenance_fee'],
                'area_sqft'             => $result['area_sqft'],
                'bedrooms'              => $result['bedrooms'],
                'bathrooms'             => $result['bathrooms'],
                'balcony'               => $result['balcony'] ? 'Yes' : 'No',
                'parking_slot'          => $result['parking_slot'] ?: '-',
                'is_active'             => $result['is_active'],
                'status_badge'          => $status_badge,
                'active_badge'          => $active_badge,
                'assignment_status'     => $result['assignment_status'],
                'notes'                 => $result['notes'],
                'edit'                  => $this->url->link('account/pms_apartments.edit', 'language=' . $this->config->get('config_language') . '&apartment_id=' . $result['id'] . $url . '&customer_token=' . $this->session->data['customer_token']),
                'view'                  => $this->url->link('account/pms_apartments.view', 'language=' . $this->config->get('config_language') . '&apartment_id=' . $result['id'] . $url . '&customer_token=' . $this->session->data['customer_token']),
                'delete'                => $this->url->link('account/pms_apartments.delete', 'language=' . $this->config->get('config_language') . '&apartment_id=' . $result['id'] . $url . '&customer_token=' . $this->session->data['customer_token']),
                'assign'                => $this->url->link('account/pms_apartments.assign', 'language=' . $this->config->get('config_language') . '&apartment_id=' . $result['id'] . $url . '&customer_token=' . $this->session->data['customer_token'])
            ];
        }
        
        // Pagination
        $data['pagination'] = $this->load->controller('common/pagination', [
			'total' => $apartment_total,
			'page'  => $page,
			'limit' => $this->config->get('config_pagination_admin'),
			'url'   => $this->url->link('account/pms_apartments', 'language=' . $this->config->get('config_language') . $url .  '&customer_token=' . $this->session->data['customer_token'] . '&page={page}')
		]);

		$data['results'] = sprintf($this->language->get('text_pagination'), ($apartment_total) ? (($page - 1) * $this->config->get('config_pagination_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_pagination_admin')) > ($apartment_total - $this->config->get('config_pagination_admin'))) ? $apartment_total : ((($page - 1) * $this->config->get('config_pagination_admin')) + $this->config->get('config_pagination_admin')), $apartment_total, ceil($apartment_total / $this->config->get('config_pagination_admin')));
        
        // Sort links
        $data['sort_apartment'] = $this->url->link('account/pms_apartments', 'language=' . $this->config->get('config_language') . '&sort=a.apartment_number' . $url . '&customer_token=' . $this->session->data['customer_token']);
        $data['sort_floor'] = $this->url->link('account/pms_apartments', 'language=' . $this->config->get('config_language') . '&sort=a.floor_number' . $url . '&customer_token=' . $this->session->data['customer_token']);
        $data['sort_area'] = $this->url->link('account/pms_apartments', 'language=' . $this->config->get('config_language') . '&sort=a.area_sqft' . $url . '&customer_token=' . $this->session->data['customer_token']);
        $data['sort_bedrooms'] = $this->url->link('account/pms_apartments', 'language=' . $this->config->get('config_language') . '&sort=a.bedrooms' . $url . '&customer_token=' . $this->session->data['customer_token']);
        $data['sort_fee'] = $this->url->link('account/pms_apartments', 'language=' . $this->config->get('config_language') . '&sort=a.fixed_maintenance_fee' . $url . '&customer_token=' . $this->session->data['customer_token']);
        $data['sort_status'] = $this->url->link('account/pms_apartments', 'language=' . $this->config->get('config_language') . '&sort=assignment_status' . $url . '&customer_token=' . $this->session->data['customer_token']);
        $data['sort_active'] = $this->url->link('account/pms_apartments', 'language=' . $this->config->get('config_language') . '&sort=a.is_active' . $url . '&customer_token=' . $this->session->data['customer_token']);
        
        // Filter values
        $data['filter_apartment'] = $filter_apartment;
        $data['filter_floor'] = $filter_floor;
        $data['filter_status'] = $filter_status;
        $data['filter_is_active'] = $filter_is_active;
        $data['filter_bedrooms'] = $filter_bedrooms;
        $data['filter_shareholder'] = $filter_shareholder;
        
        // Filter options
        $data['floors'] = $this->model_pms_apartment->getFloors($project['project_id']);
        $data['bedroom_options'] = $this->model_pms_apartment->getBedroomOptions();
        $data['status_options'] = [
            ''          => 'All',
            'assigned'  => 'Assigned',
            'available' => 'Available'
        ];
        $data['active_options'] = [
            ''  => 'All',
            '1' => 'Active',
            '0' => 'Inactive'
        ];
        
        // Get all shareholders for assignment dropdown
        $this->load->model('pms/shareholder');
        $shareholders = $this->model_pms_shareholder->getShareholders(['project_id' => $project['project_id']]);
        $data['shareholders'] = $shareholders;
        
        // Get statistics
        $data['stats'] = $this->model_pms_apartment->getApartmentStats($project['project_id']);
        
        // Sort and order
        $data['sort'] = $sort;
        $data['order'] = $order;

        $data['language'] = $this->config->get('config_language');
        $data['customer_token'] =  $this->session->data['customer_token'];
        
        // URLs
        $data['add'] = $this->url->link('account/pms_apartments.add', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        $data['back'] = $this->url->link('account/manager', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        
        // Template parts
        $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
        
        $this->response->setOutput($this->load->view('account/pms_apartments_list', $data));
    }
    
    public function add(): void {
        $this->form();
    }
    
    public function edit(): void {
        $this->form();
    }
    
    private function form(): void {
        if (!$this->customer->isLogged()) {
            $this->response->redirect($this->url->link('account/login', 'language=' . $this->config->get('config_language')));
        }
        
        $this->load->language('pms/apartments');
        
        $this->document->setTitle($this->language->get('heading_form'));
        
        // Get current project
        $this->load->model('pms/project');
        $project = $this->customer->getProject();
        
        if (!$project) {
            $this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']));
        }
        
        $data['project_id'] = $project['project_id'];
        $data['project_name'] = $project['name'];

        $this->load->model('pms/apartment');
        $this->load->model('pms/shareholder');
        
        if (isset($this->request->get['apartment_id'])) {
            $apartment_id = (int)$this->request->get['apartment_id'];
            $apartment_info = $this->model_pms_apartment->getApartment($apartment_id);

            if (!$apartment_info) {
                $this->response->redirect($this->url->link('account/pms_apartments', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']));
            }
            
            $data['apartment_id'] = $apartment_id;
            $data['shareholder_id'] = $apartment_info['shareholder_id'];
            $data['text_form'] = $this->language->get('text_edit');
        } else {
            $data['apartment_id'] = 0;
            $data['text_form'] = $this->language->get('text_add');
        }
        
        // Form action
        $url = $this->buildUrl();
        $data['action'] = $this->url->link('account/pms_apartments.save', 'language=' . $this->config->get('config_language') . $url . '&customer_token=' . $this->session->data['customer_token']);
        $data['back'] = $this->url->link('account/pms_apartments', 'language=' . $this->config->get('config_language') . $url . '&customer_token=' . $this->session->data['customer_token']);
        
        // Set form values
        $fields = ['apartment_number', 'floor_number', 'shareholder_id', 'fixed_maintenance_fee', 
                   'is_active', 'area_sqft', 'bedrooms', 'bathrooms', 'balcony', 'parking_slot', 'notes'];
        
        foreach ($fields as $field) {
            if (isset($this->request->post[$field])) {
                $data[$field] = $this->request->post[$field];
            } elseif (isset($apartment_info)) {
                $data[$field] = $apartment_info[$field];
            } else {
                $data[$field] = ($field == 'is_active') ? 1 : '';
            }
        }

        $data['fixed_maintenance_fee'] = $data['fixed_maintenance_fee'] ? $data['fixed_maintenance_fee'] : (int)$project['fixed_maintenance_fee'];
        $data['area_sqft'] = $data['area_sqft'] ? $data['area_sqft'] : (int)$project['area_sq_ft'];
        
        // Get all shareholders for dropdown
        $shareholders = $this->model_pms_shareholder->getUnallocatedApartmentShareholders(['project_id' => $project['project_id']]);

        $data['shareholders'] = $shareholders;
        
        $data['customer_token'] = $this->session->data['customer_token'];
        
        $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
        
        $this->response->setOutput($this->load->view('account/pms_apartment_form', $data));
    }
    
    public function view(): void {
        if (!$this->customer->isLogged()) {
            $this->response->redirect($this->url->link('account/login', 'language=' . $this->config->get('config_language')));
        }
        
        $this->load->language('pms/apartments');
        
        $this->document->setTitle($this->language->get('heading_view'));
        
        $apartment_id = isset($this->request->get['apartment_id']) ? (int)$this->request->get['apartment_id'] : 0;
        
        $this->load->model('pms/apartment');
        $apartment_info = $this->model_pms_apartment->getApartment($apartment_id);
        
        if (!$apartment_info) {
            $this->response->redirect($this->url->link('account/pms_apartments', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']));
        }
        
        $data['apartment'] = [
            'apartment_id'          => $apartment_info['id'],
            'apartment_number'      => $apartment_info['apartment_number'],
            'floor_number'          => $apartment_info['floor_number'],
            'shareholder_name'      => $apartment_info['shareholder_name'] ?: 'Not Assigned',
            'shareholder_code'      => $apartment_info['shareholder_code'] ?: '-',
            'fixed_maintenance_fee' => number_format($apartment_info['fixed_maintenance_fee'], 2),
            'area_sqft'             => $apartment_info['area_sqft'],
            'bedrooms'              => $apartment_info['bedrooms'],
            'bathrooms'             => $apartment_info['bathrooms'],
            'balcony'               => $apartment_info['balcony'] ? 'Yes' : 'No',
            'parking_slot'          => $apartment_info['parking_slot'] ?: 'Not Assigned',
            'is_active'             => $apartment_info['is_active'] ? 'Active' : 'Inactive',
            'is_active_badge'       => $apartment_info['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>',
            'notes'                 => nl2br($apartment_info['notes']) ?: '-',
            'created_at'            => date($this->language->get('date_format_short'), strtotime($apartment_info['created_at'])),
            'updated_at'            => $apartment_info['updated_at'] ? date($this->language->get('date_format_short'), strtotime($apartment_info['updated_at'])) : '-'
        ];
        
        $url = $this->buildUrl();
        $data['back'] = $this->url->link('account/pms_apartments', 'language=' . $this->config->get('config_language') . $url . '&customer_token=' . $this->session->data['customer_token']);
        $data['edit'] = $this->url->link('account/pms_apartments.edit', 'language=' . $this->config->get('config_language') . '&apartment_id=' . $apartment_id . $url . '&customer_token=' . $this->session->data['customer_token']);
        
        $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
        
        $this->response->setOutput($this->load->view('account/pms_apartment_view', $data));
    }
    
    public function save(): void {
        $this->load->language('pms/apartments');
        
        $json = [];
        
        // Check permission
        /*$customer_group_id = $this->customer->getGroupId();
        $manager_group_id = (int)$this->config->get('config_pms_manager_group_id');
        
        if ($customer_group_id != $manager_group_id) {
            $json['error'] = $this->language->get('error_permission');
        }*/
        
        // Validation
        if (!$json) {
            if (empty($this->request->post['apartment_number'])) {
                $json['error']['apartment_number'] = $this->language->get('error_apartment_number');
            }
            
            if ($this->request->post['floor_number'] === '' || $this->request->post['floor_number'] < 0) {
                $json['error']['floor_number'] = $this->language->get('error_floor_number');
            }
            
            if ($this->request->post['fixed_maintenance_fee'] <= 0) {
                $json['error']['fixed_maintenance_fee'] = $this->language->get('error_fixed_maintenance_fee');
            }
            
            if ($this->request->post['area_sqft'] <= 0) {
                $json['error']['area_sqft'] = $this->language->get('error_area_sqft');
            }
        }
        
        if (!$json) {
            $this->load->model('pms/apartment');
            $this->load->model('pms/project');
            
            $project = $this->customer->getProject();
            $this->request->post['project_id'] = $project['project_id'];
            
            if (isset($this->request->post['apartment_id']) && (int)$this->request->post['apartment_id'] > 0) {
                $this->model_pms_apartment->updateApartment($this->request->post['apartment_id'], $this->request->post);
                $json['success'] = $this->language->get('text_update_success');
            } else {
                $this->model_pms_apartment->addApartment($this->request->post);
                $json['success'] = $this->language->get('text_add_success');
            }
            
            $url = $this->buildUrl();

            $json['redirect'] = $this->url->link('account/pms_apartments', 'language=' . $this->config->get('config_language') . $url . '&customer_token=' . $this->session->data['customer_token']);
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    
    public function delete(): void {
        $this->load->language('pms/apartments');
        
        $json = [];
        
        /*$customer_group_id = $this->customer->getGroupId();
        $manager_group_id = (int)$this->config->get('config_pms_manager_group_id');
        
        if ($customer_group_id != $manager_group_id) {
            $json['error'] = $this->language->get('error_permission');
        }*/
        
        if (!$json) {
            $this->load->model('pms/apartment');
            
            if (isset($this->request->get['apartment_id'])) {
                $this->model_pms_apartment->deleteApartment($this->request->get['apartment_id']);
                $json['success'] = $this->language->get('text_delete_success');
            } else {
                $json['error'] = 'Invalid apartment ID';
            }
            
            $url = $this->buildUrl();
            $json['redirect'] = $this->url->link('account/pms_apartments', 'language=' . $this->config->get('config_language') . $url . '&customer_token=' . $this->session->data['customer_token']);
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    
    public function assign(): void {
        $this->load->language('pms/apartments');
        
        $json = [];
        
        /*$customer_group_id = $this->customer->getGroupId();
        $manager_group_id = (int)$this->config->get('config_pms_manager_group_id');
        
        if ($customer_group_id != $manager_group_id) {
            $json['error'] = $this->language->get('error_permission');
        }*/
        
        if (!$json) {
            $this->load->model('pms/apartment');
            
            $apartment_id = isset($this->request->post['apartment_id']) ? (int)$this->request->post['apartment_id'] : 0;
            $shareholder_id = isset($this->request->post['shareholder_id']) ? (int)$this->request->post['shareholder_id'] : 0;

            if ($apartment_id && $shareholder_id) {
                $this->model_pms_apartment->assignShareholder($apartment_id, $shareholder_id);
                $json['success'] = $this->language->get('text_assign_success');
            } elseif ($apartment_id && $shareholder_id == 0) {
                $this->model_pms_apartment->unassignShareholder($apartment_id);
                $json['success'] = $this->language->get('text_unassign_success');
            } else {
                $json['error'] = 'Invalid request';
            }
            
            $url = $this->buildUrl();
            $json['redirect'] = $this->url->link('account/pms_apartments', 'language=' . $this->config->get('config_language') . $url . '&customer_token=' . $this->session->data['customer_token']);
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    
    private function buildUrl(): string {
        $url = '';
        
        if (isset($this->request->get['filter_apartment'])) {
            $url .= '&filter_apartment=' . urlencode($this->request->get['filter_apartment']);
        }
        if (isset($this->request->get['filter_floor'])) {
            $url .= '&filter_floor=' . $this->request->get['filter_floor'];
        }
        if (isset($this->request->get['filter_status'])) {
            $url .= '&filter_status=' . $this->request->get['filter_status'];
        }
        if (isset($this->request->get['filter_is_active'])) {
            $url .= '&filter_is_active=' . $this->request->get['filter_is_active'];
        }
        if (isset($this->request->get['filter_bedrooms'])) {
            $url .= '&filter_bedrooms=' . $this->request->get['filter_bedrooms'];
        }
        if (isset($this->request->get['filter_shareholder'])) {
            $url .= '&filter_shareholder=' . urlencode($this->request->get['filter_shareholder']);
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
}
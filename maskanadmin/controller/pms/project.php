<?php
namespace Opencart\Admin\Controller\Pms;

class Project extends \Opencart\System\Engine\Controller {

    public function index(): void {
        $this->load->language('pms/project');

        $this->document->setTitle($this->language->get('heading_title'));

        $url = '';

        if (isset($this->request->get['filter_name'])) {
            $url .= '&filter_name=' . urlencode(html_entity_decode($this->request->get['filter_name'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['filter_status'])) {
            $url .= '&filter_status=' . $this->request->get['filter_status'];
        }

        if (isset($this->request->get['filter_is_active'])) {
            $url .= '&filter_is_active=' . $this->request->get['filter_is_active'];
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

        $data['breadcrumbs'] = [];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('pms/project', 'user_token=' . $this->session->data['user_token'] . $url)
        ];

        $data['add'] = $this->url->link('pms/project.form', 'user_token=' . $this->session->data['user_token'] . $url);
        $data['delete'] = $this->url->link('pms/project.delete', 'user_token=' . $this->session->data['user_token']);

        // Filter form action
        $data['action'] = $this->url->link('pms/project', 'user_token=' . $this->session->data['user_token']);

        // Get filter values from request
        $filter_name = '';
        if (isset($this->request->get['filter_name'])) {
            $filter_name = $this->request->get['filter_name'];
        }

        $filter_status = '';
        if (isset($this->request->get['filter_status'])) {
            $filter_status = $this->request->get['filter_status'];
        }

        $filter_is_active = '';
        if (isset($this->request->get['filter_is_active'])) {
            $filter_is_active = $this->request->get['filter_is_active'];
        }

        // Load models
        $this->load->model('pms/project');
        $this->load->model('setting/store');

        // Get pagination and sorting parameters
        if (isset($this->request->get['sort'])) {
            $sort = $this->request->get['sort'];
        } else {
            $sort = 'name';
        }

        if (isset($this->request->get['order'])) {
            $order = $this->request->get['order'];
        } else {
            $order = 'ASC';
        }

        if (isset($this->request->get['page'])) {
            $page = (int)$this->request->get['page'];
        } else {
            $page = 1;
        }

        // Build filter data for model
        $filter_data = [
            'filter_name'        => $filter_name,
            'filter_status'      => $filter_status,
            'filter_is_active'   => $filter_is_active,
            'sort'               => $sort,
            'order'              => $order,
            'start'              => ($page - 1) * $this->config->get('config_pagination_admin'),
            'limit'              => $this->config->get('config_pagination_admin')
        ];

        // Get total projects count
        $project_total = $this->model_pms_project->getTotalProjects($filter_data);

        // Get projects
        $results = $this->model_pms_project->getProjects($filter_data);

        // Build projects array
        $data['projects'] = [];
        
        foreach ($results as $result) {
            $store_info = $this->model_setting_store->getStore($result['store_id']);

            $status_text = '';
            switch ($result['status']) {
                case 'planning':
                    $status_text = $this->language->get('text_planning');
                    break;
                case 'land_acquisition':
                    $status_text = $this->language->get('text_land_acquisition');
                    break;
                case 'construction':
                    $status_text = $this->language->get('text_construction');
                    break;
                case 'operational':
                    $status_text = $this->language->get('text_operational');
                    break;
                case 'completed':
                    $status_text = $this->language->get('text_completed');
                    break;
                case 'suspended':
                    $status_text = $this->language->get('text_suspended');
                    break;
                default:
                    $status_text = $result['status'];
            }

            $data['projects'][] = [
                'project_id'  => $result['project_id'],
                'name'        => $result['name'],
                'status'      => $status_text,
                'status_raw'  => $result['status'],
                'store_name'  => $store_info ? $store_info['name'] : $this->language->get('text_default'),
                'is_active'   => $result['is_active'],
                'date_added'  => date($this->language->get('date_format_short'), strtotime($result['created_at'])),
                'edit'        => $this->url->link('pms/project.form', 'user_token=' . $this->session->data['user_token'] . '&project_id=' . $result['project_id'] . $url),
                'view'        => $this->url->link('pms/project.view', 'user_token=' . $this->session->data['user_token'] . '&project_id=' . $result['project_id'] . $url),
                'delete'      => $this->url->link('pms/project.delete', 'user_token=' . $this->session->data['user_token'] . '&project_id=' . $result['project_id'] . $url)
            ];
        }

        // Build pagination URL
        $pagination_url = '';

        if (isset($this->request->get['filter_name'])) {
            $pagination_url .= '&filter_name=' . urlencode(html_entity_decode($this->request->get['filter_name'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['filter_status'])) {
            $pagination_url .= '&filter_status=' . $this->request->get['filter_status'];
        }

        if (isset($this->request->get['filter_is_active'])) {
            $pagination_url .= '&filter_is_active=' . $this->request->get['filter_is_active'];
        }

        if (isset($this->request->get['sort'])) {
            $pagination_url .= '&sort=' . $this->request->get['sort'];
        }

        if (isset($this->request->get['order'])) {
            $pagination_url .= '&order=' . $this->request->get['order'];
        }

        $data['pagination'] = $this->load->controller('common/pagination', [
			'total' => $project_total,
			'page'  => $page,
			'limit' => $this->config->get('config_pagination_admin'),
			'url'   => $this->url->link('pms/project', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}')
		]);

		$data['results'] = sprintf($this->language->get('text_pagination'), ($project_total) ? (($page - 1) * $this->config->get('config_pagination_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_pagination_admin')) > ($project_total - $this->config->get('config_pagination_admin'))) ? $project_total : ((($page - 1) * $this->config->get('config_pagination_admin')) + $this->config->get('config_pagination_admin')), $project_total, ceil($project_total / $this->config->get('config_pagination_admin')));
        // Sort links
        $data['sort_name'] = $this->url->link('pms/project', 'user_token=' . $this->session->data['user_token'] . '&sort=name' . $url);
        $data['sort_status'] = $this->url->link('pms/project', 'user_token=' . $this->session->data['user_token'] . '&sort=status' . $url);
        $data['sort_store'] = $this->url->link('pms/project', 'user_token=' . $this->session->data['user_token'] . '&sort=store_id' . $url);
        $data['sort_is_active'] = $this->url->link('pms/project', 'user_token=' . $this->session->data['user_token'] . '&sort=is_active' . $url);
        $data['sort_date_added'] = $this->url->link('pms/project', 'user_token=' . $this->session->data['user_token'] . '&sort=created_at' . $url);

        // Set filter values for the form
        $data['filter_name'] = $filter_name;
        $data['filter_status'] = $filter_status;
        $data['filter_is_active'] = $filter_is_active;

        // Status options for filter
        $data['statuses'] = [
            ''                 => 'All Status',
            'planning'         => $this->language->get('text_planning'),
            'land_acquisition' => $this->language->get('text_land_acquisition'),
            'construction'     => $this->language->get('text_construction'),
            'operational'      => $this->language->get('text_operational'),
            'completed'        => $this->language->get('text_completed'),
            'suspended'        => $this->language->get('text_suspended')
        ];

        // Active options for filter
        $data['active_options'] = [
            ''  => 'All',
            '1' => $this->language->get('text_enabled'),
            '0' => $this->language->get('text_disabled')
        ];

        $data['user_token'] = $this->session->data['user_token'];

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('pms/project_list', $data));
    }

    public function form(): void {
        $this->load->language('pms/project');

        $this->document->setTitle($this->language->get('heading_title'));

        $data['text_form'] = !isset($this->request->get['project_id']) ? $this->language->get('text_add') : $this->language->get('text_edit');

        $url = '';

        if (isset($this->request->get['filter_name'])) {
            $url .= '&filter_name=' . urlencode(html_entity_decode($this->request->get['filter_name'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['filter_status'])) {
            $url .= '&filter_status=' . $this->request->get['filter_status'];
        }

        if (isset($this->request->get['filter_is_active'])) {
            $url .= '&filter_is_active=' . $this->request->get['filter_is_active'];
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

        $data['breadcrumbs'] = [];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('pms/project', 'user_token=' . $this->session->data['user_token'] . $url)
        ];

        $data['action'] = $this->url->link('pms/project.save', 'user_token=' . $this->session->data['user_token'] . $url);

        $data['back'] = $this->url->link('pms/project', 'user_token=' . $this->session->data['user_token'] . $url);

        if (isset($this->request->get['project_id'])) {
            $this->load->model('pms/project');
            $project_info = $this->model_pms_project->getProject($this->request->get['project_id']);
            $data['project_id'] = $this->request->get['project_id'];
        } else {
            $data['project_id'] = '';
        }

        // Basic Information
        if (isset($this->request->post['name'])) {
            $data['name'] = $this->request->post['name'];
        } elseif (!empty($project_info)) {
            $data['name'] = $project_info['name'];
        } else {
            $data['name'] = '';
        }

        if (isset($this->request->post['description'])) {
            $data['description'] = $this->request->post['description'];
        } elseif (!empty($project_info)) {
            $data['description'] = $project_info['description'];
        } else {
            $data['description'] = '';
        }

        // Address Information
        if (isset($this->request->post['address'])) {
            $data['address'] = $this->request->post['address'];
        } elseif (!empty($project_info)) {
            $data['address'] = $project_info['address'];
        } else {
            $data['address'] = '';
        }

        if (isset($this->request->post['city'])) {
            $data['city'] = $this->request->post['city'];
        } elseif (!empty($project_info)) {
            $data['city'] = $project_info['city'];
        } else {
            $data['city'] = '';
        }

        if (isset($this->request->post['postal_code'])) {
            $data['postal_code'] = $this->request->post['postal_code'];
        } elseif (!empty($project_info)) {
            $data['postal_code'] = $project_info['postal_code'];
        } else {
            $data['postal_code'] = '';
        }

        if (isset($this->request->post['country'])) {
            $data['country'] = $this->request->post['country'];
        } elseif (!empty($project_info)) {
            $data['country'] = $project_info['country'];
        } else {
            $data['country'] = 'Bangladesh';
        }

        // Contact Information
        if (isset($this->request->post['contact_email'])) {
            $data['contact_email'] = $this->request->post['contact_email'];
        } elseif (!empty($project_info)) {
            $data['contact_email'] = $project_info['contact_email'];
        } else {
            $data['contact_email'] = '';
        }

        if (isset($this->request->post['contact_phone'])) {
            $data['contact_phone'] = $this->request->post['contact_phone'];
        } elseif (!empty($project_info)) {
            $data['contact_phone'] = $project_info['contact_phone'];
        } else {
            $data['contact_phone'] = '';
        }

        // Theme & Store
        if (isset($this->request->post['theme_color'])) {
            $data['theme_color'] = $this->request->post['theme_color'];
        } elseif (!empty($project_info)) {
            $data['theme_color'] = $project_info['theme_color'];
        } else {
            $data['theme_color'] = '#0B3C5D';
        }

        $this->load->model('setting/store');
        $data['stores'] = $this->model_setting_store->getStores();

        if (isset($this->request->post['store_id'])) {
            $data['store_id'] = $this->request->post['store_id'];
        } elseif (!empty($project_info)) {
            $data['store_id'] = $project_info['store_id'];
        } else {
            $data['store_id'] = 0;
        }

        // Status & Progress
        $data['statuses'] = [
            'planning'         => $this->language->get('text_planning'),
            'land_acquisition' => $this->language->get('text_land_acquisition'),
            'construction'     => $this->language->get('text_construction'),
            'operational'      => $this->language->get('text_operational'),
            'completed'        => $this->language->get('text_completed'),
            'suspended'        => $this->language->get('text_suspended')
        ];

        if (isset($this->request->post['status'])) {
            $data['status'] = $this->request->post['status'];
        } elseif (!empty($project_info)) {
            $data['status'] = $project_info['status'];
        } else {
            $data['status'] = 'planning';
        }

        if (isset($this->request->post['completion_percentage'])) {
            $data['completion_percentage'] = $this->request->post['completion_percentage'];
        } elseif (!empty($project_info)) {
            $data['completion_percentage'] = $project_info['completion_percentage'];
        } else {
            $data['completion_percentage'] = 0;
        }

        if (isset($this->request->post['is_active'])) {
            $data['is_active'] = $this->request->post['is_active'];
        } elseif (!empty($project_info)) {
            $data['is_active'] = $project_info['is_active'];
        } else {
            $data['is_active'] = 1;
        }

        // Share Information
        if (isset($this->request->post['total_shares'])) {
            $data['total_shares'] = $this->request->post['total_shares'];
        } elseif (!empty($project_info)) {
            $data['total_shares'] = $project_info['total_shares'];
        } else {
            $data['total_shares'] = 10;
        }

        if (isset($this->request->post['share_value'])) {
            $data['share_value'] = $this->request->post['share_value'];
        } elseif (!empty($project_info)) {
            $data['share_value'] = $project_info['share_value'];
        } else {
            $data['share_value'] = '';
        }

        // Dates
        if (isset($this->request->post['registration_date'])) {
            $data['registration_date'] = $this->request->post['registration_date'];
        } elseif (!empty($project_info) && $project_info['registration_date'] != '0000-00-00') {
            $data['registration_date'] = date('Y-m-d', strtotime($project_info['registration_date']));
        } else {
            $data['registration_date'] = '';
        }

        if (isset($this->request->post['construction_start_date'])) {
            $data['construction_start_date'] = $this->request->post['construction_start_date'];
        } elseif (!empty($project_info) && $project_info['construction_start_date'] != '0000-00-00') {
            $data['construction_start_date'] = date('Y-m-d', strtotime($project_info['construction_start_date']));
        } else {
            $data['construction_start_date'] = '';
        }

        if (isset($this->request->post['construction_end_date'])) {
            $data['construction_end_date'] = $this->request->post['construction_end_date'];
        } elseif (!empty($project_info) && $project_info['construction_end_date'] != '0000-00-00') {
            $data['construction_end_date'] = date('Y-m-d', strtotime($project_info['construction_end_date']));
        } else {
            $data['construction_end_date'] = '';
        }

        if (isset($this->request->post['handover_date'])) {
            $data['handover_date'] = $this->request->post['handover_date'];
        } elseif (!empty($project_info) && $project_info['handover_date'] != '0000-00-00') {
            $data['handover_date'] = date('Y-m-d', strtotime($project_info['handover_date']));
        } else {
            $data['handover_date'] = '';
        }

        $data['user_token'] = $this->session->data['user_token'];

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('pms/project_form', $data));
    }

    public function save(): void {
        $this->load->language('pms/project');

        $json = [];

        if (!$this->user->hasPermission('modify', 'pms/project')) {
            $json['error'] = $this->language->get('error_permission');
        }

        if (!$json) {
            $this->load->model('pms/project');

            if (empty($this->request->post['name']) || !oc_validate_length((string)$this->request->post['name'], 1, 255)) {
                $json['error']['name'] = $this->language->get('error_name');
            }

            if (empty($this->request->post['total_shares']) || $this->request->post['total_shares'] < 1) {
                $json['error']['total_shares'] = $this->language->get('error_total_shares');
            }

            if (!empty($this->request->post['share_value']) && $this->request->post['share_value'] < 0) {
                $json['error']['share_value'] = $this->language->get('error_share_value');
            }

            if ($this->request->post['completion_percentage'] < 0 || $this->request->post['completion_percentage'] > 100) {
                $json['error']['completion_percentage'] = $this->language->get('error_completion');
            }
        }

        if (!$json) {
            
            if ($this->request->post['project_id'] != '') {
                
                $this->model_pms_project->editProject($this->request->post['project_id'], $this->request->post);
            } else {
                
                $this->model_pms_project->addProject($this->request->post);
            }
            

            $json['success'] = $this->language->get('text_success');
            $json['redirect'] = $this->url->link('pms/project', 'user_token=' . $this->session->data['user_token'], true);
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function delete(): void {
        $this->load->language('pms/project');

        $json = [];

        if (!$this->user->hasPermission('modify', 'pms/project')) {
            $json['error'] = $this->language->get('error_permission');
        }

        if (!$json) {
            $this->load->model('pms/project');

            if (isset($this->request->get['project_id'])) {
                $this->model_pms_project->deleteProject($this->request->get['project_id']);
            } else {
                if (isset($this->request->post['selected'])) {
                    foreach ($this->request->post['selected'] as $project_id) {
                        $this->model_pms_project->deleteProject($project_id);
                    }
                }
            }

            $json['success'] = $this->language->get('text_success');
            $json['redirect'] = $this->url->link('pms/project', 'user_token=' . $this->session->data['user_token'], true);
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * View project details (read-only mode)
     */
    public function view(): void {
        $this->load->language('pms/project');

        $this->document->setTitle($this->language->get('heading_title'));

        $url = '';

        if (isset($this->request->get['filter_name'])) {
            $url .= '&filter_name=' . urlencode(html_entity_decode($this->request->get['filter_name'], ENT_QUOTES, 'UTF-8'));
        }

        if (isset($this->request->get['filter_status'])) {
            $url .= '&filter_status=' . $this->request->get['filter_status'];
        }

        if (isset($this->request->get['filter_is_active'])) {
            $url .= '&filter_is_active=' . $this->request->get['filter_is_active'];
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

        $data['breadcrumbs'] = [];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('pms/project', 'user_token=' . $this->session->data['user_token'] . $url)
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_view'),
            'href' => $this->url->link('pms/project.view', 'user_token=' . $this->session->data['user_token'] . '&project_id=' . $this->request->get['project_id'] . $url)
        ];

        if (isset($this->request->get['project_id'])) {
            $this->load->model('pms/project');
            $this->load->model('setting/store');
            $this->load->model('customer/customer');
            
            $project_id = (int)$this->request->get['project_id'];
            $project_info = $this->model_pms_project->getProject($project_id);
            
            if (!$project_info) {
                $this->response->redirect($this->url->link('pms/project', 'user_token=' . $this->session->data['user_token'] . $url));
            }
            
            $data['project_id'] = $project_id;
            
            // Basic Information
            $data['name'] = $project_info['name'];
            $data['description'] = nl2br($project_info['description']);
            $data['theme_color'] = $project_info['theme_color'];
            
            // Store Information
            $store_info = $this->model_setting_store->getStore($project_info['store_id']);
            $data['store_name'] = $store_info ? $store_info['name'] : $this->language->get('text_default');
            
            // Status Information
            $status_text = '';
            switch ($project_info['status']) {
                case 'planning':
                    $status_text = $this->language->get('text_planning');
                    break;
                case 'land_acquisition':
                    $status_text = $this->language->get('text_land_acquisition');
                    break;
                case 'construction':
                    $status_text = $this->language->get('text_construction');
                    break;
                case 'operational':
                    $status_text = $this->language->get('text_operational');
                    break;
                case 'completed':
                    $status_text = $this->language->get('text_completed');
                    break;
                case 'suspended':
                    $status_text = $this->language->get('text_suspended');
                    break;
                default:
                    $status_text = $project_info['status'];
            }
            $data['status'] = $status_text;
            $data['completion_percentage'] = $project_info['completion_percentage'];
            $data['is_active'] = $project_info['is_active'] ? $this->language->get('text_enabled') : $this->language->get('text_disabled');
            
            // Contact Information
            $data['contact_email'] = $project_info['contact_email'] ?: '-';
            $data['contact_phone'] = $project_info['contact_phone'] ?: '-';
            
            // Address Information
            $data['address'] = nl2br($project_info['address']) ?: '-';
            $data['city'] = $project_info['city'] ?: '-';
            $data['postal_code'] = $project_info['postal_code'] ?: '-';
            $data['country'] = $project_info['country'] ?: '-';
            
            // Share Information
            $data['total_shares'] = $project_info['total_shares'];
            $data['share_value'] = $project_info['share_value'] ? number_format($project_info['share_value'], 2) : '-';
            
            // Dates
            $data['registration_date'] = $project_info['registration_date'] && $project_info['registration_date'] != '0000-00-00' 
                ? date($this->language->get('date_format_short'), strtotime($project_info['registration_date'])) : '-';
            $data['construction_start_date'] = $project_info['construction_start_date'] && $project_info['construction_start_date'] != '0000-00-00' 
                ? date($this->language->get('date_format_short'), strtotime($project_info['construction_start_date'])) : '-';
            $data['construction_end_date'] = $project_info['construction_end_date'] && $project_info['construction_end_date'] != '0000-00-00' 
                ? date($this->language->get('date_format_short'), strtotime($project_info['construction_end_date'])) : '-';
            $data['handover_date'] = $project_info['handover_date'] && $project_info['handover_date'] != '0000-00-00' 
                ? date($this->language->get('date_format_short'), strtotime($project_info['handover_date'])) : '-';
            
            // Created At
            $data['created_at'] = date($this->language->get('date_format_short') . ' H:i', strtotime($project_info['created_at']));
            
            // Get Project Statistics
            $data['stats'] = $this->getProjectStatistics($project_id);
            
            // Get Project Users (Shareholders, Managers, Accountants)
            $data['project_users'] = $this->getProjectUsers($project_id);
            
            // URLs
            $data['edit'] = $this->url->link('pms/project.form', 'user_token=' . $this->session->data['user_token'] . '&project_id=' . $project_id . $url);
            $data['back'] = $this->url->link('pms/project', 'user_token=' . $this->session->data['user_token'] . $url);
            
        } else {
            $this->response->redirect($this->url->link('pms/project', 'user_token=' . $this->session->data['user_token'] . $url));
        }

        $data['user_token'] = $this->session->data['user_token'];

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('pms/project_view', $data));
    }

    /**
     * Get project statistics for view page
     */
    private function getProjectStatistics(int $project_id): array {
        $stats = [];
        
        // Count shareholders
        $query = $this->db->query("
            SELECT COUNT(*) as total
            FROM `" . DB_PREFIX . "shareholders`
            WHERE project_id = '" . (int)$project_id . "'
            AND is_deleted = 0
        ");
        $stats['total_shareholders'] = (int)$query->row['total'];
        
        // Count apartments
        $query = $this->db->query("
            SELECT COUNT(*) as total,
                SUM(CASE WHEN shareholder_id IS NOT NULL THEN 1 ELSE 0 END) as assigned
            FROM `" . DB_PREFIX . "pms_apartment`
            WHERE project_id = '" . (int)$project_id . "'
            AND is_deleted = 0
        ");
        $stats['total_apartments'] = (int)$query->row['total'];
        $stats['assigned_apartments'] = (int)$query->row['assigned'];
        $stats['available_apartments'] = $stats['total_apartments'] - $stats['assigned_apartments'];
        
        // Count expenses
        $query = $this->db->query("
            SELECT COUNT(*) as total,
                COALESCE(SUM(total_amount), 0) as total_amount
            FROM `" . DB_PREFIX . "expenses`
            WHERE project_id = '" . (int)$project_id . "'
            AND is_deleted = 0
        ");
        $stats['total_expenses'] = (int)$query->row['total'];
        $stats['total_expense_amount'] = (float)$query->row['total_amount'];
        
        // Count payments
        $query = $this->db->query("
            SELECT COUNT(*) as total,
                COALESCE(SUM(amount), 0) as total_amount
            FROM `" . DB_PREFIX . "pms_payment`
            WHERE project_id = '" . (int)$project_id . "'
            AND is_deleted = 0
        ");
        $stats['total_payments'] = (int)$query->row['total'];
        $stats['total_payment_amount'] = (float)$query->row['total_amount'];
        
        // Outstanding dues
        $stats['outstanding_dues'] = $stats['total_expense_amount'] - $stats['total_payment_amount'];
        
        // Collection percentage
        $stats['collection_percentage'] = $stats['total_expense_amount'] > 0 
            ? round(($stats['total_payment_amount'] / $stats['total_expense_amount']) * 100, 2) 
            : 0;
        
        return $stats;
    }

    /**
     * Get project users for view page
     */
    private function getProjectUsers(int $project_id): array {
        $users = [
            'shareholders' => [],
            'managers' => [],
            'accountants' => []
        ];
        
        // Get Shareholders
        $query = $this->db->query("
            SELECT s.*, 
                c.firstname, c.lastname, c.email, c.telephone,
                CONCAT(c.firstname, ' ', c.lastname) as fullname
            FROM `" . DB_PREFIX . "shareholders` s
            INNER JOIN `" . DB_PREFIX . "customer` c ON s.user_id = c.customer_id
            WHERE s.project_id = '" . (int)$project_id . "'
            AND c.customer_group_id = 3
            AND s.is_deleted = 0
            ORDER BY c.firstname ASC
        ");
        $users['shareholders'] = $query->rows;
        
        // Get Managers (customer_group_id = 2 assumption)
        $query = $this->db->query("
            SELECT c.customer_id, c.firstname, c.lastname, c.email, c.telephone,
                CONCAT(c.firstname, ' ', c.lastname) as fullname
            FROM `" . DB_PREFIX . "customer` c
            WHERE c.project_id = '" . (int)$project_id . "'
            AND c.customer_group_id = 1
            AND c.is_deleted = 0
            ORDER BY c.firstname ASC
        ");
        $users['managers'] = $query->rows;
        
        // Get Accountants (customer_group_id = 3 assumption)
        $query = $this->db->query("
            SELECT c.customer_id, c.firstname, c.lastname, c.email, c.telephone,
                CONCAT(c.firstname, ' ', c.lastname) as fullname
            FROM `" . DB_PREFIX . "customer` c
            WHERE c.project_id = '" . (int)$project_id . "'
            AND c.customer_group_id = 2
            AND c.is_deleted = 0
            ORDER BY c.firstname ASC
        ");
        $users['accountants'] = $query->rows;
        
        return $users;
    }
}
<?php
namespace Opencart\Admin\Controller\Pms;

class SubscriptionPlan extends \Opencart\System\Engine\Controller {

    public function index(): void {
        $this->load->language('pms/subscription_plan');
        
        $this->document->setTitle($this->language->get('heading_title'));
        
        $url = '';
        
        if (isset($this->request->get['filter_name'])) {
            $url .= '&filter_name=' . urlencode(html_entity_decode($this->request->get['filter_name'], ENT_QUOTES, 'UTF-8'));
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

        if (isset($this->request->get['page'])) {
            $page = (int)$this->request->get['page'];
        } else {
            $page = 1;
        }
        
        $data['breadcrumbs'] = [];
        
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
        ];
        
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('pms/subscription_plan', 'user_token=' . $this->session->data['user_token'] . $url)
        ];
        
        $data['add'] = $this->url->link('pms/subscription_plan.form', 'user_token=' . $this->session->data['user_token'] . $url);
        $data['delete'] = $this->url->link('pms/subscription_plan.delete', 'user_token=' . $this->session->data['user_token']);
        
        // Get the list data directly
        $this->load->model('pms/subscription_plan');
        
        // Set filter data
        $filter_data = [
            'filter_name'      => isset($this->request->get['filter_name']) ? $this->request->get['filter_name'] : '',
            'filter_is_active' => isset($this->request->get['filter_is_active']) ? $this->request->get['filter_is_active'] : '',
            'sort'             => isset($this->request->get['sort']) ? $this->request->get['sort'] : 'sort_order',
            'order'            => isset($this->request->get['order']) ? $this->request->get['order'] : 'ASC',
            'start'            => (isset($this->request->get['page']) ? (int)$this->request->get['page'] : 1) - 1 * $this->config->get('config_pagination_admin'),
            'limit'            => $this->config->get('config_pagination_admin')
        ];
        
        $plan_total = $this->model_pms_subscription_plan->getTotalPlans($filter_data);
        $results = $this->model_pms_subscription_plan->getPlans($filter_data);
        
        $data['plans'] = [];
        
        foreach ($results as $result) {
            $data['plans'][] = [
                'plan_id'        => $result['plan_id'],
                'name'           => $result['name'],
                'price_monthly'  => $result['price_monthly'],
                'price_yearly'   => $result['price_yearly'],
                'max_projects'   => $result['max_projects'],
                'max_shareholders'=> $result['max_shareholders'],
                'max_apartments' => $result['max_apartments'],
                'is_active'      => $result['is_active'],
                'sort_order'     => $result['sort_order'],
                'edit'           => $this->url->link('pms/subscription_plan.form', 'user_token=' . $this->session->data['user_token'] . '&plan_id=' . $result['plan_id'] . $url),
                'delete'         => $this->url->link('pms/subscription_plan.delete', 'user_token=' . $this->session->data['user_token'] . '&plan_id=' . $result['plan_id'] . $url)
            ];
        }
        
        // Pagination
        /*$pagination = new \Opencart\System\Library\Pagination();
        $pagination->total = $plan_total;
        $pagination->page = isset($this->request->get['page']) ? (int)$this->request->get['page'] : 1;
        $pagination->limit = $this->config->get('config_pagination_admin');
        $pagination->url = $this->url->link('pms/subscription_plan', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}');
        
        $data['pagination'] = $pagination->render();
        $data['results'] = sprintf($this->language->get('text_pagination'), ($plan_total) ? (($pagination->page - 1) * $this->config->get('config_pagination_admin')) + 1 : 0, ((($pagination->page - 1) * $this->config->get('config_pagination_admin')) > ($plan_total - $this->config->get('config_pagination_admin'))) ? $plan_total : ((($pagination->page - 1) * $this->config->get('config_pagination_admin')) + $this->config->get('config_pagination_admin')), $plan_total, ceil($plan_total / $this->config->get('config_pagination_admin')));
        */
        
        $data['pagination'] = $this->load->controller('common/pagination', [
			'total' => $plan_total,
			'page'  => $page,
			'limit' => $this->config->get('config_pagination_admin'),
			'url'   => $this->url->link('pms/project', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}')
		]);

		$data['results'] = sprintf($this->language->get('text_pagination'), ($plan_total) ? (($page - 1) * $this->config->get('config_pagination_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_pagination_admin')) > ($plan_total - $this->config->get('config_pagination_admin'))) ? $plan_total : ((($page - 1) * $this->config->get('config_pagination_admin')) + $this->config->get('config_pagination_admin')), $plan_total, ceil($plan_total / $this->config->get('config_pagination_admin')));
        
        $data['user_token'] = $this->session->data['user_token'];
        
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        
        $this->response->setOutput($this->load->view('pms/subscription_plan_list', $data));
    }
    
    public function form(): void {
        $this->load->language('pms/subscription_plan');
        
        $this->document->setTitle($this->language->get('heading_title'));
        
        $data['text_form'] = !isset($this->request->get['plan_id']) ? $this->language->get('text_add') : $this->language->get('text_edit');
        
        $url = '';
        
        if (isset($this->request->get['filter_name'])) {
            $url .= '&filter_name=' . urlencode(html_entity_decode($this->request->get['filter_name'], ENT_QUOTES, 'UTF-8'));
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
            'href' => $this->url->link('pms/subscription_plan', 'user_token=' . $this->session->data['user_token'] . $url)
        ];
        
        $data['action'] = $this->url->link('pms/subscription_plan.save', 'user_token=' . $this->session->data['user_token'] . $url);
        
        $data['back'] = $this->url->link('pms/subscription_plan', 'user_token=' . $this->session->data['user_token'] . $url);
        
        if (isset($this->request->get['plan_id'])) {
            $this->load->model('pms/subscription_plan');
            $plan_info = $this->model_pms_subscription_plan->getPlan($this->request->get['plan_id']);
        }
        
        $fields = ['name', 'slug', 'description', 'price_monthly', 'price_yearly', 
                   'max_projects', 'max_shareholders', 'max_apartments', 'features', 
                   'is_active', 'sort_order'];
        
        foreach ($fields as $field) {
            if (isset($this->request->post[$field])) {
                $data[$field] = $this->request->post[$field];
            } elseif (isset($plan_info)) {
                $data[$field] = $plan_info[$field];
            } else {
                $data[$field] = ($field == 'is_active') ? 1 : '';
            }
        }
        
        $data['user_token'] = $this->session->data['user_token'];
        
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        
        $this->response->setOutput($this->load->view('pms/subscription_plan_form', $data));
    }
    
    public function save(): void {
        $this->load->language('pms/subscription_plan');
        
        $json = [];
        
        if (!$this->user->hasPermission('modify', 'pms/subscription_plan')) {
            $json['error'] = $this->language->get('error_permission');
        }
        
        if (!$json) {
            $this->load->model('pms/subscription_plan');
            
            if (isset($this->request->get['plan_id'])) {
                $this->model_pms_subscription_plan->updatePlan($this->request->get['plan_id'], $this->request->post);
                $json['success'] = $this->language->get('text_update_success');
            } else {
                $this->model_pms_subscription_plan->addPlan($this->request->post);
                $json['success'] = $this->language->get('text_add_success');
            }
            
            $json['redirect'] = $this->url->link('pms/subscription_plan', 'user_token=' . $this->session->data['user_token'], true);
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    
    public function delete(): void {
        $this->load->language('pms/subscription_plan');
        
        $json = [];
        
        if (!$this->user->hasPermission('modify', 'pms/subscription_plan')) {
            $json['error'] = $this->language->get('error_permission');
        }
        
        if (!$json) {
            $this->load->model('pms/subscription_plan');
            
            if (isset($this->request->get['plan_id'])) {
                $this->model_pms_subscription_plan->deletePlan($this->request->get['plan_id']);
                $json['success'] = $this->language->get('text_delete_success');
            } else {
                if (isset($this->request->post['selected'])) {
                    foreach ($this->request->post['selected'] as $plan_id) {
                        $this->model_pms_subscription_plan->deletePlan($plan_id);
                    }
                    $json['success'] = $this->language->get('text_delete_success');
                } else {
                    $json['error'] = 'No plans selected';
                }
            }
            
            $json['redirect'] = $this->url->link('pms/subscription_plan', 'user_token=' . $this->session->data['user_token'], true);
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
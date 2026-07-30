<?php
namespace Opencart\Admin\Controller\Pms;

class SubscriptionSettings extends \Opencart\System\Engine\Controller {

    public function index(): void {
        $this->load->language('pms/subscription_settings');
        $this->document->setTitle($this->language->get('heading_title'));
        
        $data['breadcrumbs'] = [];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
        ];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('pms/subscription_settings', 'user_token=' . $this->session->data['user_token'])
        ];

        if (isset($this->request->get['page'])) {
            $page = (int)$this->request->get['page'];
        } else {
            $page = 1;
        }

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
        
        $this->load->model('pms/subscription_settings');

        $data['discount_type'] = $this->model_pms_subscription_settings->getSetting('discount_type');
        $data['discount_value'] = $this->model_pms_subscription_settings->getSetting('discount_value');
        $data['discount_min_apartments'] = $this->model_pms_subscription_settings->getSetting('discount_min_apartments');
        $data['apartment_rate'] = $this->model_pms_subscription_settings->getSetting('apartment_rate');

        
        // Handle form submission
        if ($this->request->server['REQUEST_METHOD'] == 'POST') {
            $this->model_pms_subscription_settings->updateSetting('monthly_rate', $this->request->post['monthly_rate']);
            $this->model_pms_subscription_settings->updateSetting('apartment_rate', $this->request->post['apartment_rate']);
            $this->model_pms_subscription_settings->updateSetting('discount_type', $this->request->post['discount_type']);
            $this->model_pms_subscription_settings->updateSetting('discount_value', $this->request->post['discount_value']);
            $this->model_pms_subscription_settings->updateSetting('discount_min_apartments', $this->request->post['discount_min_apartments']);
            $this->session->data['success'] = $this->language->get('text_settings_updated');
            $this->response->redirect($this->url->link('pms/subscription_settings', 'user_token=' . $this->session->data['user_token']));
        }
        
        // Get current settings
        $data['monthly_rate'] = $this->model_pms_subscription_settings->getSetting('monthly_rate');
        
        $data['user_token'] = $this->session->data['user_token'];
        
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        
        $this->response->setOutput($this->load->view('pms/subscription_settings', $data));
    }
    
    public function edit(): void {
        $this->load->language('pms/subscription_settings');
        $this->document->setTitle($this->language->get('heading_edit'));
        
        $subscription_id = (int)$this->request->get['subscription_id'];
        
        $this->load->model('pms/subscription_settings');
        $subscription = $this->model_pms_subscription_settings->getSubscription($subscription_id);
        
        if (!$subscription) {
            $this->response->redirect($this->url->link('pms/subscription_settings', 'user_token=' . $this->session->data['user_token']));
        }
        
        $data['breadcrumbs'] = [];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
        ];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('pms/subscription_settings', 'user_token=' . $this->session->data['user_token'])
        ];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_edit'),
            'href' => $this->url->link('pms/subscription_settings.edit', 'user_token=' . $this->session->data['user_token'] . '&subscription_id=' . $subscription_id)
        ];
        
        $data['subscription'] = $subscription;
        $data['action'] = $this->url->link('pms/subscription_settings.update', 'user_token=' . $this->session->data['user_token'] . '&subscription_id=' . $subscription_id, true);
        $data['back'] = $this->url->link('pms/subscription_settings', 'user_token=' . $this->session->data['user_token']);
        
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        
        $this->response->setOutput($this->load->view('pms/subscription_edit', $data));
    }
    
    public function update(): void {
        $this->load->language('pms/subscription_settings');
        
        $json = [];
        
        if (!$this->user->hasPermission('modify', 'pms/subscription_settings')) {
            $json['error'] = $this->language->get('error_permission');
        }
        
        if (!$json) {
            $this->load->model('pms/subscription_settings');
            
            $subscription_id = (int)$this->request->get['subscription_id'];
            $action = $this->request->post['action'];
            
            if ($action == 'add_override') {
                $months = (int)$this->request->post['override_months'];
                $reason = $this->request->post['override_reason'];
                $this->model_pms_subscription_settings->addAdminOverride($subscription_id, $months, $reason);
                $json['success'] = $this->language->get('text_override_added');
            } elseif ($action == 'remove_override') {
                $this->model_pms_subscription_settings->removeAdminOverride($subscription_id);
                $json['success'] = $this->language->get('text_override_removed');
            }
            
            $json['redirect'] = $this->url->link('pms/subscription_settings', 'user_token=' . $this->session->data['user_token'], true);
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
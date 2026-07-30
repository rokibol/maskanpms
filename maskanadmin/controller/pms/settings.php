<?php
namespace Opencart\Admin\Controller\Pms;

class Settings extends \Opencart\System\Engine\Controller {

    public function index(): void {
        $this->load->language('pms/settings');
        
        $this->document->setTitle($this->language->get('heading_title'));
        
        $data['breadcrumbs'] = [];
        
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
        ];
        
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('pms/settings', 'user_token=' . $this->session->data['user_token'])
        ];
        
        $this->load->model('pms/settings');
        $this->load->model('customer/customer_group');
        
        // Get all customer groups for dropdown
        $data['customer_groups'] = $this->model_customer_customer_group->getCustomerGroups();
        
        // Get current settings
        $settings = $this->model_pms_settings->getSettings();
        
        $data['manager_group_id'] = $settings['manager_group_id'] ?? '';
        $data['accountant_group_id'] = $settings['accountant_group_id'] ?? '';
        $data['shareholder_group_id'] = $settings['shareholder_group_id'] ?? '';
        
        // Handle form submission
        if ($this->request->server['REQUEST_METHOD'] == 'POST') {
            $this->model_pms_settings->updateSettings($this->request->post);
            
            $this->session->data['success'] = $this->language->get('text_settings_updated');
            $this->response->redirect($this->url->link('pms/settings', 'user_token=' . $this->session->data['user_token']));
        }
        
        $data['action'] = $this->url->link('pms/settings', 'user_token=' . $this->session->data['user_token']);
        $data['user_token'] = $this->session->data['user_token'];
        
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        
        $this->response->setOutput($this->load->view('pms/settings', $data));
    }
}
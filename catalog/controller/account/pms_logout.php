<?php
namespace Opencart\Catalog\Controller\Account;

class PmsLogout extends \Opencart\System\Engine\Controller {
    
    public function index(): void {
        $this->load->language('account/logout');
        
        $this->document->setTitle($this->language->get('heading_title'));
        
        $data['breadcrumbs'] = [];
        
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home', 'language=' . $this->config->get('config_language'))
        ];
        
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_account'),
            'href' => $this->url->link('account/account', 'language=' . $this->config->get('config_language'))
        ];
        
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_logout'),
            'href' => $this->url->link('account/logout', 'language=' . $this->config->get('config_language'))
        ];
        
        $data['text_logout'] = $this->language->get('text_logout');
        $data['text_account_logout'] = $this->language->get('text_account_logout');
        
        $data['login'] = $this->url->link('account/login', 'language=' . $this->config->get('config_language'));
        $data['home'] = $this->url->link('common/home', 'language=' . $this->config->get('config_language'));
        
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');
        
        $this->response->setOutput($this->load->view('account/pms_logout', $data));
    }
}
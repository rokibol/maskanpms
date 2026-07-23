<?php
namespace Opencart\Catalog\Controller\Common;

class PmsTopMenu extends \Opencart\System\Engine\Controller {

    public function index(): string {
        $data = [];

        $data['home'] = $this->url->link('common/home', 'language=' . $this->config->get('config_language'));
        $data['features'] = $this->url->link('information/pms_features', 'language=' . $this->config->get('config_language'));
        $data['faq'] = $this->url->link('information/pms_faq', 'language=' . $this->config->get('config_language'));
        $data['contact'] = $this->url->link('information/pms_contact', 'language=' . $this->config->get('config_language'));
        $data['login'] = $this->url->link('account/login', 'language=' . $this->config->get('config_language'));
        $data['register'] = $this->url->link('account/register', 'language=' . $this->config->get('config_language'));

        //$this->response->setOutput($this->load->view('common/pms_top_menu', $data));
        return $this->load->view('common/pms_top_menu', $data);
    }
}
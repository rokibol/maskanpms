<?php
namespace Opencart\Catalog\Controller\Account;
/**
 * Class Account
 *
 * @package Opencart\Catalog\Controller\Account
 */
class Account extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		if ($this->load->controller('account/login.validate')) {
			if($this->customer->getGroupId() == 1) { //Manager
				$this->response->redirect($this->url->link('account/manager', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : '')));
			} else if($this->customer->getGroupId() == 2) {  //Accountant
				$this->response->redirect($this->url->link('account/accountant', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : '')));
			} else if($this->customer->getGroupId() == 3) {  //Shareholders
				$this->response->redirect($this->url->link('account/shareholder', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : '')));
			}
		} else {
			$this->response->redirect($this->url->link('account/login', 'language=' . $this->config->get('config_language'), true));
		}
	}
}

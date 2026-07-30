<?php
namespace Opencart\Catalog\Controller\Common;
/**
 * Class Search
 *
 * Can be called from $this->load->controller('common/search');
 *
 * @package Opencart\Catalog\Controller\Common
 */
class Search extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return string
	 */
	public function index(): string {
		/*if ($this->load->controller('account/login.validate')) {
			if($this->customer->getGroupId() == 1) { //Manager
				$this->response->redirect($this->url->link('account/manager', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : '')));
			} else if($this->customer->getGroupId() == 2) {  //Accountant
				$this->response->redirect($this->url->link('account/accountant', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : '')));
			} else if($this->customer->getGroupId() == 3) {  //Shareholders
				$this->response->redirect($this->url->link('account/shareholder', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : '')));
			}
		} else {
			$this->response->redirect($this->url->link('account/login', 'language=' . $this->config->get('config_language'), true));
		}*/

		/*$this->load->language('common/search');

		$data['text_search'] = $this->language->get('text_search');

		$data['action'] = $this->url->link('common/search.redirect', 'language=' . $this->config->get('config_language'));

		if (isset($this->request->get['search'])) {
			$data['search'] = $this->request->get['search'];
		} else {
			$data['search'] = '';
		}

		return $this->load->view('common/search', $data);*/
		return '';
	}

	/**
	 * Redirect
	 */
	/*public function redirect(): void {
		if (isset($this->request->post['search'])) {
			$search = urlencode(html_entity_decode($this->request->post['search'], ENT_QUOTES, 'UTF-8'));
		} else {
			$search = '';
		}

		$this->response->redirect($this->url->link('product/search', 'language=' . $this->config->get('config_language') . '&search=' . $search, true));
	}*/
}

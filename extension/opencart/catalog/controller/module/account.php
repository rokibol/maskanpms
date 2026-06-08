<?php
namespace Opencart\Catalog\Controller\Extension\Opencart\Module;
/**
 * Class Account
 *
 * @package Opencart\Catalog\Controller\Extension\Opencart\Module
 */
class Account extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return string
	 */
	public function index(): string {
		$this->load->language('extension/opencart/module/account');

		// Source - https://stackoverflow.com/a/33877000
		// Posted by miralong
		// Retrieved 2026-05-22, License - CC BY-SA 3.0

		$base_url = ( isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']=='on' ? 'https' : 'http' ) . '://' .  $_SERVER['HTTP_HOST'];
		$url = $base_url . $_SERVER["REQUEST_URI"];

		$route = $_GET['route'] ?? '';

		$parts = explode('/', $route);

		$param = end($parts);

		$data['logged'] = $this->customer->isLogged();
		$data['project'] = $this->customer->getProject();
		$data['customer_name'] = $this->customer->getFullName();
		$data['group'] = $this->customer->getGroup();
		$data['register'] = $this->url->link('account/register', 'language=' . $this->config->get('config_language'));

		$data['customer_group_id'] = $this->customer->getGroupId();

		if($this->customer->getGroupId() == 1) { //Manager
			
			if($param == 'manager') {
				$data['active_dashboard'] = 'active';
			} else if($param == 'pms_shareholders') {
				$data['active_shareholders'] = 'active';
			} else if($param == 'pms_customers') {
				$data['active_customers'] = 'active';
			} else if($param == 'pms_apartments') {
				$data['active_apartments'] = 'active';
			} else if($param == 'pms_expenses') {
				$data['active_expenses'] = 'active';
			} else if($param == 'pms_expense_invoices') {
				$data['active_expense_invoices'] = 'active';
			} else if($param == 'pms_share_transfer') {
				$data['active_transfer'] = 'active';
			} else if($param == 'pms_payment_allocations') {
				$data['active_payment_allocations'] = 'active';
			} else if($param == 'pms_project_details') {
				$data['active_project_details'] = 'active';
			} else if($param == 'pms_project_details') {
				$data['pms_subscription'] = 'active';
			}

			$data['pms_shareholders'] = $this->url->link('account/pms_shareholders', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : ''));
			$data['pms_payment_allocations'] = $this->url->link('account/pms_payment_allocations', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : ''));
			$data['pms_dashboard'] = $this->url->link('account/manager', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : ''));
			$data['pms_customers'] = $this->url->link('account/pms_customers', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : ''));
			$data['pms_apartments'] = $this->url->link('account/pms_apartments', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : ''));
			$data['pms_expenses'] = $this->url->link('account/pms_expenses', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : ''));
			$data['pms_expense_invoices'] = $this->url->link('account/pms_expense_invoices', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : ''));
			$data['pms_share_transfer'] = $this->url->link('account/pms_share_transfer', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : ''));

			$data['pms_project_details'] = $this->url->link('account/pms_project_details', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : ''));
			
			$data['pms_subscription'] = $this->url->link('account/pms_subscription', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : ''));

		} else if($this->customer->getGroupId() == 2) {  //Accountant
			if($param == 'accountant') {
				$data['active_dashboard'] = 'active';
			} else if($param == 'pms_payments') {
				$data['active_payments'] = 'active';
			} else if($param == 'pms_expenses') {
				$data['active_expenses'] = 'active';
			} else if($param == 'pms_bills') {
				$data['active_bills'] = 'active';
			} else if($param == 'pms_bills.unpaid') {
				$data['active_unpaid_bills'] = 'active';
			} else if($param == 'pms_suppliers') {
				$data['active_suppliers'] = 'active';
			} else if($param == 'pms_expense_invoices') {
				$data['active_expense_invoices'] = 'active';
			}

			$data['pms_expenses'] = $this->url->link('account/pms_expenses', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : ''));
			$data['pms_dashboard'] = $this->url->link('account/accountant', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : ''));
			$data['pms_payments'] = $this->url->link('account/pms_payments', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : ''));
			$data['pms_bills'] = $this->url->link('account/pms_bills', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : ''));

			$data['pms_suppliers'] = $this->url->link('account/pms_suppliers', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : ''));
			$data['pms_expense_invoices'] = $this->url->link('account/pms_expense_invoices', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : ''));

			$this->load->model('pms/bill');
			$data['unpaid_bills_count'] = $this->model_pms_bill->getTotalUnpaidBillsCount($this->customer->getProject()['project_id']);
			$data['pms_bills_unpaid'] = $this->url->link('account/pms_bills.unpaid', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : ''));
		} else if($this->customer->getGroupId() == 3) {  //Shareholders
			if($param == 'shareholder') {
				$data['active_dashboard'] = 'active';
			} else if($param == 'pms_my_bills') {
				$data['active_my_bills'] = 'active';
			} else if($param == 'pms_my_bills.paymentHistory') {
				$data['active_my_paymenthistory'] = 'active';
			} else if($param == 'pms_my_payment_allocations') {
				$data['active_my_payment_allocations'] = 'active';
			} else if($param == 'pms_my_apartments') {
				$data['active_my_apartments'] = 'active';
			}

			$data['pms_dashboard'] = $this->url->link('account/shareholder', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : ''));
			$this->load->model('pms/bill');
			$data['unpaid_bills_count'] = $this->model_pms_bill->getTotalUnpaidBillsCountForShareholder($this->customer->getId());
			$data['pms_my_bills'] = $this->url->link('account/pms_my_bills', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : ''));
			$data['pms_my_payment_history'] = $this->url->link('account/pms_my_bills.paymentHistory', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : ''));
			$data['pms_my_payment_allocations'] = $this->url->link('account/pms_my_payment_allocations', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : ''));
			$data['pms_my_apartments'] = $this->url->link('account/pms_my_apartments', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : ''));

		}

		if($param == 'edit') {
			$data['active_edit'] = 'active';
		} else if($param == 'password') {
			$data['active_password'] = 'active';
		}
		
		$data['login'] = $this->url->link('account/login', 'language=' . $this->config->get('config_language'));
		$data['logout'] = $this->url->link('account/logout', 'language=' . $this->config->get('config_language'));
		$data['forgotten'] = $this->url->link('account/forgotten', 'language=' . $this->config->get('config_language'));
		
		$data['account'] = $this->url->link('account/shareholder', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : ''));
		$data['edit'] = $this->url->link('account/edit', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : ''));
		$data['password'] = $this->url->link('account/password', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : ''));
		$data['address'] = $this->url->link('account/address', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : ''));
		$data['wishlist'] = $this->url->link('account/wishlist', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : ''));
		$data['order'] = $this->url->link('account/order', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : ''));
		$data['download'] = $this->url->link('account/download', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : ''));
		$data['reward'] = $this->url->link('account/reward', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : ''));
		$data['return'] = $this->url->link('account/returns', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : ''));
		$data['transaction'] = $this->url->link('account/transaction', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : ''));
		$data['newsletter'] = $this->url->link('account/newsletter', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : ''));
		$data['subscription'] = $this->url->link('account/subscription', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : ''));
		$data['payment_method'] = $this->url->link('account/payment_method', 'language=' . $this->config->get('config_language') . (isset($this->session->data['customer_token']) ? '&customer_token=' . $this->session->data['customer_token'] : ''));

		return $this->load->view('extension/opencart/module/account', $data);
	}
}

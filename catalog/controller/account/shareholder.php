<?php
namespace Opencart\Catalog\Controller\Account;
/**
 * Class Account
 *
 * @package Opencart\Catalog\Controller\Account
 */
class Shareholder extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('account/account');

		if (!$this->load->controller('account/login.validate')) {
			$this->session->data['redirect'] = $this->url->link('account/shareholder', 'language=' . $this->config->get('config_language'));

			$this->response->redirect($this->url->link('account/login', 'language=' . $this->config->get('config_language'), true));
		}

		$this->load->model('pms/shareholder');

		$shareholder = $this->model_pms_shareholder->getShareholderByCustomerId($this->customer->getId());
        
        if (!$shareholder) {
            $this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']));
        }
        $data['customer_name'] = $this->customer->getFullName();
        $data['shareholder_id'] = $shareholder['id'];
        $data['shareholder_name'] = $shareholder['firstname'] . ' ' . $shareholder['lastname'];
        $data['shareholder_code'] = $shareholder['shareholder_code'];
		

		$this->document->setTitle($this->language->get('heading_title'));

		$data['project'] = $this->customer->getProject();

		$apartments = $this->model_pms_shareholder->getShareholderApartments($data['project']['project_id'], $shareholder['id']);
		$apartment_number = implode(', ', array_column($apartments, 'apartment_number'));
		$data['apartment_number'] = $apartment_number;
		$ownership_percentage = $this->model_pms_shareholder->getOwnershipPercentage($shareholder['id']);
		$data['ownership_percentage'] = $ownership_percentage;

		$data['current_due'] = $this->model_pms_shareholder->getTotalDue($shareholder['id']);

		$data['credit_balance'] = $this->model_pms_shareholder->getCreditBalance($shareholder['id']);

		// Get 5 most recent transactions for dashboard
    	$data['recent_transactions'] = $this->model_pms_shareholder->getRecentTransactions($shareholder['id'], 5);

		$shareholder = $this->model_pms_shareholder->getShareholderByCustomerId($this->customer->getId());

		$data['payment_link'] = $this->url->link('account/pms_my_bills', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
		$data['ledger_link'] = $this->url->link('account/pms_my_bills.paymentHistory', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
		$data['bills_link'] = $this->url->link('account/pms_my_bills', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
		$data['unpaid_dues_link'] = $this->url->link('account/pms_my_payment_allocations', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
		$data['apartment_link'] = $this->url->link('account/pms_my_apartments', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);

		if ($shareholder) {
			$data['unpaid_bills_summary'] = $this->model_pms_shareholder->getUnpaidBillsSummary($shareholder['id']);
			$data['total_unpaid_bills'] = $data['unpaid_bills_summary']['total_unpaid'];
			$data['unpaid_bills_count'] = $data['unpaid_bills_summary']['unpaid_count'];
			$data['earliest_due_date'] = $data['unpaid_bills_summary']['earliest_due_date'];
		} else {
			$data['unpaid_bills_summary'] = [];
			$data['total_unpaid_bills'] = 0;
			$data['unpaid_bills_count'] = 0;
			$data['earliest_due_date'] = 'N/A';
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];

			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('account/shareholder_dashboard', $data));
	}
}

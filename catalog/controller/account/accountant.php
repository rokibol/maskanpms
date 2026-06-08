<?php
namespace Opencart\Catalog\Controller\Account;
/**
 * Class Accountant
 *
 * @package Opencart\Catalog\Controller\Accountant
 */
class Accountant extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('account/accountant');

		if (!$this->load->controller('account/login.validate')) {
			$this->session->data['redirect'] = $this->url->link('account/accountant', 'language=' . $this->config->get('config_language'));

			$this->response->redirect($this->url->link('account/login', 'language=' . $this->config->get('config_language'), true));
		}

		$this->load->model('pms/expense');
		$this->load->model('pms/payment');
		$this->load->model('pms/expense_invoice');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		/*$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('account/accountant', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_account'),
			'href' => $this->url->link('account/accountant', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token'])
		];*/

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];

			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		$project_info = $this->customer->getProject();

		$data['monthly_expenses'] = $this->model_pms_expense->getTotalExpenseSum($project_info['project_id']);

		$filter_data = array(
			'filter_date_from' => date('Y-m-d', strtotime('-30 days')),
			'filter_date_to' => date('Y-m-d'),
			'project_id' => $project_info['project_id']
		);

		$expense_count = $this->model_pms_expense->getTotalExpenses($filter_data);

		$total_payment_collected = $this->model_pms_payment->getProjectTotalPayment($project_info['project_id']);

		$total_payment = $this->model_pms_payment->getTotalPayments($filter_data);

		$data['recent_payments'] = $this->model_pms_payment->getRecentPayments($project_info['project_id'], 5);

		$data['recent_expenses'] = $this->model_pms_expense_invoice->getInvoices($project_info);

		$data['expense_count'] = $expense_count;

		$data['monthly_collections'] = $total_payment_collected;

		$data['payment_count'] = $total_payment;

		$data['accountant_name'] = $this->customer->getFullName();

		$data['current_month'] = date('M');

		$data['current_year'] = date('Y');

		$data['project_name'] = $project_info['name'];

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('account/accountant_dashboard', $data));
	}
}

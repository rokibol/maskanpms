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
    	//$data['recent_transactions'] = $this->model_pms_shareholder->getRecentTransactions($shareholder['id'], 5);

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

	/**
     * AJAX: Load Unpaid Bills Breakdown table rows
     */
    public function loadUnpaidBreakdown(): void {
        if (!$this->customer->isLogged()) {
            return;
        }

        $this->load->language('account/account');
        $this->load->model('pms/shareholder');

        $shareholder = $this->model_pms_shareholder->getShareholderByCustomerId($this->customer->getId());
        if (!$shareholder) {
            return;
        }

        $summary = $this->model_pms_shareholder->getUnpaidBillsSummary($shareholder['id']);

        $html = '';
        if (!empty($summary['by_type'])) {
            foreach ($summary['by_type'] as $bill) {
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($bill['bill_type']) . '</td>';
                $html .= '<td>' . (int)$bill['bill_count'] . ' bill(s)</td>';
                $html .= '<td class="text-danger">৳ ' . number_format($bill['total_amount'], 2) . '</td>';
                $html .= '<td>' . date('M Y', strtotime($bill['oldest_month'])) . '</td>';
                $html .= '</tr>';
            }
        } else {
            $html = '<tr><td colspan="4" class="text-center text-muted py-3">No unpaid bills found</td></tr>';
        }

        $this->response->setOutput($html);
    }

    /**
     * AJAX: Load Recent Transactions table rows
     */
    public function loadRecentTransactions(): void {
        if (!$this->customer->isLogged()) {
            return;
        }

        $this->load->language('account/account');
        $this->load->model('pms/shareholder');

        $shareholder = $this->model_pms_shareholder->getShareholderByCustomerId($this->customer->getId());
        if (!$shareholder) {
            return;
        }

        $transactions = $this->model_pms_shareholder->getRecentTransactions($shareholder['id'], 5);

        $html = '';
        if ($transactions) {
            foreach ($transactions as $transaction) {
                $status_class = '';
                if ($transaction['status'] == 'paid') {
                    $status_class = 'pms-status-paid';
                } elseif ($transaction['status'] == 'pending') {
                    $status_class = 'pms-status-pending';
                } elseif ($transaction['status'] == 'overdue') {
                    $status_class = 'pms-status-overdue';
                }

                $type_class = ($transaction['type'] == 'debit') ? 'text-danger' : 'text-success';
                $sign = ($transaction['type'] == 'debit') ? '-' : '+';

                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($transaction['transaction_date']) . '</td>';
                $html .= '<td>' . htmlspecialchars($transaction['description']) . '</td>';
                $html .= '<td class="' . $type_class . '">' . $sign . ' ৳ ' . number_format($transaction['amount'], 2) . '</td>';
                $html .= '<td><span class="pms-status ' . $status_class . '">' . ucfirst($transaction['status']) . '</span></td>';
                $html .= '</tr>';
            }
        } else {
            $html = '<tr><td colspan="4" class="text-center text-muted py-3">No transactions found</td></tr>';
        }

        $this->response->setOutput($html);
    }
}

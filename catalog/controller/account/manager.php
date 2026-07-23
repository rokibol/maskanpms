<?php
namespace Opencart\Catalog\Controller\Account;
/**
 * Class Manager
 *
 * @package Opencart\Catalog\Controller\Manager
 */
class Manager extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->load->language('account/manager');

		if (!$this->load->controller('account/login.validate')) {
			$this->session->data['redirect'] = $this->url->link('account/manager', 'language=' . $this->config->get('config_language'));

			$this->response->redirect($this->url->link('account/login', 'language=' . $this->config->get('config_language'), true));
		}

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		/*$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('account/manager', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_account'),
			'href' => $this->url->link('account/manager', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token'])
		];*/

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];

			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		$this->load->model('pms/apartment');

		$this->load->model('pms/payment');

		$this->load->model('pms/shareholder');

		$this->load->model('pms/expense');

		$this->load->model('pms/expense_invoice');

		$project_info = $this->customer->getProject();

		$data['project_name'] = $project_info['name'];

		$data['total_apartments'] = $data['total_shares'] = $project_info['total_shares'];

		$data['project_status'] = $project_info['status'];

		$data['total_collections'] = $this->model_pms_payment->getProjectTotalPayment($project_info['project_id']);

		$data['allocated_amount'] = $this->model_pms_shareholder->getProjectTotalAllocatedPayment($project_info['project_id']);

		$data['total_due'] = (int)$data['allocated_amount'] - (int)$data['total_collections'];

		if($data['allocated_amount'] > 0) {
			$data['collection_percentage'] = $data['allocated_amount'] = (int)(($data['total_collections'] / $data['allocated_amount']) * 100);
		} else {
			$data['collection_percentage'] = 0;
		}

		$data['manager_name'] = $this->customer->getFullName();

		$data['paying_shareholders'] = $data['total_shareholders'] = $this->model_pms_shareholder->getTotalShareholders(['project_id' => $project_info['project_id']]);

		$data['total_expenses'] = $this->model_pms_expense->getTotalExpenseSum($project_info['project_id']);

		$data['expense_categories'] = $this->model_pms_expense->getExpenseBreakdownByCategory($project_info['project_id']);

		$data['recent_expenses'] = $this->model_pms_expense_invoice->getInvoices($project_info);

		$apartment_stats = $this->model_pms_apartment->getApartmentStats($project_info['project_id']);

		$data['occupied_apartments'] = (int)$project_info['total_shares'] - (int)$apartment_stats['available'];

		// Get top shareholders by ownership (default)
		$data['top_shareholders'] = $this->model_pms_shareholder->getTopShareholders($project_info['project_id'], 'ownership', 5);

		// OR get summary with all metrics
		$data['top_shareholders_summary'] = $this->model_pms_shareholder->getTopShareholdersSummary($project_info['project_id'], 5);

		//expense_report_link
		$data['expense_report_link'] = $this->url->link('account/pms_expenses', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
		$data['all_expenses_link'] = $this->url->link('account/pms_expense_invoices', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);

		// Get recent payments
		$data['recent_payments'] = $this->model_pms_payment->getRecentPayments($project_info['project_id'], 5);

		// Get top shareholders
		$data['top_shareholders'] = $this->model_pms_shareholder->getTopShareholders($project_info['project_id'], 'ownership', 5);

		$data['language'] = $this->config->get('config_language');
		$data['customer_token'] = $this->session->data['customer_token'];

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('account/manager_dashboard', $data));
	}

	/**
	 * Get collection breakdown for AJAX modal
	 */
	public function getCollectionBreakdown(): void {
		$json = [];
		
		// Get current project
		$this->load->model('pms/project');
		$project = $this->customer->getProject();
		
		if ($project) {
			$query = $this->db->query("
				SELECT 
					pp.phase_id,
					pp.name AS phase_name,
					COUNT(p.id) AS total_transactions,
					COALESCE(SUM(p.amount), 0) AS total_collected_amount
				FROM `" . DB_PREFIX . "payment_phase` pp
				LEFT JOIN `" . DB_PREFIX . "pms_payment` p 
					ON pp.phase_id = p.payment_phase_id 
					AND p.project_id = '" . (int)$project['project_id'] . "'
					AND p.is_deleted = 0
				WHERE pp.is_active = 1
				GROUP BY pp.phase_id, pp.name
				ORDER BY pp.sort_order ASC
			");
			
			if ($query->num_rows > 0) {
				$json['success'] = true;
				$json['data'] = $query->rows;
			} else {
				$json['success'] = true;
				$json['data'] = [];
			}
		} else {
			$json['success'] = false;
			$json['error'] = 'Project not found';
		}
		
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Get top shareholders via AJAX for dynamic switching
	 */
	public function getTopShareholders(): void {
		if (!$this->customer->isLogged()) {
			$this->response->redirect($this->url->link('account/login', 'language=' . $this->config->get('config_language')));
		}
		
		// Check if user is Manager
		/*$customer_group_id = $this->customer->getGroupId();
		$manager_group_id = (int)$this->config->get('config_pms_manager_group_id');
		
		if ($customer_group_id != $manager_group_id) {
			$this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language')));
		}*/
		
		$criteria = isset($this->request->get['criteria']) ? $this->request->get['criteria'] : 'ownership';
		$limit = isset($this->request->get['limit']) ? (int)$this->request->get['limit'] : 5;
		
		// Get current project
		$this->load->model('pms/project');
		$project = $this->model_pms_project->getProjectByCustomerId($this->customer->getId());
		
		if (!$project) {
			$this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language')));
		}
		
		$this->load->model('pms/shareholder');
		$top_shareholders = $this->model_pms_shareholder->getTopShareholders($project['project_id'], $criteria, $limit);
		
		// Return HTML for AJAX request
		if ($this->request->server['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
			$html = $this->renderTopShareholdersTable($top_shareholders, $criteria);
			$this->response->setOutput($html);
		} else {
			// Return JSON for API calls
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($top_shareholders));
		}
	}

	/**
	 * Render top shareholders table HTML
	 * @param array $shareholders
	 * @param string $criteria
	 * @return string
	 */
	private function renderTopShareholdersTable(array $shareholders, string $criteria): string {
		$html = '<div class="table-responsive">';
		$html .= '<table class="pms-table">';
		$html .= '<thead>';
		$html .= '<tr>';
		$html .= '<th>Shareholder</th>';
		$html .= '<th>Code</th>';
		$html .= '<th>Ownership %</th>';
		$html .= '<th>Units</th>';
		
		switch ($criteria) {
			case 'paid':
				$html .= '<th>Total Paid</th>';
				$html .= '<th>Outstanding</th>';
				break;
			case 'due':
				$html .= '<th>Outstanding Due</th>';
				$html .= '<th>Total Paid</th>';
				break;
			case 'allocated':
				$html .= '<th>Total Allocated</th>';
				$html .= '<th>Total Paid</th>';
				break;
			default:
				$html .= '<th>Total Paid</th>';
				$html .= '<th>Outstanding</th>';
		}
		
		$html .= '</tr>';
		$html .= '</thead>';
		$html .= '<tbody>';
		
		foreach ($shareholders as $shareholder) {
			$html .= '<tr>';
			$html .= '<td>' . $shareholder['name'] . '</td>';
			$html .= '<td>' . $shareholder['shareholder_code'] . '</td>';
			$html .= '<td class="fw-bold">' . round($shareholder['percentage'], 2) . '%</td>';
			$html .= '<td>' . $shareholder['ownership_units'] . ' units</td>';
			
			switch ($criteria) {
				case 'paid':
					$html .= '<td class="text-success">৳ ' . number_format($shareholder['total_paid'], 2) . '</td>';
					$html .= '<td class="text-danger">৳ ' . number_format($shareholder['total_due'], 2) . '</td>';
					break;
				case 'due':
					$html .= '<td class="text-danger">৳ ' . number_format($shareholder['total_due'], 2) . '</td>';
					$html .= '<td class="text-success">৳ ' . number_format($shareholder['total_paid'], 2) . '</td>';
					break;
				case 'allocated':
					$html .= '<td class="text-success">৳ ' . number_format($shareholder['total_allocated'], 2) . '</td>';
					$html .= '<td class="text-success">৳ ' . number_format($shareholder['total_paid'], 2) . '</td>';
					break;
				default:
					$html .= '<td class="text-success">৳ ' . number_format($shareholder['total_paid'], 2) . '</td>';
					$html .= '<td class="text-danger">৳ ' . number_format($shareholder['total_due'], 2) . '</td>';
			}
			
			$html .= '</tr>';
		}
		
		$html .= '</tbody>';
		$html .= '</table>';
		$html .= '</div>';
		
		return $html;
	}
}
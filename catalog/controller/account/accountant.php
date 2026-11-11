<?php
namespace Opencart\Catalog\Controller\Account;

class Accountant extends \Opencart\System\Engine\Controller {
    
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

        $project_info = $this->customer->getProject();

        // Main stats (these stay server-side for instant display)
        $data['monthly_expenses'] = $this->model_pms_expense->getTotalExpenseSum($project_info['project_id']);

        $filter_data = [
            'filter_date_from' => date('Y-m-d', strtotime('-30 days')),
            'filter_date_to'   => date('Y-m-d'),
            'project_id'       => $project_info['project_id']
        ];

        $data['expense_count'] = $this->model_pms_expense->getTotalExpenses($filter_data);
        $data['monthly_collections'] = $this->model_pms_payment->getProjectTotalPayment($project_info['project_id']);
        $data['payment_count'] = $this->model_pms_payment->getTotalPayments($filter_data);

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

    /**
     * AJAX: Load recent expenses table rows
     */
    public function loadRecentExpenses(): void {
        if (!$this->customer->isLogged()) {
            return;
        }

        $this->load->model('pms/expense_invoice');
        $project_info = $this->customer->getProject();

        if (!$project_info) {
            return;
        }

        $results = $this->model_pms_expense_invoice->getInvoices($project_info);

        // Build only the <tr> rows
        $html = '';
        if ($results) {
            foreach ($results as $expense) {
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($expense['invoice_date']) . '</td>';
                $html .= '<td>' . htmlspecialchars($expense['invoice_number']) . '</td>';
                $html .= '<td>' . htmlspecialchars($expense['supplier_name']) . '</td>';
                $html .= '<td>' . (int)$expense['payment_count'] . '</td>';
                $html .= '<td class="text-danger"> ' . htmlspecialchars($expense['paid_amount']) . '</td>';
                $html .= '<td><span class="pms-status pms-status-paid">' . htmlspecialchars($expense['payment_status']) . '</span></td>';
                $html .= '</tr>';
            }
        } else {
            $html .= '<tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            <i class="fas fa-regular fa-inbox fa-2x mb-2 d-block"></i>
                            No expenses recorded yet
                        </td>
                      </tr>';
        }

        $this->response->setOutput($html);
    }

    /**
     * AJAX: Load recent payments table rows
     */
    public function loadRecentPayments(): void {
        if (!$this->customer->isLogged()) {
            return;
        }

        $this->load->model('pms/payment');
        $project_info = $this->customer->getProject();

        if (!$project_info) {
            return;
        }

        $results = $this->model_pms_payment->getRecentPayments($project_info['project_id'], 5);

        // Build only the <tr> rows
        $html = '';
        if ($results) {
            foreach ($results as $payment) {
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($payment['payment_date']) . '</td>';
                $html .= '<td>' . htmlspecialchars($payment['shareholder_name']) . '</td>';
                $html .= '<td>' . htmlspecialchars($payment['phase_name']) . '</td>';
                $html .= '<td class="text-success">+  ' . htmlspecialchars($payment['amount']) . '</td>';
                $html .= '</tr>';
            }
        } else {
            $html .= '<tr>
                        <td colspan="4" class="text-center text-muted py-3">No payments recorded yet</td>
                      </tr>';
        }

        $this->response->setOutput($html);
    }
}
<?php
namespace Opencart\Catalog\Controller\Account;

class PmsPaymentHistory extends \Opencart\System\Engine\Controller {

    public function index(): void {
        // Check if logged in
        if (!$this->customer->isLogged()) {
            $this->session->data['redirect'] = $this->url->link('account/pms_payment_history', 'language=' . $this->config->get('config_language'));
            $this->response->redirect($this->url->link('account/login', 'language=' . $this->config->get('config_language')));
        }
        
        $this->load->language('pms/my_bills');
        
        $this->document->setTitle($this->language->get('heading_payment_history'));
        
        // Get shareholder info
        $this->load->model('pms/shareholder');
        $shareholder = $this->model_pms_shareholder->getShareholderByCustomerId($this->customer->getId());
        
        if (!$shareholder) {
            $this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']));
        }
        
        // Get filter parameters
        $filter_year = isset($this->request->get['filter_year']) ? (int)$this->request->get['filter_year'] : '';
        $filter_month = isset($this->request->get['filter_month']) ? (int)$this->request->get['filter_month'] : '';
        
        // Load payment model
        $this->load->model('pms/payment');
        
        // Get payment history
        $filter_data = [
            'filter_year' => $filter_year,
            'filter_month' => $filter_month,
            'limit' => 100
        ];
        
        $data['payments'] = $this->model_pms_payment->getFilteredPaymentHistory($shareholder['shareholder_id'], $filter_data);
        $data['payment_summary'] = $this->model_pms_payment->getPaymentSummaryForShareholder($shareholder['shareholder_id']);
        
        // Get available years for filter
        $query = $this->db->query("
            SELECT DISTINCT YEAR(payment_date) as year
            FROM `" . DB_PREFIX . "pms_payment`
            WHERE shareholder_id = '" . (int)$shareholder['shareholder_id'] . "'
            AND is_deleted = 0
            ORDER BY year DESC
        ");
        $data['available_years'] = $query->rows;
        
        $data['filter_year'] = $filter_year;
        $data['filter_month'] = $filter_month;
        
        // Months array for filter
        $data['months'] = [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
        ];
        
        $data['back'] = $this->url->link('account/pms_my_bills', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        
        $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
        
        $this->response->setOutput($this->load->view('account/pms_payment_history', $data));
    }
}
<?php
namespace Opencart\Catalog\Controller\Account;

class PmsPayments extends \Opencart\System\Engine\Controller {

    public function index(): void {
        // Check if logged in
        if (!$this->customer->isLogged()) {
            $this->session->data['redirect'] = $this->url->link('account/pms_payments', 'language=' . $this->config->get('config_language'));
            $this->response->redirect($this->url->link('account/login', 'language=' . $this->config->get('config_language')));
        }
        
        // Check if user is Accountant or Manager
        /*$customer_group_id = $this->customer->getGroupId();
        $accountant_group_id = (int)$this->config->get('config_pms_accountant_group_id');
        $manager_group_id = (int)$this->config->get('config_pms_manager_group_id');
        
        if ($customer_group_id != $accountant_group_id && $customer_group_id != $manager_group_id) {
            $this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language')));
        }*/
        
        $this->load->language('pms/payments');
        
        $this->document->setTitle($this->language->get('heading_title'));
        
        // Get current project
        $this->load->model('pms/project');
        $project = $this->customer->getProject();
        
        if (!$project) {
            $this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language')));
        }
        
        $data['project_id'] = $project['project_id'];
        $data['project_name'] = $project['name'];
        
        // Set up pagination and filters
        $url = '';
        
        $filter_shareholder = isset($this->request->get['filter_shareholder']) ? $this->request->get['filter_shareholder'] : '';
        $filter_phase = isset($this->request->get['filter_phase']) ? $this->request->get['filter_phase'] : '';
        $filter_method = isset($this->request->get['filter_method']) ? $this->request->get['filter_method'] : '';
        $filter_date_from = isset($this->request->get['filter_date_from']) ? $this->request->get['filter_date_from'] : '';
        $filter_date_to = isset($this->request->get['filter_date_to']) ? $this->request->get['filter_date_to'] : '';
        
        $sort = isset($this->request->get['sort']) ? $this->request->get['sort'] : 'p.payment_date';
        $order = isset($this->request->get['order']) ? $this->request->get['order'] : 'DESC';
        $page = isset($this->request->get['page']) ? (int)$this->request->get['page'] : 1;
        
        // Build URL for pagination
        if ($filter_shareholder) $url .= '&filter_shareholder=' . urlencode($filter_shareholder);
        if ($filter_phase) $url .= '&filter_phase=' . $filter_phase;
        if ($filter_method) $url .= '&filter_method=' . $filter_method;
        if ($filter_date_from) $url .= '&filter_date_from=' . $filter_date_from;
        if ($filter_date_to) $url .= '&filter_date_to=' . $filter_date_to;
        if ($sort) $url .= '&sort=' . $sort;
        if ($order) $url .= '&order=' . $order;
        
        $limit = $this->config->get('config_pagination_admin');
        
        $filter_data = [
            'project_id'          => $project['project_id'],
            'filter_shareholder'  => $filter_shareholder,
            'filter_phase'        => $filter_phase,
            'filter_method'       => $filter_method,
            'filter_date_from'    => $filter_date_from,
            'filter_date_to'      => $filter_date_to,
            'sort'                => $sort,
            'order'               => $order,
            'start'               => ($page - 1) * $limit,
            'limit'               => $limit
        ];
        
        $this->load->model('pms/payment');
        
        $payment_total = $this->model_pms_payment->getTotalPayments($filter_data);
        $results = $this->model_pms_payment->getPayments($filter_data);
        
        $data['payments'] = [];
        
        foreach ($results as $result) {
            $data['payments'][] = [
                'payment_id'        => $result['id'],
                'shareholder_name'  => $result['shareholder_name'],
                'shareholder_code'  => $result['shareholder_code'],
                'phase_name'        => $result['phase_name'],
                'amount'            => $result['amount'],
                'payment_date'      => date($this->language->get('date_format_short'), strtotime($result['payment_date'])),
                'payment_method'    => ucfirst($result['payment_method']),
                'reference_number'  => $result['reference_number'] ?: '-',
                'recorded_by'       => $result['recorded_by_name'],
                'view'              => $this->url->link('account/pms_payments.view', 'language=' . $this->config->get('config_language') . '&payment_id=' . $result['id'] . $url . '&customer_token=' . $this->session->data['customer_token']),
                'delete'            => $this->url->link('account/pms_payments.delete', 'language=' . $this->config->get('config_language') . '&payment_id=' . $result['id'] . $url . '&customer_token=' . $this->session->data['customer_token'])
            ];
        }
        
        // Pagination
        $data['pagination'] = $this->load->controller('common/pagination', [
			'total' => $payment_total,
			'page'  => $page,
			'limit' => $this->config->get('config_pagination_admin'),
			'url'   => $this->url->link('account/pms_shareholders.list', $url .  '&customer_token=' . $this->session->data['customer_token'] . '&page={page}')
		]);

		$data['results'] = sprintf($this->language->get('text_pagination'), ($payment_total) ? (($page - 1) * $this->config->get('config_pagination_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_pagination_admin')) > ($payment_total - $this->config->get('config_pagination_admin'))) ? $payment_total : ((($page - 1) * $this->config->get('config_pagination_admin')) + $this->config->get('config_pagination_admin')), $payment_total, ceil($payment_total / $this->config->get('config_pagination_admin')));
        

        // Sort links
        $data['sort_date'] = $this->url->link('account/pms_payments', 'language=' . $this->config->get('config_language') . '&sort=p.payment_date' . $url . '&customer_token=' . $this->session->data['customer_token']);
        $data['sort_amount'] = $this->url->link('account/pms_payments', 'language=' . $this->config->get('config_language') . '&sort=p.amount' . $url . '&customer_token=' . $this->session->data['customer_token']);
        $data['sort_method'] = $this->url->link('account/pms_payments', 'language=' . $this->config->get('config_language') . '&sort=p.payment_method' . $url . '&customer_token=' . $this->session->data['customer_token']);
        
        // Filter values
        $data['filter_shareholder'] = $filter_shareholder;
        $data['filter_phase'] = $filter_phase;
        $data['filter_method'] = $filter_method;
        $data['filter_date_from'] = $filter_date_from;
        $data['filter_date_to'] = $filter_date_to;
        
        // Filter options
        $data['payment_phases'] = $this->model_pms_payment->getPaymentPhases();
        $data['payment_methods'] = $this->model_pms_payment->getPaymentMethods();
        
        // Payment statistics
        $data['stats'] = $this->model_pms_payment->getPaymentStats($project['project_id']);
        
        // Sort and order
        $data['sort'] = $sort;
        $data['order'] = $order;
        
        // URLs
        $data['add'] = $this->url->link('account/pms_payments.add', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        $data['back'] = $this->url->link('account/accountant', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        
        // Template parts
        $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
        
        $this->response->setOutput($this->load->view('account/pms_payments_list', $data));
    }
    
    public function add(): void {
        $this->form();
    }
    
    private function form(): void {
        if (!$this->customer->isLogged()) {
            $this->response->redirect($this->url->link('account/login', 'language=' . $this->config->get('config_language')));
        }
        
        $this->load->language('pms/payments');
        
        $this->document->setTitle($this->language->get('heading_add'));
        
        // Get current project
        $this->load->model('pms/project');
        $project = $this->customer->getProject();
        
        if (!$project) {
            $this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']));
        }
        
        $data['project_id'] = $project['project_id'];
        $data['project_name'] = $project['name'];
        
        // Load required models
        $this->load->model('pms/payment');
        $this->load->model('pms/shareholder');
        
        // Form action
        $url = $this->buildUrl();
        $data['action'] = $this->url->link('account/pms_payments.save', 'language=' . $this->config->get('config_language') . $url . '&customer_token=' . $this->session->data['customer_token']);
        $data['back'] = $this->url->link('account/pms_payments', 'language=' . $this->config->get('config_language') . $url . '&customer_token=' . $this->session->data['customer_token']);
        
        // Get all shareholders for this project
        $shareholders = $this->model_pms_shareholder->getShareholders(['project_id' => $project['project_id']]);
        $data['shareholders'] = $shareholders;
        
        // Get payment phases and methods
        $data['payment_phases'] = $this->model_pms_payment->getPaymentPhases();
        $data['payment_methods'] = $this->model_pms_payment->getPaymentMethods();
        
        // Set default values
        $data['payment_date'] = date('Y-m-d');
        $data['payment_method'] = 'cash';
        
        $data['customer_token'] = $this->session->data['customer_token'];
        
        $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
        
        $this->response->setOutput($this->load->view('account/pms_payment_form', $data));
    }
    
    public function view(): void {
        if (!$this->customer->isLogged()) {
            $this->response->redirect($this->url->link('account/login', 'language=' . $this->config->get('config_language')));
        }
        
        $this->load->language('pms/payments');
        
        $this->document->setTitle($this->language->get('heading_view'));
        
        $payment_id = isset($this->request->get['payment_id']) ? (int)$this->request->get['payment_id'] : 0;
        
        $this->load->model('pms/payment');
        $payment_info = $this->model_pms_payment->getPayment($payment_id);
        
        if (!$payment_info) {
            $this->response->redirect($this->url->link('account/pms_payments', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']));
        }
        
        $data['payment'] = [
            'payment_id'        => $payment_info['id'],
            'shareholder_name'  => $payment_info['shareholder_name'],
            'shareholder_code'  => $payment_info['shareholder_code'],
            'shareholder_email' => $payment_info['shareholder_email'],
            'phase_name'        => $payment_info['phase_name'],
            'amount'            => number_format($payment_info['amount'], 2),
            'payment_date'      => date($this->language->get('date_format_short'), strtotime($payment_info['payment_date'])),
            'payment_method'    => ucfirst($payment_info['payment_method']),
            'reference_number'  => $payment_info['reference_number'] ?: '-',
            'recorded_by'       => $payment_info['recorded_by_name'],
            'created_at'        => date($this->language->get('date_format_short') . ' H:i', strtotime($payment_info['created_at'])),
            'amount_in_words'   => $this->convertAmountToWords($payment_info['amount'])
        ];
        
        $url = $this->buildUrl();
        $data['back'] = $this->url->link('account/pms_payments', 'language=' . $this->config->get('config_language') . $url . '&customer_token=' . $this->session->data['customer_token']);
        
        $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
        
        $this->response->setOutput($this->load->view('account/pms_payment_view', $data));
    }

    /**
     * Convert number to words for Taka/Poisha format
     * @param float $number
     * @return string
     */
    private function convertAmountToWords(float $number): string {
        $taka = floor($number);
        $poisha = round(($number - $taka) * 100);
        
        $taka_in_words = $this->convertNumberToWordsBangla($taka);
        
        if ($poisha > 0) {
            $poisha_in_words = $this->convertNumberToWordsBangla($poisha);
            return $taka_in_words . ' Taka and ' . $poisha_in_words . ' Poisha Only';
        }
        
        return $taka_in_words . ' Taka Only';
    }

    /**
     * Convert number to words (Bangladeshi format - Crore, Lakh)
     * @param int $num
     * @return string
     */
    private function convertNumberToWordsBangla(int $num): string {
        if ($num == 0) return 'Zero';
        
        $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
                'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen',
                'Seventeen', 'Eighteen', 'Nineteen'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
        
        $words = '';
        
        // Crore (10000000)
        if ($num >= 10000000) {
            $crore = floor($num / 10000000);
            $words .= $this->convertNumberToWordsBangla($crore) . ' Crore ';
            $num %= 10000000;
        }
        
        // Lakh (100000)
        if ($num >= 100000) {
            $lakh = floor($num / 100000);
            $words .= $this->convertNumberToWordsBangla($lakh) . ' Lakh ';
            $num %= 100000;
        }
        
        // Thousand
        if ($num >= 1000) {
            $thousand = floor($num / 1000);
            $words .= $this->convertNumberToWordsBangla($thousand) . ' Thousand ';
            $num %= 1000;
        }
        
        // Hundred
        if ($num >= 100) {
            $hundred = floor($num / 100);
            $words .= $ones[$hundred] . ' Hundred ';
            $num %= 100;
        }
        
        // Tens and Ones
        if ($num > 0) {
            if ($num < 20) {
                $words .= $ones[$num] . ' ';
            } else {
                $tensDigit = floor($num / 10);
                $onesDigit = $num % 10;
                $words .= $tens[$tensDigit] . ($onesDigit ? ' ' . $ones[$onesDigit] : '') . ' ';
            }
        }
        
        return trim($words);
    }
    
    public function save(): void {
        $this->load->language('pms/payments');
        
        $json = [];
        
        // Check permission
        /*$customer_group_id = $this->customer->getGroupId();
        $accountant_group_id = (int)$this->config->get('config_pms_accountant_group_id');
        $manager_group_id = (int)$this->config->get('config_pms_manager_group_id');
        
        if ($customer_group_id != $accountant_group_id && $customer_group_id != $manager_group_id) {
            $json['error'] = $this->language->get('error_permission');
        }*/
        
        // Validation
        if (!$json) {
            if (empty($this->request->post['shareholder_id'])) {
                $json['error']['shareholder_id'] = $this->language->get('error_shareholder');
            }
            
            if (empty($this->request->post['amount']) || $this->request->post['amount'] <= 0) {
                $json['error']['amount'] = $this->language->get('error_amount');
            }
            
            if (empty($this->request->post['payment_date'])) {
                $json['error']['payment_date'] = $this->language->get('error_payment_date');
            }
            
            if (empty($this->request->post['payment_method'])) {
                $json['error']['payment_method'] = $this->language->get('error_payment_method');
            }
        }

        if (!$json) {
            $this->load->model('pms/payment');
            
            $project = $this->customer->getProject();

            $this->request->post['project_id'] = $project['project_id'];
            
            $this->model_pms_payment->addPayment($this->request->post);
            
            $json['success'] = $this->language->get('text_add_success');
            $url = $this->buildUrl();
            $json['redirect'] = $this->url->link('account/pms_payments', 'language=' . $this->config->get('config_language') . $url . '&customer_token=' . $this->session->data['customer_token']);
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    
    public function delete(): void {
        $this->load->language('pms/payments');
        
        $json = [];
        
        $customer_group_id = $this->customer->getGroupId();
        $accountant_group_id = (int)$this->config->get('config_pms_accountant_group_id');
        $manager_group_id = (int)$this->config->get('config_pms_manager_group_id');
        
        if ($customer_group_id != $accountant_group_id && $customer_group_id != $manager_group_id) {
            $json['error'] = $this->language->get('error_permission');
        }
        
        if (!$json) {
            $this->load->model('pms/payment');
            
            if (isset($this->request->get['payment_id'])) {
                $this->model_pms_payment->deletePayment($this->request->get['payment_id']);
                $json['success'] = $this->language->get('text_delete_success');
            } else {
                $json['error'] = 'Invalid payment ID';
            }
            
            $url = $this->buildUrl();
            $json['redirect'] = $this->url->link('account/pms_payments', 'language=' . $this->config->get('config_language') . $url . '&customer_token=' . $this->session->data['customer_token']);
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    
    private function buildUrl(): string {
        $url = '';
        
        if (isset($this->request->get['filter_shareholder'])) {
            $url .= '&filter_shareholder=' . urlencode($this->request->get['filter_shareholder']);
        }
        if (isset($this->request->get['filter_phase'])) {
            $url .= '&filter_phase=' . $this->request->get['filter_phase'];
        }
        if (isset($this->request->get['filter_method'])) {
            $url .= '&filter_method=' . $this->request->get['filter_method'];
        }
        if (isset($this->request->get['filter_date_from'])) {
            $url .= '&filter_date_from=' . $this->request->get['filter_date_from'];
        }
        if (isset($this->request->get['filter_date_to'])) {
            $url .= '&filter_date_to=' . $this->request->get['filter_date_to'];
        }
        if (isset($this->request->get['sort'])) {
            $url .= '&sort=' . $this->request->get['sort'];
        }
        if (isset($this->request->get['order'])) {
            $url .= '&order=' . $this->request->get['order'];
        }
        if (isset($this->request->get['page'])) {
            $url .= '&page=' . $this->request->get['page'];
        }
        
        return $url;
    }
}
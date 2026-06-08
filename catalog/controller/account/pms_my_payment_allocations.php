<?php
namespace Opencart\Catalog\Controller\Account;

class PmsMyPaymentAllocations extends \Opencart\System\Engine\Controller {

    public function index(): void {
        // Check if logged in
        if (!$this->customer->isLogged()) {
            $this->session->data['redirect'] = $this->url->link('account/pms_my_payment_allocations', 'language=' . $this->config->get('config_language'));
            $this->response->redirect($this->url->link('account/login', 'language=' . $this->config->get('config_language')));
        }
        
        // Check if user is Shareholder
        /*$customer_group_id = $this->customer->getGroupId();
        $shareholder_group_id = (int)$this->config->get('config_pms_shareholder_group_id');
        
        if ($customer_group_id != $shareholder_group_id) {
            $this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language')));
        }*/
        
        $this->load->language('pms/my_payment_allocations');
        
        $this->document->setTitle($this->language->get('heading_title'));
        
        // Get shareholder info
        $this->load->model('pms/shareholder');
        $shareholder = $this->model_pms_shareholder->getShareholderByCustomerId($this->customer->getId());
        
        if (!$shareholder) {
            $this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language')));
        }
        
        $data['shareholder_id'] = $shareholder['id'];
        $data['shareholder_name'] = $shareholder['firstname'] . ' ' . $shareholder['lastname'];
        $data['shareholder_code'] = $shareholder['shareholder_code'];
        $data['ownership_percentage'] = $shareholder['ownership_percentage'] ?? 0;
        
        // Get project info
        $this->load->model('pms/project');
        $project = $this->model_pms_project->getProject($shareholder['project_id']);
        $data['project_name'] = $project['name'];
        $data['theme_color'] = $project['theme_color'];
        
        // Filter parameters
        $filter_phase = isset($this->request->get['filter_phase']) ? $this->request->get['filter_phase'] : '';
        $filter_year = isset($this->request->get['filter_year']) ? $this->request->get['filter_year'] : '';
        $filter_month = isset($this->request->get['filter_month']) ? $this->request->get['filter_month'] : '';
        
        // Get payment allocation summary
        $data['allocation_summary'] = $this->model_pms_shareholder->getMyPaymentAllocationSummary($shareholder['id']);
        
        // Get available years for filter
        $data['available_years'] = $this->model_pms_shareholder->getAvailableAllocationYears($shareholder['id']);
        
        // Get payment phases for filter
        $this->load->model('pms/payment');
        $data['payment_phases'] = $this->model_pms_payment->getPaymentPhases();
        
        // Get payment allocations
        $filter_data = [
            'filter_phase' => $filter_phase,
            'filter_year' => $filter_year,
            'filter_month' => $filter_month
        ];
        
        $data['allocations'] = $this->model_pms_shareholder->getMyPaymentAllocations($shareholder['id'], $filter_data);
        
        // Filter values
        $data['filter_phase'] = $filter_phase;
        $data['filter_year'] = $filter_year;
        $data['filter_month'] = $filter_month;
        
        // Months array for filter
        $data['months'] = [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
        ];
        
        // URLs
        $data['back'] = $this->url->link('account/pms_dashboard', 'language=' . $this->config->get('config_language'));
        
        $data['customer_token'] = $this->session->data['customer_token'];

        $data['language'] = $this->config->get('config_language');
        
        $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
        
        $this->response->setOutput($this->load->view('account/pms_my_payment_allocations', $data));
    }
}
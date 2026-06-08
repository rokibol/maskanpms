<?php
namespace Opencart\Catalog\Controller\Account;

class PmsPaymentAllocations extends \Opencart\System\Engine\Controller {

    public function index(): void {
        if (!$this->customer->isLogged()) {
            $this->response->redirect($this->url->link('account/login', 'language=' . $this->config->get('config_language')));
        }
        
        // Check if user is Manager
        /*$customer_group_id = $this->customer->getGroupId();
        $manager_group_id = (int)$this->config->get('config_pms_manager_group_id');
        
        if ($customer_group_id != $manager_group_id) {
            $this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language')));
        }*/
        
        $this->load->language('pms/payment_allocations');
        $this->document->setTitle($this->language->get('heading_title'));
        
        // Get current project
        $this->load->model('pms/project');
        $project = $this->model_pms_project->getProjectByCustomerId($this->customer->getId());
        
        if (!$project) {
            $this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']));
        }
        
        $data['project_id'] = $project['project_id'];
        $data['project_name'] = $project['name'];
        
        // Filter parameters
        $filter_shareholder = isset($this->request->get['filter_shareholder']) ? $this->request->get['filter_shareholder'] : '';
        $filter_phase = isset($this->request->get['filter_phase']) ? $this->request->get['filter_phase'] : '';
        $filter_date_from = isset($this->request->get['filter_date_from']) ? $this->request->get['filter_date_from'] : '';
        $filter_date_to = isset($this->request->get['filter_date_to']) ? $this->request->get['filter_date_to'] : '';
        $filter_amount_min = isset($this->request->get['filter_amount_min']) ? $this->request->get['filter_amount_min'] : '';
        $filter_amount_max = isset($this->request->get['filter_amount_max']) ? $this->request->get['filter_amount_max'] : '';
        
        $sort = isset($this->request->get['sort']) ? $this->request->get['sort'] : 'pa.created_at';
        $order = isset($this->request->get['order']) ? $this->request->get['order'] : 'DESC';
        $page = isset($this->request->get['page']) ? (int)$this->request->get['page'] : 1;
        
        $this->load->model('pms/shareholder');
        $this->load->model('pms/payment');
        
        // Build URL for pagination
        $url = '';
        if ($filter_shareholder) $url .= '&filter_shareholder=' . urlencode($filter_shareholder);
        if ($filter_phase) $url .= '&filter_phase=' . $filter_phase;
        if ($filter_date_from) $url .= '&filter_date_from=' . $filter_date_from;
        if ($filter_date_to) $url .= '&filter_date_to=' . $filter_date_to;
        if ($filter_amount_min) $url .= '&filter_amount_min=' . $filter_amount_min;
        if ($filter_amount_max) $url .= '&filter_amount_max=' . $filter_amount_max;
        if ($sort) $url .= '&sort=' . $sort;
        if ($order) $url .= '&order=' . $order;
        
        $limit = $this->config->get('config_pagination_admin');
        
        $filter_data = [
            'project_id'          => $project['project_id'],
            'filter_shareholder'  => $filter_shareholder,
            'filter_phase'        => $filter_phase,
            'filter_date_from'    => $filter_date_from,
            'filter_date_to'      => $filter_date_to,
            'filter_amount_min'   => $filter_amount_min,
            'filter_amount_max'   => $filter_amount_max,
            'sort'                => $sort,
            'order'               => $order,
            'start'               => ($page - 1) * $limit,
            'limit'               => $limit
        ];
        
        $allocation_total = $this->model_pms_shareholder->getTotalPaymentAllocations($filter_data);
        $allocations = $this->model_pms_shareholder->getAllPaymentAllocations($filter_data);
        
        // Get summary
        $data['summary'] = $this->model_pms_shareholder->getPaymentAllocationSummary($project['project_id']);
        
        // Get payment phases for filter
        $data['payment_phases'] = $this->model_pms_payment->getPaymentPhases();

        $data['language'] = $this->config->get('config_language');

        $data['customer_token'] = $this->session->data['customer_token'];
        
        $data['allocations'] = [];
        foreach ($allocations as $allocation) {
            $data['allocations'][] = [
                'allocation_id'      => $allocation['allocation_id'],
                'shareholder_name'   => $allocation['shareholder_name'],
                'shareholder_code'   => $allocation['shareholder_code'],
                'ownership_units'    => $allocation['ownership_units'],
                'ownership_percentage' => round($allocation['ownership_percentage'], 2),
                'phase_name'         => $allocation['phase_name'],
                'allocated_amount'   => $allocation['allocated_amount'],
                'notes'              => $allocation['notes'],
                'created_by'         => $allocation['created_by_name'],
                'created_at'         => date('d M Y h:i A', strtotime($allocation['created_at'])),
                'date_only'          => date('d M Y', strtotime($allocation['created_at']))
            ];
        }
        
        // Pagination
        $data['pagination'] = $this->load->controller('common/pagination', [
			'total' => $allocation_total,
			'page'  => $page,
			'limit' => $this->config->get('config_pagination_admin'),
			'url'   => $this->url->link('account/pms_payment_allocations', $url .  'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token'] . '&page={page}')
		]);

		$data['results'] = sprintf($this->language->get('text_pagination'), ($allocation_total) ? (($page - 1) * $this->config->get('config_pagination_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_pagination_admin')) > ($allocation_total - $this->config->get('config_pagination_admin'))) ? $allocation_total : ((($page - 1) * $this->config->get('config_pagination_admin')) + $this->config->get('config_pagination_admin')), $allocation_total, ceil($allocation_total / $this->config->get('config_pagination_admin')));
        
        // Sort links
        $data['sort_date'] = $this->url->link('account/pms_payment_allocations', 'language=' . $this->config->get('config_language') . '&sort=pa.created_at' . $url . '&customer_token=' . $this->session->data['customer_token']);
        $data['sort_amount'] = $this->url->link('account/pms_payment_allocations', 'language=' . $this->config->get('config_language') . '&sort=pa.allocated_amount' . $url . '&customer_token=' . $this->session->data['customer_token']);
        $data['sort_shareholder'] = $this->url->link('account/pms_payment_allocations', 'language=' . $this->config->get('config_language') . '&sort=shareholder_name' . $url . '&customer_token=' . $this->session->data['customer_token']);
        $data['sort_phase'] = $this->url->link('account/pms_payment_allocations', 'language=' . $this->config->get('config_language') . '&sort=phase_name' . $url . '&customer_token=' . $this->session->data['customer_token']);
        
        // Filter values
        $data['filter_shareholder'] = $filter_shareholder;
        $data['filter_phase'] = $filter_phase;
        $data['filter_date_from'] = $filter_date_from;
        $data['filter_date_to'] = $filter_date_to;
        $data['filter_amount_min'] = $filter_amount_min;
        $data['filter_amount_max'] = $filter_amount_max;
        
        $data['sort'] = $sort;
        $data['order'] = $order;
        
        $data['back'] = $this->url->link('account/manager', 'language=' . $this->config->get('config_language'). '&customer_token=' . $this->session->data['customer_token']);
        
        $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
        
        $this->response->setOutput($this->load->view('account/pms_payment_allocations', $data));
    }
}
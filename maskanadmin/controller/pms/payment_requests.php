<?php
namespace Opencart\Admin\Controller\Pms;

class PaymentRequests extends \Opencart\System\Engine\Controller {

    public function index(): void {
        $this->load->language('pms/payment_requests');
        
        $this->document->setTitle($this->language->get('heading_title'));
        
        $url = '';
        
        // Get current tab
        $tab = isset($this->request->get['tab']) ? $this->request->get['tab'] : 'all';
        
        if (isset($this->request->get['filter_project'])) {
            $url .= '&filter_project=' . urlencode($this->request->get['filter_project']);
        }
        
        if (isset($this->request->get['filter_bkash_number'])) {
            $url .= '&filter_bkash_number=' . urlencode($this->request->get['filter_bkash_number']);
        }
        
        if (isset($this->request->get['filter_transaction_id'])) {
            $url .= '&filter_transaction_id=' . urlencode($this->request->get['filter_transaction_id']);
        }
        
        if (isset($this->request->get['filter_amount_min'])) {
            $url .= '&filter_amount_min=' . $this->request->get['filter_amount_min'];
        }
        
        if (isset($this->request->get['filter_amount_max'])) {
            $url .= '&filter_amount_max=' . $this->request->get['filter_amount_max'];
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
        
        // Tab URLs
        $data['tab_all'] = $this->url->link('pms/payment_requests', 'user_token=' . $this->session->data['user_token'] . '&tab=all');
        $data['tab_pending'] = $this->url->link('pms/payment_requests', 'user_token=' . $this->session->data['user_token'] . '&tab=pending');
        $data['tab_approved'] = $this->url->link('pms/payment_requests', 'user_token=' . $this->session->data['user_token'] . '&tab=approved');
        $data['tab_rejected'] = $this->url->link('pms/payment_requests', 'user_token=' . $this->session->data['user_token'] . '&tab=rejected');
        $data['active_tab'] = $tab;
        
        $data['breadcrumbs'] = [];
        
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
        ];
        
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('pms/payment_requests', 'user_token=' . $this->session->data['user_token'] . $url)
        ];
        
        $this->load->model('pms/subscription_payment_request');
        
        // Filter data
        $filter_data = [
            'filter_project'          => $this->request->get['filter_project'] ?? '',
            'filter_bkash_number'     => $this->request->get['filter_bkash_number'] ?? '',
            'filter_transaction_id'   => $this->request->get['filter_transaction_id'] ?? '',
            'filter_amount_min'       => $this->request->get['filter_amount_min'] ?? '',
            'filter_amount_max'       => $this->request->get['filter_amount_max'] ?? '',
            'filter_status'           => $tab != 'all' ? $tab : '',
            'filter_date_from'        => $this->request->get['filter_date_from'] ?? '',
            'filter_date_to'          => $this->request->get['filter_date_to'] ?? '',
            'sort'                    => $this->request->get['sort'] ?? 'r.submitted_at',
            'order'                   => $this->request->get['order'] ?? 'DESC',
            'start'                   => (($this->request->get['page'] ?? 1) - 1) * $this->config->get('config_pagination_admin'),
            'limit'                   => $this->config->get('config_pagination_admin')
        ];
        
        $request_total = $this->model_pms_subscription_payment_request->getTotalRequests($filter_data);
        $requests = $this->model_pms_subscription_payment_request->getRequests($filter_data);
        
        // Get counts for tabs
        $data['count_all'] = $this->model_pms_subscription_payment_request->getTotalRequests([]);
        $data['count_pending'] = $this->model_pms_subscription_payment_request->getTotalRequests(['filter_status' => 'pending']);
        $data['count_approved'] = $this->model_pms_subscription_payment_request->getTotalRequests(['filter_status' => 'approved']);
        $data['count_rejected'] = $this->model_pms_subscription_payment_request->getTotalRequests(['filter_status' => 'rejected']);
        
        $data['requests'] = [];
        
        foreach ($requests as $request) {
            $status_class = '';
            $status_text = '';
            $action_buttons = '';
            
            switch ($request['status']) {
                case 'pending':
                    $status_class = 'warning';
                    $status_text = 'Pending';
                    $action_buttons = '
                        <button type="button" class="btn btn-success btn-sm" onclick="approveRequest(' . $request['request_id'] . ')" title="Approve">
                            <i class="fa-regular fa-circle-check"></i>
                        </button>
                        <button type="button" class="btn btn-danger btn-sm" onclick="showRejectModal(' . $request['request_id'] . ')" title="Reject">
                            <i class="fa-regular fa-circle-xmark"></i>
                        </button>
                    ';
                    break;
                case 'approved':
                    $status_class = 'success';
                    $status_text = 'Approved';
                    $action_buttons = '
                        <span class="text-success"><i class="fa-regular fa-circle-check"></i> Approved</span>
                    ';
                    break;
                case 'rejected':
                    $status_class = 'danger';
                    $status_text = 'Rejected';
                    $action_buttons = '
                        <span class="text-danger"><i class="fa-regular fa-circle-xmark"></i> Rejected</span>
                    ';
                    break;
                default:
                    $status_class = 'secondary';
                    $status_text = $request['status'];
            }
            
            $data['requests'][] = [
                'request_id'        => $request['request_id'],
                'project_name'      => $request['project_name'],
                'duration_months'   => $request['duration_months'],
                'amount'            => $request['amount'],
                'bkash_number'      => $request['bkash_number'],
                'transaction_id'    => $request['transaction_id'],
                'status'            => $request['status'],
                'status_class'      => $status_class,
                'status_text'       => $status_text,
                'submitted_by'      => $request['submitted_by_name'],
                'submitted_at'      => date('d M Y h:i A', strtotime($request['submitted_at'])),
                'approved_by'       => $request['approved_by_name'] ?? '-',
                'approved_at'       => $request['approved_at'] ? date('d M Y h:i A', strtotime($request['approved_at'])) : '-',
                'admin_notes'       => $request['admin_notes'] ?? '-',
                'action_buttons'    => $action_buttons,
                'approve'           => $this->url->link('pms/payment_requests.approve', 'user_token=' . $this->session->data['user_token'] . '&request_id=' . $request['request_id'] . $url),
                'reject'            => $this->url->link('pms/payment_requests.reject', 'user_token=' . $this->session->data['user_token'] . '&request_id=' . $request['request_id'] . $url),
                'delete'            => $this->url->link('pms/payment_requests.delete', 'user_token=' . $this->session->data['user_token'] . '&request_id=' . $request['request_id'] . $url)
            ];
        }
        
        // Pagination
        $page = $this->request->get['page'] ?? 1;

        $data['pagination'] = $this->load->controller('common/pagination', [
			'total' => $request_total,
			'page'  => $page,
			'limit' => $this->config->get('config_pagination_admin'),
			'url'   => $this->url->link('pms/project', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}')
		]);

		$data['results'] = sprintf($this->language->get('text_pagination'), ($request_total) ? (($page - 1) * $this->config->get('config_pagination_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_pagination_admin')) > ($request_total - $this->config->get('config_pagination_admin'))) ? $request_total : ((($page - 1) * $this->config->get('config_pagination_admin')) + $this->config->get('config_pagination_admin')), $request_total, ceil($request_total / $this->config->get('config_pagination_admin')));
        
        // Filter values
        $data['filter_project'] = $this->request->get['filter_project'] ?? '';
        $data['filter_bkash_number'] = $this->request->get['filter_bkash_number'] ?? '';
        $data['filter_transaction_id'] = $this->request->get['filter_transaction_id'] ?? '';
        $data['filter_amount_min'] = $this->request->get['filter_amount_min'] ?? '';
        $data['filter_amount_max'] = $this->request->get['filter_amount_max'] ?? '';
        $data['filter_date_from'] = $this->request->get['filter_date_from'] ?? '';
        $data['filter_date_to'] = $this->request->get['filter_date_to'] ?? '';
        
        $data['user_token'] = $this->session->data['user_token'];
        $data['action'] = $this->url->link('pms/payment_requests', 'user_token=' . $this->session->data['user_token']);
        
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        
        $this->response->setOutput($this->load->view('pms/payment_requests', $data));
    }
    
    public function approve(): void {
        $this->load->language('pms/payment_requests');
        
        $json = [];
        
        if (!$this->user->hasPermission('modify', 'pms/payment_requests')) {
            $json['error'] = $this->language->get('error_permission');
        }
        
        if (!$json) {
            $request_id = isset($this->request->get['request_id']) ? (int)$this->request->get['request_id'] : 0;
            
            $this->load->model('pms/subscription_payment_request');
            $request = $this->model_pms_subscription_payment_request->getRequest($request_id);
            
            if (!$request) {
                $json['error'] = 'Request not found';
            } elseif ($request['status'] != 'pending') {
                $json['error'] = 'This request is already processed (Status: ' . ucfirst($request['status']) . ')';
            }
        }
        
        if (!$json) {
            try {
                $this->model_pms_subscription_payment_request->approveRequest($request_id, $this->user->getId());
                
                $json['success'] = 'Payment request approved and subscription activated successfully!';
                $json['redirect'] = $this->url->link('pms/payment_requests', 'user_token=' . $this->session->data['user_token']);
                
            } catch (\Exception $e) {
                $json['error'] = $e->getMessage();
            }
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    
    public function reject(): void {
        $this->load->language('pms/payment_requests');
        
        $json = [];
        
        if (!$this->user->hasPermission('modify', 'pms/payment_requests')) {
            $json['error'] = $this->language->get('error_permission');
        }
        
        if (!$json) {
            $request_id = isset($this->request->get['request_id']) ? (int)$this->request->get['request_id'] : 0;
            $reason = $this->request->post['reason'] ?? '';
            
            $this->load->model('pms/subscription_payment_request');
            $request = $this->model_pms_subscription_payment_request->getRequest($request_id);
            
            if (!$request) {
                $json['error'] = 'Request not found';
            } elseif ($request['status'] != 'pending') {
                $json['error'] = 'This request is already processed (Status: ' . ucfirst($request['status']) . ')';
            }
        }
        
        if (!$json) {
            $this->model_pms_subscription_payment_request->rejectRequest($request_id, $reason);
            
            $json['success'] = 'Payment request rejected.';
            $json['redirect'] = $this->url->link('pms/payment_requests', 'user_token=' . $this->session->data['user_token']);
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    
    public function delete(): void {
        $this->load->language('pms/payment_requests');
        
        $json = [];
        
        if (!$this->user->hasPermission('modify', 'pms/payment_requests')) {
            $json['error'] = $this->language->get('error_permission');
        }
        
        if (!$json) {
            $request_id = isset($this->request->get['request_id']) ? (int)$this->request->get['request_id'] : 0;
            
            $this->load->model('pms/subscription_payment_request');
            $this->model_pms_subscription_payment_request->deleteRequest($request_id);
            
            $json['success'] = 'Payment request deleted.';
            $json['redirect'] = $this->url->link('pms/payment_requests', 'user_token=' . $this->session->data['user_token']);
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    
    public function getStatus(): void {
        $this->load->language('pms/payment_requests');
        
        $json = [];
        
        $request_id = isset($this->request->get['request_id']) ? (int)$this->request->get['request_id'] : 0;
        
        if ($request_id) {
            $this->load->model('pms/subscription_payment_request');
            $request = $this->model_pms_subscription_payment_request->getRequest($request_id);
            
            if ($request) {
                $json['success'] = true;
                $json['status'] = $request['status'];
                $json['status_text'] = ucfirst($request['status']);
                
                switch ($request['status']) {
                    case 'pending':
                        $json['status_class'] = 'warning';
                        $json['status_badge'] = '<span class="badge bg-warning">Pending</span>';
                        break;
                    case 'approved':
                        $json['status_class'] = 'success';
                        $json['status_badge'] = '<span class="badge bg-success">Approved</span>';
                        break;
                    case 'rejected':
                        $json['status_class'] = 'danger';
                        $json['status_badge'] = '<span class="badge bg-danger">Rejected</span>';
                        break;
                    default:
                        $json['status_class'] = 'secondary';
                        $json['status_badge'] = '<span class="badge bg-secondary">' . ucfirst($request['status']) . '</span>';
                }
            } else {
                $json['error'] = 'Request not found';
            }
        } else {
            $json['error'] = 'Invalid request ID';
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
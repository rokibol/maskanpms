<?php
namespace Opencart\Catalog\Controller\Account;

class PmsShareTransfer extends \Opencart\System\Engine\Controller {

    public function index(): void {
        if (!$this->customer->isLogged()) {
            $this->response->redirect($this->url->link('account/login', 'language=' . $this->config->get('config_language')));
        }
        
        $this->load->language('pms/share_transfer');
        $this->document->setTitle($this->language->get('heading_title'));
        
        // Get current project
        $this->load->model('pms/project');
        $project = $this->model_pms_project->getProjectByCustomerId($this->customer->getId());
        
        if (!$project) {
            $this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']));
        }
        
        $data['project_id'] = $project['project_id'];
        $data['project_name'] = $project['name'];

        $data['language'] = $this->config->get('config_language');
        $data['customer_token'] = $this->session->data['customer_token'];
        
        // Filter parameters
        $filter_from = isset($this->request->get['filter_from']) ? $this->request->get['filter_from'] : '';
        $filter_to = isset($this->request->get['filter_to']) ? $this->request->get['filter_to'] : '';
        $filter_date_from = isset($this->request->get['filter_date_from']) ? $this->request->get['filter_date_from'] : '';
        $filter_date_to = isset($this->request->get['filter_date_to']) ? $this->request->get['filter_date_to'] : '';
        
        $this->load->model('pms/shareholder');
        
        // Get transfer history
        $filter_data = [
            'filter_from' => $filter_from,
            'filter_to' => $filter_to,
            'filter_date_from' => $filter_date_from,
            'filter_date_to' => $filter_date_to
        ];
        
        $data['transfers'] = $this->model_pms_shareholder->getTransferHistory($project['project_id'], $filter_data);
        
        // Get shareholders for transfer form
        $data['shareholders'] = $this->model_pms_shareholder->getShareholdersForTransfer($project['project_id']);
        
        // Filter values
        $data['filter_from'] = $filter_from;
        $data['filter_to'] = $filter_to;
        $data['filter_date_from'] = $filter_date_from;
        $data['filter_date_to'] = $filter_date_to;
        
        $data['action'] = $this->url->link('account/pms_share_transfer.save', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        $data['back'] = $this->url->link('account/manager', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        
        $data['customer_token'] = $this->session->data['customer_token'];
        
        $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
        
        $this->response->setOutput($this->load->view('account/pms_share_transfer', $data));
    }
    
    public function save(): void {
        $this->load->language('pms/share_transfer');
        
        $json = [];
        
        if (!$this->customer->isLogged()) {
            $json['error'] = $this->language->get('error_permission');
        }
        
        if (!$json) {
            $this->load->model('pms/shareholder');
            $this->load->model('pms/project');
            
            $project = $this->customer->getProject();
            
            // Validation
            if (empty($this->request->post['from_shareholder_id'])) {
                $json['error'] = 'Please select source shareholder';
            }
            
            if (empty($this->request->post['to_shareholder_id'])) {
                $json['error'] = 'Please select destination shareholder';
            }
            
            if ($this->request->post['from_shareholder_id'] == $this->request->post['to_shareholder_id']) {
                $json['error'] = 'Cannot transfer shares to the same shareholder';
            }
            
            if (empty($this->request->post['units_to_transfer']) || $this->request->post['units_to_transfer'] <= 0) {
                $json['error'] = 'Please enter valid units to transfer';
            }
            
            if (empty($this->request->post['transfer_date'])) {
                $json['error'] = 'Please select transfer date';
            }
        }
        
        if (!$json) {
            $transfer_data = [
                'from_shareholder_id' => (int)$this->request->post['from_shareholder_id'],
                'to_shareholder_id'   => (int)$this->request->post['to_shareholder_id'],
                'units_to_transfer'   => (int)$this->request->post['units_to_transfer'],
                'transfer_date'       => $this->request->post['transfer_date'],
                'reference_number'    => $this->request->post['reference_number'] ?? '',
                'notes'               => $this->request->post['notes'] ?? ''
            ];
            
            $result = $this->model_pms_shareholder->transferShares($transfer_data);
            
            if ($result['success']) {
                $json['success'] = $this->language->get('text_transfer_success');
                $json['redirect'] = $this->url->link('account/pms_share_transfer', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
            } else {
                $json['error'] = implode(', ', $result['errors']);
            }
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    
    /**
     * Get shareholder details for AJAX preview
     */
    public function getShareholderDetails(): void {
        $json = [];
        
        $shareholder_id = isset($this->request->get['shareholder_id']) ? (int)$this->request->get['shareholder_id'] : 0;
        
        if ($shareholder_id) {
            $this->load->model('pms/shareholder');
            $this->load->model('pms/project');
            
            $project = $this->model_pms_project->getProjectByCustomerId($this->customer->getId());
            $shareholder = $this->model_pms_shareholder->getShareholder($shareholder_id);
            
            if ($shareholder) {
                $total_due = $this->model_pms_shareholder->getTotalDue($shareholder_id);
                
                $json['success'] = true;
                $json['shareholder'] = [
                    'name' => $shareholder['firstname'] . ' ' . $shareholder['lastname'],
                    'code' => $shareholder['shareholder_code'],
                    'units' => $shareholder['ownership_units'],
                    'percentage' => round(($shareholder['ownership_units'] / $project['total_shares']) * 100, 2),
                    'total_due' => $total_due
                ];
            } else {
                $json['success'] = false;
            }
        } else {
            $json['success'] = false;
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
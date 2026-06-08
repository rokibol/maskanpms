<?php
namespace Opencart\Catalog\Controller\Account;

class PmsProjectDetails extends \Opencart\System\Engine\Controller {

    public function index(): void {
        if (!$this->customer->isLogged()) {
            $this->response->redirect($this->url->link('account/login', 'language=' . $this->config->get('config_language')));
        }
        
        // Check if user is Manager
        /*$customer_group_id = $this->customer->getGroupId();
        $manager_group_id = (int)$this->config->get('config_pms_manager_group_id');
        
        if ($customer_group_id != $manager_group_id) {
            $this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']));
        }*/
        
        $this->load->language('pms/project_details');
        $this->document->setTitle($this->language->get('heading_title'));
        
        // Get current project
        $this->load->model('pms/project');
        $project = $this->model_pms_project->getProjectByCustomerId($this->customer->getId());
        
        if (!$project) {
            $this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']));
        }
        
        $data['project'] = [
            'project_id'            => $project['project_id'],
            'name'                  => $project['name'],
            'description'           => nl2br($project['description']) ?: '-',
            'status'                => $project['status'],
            'status_text'           => $this->getStatusText($project['status']),
            'completion_percentage' => $project['completion_percentage'],
            'is_active'             => $project['is_active'],
            'theme_color'           => $project['theme_color'],
            'contact_email'         => $project['contact_email'] ?: '-',
            'contact_phone'         => $project['contact_phone'] ?: '-',
            'address'               => nl2br($project['address']) ?: '-',
            'city'                  => $project['city'] ?: '-',
            'country'               => $project['country'] ?: '-',
            'total_shares'          => $project['total_shares'],
            'share_value'           => $project['share_value'] ? number_format($project['share_value'], 2) : '-',
            'registration_date'     => $project['registration_date'] ? date('d M Y', strtotime($project['registration_date'])) : '-',
            'construction_start'    => $project['construction_start_date'] ? date('d M Y', strtotime($project['construction_start_date'])) : '-',
            'construction_end'      => $project['construction_end_date'] ? date('d M Y', strtotime($project['construction_end_date'])) : '-',
            'handover_date'         => $project['handover_date'] ? date('d M Y', strtotime($project['handover_date'])) : '-',
            'created_at'            => date('d M Y', strtotime($project['created_at']))
        ];
        
        // Get project statistics
        $data['stats'] = $this->model_pms_project->getProjectStatisticsForManager($project['project_id']);
        
        // Status options for dropdown
        $data['status_options'] = [
            'planning'          => 'Planning',
            'land_acquisition'  => 'Land Acquisition',
            'construction'      => 'Construction',
            'operational'       => 'Operational',
            'completed'         => 'Completed',
            'suspended'         => 'Suspended'
        ];
        
        $data['action'] = $this->url->link('account/pms_project_details.updateStatus', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        $data['back'] = $this->url->link('account/pms_manager_dashboard', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        
        $data['customer_token'] = $this->session->data['customer_token'];
        
        $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
        
        $this->response->setOutput($this->load->view('account/pms_project_details', $data));
    }
    
    public function updateStatus(): void {
        $this->load->language('pms/project_details');
        
        $json = [];
        
        if (!$this->customer->isLogged()) {
            $json['error'] = $this->language->get('error_permission');
        }
        
        if (!$json) {
            $this->load->model('pms/project');
            $project = $this->model_pms_project->getProjectByCustomerId($this->customer->getId());
            
            if ($project) {
                $new_status = $this->request->post['status'];
                $this->model_pms_project->updateProjectStatus($project['project_id'], $new_status);
                $json['success'] = $this->language->get('text_status_updated');
            } else {
                $json['error'] = 'Project not found';
            }
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    
    private function getStatusText(string $status): string {
        $statuses = [
            'planning' => 'Planning',
            'land_acquisition' => 'Land Acquisition',
            'construction' => 'Construction',
            'operational' => 'Operational',
            'completed' => 'Completed',
            'suspended' => 'Suspended'
        ];
        
        return $statuses[$status] ?? ucfirst($status);
    }
}
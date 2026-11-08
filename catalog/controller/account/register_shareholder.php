<?php
namespace Opencart\Catalog\Controller\Account;

class RegisterShareholder extends \Opencart\System\Engine\Controller {

    public function index(): void {
        $this->load->language('pms/shareholder');
        
        $this->document->setTitle($this->language->get('heading_add'));
        
        // Get current project
        $this->load->model('pms/project');
        
        $project_id = $this->request->get['reference'] ?? 0;

        if($project_id == 0) {
            $this->response->redirect($this->url->link('account/login', 'language=' . $this->config->get('config_language')));
        }
        
        $project = $this->model_pms_project->getProject($project_id);
        
        $data['project_id'] = $project['project_id'];
        $data['project_name'] = $project['name'];
        $data['total_shares'] = $project['total_shares'];
        
        $data['text_form'] = $this->language->get("text_add");
        
        // Form action
        $data['action'] = $this->url->link('account/register_shareholder.save', 'language=' . $this->config->get('config_language'));
        
        // Set form values
        if (isset($this->request->post['firstname'])) {
            $data['firstname'] = $this->request->post['firstname'];
        } else {
            $data['firstname'] = '';
        }
        
        if (isset($this->request->post['lastname'])) {
            $data['lastname'] = $this->request->post['lastname'];
        } else {
            $data['lastname'] = '';
        }
        
        if (isset($this->request->post['phone'])) {
            $data['phone'] = $this->request->post['phone'];
        } else {
            $data['phone'] = '';
        }
        
        if (isset($this->request->post['password'])) {
            $data['password'] = $this->request->post['password'];
        } else {
            $data['password'] = '';
        }
        
        if (isset($this->request->post['ownership_units'])) {
            $data['ownership_units'] = $this->request->post['ownership_units'];
        } else {
            $data['ownership_units'] = '';
        }
        
        if (isset($this->request->post['address'])) {
            $data['address'] = $this->request->post['address'];
        } else {
            $data['address'] = '';
        }
        
        if (isset($this->request->post['nominee_name'])) {
            $data['nominee_name'] = $this->request->post['nominee_name'];
        } else {
            $data['nominee_name'] = '';
        }
        
        if (isset($this->request->post['nominee_relation'])) {
            $data['nominee_relation'] = $this->request->post['nominee_relation'];
        } else {
            $data['nominee_relation'] = '';
        }
        
        if (isset($this->request->post['nominee_phone'])) {
            $data['nominee_phone'] = $this->request->post['nominee_phone'];
        } else {
            $data['nominee_phone'] = '';
        }
        
        if (isset($this->request->post['bank_account_no'])) {
            $data['bank_account_no'] = $this->request->post['bank_account_no'];
        } else {
            $data['bank_account_no'] = '';
        }
        
        if (isset($this->request->post['bank_name'])) {
            $data['bank_name'] = $this->request->post['bank_name'];
        } else {
            $data['bank_name'] = '';
        }
        
        if (isset($this->request->post['tin_number'])) {
            $data['tin_number'] = $this->request->post['tin_number'];
        } else {
            $data['tin_number'] = '';
        }
        
        if (isset($this->request->post['notes'])) {
            $data['notes'] = $this->request->post['notes'];
        } else {
            $data['notes'] = '';
        }
        
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
        
        $this->response->setOutput($this->load->view('account/register_shareholder', $data));
    }
    
    public function save(): void {
        $this->load->language('pms/shareholder');
        
        $json = [];

        $project_id = $this->request->post['project_id'] ?? '';

        if (!$json) {
            $this->load->model('pms/project');

            $project = $this->model_pms_project->getProject($project_id);
            
            $this->load->model('pms/shareholder');
            
            $phone = $this->request->post['phone'] ?? '';
            
            // Validate
            if (empty($this->request->post['firstname']) || strlen($this->request->post['firstname']) < 1) {
                $json['error']['firstname'] = $this->language->get('error_firstname');
            }
            
            if (empty($this->request->post['lastname']) || strlen($this->request->post['lastname']) < 1) {
                $json['error']['lastname'] = $this->language->get('error_lastname');
            }
            
            // Validate phone number (Bangladesh format)
            if (empty($phone) || !preg_match('/^01[3-9]\d{8}$/', preg_replace('/[\s\-\(\)]/', '', $phone))) {
                $json['error']['phone'] = 'Valid mobile number is required (11 digits starting with 01)';
            }
            
            // Generate email from phone number
            $clean_phone = preg_replace('/[\s\-\(\)]/', '', $phone);
            $email = $clean_phone . '@gmail.com';
            
            // Check if email already exists
            $email_check = $this->db->query("
                SELECT COUNT(*) as total 
                FROM `" . DB_PREFIX . "customer` 
                WHERE email = '" . $this->db->escape($email) . "'
                AND customer_id != '" . (isset($this->request->get['shareholder_id']) ? (int)$this->request->get['shareholder_id'] : 0) . "'
            ");
            
            if ($email_check->row['total'] > 0) {
                $json['error']['phone'] = 'This mobile number is already registered!';
            }
            
            if (empty($this->request->post['ownership_units']) || $this->request->post['ownership_units'] < 1) {
                $json['error']['ownership_units'] = $this->language->get('error_ownership_units');
            }
            
            // Password validation for new shareholder
            if (!isset($this->request->get['shareholder_id'])) {
                if (empty($this->request->post['password']) || strlen($this->request->post['password']) < 4) {
                    $json['error']['password'] = $this->language->get('error_password');
                }
            }
        }
        
        if (!$json) {
            // Add generated email to post data
            $this->request->post['email'] = $email;
            $this->request->post['customer_group_id'] = 3;
            $this->model_pms_shareholder->addShareholder($this->request->post);
            $json['success'] = $this->language->get('text_add_success');
            
            $json['redirect'] = $this->url->link('account/login', 'language=' . $this->config->get('config_language'));
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
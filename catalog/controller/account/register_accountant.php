<?php
namespace Opencart\Catalog\Controller\Account;

class RegisterAccountant extends \Opencart\System\Engine\Controller {

    public function index(): void {
        if ($this->customer->isLogged()) {
            $this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language') . '$customer_token=' . $this->session->data['customer_token']));
        }
        
        $this->load->language('pms/customers');
        
        $this->document->setTitle($this->language->get('heading_add'));
        
        // Get current project
        $this->load->model('pms/project');
        $project_id = $this->request->get['reference'] ?? 0;

        if($project_id == 0) {
            $this->response->redirect($this->url->link('account/login', 'language=' . $this->config->get('config_language')));
        }
        
        $project = $this->model_pms_project->getProject($project_id);
        
        if (!$project) {
            $this->response->redirect($this->url->link('account/login', 'language=' . $this->config->get('config_language')));
        }
        
        $data['project_id'] = $project['project_id'];
        $data['project_name'] = $project['name'];
        $data['total_shares'] = $project['total_shares'];
        $data['text_form'] = 'Register accountant';
        $data['button_save'] = 'Save';
        
        // Form action
        $data['action'] = $this->url->link('account/register_accountant.save', 'language=' . $this->config->get('config_language'));
        
        // Set form values
        $fields = ['firstname', 'lastname', 'email', 'telephone', 'password', 'confirm'];
        foreach ($fields as $field) {
            if (isset($this->request->post[$field])) {
                $data[$field] = $this->request->post[$field];
            } else {
                $data[$field] = '';
            }
        }
        
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
        
        $this->response->setOutput($this->load->view('account/register_accountant_form', $data));
    }
    
    public function save(): void {
        $this->load->language('pms/customers');
        
        $json = [];

        $project_id = $this->request->post['project_id'] ?? '';
        
        // Validation
        if (!$json) {
            $this->load->model('account/customer');
            
            // Get current project
            $this->load->model('pms/project');
            $project = $this->model_pms_project->getProject($project_id);
            
            $project_id = $project['project_id'];
            $phone = $this->request->post['telephone'] ?? '';
            
            if (empty($this->request->post['firstname']) || !oc_validate_length((string)$this->request->post['firstname'], 1, 32)) {
                $json['error']['firstname'] = $this->language->get('error_firstname');
            }
            
            if (empty($this->request->post['lastname']) || !oc_validate_length((string)$this->request->post['lastname'], 1, 32)) {
                $json['error']['lastname'] = $this->language->get('error_lastname');
            }
            
            /*if (empty($this->request->post['email']) || !oc_validate_length((string)$this->request->post['email'], 1, 32) || !filter_var($this->request->post['email'], FILTER_VALIDATE_EMAIL)) {
                $json['error']['email'] = $this->language->get('error_email');
            }*/
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
                AND customer_id != '" . (isset($this->request->get['customer_id']) ? (int)$this->request->get['customer_id'] : 0) . "'
            ");
            
            if ($email_check->row['total'] > 0) {
                $json['error']['phone'] = 'This mobile number is already registered!';
            }
            
            // Password validation for new customer
            if (!isset($this->request->get['customer_id'])) {
                if (empty($this->request->post['password']) || oc_validate_length((string)$this->request->post['firstname'], 1, 4)) {
                    $json['error']['password'] = $this->language->get('error_password');
                }
                
                if ($this->request->post['password'] != $this->request->post['confirm']) {
                    $json['error']['confirm'] = $this->language->get('error_confirm');
                }
            } else {
                if (!empty($this->request->post['password'])) {
                    if ( oc_validate_length((string)$this->request->post['firstname'], 1, 4)) {
                        $json['error']['password'] = $this->language->get('error_password');
                    }
                    
                    if ($this->request->post['password'] != $this->request->post['confirm']) {
                        $json['error']['confirm'] = $this->language->get('error_confirm');
                    }
                }
            }
        }
        
        if (!$json) {
            $customer_data = [
                'firstname' => $this->request->post['firstname'],
                'lastname'  => $this->request->post['lastname'],
                'email'     => $this->request->post['email'],
                'telephone' => $this->request->post['telephone'],
                'customer_group_id' => 2, // Accountant group
                'status'    => 1,
                'approved'  => 1,
                'project_id' => $project_id
            ];
            
            $myfile = fopen("customer_account.txt", "a+");
            fwrite($myfile, print_r($customer_data, true) . PHP_EOL);
            fclose($myfile);

            if (!empty($this->request->post['password'])) {
                $customer_data['password'] = $this->request->post['password'];
            }
            
            // Add new customer
            $customer_id = $this->model_account_customer->addCustomer($customer_data);
            $json['success'] = $this->language->get('text_add_success');
            
            
            $json['redirect'] = $this->url->link('account/login', 'language=' . $this->config->get('config_language'));
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    
}
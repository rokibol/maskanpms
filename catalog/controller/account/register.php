<?php
namespace Opencart\Catalog\Controller\Account;

class Register extends \Opencart\System\Engine\Controller {

    public function index(): void {
        $this->load->language('account/register');
        $this->load->language('pms/register_project');

        $this->document->setTitle($this->language->get('heading_title'));

        $data['breadcrumbs'] = [];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home', 'language=' . $this->config->get('config_language'))
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_account'),
            'href' => $this->url->link('account/account', 'language=' . $this->config->get('config_language'))
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_register'),
            'href' => $this->url->link('account/register', 'language=' . $this->config->get('config_language'))
        ];

        $this->load->model('account/customer');
        $this->load->model('account/customer_group');

        // ======================================================
        // FETCH INFORMATION PAGES FOR TERMS & PRIVACY POLICY
        // ======================================================
        $this->load->model('catalog/information');
        
        $terms_info = $this->model_catalog_information->getInformation(1);
        if ($terms_info) {
            $data['terms_title'] = $terms_info['title'];
            $data['terms_description'] = html_entity_decode($terms_info['description'], ENT_QUOTES, 'UTF-8');
        } else {
            $data['terms_title'] = 'Terms & Conditions';
            $data['terms_description'] = '';
        }
        
        $privacy_info = $this->model_catalog_information->getInformation(2);
        if ($privacy_info) {
            $data['privacy_title'] = $privacy_info['title'];
            $data['privacy_description'] = html_entity_decode($privacy_info['description'], ENT_QUOTES, 'UTF-8');
        } else {
            $data['privacy_title'] = 'Privacy Policy';
            $data['privacy_description'] = '';
        }

        // ======================================================
        // PERSONAL INFORMATION FIELDS
        // ======================================================
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

        if (isset($this->request->post['telephone'])) {
            $data['telephone'] = $this->request->post['telephone'];
        } else {
            $data['telephone'] = '';
        }

        if (isset($this->request->post['password'])) {
            $data['password'] = $this->request->post['password'];
        } else {
            $data['password'] = '';
        }

        if (isset($this->request->post['confirm'])) {
            $data['confirm'] = $this->request->post['confirm'];
        } else {
            $data['confirm'] = '';
        }

        // ======================================================
        // PROJECT INFORMATION FIELDS
        // ======================================================
        if (isset($this->request->post['project_name'])) {
            $data['project_name'] = $this->request->post['project_name'];
        } else {
            $data['project_name'] = '';
        }

        if (isset($this->request->post['project_description'])) {
            $data['project_description'] = $this->request->post['project_description'];
        } else {
            $data['project_description'] = '';
        }

        if (isset($this->request->post['project_address'])) {
            $data['project_address'] = $this->request->post['project_address'];
        } else {
            $data['project_address'] = '';
        }

        if (isset($this->request->post['project_city'])) {
            $data['project_city'] = $this->request->post['project_city'];
        } else {
            $data['project_city'] = '';
        }

        if (isset($this->request->post['project_country'])) {
            $data['project_country'] = $this->request->post['project_country'];
        } else {
            $data['project_country'] = '';
        }

        if (isset($this->request->post['project_total_shares'])) {
            $data['project_total_shares'] = $this->request->post['project_total_shares'];
        } else {
            $data['project_total_shares'] = '';
        }

        // Trial Information
        $this->load->model('pms/subscription_settings');
        $trial_days = $this->model_pms_subscription_settings->getSetting('trial_days') ?: 30;
        $data['trial_days'] = $trial_days;
        $data['trial_end_date'] = date('d F Y', strtotime('+' . $trial_days . ' days'));

        // Agree to terms
        if (isset($this->request->post['agree'])) {
            $data['agree'] = $this->request->post['agree'];
        } else {
            $data['agree'] = false;
        }

        $data['action'] = $this->url->link('account/register.save', 'language=' . $this->config->get('config_language'));

        $data['header'] = $this->load->controller('common/header');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('account/register_project', $data));
    }

    public function save(): void {
        $this->load->language('account/register');
        $this->load->language('pms/register_project');
        $this->load->model('pms/subscription_settings');

        $json = [];

        // ======================================================
        // VALIDATE PERSONAL INFORMATION
        // ======================================================
        if ((strlen($this->request->post['firstname']) < 1) || (strlen($this->request->post['firstname']) > 32)) {
            $json['error']['firstname'] = $this->language->get('error_firstname');
        }

        if ((strlen($this->request->post['lastname']) < 1) || (strlen($this->request->post['lastname']) > 32)) {
            $json['error']['lastname'] = $this->language->get('error_lastname');
        }

        // Validate phone number (Bangladesh format)
        $telephone = preg_replace('/[\s\-\(\)]/', '', $this->request->post['telephone']);
        
        if (empty($telephone) || !preg_match('/^01[3-9]\d{8}$/', $telephone)) {
            $json['error']['telephone'] = 'Valid mobile number is required (11 digits starting with 01)';
        }

        // Generate email from phone number
        $email = $telephone . '@gmail.com';

        // Check if phone/email already exists
        $this->load->model('account/customer');
        $email_check = $this->db->query("
            SELECT COUNT(*) as total 
            FROM `" . DB_PREFIX . "customer` 
            WHERE email = '" . $this->db->escape($email) . "'
        ");

        if ($email_check->row['total'] > 0) {
            $json['error']['telephone'] = 'This mobile number is already registered!';
        }

        if ((strlen($this->request->post['password']) < 4) || (strlen($this->request->post['password']) > 40)) {
            $json['error']['password'] = $this->language->get('error_password');
        }

        if ($this->request->post['confirm'] != $this->request->post['password']) {
            $json['error']['confirm'] = $this->language->get('error_confirm');
        }

        // ======================================================
        // VALIDATE PROJECT INFORMATION
        // ======================================================
        if (empty($this->request->post['project_name']) || (strlen($this->request->post['project_name']) < 3) || (strlen($this->request->post['project_name']) > 100)) {
            $json['error']['project_name'] = $this->language->get('error_project_name');
        }

        if (empty($this->request->post['project_total_shares']) || (int)$this->request->post['project_total_shares'] < 1) {
            $json['error']['project_total_shares'] = $this->language->get('error_project_total_shares');
        }

        if (!isset($this->request->post['agree'])) {
            $json['error']['agree'] = $this->language->get('error_agree');
        }

        if (!$json) {
            $this->db->query("START TRANSACTION");

            try {
                // ======================================================
                // 1. CREATE CUSTOMER (MANAGER)
                // ======================================================
                $customer_data = [
                    'firstname'         => $this->request->post['firstname'],
                    'lastname'          => $this->request->post['lastname'],
                    'email'             => $email,
                    'telephone'         => $telephone,
                    'password'          => password_hash($this->request->post['password'], PASSWORD_DEFAULT),
                    'customer_group_id' => 1, // Manager group
                    'status'            => 1,
                    'newsletter'        => 0,
                    'safe'              => 1
                ];

                $this->db->query("
                    INSERT INTO `" . DB_PREFIX . "customer` 
                    SET `firstname` = '" . $this->db->escape($customer_data['firstname']) . "',
                        `lastname` = '" . $this->db->escape($customer_data['lastname']) . "',
                        `email` = '" . $this->db->escape($customer_data['email']) . "',
                        `telephone` = '" . $this->db->escape($customer_data['telephone']) . "',
                        `password` = '" . $this->db->escape($customer_data['password']) . "',
                        `customer_group_id` = '" . (int)$customer_data['customer_group_id'] . "',
                        `status` = '" . (int)$customer_data['status'] . "',
                        `newsletter` = '" . (int)$customer_data['newsletter'] . "',
                        `safe` = '" . (int)$customer_data['safe'] . "',
                        `date_added` = NOW()
                ");

                $customer_id = $this->db->getLastId();

                // ======================================================
                // 2. CREATE PROJECT (WITHOUT SLUG)
                // ======================================================
                $project_data = [
                    'name'              => $this->request->post['project_name'],
                    'description'       => $this->request->post['project_description'],
                    'address'           => $this->request->post['project_address'],
                    'city'              => $this->request->post['project_city'],
                    'country'           => $this->request->post['project_country'],
                    'contact_email'     => $email,
                    'contact_phone'     => $telephone,
                    'customer_id'        => $customer_id,
                    'total_shares'      => (int)$this->request->post['project_total_shares'],
                    'status'            => 'planning',
                    'is_active'         => 1,
                    'theme_color'       => '#0B3C5D',
                    'store_id'          => 0,
                    'registration_date' => date('Y-m-d'),
                    'subscription_rate_per_apartment' => 50.00,
                    'subscription_discount_type' => 'none',
                    'subscription_discount_value' => 0,
                    'subscription_discount_reason' => ''
                ];

                $this->db->query("
                    INSERT INTO `" . DB_PREFIX . "pms_project` 
                    SET `name` = '" . $this->db->escape($project_data['name']) . "',
                        `description` = '" . $this->db->escape($project_data['description']) . "',
                        `address` = '" . $this->db->escape($project_data['address']) . "',
                        `city` = '" . $this->db->escape($project_data['city']) . "',
                        `country` = '" . $this->db->escape($project_data['country']) . "',
                        `contact_email` = '" . $this->db->escape($project_data['contact_email']) . "',
                        `contact_phone` = '" . $this->db->escape($project_data['contact_phone']) . "',
                        `total_shares` = '" . (int)$project_data['total_shares'] . "',
                        `status` = '" . $this->db->escape($project_data['status']) . "',
                        `is_active` = '" . (int)$project_data['is_active'] . "',
                        `theme_color` = '" . $this->db->escape($project_data['theme_color']) . "',
                        `store_id` = '" . (int)$project_data['store_id'] . "',
                        `registration_date` = '" . $this->db->escape($project_data['registration_date']) . "',
                        `subscription_rate_per_apartment` = '" . (float)$project_data['subscription_rate_per_apartment'] . "',
                        `subscription_discount_type` = '" . $this->db->escape($project_data['subscription_discount_type']) . "',
                        `subscription_discount_value` = '" . (float)$project_data['subscription_discount_value'] . "',
                        `subscription_discount_reason` = '" . $this->db->escape($project_data['subscription_discount_reason']) . "',
                        `created_at` = NOW()
                ");

                $project_id = $this->db->getLastId();

                // ======================================================
                // 3. UPDATE CUSTOMER WITH PROJECT_ID
                // ======================================================
                $this->db->query("
                    UPDATE `" . DB_PREFIX . "customer` 
                    SET `project_id` = '" . (int)$project_id . "'
                    WHERE `customer_id` = '" . (int)$customer_id . "'
                ");

                // ======================================================
                // 4. CREATE TRIAL SUBSCRIPTION
                // ======================================================
                $trial_days = $this->model_pms_subscription_settings->getSetting('trial_days') ?: 30;
                $start_date = date('Y-m-d');
                $end_date = date('Y-m-d', strtotime('+' . $trial_days . ' days'));

                $this->db->query("
                    INSERT INTO `" . DB_PREFIX . "pms_project_subscription` 
                    SET `project_id` = '" . (int)$project_id . "',
                        `duration_months` = 1,
                        `total_amount` = 0,
                        `start_date` = '" . $this->db->escape($start_date) . "',
                        `end_date` = '" . $this->db->escape($end_date) . "',
                        `status` = 'trial',
                        `admin_override` = 0
                ");

                $this->db->query("COMMIT");

                // Auto login
                //$this->customer->login($email, $this->request->post['password']);

                $json['success'] = $this->language->get('text_register_success');
                if ($this->customer->isLogged()) {
                    $json['redirect'] = $this->url->link('account/account', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token'], true);
                } else {
                    $json['redirect'] = $this->url->link('account/login', 'language=' . $this->config->get('config_language'));
                }

            } catch (\Exception $e) {
                $this->db->query("ROLLBACK");
                $json['error']['warning'] = $e->getMessage();
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
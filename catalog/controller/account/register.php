<?php
namespace Opencart\Catalog\Controller\Account;

class Register extends \Opencart\System\Engine\Controller {

    public function index(): void {
        $this->load->language('account/register');
        $this->load->language('pms/register_project');

        $this->document->setTitle($this->language->get('heading_title'));

        $data['breadcrumbs'] = [];

		// ======================================================
		// FETCH INFORMATION PAGES FOR TERMS & PRIVACY POLICY
		// ======================================================
		$this->load->model('catalog/information');
		
		// Get Terms & Conditions (ID 1)
		$terms_info = $this->model_catalog_information->getInformation(1);
		if ($terms_info) {
			$data['terms_title'] = $terms_info['title'];
			$data['terms_description'] = html_entity_decode($terms_info['description'], ENT_QUOTES, 'UTF-8');
		} else {
			$data['terms_title'] = 'Terms & Conditions';
			$data['terms_description'] = 'Terms and conditions content goes here.';
		}
		
		// Get Privacy Policy (ID 2)
		$privacy_info = $this->model_catalog_information->getInformation(2);
		if ($privacy_info) {
			$data['privacy_title'] = $privacy_info['title'];
			$data['privacy_description'] = html_entity_decode($privacy_info['description'], ENT_QUOTES, 'UTF-8');
		} else {
			$data['privacy_title'] = 'Privacy Policy';
			$data['privacy_description'] = 'Privacy policy content goes here.';
		}

        $this->load->model('account/customer');
        $this->load->model('account/customer_group');

        // Customer Groups
        $data['customer_groups'] = $this->model_account_customer_group->getCustomerGroups();

        if (isset($this->request->post['customer_group_id'])) {
            $data['customer_group_id'] = (int)$this->request->post['customer_group_id'];
        } else {
            $data['customer_group_id'] = (int)$this->config->get('config_customer_group_id');
        }

        // Countries
        $this->load->model('localisation/country');
        $data['countries'] = $this->model_localisation_country->getCountries();

        // Account Fields
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

        if (isset($this->request->post['email'])) {
            $data['email'] = $this->request->post['email'];
        } else {
            $data['email'] = '';
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
        // PROJECT REGISTRATION FIELDS
        // ======================================================

        // Project Name
        if (isset($this->request->post['project_name'])) {
            $data['project_name'] = $this->request->post['project_name'];
        } else {
            $data['project_name'] = '';
        }

        // Project Description
        if (isset($this->request->post['project_description'])) {
            $data['project_description'] = $this->request->post['project_description'];
        } else {
            $data['project_description'] = '';
        }

        // Project Address
        if (isset($this->request->post['project_address'])) {
            $data['project_address'] = $this->request->post['project_address'];
        } else {
            $data['project_address'] = '';
        }

        // Project City
        if (isset($this->request->post['project_city'])) {
            $data['project_city'] = $this->request->post['project_city'];
        } else {
            $data['project_city'] = '';
        }

        // Project Country
        if (isset($this->request->post['project_country'])) {
            $data['project_country'] = $this->request->post['project_country'];
        } else {
            $data['project_country'] = '';
        }

        // Project Total Shares
        if (isset($this->request->post['project_total_shares'])) {
            $data['project_total_shares'] = $this->request->post['project_total_shares'];
        } else {
            $data['project_total_shares'] = '';
        }

        // Trial Information
        /*$this->load->model('pms/subscription_settings');*/
        $trial_days = 30;
        $data['trial_days'] = $trial_days;
        $data['trial_end_date'] = date('d F Y', strtotime('+' . $trial_days . ' days'));

        // Agree to terms
        if (isset($this->request->post['agree'])) {
            $data['agree'] = $this->request->post['agree'];
        } else {
            $data['agree'] = false;
        }

        $data['action'] = $this->url->link('account/register.save', 'language=' . $this->config->get('config_language'));

        $this->load->model('localisation/country');
        $data['countries'] = $this->model_localisation_country->getCountries();

        $data['header'] = $this->load->controller('common/header');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('account/register_project', $data));
    }

    public function save(): void {
        $this->load->language('account/register');
        $this->load->language('pms/register_project');

        $json = [];

        // Validate customer fields
        if ((strlen($this->request->post['firstname']) < 1) || (strlen($this->request->post['firstname']) > 32)) {
            $json['error']['firstname'] = $this->language->get('error_firstname');
        }

        if ((strlen($this->request->post['lastname']) < 1) || (strlen($this->request->post['lastname']) > 32)) {
            $json['error']['lastname'] = $this->language->get('error_lastname');
        }

        if ((strlen($this->request->post['email']) > 96) || !filter_var($this->request->post['email'], FILTER_VALIDATE_EMAIL)) {
            $json['error']['email'] = $this->language->get('error_email');
        }

        $this->load->model('account/customer');

        if ($this->model_account_customer->getTotalCustomersByEmail($this->request->post['email'])) {
            $json['error']['email'] = $this->language->get('error_exists');
        }

        if ((strlen($this->request->post['telephone']) < 3) || (strlen($this->request->post['telephone']) > 32)) {
            $json['error']['telephone'] = $this->language->get('error_telephone');
        }

        if ((strlen($this->request->post['password']) < 4) || (strlen($this->request->post['password']) > 40)) {
            $json['error']['password'] = $this->language->get('error_password');
        }

        if ($this->request->post['confirm'] != $this->request->post['password']) {
            $json['error']['confirm'] = $this->language->get('error_confirm');
        }

        // ======================================================
        // PROJECT VALIDATION
        // ======================================================

        if (empty($this->request->post['project_name']) || (strlen($this->request->post['project_name']) < 3) || (strlen($this->request->post['project_name']) > 100)) {
            $json['error']['project_name'] = $this->language->get('error_project_name');
        }

        $this->load->model('pms/project');
        
        if (empty($this->request->post['project_total_shares']) || (int)$this->request->post['project_total_shares'] < 1) {
            $json['error']['project_total_shares'] = $this->language->get('error_project_total_shares');
        }

        if (!isset($this->request->post['agree'])) {
            $json['error']['agree'] = $this->language->get('error_agree');
        }

        if (!$json) {
            // Start transaction
            $this->db->query("START TRANSACTION");

            try {
                // 1. Create Customer (Manager)
                $customer_data = [
                    'firstname'         => $this->request->post['firstname'],
                    'lastname'          => $this->request->post['lastname'],
                    'email'             => $this->request->post['email'],
                    'telephone'         => $this->request->post['telephone'],
                    'password'          => password_hash($this->request->post['password'], PASSWORD_DEFAULT),
                    'customer_group_id' => 1, // Manager group
                    'status'            => 1,
                    'newsletter'        => 0,
                    'safe'              => 1
                ];

                // 2. Create Project
                $project_data = [
                    'name'              => $this->request->post['project_name'],
                    'description'       => $this->request->post['project_description'],
                    'address'           => $this->request->post['project_address'],
                    'city'              => $this->request->post['project_city'],
                    'country'           => $this->request->post['project_country'],
                    'contact_email'     => $this->request->post['email'],
                    'contact_phone'     => $this->request->post['telephone'],
                    'total_shares'      => (int)$this->request->post['project_total_shares'],
                    'status'            => 'planning',
                    'is_active'         => 1,
                    'theme_color'       => '#0B3C5D',
                    'store_id'          => 0,
                    'registration_date' => date('Y-m-d')
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
                        `created_at` = NOW()
                ");

                $project_id = $this->db->getLastId();

                // 3. Update customer with project_id
                $this->db->query("
                    INSERT INTO `" . DB_PREFIX . "customer` 
                    SET `firstname` = '" . $this->db->escape($customer_data['firstname']) . "',
                        `lastname` = '" . $this->db->escape($customer_data['lastname']) . "',
                        `project_id` = '" . $project_id . "',
                        `email` = '" . $this->db->escape($customer_data['email']) . "',
                        `telephone` = '" . $this->db->escape($customer_data['telephone']) . "',
                        `password` = '" . $this->db->escape($customer_data['password']) . "',
                        `customer_group_id` = '" . (int)$customer_data['customer_group_id'] . "',
                        `status` = '" . (int)$customer_data['status'] . "',
                        `newsletter` = '" . (int)$customer_data['newsletter'] . "',
                        `safe` = '" . (int)$customer_data['safe'] . "',
                        `date_added` = NOW()
                ");

                // 4. Create Trial Subscription
                $trial_days = 30;
                $start_date = date('Y-m-d');
                $end_date = date('Y-m-d', strtotime('+' . $trial_days . ' days'));

                $this->db->query("
                    INSERT INTO `" . DB_PREFIX . "pms_project_subscription` 
                    SET `project_id` = '" . (int)$project_id . "',
                        `duration_months` = 1,
                        `total_amount` = 0,
                        `start_date` = '" . $this->db->escape($start_date) . "',
                        `end_date` = '" . $this->db->escape($end_date) . "',
                        `status` = 'active',
                        `admin_override` = 0
                ");

                $this->db->query("COMMIT");

                // Auto login the user
                if($this->customer->login($this->request->post['email'], $this->request->post['password'])) {

					$this->session->data['customer_token'] = oc_token(26);

					$json['success'] = $this->language->get('text_register_success');
					$json['redirect'] = $this->url->link('account/manager', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
				} else {
					$json['status'] = false;
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
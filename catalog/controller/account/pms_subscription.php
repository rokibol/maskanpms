<?php
namespace Opencart\Catalog\Controller\Account;

class PmsSubscription extends \Opencart\System\Engine\Controller {

    public function index(): void {
        if (!$this->customer->isLogged()) {
            $this->response->redirect($this->url->link('account/login', 'language=' . $this->config->get('config_language')));
        }

        $this->load->language('pms/subscription');
        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('pms/settings');
        $settings = $this->model_pms_settings->getAll();

        $this->load->model('pms/project');
        $project = $this->customer->getProject();

        if (!$project) {
            $this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language')));
        }

        $this->load->model('pms/project_subscription');

        $data['project_id'] = $project['project_id'];
        $data['project_name'] = $project['name'];
        $data['subscription'] = $this->model_pms_project_subscription->getSubscriptionByProject($project['project_id']);
        $data['is_active'] = $this->model_pms_project_subscription->isSubscriptionActiveOptimized($project['project_id']);
        $data['expiry_days'] = $this->model_pms_project_subscription->getExpiryDays($project['project_id']);

        // Get project-specific calculation
        $data['calculation'] = $this->model_pms_project_subscription->getSubscriptionCalculationDisplay($project['project_id']);
        $data['apartment_count'] = $data['calculation']['apartment_count'];

        // Duration options
        $data['duration_options'] = [
            1 => '1 Month',
            3 => '3 Months',
            6 => '6 Months',
            12 => '12 Months (1 Year)',
            24 => '24 Months (2 Years)'
        ];

        // Calculate amounts for each duration
        $data['duration_amounts'] = [];
        foreach ($data['duration_options'] as $months => $label) {
            $calculation = $this->model_pms_project_subscription->calculateSubscriptionAmount($project['project_id'], $months);
            $data['duration_amounts'][$months] = $calculation['final_amount'];
        }

        $data['action'] = $this->url->link('account/pms_subscription.activate', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        $data['back'] = $this->url->link('account/manager', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);

        $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

        $this->response->setOutput($this->load->view('account/pms_subscription', $data));
    }

    /*public function activate(): void {
        $this->load->language('pms/subscription');

        $json = [];

        if (!$this->customer->isLogged()) {
            $json['error'] = $this->language->get('error_permission');
        }

        if (!$json) {
            $this->load->model('pms/project');
            $project = $this->model_pms_project->getProjectByCustomerId($this->customer->getId());

            if ($project) {
                $this->load->model('pms/project_subscription');

                $duration_months = (int)$this->request->post['duration_months'];

                if ($duration_months <= 0) {
                    $json['error'] = 'Invalid duration selected';
                } else {
                    $result = $this->model_pms_project_subscription->activateSubscription($project['project_id'], $duration_months);

                    if ($result['success']) {
                        // Clear subscription cache
                        unset($this->session->data['pms_cache']);
                        unset($this->session->data['pms_model_cache']);
                        
                        // Clear specific subscription cache
                        $this->session->data['pms_cache']['pms_sub_active_' . $project['project_id']] = [
                            'status' => true,
                            'expires' => time() + 3600
                        ];
                        
                        $json['success'] = $this->language->get('text_activation_success');
                        $json['total_amount'] = $result['calculation']['final_amount'];
                        $json['end_date'] = date('d M Y', strtotime($result['end_date']));
                        $json['apartment_count'] = $result['apartment_count'];
                        $json['per_month'] = number_format($result['calculation']['monthly_after_discount'], 2);
                        $json['redirect'] = $this->url->link('account/pms_subscription', 'language=' . $this->config->get('config_language'));
                    } else {
                        $json['error'] = $result['message'];
                    }
                }
            } else {
                $json['error'] = 'Project not found';
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }*/

    public function activate(): void {
        $this->load->language('pms/subscription');

        $json = [];

        if (!$this->customer->isLogged()) {
            $json['error'] = $this->language->get('error_permission');
        }

        if (!$json) {
            $this->load->model('pms/project');
            $project = $this->model_pms_project->getProjectByCustomerId($this->customer->getId());

            if ($project) {
                $duration_months = (int)$this->request->post['duration_months'];

                if ($duration_months <= 0) {
                    $json['error'] = 'Invalid duration selected';
                } else {
                    // Redirect to payment request form instead of direct activation
                    $json['success'] = true;
                    $json['redirect'] = $this->url->link('account/pms_subscription_payment_request', 'language=' . $this->config->get('config_language') . '&duration=' . $duration_months);
                }
            } else {
                $json['error'] = 'Project not found';
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Get project subscription details for AJAX
     */
    public function getSubscriptionDetails(): void {
        $json = [];

        $project_id = (int)$this->request->get['project_id'];

        if ($project_id) {
            $this->load->model('pms/project_subscription');
            $calculation = $this->model_pms_project_subscription->getSubscriptionCalculationDisplay($project_id);
            
            $json['success'] = true;
            $json['apartment_count'] = $calculation['apartment_count'];
            $json['rate_per_apartment'] = $calculation['rate_per_apartment'];
            $json['base_monthly'] = $calculation['base_monthly'];
            $json['final_monthly'] = $calculation['final_monthly'];
            $json['discount_amount'] = $calculation['discount_amount'];
            $json['discount_reason'] = $calculation['discount_reason'];
            $json['has_discount'] = $calculation['has_discount'];
        } else {
            $json['success'] = false;
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
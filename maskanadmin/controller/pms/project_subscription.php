<?php
namespace Opencart\Admin\Controller\Pms;

class ProjectSubscription extends \Opencart\System\Engine\Controller {

    public function index(): void {
        $this->load->language('pms/project_subscription');
        
        $project_id = isset($this->request->get['project_id']) ? (int)$this->request->get['project_id'] : 0;
        
        $this->load->model('pms/project');
        $project_info = $this->model_pms_project->getProject($project_id);

        $this->document->addStyle('view/stylesheet/project_subscription.css');
        
        if (!$project_info) {
            $this->response->redirect($this->url->link('pms/project', 'user_token=' . $this->session->data['user_token']));
        }
        
        $this->document->setTitle($this->language->get('heading_title') . ' - ' . $project_info['name']);
        
        $url = '';
        if (isset($this->request->get['filter_name'])) {
            $url .= '&filter_name=' . urlencode($this->request->get['filter_name']);
        }
        if (isset($this->request->get['filter_status'])) {
            $url .= '&filter_status=' . $this->request->get['filter_status'];
        }
        if (isset($this->request->get['filter_is_active'])) {
            $url .= '&filter_is_active=' . $this->request->get['filter_is_active'];
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
        
        $data['breadcrumbs'] = [];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
        ];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_projects'),
            'href' => $this->url->link('pms/project', 'user_token=' . $this->session->data['user_token'] . $url)
        ];
        $data['breadcrumbs'][] = [
            'text' => $project_info['name'],
            'href' => $this->url->link('pms/project.view', 'user_token=' . $this->session->data['user_token'] . '&project_id=' . $project_id)
        ];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('pms/project_subscription', 'user_token=' . $this->session->data['user_token'] . '&project_id=' . $project_id)
        ];
        
        $this->load->model('pms/subscription_settings');
        $this->load->model('pms/project');
        
        // Get monthly rate
        $data['monthly_rate'] = (int)$this->model_pms_subscription_settings->getSetting('monthly_rate');
        
        // Get subscription history
        $subscriptions = $this->db->query("
            SELECT s.*, 
                   CASE 
                       WHEN s.admin_override = 1 AND s.override_until > NOW() THEN 'override'
                       WHEN s.status = 'active' AND s.end_date > NOW() THEN 'active'
                       ELSE s.status
                   END as current_status,
                   DATEDIFF(s.end_date, NOW()) as days_remaining
            FROM `" . DB_PREFIX . "pms_project_subscription` s
            WHERE s.project_id = '" . (int)$project_id . "'
            ORDER BY s.created_at DESC
        ");
        
        $data['subscriptions'] = [];
        foreach ($subscriptions->rows as $sub) {
            if ($sub['admin_override'] && strtotime($sub['override_until']) > time()) {
                $status_class = 'info';
                $status_text = 'Admin Override';
            } elseif ($sub['status'] == 'active' && strtotime($sub['end_date']) > time()) {
                $status_class = 'success';
                $status_text = 'Active';
            } else {
                $status_class = 'danger';
                $status_text = 'Expired';
            }
            
            $data['subscriptions'][] = [
                'subscription_id' => $sub['subscription_id'],
                'duration_months' => $sub['duration_months'],
                'total_amount' => $sub['total_amount'],
                'start_date' => date('d M Y', strtotime($sub['start_date'])),
                'end_date' => date('d M Y', strtotime($sub['end_date'])),
                'status' => $sub['status'],
                'status_class' => $status_class,
                'status_text' => $status_text,
                'admin_override' => $sub['admin_override'],
                'override_until' => $sub['override_until'] ? date('d M Y', strtotime($sub['override_until'])) : '-',
                'override_reason' => $sub['override_reason'] ?: '-',
                'created_at' => date('d M Y H:i', strtotime($sub['created_at']))
            ];
        }

        // Get current active subscription (if any)
        $current = $this->db->query("
            SELECT * FROM `" . DB_PREFIX . "pms_project_subscription`
            WHERE project_id = '" . (int)$project_id . "'
            AND status = 'active'
            ORDER BY subscription_id DESC
            LIMIT 1
        ");
        
        $data['current_subscription'] = $current->row;
        $data['project'] = $project_info;
        $data['project_id'] = $project_id;
        
        // Duration options for new subscription
        $data['duration_options'] = [
            1 => '1 Month',
            3 => '3 Months',
            6 => '6 Months',
            12 => '12 Months (1 Year)',
            24 => '24 Months (2 Years)'
        ];
        
        $data['action_add'] = $this->url->link('pms/project_subscription.add', 'user_token=' . $this->session->data['user_token'] . '&project_id=' . $project_id);
        $data['action_override'] = $this->url->link('pms/project_subscription.addOverride', 'user_token=' . $this->session->data['user_token'] . '&project_id=' . $project_id);
        $data['action_remove'] = $this->url->link('pms/project_subscription.removeOverride', 'user_token=' . $this->session->data['user_token'] . '&project_id=' . $project_id);
        $data['back'] = $this->url->link('pms/project', 'user_token=' . $this->session->data['user_token'] . $url);
        
        $data['user_token'] = $this->session->data['user_token'];
        
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        
        $this->response->setOutput($this->load->view('pms/project_subscription', $data));
    }
    
    public function add(): void {
        $this->load->language('pms/project_subscription');
        
        $json = [];
        
        if (!$this->user->hasPermission('modify', 'pms/project_subscription')) {
            $json['error'] = $this->language->get('error_permission');
        }
        
        if (!$json) {
            $project_id = (int)$this->request->get['project_id'];
            $duration_months = (int)$this->request->post['duration_months'];
            
            $this->load->model('pms/subscription_settings');
            $monthly_rate = $this->model_pms_subscription_settings->getSetting('monthly_rate');
            $total_amount = $duration_months * $monthly_rate;
            $start_date = date('Y-m-d');
            $end_date = date('Y-m-d', strtotime('+' . $duration_months . ' months'));
            
            // Check for existing active subscription
            $existing = $this->db->query("
                SELECT * FROM `" . DB_PREFIX . "pms_project_subscription`
                WHERE project_id = '" . (int)$project_id . "'
                AND status = 'active'
                ORDER BY subscription_id DESC
                LIMIT 1
            ");
            
            if ($existing->num_rows > 0) {
                // Extend from existing end date
                $current_end = $existing->row['end_date'];
                $new_end_date = date('Y-m-d', strtotime($current_end . ' + ' . $duration_months . ' months'));
                $end_date = $new_end_date;
            }
            
            $this->db->query("
                INSERT INTO `" . DB_PREFIX . "pms_project_subscription` 
                SET `project_id` = '" . (int)$project_id . "',
                    `duration_months` = '" . (int)$duration_months . "',
                    `total_amount` = '" . (float)$total_amount . "',
                    `start_date` = '" . $this->db->escape($start_date) . "',
                    `end_date` = '" . $this->db->escape($end_date) . "',
                    `status` = 'active'
            ");
            
            // If extending, mark old as superseded
            if ($existing->num_rows > 0) {
                $this->db->query("
                    UPDATE `" . DB_PREFIX . "pms_project_subscription` 
                    SET `status` = 'superseded' 
                    WHERE `subscription_id` = '" . (int)$existing->row['subscription_id'] . "'
                ");
            }

            $this->model_pms_subscription_settings->clearCache($project_id);

            $json['success'] = sprintf('Subscription activated! Expires on %s', date('d M Y', strtotime($end_date)));
            $json['redirect'] = $this->url->link('pms/project_subscription', 'user_token=' . $this->session->data['user_token'] . '&project_id=' . $project_id, true);
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    
    public function addOverride(): void {
        $this->load->language('pms/project_subscription');
        $this->load->model('pms/subscription_settings');

        $json = [];
        
        if (!$this->user->hasPermission('modify', 'pms/project_subscription')) {
            $json['error'] = $this->language->get('error_permission');
        }
        
        if (!$json) {
            $project_id = (int)$this->request->get['project_id'];
            $months = (int)$this->request->post['override_months'];
            $reason = $this->request->post['override_reason'];
            
            // Get current subscription
            $current = $this->db->query("
                SELECT * FROM `" . DB_PREFIX . "pms_project_subscription`
                WHERE project_id = '" . (int)$project_id . "'
                AND status = 'active'
                ORDER BY subscription_id DESC
                LIMIT 1
            ");
            
            if ($current->num_rows > 0) {
                $current_end = $current->row['end_date'];
                $today = date('Y-m-d');
                
                if (strtotime($current_end) < strtotime($today)) {
                    $new_end_date = date('Y-m-d', strtotime('+' . $months . ' months'));
                } else {
                    $new_end_date = date('Y-m-d', strtotime($current_end . ' + ' . $months . ' months'));
                }
                
                $this->db->query("
                    UPDATE `" . DB_PREFIX . "pms_project_subscription` 
                    SET `admin_override` = 1,
                        `override_until` = '" . $this->db->escape($new_end_date) . "',
                        `override_reason` = '" . $this->db->escape($reason) . "',
                        `end_date` = '" . $this->db->escape($new_end_date) . "',
                        `status` = 'active'
                    WHERE `subscription_id` = '" . (int)$current->row['subscription_id'] . "'
                ");

                $this->model_pms_subscription_settings->clearCache($project_id);
                
                $json['success'] = sprintf('Admin override added! New expiry: %s', date('d M Y', strtotime($new_end_date)));
            } else {
                $json['error'] = 'No active subscription found to override';
            }
            
            $json['redirect'] = $this->url->link('pms/project_subscription', 'user_token=' . $this->session->data['user_token'] . '&project_id=' . $project_id, true);
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    
    public function removeOverride(): void {
        $this->load->language('pms/project_subscription');
        $this->load->model('pms/subscription_settings');
        
        $json = [];
        
        if (!$this->user->hasPermission('modify', 'pms/project_subscription')) {
            $json['error'] = $this->language->get('error_permission');
        }
        
        if (!$json) {
            $subscription_id = (int)$this->request->post['subscription_id'];
            
            $subscription = $this->db->query("
                SELECT * FROM `" . DB_PREFIX . "pms_project_subscription`
                WHERE subscription_id = '" . (int)$subscription_id . "'
            ");
            
            if ($subscription->num_rows > 0) {
                $original_end_date = date('Y-m-d', strtotime($subscription->row['start_date'] . ' + ' . $subscription->row['duration_months'] . ' months'));
                
                $this->db->query("
                    UPDATE `" . DB_PREFIX . "pms_project_subscription` 
                    SET `admin_override` = 0,
                        `override_until` = NULL,
                        `override_reason` = NULL,
                        `end_date` = '" . $this->db->escape($original_end_date) . "',
                        `status` = CASE 
                            WHEN '" . $this->db->escape($original_end_date) . "' < NOW() THEN 'expired' 
                            ELSE 'active' 
                        END
                    WHERE `subscription_id` = '" . (int)$subscription_id . "'
                ");

                $project_id = $subscription->row['project_id'];

                $this->model_pms_subscription_settings->clearCache($project_id);
                
                $json['success'] = 'Admin override removed';
            }
            
            $json['redirect'] = $this->url->link('pms/project_subscription', 'user_token=' . $this->session->data['user_token'] . '&project_id=' . $subscription->row['project_id'], true);
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
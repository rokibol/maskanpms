<?php
namespace Opencart\Admin\Controller\Pms;

class ProjectCustomers extends \Opencart\System\Engine\Controller {

    public function index(): void {
        $this->load->language('pms/project');

        $this->response->setOutput($this->getList());
    }

    public function getList(): string {
        if (isset($this->request->get['project_id'])) {
            $project_id = (int)$this->request->get['project_id'];
        } else {
            $project_id = 0;
        }

        $this->load->model('pms/project');
        $this->load->model('customer/customer');
        $this->load->model('customer/customer_group');

        $project_info = $this->model_pms_project->getProject($project_id);

        if (!$project_info) {
            return '<div class="alert alert-danger">Project not found</div>';
        }

        // Get customers for this project
        $filter_data = [
            'filter_project_id' => $project_id,
            'sort'              => 'c.date_added',
            'order'             => 'DESC'
        ];

        $customers = $this->model_customer_customer->getCustomers($filter_data);

        $html = '<div class="table-responsive">';
        $html .= '<table class="table table-bordered table-hover">';
        $html .= '<thead>';
        $html .= '<tr>';
        $html .= '<th>' . $this->language->get('column_customer_name') . '</th>';
        $html .= '<th>' . $this->language->get('column_email') . '</th>';
        $html .= '<th>' . $this->language->get('column_customer_group') . '</th>';
        $html .= '<th>' . $this->language->get('column_date_added') . '</th>';
        $html .= '<th>' . $this->language->get('column_status') . '</th>';
        $html .= '<th class="text-end">' . $this->language->get('column_action') . '</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';

        if ($customers) {
            foreach ($customers as $customer) {
                $customer_group_info = $this->model_customer_customer_group->getCustomerGroup($customer['customer_group_id']);

                $status_badge = $customer['status'] ? '<span class="badge bg-success">Enabled</span>' : '<span class="badge bg-danger">Disabled</span>';

                $edit_link = $this->url->link('customer/customer.form', 'user_token=' . $this->session->data['user_token'] . '&customer_id=' . $customer['customer_id']);

                $remove_link = $this->url->link('pms/project_customers.remove', 'user_token=' . $this->session->data['user_token'] . '&customer_id=' . $customer['customer_id'] . '&project_id=' . $project_id);

                $html .= '<tr>';
                $html .= '<td>' . $customer['firstname'] . ' ' . $customer['lastname'] . '</td>';
                $html .= '<td>' . $customer['email'] . '</td>';
                $html .= '<td>' . ($customer_group_info ? $customer_group_info['name'] : '') . '</td>';
                $html .= '<td>' . date($this->language->get('date_format_short'), strtotime($customer['date_added'])) . '</td>';
                $html .= '<td>' . $status_badge . '</td>';
                $html .= '<td class="text-end">';
                $html .= '<a href="' . $edit_link . '" data-bs-toggle="tooltip" title="' . $this->language->get('button_edit') . '" class="btn btn-primary btn-sm"><i class="fa-regular fa-pen-to-square"></i></a>';
                $html .= ' <button type="button" onclick="removeCustomer(' . $customer['customer_id'] . ')" data-bs-toggle="tooltip" title="' . $this->language->get('button_remove_customer') . '" class="btn btn-danger btn-sm"><i class="fa-regular fa-trash-can"></i></button>';
                $html .= '</td>';
                $html .= '</tr>';
            }
        } else {
            $html .= '<tr>';
            $html .= '<td colspan="6" class="text-center">' . $this->language->get('text_no_customers') . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</div>';

        // Add JavaScript for remove functionality
        $html .= '<script>
        function removeCustomer(customerId) {
            if (confirm("Are you sure you want to remove this customer from the project?")) {
                $.ajax({
                    url: "index.php?route=pms/project_customers.remove&user_token=' . $this->session->data['user_token'] . '&customer_id=" + customerId + "&project_id=' . $project_id . '",
                    type: "POST",
                    dataType: "json",
                    success: function(json) {
                        if (json.success) {
                            location.reload();
                        } else {
                            alert(json.error);
                        }
                    },
                    error: function(xhr, ajaxOptions, thrownError) {
                        alert(thrownError + "\\r\\n" + xhr.statusText + "\\r\\n" + xhr.responseText);
                    }
                });
            }
        }
        </script>';

        return $html;
    }

    public function remove(): void {
        $this->load->language('pms/project');

        $json = [];

        if (!$this->user->hasPermission('modify', 'pms/project')) {
            $json['error'] = $this->language->get('error_permission');
        }

        if (!$json) {
            $this->load->model('customer/customer');

            $customer_id = isset($this->request->get['customer_id']) ? (int)$this->request->get['customer_id'] : 0;
            $project_id = isset($this->request->get['project_id']) ? (int)$this->request->get['project_id'] : 0;

            if ($customer_id && $project_id) {
                // Remove project assignment (set project_id to NULL)
                $this->db->query("
                    UPDATE `" . DB_PREFIX . "customer` 
                    SET `project_id` = NULL 
                    WHERE `customer_id` = '" . (int)$customer_id . "'
                    AND `project_id` = '" . (int)$project_id . "'
                ");
                $json['success'] = true;
            } else {
                $json['error'] = 'Invalid customer or project';
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function addCustomerForm(): void {
        $this->load->language('pms/project');

        $project_id = isset($this->request->get['project_id']) ? (int)$this->request->get['project_id'] : 0;

        $data['project_id'] = $project_id;
        $data['user_token'] = $this->session->data['user_token'];

        $this->load->model('customer/customer');
        $this->load->model('customer/customer_group');

        // Get customers not assigned to any project or with different project
        $filter_data = [
            'filter_project_id_not' => $project_id,
            'sort' => 'c.firstname',
            'order' => 'ASC'
        ];

        $available_customers = $this->model_customer_customer->getCustomers($filter_data);

        $data['customers'] = [];
        foreach ($available_customers as $customer) {
            $customer_group_info = $this->model_customer_customer_group->getCustomerGroup($customer['customer_group_id']);
            $data['customers'][] = [
                'customer_id' => $customer['customer_id'],
                'name' => $customer['firstname'] . ' ' . $customer['lastname'],
                'email' => $customer['email'],
                'customer_group' => $customer_group_info ? $customer_group_info['name'] : ''
            ];
        }

        $this->response->setOutput($this->load->view('pms/project_add_customer', $data));
    }

    public function addCustomer(): void {
        $this->load->language('pms/project');

        $json = [];

        if (!$this->user->hasPermission('modify', 'pms/project')) {
            $json['error'] = $this->language->get('error_permission');
        }

        if (!$json) {
            $customer_id = isset($this->request->post['customer_id']) ? (int)$this->request->post['customer_id'] : 0;
            $project_id = isset($this->request->post['project_id']) ? (int)$this->request->post['project_id'] : 0;

            if ($customer_id && $project_id) {
                // Update customer with project_id
                $this->db->query("
                    UPDATE `" . DB_PREFIX . "customer` 
                    SET `project_id` = '" . (int)$project_id . "' 
                    WHERE `customer_id` = '" . (int)$customer_id . "'
                ");
                $json['success'] = true;
            } else {
                $json['error'] = 'Invalid customer or project';
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
<?php
namespace Opencart\Catalog\Controller\Account;

class PmsExpenses extends \Opencart\System\Engine\Controller {

    public function index(): void {
        // Check if logged in
        if (!$this->customer->isLogged()) {
            $this->session->data['redirect'] = $this->url->link('account/pms_expenses', 'language=' . $this->config->get('config_language'));
            $this->response->redirect($this->url->link('account/login', 'language=' . $this->config->get('config_language')));
        }
        
        // Check if user is Accountant or Manager
        /*$customer_group_id = $this->customer->getGroupId();
        $accountant_group_id = (int)$this->config->get('config_pms_accountant_group_id');
        $manager_group_id = (int)$this->config->get('config_pms_manager_group_id');
        
        if ($customer_group_id != $accountant_group_id && $customer_group_id != $manager_group_id) {
            $this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language')));
        }*/
        
        $this->load->language('pms/expenses');
        
        $this->document->setTitle($this->language->get('heading_title'));
        
        // Get current project
        $this->load->model('pms/project');
        $project = $this->customer->getProject();
        
        if (!$project) {
            $this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language')));
        }
        
        $data['project_id'] = $project['project_id'];
        $data['project_name'] = $project['name'];

        $data['is_accountant'] = $this->customer->getGroupId() == 2 ? 1 : 0;
        
        // Filter parameters
        $filter_invoice = isset($this->request->get['filter_invoice']) ? $this->request->get['filter_invoice'] : '';
        $filter_category = isset($this->request->get['filter_category']) ? $this->request->get['filter_category'] : '';
        $filter_date_from = isset($this->request->get['filter_date_from']) ? $this->request->get['filter_date_from'] : '';
        $filter_date_to = isset($this->request->get['filter_date_to']) ? $this->request->get['filter_date_to'] : '';
        $filter_amount_min = isset($this->request->get['filter_amount_min']) ? $this->request->get['filter_amount_min'] : '';
        $filter_amount_max = isset($this->request->get['filter_amount_max']) ? $this->request->get['filter_amount_max'] : '';
        
        $sort = isset($this->request->get['sort']) ? $this->request->get['sort'] : 'e.expense_date';
        $order = isset($this->request->get['order']) ? $this->request->get['order'] : 'DESC';
        $page = isset($this->request->get['page']) ? (int)$this->request->get['page'] : 1;
        
        // Build URL for pagination
        $url = '';
        if ($filter_invoice) $url .= '&filter_invoice=' . urlencode($filter_invoice);
        if ($filter_category) $url .= '&filter_category=' . $filter_category;
        if ($filter_date_from) $url .= '&filter_date_from=' . $filter_date_from;
        if ($filter_date_to) $url .= '&filter_date_to=' . $filter_date_to;
        if ($filter_amount_min) $url .= '&filter_amount_min=' . $filter_amount_min;
        if ($filter_amount_max) $url .= '&filter_amount_max=' . $filter_amount_max;
        if ($sort) $url .= '&sort=' . $sort;
        if ($order) $url .= '&order=' . $order;
        
        $limit = $this->config->get('config_pagination_admin');
        
        $filter_data = [
            'project_id'        => $project['project_id'],
            'filter_invoice'    => $filter_invoice,
            'filter_category'   => $filter_category,
            'filter_date_from'  => $filter_date_from,
            'filter_date_to'    => $filter_date_to,
            'filter_amount_min' => $filter_amount_min,
            'filter_amount_max' => $filter_amount_max,
            'sort'              => $sort,
            'order'             => $order,
            'start'             => ($page - 1) * $limit,
            'limit'             => $limit
        ];
        
        $this->load->model('pms/expense');
        $this->load->model('pms/expense_category');
        
        $expense_total = $this->model_pms_expense->getTotalExpenses($filter_data);
        $results = $this->model_pms_expense->getExpenses($filter_data);
        
        // Get categories for filter dropdown
        $data['categories'] = $this->model_pms_expense_category->getCategories();

        $data['expenses'] = [];
        
        foreach ($results as $result) {
            // Get category path
            $category_info = $this->model_pms_expense_category->getCategory($result['category_id']);
            
            $data['expenses'][] = [
                'expense_id'        => $result['expense_id'],
                'invoice_number'    => $result['invoice_number'],
                'expense_date'      => date($this->language->get('date_format_short'), strtotime($result['expense_date'])),
                'category_name'     => $result['category_name'],
                'description'       => $result['description'],
                'total_amount'      => $result['total_amount'],
                'has_document'      => !empty($result['document_path']),
                'document_path'     => $result['document_path'],
                'unpaid_count'      => $result['unpaid_count'],
                'created_by'        => $result['created_by_name'],
                'edit'              => $this->customer->getGroupId() == 2 ? $this->url->link('account/pms_expenses.edit', 'language=' . $this->config->get('config_language') . '&expense_id=' . $result['expense_id'] . $url . '&customer_token=' . $this->session->data['customer_token']) : '',
                'view'              => $this->url->link('account/pms_expenses.view', 'language=' . $this->config->get('config_language') . '&expense_id=' . $result['expense_id'] . $url . '&customer_token=' . $this->session->data['customer_token']),
                'delete'            => $this->url->link('account/pms_expenses.delete', 'language=' . $this->config->get('config_language') . '&expense_id=' . $result['expense_id'] . $url . '&customer_token=' . $this->session->data['customer_token']),
                'download'          => $this->url->link('account/pms_expenses.download', 'language=' . $this->config->get('config_language') . '&expense_id=' . $result['expense_id'] . '&customer_token=' . $this->session->data['customer_token'])
            ];
        }
        
        // Pagination
        $data['pagination'] = $this->load->controller('common/pagination', [
			'total' => $expense_total,
			'page'  => $page,
			'limit' => $this->config->get('config_pagination_admin'),
			'url'   => $this->url->link('account/pms_shareholders.list', 'customer_token=' . $this->session->data['customer_token'] . $url .  '&customer_token=' . $this->session->data['customer_token'] . '&page={page}')
		]);

		$data['results'] = sprintf($this->language->get('text_pagination'), ($expense_total) ? (($page - 1) * $this->config->get('config_pagination_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_pagination_admin')) > ($expense_total - $this->config->get('config_pagination_admin'))) ? $expense_total : ((($page - 1) * $this->config->get('config_pagination_admin')) + $this->config->get('config_pagination_admin')), $expense_total, ceil($expense_total / $this->config->get('config_pagination_admin')));
        
        // Sort links
        $data['sort_date'] = $this->url->link('account/pms_expenses', 'language=' . $this->config->get('config_language') . '&sort=e.expense_date' . $url . '&customer_token=' . $this->session->data['customer_token']);
        $data['sort_invoice'] = $this->url->link('account/pms_expenses', 'language=' . $this->config->get('config_language') . '&sort=e.invoice_number' . $url . '&customer_token=' . $this->session->data['customer_token']);
        $data['sort_category'] = $this->url->link('account/pms_expenses', 'language=' . $this->config->get('config_language') . '&sort=category_name' . $url . '&customer_token=' . $this->session->data['customer_token']);
        $data['sort_amount'] = $this->url->link('account/pms_expenses', 'language=' . $this->config->get('config_language') . '&sort=e.total_amount' . $url . '&customer_token=' . $this->session->data['customer_token']);
        
        // Filter values
        $data['filter_invoice'] = $filter_invoice;
        $data['filter_category'] = $filter_category;
        $data['filter_date_from'] = $filter_date_from;
        $data['filter_date_to'] = $filter_date_to;
        $data['filter_amount_min'] = $filter_amount_min;
        $data['filter_amount_max'] = $filter_amount_max;
        
        // Sort and order
        $data['sort'] = $sort;
        $data['order'] = $order;
        
        // Expense statistics
        $data['stats'] = $this->model_pms_expense->getExpenseStats($project['project_id']);

        $data['language'] = $this->config->get('config_language');
        $data['customer_token'] = $this->session->data['customer_token'];
        
        // URLs
        $data['add'] = $this->url->link('account/pms_expenses.add', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);

        if($data['is_accountant']) {
            $data['back'] = $this->url->link('account/accountant', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        } else {
            $data['back'] = $this->url->link('account/manager', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        }
        // Template parts
        $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
        
        $this->response->setOutput($this->load->view('account/pms_expenses_list', $data));
    }
    
    public function add(): void {
        $this->form();
    }
    
    public function edit(): void {
        $this->form();
    }
    
    private function form(): void {
        if (!$this->customer->isLogged()) {
            $this->response->redirect($this->url->link('account/login', 'language=' . $this->config->get('config_language')));
        }
        
        $this->load->language('pms/expenses');
        
        $this->document->setTitle($this->language->get('heading_form'));
        
        // Get current project
        $this->load->model('pms/project');
        $project = $this->customer->getProject();
        
        if (!$project) {
            $this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language')));
        }
        
        $data['project_id'] = $project['project_id'];
        $data['project_name'] = $project['name'];
        
        // Load models
        $this->load->model('pms/expense');
        $this->load->model('pms/expense_category');
        
        if (isset($this->request->get['expense_id'])) {
            $expense_id = (int)$this->request->get['expense_id'];
            $expense_info = $this->model_pms_expense->getExpense($expense_id);
            
            if (!$expense_info) {
                $this->response->redirect($this->url->link('account/pms_expenses', 'language=' . $this->config->get('config_language' . '&customer_token=' . $this->session->data['customer_token'])));
            }
            
            $data['expense_id'] = $expense_id;
            $data['text_form'] = $this->language->get('text_edit');
        } else {
            $data['expense_id'] = 0;
            $data['text_form'] = $this->language->get('text_add');
        }
        
        // Form action
        $url = $this->buildUrl();
        $data['action'] = $this->url->link('account/pms_expenses.save', 'language=' . $this->config->get('config_language') . $url . '&customer_token=' . $this->session->data['customer_token']);
        $data['back'] = $this->url->link('account/pms_expenses', 'language=' . $this->config->get('config_language') . $url . '&customer_token=' . $this->session->data['customer_token']);
        
        // Get categories for dropdown
        $data['categories'] = $this->model_pms_expense_category->getCategories();
        
        // Set form values
        $fields = ['invoice_number', 'expense_date', 'category_id', 'description', 'total_amount'];
        
        foreach ($fields as $field) {
            if (isset($this->request->post[$field])) {
                $data[$field] = $this->request->post[$field];
            } elseif (isset($expense_info)) {
                if ($field == 'expense_date') {
                    $data[$field] = date('Y-m-d', strtotime($expense_info[$field]));
                } else {
                    $data[$field] = $expense_info[$field];
                }
            } else {
                $data[$field] = ($field == 'expense_date') ? date('Y-m-d') : '';
            }
        }
        
        $data['customer_token'] = $this->session->data['customer_token'];
        
        $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
        
        $this->response->setOutput($this->load->view('account/pms_expense_form', $data));
    }
    
    public function view(): void {
        if (!$this->customer->isLogged()) {
            $this->response->redirect($this->url->link('account/login', 'language=' . $this->config->get('config_language')));
        }
        
        $this->load->language('pms/expenses');
        
        $this->document->setTitle($this->language->get('heading_view'));
        
        $expense_id = isset($this->request->get['expense_id']) ? (int)$this->request->get['expense_id'] : 0;
        
        $this->load->model('pms/expense');
        $this->load->model('pms/expense_category');
        
        $expense_info = $this->model_pms_expense->getExpense($expense_id);
        
        if (!$expense_info) {
            $this->response->redirect($this->url->link('account/pms_expenses', 'language=' . $this->config->get('config_language')));
        }
        
        // Get allocation details
        $allocations = $this->db->query("
            SELECT ea.*, 
                   CONCAT(c.firstname, ' ', c.lastname) as shareholder_name,
                   s.shareholder_code
            FROM `" . DB_PREFIX . "expenses_allocation` ea
            LEFT JOIN `" . DB_PREFIX . "shareholders` s ON ea.shareholder_id = s.id
            LEFT JOIN `" . DB_PREFIX . "customer` c ON s.user_id = c.customer_id
            WHERE ea.expense_id = '" . (int)$expense_id . "'
            AND ea.is_deleted = 0
        ");
        
        $data['allocations'] = $allocations->rows;
        
        $data['expense'] = [
            'expense_id'        => $expense_info['expense_id'],
            'invoice_number'    => $expense_info['invoice_number'],
            'expense_date'      => date($this->language->get('date_format_short'), strtotime($expense_info['expense_date'])),
            'category_name'     => $expense_info['category_name'],
            'description'       => nl2br($expense_info['description']),
            'total_amount'      => number_format($expense_info['total_amount'], 2),
            'has_document'      => !empty($expense_info['document_path']),
            'document_path'     => $expense_info['document_path'],
            'document_url'      => $expense_info['document_path'] ? HTTP_SERVER . $expense_info['document_path'] : '',
            'created_by'        => $expense_info['created_by_name'],
            'created_at'        => date($this->language->get('date_format_short') . ' H:i', strtotime($expense_info['created_at'])),
            'updated_at'        => $expense_info['updated_at'] ? date($this->language->get('date_format_short') . ' H:i', strtotime($expense_info['updated_at'])) : '-'
        ];
        
        $url = $this->buildUrl();
        $data['back'] = $this->url->link('account/pms_expenses', 'language=' . $this->config->get('config_language') . $url . '&customer_token=' . $this->session->data['customer_token']);
        if($this->customer->getGroupId() == 2) {
            $data['edit'] = $this->url->link('account/pms_expenses.edit', 'language=' . $this->config->get('config_language') . '&expense_id=' . $expense_id . $url . '&customer_token=' . $this->session->data['customer_token']);
        }
        $data['download'] = $this->url->link('account/pms_expenses.download', 'language=' . $this->config->get('config_language') . '&expense_id=' . $expense_id . '&customer_token=' . $this->session->data['customer_token']);
        
        $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
        
        $this->response->setOutput($this->load->view('account/pms_expense_view', $data));
    }
    
    /**
     * Save expense with proper file upload handling for OpenCart 4.0.1.3
     */
    public function save(): void {
        $this->load->language('pms/expenses');
        
        $json = [];
        
        // Check permission
        /*$customer_group_id = $this->customer->getGroupId();
        $accountant_group_id = (int)$this->config->get('config_pms_accountant_group_id');
        $manager_group_id = (int)$this->config->get('config_pms_manager_group_id');
        
        if ($customer_group_id != $accountant_group_id && $customer_group_id != $manager_group_id) {
            $json['error'] = $this->language->get('error_permission');
        }*/
        
        // Validation
        if (!$json) {
            if (empty($this->request->post['invoice_number'])) {
                $json['error']['invoice_number'] = $this->language->get('error_invoice_number');
            }
            
            if (empty($this->request->post['expense_date'])) {
                $json['error']['expense_date'] = $this->language->get('error_expense_date');
            }
            
            if (empty($this->request->post['category_id'])) {
                $json['error']['category_id'] = $this->language->get('error_category');
            }
            
            if (empty($this->request->post['total_amount']) || $this->request->post['total_amount'] <= 0) {
                $json['error']['total_amount'] = $this->language->get('error_total_amount');
            }
        }
        
        // Handle file upload - Direct upload to image directory
        if (!$json && isset($this->request->files['document']) && $this->request->files['document']['error'] == UPLOAD_ERR_OK) {
            $file = $this->request->files['document'];
            $filename = $file['name'];
            $tmp_name = $file['tmp_name'];
            $filesize = $file['size'];
            
            // Validate file extension
            $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (!in_array($extension, $allowed_extensions)) {
                $json['error']['document'] = 'Invalid file type. Allowed: PDF, JPG, PNG, DOC, DOCX';
            }
            
            // Validate file size (max 5MB)
            if ($filesize > 5242880) {
                $json['error']['document'] = 'File size too large. Maximum 5MB allowed.';
            }
            
            if (!$json) {
                // Create directory structure
                //$upload_dir = 'catalog/view/theme/default/image/pms/expenses/';
                $upload_dir = DIR_IMAGE . 'pms/expenses/';
                $full_dir = DIR_IMAGE . 'pms/expenses/';
                
                if (!is_dir($full_dir)) {
                    mkdir($full_dir, 0777, true);
                }
                
                // Generate unique filename
                $unique_filename = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
                $filepath = $upload_dir . $unique_filename;
                $full_filepath = $full_dir . $unique_filename;
                
                if (move_uploaded_file($tmp_name, $full_filepath)) {
                    $this->request->post['document_path'] = $filepath;
                } else {
                    $json['error']['document'] = 'Failed to upload file. Check directory permissions.';
                }
            }
        }
        
        // Also check for existing document from form (if no new file uploaded)
        if (!$json && !isset($this->request->post['document_path']) && isset($this->request->post['existing_document'])) {
            $this->request->post['document_path'] = $this->request->post['existing_document'];
        }
        
        if (!$json) {
            $this->load->model('pms/expense');
            $this->load->model('pms/project');
            
            $project = $this->model_pms_project->getProjectByCustomerId($this->customer->getId());
            $this->request->post['project_id'] = $project['project_id'];
            
            if (isset($this->request->get['expense_id'])) {
                $this->model_pms_expense->updateExpense($this->request->get['expense_id'], $this->request->post);
                $json['success'] = $this->language->get('text_update_success');
            } else {
                $this->model_pms_expense->addExpense($this->request->post);
                $json['success'] = $this->language->get('text_add_success');
            }
            
            $url = $this->buildUrl();
            $json['redirect'] = $this->url->link('account/pms_expenses', 'language=' . $this->config->get('config_language') . $url);
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Upload document via AJAX (for OpenCart 4.0.1.3)
     */
    public function uploadDocument(): void {
        $this->load->language('pms/expenses');
        
        $json = [];
        
        if (isset($this->request->files['file']) && $this->request->files['file']['error'] == UPLOAD_ERR_OK) {
            $filename = $this->request->files['file']['name'];
            $tmp_name = $this->request->files['file']['tmp_name'];
            $filesize = $this->request->files['file']['size'];
            
            // Validate file extension
            $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (!in_array($extension, $allowed_extensions)) {
                $json['error'] = 'Invalid file type. Allowed: PDF, JPG, PNG, DOC, DOCX';
            }
            
            // Validate file size (max 5MB)
            if ($filesize > 5242880) {
                $json['error'] = 'File size too large. Maximum 5MB allowed.';
            }
            
            if (!isset($json['error'])) {
                try {
                    // Create the directory structure
                    $upload_dir = DIR_IMAGE . 'pms/expenses/';
                    $full_dir = DIR_IMAGE . 'pms/expenses/';
                    
                    if (!is_dir($full_dir)) {
                        mkdir($full_dir, 0777, true);
                    }
                    
                    // Generate unique filename
                    $unique_filename = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
                    $filepath = $upload_dir . $unique_filename;
                    $full_filepath = $full_dir . $unique_filename;
                    
                    // Move uploaded file
                    if (move_uploaded_file($tmp_name, $full_filepath)) {
                        $json['success'] = true;
                        $json['filename'] = $filename;
                        $json['file_path'] = $filepath;
                        $json['unique_filename'] = $unique_filename;
                    } else {
                        $json['error'] = 'Failed to move uploaded file. Check directory permissions.';
                    }
                    
                } catch (\Exception $e) {
                    $json['error'] = 'Upload error: ' . $e->getMessage();
                }
            }
        } else {
            $json['error'] = 'No file selected or upload error';
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Delete uploaded document
     */
    public function deleteDocument(): void {
        $this->load->language('pms/expenses');
        
        $json = [];
        
        if (isset($this->request->get['file_path'])) {
            $file_path = $this->request->get['file_path'];
            $full_path = DIR_APPLICATION . str_replace('catalog/view/theme/default/', '', $file_path);
            
            if (file_exists($full_path)) {
                unlink($full_path);
                $json['success'] = true;
            } else {
                $json['success'] = true; // File already doesn't exist
            }
        } else {
            $json['error'] = 'Invalid request';
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    
    public function delete(): void {
        $this->load->language('pms/expenses');
        
        $json = [];
        
        $customer_group_id = $this->customer->getGroupId();
        $accountant_group_id = (int)$this->config->get('config_pms_accountant_group_id');
        $manager_group_id = (int)$this->config->get('config_pms_manager_group_id');
        
        if ($customer_group_id != $accountant_group_id && $customer_group_id != $manager_group_id) {
            $json['error'] = $this->language->get('error_permission');
        }
        
        if (!$json) {
            $this->load->model('pms/expense');
            
            if (isset($this->request->get['expense_id'])) {
                $this->model_pms_expense->deleteExpense($this->request->get['expense_id']);
                $json['success'] = $this->language->get('text_delete_success');
            } else {
                $json['error'] = 'Invalid expense ID';
            }
            
            $url = $this->buildUrl();
            $json['redirect'] = $this->url->link('account/pms_expenses', 'language=' . $this->config->get('config_language') . $url);
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    
    public function download(): void {
        if (!$this->customer->isLogged()) {
            $this->response->redirect($this->url->link('account/login', 'language=' . $this->config->get('config_language')));
        }
        
        $expense_id = isset($this->request->get['expense_id']) ? (int)$this->request->get['expense_id'] : 0;
        
        $this->load->model('pms/expense');
        $expense_info = $this->model_pms_expense->getExpense($expense_id);
        
        if ($expense_info && !empty($expense_info['document_path'])) {
            // Get the full file path
            $file_path = DIR_IMAGE . $expense_info['document_path'];
            
            if (file_exists($file_path)) {
                // Set headers for download
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . basename($expense_info['document_path']) . '"');
                header('Content-Length: ' . filesize($file_path));
                readfile($file_path);
                exit;
            } else {
                // Try alternative path
                $alt_path = DIR_IMAGE . 'pms/expenses/' . basename($expense_info['document_path']);
                if (file_exists($alt_path)) {
                    header('Content-Type: application/octet-stream');
                    header('Content-Disposition: attachment; filename="' . basename($expense_info['document_path']) . '"');
                    header('Content-Length: ' . filesize($alt_path));
                    readfile($alt_path);
                    exit;
                }
            }
        }
        
        $this->response->redirect($this->url->link('account/pms_expenses', 'language=' . $this->config->get('config_language')));
    }
    
    private function buildUrl(): string {
        $url = '';
        
        if (isset($this->request->get['filter_invoice'])) {
            $url .= '&filter_invoice=' . urlencode($this->request->get['filter_invoice']);
        }
        if (isset($this->request->get['filter_category'])) {
            $url .= '&filter_category=' . $this->request->get['filter_category'];
        }
        if (isset($this->request->get['filter_date_from'])) {
            $url .= '&filter_date_from=' . $this->request->get['filter_date_from'];
        }
        if (isset($this->request->get['filter_date_to'])) {
            $url .= '&filter_date_to=' . $this->request->get['filter_date_to'];
        }
        if (isset($this->request->get['filter_amount_min'])) {
            $url .= '&filter_amount_min=' . $this->request->get['filter_amount_min'];
        }
        if (isset($this->request->get['filter_amount_max'])) {
            $url .= '&filter_amount_max=' . $this->request->get['filter_amount_max'];
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
        
        return $url;
    }
}
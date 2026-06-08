<?php
namespace Opencart\Catalog\Controller\Account;

class PmsExpenseInvoices extends \Opencart\System\Engine\Controller {

    public function index(): void {
        if (!$this->customer->isLogged()) {
            $this->response->redirect($this->url->link('account/login', 'language=' . $this->config->get('config_language')));
        }
        
        $this->load->language('pms/expense_invoices');
        $this->document->setTitle($this->language->get('heading_title'));
        
        // Get current project
        $this->load->model('pms/project');
        $project = $this->customer->getProject();
        
        if (!$project) {
            $this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']));
        }
        
        // Filter parameters
        $filter_invoice_no = isset($this->request->get['filter_invoice_no']) ? $this->request->get['filter_invoice_no'] : '';
        $filter_supplier = isset($this->request->get['filter_supplier']) ? $this->request->get['filter_supplier'] : '';
        $filter_payment_status = isset($this->request->get['filter_payment_status']) ? $this->request->get['filter_payment_status'] : '';
        $filter_date_from = isset($this->request->get['filter_date_from']) ? $this->request->get['filter_date_from'] : '';
        $filter_date_to = isset($this->request->get['filter_date_to']) ? $this->request->get['filter_date_to'] : '';
        
        $sort = isset($this->request->get['sort']) ? $this->request->get['sort'] : 'i.invoice_date';
        $order = isset($this->request->get['order']) ? $this->request->get['order'] : 'DESC';
        $page = isset($this->request->get['page']) ? (int)$this->request->get['page'] : 1;
        
        $this->load->model('pms/expense_invoice');
        $this->load->model('pms/supplier');
        
        // Get suppliers for filter dropdown
        $data['suppliers'] = $this->model_pms_supplier->getSupplierOptions($project['project_id']);
        
        $filter_data = [
            'project_id'          => $project['project_id'],
            'filter_invoice_no'   => $filter_invoice_no,
            'filter_supplier'     => $filter_supplier,
            'filter_payment_status' => $filter_payment_status,
            'filter_date_from'    => $filter_date_from,
            'filter_date_to'      => $filter_date_to,
            'sort'                => $sort,
            'order'               => $order,
            'start'               => ($page - 1) * 20,
            'limit'               => 20
        ];
        
        $invoice_total = $this->model_pms_expense_invoice->getTotalInvoices($filter_data);
        $invoices = $this->model_pms_expense_invoice->getInvoices($filter_data);
        
        $data['invoices'] = [];
        foreach ($invoices as $invoice) {
            $status_badge = $this->getStatusBadge($invoice['payment_status']);
            $due_amount = $invoice['total_amount'] - $invoice['paid_amount'];
            
            // In the foreach loop where building $data['invoices']
            $data['invoices'][] = [
                'invoice_id'        => $invoice['invoice_id'],
                'invoice_number'    => $invoice['invoice_number'],
                'invoice_date'      => date('d M Y', strtotime($invoice['invoice_date'])),
                'supplier_name'     => $invoice['supplier_name'],
                'sub_total'         => $invoice['sub_total'],
                'tax_amount'        => $invoice['tax_amount'],
                'discount_amount'   => $invoice['discount_amount'],
                'total_amount'      => $invoice['total_amount'],
                'paid_amount'       => $invoice['paid_amount'],
                'due_amount'        => $due_amount,
                'payment_status'    => $invoice['payment_status'],
                'status_badge'      => $status_badge,
                'payment_count'     => $invoice['payment_count'],
                'has_document'      => !empty($invoice['invoice_document']),
                'view'              => $this->url->link('account/pms_expense_invoices.view', 'language=' . $this->config->get('config_language') . '&invoice_id=' . $invoice['invoice_id']),
                'edit'              => $this->customer->getGroupId() == 2 ? $this->url->link('account/pms_expense_invoices.edit', 'language=' . $this->config->get('config_language') . '&invoice_id=' . $invoice['invoice_id']) : '',
                'download'          => !empty($invoice['invoice_document']) ? $this->url->link('account/pms_expense_invoices.downloadDocument', 'language=' . $this->config->get('config_language') . '&invoice_id=' . $invoice['invoice_id']) : '',
                'delete'            => $this->url->link('account/pms_expense_invoices.delete', 'language=' . $this->config->get('config_language') . '&invoice_id=' . $invoice['invoice_id'])
            ];
        }
        
        // Get invoice statistics
        $data['stats'] = $this->model_pms_expense_invoice->getInvoiceStats($project['project_id']);

        $data['is_accountant'] = $this->customer->getGroupId() == 2 ? 1 : 0;

        // Pagination
        $data['pagination'] = $this->load->controller('common/pagination', [
			'total' => $invoice_total,
			'page'  => $page,
			'limit' => $this->config->get('config_pagination_admin'),
			'url'   => $this->url->link('account/pms_expense_invoices', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token'] . '&page={page}')
		]);

		$data['results'] = sprintf($this->language->get('text_pagination'), ($invoice_total) ? (($page - 1) * $this->config->get('config_pagination_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_pagination_admin')) > ($invoice_total - $this->config->get('config_pagination_admin'))) ? $invoice_total : ((($page - 1) * $this->config->get('config_pagination_admin')) + $this->config->get('config_pagination_admin')), $invoice_total, ceil($invoice_total / $this->config->get('config_pagination_admin')));
        

        // Payment status options
        $data['payment_statuses'] = [
            'unpaid'    => 'Unpaid',
            'partial'   => 'Partial',
            'paid'      => 'Paid',
            'overdue'   => 'Overdue'
        ];
        
        $data['filter_invoice_no'] = $filter_invoice_no;
        $data['filter_supplier'] = $filter_supplier;
        $data['filter_payment_status'] = $filter_payment_status;
        $data['filter_date_from'] = $filter_date_from;
        $data['filter_date_to'] = $filter_date_to;

        $data['language'] = $this->config->get('config_language');
        $data['customer_token'] = $this->session->data['customer_token'];
        
        $data['add'] = $this->url->link('account/pms_expense_invoices.add', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        if($data['is_accountant']) {
            $data['back'] = $this->url->link('account/accountant', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        } else {
            $data['back'] = $this->url->link('account/manager', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        }
        
        $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
        
        $this->response->setOutput($this->load->view('account/pms_expense_invoices_list', $data));
    }
    
    public function add(): void {
        $this->form();
    }
    
    public function edit(): void {
        $this->form();
    }
    
    private function form(): void {
        $this->load->language('pms/expense_invoices');
        
        if (isset($this->request->get['invoice_id'])) {
            $this->document->setTitle($this->language->get('heading_edit'));
            $data['text_form'] = $this->language->get('heading_edit');
        } else {
            $this->document->setTitle($this->language->get('heading_add'));
            $data['text_form'] = $this->language->get('heading_add');
        }
        
        // Get current project
        $this->load->model('pms/project');
        $project = $this->customer->getProject();
        
        $data['project_id'] = $project['project_id'];
        
        $this->load->model('pms/expense_invoice');
        $this->load->model('pms/supplier');
        $this->load->model('pms/expense_category');
        
        // Get suppliers for dropdown
        $data['suppliers'] = $this->model_pms_supplier->getSupplierOptions($project['project_id']);
        
        // Get expense categories for items
        $data['categories'] = $this->model_pms_expense_category->getCategories();

        $data['customer_token'] = $this->session->data['customer_token'];

        $data['language'] = $this->config->get('config_language');
        
        if (isset($this->request->get['invoice_id'])) {
            $invoice_info = $this->model_pms_expense_invoice->getInvoice($this->request->get['invoice_id']);
            $invoice_items = $this->model_pms_expense_invoice->getInvoiceItems($this->request->get['invoice_id']);

            $data['invoice_items'] = $invoice_items;
        }
        
        $fields = ['supplier_id', 'invoice_number', 'invoice_date', 'due_date', 'sub_total', 'tax_amount', 'discount_amount', 'total_amount', 'currency', 'notes'];
        
        foreach ($fields as $field) {
            if (isset($this->request->post[$field])) {
                $data[$field] = $this->request->post[$field];
            } elseif (isset($invoice_info)) {
                $data[$field] = $invoice_info[$field];
            } else {
                $data[$field] = ($field == 'invoice_date') ? date('Y-m-d') : '';
            }
        }
        
        $data['action'] = $this->url->link('account/pms_expense_invoices.save', 'language=' . $this->config->get('config_language') . (isset($this->request->get['invoice_id']) ? '&invoice_id=' . $this->request->get['invoice_id'] : '') . '&customer_token=' . $this->session->data['customer_token']);
        $data['back'] = $this->url->link('account/pms_expense_invoices', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        
        $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
        
        $this->response->setOutput($this->load->view('account/pms_expense_invoice_form', $data));
    }
    
    /**
     * Save invoice with proper JSON decoding and document upload
     */
    public function save(): void {
        $this->load->language('pms/expense_invoices');
        
        $json = [];
        
        if (!$this->customer->isLogged()) {
            $json['error'] = $this->language->get('error_permission');
        }
        
        if (!$json) {
            $this->load->model('pms/project');

            $project = $this->customer->getProject();
            
            $this->load->model('pms/expense_invoice');
            
            // Fix: Properly decode JSON from HTML-encoded string
            $items_json = $this->request->post['items_json'];
            
            // First, decode HTML entities
            $items_json = html_entity_decode($items_json);
            
            // Then decode JSON
            $items = json_decode($items_json, true);
            
            // If still fails, try alternative approach
            if (!$items) {
                // Remove any slashes that might be escaping
                $items_json = stripslashes($items_json);
                $items = json_decode($items_json, true);
            }
            
            // Calculate totals from items
            $sub_total = 0;
            
            if ($items && is_array($items)) {
                foreach ($items as $item) {
                    $sub_total += $item['total_price'];
                }
            }
            
            $tax_amount = (float)$this->request->post['tax_amount'];
            $discount_amount = (float)$this->request->post['discount_amount'];
            $total_amount = $sub_total + $tax_amount - $discount_amount;
            
            // Handle document upload
            $document_path = '';
            if (isset($this->request->files['invoice_document']) && $this->request->files['invoice_document']['error'] == UPLOAD_ERR_OK) {
                $file = $this->request->files['invoice_document'];
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
                
                if (!isset($json['error'])) {
                    // Create directory
                    $upload_dir = DIR_IMAGE . 'pms/invoices/';
                    $full_dir = DIR_IMAGE . 'pms/invoices/';
                    
                    if (!is_dir($full_dir)) {
                        mkdir($full_dir, 0777, true);
                    }
                    
                    // Generate unique filename
                    $unique_filename = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
                    $filepath = $upload_dir . $unique_filename;
                    $full_filepath = $full_dir . $unique_filename;
                    
                    if (move_uploaded_file($tmp_name, $full_filepath)) {
                        $document_path = $filepath;
                    } else {
                        $json['error']['document'] = 'Failed to upload file. Check directory permissions.';
                    }
                }
            }
            
            // Also check for existing document
            if (empty($document_path) && isset($this->request->post['existing_document'])) {
                $document_path = $this->request->post['existing_document'];
            }
            
            if (!$json) {
                $data = [
                    'project_id'      => $project['project_id'],
                    'supplier_id'     => (int)$this->request->post['supplier_id'],
                    'invoice_number'  => $this->request->post['invoice_number'],
                    'invoice_date'    => $this->request->post['invoice_date'],
                    'due_date'        => $this->request->post['due_date'],
                    'sub_total'       => $sub_total,
                    'tax_amount'      => $tax_amount,
                    'discount_amount' => $discount_amount,
                    'total_amount'    => $total_amount,
                    'currency'        => $this->request->post['currency'],
                    'notes'           => $this->request->post['notes'],
                    'invoice_document'=> $document_path,
                    'items'           => $items
                ];
                
                if (isset($this->request->get['invoice_id'])) {
                    $this->model_pms_expense_invoice->updateInvoice($this->request->get['invoice_id'], $data);
                    $json['success'] = $this->language->get('text_update_success');
                } else {
                    $this->model_pms_expense_invoice->addInvoice($data);
                    $json['success'] = $this->language->get('text_add_success');
                }
                
                $json['redirect'] = $this->url->link('account/pms_expense_invoices', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
            }
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Upload invoice document via AJAX
     */
    public function uploadDocument(): void {
        $this->load->language('pms/expense_invoices');
        
        $json = [];
        
        if (isset($this->request->files['file']) && $this->request->files['file']['error'] == UPLOAD_ERR_OK) {
            $filename = $this->request->files['file']['name'];
            $tmp_name = $this->request->files['file']['tmp_name'];
            $filesize = $this->request->files['file']['size'];
            
            $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (!in_array($extension, $allowed_extensions)) {
                $json['error'] = 'Invalid file type. Allowed: PDF, JPG, PNG, DOC, DOCX';
            } elseif ($filesize > 5242880) {
                $json['error'] = 'File size too large. Maximum 5MB allowed.';
            } else {
                $upload_dir = DIR_IMAGE . 'pms/invoices/';
                $full_dir = DIR_IMAGE . 'pms/invoices/';
                
                if (!is_dir($full_dir)) {
                    mkdir($full_dir, 0777, true);
                }
                
                $unique_filename = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
                $filepath = $upload_dir . $unique_filename;
                $full_filepath = $full_dir . $unique_filename;
                
                if (move_uploaded_file($tmp_name, $full_filepath)) {
                    $json['success'] = true;
                    $json['filename'] = $filename;
                    $json['file_path'] = 'pms/invoices/' . $unique_filename;
                    $json['unique_filename'] = $unique_filename;
                } else {
                    $json['error'] = 'Failed to move uploaded file';
                }
            }
        } else {
            $json['error'] = 'No file selected or upload error';
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Delete invoice document
     */
    public function deleteDocument(): void {
        $json = [];
        
        if (isset($this->request->get['file_path'])) {
            $file_path = $this->request->get['file_path'];
            $full_path = DIR_APPLICATION . str_replace('catalog/view/theme/default/', '', $file_path);
            
            if (file_exists($full_path)) {
                unlink($full_path);
            }
            $json['success'] = true;
        } else {
            $json['error'] = 'Invalid request';
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Download invoice document
     */
    public function downloadDocument(): void {
        if (!$this->customer->isLogged()) {
            $this->response->redirect($this->url->link('account/login', 'language=' . $this->config->get('config_language')));
        }
        
        $invoice_id = isset($this->request->get['invoice_id']) ? (int)$this->request->get['invoice_id'] : 0;
        
        $this->load->model('pms/expense_invoice');
        $invoice_info = $this->model_pms_expense_invoice->getInvoice($invoice_id);
        
        if ($invoice_info && !empty($invoice_info['invoice_document'])) {
            $file_path = DIR_IMAGE . $invoice_info['invoice_document'];
            
            if (file_exists($file_path)) {
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . basename($invoice_info['invoice_document']) . '"');
                header('Content-Length: ' . filesize($file_path));
                readfile($file_path);
                exit;
            }
        }
        
        $this->response->redirect($this->url->link('account/pms_expense_invoices', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']));
    }
    
    public function view(): void {
        if (!$this->customer->isLogged()) {
            $this->response->redirect($this->url->link('account/login', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']));
        }
        
        $this->load->language('pms/expense_invoices');
        $this->document->setTitle($this->language->get('heading_view'));
        
        $invoice_id = isset($this->request->get['invoice_id']) ? (int)$this->request->get['invoice_id'] : 0;
        
        $this->load->model('pms/expense_invoice');
        
        $invoice_info = $this->model_pms_expense_invoice->getInvoice($invoice_id);
        $invoice_items = $this->model_pms_expense_invoice->getInvoiceItems($invoice_id);
        $payments = $this->model_pms_expense_invoice->getPayments($invoice_id);
        $total_paid = $this->model_pms_expense_invoice->getTotalPaidAmount($invoice_id);

        // Get payments for this invoice
        $data['payments'] = $this->model_pms_expense_invoice->getInvoicePayments($invoice_id);
        $data['total_paid'] = $this->model_pms_expense_invoice->getTotalPaidForInvoice($invoice_id);

        $invoice_document = '';
        if($invoice_info['invoice_document']) {
            $invoice_document = DIR_IMAGE . $invoice_info['invoice_document'];
        }
        
        $data['invoice'] = [
            'invoice_id'        => $invoice_info['invoice_id'],
            'invoice_number'    => $invoice_info['invoice_number'],
            'invoice_date'      => date('d F Y', strtotime($invoice_info['invoice_date'])),
            'due_date'          => date('d F Y', strtotime($invoice_info['due_date'])),
            'supplier_name'     => $invoice_info['supplier_name'],
            'supplier_code'     => $invoice_info['supplier_code'],
            'sub_total'         => number_format($invoice_info['sub_total'], 2),
            'tax_amount'        => number_format($invoice_info['tax_amount'], 2),
            'discount_amount'   => number_format($invoice_info['discount_amount'], 2),
            'total_amount'      => number_format($invoice_info['total_amount'], 2),
            'paid_amount'       => number_format($total_paid, 2),
            'due_amount'        => number_format($invoice_info['total_amount'] - $total_paid, 2),
            'payment_status'    => ucfirst($invoice_info['payment_status']),
            'status_badge'      => $this->getStatusBadge($invoice_info['payment_status']),
            'invoice_document'  => $invoice_document,
            'notes'             => nl2br($invoice_info['notes']),
            'created_by'        => $invoice_info['created_by_name'],
            'created_at'        => date('d F Y h:i A', strtotime($invoice_info['created_at']))
        ];
        
        $data['items'] = $invoice_items;
        $data['payments'] = $payments;
        $data['total_paid'] = $total_paid;

        $data['language'] = $this->config->get('config_language');
        $data['customer_token'] = $this->session->data['customer_token'];
        
        $data['add_payment'] = $this->url->link('account/pms_expense_invoices.addPayment', 'language=' . $this->config->get('config_language') . '&invoice_id=' . $invoice_id . '&customer_token=' . $this->session->data['customer_token']);
        if($this->customer->getGroupId() == 2) {
            $data['edit'] = $this->url->link('account/pms_expense_invoices.edit', 'language=' . $this->config->get('config_language') . '&invoice_id=' . $invoice_id . '&customer_token=' . $this->session->data['customer_token']);
        }
        $data['back'] = $this->url->link('account/pms_expense_invoices', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        $data['download_document'] = $this->url->link('account/pms_expense_invoices.downloadDocument', 'language=' . $this->config->get('config_language') . '&invoice_id=' . $invoice_id . '&customer_token=' . $this->session->data['customer_token']);

        $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
        
        $this->response->setOutput($this->load->view('account/pms_expense_invoice_view', $data));
    }
    
    public function delete(): void {
        $this->load->language('pms/expense_invoices');
        
        $json = [];
        
        if (!$this->customer->isLogged()) {
            $json['error'] = $this->language->get('error_permission');
        }
        
        if (!$json) {
            $this->load->model('pms/expense_invoice');
            
            if (isset($this->request->get['invoice_id'])) {
                $this->model_pms_expense_invoice->deleteInvoice($this->request->get['invoice_id']);
                $json['success'] = $this->language->get('text_delete_success');
            }
            
            $json['redirect'] = $this->url->link('account/pms_expense_invoices', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    
    public function addPayment(): void {
        $this->load->language('pms/expense_invoices');
        
        $json = [];
        
        if (!$this->customer->isLogged()) {
            $json['error'] = $this->language->get('error_permission');
        }
        
        if (!$json) {
            $this->load->model('pms/expense_invoice');
            
            $invoice_id = (int)$this->request->post['invoice_id'];
            $amount = (float)$this->request->post['amount'];
            $payment_date = $this->request->post['payment_date'];
            $payment_method = $this->request->post['payment_method'];
            $reference_number = $this->request->post['reference_number'];
            $notes = $this->request->post['notes'];
            
            $data = [
                'invoice_id'       => $invoice_id,
                'amount'           => $amount,
                'payment_date'     => $payment_date,
                'payment_method'   => $payment_method,
                'reference_number' => $reference_number,
                'notes'            => $notes
            ];
            
            $this->model_pms_expense_invoice->addPayment($data);
            $json['success'] = $this->language->get('text_payment_success');
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    
    private function getStatusBadge(string $status): string {
        switch ($status) {
            case 'paid':
                return '<span class="badge bg-success"><i class="fas fa-regular fa-circle-check"></i> Paid</span>';
            case 'partial':
                return '<span class="badge bg-warning"><i class="fas fa-regular fa-clock"></i> Partial</span>';
            case 'overdue':
                return '<span class="badge bg-danger"><i class="fas fa-regular fa-circle-exclamation"></i> Overdue</span>';
            default:
                return '<span class="badge bg-secondary"><i class="fas fa-regular fa-hourglass"></i> Unpaid</span>';
        }
    }

    /**
     * Add payment to invoice (AJAX form)
     */
    public function addPaymentForm(): void {
        $this->load->language('pms/expense_invoices');
        
        $invoice_id = isset($this->request->get['invoice_id']) ? (int)$this->request->get['invoice_id'] : 0;
        
        $this->load->model('pms/expense_invoice');
        $invoice = $this->model_pms_expense_invoice->getInvoice($invoice_id);
        
        $data['invoice_id'] = $invoice_id;
        $data['invoice_number'] = $invoice['invoice_number'];
        $data['total_amount'] = $invoice['total_amount'];
        $data['paid_amount'] = $this->model_pms_expense_invoice->getTotalPaidForInvoice($invoice_id);
        $data['due_amount'] = $data['total_amount'] - $data['paid_amount'];
        $data['payment_methods'] = $this->model_pms_expense_invoice->getPaymentMethods();
        $data['action'] = $this->url->link('account/pms_expense_invoices.savePayment', 'language=' . $this->config->get('config_language'));
        
        $this->response->setOutput($this->load->view('account/pms_expense_invoice_payment_form', $data));
    }

    /**
     * Save payment to invoice
     */
    public function savePayment(): void {
        $this->load->language('pms/expense_invoices');
        
        $json = [];
        
        if (!$this->customer->isLogged()) {
            $json['error'] = $this->language->get('error_permission');
        }
        
        if (!$json) {
            $this->load->model('pms/expense_invoice');
            
            $invoice_id = (int)$this->request->post['invoice_id'];
            $amount = (float)$this->request->post['amount'];
            $payment_date = $this->request->post['payment_date'];
            $payment_method = $this->request->post['payment_method'];
            $reference_number = $this->request->post['reference_number'] ?? '';
            $notes = $this->request->post['notes'] ?? '';
            
            // Validation
            if ($amount <= 0) {
                $json['error'] = 'Payment amount must be greater than 0';
            }
            
            $invoice = $this->model_pms_expense_invoice->getInvoice($invoice_id);
            $paid_so_far = $this->model_pms_expense_invoice->getTotalPaidForInvoice($invoice_id);
            $remaining = $invoice['total_amount'] - $paid_so_far;
            
            if ($amount > $remaining) {
                $json['error'] = 'Payment amount exceeds remaining due of ' . number_format($remaining, 2);
            }
            
            if (!$json) {
                $payment_data = [
                    'invoice_id'        => $invoice_id,
                    'amount'            => $amount,
                    'payment_date'      => $payment_date,
                    'payment_method'    => $payment_method,
                    'reference_number'  => $reference_number,
                    'notes'             => $notes
                ];
                
                $this->model_pms_expense_invoice->addInvoicePayment($payment_data);
                $json['success'] = $this->language->get('text_payment_success');
            }
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Delete payment from invoice
     */
    public function deletePayment(): void {
        $this->load->language('pms/expense_invoices');
        
        $json = [];
        
        if (!$this->customer->isLogged()) {
            $json['error'] = $this->language->get('error_permission');
        }
        
        if (!$json) {
            $this->load->model('pms/expense_invoice');
            
            $payment_id = isset($this->request->get['payment_id']) ? (int)$this->request->get['payment_id'] : 0;
            
            if ($payment_id) {
                $this->model_pms_expense_invoice->deleteInvoicePayment($payment_id);
                $json['success'] = true;
            } else {
                $json['error'] = 'Invalid payment ID';
            }
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
<?php
namespace Opencart\Catalog\Controller\Account;

class PmsMyApartments extends \Opencart\System\Engine\Controller {

    public function index(): void {
        // Check if logged in
        if (!$this->customer->isLogged()) {
            $this->session->data['redirect'] = $this->url->link('account/pms_my_apartments', 'language=' . $this->config->get('config_language'));
            $this->response->redirect($this->url->link('account/login', 'language=' . $this->config->get('config_language')));
        }
        
        // Check if user is Shareholder
        /*$customer_group_id = $this->customer->getGroupId();
        $shareholder_group_id = (int)$this->config->get('config_pms_shareholder_group_id');
        
        if ($customer_group_id != $shareholder_group_id) {
            $this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language')));
        }*/
        
        $this->load->language('pms/my_apartments');
        
        // Get shareholder info
        $this->load->model('pms/shareholder');
        $shareholder = $this->model_pms_shareholder->getShareholderByCustomerId($this->customer->getId());

        $project = $this->customer->getProject();
        
        if (!$shareholder) {
            $this->response->redirect($this->url->link('account/account', 'language=' . $this->config->get('config_language')));
        }
        
        // Get all apartments owned by this shareholder
        $apartments = $this->model_pms_shareholder->getShareholderApartments($project['project_id'], $shareholder['id']);
        
        // Check if apartment_id is specified in URL
        $apartment_id = isset($this->request->get['apartment_id']) ? (int)$this->request->get['apartment_id'] : 0;
        
        // If only one apartment and no specific apartment selected, go directly to details
        if (count($apartments) == 1 && !$apartment_id) {
            $this->response->redirect($this->url->link('account/pms_my_apartments', 'language=' . $this->config->get('config_language') . '&apartment_id=' . $apartments[0]['id']) . '&customer_token=' . $this->session->data['customer_token']);
            return;
        }
        
        // If multiple apartments and no specific apartment selected, show list
        if (count($apartments) > 1 && !$apartment_id) {
            $this->list($apartments, $shareholder);
            return;
        }
        
        // If specific apartment selected, show details
        if ($apartment_id) {
            $this->view($apartment_id, $apartments, $shareholder);
            return;
        }
        
        // Fallback - no apartments found
        $this->noApartments();
    }
    
    private function list(array $apartments, array $shareholder): void {
        $this->document->setTitle($this->language->get('heading_list'));
        
        $data['heading_title'] = $this->language->get('heading_list');
        $data['shareholder_name'] = $shareholder['firstname'] . ' ' . $shareholder['lastname'];
        $data['shareholder_code'] = $shareholder['shareholder_code'];
        
        $data['apartments'] = [];
        
        foreach ($apartments as $apartment) {
            $data['apartments'][] = [
                'apartment_id'       => $apartment['id'],
                'apartment_number'   => $apartment['apartment_number'],
                'floor_number'       => $apartment['floor_number'],
                'area_sqft'          => $apartment['area_sqft'],
                'bedrooms'           => $apartment['bedrooms'],
                'bathrooms'          => $apartment['bathrooms'],
                'balcony'            => $apartment['balcony'] ? 'Yes' : 'No',
                'parking_slot'       => $apartment['parking_slot'] ?: 'Not Assigned',
                'fixed_maintenance_fee' => $apartment['fixed_maintenance_fee'],
                'view_link'          => $this->url->link('account/pms_my_apartments', 'language=' . $this->config->get('config_language') . '&apartment_id=' . $apartment['id'] . '&customer_token=' . $this->session->data['customer_token'])
            ];
        }
        
        $data['back'] = $this->url->link('account/shareholder', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        
        $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
        
        $this->response->setOutput($this->load->view('account/pms_my_apartments_list', $data));
    }
    
    private function view(int $apartment_id, array $apartments, array $shareholder): void {
        // Verify that this apartment belongs to the shareholder
        $valid = false;
        $selected_apartment = null;
        
        foreach ($apartments as $apartment) {
            if ($apartment['id'] == $apartment_id) {
                $valid = true;
                $selected_apartment = $apartment;
                break;
            }
        }
        
        if (!$valid) {
            $this->response->redirect($this->url->link('account/pms_my_apartments', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']));
            return;
        }
        
        $this->document->setTitle($this->language->get('heading_view'));
        
        $data['heading_title'] = $this->language->get('heading_view');
        $data['shareholder_name'] = $shareholder['firstname'] . ' ' . $shareholder['lastname'];
        $data['shareholder_code'] = $shareholder['shareholder_code'];
        
        $data['apartment'] = [
            'apartment_id'          => $selected_apartment['id'],
            'apartment_number'      => $selected_apartment['apartment_number'],
            'floor_number'          => $selected_apartment['floor_number'],
            'area_sqft'             => $selected_apartment['area_sqft'] ? number_format($selected_apartment['area_sqft'], 2) . ' sq ft' : 'N/A',
            'bedrooms'              => $selected_apartment['bedrooms'] ?: 'N/A',
            'bathrooms'             => $selected_apartment['bathrooms'] ?: 'N/A',
            'balcony'               => $selected_apartment['balcony'] ? 'Yes' : 'No',
            'parking_slot'          => $selected_apartment['parking_slot'] ?: 'Not Assigned',
            'fixed_maintenance_fee' => number_format($selected_apartment['fixed_maintenance_fee'], 2),
            'notes'                 => nl2br($selected_apartment['notes']) ?: 'No additional notes',
            'created_at'            => date('d M Y', strtotime($selected_apartment['created_at'])),
            'updated_at'            => $selected_apartment['updated_at'] ? date('d M Y', strtotime($selected_apartment['updated_at'])) : 'N/A'
        ];
        
        // Get other apartments for navigation (if multiple)
        $data['other_apartments'] = [];
        if (count($apartments) > 1) {
            foreach ($apartments as $apt) {
                if ($apt['id'] != $apartment_id) {
                    $data['other_apartments'][] = [
                        'apartment_id'     => $apt['id'],
                        'apartment_number' => $apt['apartment_number'],
                        'link'             => $this->url->link('account/pms_my_apartments', 'language=' . $this->config->get('config_language') . '&apartment_id=' . $apt['id'] . '&customer_token=' . $this->session->data['customer_token'])
                    ];
                }
            }
        }
        
        $data['has_multiple'] = count($apartments) > 1;
        $data['back'] = (count($apartments) > 1) 
            ? $this->url->link('account/pms_my_apartments', 'language=' . $this->config->get('config_language'))
            : $this->url->link('account/pms_dashboard', 'language=' . $this->config->get('config_language'));
        
        $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
        
        $this->response->setOutput($this->load->view('account/pms_my_apartment_view', $data));
    }
    
    private function noApartments(): void {
        $this->document->setTitle($this->language->get('heading_no_apartment'));
        
        $data['heading_title'] = $this->language->get('heading_no_apartment');
        $data['message'] = $this->language->get('text_no_apartment');
        $data['back'] = $this->url->link('account/shareholder', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
        
        $data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
        
        $this->response->setOutput($this->load->view('account/pms_no_apartment', $data));
    }
}
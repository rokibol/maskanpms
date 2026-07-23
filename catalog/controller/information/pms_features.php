<?php
namespace Opencart\Catalog\Controller\Information;

class PmsFeatures extends \Opencart\System\Engine\Controller {

    public function index(): void {
        $this->load->language('information/pms_features');

        $this->document->setTitle($this->language->get('heading_title'));
        $this->document->setDescription($this->language->get('meta_description'));

        $data['breadcrumbs'] = [];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home', 'language=' . $this->config->get('config_language'))
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('information/pms_features', 'language=' . $this->config->get('config_language'))
        ];

        // Set active menu
        $data['active_features'] = 'active';

        // Get top menu
        $data['top_menu'] = $this->load->controller('common/pms_top_menu');

        // Features data
        $data['features'] = $this->getFeatures();

        $data['header'] = $this->load->controller('common/header');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('information/pms_features', $data));
    }

    private function getFeatures(): array {
        return [
            'project_management' => [
                'icon' => 'fa-building',
                'title' => 'Project Management',
                'description' => 'Create and manage multiple building projects with ease. Track all project details, status, and progress in one centralized dashboard.',
                'features' => [
                    'Multi-project support',
                    'Project status tracking',
                    'Completion percentage',
                    'Project timeline management'
                ]
            ],
            'shareholder_management' => [
                'icon' => 'fa-users',
                'title' => 'Shareholder Management',
                'description' => 'Efficiently manage all shareholders with ownership tracking, share transfers, and complete financial history.',
                'features' => [
                    'Shareholder registration',
                    'Ownership percentage tracking',
                    'Share transfer history',
                    'Credit balance management'
                ]
            ],
            'expense_tracking' => [
                'icon' => 'fa-receipt',
                'title' => 'Expense Tracking',
                'description' => 'Record and track all project expenses with invoice management, category organization, and document attachments.',
                'features' => [
                    'Invoice management',
                    'Category-based expenses',
                    'Document upload',
                    'Supplier management'
                ]
            ],
            'payment_management' => [
                'icon' => 'fa-credit-card',
                'title' => 'Payment Management',
                'description' => 'Streamline payment collection and allocation with automated calculations and real-time tracking.',
                'features' => [
                    'Payment allocation',
                    'Bulk payment processing',
                    'Payment history',
                    'Credit balance tracking'
                ]
            ],
            'billing_system' => [
                'icon' => 'fa-file-invoice',
                'title' => 'Billing System',
                'description' => 'Generate monthly bills for utilities, maintenance, and services with automatic distribution to shareholders.',
                'features' => [
                    'Monthly bill generation',
                    'Utility bill management',
                    'Equal distribution',
                    'Payment status tracking'
                ]
            ],
            'apartment_management' => [
                'icon' => 'fa-building',
                'title' => 'Apartment Management',
                'description' => 'Manage all apartments with detailed information including area, rooms, maintenance fees, and ownership assignment.',
                'features' => [
                    'Apartment registration',
                    'Unit details management',
                    'Maintenance fee setup',
                    'Ownership assignment'
                ]
            ],
            'reporting' => [
                'icon' => 'fa-chart-line',
                'title' => 'Reports & Analytics',
                'description' => 'Access comprehensive reports and analytics to make informed decisions about your property investments.',
                'features' => [
                    'Financial reports',
                    'Shareholder ledgers',
                    'Expense summaries',
                    'Collection reports'
                ]
            ],
            'subscription_management' => [
                'icon' => 'fa-chart-simple',
                'title' => 'Subscription Management',
                'description' => 'Flexible subscription plans with monthly and yearly options. Start with a free trial to explore all features.',
                'features' => [
                    'Free trial period',
                    'Monthly & yearly plans',
                    'Flexible duration',
                    'Easy renewal'
                ]
            ]
        ];
    }
}
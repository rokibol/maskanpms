<?php
namespace Opencart\Catalog\Controller\Information;

class PmsFaq extends \Opencart\System\Engine\Controller {

    public function index(): void {
        $this->load->language('information/pms_faq');

        $this->document->setTitle($this->language->get('heading_title'));

        $data['breadcrumbs'] = [];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home', 'language=' . $this->config->get('config_language'))
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('information/pms_faq', 'language=' . $this->config->get('config_language'))
        ];

        $data['active_faq'] = 'active';

        $data['top_menu'] = $this->load->controller('common/pms_top_menu');

        $data['faqs'] = $this->getFaqs();

        $data['header'] = $this->load->controller('common/header');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('information/pms_faq', $data));
    }

    private function getFaqs(): array {
        return [
            'general' => [
                'title' => 'General Questions',
                'items' => [
                    [
                        'question' => 'What is Maskan PMS?',
                        'answer' => 'Maskan PMS is a comprehensive Property Management System designed to help building project managers, accountants, and shareholders manage expenses, payments, bills, and all property-related activities efficiently.'
                    ],
                    [
                        'question' => 'Who can use Maskan PMS?',
                        'answer' => 'Maskan PMS is designed for building project managers, accountants, shareholders, and property developers. It supports multiple user roles with different access levels.'
                    ],
                    [
                        'question' => 'Is there a free trial?',
                        'answer' => 'Yes! We offer a 30-day free trial with full access to all features. No credit card required to start.'
                    ]
                ]
            ],
            'subscription' => [
                'title' => 'Subscription & Pricing',
                'items' => [
                    [
                        'question' => 'How much does Maskan PMS cost?',
                        'answer' => 'We offer flexible plans starting at 1,000 BDT per month. You can choose monthly or yearly billing. Yearly plans offer significant savings.'
                    ],
                    [
                        'question' => 'Can I cancel my subscription anytime?',
                        'answer' => 'Yes, you can cancel your subscription at any time. Your access will continue until the end of your current billing period.'
                    ],
                    [
                        'question' => 'What payment methods do you accept?',
                        'answer' => 'We accept bKash, Nagad, Rocket, and bank transfers. All payments are processed securely.'
                    ]
                ]
            ],
            'features' => [
                'title' => 'Features & Usage',
                'items' => [
                    [
                        'question' => 'How do I add a new shareholder?',
                        'answer' => 'As a Manager, go to the Shareholders section, click "Add Shareholder", and fill in the required information. The shareholder will receive their login credentials.'
                    ],
                    [
                        'question' => 'How are expenses allocated?',
                        'answer' => 'Expenses are automatically allocated to all shareholders based on their ownership percentage. You can also manually allocate payments to specific shareholders.'
                    ],
                    [
                        'question' => 'Can I generate monthly bills for utilities?',
                        'answer' => 'Yes! Our billing system allows you to generate monthly bills for electricity, water, security, lift, and other services. Bills are distributed equally among all apartments.'
                    ]
                ]
            ],
            'security' => [
                'title' => 'Security & Data',
                'items' => [
                    [
                        'question' => 'Is my data secure?',
                        'answer' => 'Absolutely. We use SSL encryption for all data transmission, secure servers with daily backups, and role-based access control to protect your data.'
                    ],
                    [
                        'question' => 'Who can see my data?',
                        'answer' => 'Data visibility is role-based. Managers see all project data, Accountants see financial data, and Shareholders see only their own information.'
                    ],
                    [
                        'question' => 'Can I export my data?',
                        'answer' => 'Yes, you can export reports and data in various formats including PDF, Excel, and CSV for your records or external audits.'
                    ]
                ]
            ]
        ];
    }
}
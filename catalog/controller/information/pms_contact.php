<?php
namespace Opencart\Catalog\Controller\Information;

class PmsContact extends \Opencart\System\Engine\Controller {

    public function index(): void {
        $this->load->language('information/pms_contact');

        $this->document->setTitle($this->language->get('heading_title'));

        $data['breadcrumbs'] = [];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home', 'language=' . $this->config->get('config_language'))
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('information/pms_contact', 'language=' . $this->config->get('config_language'))
        ];

        $data['active_contact'] = 'active';

        $data['top_menu'] = $this->load->controller('common/pms_top_menu');

        // Store contact info
        $data['address'] = $this->config->get('config_address');
        $data['email'] = $this->config->get('config_email');
        $data['telephone'] = $this->config->get('config_telephone');
        $data['fax'] = $this->config->get('config_fax');

        // For contact form
        if ($this->request->server['REQUEST_METHOD'] == 'POST' && isset($this->request->post['submit'])) {
            $this->sendContactEmail();
        }

        $data['action'] = $this->url->link('information/pms_contact', 'language=' . $this->config->get('config_language'));

        $data['header'] = $this->load->controller('common/header');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('information/pms_contact', $data));
    }

    private function sendContactEmail(): void {
        // Simple email sending - you can expand this
        $to = $this->config->get('config_email');
        $subject = 'Contact Form Submission from ' . $this->request->post['name'];
        $message = "Name: " . $this->request->post['name'] . "\n";
        $message .= "Email: " . $this->request->post['email'] . "\n";
        $message .= "Subject: " . $this->request->post['subject'] . "\n\n";
        $message .= "Message:\n" . $this->request->post['message'];

        $headers = 'From: ' . $this->request->post['email'] . "\r\n";
        $headers .= 'Reply-To: ' . $this->request->post['email'] . "\r\n";

        mail($to, $subject, $message, $headers);

        $this->session->data['success'] = 'Your message has been sent successfully.';
        $this->response->redirect($this->url->link('information/pms_contact', 'language=' . $this->config->get('config_language')));
    }
}
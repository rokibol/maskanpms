<?php
namespace Opencart\Catalog\Controller\Common;
/**
 * Class Content Top
 *
 * Can be called from $this->load->controller('common/content_top');
 *
 * @package Opencart\Catalog\Controller\Common
 */
class ContentTop extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return string
	 */
	public function index(): string {
		// Layout
		$this->load->model('design/layout');

		if (isset($this->request->get['route'])) {
			$route = (string)$this->request->get['route'];
		} else {
			$route = 'common/home';
		}

		$layout_id = 0;
		$data = array();

		if($this->customer->isLogged()) {
			$project_info = $this->customer->getProject();
			$data['project_name'] = $project_info['name'];
		}

		// ======================================================
        // SUBSCRIPTION EXPIRY WARNING
        // ======================================================
        if ($this->customer->isLogged()) {
            $customer_group_id = $this->customer->getGroupId();
            $manager_group_id = 1; //(int)$this->config->get('config_pms_manager_group_id');
            $accountant_group_id = 2; //(int)$this->config->get('config_pms_accountant_group_id');
            
            // Only show for Manager and Accountant
            if ($customer_group_id == $manager_group_id || $customer_group_id == $accountant_group_id) {
                $this->load->model('pms/project');
                $project = $this->customer->getProject();
                
                if ($project) {
                    $this->load->model('pms/project_subscription');
                    
                    // Check if subscription is active
                    $is_active = $this->model_pms_project_subscription->isSubscriptionActive($project['project_id']);

                    $expiry_days = $this->model_pms_project_subscription->getExpiryDays($project['project_id']);
					
                    // Check for admin override
                    $this->load->model('pms/project_subscription');
                    $subscription = $this->model_pms_project_subscription->getSubscriptionByProject($project['project_id']);

                    $has_override = isset($subscription['admin_override']) && $subscription['admin_override'] == 1;
                    
                    $data['subscription_warning'] = false;
                    $data['subscription_expiry_days'] = 0;
                    $data['subscription_end_date'] = date('d F Y', strtotime($subscription['end_date'] ?? ''));
                    $data['subscription_has_override'] = $has_override;
                    
                    // Show warning if active and days remaining <= 7
                    if ($is_active && $expiry_days > 0 && $expiry_days <= 7) {
                        $data['subscription_warning'] = true;
                        $data['subscription_expiry_days'] = $expiry_days;
                        $data['subscription_end_date'] = date('d F Y', strtotime($subscription['end_date'] ?? ''));
						$data['subscription_renew_link'] = $this->url->link('account/pms_subscription', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
                    }
                }
            }
        }

		// Category
		/*if ($route == 'product/category' && isset($this->request->get['path'])) {
			$this->load->model('catalog/category');

			$path = explode('_', (string)$this->request->get['path']);

			$layout_id = $this->model_catalog_category->getLayoutId((int)end($path));
		}

		// Product
		if ($route == 'product/product' && isset($this->request->get['product_id'])) {
			$this->load->model('catalog/product');

			$layout_id = $this->model_catalog_product->getLayoutId((int)$this->request->get['product_id']);
		}

		// Manufacturer
		if ($route == 'product/manufacturer.info' && isset($this->request->get['manufacturer_id'])) {
			$this->load->model('catalog/manufacturer');

			$layout_id = $this->model_catalog_manufacturer->getLayoutId((int)$this->request->get['manufacturer_id']);
		}

		// Information
		if ($route == 'information/information' && isset($this->request->get['information_id'])) {
			$this->load->model('catalog/information');

			$layout_id = $this->model_catalog_information->getLayoutId((int)$this->request->get['information_id']);
		}

		// Blog
		if ($route == 'cms/blog' && isset($this->request->get['topic_id'])) {
			$this->load->model('cms/topic');

			$layout_id = $this->model_cms_topic->getLayoutId((int)$this->request->get['topic_id']);
		}

		if ($route == 'cms/blog.info' && isset($this->request->get['article_id'])) {
			$this->load->model('cms/article');

			$layout_id = $this->model_cms_article->getLayoutId((int)$this->request->get['article_id']);
		}

		if (!$layout_id) {
			$layout_id = $this->model_design_layout->getLayout($route);
		}

		if (!$layout_id) {
			$layout_id = $this->config->get('config_layout_id');
		}

		// Extension
		$this->load->model('setting/module');

		$data['modules'] = [];

		$modules = $this->model_design_layout->getModules($layout_id, 'content_top');

		foreach ($modules as $module) {
			$part = explode('.', $module['code']);

			if (isset($part[1]) && $this->config->get('module_' . $part[1] . '_status')) {
				$module_data = $this->load->controller('extension/' . $part[0] . '/module/' . $part[1]);

				if ($module_data) {
					$data['modules'][] = $module_data;
				}
			}

			if (isset($part[2])) {
				$setting_info = $this->model_setting_module->getModule((int)$part[2]);

				if ($setting_info && $setting_info['status']) {
					$output = $this->load->controller('extension/' . $part[0] . '/module/' . $part[1], $setting_info);

					if ($output) {
						$data['modules'][] = $output;
					}
				}
			}
		}*/

		return $this->load->view('common/content_top', $data);
	}
}

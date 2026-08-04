<?php
namespace Opencart\Admin\Controller\Startup;
/**
 * Class Event
 *
 * @package Opencart\Admin\Controller\Startup
 */
class Event extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		// Add events from the DB
		$this->load->model('setting/event');

		// Add to your event registration
		/*$this->model_setting_event->addEvent([
			'code' => 'pms_subscription_check',
			'description' => 'Check subscription status before loading pages',
			'trigger' => 'catalog/controller//before',
			'action' => 'startup/pms_subscription',
			'status' => 1,
			'sort_order' => 0
		]);*/

		$results = $this->model_setting_event->getEvents();

		foreach ($results as $result) {
			if ($result['status']) {
				$part = explode('/', $result['trigger']);

				if ($part[0] == 'admin') {
					array_shift($part);

					$this->event->register(implode('/', $part), new \Opencart\System\Engine\Action($result['action']), $result['sort_order']);
				}

				if ($part[0] == 'system') {
					$this->event->register($result['trigger'], new \Opencart\System\Engine\Action($result['action']), $result['sort_order']);
				}
			}
		}
	}
}

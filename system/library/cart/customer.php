<?php
namespace Opencart\System\Library\Cart;
/**
 * Class Customer
 *
 * @package Opencart\System\Library\Cart
 */
class Customer {
	/**
	 * @var object
	 */
	private object $db;
	/**
	 * @var object
	 */
	private object $config;
	/**
	 * @var object
	 */
	private object $request; // Do not add namespace as it stops devs being able to extend classes
	/**
	 * @var object
	 */
	private object $session;
	/**
	 * @var int
	 */
	private int $customer_id = 0;
	/**
	 * @var string
	 */
	private string $firstname = '';
	/**
	 * @var string
	 */
	private string $lastname = '';
	/**
	 * @var int
	 */
	private int $customer_group_id = 0;

	private array $customer_group = [];
	/**
	 * @var string
	 */
	private string $email = '';
	/**
	 * @var string
	 */
	private string $telephone = '';
	/**
	 * @var bool
	 */
	private bool $newsletter = false;
	/**
	 * @var bool
	 */
	private bool $safe = false;
	/**
	 * @var bool
	 */
	private bool $commenter = false;

	private array $project = [];

	private array $manager = [];

	private array $accountant = [];

	/**
	 * Constructor
	 *
	 * @param \Opencart\System\Engine\Registry $registry
	 */
	public function __construct(\Opencart\System\Engine\Registry $registry) {
		$this->db = $registry->get('db');
		$this->config = $registry->get('config');
		$this->request = $registry->get('request');
		$this->session = $registry->get('session');

		if (isset($this->session->data['customer_id'])) {
			$customer_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "customer` WHERE `customer_id` = '" . (int)$this->session->data['customer_id'] . "' AND `status` = '1'");

			if ($customer_query->num_rows) {
				$this->customer_id = $customer_query->row['customer_id'];
				$this->firstname = $customer_query->row['firstname'];
				$this->lastname = $customer_query->row['lastname'];
				$this->customer_group_id = $customer_query->row['customer_group_id'];
				$this->email = $customer_query->row['email'];
				$this->telephone = $customer_query->row['telephone'];
				$this->newsletter = $customer_query->row['newsletter'];
				$this->safe = (bool)$customer_query->row['safe'];
				$this->commenter = (bool)$customer_query->row['commenter'];
				$this->project = $this->getProjectByCustomerId($this->customer_id);
				$this->manager = $this->getManagerByCustomerId($this->customer_id);
				$this->customer_group = $this->getCustomerGroup($this->customer_group_id);

				$this->db->query("UPDATE `" . DB_PREFIX . "customer` SET `language_id` = '" . (int)$this->config->get('config_language_id') . "', `ip` = '" . $this->db->escape(oc_get_ip()) . "' WHERE `customer_id` = '" . (int)$this->customer_id . "'");
			} else {
				$this->logout();
			}
		}
	}

	/**
	 * Login
	 *
	 * @param string $email
	 * @param string $password
	 * @param bool   $override
	 *
	 * @return bool
	 *
	 * @example
	 *
	 * $login = $this->customer->login($email, $password, $override);
	 */
	public function login(string $email, string $password, bool $override = false): bool {
		$customer_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "customer` WHERE LCASE(`email`) = '" . $this->db->escape(oc_strtolower($email)) . "' AND `status` = '1'");

		if ($customer_query->row) {
			if (!$override) {
				if (password_verify($password, $customer_query->row['password'])) {
					$rehash = password_needs_rehash($customer_query->row['password'], PASSWORD_DEFAULT);
				} elseif (isset($customer_query->row['salt']) && $customer_query->row['password'] == sha1($customer_query->row['salt'] . sha1($customer_query->row['salt'] . sha1($password)))) {
					$rehash = true;
				} elseif ($customer_query->row['password'] == md5($password)) {
					$rehash = true;
				} else {
					return false;
				}

				if ($rehash) {
					$this->db->query("UPDATE `" . DB_PREFIX . "customer` SET `password` = '" . $this->db->escape(password_hash($password, PASSWORD_DEFAULT)) . "' WHERE `customer_id` = '" . (int)$customer_query->row['customer_id'] . "'");
				}
			}

			$this->session->data['customer_id'] = $customer_query->row['customer_id'];

			$this->customer_id = $customer_query->row['customer_id'];
			$this->firstname = $customer_query->row['firstname'];
			$this->lastname = $customer_query->row['lastname'];
			$this->customer_group_id = $customer_query->row['customer_group_id'];
			$this->email = $customer_query->row['email'];
			$this->telephone = $customer_query->row['telephone'];
			$this->newsletter = $customer_query->row['newsletter'];
			$this->safe = (bool)$customer_query->row['safe'];
			$this->commenter = (bool)$customer_query->row['commenter'];
			$this->project = $this->getProjectByCustomerId($this->customer_id);
			$this->manager = $this->getManagerByCustomerId($this->customer_id);
			$this->customer_group = $this->getCustomerGroup($this->customer_group_id);

			$this->db->query("UPDATE `" . DB_PREFIX . "customer` SET `language_id` = '" . (int)$this->config->get('config_language_id') . "', `ip` = '" . $this->db->escape(oc_get_ip()) . "' WHERE `customer_id` = '" . (int)$this->customer_id . "'");

			return true;
		} else {
			return false;
		}
	}

	private function getCustomerGroup(int $customer_group_id): array {
		$query = $this->db->query("SELECT DISTINCT * FROM `" . DB_PREFIX . "customer_group` `cg` LEFT JOIN `" . DB_PREFIX . "customer_group_description` `cgd` ON (`cg`.`customer_group_id` = `cgd`.`customer_group_id`) WHERE `cg`.`customer_group_id` = '" . (int)$customer_group_id . "' AND `cgd`.`language_id` = '" . (int)$this->config->get('config_language_id') . "'");

		return $query->row;
	}

	public function getProject() {
		if($this->customer_group_id == 1 || $this->customer_group_id == 2) {
			return $this->manager;
		} else {
			return $this->project;
		}
	}

	public function getManager() {
		return $this->manager;
	}

	private function getProjectByCustomerId(int $customer_id): array {
        $query = $this->db->query("
            SELECT p.* , share_occupied.*, ps.*
            FROM `" . DB_PREFIX . "pms_project` p
            INNER JOIN `" . DB_PREFIX . "customer` c ON c.project_id = p.project_id
			INNER JOIN (SELECT project_id, SUM(ownership_units) AS occupied_total_units FROM `" . DB_PREFIX . "shareholders` GROUP BY project_id) AS `share_occupied` ON `share_occupied`.project_id = p.project_id
            INNER JOIN `" . DB_PREFIX . "project_status` ps ON ps.status_code = p.status
			WHERE c.customer_id = '" . (int)$customer_id . "'
            AND p.is_deleted = 0
            AND p.is_active = 1
        ");
        
        return $query->row;
    }

	private function getManagerByCustomerId(int $customer_id): array {
        $query = $this->db->query("
            SELECT p.* , ps.*
            FROM `" . DB_PREFIX . "pms_project` p
            INNER JOIN `" . DB_PREFIX . "customer` c ON c.project_id = p.project_id
			INNER JOIN `" . DB_PREFIX . "project_status` ps ON ps.status_code = p.status
			WHERE c.customer_id = '" . (int)$customer_id . "'
            AND p.is_deleted = 0
            AND p.is_active = 1
        ");
        
        return $query->row;
    }

	/**
	 * Logout
	 *
	 * @return void
	 *
	 * @example
	 *
	 * $this->customer->logout();
	 */
	public function logout(): void {
		unset($this->session->data['customer_id']);

		$this->customer_id = 0;
		$this->firstname = '';
		$this->lastname = '';
		$this->customer_group_id = 0;
		$this->email = '';
		$this->telephone = '';
		$this->newsletter = false;
		$this->safe = false;
		$this->commenter = false;
	}

	/**
	 * Is Logged
	 *
	 * @return bool
	 *
	 * @example
	 *
	 * $logged = $this->customer->isLogged();
	 */
	public function isLogged(): bool {
		return $this->customer_id ? true : false;
	}

	/**
	 * Get Id
	 *
	 * @return int
	 *
	 * @example
	 *
	 * $customer_id = $this->customer->getId();
	 */
	public function getId(): int {
		return $this->customer_id;
	}

	/**
	 * Get First Name
	 *
	 * @return string
	 *
	 * @example
	 *
	 * $firstname = $this->customer->getFirstName();
	 */
	public function getFirstName(): string {
		return $this->firstname;
	}

	/**
	 * Get Last Name
	 *
	 * @return string
	 *
	 * @example
	 *
	 * $lastname = $this->customer->getLastName();
	 */
	public function getLastName(): string {
		return $this->lastname;
	}

	public function getFullName(): string {
		return $this->firstname . ' ' . $this->lastname;
	}

	/**
	 * Get Group Id
	 *
	 * @return int
	 *
	 * @example
	 *
	 * $group_id = $this->customer->getGroupId();
	 */
	public function getGroupId(): int {
		return $this->customer_group_id;
	}

	public function getGroup() {
		return $this->customer_group;
	}

	/**
	 * Get Email
	 *
	 * @return string
	 *
	 * @example
	 *
	 * $customer = $this->customer->getEmail();
	 */
	public function getEmail(): string {
		return $this->email;
	}

	/**
	 * Get Telephone
	 *
	 * @return string
	 *
	 * @example
	 *
	 * $telephone = $this->customer->getTelephone();
	 */
	public function getTelephone(): string {
		return $this->telephone;
	}

	/**
	 * Get Newsletter
	 *
	 * @return bool
	 *
	 * @example
	 *
	 * $newsletter = $this->customer->getNewsletter();
	 */
	public function getNewsletter(): bool {
		return $this->newsletter;
	}

	/**
	 * Is Safe
	 *
	 * @return bool
	 *
	 * @example
	 *
	 * $safe = $this->customer->isSafe();
	 */
	public function isSafe(): bool {
		return $this->safe;
	}

	/**
	 * Is Commenter
	 *
	 * @return bool
	 *
	 * @example
	 *
	 * $customer = $this->customer->isCommenter();
	 */
	public function isCommenter(): bool {
		return $this->commenter;
	}

	/**
	 * Get Address Id
	 *
	 * @return int address record
	 *
	 * @example
	 *
	 * $address_id = $this->customer->getAddressId();
	 */
	public function getAddressId(): int {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "address` WHERE `customer_id` = '" . (int)$this->customer_id . "' AND `default` = '1'");

		if ($query->num_rows) {
			return (int)$query->row['address_id'];
		} else {
			return 0;
		}
	}

	/**
	 * Get Balance
	 *
	 * @return float total number of balance records
	 *
	 * @example
	 *
	 * $balance = $this->customer->getBalance();
	 */
	public function getBalance(): float {
		$query = $this->db->query("SELECT SUM(`amount`) AS `total` FROM `" . DB_PREFIX . "customer_transaction` WHERE `customer_id` = '" . (int)$this->customer_id . "'");

		return (float)$query->row['total'];
	}

	/**
	 * Get Reward Points
	 *
	 * @return float total number of reward point records
	 *
	 * @example
	 *
	 * $reward_total = $this->customer->getRewardPoints();
	 */
	public function getRewardPoints(): float {
		$query = $this->db->query("SELECT SUM(`points`) AS `total` FROM `" . DB_PREFIX . "customer_reward` WHERE `customer_id` = '" . (int)$this->customer_id . "'");

		return (float)$query->row['total'];
	}
}

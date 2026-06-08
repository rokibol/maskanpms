<?php
namespace Opencart\Catalog\Model\Pms;

class ExpenseCategory extends \Opencart\System\Engine\Model {

    /**
     * Get all expense categories
     * @return array
     */
    public function getCategories(): array {
        $sql = "
            WITH RECURSIVE category_tree AS (
                SELECT 
                    id,
                    parent_id,
                    category_name,
                    CAST(category_name AS CHAR(1000)) AS path,
                    0 AS level
                FROM " . DB_PREFIX . "expense_categories
                WHERE parent_id = 0

                UNION ALL
                SELECT 
                    c.id,
                    c.parent_id,
                    c.category_name,
                    CONCAT(ct.path, ' → ', c.category_name) AS path,
                    ct.level + 1 AS level
                FROM " . DB_PREFIX . "expense_categories c
                INNER JOIN category_tree ct 
                    ON c.parent_id = ct.id
            )

            SELECT *
            FROM category_tree
            ORDER BY path
        ";

        $query = $this->db->query($sql);
        
        /*$categories = [];
        foreach ($query->rows as $category) {
            $categories[] = $category;
        }*/
        
        return $query->rows;
    }
    
    /**
     * Get category by ID
     * @param int $category_id
     * @return array
     */
    public function getCategory(int $category_id): array {
        $query = $this->db->query("
            SELECT * 
            FROM `" . DB_PREFIX . "expense_categories` 
            WHERE id = '" . (int)$category_id . "'
        ");
        
        return $query->row;
    }
    
    /**
     * Add a new category
     * @param array $data
     * @return int
     */
    public function addCategory(array $data): int {
        $this->db->query("
            INSERT INTO `" . DB_PREFIX . "expense_categories` 
            SET `parent_id` = '" . (int)$data['parent_id'] . "',
                `category_name` = '" . $this->db->escape($data['category_name']) . "'
        ");
        
        return $this->db->getLastId();
    }
    
    /**
     * Update category
     * @param int $category_id
     * @param array $data
     */
    public function updateCategory(int $category_id, array $data): void {
        $this->db->query("
            UPDATE `" . DB_PREFIX . "expense_categories` 
            SET `parent_id` = '" . (int)$data['parent_id'] . "',
                `category_name` = '" . $this->db->escape($data['category_name']) . "'
            WHERE `id` = '" . (int)$category_id . "'
        ");
    }
    
    /**
     * Delete category
     * @param int $category_id
     */
    public function deleteCategory(int $category_id): void {
        // Check if category has expenses
        $check = $this->db->query("
            SELECT COUNT(*) as total 
            FROM `" . DB_PREFIX . "pms_expense` 
            WHERE category_id = '" . (int)$category_id . "'
        ");
        
        if ($check->row['total'] > 0) {
            throw new \Exception('Cannot delete category with existing expenses.');
        }
        
        $this->db->query("
            DELETE FROM `" . DB_PREFIX . "expense_categories` 
            WHERE id = '" . (int)$category_id . "'
        ");
    }
}
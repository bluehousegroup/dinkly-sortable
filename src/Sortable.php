<?php

namespace BluehouseGroup\DinklySortable;

use \Exception;

trait Sortable
{
    public function reorder(int $position)
    {
        if ($this->isNew()) {
            throw new Exception('An unsaved record cannot be reordered.');
        }

        $sort_column = $this->getSortableColumn();

        $property = $this->getRegistryValue($sort_column);

        if (!$property) {
            throw new Exception('Sort Column `' . $sort_column . '` does not exist on the following Model: ' . static::class . '.');
        }

        $getter = 'get' . $property;
        $current_position = (int)($this->$getter());

        if ($current_position === $position) {
            return;
        }

        $temp_position = $position * 10;
        if ($current_position < $position) {
            $temp_position += 1;
        } else {
            $temp_position -= 1;
        }

        $table = $this->getDBTable();

        $params = [$current_position, $temp_position];

        $conditions = [];
        $conditional_params = [];
        foreach ($this->getSortableFilters() as $column => $value) {
            $conditions[] = "`$column` = ?";
            $conditional_params[] = $value;
        }

        $where = '';
        if (!empty($conditions)) {
            $where = ' WHERE ' . implode($conditions, ' AND ');
        }

        $query = " UPDATE
                        `$table`
                    SET `$sort_column` = CASE
                        WHEN `$sort_column` = ? THEN
                            ?
                        ELSE
                            `$sort_column` * 10
                    END";

        if ($where) {
            $query .= $where;
        }

        $stmt = $this->db->prepare($query);
        $stmt->execute(array_merge($params, $conditional_params));

        $mysql_variable = '@' . $table . '_' . $sort_column;

        $this->db->query("SET $mysql_variable = 0");

        $query = "  UPDATE
                        `$table`
                    SET
                        `$sort_column` = ($mysql_variable := $mysql_variable + 1)";

        if ($where) {
            $query .= $where;
        }

        $query .= " ORDER BY `$sort_column`";

        $stmt = $this->db->prepare($query);
        $stmt->execute($conditional_params);

        $this->db->query("SET $mysql_variable = NULL");

        // Reinit object
        $this->init($this->getId());

        return $this;
    }

    public function getSortableFilters()
    {
        return [];
    }

    public function getSortableColumn()
    {
        if (isset(static::$sort_column)) {
            return static::$sort_column;
        }

        return 'position';
    }
}
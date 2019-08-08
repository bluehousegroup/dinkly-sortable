<?php

namespace BluehouseGroup\DinklySortable;

use \Exception;
use \ReflectionClass;

trait Sortable
{
    public function reorder(int $position)
    {
        if ($this->isNew()) {
            throw new Exception('You cannot reorder an unsaved record.');
        }

        $sort_column = $this->getSortableColumn();
        $fallback_column = $this->getSortableFallbackColumn();

        $property = $this->getRegistryValue($sort_column);
        $fallback_property = $this->getRegistryValue($fallback_column);

        if (!$property) {
            throw new Exception('Sort Column `' . $sort_column . '` does not exist on the following Model: ' . static::class . '.');
        } elseif (!$fallback_property) {
            throw new Exception('Fallback Sort Column `' . $fallback_column . '` does not exist on the following Model: ' . static::class . '.');
        }

        $table = $this->getDBTable();

        list($where, $conditional_params) = $this->buildWhereClause();

        $mysql_variable = '@' . $table . '_' . $sort_column;

        $this->db->query("SET $mysql_variable = 0");

        $query = "  UPDATE
                        `$table`
                    SET
                        `$sort_column` = ($mysql_variable := $mysql_variable + 1)";

        if ($where) {
            $query .= $where;
        }

        $query .= " ORDER BY -`$sort_column` DESC, `$fallback_column`";

        $stmt = $this->db->prepare($query);
        $stmt->execute($conditional_params);

        $this->db->query("SET $mysql_variable = NULL");

        $getter = 'get' . $property;

        $this->init($this->getId());
        $current_position = (int)($this->$getter());

        if ($current_position === $position) {
            return $this;
        }

        $temp_position = $position * 10;
        if ($current_position < $position) {
            $temp_position += 1;
        } else {
            $temp_position -= 1;
        }

        $params = [$current_position, $temp_position];

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

    public function getMaxSortableColumnValue()
    {
        $sort_column = $this->getSortableColumn();
        $property = $this->getRegistryValue($sort_column);
        if (!$property) {
            throw new Exception('Sort Column `' . $sort_column . '` does not exist on the following Model: ' . static::class . '.');
        }

        $table = $this->getDBTable();

        list($where, $conditional_params) = $this->buildWhereClause();

        $query = "SELECT MAX(`$sort_column`) FROM `$table`" . $where;

        $stmt = $this->db->prepare($query);
        $stmt->execute($conditional_params);
        $results = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

        return $results[0] ?? 0;
    }

    public function getSortableFilters()
    {
        return [];
    }

    public function getSortableColumn()
    {
        $reflection = new ReflectionClass($this);
        if (array_key_exists('SORT_COLUMN', $reflection->getConstants())) {
            return static::SORT_COLUMN;
        }

        return 'position';
    }

    public function getSortableFallbackColumn()
    {
        $reflection = new ReflectionClass($this);
        if (array_key_exists('SORT_FALLBACK_COLUMN', $reflection->getConstants())) {
            return static::SORT_FALLBACK_COLUMN;
        }

        return 'id';
    }

    private function buildWhereClause()
    {
        $conditions = [];
        $conditional_params = [];
        foreach ($this->getSortableFilters() as $column => $value) {
            $condition = "`$column` = ?";
            if ($value === false) {
                $condition = "($condition OR `$column` IS NULL)";
            }
            $conditions[] = $condition;
            $conditional_params[] = $value;
        }

        $where = '';
        if (!empty($conditions)) {
            $where = ' WHERE ' . implode(' AND ', $conditions);
        }

        return [$where, $conditional_params];
    }
}

<?php

namespace BluehouseGroup\DinklySortable;

interface SortableModel
{
    public function reorder(int $position);

    public function getSortableFilters();

    public function getSortableColumn();

    public function getSortableFallbackColumn();
}

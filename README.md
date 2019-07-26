# Sortable Model Module for Dinkly

## Description
This module provides a trait that adds sortable behavior to a Dinkly Data Model.

## Usage
To add sortable behaviour to your model you must:
1. Implement the `BluehouseGroup\DinklySortable\SortableModel` interface.
2. Use the trait `BluehouseGroup\DinklySortable\Sortable trait`.

Optionally, you may specify the name of the sorting column using a (public) static variable called `$sort_column`. If not specified, it will default to `position`.

```
use BluehouseGroup\DinklySortable\Sortable;
use BluehouseGroup\DinklySortable\SortableTrait;

class MyModel extends BaseMyModel implements SortableModel
{
    use Sortable;

    public static $sort_column = 'position';
}
```

You can reorder a model in the following fashion:
```
$model = new MyModel($db);
$model->init(500);
$model->reorder(4);
```
Note that you will not be able to reorder an unsaved record.

By default, the trait will try to reorder ALL the records in the table. If you'd like to reorder relative to a certain group of records you can override the `getSortableFilters` method in your model class:
```
public function getSortableFilters()
{
    return [
        'category_id' => $this->getCategoryId(),
        'is_deleted' => false,
    ];
}
```

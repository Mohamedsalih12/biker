<?php

namespace App\Support;

use PowerComponents\LivewirePowerGrid\Column;

class IndexNumberColumn
{
    public static function make(string $title, string $field, string $dataField = ''): Column
    {
        $column = Column::make($title, $field, $dataField)
            ->headerAttribute('text-end')
            ->bodyAttribute('text-end');

        $column->customContent = ['view' => 'components.index-number', 'params' => []];

        return $column;
    }
}

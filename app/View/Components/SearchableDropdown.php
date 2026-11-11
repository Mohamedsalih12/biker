<?php

namespace App\View\Components;

use Illuminate\View\Component;

class SearchableDropdown extends Component
{
    public $label;

    public $column;

    public $items;

    public $arrayval;

    public $fk;

    public $col;

    public function __construct($label, $column, $items, $fk = null, $arrayval = null, $col = null)
    {
        $this->label = $label;
        $this->column = $column;
        $this->items = $items;
        $this->arrayval = $arrayval;
        $this->col = $col;
        $this->fk = $fk;
    }

    public function render()
    {
        return view('components.searchable-dropdown');
    }
}

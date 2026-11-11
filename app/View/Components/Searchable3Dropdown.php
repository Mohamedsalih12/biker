<?php

namespace App\View\Components;

use Illuminate\View\Component;

class Searchable3Dropdown extends Component
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
        $this->fk = $fk;
        $this->col = $col;
    }

    public function render()
    {
        return view('components.searchable3-dropdown');
    }
}

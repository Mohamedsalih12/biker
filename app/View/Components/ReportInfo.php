<?php

namespace App\View\Components;

use Illuminate\View\Component;

class ReportInfo extends Component
{
    public $selection;

    public $label;

    public function __construct($selection, $label)
    {
        $this->selection = $selection;
        $this->label = $label;
    }

    public function render()
    {
        return view('components.report-info');
    }
}

<?php

namespace App\View\Components;

use Illuminate\View\Component;

class FilterMessage extends Component
{
    public $filter;

    public $message;

    public $style;

    public $clearUrl;

    public function __construct($clearUrl = null)
    {
        $this->filter = request('filter');
        $this->clearUrl = $clearUrl;

        $messages = [
            'active' => 'Showing Active Contracts',
            'expired' => 'Showing Expired Contracts',
            'upcoming' => 'Showing Upcoming Expiry Contracts',
            'all' => 'Showing All Contracts',
        ];

        $styles = [
            'active' => 'success',
            'expired' => 'danger',
            'upcoming' => 'warning',
            'all' => 'primary',
        ];

        $this->message = $messages[$this->filter] ?? null;
        $this->style = $styles[$this->filter] ?? 'info';
    }

    public function render()
    {
        return view('components.filter-message');
    }
}

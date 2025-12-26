<?php

namespace App\View\Components;

use Illuminate\View\Component;

class DashboardCard extends Component
{
    public $title;
    public $count;
    public $icon;
    public $class;

    public function __construct($title, $count, $icon, $class = 'material-icons')
    {
        $this->title = $title;
        $this->count = $count;
        $this->icon = $icon;
        $this->class = $class;
    }

    public function render()
    {
        return view('components.dashboard-card');
    }
}

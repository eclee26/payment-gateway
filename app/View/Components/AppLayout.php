<?php

namespace App\View\Components;

use App\Support\Navigation;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;
use Illuminate\View\View;

class AppLayout extends Component
{
    public function __construct(
        public ?string $title = null,
        public ?string $subtitle = null,
    ) {}

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('layouts.app', [
            'navigation' => Navigation::for(Auth::user()),
        ]);
    }
}

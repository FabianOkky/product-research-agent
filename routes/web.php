<?php

use App\Livewire\Dashboard;
use App\Livewire\Research\Create;
use App\Livewire\Research\History;
use App\Livewire\Research\Show;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', Dashboard::class)->name('dashboard');
});

Route::middleware(['auth'])->group(function () {
    Route::livewire('research', History::class)->name('research.history');
    Route::livewire('research/create', Create::class)->name('research.create');
    Route::livewire('research/{job}', Show::class)->name('research.show');
});

require __DIR__.'/settings.php';

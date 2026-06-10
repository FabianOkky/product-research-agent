<?php

arch('research agents implement the AI Agent contract')
    ->expect('App\Ai\Agents')
    ->toImplement('Laravel\Ai\Contracts\Agent');

arch('service clients are plain classes')
    ->expect('App\Services')
    ->toBeClasses();

arch('queue jobs are queueable')
    ->expect('App\Jobs')
    ->toImplement('Illuminate\Contracts\Queue\ShouldQueue');

arch('no debugging helpers are left behind')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();

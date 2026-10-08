<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';

// Last: contains the /{workspace}/... catch-all prefix.
require __DIR__.'/workspace.php';

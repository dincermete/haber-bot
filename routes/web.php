<?php

use Illuminate\Support\Facades\Route;

Route::permanentRedirect('/admin', '/');
Route::permanentRedirect('/admin/{path}', '/{path}')->where('path', '.*');

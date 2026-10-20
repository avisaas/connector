<?php

use AviSaaS\Connector\Http\Controllers\Connector;
use Illuminate\Support\Facades\Route;

Route::prefix('avisaas/connector')->group(function (): void {
    Route::match(['get', 'post'], '/', [Connector::class, 'index']);
});

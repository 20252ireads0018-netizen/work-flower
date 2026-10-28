<?php

use Illuminate\Support\Facades\Route;

Route::get('/plans', function () {
    return response()->json(['ok' => true]); // troque pelo seu controller do Asaas
});

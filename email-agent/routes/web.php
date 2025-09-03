<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

// Authentication Routes
Auth::routes();

// Dashboard (protected)
Route::get('/', function () {
    if (Auth::check()) {
        return view('dashboard');
    }
    return redirect()->route('login');
})->name('dashboard');

// Email Management Routes
Route::middleware('auth')->group(function () {
    Route::get('/inbox', function () {
        return view('inbox');
    })->name('inbox');
    
    Route::get('/accounts', function () {
        return view('accounts.index');
    })->name('accounts.index');
    
    Route::get('/accounts/create', function () {
        return view('accounts.create');
    })->name('accounts.create');
    
    Route::get('/accounts/{account}/edit', function ($account) {
        return view('accounts.edit', compact('account'));
    })->name('accounts.edit');
    
    Route::get('/rules', function () {
        return view('rules.index');
    })->name('rules.index');
    
    Route::get('/rules/create', function () {
        return view('rules.create');
    })->name('rules.create');
    
    Route::get('/escalations', function () {
        return view('escalations.index');
    })->name('escalations.index');
    
    Route::get('/statistics', function () {
        return view('statistics');
    })->name('statistics');
});

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

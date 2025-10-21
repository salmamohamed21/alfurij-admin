<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AdminController\AdminController;

/***
 * 
 * 
 * 0 For Users
 * 1 For Admin
 * 
 */
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    /**
     * **********************************************************************************************************
     * **************************Ctreating Routes For Admin******************************************************
     * **********************************************************************************************************
     */
//    Route::post('/create_admin_account',action: [AdminController::class,'CreateAdminAccount']);For Example

    });

Route::middleware(['auth:sanctum', 'user'])->group(function () {
/**
 * **********************************************************************************************************
 * **************************Ctreating Routes For User******************************************************
 * **********************************************************************************************************
 */



});
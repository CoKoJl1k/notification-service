<?php

use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::post('/notifications/send', [NotificationController::class, 'send']);
Route::get('/notifications/subscriber/{recipientId}', [NotificationController::class, 'subscriberHistory']);
Route::get('/notifications/{id}', [NotificationController::class, 'show']);

<?php

use Illuminate\Support\Facades\Route;
use HeyGeeks\SecureFileTransfer\Http\Controllers\SecureTransferController;
use HeyGeeks\SecureFileTransfer\Http\Middleware\SecureTransferAuthMiddleware;

// Routes that require authentication
Route::middleware([SecureTransferAuthMiddleware::class])->group(function () {
    Route::post('/secure-transfer/upload', [SecureTransferController::class, 'upload']);
    Route::get('/secure-transfer/download/{id}', [SecureTransferController::class, 'download']);
    Route::post('/secure-transfer/request-url/{id}', [SecureTransferController::class, 'requestSignedUrl']);
    Route::get('/secure-transfer/status/{id}', [SecureTransferController::class, 'status']);
});

// Public routes (signed URL download)
Route::get('/secure-transfer/signed-download/{token}', [SecureTransferController::class, 'downloadViaSignedUrl'])
    ->name('secure-transfer.signed-download');

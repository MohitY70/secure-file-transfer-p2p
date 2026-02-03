<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Heygeeks\SecureFileTransfer\Http\Server\SignedDownloadController;
use Heygeeks\SecureFileTransfer\Http\Server\TransferController;

Route::middleware('auth.secure-transfer')->group(function (): void {
    Route::post('/secure-transfer/upload', [TransferController::class, 'upload']);
    Route::get('/secure-transfer/download/{id}', [TransferController::class, 'download']);
    Route::get('/secure-transfer/request-url/{id}', [TransferController::class, 'requestSignedUrl']);
    Route::get('/secure-transfer/status/{id}', [TransferController::class, 'status']);
});

Route::get('/secure-transfer/signed-download/{id}', [SignedDownloadController::class, 'download'])
    ->name('secure-transfer.signed-download');

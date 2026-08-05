<?php

use App\Http\Controllers\DocumentContentController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentMemberController;
use App\Http\Controllers\FolderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('documents', DocumentController::class)->except(['create', 'edit']);

    Route::put('documents/{document}/content', [DocumentContentController::class, 'update'])
        ->middleware('throttle:120,1')
        ->name('documents.content.update');

    Route::resource('folders', FolderController::class)->only(['store', 'update', 'destroy']);

    Route::resource('documents.members', DocumentMemberController::class)
        ->only(['store', 'update', 'destroy'])
        ->parameters(['members' => 'membership'])
        ->scoped();
});

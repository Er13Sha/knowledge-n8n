<?php

use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Internal\RagController;
use App\Http\Controllers\Knowledge\KnowledgeDocumentController;
use App\Http\Controllers\Knowledge\KnowledgeSearchController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Middleware\EnsureRagTokenIsValid;
use App\Http\Middleware\EnsureSuperAdmin;
use Illuminate\Support\Facades\Route;

Route::prefix('internal/rag')->name('api.internal.rag.')->middleware(EnsureRagTokenIsValid::class)->group(function () {
    Route::post('index', [RagController::class, 'index'])->name('index');
    Route::post('search', [RagController::class, 'search'])->name('search');
    Route::post('delete', [RagController::class, 'destroy'])->name('delete');
});

Route::middleware('web')->prefix('auth')->name('api.auth.')->group(function () {
    Route::post('login', [AuthController::class, 'login'])
        ->middleware(['guest', 'throttle:login'])
        ->name('login');

    Route::middleware('auth')->group(function () {
        Route::get('user', [AuthController::class, 'user'])->name('user');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    });
});

Route::middleware(['web', 'auth'])->prefix('knowledge')->name('api.knowledge.')->group(function () {
    Route::get('documents', [KnowledgeDocumentController::class, 'index'])->name('documents.index');
    Route::post('documents', [KnowledgeDocumentController::class, 'store'])->name('documents.store');
    Route::patch('documents/{knowledgeDocument}', [KnowledgeDocumentController::class, 'update'])->name('documents.update');
    Route::get('documents/{knowledgeDocument}', [KnowledgeDocumentController::class, 'show'])->name('documents.show');
    Route::post('documents/{knowledgeDocument}/retry-indexing', [KnowledgeDocumentController::class, 'retryIndexing'])->name('documents.retry-indexing');
    Route::delete('documents/{knowledgeDocument}', [KnowledgeDocumentController::class, 'destroy'])->name('documents.destroy');
    Route::post('search', KnowledgeSearchController::class)->name('search');
});

Route::middleware(['web', 'auth', EnsureSuperAdmin::class])->prefix('admin')->name('api.admin.')->group(function () {
    Route::get('employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::post('employees', [EmployeeController::class, 'store'])->name('employees.store');
    Route::patch('employees/{user}', [EmployeeController::class, 'update'])->name('employees.update');
    Route::put('roles/{role}/permissions', [EmployeeController::class, 'updateRole'])->name('roles.permissions.update');
    Route::put('departments/{department}/permissions', [EmployeeController::class, 'updateDepartment'])->name('departments.permissions.update');
});

Route::middleware(['web', 'auth'])->prefix('settings')->name('api.settings.')->group(function () {
    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::put('password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('password.update');
});

<?php

use Illuminate\Support\Facades\Route;
use Modules\Glitter\HelpDesk\Http\Controllers\TicketController;

Route::prefix('user')->middleware('auth:sanctum')->name('user.')->group(function () {
        Route::get('categories', [TicketController::class, 'categories'])->middleware('permission:user,glitter-help_desk.tickets.read-categories')->name('categories.index');
    Route::get('tickets', [TicketController::class, 'ownIndex'])->middleware('permission:user,glitter-help_desk.tickets.read-own')->name('tickets.index');
    Route::post('tickets', [TicketController::class, 'create'])->middleware('permission:user,glitter-help_desk.tickets.create')->name('tickets.store');
    Route::get('tickets/{ticket}', [TicketController::class, 'ownShow'])->middleware('permission:user,glitter-help_desk.tickets.read-own')->whereNumber('ticket')->name('tickets.show');
    Route::post('tickets/{ticket}/messages', [TicketController::class, 'ownReply'])->middleware('permission:user,glitter-help_desk.tickets.reply')->whereNumber('ticket')->name('tickets.messages.store');
    Route::post('tickets/{ticket}/reopen', [TicketController::class, 'reopen'])->middleware('permission:user,glitter-help_desk.tickets.read-own')->whereNumber('ticket')->name('tickets.reopen');
});

Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->name('admin.')->group(function () {
    Route::get('tickets', [TicketController::class, 'adminIndex'])->middleware('permission:admin,glitter-help_desk.tickets.read-assigned|glitter-help_desk.tickets.read-all,false')->name('tickets.index');
    Route::get('my-tickets', [TicketController::class, 'assignedIndex'])->middleware('permission:admin,glitter-help_desk.tickets.read-assigned')->name('tickets.assigned');
    Route::get('tickets/{ticket}', [TicketController::class, 'adminShow'])->middleware('permission:admin,glitter-help_desk.tickets.read-assigned|glitter-help_desk.tickets.read-all,false')->whereNumber('ticket')->name('tickets.show');
    Route::post('tickets/{ticket}/messages', [TicketController::class, 'adminReply'])->middleware('permission:admin,glitter-help_desk.tickets.respond')->whereNumber('ticket')->name('tickets.messages.store');
    Route::post('tickets/{ticket}/notes', [TicketController::class, 'note'])->middleware('permission:admin,glitter-help_desk.notes.manage')->whereNumber('ticket')->name('tickets.notes.store');
    Route::patch('tickets/{ticket}/status', [TicketController::class, 'updateStatus'])->middleware('permission:admin,glitter-help_desk.tickets.update-status')->whereNumber('ticket')->name('tickets.status.update');
    Route::patch('tickets/{ticket}/priority', [TicketController::class, 'updatePriority'])->middleware('permission:admin,glitter-help_desk.tickets.update-priority')->whereNumber('ticket')->name('tickets.priority.update');
    Route::put('tickets/{ticket}/assignee', [TicketController::class, 'assign'])->middleware('permission:admin,glitter-help_desk.tickets.assign')->whereNumber('ticket')->name('tickets.assign');
    Route::get('agents', [TicketController::class, 'agents'])->middleware('permission:admin,glitter-help_desk.tickets.read-assigned|glitter-help_desk.tickets.read-all,false')->name('agents.index');
    Route::get('categories', [TicketController::class, 'categoriesAdmin'])->middleware('permission:admin,glitter-help_desk.categories.manage')->name('categories.index');
    Route::post('categories', [TicketController::class, 'storeCategory'])->middleware('permission:admin,glitter-help_desk.categories.manage')->name('categories.store');
    Route::patch('categories/{category}', [TicketController::class, 'updateCategory'])->middleware('permission:admin,glitter-help_desk.categories.manage')->whereNumber('category')->name('categories.update');
    Route::get('canned-replies', [TicketController::class, 'cannedReplies'])->middleware('permission:admin,glitter-help_desk.notes.manage|glitter-help_desk.tickets.respond,false')->name('canned-replies.index');
    Route::post('canned-replies', [TicketController::class, 'storeCannedReply'])->middleware('permission:admin,glitter-help_desk.notes.manage')->name('canned-replies.store');
    Route::patch('canned-replies/{reply}', [TicketController::class, 'updateCannedReply'])->middleware('permission:admin,glitter-help_desk.notes.manage')->whereNumber('reply')->name('canned-replies.update');
    Route::delete('canned-replies/{reply}', [TicketController::class, 'destroyCannedReply'])->middleware('permission:admin,glitter-help_desk.notes.manage')->whereNumber('reply')->name('canned-replies.destroy');
    Route::get('settings', [TicketController::class, 'settings'])->middleware('permission:admin,glitter-help_desk.settings.manage')->name('settings.index');
    Route::put('settings', [TicketController::class, 'updateSettings'])->middleware('permission:admin,glitter-help_desk.settings.manage')->name('settings.update');
    Route::get('reports', [TicketController::class, 'report'])->middleware('permission:admin,glitter-help_desk.reports.read')->name('reports.index');
});

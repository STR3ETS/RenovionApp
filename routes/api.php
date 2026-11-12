<?php

use App\Http\Controllers\Api\App\ChatController as AppChatController;
use App\Http\Controllers\Api\App\CustomerController as AppCustomerController;
use App\Http\Controllers\Api\App\InboxController as AppInboxController;
use App\Http\Controllers\Api\App\LeadController as AppLeadController;
use App\Http\Controllers\Api\App\NotificationController as AppNotificationController;
use App\Http\Controllers\Api\App\PlanningController as AppPlanningController;
use App\Http\Controllers\Api\App\ProjectController as AppProjectController;
use App\Http\Controllers\Api\App\ProjectDetailController as AppProjectDetailController;
use App\Http\Controllers\Api\App\SearchController as AppSearchController;
use App\Http\Controllers\Api\App\SessionController as AppSessionController;
use App\Http\Controllers\Api\App\TaskController as AppTaskController;
use App\Http\Controllers\Api\App\SettingsController as AppSettingsController;
use App\Http\Controllers\Api\App\TodayController as AppTodayController;
use App\Http\Controllers\Api\App\WorkPackageController as AppWorkPackageController;
use App\Http\Controllers\Api\LeadIntakeController;
use App\Http\Controllers\AppThemeController;
use App\Http\Controllers\ChatMessageController;
use App\Http\Controllers\DeviceTokenController;
use App\Http\Controllers\LeadContactController;
use App\Http\Controllers\LeadStatusController;
use App\Http\Controllers\NovaController;
use App\Http\Controllers\TaskStatusController;
use App\Http\Middleware\RequireAppUpdate;
use App\Http\Middleware\VerifyLeadIntakeToken;
use Illuminate\Support\Facades\Route;

Route::post('/aanvragen', LeadIntakeController::class)
    ->middleware([VerifyLeadIntakeToken::class, 'throttle:30,1'])
    ->name('api.leads.intake');

// De iOS/Android-app (mobile/app): schermen zitten in de app, deze routes leveren de gegevens.
// Een app ouder dan minimum_build (mobile/release.json) krijgt overal 426 met uitleg (RequireAppUpdate).
Route::prefix('app')->name('api.app.')->middleware(RequireAppUpdate::class)->group(function () {
    // Bij opstarten en terugkeren: mag deze app nog, of moet hij eerst updaten?
    Route::get('versie', [AppSessionController::class, 'version'])->middleware('throttle:60,1')->name('version');
    Route::post('inloggen', [AppSessionController::class, 'store'])->middleware('throttle:login')->name('login');
    // Foto's voor <img> in de app: zonder token, met een ondertekend adres uit het werkpakket.
    Route::get('fotos/{photo}', [AppWorkPackageController::class, 'photo'])->middleware('signed:relative')->name('photo');
    Route::get('chat/bijlagen/{message}', [AppChatController::class, 'attachment'])->middleware('signed:relative')->name('chat.attachment');

    // 120 per minuut per gebruiker: ruim voor vooraf laden en verversen, te weinig om de server te bestoken.
    Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {
        Route::get('ik', [AppSessionController::class, 'show'])->name('me');
        Route::post('uitloggen', [AppSessionController::class, 'destroy'])->name('logout');
        Route::post('webcode', [AppSessionController::class, 'webCode'])->middleware('throttle:app-webcode')->name('web-code');
        Route::post('apparaten', [DeviceTokenController::class, 'store'])->middleware('throttle:30,1')->name('devices');
        Route::patch('weergave', [AppThemeController::class, 'update'])->middleware('throttle:30,1')->name('theme');

        Route::middleware('can:internal')->group(function () {
            Route::get('vandaag', AppTodayController::class)->name('today');
            Route::get('meldingen', AppNotificationController::class)->name('notifications');

            // Teamchat: kanalen en een gesprek openen hier; berichten via dezelfde controller als de website.
            Route::get('chat', [AppChatController::class, 'index'])->name('chat');
            Route::post('chat/kanalen', [AppChatController::class, 'store'])->middleware('throttle:10,1')->name('chat.channels.store');
            // Instellingen: de app stuurt wijzigingen in één keer bij het verlaten van het scherm of het zijmenu.
            Route::get('instellingen', [AppSettingsController::class, 'show'])->name('settings');
            Route::patch('instellingen', [AppSettingsController::class, 'update'])->middleware('throttle:30,1')->name('settings.update');
            Route::post('instellingen/testmelding', [AppSettingsController::class, 'test'])->middleware('throttle:5,1')->name('settings.test');

            Route::get('chat/{channel}', [AppChatController::class, 'show'])->name('chat.show');
            Route::get('chat/{channel}/berichten', [ChatMessageController::class, 'index'])->name('chat.messages');
            Route::post('chat/{channel}/berichten', [ChatMessageController::class, 'store'])->middleware('throttle:60,1')->name('chat.messages.store');
            Route::post('chat/berichten/{message}/nova-bevestigen', [ChatMessageController::class, 'confirmNova'])->name('chat.nova.confirm');
            Route::post('chat/berichten/{message}/taak', [ChatMessageController::class, 'toTask'])->middleware('throttle:30,1')->name('chat.task');
            Route::get('projecten', AppProjectController::class)->name('projects');
            Route::get('projecten/{project}', AppProjectDetailController::class)->name('projects.show');
            Route::get('taken', [AppTaskController::class, 'index'])->name('tasks');
            Route::post('taken', [AppTaskController::class, 'store'])->name('tasks.store');
            Route::get('inbox', AppInboxController::class)->middleware('can:manage-crm')->name('inbox');
            Route::get('planning', [AppPlanningController::class, 'index'])->name('planning');
            Route::post('planning', [AppPlanningController::class, 'store'])->middleware('can:manage-crm')->name('planning.store');
            Route::get('werkpakketten/{workPackage}', [AppWorkPackageController::class, 'show'])->name('work-packages.show');
            Route::patch('werkpakketten/{workPackage}/items/{item}', [AppWorkPackageController::class, 'toggle'])->scopeBindings()->name('checklist-items.toggle');
            Route::post('werkpakketten/{workPackage}/fotos', [AppWorkPackageController::class, 'photos'])->middleware('throttle:30,1')->name('work-packages.photos');
            Route::post('werkpakketten/{workPackage}/afronden', [AppWorkPackageController::class, 'complete'])->name('work-packages.complete');
            Route::post('werkpakketten/{workPackage}/heropenen', [AppWorkPackageController::class, 'reopen'])->name('work-packages.reopen');
            Route::delete('fotos/{photo}', [AppWorkPackageController::class, 'destroyPhoto'])->name('photos.destroy');
            Route::delete('planning/{scheduleEntry}', [AppPlanningController::class, 'destroy'])->middleware('can:manage-crm')->name('planning.destroy');

            Route::middleware('can:manage-crm')->group(function () {
                Route::post('nova', [NovaController::class, 'propose'])->middleware('throttle:30,1')->name('nova');
                Route::post('nova/uitvoeren', [NovaController::class, 'execute'])->name('nova.execute');
                Route::get('nova/briefing', [NovaController::class, 'briefing'])->name('nova.briefing');
                Route::get('zoeken', AppSearchController::class)->middleware('throttle:60,1')->name('search');
                Route::get('klanten', [AppCustomerController::class, 'index'])->name('customers');
                Route::get('klanten/{customer}', [AppCustomerController::class, 'show'])->name('customers.show');
                Route::post('klanten/{customer}/notities', [AppCustomerController::class, 'storeNote'])->middleware('throttle:30,1')->name('customers.notes');
                Route::get('aanvragen', [AppLeadController::class, 'index'])->name('leads');
                Route::get('aanvragen/{lead}', [AppLeadController::class, 'show'])->name('leads.show');
                // Dezelfde regels als de website: die controllers antwoorden JSON aan de app.
                Route::patch('aanvragen/{lead}/status', [LeadStatusController::class, 'update'])->name('leads.status');
                Route::post('aanvragen/{lead}/contactmomenten', [LeadContactController::class, 'store'])->middleware('throttle:30,1')->name('leads.contacts');
            });
            Route::patch('taken/{task}/status', [TaskStatusController::class, 'update'])->name('tasks.status');
        });
    });
});

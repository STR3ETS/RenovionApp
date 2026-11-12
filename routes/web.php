<?php

use App\Http\Controllers\AcademyController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\AppThemeController;
use App\Http\Controllers\AppWebSessionController;
use App\Http\Controllers\AttentionController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\AutomationController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\BudgetExtrasController;
use App\Http\Controllers\BudgetLineController;
use App\Http\Controllers\BudgetStructureController;
use App\Http\Controllers\CalculationAssistController;
use App\Http\Controllers\CalculationController;
use App\Http\Controllers\CalculationLineController;
use App\Http\Controllers\CalculationQuoteController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ChatMessageController;
use App\Http\Controllers\ChecklistItemController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryReportController;
use App\Http\Controllers\DeviceTokenController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\FollowUpController;
use App\Http\Controllers\IntakeController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\JobCompletionController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\JobReportController;
use App\Http\Controllers\JobStatusController;
use App\Http\Controllers\JobStepController;
use App\Http\Controllers\LeadContactController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\LeadStatusController;
use App\Http\Controllers\MailTemplateController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\MotionPreferenceController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NotificationSettingsController;
use App\Http\Controllers\NovaController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PartnerPortalController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\PlanningController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\PortalPreviewController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\PriceItemSearchController;
use App\Http\Controllers\PriceListController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectPhaseController;
use App\Http\Controllers\ProjectStatusController;
use App\Http\Controllers\PurchaseInvoiceController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\QualityController;
use App\Http\Controllers\QuoteBuilderController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\QuoteDashboardController;
use App\Http\Controllers\QuoteLineProposalController;
use App\Http\Controllers\QuotePublicController;
use App\Http\Controllers\QuoteStatusController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RfqController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ServiceTicketController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SubcontractorController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskStatusController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\WorkforceController;
use App\Http\Controllers\WorkOrderApprovalController;
use App\Http\Controllers\WorkOrderController;
use App\Http\Controllers\WorkPackageController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [LoginController::class, 'create'])->name('login');
Route::get('/app/sessie', AppWebSessionController::class)->middleware('throttle:app-session')->name('app.web-session');
Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');
Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
Route::post('/voorkeuren/beweging', MotionPreferenceController::class)->middleware('auth')->name('preferences.motion');

// Privacyverklaring: openbaar, ook voor de stores (Google Play en App Store vragen een link).
Route::view('/privacy', 'privacy')->name('privacy');

// Klantview van de offerte: privélink zonder inlog (briefing §6).
if (config('renovion.modules.quotes')) {
    Route::middleware('throttle:30,1')->group(function () {
        Route::get('offerte/{quote:public_token}', [QuotePublicController::class, 'show'])->name('quotes.public');
        Route::get('offerte/{quote:public_token}/media/{file}', [QuoteBuilderController::class, 'media'])->name('quotes.public.media');
        Route::post('offerte/{quote:public_token}/ondertekenen', [QuotePublicController::class, 'sign'])->name('quotes.public.sign');
        Route::post('offerte/{quote:public_token}/aanpassing', [QuotePublicController::class, 'requestChange'])->name('quotes.public.change');
        Route::post('offerte/{quote:public_token}/afwijzen', [QuotePublicController::class, 'reject'])->name('quotes.public.reject');
    });
}

Route::middleware('auth')->group(function () {
    // Voor iedereen; klanten worden vanuit het dashboard doorgestuurd naar het portaal.
    Route::get('/', DashboardController::class)->name('dashboard');

    // Klantportaal (briefing §10): rol Klant, alleen eigen projecten en klantzichtbare content.
    Route::get('portaal', [PortalController::class, 'index'])->name('portal.index');

    // Partnerportaal: wat een onderaannemer ziet als hij inlogt. Hij komt niet
    // in de interne omgeving, net als een klant.
    Route::get('partner', [PartnerPortalController::class, 'index'])->name('partner.index');
    Route::post('partner/aanvragen/{rfq}/bieden', [PartnerPortalController::class, 'bid'])->middleware('throttle:10,1')->name('partner.bid');
    Route::post('partner/{subcontractor}/dossier', [PartnerPortalController::class, 'document'])->middleware('throttle:10,1')->name('partner.document');
    Route::get('portaal/projecten/{project}', [PortalController::class, 'show'])->name('portal.show');
    Route::post('portaal/fasen/{phase}/akkoord', [PortalController::class, 'approvePhase'])->middleware('throttle:10,1')->name('portal.phases.approve');
    Route::post('portaal/projecten/{project}/fasen-akkoord', [PortalController::class, 'approveAllPhases'])->middleware('throttle:10,1')->name('portal.phases.approve-all');
    Route::get('portaal/opleverrapporten/{report}', [PortalController::class, 'report'])->name('portal.report');
    Route::post('portaal/opleverrapporten/{report}/ondertekenen', [PortalController::class, 'signReport'])->middleware('throttle:10,1')->name('portal.report.sign');

    // Beeldstreams: intern én klantportaal (toegangscheck in de controller).
    Route::get('projecten/{project}/omslagfoto', [ProjectController::class, 'coverPhoto'])->name('projects.cover');
    Route::get('fotos/{photo}', [PhotoController::class, 'show'])->name('photos.show');

    // Interne omgeving: niet voor klanten.
    Route::middleware('can:internal')->group(function () {
        // "Aan de slag": uitleg van het systeem voor het hele team, checklist voor wie het CRM beheert.
        Route::get('aan-de-slag', [OnboardingController::class, 'show'])->name('onboarding');
        Route::post('aan-de-slag/verbergen', [OnboardingController::class, 'dismiss'])->name('onboarding.dismiss');
        Route::post('aan-de-slag/tonen', [OnboardingController::class, 'restore'])->name('onboarding.restore');

        // Voor het hele team (uitvoerders zien alleen hun eigen projecten, taken en planning).
        Route::get('projecten', [ProjectController::class, 'index'])->name('projects.index');
        Route::get('portfolio', PortfolioController::class)->middleware('can:manage-crm')->name('portfolio');
        Route::get('projecten/aanmaken', [ProjectController::class, 'create'])->middleware('can:manage-crm')->name('projects.create');
        Route::get('projecten/{project}', [ProjectController::class, 'show'])->name('projects.show');

        // Foto-bewijs: uploaden kan ook door uitvoerders op eigen projecten.
        Route::post('projecten/{project}/fotos', [PhotoController::class, 'store'])->middleware('throttle:30,1')->name('photos.store');
        Route::delete('fotos/{photo}', [PhotoController::class, 'destroy'])->name('photos.destroy');

        // Werkpakketten: uitvoerders werken hierin op eigen projecten (toegangscheck in controller).
        Route::get('werkpakketten/{workPackage}', [WorkPackageController::class, 'show'])->name('work-packages.show');
        Route::post('werkpakketten/{workPackage}/afronden', [WorkPackageController::class, 'complete'])->name('work-packages.complete');
        Route::post('werkpakketten/{workPackage}/heropenen', [WorkPackageController::class, 'reopen'])->name('work-packages.reopen');
        Route::patch('werkpakketten/{workPackage}/items/{item}/toggle', [ChecklistItemController::class, 'toggle'])->scopeBindings()->name('checklist-items.toggle');

        // Mijn klussen: de werkweergave voor op de bouwplaats. Uitvoeren mag alleen
        // de eigenaar van het blok; meekijken mag de projectleiding.
        Route::get('klussen', [JobController::class, 'index'])->name('jobs.index');
        Route::get('klussen/{scheduleEntry}', [JobController::class, 'show'])->name('jobs.show');
        Route::patch('klussen/{job}/status', [JobStatusController::class, 'update'])->name('jobs.status');
        Route::patch('klussen/{job}/stappen/{step}/toggle', [JobStepController::class, 'toggle'])->name('job-steps.toggle');
        Route::patch('klussen/{job}/stappen/{step}/notitie', [JobStepController::class, 'note'])->name('job-steps.note');
        Route::post('klussen/{job}/stappen/{step}/fotos', [JobStepController::class, 'photo'])->middleware('throttle:30,1')->name('job-steps.photos');
        Route::post('klussen/{job}/meldingen', [JobReportController::class, 'store'])->middleware('throttle:30,1')->name('job-reports.store');
        Route::post('meldingen/{report}/afhandelen', [JobReportController::class, 'resolve'])->name('job-reports.resolve');
        Route::post('klussen/{job}/afronden', JobCompletionController::class)->name('jobs.complete');

        // Kwaliteit bekijken mag het hele team op eigen projecten; de controlepunten
        // afvinken en de oplevering doorzetten is voor de projectleiding.
        Route::get('kwaliteit', [QualityController::class, 'index'])->name('quality.index');
        Route::get('kwaliteit/{project}', [QualityController::class, 'show'])->name('quality.show');

        // Werkbonnen: uitvoerders zien hun eigen bonnen, de projectleiding alles.
        Route::get('werkbonnen', [WorkOrderController::class, 'index'])->name('work-orders.index');
        Route::get('werkbonnen/{workOrder}', [WorkOrderController::class, 'show'])->name('work-orders.show');

        // Opleverrapport (briefing §13): bekijken kan het hele team (toegangscheck in controller).
        Route::get('opleverrapporten/{deliveryReport}', [DeliveryReportController::class, 'show'])->name('delivery-reports.show');

        // Teamchat (briefing §11): teamkanalen voor iedereen intern, projectkanalen volgen projecttoegang.
        Route::get('chat', [ChatController::class, 'index'])->name('chat.index');
        // Vóór chat/{channel}: anders wordt "ongelezen" als kanaal gelezen.
        Route::get('chat/ongelezen', [ChatController::class, 'unread'])->middleware('throttle:60,1')->name('chat.unread');
        Route::post('chat/kanalen', [ChatController::class, 'store'])->name('chat.channels.store');
        Route::get('chat/bijlagen/{message}', [ChatMessageController::class, 'attachment'])->name('chat.attachment');
        Route::post('chat/berichten/{message}/nova-bevestigen', [ChatMessageController::class, 'confirmNova'])->name('chat.nova.confirm');
        Route::post('chat/berichten/{message}/taak', [ChatMessageController::class, 'toTask'])->name('chat.task');
        Route::get('chat/{channel}', [ChatController::class, 'show'])->name('chat.show');
        Route::get('chat/{channel}/berichten', [ChatMessageController::class, 'index'])->name('chat.messages');
        Route::post('chat/{channel}/berichten', [ChatMessageController::class, 'store'])->middleware('throttle:60,1')->name('chat.messages.store');

        Route::get('taken', [TaskController::class, 'index'])->name('tasks.index');
        Route::post('taken', [TaskController::class, 'store'])->name('tasks.store');
        Route::match(['put', 'patch'], 'taken/{task}', [TaskController::class, 'update'])->name('tasks.update');
        Route::patch('taken/{task}/status', [TaskStatusController::class, 'update'])->name('tasks.status');

        Route::get('planning', [PlanningController::class, 'index'])->name('planning.index');

        // Pushmeldingen: de app registreert hier het token van de telefoon.
        Route::post('apparaten', [DeviceTokenController::class, 'store'])->middleware('throttle:30,1')->name('devices.store');

        // Instellingen > Meldingen: welke pushmeldingen je wilt krijgen, plus een testmelding.
        Route::get('meldingen', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('instellingen/meldingen', [NotificationSettingsController::class, 'edit'])->name('notification-settings.edit');
        Route::patch('instellingen/meldingen', [NotificationSettingsController::class, 'update'])->middleware('throttle:60,1')->name('notification-settings.update');
        Route::patch('instellingen/beweging', [NotificationSettingsController::class, 'updateMotion'])->middleware('throttle:60,1')->name('motion-settings.update');
        // Weergave van de app (licht/donker/telefoon), vanuit het zijmenu van een serverpagina in de app.
        Route::patch('instellingen/weergave', [AppThemeController::class, 'update'])->middleware('throttle:30,1')->name('app-theme.update');
        Route::post('instellingen/meldingen/test', [NotificationSettingsController::class, 'test'])->middleware('throttle:5,1')->name('notification-settings.test');

        // Gespreksverslagen (Nova notuleert): opnemen kan het hele team, toegang volgt project of eigen opname.
        Route::get('verslagen', [MeetingController::class, 'index'])->name('meetings.index');
        Route::get('verslagen/opnemen', [MeetingController::class, 'create'])->name('meetings.create');
        Route::post('verslagen', [MeetingController::class, 'store'])->middleware('throttle:10,1')->name('meetings.store');
        Route::post('verslagen/{meeting}/geluid', [MeetingController::class, 'chunk'])->middleware('throttle:120,1')->name('meetings.chunk');
        Route::post('verslagen/{meeting}/stoppen', [MeetingController::class, 'stop'])->name('meetings.stop');
        Route::get('verslagen/{meeting}', [MeetingController::class, 'show'])->name('meetings.show');
        Route::get('verslagen/{meeting}/status', [MeetingController::class, 'status'])->middleware('throttle:30,1')->name('meetings.status');
        Route::get('verslagen/{meeting}/geluid', [MeetingController::class, 'audio'])->name('meetings.audio');
        Route::post('verslagen/{meeting}/taken', [MeetingController::class, 'tasks'])->name('meetings.tasks');
        Route::post('verslagen/{meeting}/delen', [MeetingController::class, 'share'])->name('meetings.share');
        Route::post('verslagen/{meeting}/opnieuw', [MeetingController::class, 'retry'])->name('meetings.retry');
        Route::delete('verslagen/{meeting}', [MeetingController::class, 'destroy'])->name('meetings.destroy');
        // De Academy is voor iedereen met een interne rol, ook voor uitvoerders.
        Route::get('academy', [AcademyController::class, 'index'])->name('academy.index');
        Route::get('academy/{tour}', [AcademyController::class, 'show'])->name('academy.show');
        Route::post('academy/{tour}/afronden', [AcademyController::class, 'complete'])->name('academy.complete');

        Route::post('documenten', [DocumentController::class, 'store'])->name('documents.store');
        Route::get('documenten/{document}', [DocumentController::class, 'show'])->name('documents.show');

        // CRM en beheer: niet voor uitvoerders (briefing §15).
        Route::middleware('can:manage-crm')->group(function () {
            Route::resource('aanvragen', LeadController::class)
                ->parameters(['aanvragen' => 'lead'])
                ->names('leads')
                ->except('destroy');
            Route::patch('aanvragen/{lead}/status', [LeadStatusController::class, 'update'])->name('leads.status');
            Route::post('aanvragen/{lead}/contactmomenten', [LeadContactController::class, 'store'])->name('leads.contacts.store');

            Route::get('klanten', [CustomerController::class, 'index'])->name('customers.index');
            Route::get('klanten/{customer}', [CustomerController::class, 'show'])->name('customers.show');
            Route::post('klanten/{customer}/notities', [CustomerController::class, 'storeNote'])->name('customers.notes.store');
            Route::post('klanten/{customer}/portaal', [CustomerController::class, 'storePortalAccount'])->name('customers.portal');

            if (config('renovion.modules.quotes')) {
                Route::resource('offertes', QuoteController::class)
                    ->parameters(['offertes' => 'quote'])
                    ->names('quotes');
                Route::patch('offertes/{quote}/status', [QuoteStatusController::class, 'update'])->name('quotes.status');
                Route::post('offertes/{quote}/nieuwe-versie', [QuoteController::class, 'newVersion'])->name('quotes.version');

                // Offerte-builder (briefing §6, EasyDash-concept)
                Route::get('offertes/{quote}/builder', [QuoteBuilderController::class, 'edit'])->name('quotes.builder');
                Route::put('offertes/{quote}/builder', [QuoteBuilderController::class, 'update'])->name('quotes.builder.update');
                Route::post('offertes/{quote}/builder/template', [QuoteBuilderController::class, 'applyTemplate'])->name('quotes.builder.template');
                Route::post('offertes/{quote}/builder/template-opslaan', [QuoteBuilderController::class, 'saveAsTemplate'])->name('quotes.builder.save-template');
                Route::delete('offerte-templates/{template}', [QuoteBuilderController::class, 'deleteTemplate'])->name('quote-templates.destroy');
                Route::post('offertes/{quote}/builder/upload', [QuoteBuilderController::class, 'upload'])->middleware('throttle:30,1')->name('quotes.builder.upload');
                Route::post('offertes/{quote}/builder/blok-ai', [QuoteBuilderController::class, 'blockAi'])->middleware('throttle:30,1')->name('quotes.builder.ai');
                Route::post('offertes/{quote}/builder/nova-offerte', [QuoteBuilderController::class, 'generate'])->middleware('throttle:10,1')->name('quotes.builder.generate');
                Route::post('offertes/{quote}/posten-voorstel', QuoteLineProposalController::class)->middleware('throttle:15,1')->name('quotes.lines.propose');
                Route::post('calculaties/{calculation}/offerte', CalculationQuoteController::class)->name('calculations.quote');
            }

            Route::get('calculaties', [CalculationController::class, 'index'])->name('calculations.index');
            Route::get('calculaties/nieuw', [CalculationController::class, 'create'])->name('calculations.create');
            Route::post('calculaties', [CalculationController::class, 'store'])->name('calculations.store');
            Route::post('calculaties/ai-voorstel', CalculationAssistController::class)->middleware('throttle:30,1')->name('calculations.propose');
            Route::get('calculaties/{calculation}', [CalculationController::class, 'show'])->name('calculations.show');
            Route::match(['put', 'patch'], 'calculaties/{calculation}', [CalculationController::class, 'update'])->name('calculations.update');
            Route::delete('calculaties/{calculation}', [CalculationController::class, 'destroy'])->name('calculations.destroy');
            Route::post('calculaties/{calculation}/regels', [CalculationLineController::class, 'store'])->name('calculations.lines.store');
            Route::patch('calculaties/{calculation}/regels/{line}', [CalculationLineController::class, 'update'])->scopeBindings()->name('calculations.lines.update');
            Route::delete('calculaties/{calculation}/regels/{line}', [CalculationLineController::class, 'destroy'])->scopeBindings()->name('calculations.lines.destroy');
            // Mailbuilder: de teksten die naar klanten gaan, op één plek.
            Route::get('mailteksten', [MailTemplateController::class, 'index'])->name('mail-templates.index');
            Route::match(['put', 'patch'], 'mailteksten/{mailTemplate}', [MailTemplateController::class, 'update'])->name('mail-templates.update');

            // Rapportages en marketing: momentopnames uit de data die er is.
            Route::get('rapportages', [ReportController::class, 'index'])->name('reports.index');
            Route::get('marketing', [ReportController::class, 'marketing'])->name('marketing.index');

            // Het klantportaal zoals de klant het ziet, van binnenuit te openen.
            Route::get('klantportaal', [PortalPreviewController::class, 'index'])->name('portal-preview.index');
            Route::get('klantportaal/{project}', [PortalPreviewController::class, 'show'])->name('portal-preview.show');

            // Nova en de agents: uitvoeringen, goedkeuringen, audit en skills.
            Route::get('agents', [AgentController::class, 'runs'])->name('agents.index');
            Route::get('goedkeuringen', [AgentController::class, 'approvals'])->name('approvals.index');
            Route::post('goedkeuringen/{approval}', [AgentController::class, 'decide'])->name('approvals.decide');
            Route::get('audit', [AgentController::class, 'audit'])->name('audit.index');

            // Wat de agents mogen is inrichting: dat bepaalt de directie.
            Route::middleware('can:manage-team')->group(function () {
                Route::get('skills', [AgentController::class, 'skills'])->name('skills.index');
                Route::match(['put', 'patch'], 'skills/{skill}', [AgentController::class, 'updateSkill'])->name('skills.update');
            });

            // Kwaliteit, oplevering en nazorg.
            Route::patch('controlepunten/{check}/toggle', [QualityController::class, 'toggleCheck'])->name('quality-checks.toggle');
            Route::post('kwaliteit/{review}/volgende-stap', [QualityController::class, 'advance'])->name('quality.advance');
            Route::post('kwaliteit/{review}/ondertekenen', [QualityController::class, 'sign'])->name('quality.sign');
            Route::post('kwaliteit/{project}/restpunten', [QualityController::class, 'storeSnag'])->name('snags.store');
            Route::match(['put', 'patch'], 'restpunten/{snag}', [QualityController::class, 'updateSnag'])->name('snags.update');
            Route::delete('restpunten/{snag}', [QualityController::class, 'destroySnag'])->name('snags.destroy');

            Route::get('nazorg', [ServiceTicketController::class, 'index'])->name('service.index');
            Route::post('nazorg', [ServiceTicketController::class, 'store'])->name('service.store');
            Route::match(['put', 'patch'], 'nazorg/{serviceTicket}', [ServiceTicketController::class, 'update'])->name('service.update');

            Route::get('documenten', [DocumentController::class, 'index'])->name('documents.index');

            // Verkoop: intake in vijf stappen, opvolging en het offertedashboard.
            Route::get('intakes', [IntakeController::class, 'index'])->name('intakes.index');
            Route::post('intakes', [IntakeController::class, 'store'])->name('intakes.store');
            Route::get('intakes/{intake}', [IntakeController::class, 'show'])->name('intakes.show');
            Route::match(['put', 'patch'], 'intakes/{intake}', [IntakeController::class, 'update'])->name('intakes.update');
            Route::post('intakes/{intake}/opname', [IntakeController::class, 'planSurvey'])->name('intakes.survey');
            Route::post('intakes/{intake}/afronden', [IntakeController::class, 'complete'])->name('intakes.complete');

            Route::get('opvolging', [FollowUpController::class, 'index'])->name('follow-ups.index');
            Route::get('offertedashboard', QuoteDashboardController::class)->name('quote-dashboard');

            // Mensen en partners: eigen mensen, onderaannemers en prijsaanvragen.
            Route::get('mensen', [WorkforceController::class, 'index'])->name('workforce.index');
            Route::match(['put', 'patch'], 'mensen/{user}', [WorkforceController::class, 'update'])->name('workforce.update');
            Route::post('mensen/{user}/certificaten', [WorkforceController::class, 'storeCertificate'])->name('certificates.store');
            Route::delete('certificaten/{certificate}', [WorkforceController::class, 'destroyCertificate'])->name('certificates.destroy');
            Route::post('mensen/{user}/afwezigheid', [WorkforceController::class, 'storeAbsence'])->name('absences.store');
            Route::delete('afwezigheid/{absence}', [WorkforceController::class, 'destroyAbsence'])->name('absences.destroy');

            Route::get('onderaannemers', [SubcontractorController::class, 'index'])->name('subcontractors.index');
            Route::post('onderaannemers', [SubcontractorController::class, 'store'])->name('subcontractors.store');
            Route::get('onderaannemers/{subcontractor}', [SubcontractorController::class, 'show'])->name('subcontractors.show');
            Route::match(['put', 'patch'], 'onderaannemers/{subcontractor}', [SubcontractorController::class, 'update'])->name('subcontractors.update');
            Route::post('onderaannemers/{subcontractor}/dossier', [SubcontractorController::class, 'storeDocument'])->name('subcontractor-documents.store');
            Route::post('onderaannemers/{subcontractor}/portaal', [SubcontractorController::class, 'storePortalAccount'])->name('subcontractors.portal');
            Route::delete('partnerdossier/{document}', [SubcontractorController::class, 'destroyDocument'])->name('subcontractor-documents.destroy');

            Route::get('prijsaanvragen', [RfqController::class, 'index'])->name('rfqs.index');
            Route::get('prijsaanvragen/nieuw', [RfqController::class, 'create'])->name('rfqs.create');
            Route::post('prijsaanvragen', [RfqController::class, 'store'])->name('rfqs.store');
            Route::get('prijsaanvragen/{rfq}', [RfqController::class, 'show'])->name('rfqs.show');
            Route::match(['put', 'patch'], 'prijsaanvragen/{rfq}', [RfqController::class, 'update'])->name('rfqs.update');
            Route::post('prijsaanvragen/{rfq}/uitnodigen', [RfqController::class, 'invite'])->name('rfqs.invite');
            Route::post('prijsaanvragen/{rfq}/biedingen', [RfqController::class, 'storeBid'])->name('rfq-bids.store');
            Route::post('prijsaanvragen/{rfq}/gunnen/{bid}', [RfqController::class, 'award'])->name('rfqs.award');

            // Geld: facturatie, inkoop, nacalculatie en het financiële beeld.
            Route::get('financieel', [FinanceController::class, 'index'])->name('finance.index');
            Route::get('nacalculatie', [FinanceController::class, 'costReviews'])->name('cost-reviews.index');

            Route::get('facturen', [InvoiceController::class, 'index'])->name('invoices.index');
            Route::get('facturen/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
            Route::match(['put', 'patch'], 'facturen/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
            Route::delete('facturen/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');
            Route::post('begrotingstermijnen/{term}/factureren', [InvoiceController::class, 'storeTerm'])->name('invoices.term');
            Route::post('projecten/{project}/eindafrekening', [InvoiceController::class, 'storeFinal'])->name('invoices.final');

            Route::get('inkoop', [PurchaseOrderController::class, 'index'])->name('purchase-orders.index');
            Route::post('inkoop', [PurchaseOrderController::class, 'store'])->name('purchase-orders.store');
            Route::get('inkoop/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->name('purchase-orders.show');
            Route::match(['put', 'patch'], 'inkoop/{purchaseOrder}', [PurchaseOrderController::class, 'update'])->name('purchase-orders.update');
            Route::post('inkoop/{purchaseOrder}/regels', [PurchaseOrderController::class, 'storeLine'])->name('purchase-order-lines.store');
            Route::delete('inkoopregels/{line}', [PurchaseOrderController::class, 'destroyLine'])->name('purchase-order-lines.destroy');
            Route::post('inkoopfacturen', [PurchaseInvoiceController::class, 'store'])->name('purchase-invoices.store');
            Route::match(['put', 'patch'], 'inkoopfacturen/{purchaseInvoice}', [PurchaseInvoiceController::class, 'update'])->name('purchase-invoices.update');
            Route::delete('inkoopfacturen/{purchaseInvoice}', [PurchaseInvoiceController::class, 'destroy'])->name('purchase-invoices.destroy');

            // Begrotingen met hoofdstukken: de calculatietafel van Renovion.
            Route::get('begrotingen', [BudgetController::class, 'index'])->name('budgets.index');
            Route::get('begrotingen/nieuw', [BudgetController::class, 'create'])->name('budgets.create');
            Route::post('begrotingen', [BudgetController::class, 'store'])->name('budgets.store');
            Route::get('begrotingen/{budget}', [BudgetController::class, 'show'])->name('budgets.show');
            Route::match(['put', 'patch'], 'begrotingen/{budget}', [BudgetController::class, 'update'])->name('budgets.update');
            Route::delete('begrotingen/{budget}', [BudgetController::class, 'destroy'])->name('budgets.destroy');
            Route::post('begrotingen/{budget}/definitief', [BudgetController::class, 'finalise'])->name('budgets.finalise');
            Route::post('begrotingen/{budget}/heropenen', [BudgetController::class, 'reopen'])->name('budgets.reopen');

            // Indeling: hoofdstukken en paragrafen.
            Route::post('begrotingen/{budget}/hoofdstukken', [BudgetStructureController::class, 'storeChapter'])->name('budget-chapters.store');
            Route::match(['put', 'patch'], 'begrotingshoofdstukken/{chapter}', [BudgetStructureController::class, 'updateChapter'])->name('budget-chapters.update');
            Route::delete('begrotingshoofdstukken/{chapter}', [BudgetStructureController::class, 'destroyChapter'])->name('budget-chapters.destroy');
            Route::post('begrotingshoofdstukken/{chapter}/paragrafen', [BudgetStructureController::class, 'storeParagraph'])->name('budget-paragraphs.store');
            Route::match(['put', 'patch'], 'begrotingsparagrafen/{paragraph}', [BudgetStructureController::class, 'updateParagraph'])->name('budget-paragraphs.update');
            Route::delete('begrotingsparagrafen/{paragraph}', [BudgetStructureController::class, 'destroyParagraph'])->name('budget-paragraphs.destroy');

            // Regels.
            Route::post('begrotingsparagrafen/{paragraph}/regels', [BudgetLineController::class, 'store'])->name('budget-lines.store');
            Route::match(['put', 'patch'], 'begrotingsregels/{line}', [BudgetLineController::class, 'update'])->name('budget-lines.update');
            Route::delete('begrotingsregels/{line}', [BudgetLineController::class, 'destroy'])->name('budget-lines.destroy');

            // Staart, opties en termijnen.
            Route::post('begrotingen/{budget}/staart', [BudgetExtrasController::class, 'storeMarkup'])->name('budget-markups.store');
            Route::match(['put', 'patch'], 'begrotingsstaart/{markup}', [BudgetExtrasController::class, 'updateMarkup'])->name('budget-markups.update');
            Route::delete('begrotingsstaart/{markup}', [BudgetExtrasController::class, 'destroyMarkup'])->name('budget-markups.destroy');
            Route::post('begrotingen/{budget}/opties', [BudgetExtrasController::class, 'storeOption'])->name('budget-options.store');
            Route::match(['put', 'patch'], 'begrotingsopties/{option}', [BudgetExtrasController::class, 'updateOption'])->name('budget-options.update');
            Route::delete('begrotingsopties/{option}', [BudgetExtrasController::class, 'destroyOption'])->name('budget-options.destroy');
            Route::post('begrotingen/{budget}/termijnen', [BudgetExtrasController::class, 'storeTerm'])->name('budget-terms.store');
            Route::match(['put', 'patch'], 'begrotingstermijnen/{term}', [BudgetExtrasController::class, 'updateTerm'])->name('budget-terms.update');
            Route::delete('begrotingstermijnen/{term}', [BudgetExtrasController::class, 'destroyTerm'])->name('budget-terms.destroy');

            Route::get('prijsitems', PriceItemSearchController::class)->name('price-items.search');
            Route::get('prijslijst', [PriceListController::class, 'index'])->name('price-items.index');
            Route::post('prijslijst', [PriceListController::class, 'store'])->name('price-items.store');
            Route::match(['put', 'patch'], 'prijslijst/{priceItem}', [PriceListController::class, 'update'])->name('price-items.update');

            Route::post('projecten', [ProjectController::class, 'store'])->name('projects.store');
            Route::get('projecten/{project}/bewerken', [ProjectController::class, 'edit'])->name('projects.edit');
            Route::match(['put', 'patch'], 'projecten/{project}', [ProjectController::class, 'update'])->name('projects.update');
            Route::patch('projecten/{project}/status', [ProjectStatusController::class, 'update'])->name('projects.status');

            Route::post('projecten/{project}/opleverrapport', [DeliveryReportController::class, 'store'])->name('delivery-reports.store');
            Route::post('opleverrapporten/{deliveryReport}/ondertekenen', [DeliveryReportController::class, 'sign'])->name('delivery-reports.sign');
            Route::post('projecten/{project}/werkpakketten', [WorkPackageController::class, 'store'])->name('work-packages.store');
            Route::match(['put', 'patch'], 'werkpakketten/{workPackage}', [WorkPackageController::class, 'update'])->name('work-packages.update');
            Route::delete('werkpakketten/{workPackage}', [WorkPackageController::class, 'destroy'])->name('work-packages.destroy');
            Route::post('werkpakketten/{workPackage}/items', [ChecklistItemController::class, 'store'])->name('checklist-items.store');
            Route::delete('werkpakketten/{workPackage}/items/{item}', [ChecklistItemController::class, 'destroy'])->scopeBindings()->name('checklist-items.destroy');
            Route::patch('fotos/{photo}', [PhotoController::class, 'update'])->name('photos.update');
            // Werkbonnen beheren en goedkeuren: alleen de projectleiding.
            Route::post('werkbonnen', [WorkOrderController::class, 'store'])->name('work-orders.store');
            Route::match(['put', 'patch'], 'werkbonnen/{workOrder}', [WorkOrderController::class, 'update'])->name('work-orders.update');
            Route::post('werkbonnen/{workOrder}/goedkeuren', [WorkOrderApprovalController::class, 'approve'])->name('work-orders.approve');
            Route::post('werkbonnen/{workOrder}/afkeuren', [WorkOrderApprovalController::class, 'reject'])->name('work-orders.reject');

            Route::patch('fasen/{phase}', [ProjectPhaseController::class, 'update'])->name('phases.update');
            Route::post('fasen/{phase}/vrijgeven', [ProjectPhaseController::class, 'approve'])->name('phases.approve');

            Route::post('planning', [PlanningController::class, 'store'])->name('planning.store');
            Route::delete('planning/{scheduleEntry}', [PlanningController::class, 'destroy'])->name('planning.destroy');

            Route::post('nova', [NovaController::class, 'propose'])->middleware('throttle:30,1')->name('nova.propose');
            Route::post('nova/uitvoeren', [NovaController::class, 'execute'])->name('nova.execute');
            Route::get('nova/briefing', [NovaController::class, 'briefing'])->name('nova.briefing');

            Route::get('aandacht', [AttentionController::class, 'index'])->name('attention.index');

            Route::get('zoeken', SearchController::class)->name('search');

            Route::view('docs/api', 'docs.api')->name('docs.api');

            // De levende stijlgids (referentie voor alle UI).
            Route::view('stijlgids', 'styleguide.index')->name('styleguide');

            if (config('renovion.modules.automations')) {
                Route::get('automations', [AutomationController::class, 'index'])->name('automations.index');
                Route::patch('automations/{key}', [AutomationController::class, 'update'])->name('automations.update');
            }
        });

        // Teambeheer: alleen admins.
        Route::middleware('can:manage-team')->group(function () {
            Route::get('team', [TeamController::class, 'index'])->name('team.index');
            Route::get('team/aanmaken', [TeamController::class, 'create'])->name('team.create');
            Route::post('team', [TeamController::class, 'store'])->name('team.store');
            Route::get('team/{user}/bewerken', [TeamController::class, 'edit'])->name('team.edit');
            Route::match(['put', 'patch'], 'team/{user}', [TeamController::class, 'update'])->name('team.update');

            // Instellingen: bedrijfsgegevens en de standaardwaarden waarmee het
            // systeem rekent. Geheimen blijven in .env.
            Route::get('instellingen', [SettingController::class, 'index'])->name('settings.index');
            Route::match(['put', 'patch'], 'instellingen', [SettingController::class, 'update'])->name('settings.update');
        });
    });
});

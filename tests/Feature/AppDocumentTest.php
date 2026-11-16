<?php

namespace Tests\Feature;

use App\Enums\DocumentCategory;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** De documentenbibliotheek en één document als eigen schermen in de app, met het bestand via een ondertekend adres. */
class AppDocumentTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(User $user): string
    {
        $this->app['auth']->forgetGuards();

        return $user->createToken('test')->plainTextToken;
    }

    public function test_the_library_filters_like_the_website_with_groups_and_counts(): void
    {
        $customer = Customer::factory()->create(['name' => 'Fam. Jansen']);
        Document::factory()->create(['name' => 'Bouwtekening begane grond.pdf', 'category' => DocumentCategory::Tekening, 'customer_id' => $customer->id]);
        Document::factory()->create(['name' => 'Overeenkomst.pdf', 'category' => DocumentCategory::Contract]);
        Document::factory()->create(['name' => 'Keuringsrapport.pdf', 'category' => DocumentCategory::Keuring]);
        $token = $this->tokenFor(User::factory()->create(['role' => UserRole::Admin]));

        $all = $this->withToken($token)->getJson(route('api.app.documents.index'))->assertOk()
            ->assertJsonPath('total', 3)
            ->assertJsonPath('all', 3)
            ->assertJsonPath('more', false)
            ->assertJsonPath('groups.*.value', ['Commercieel', 'Technisch', 'Veiligheid en keuring']);
        $this->assertSame(1, collect($all->json('categories'))->firstWhere('value', 'tekening')['count']);

        $this->withToken($token)->getJson(route('api.app.documents.index', ['groep' => 'Technisch']))->assertOk()
            ->assertJsonPath('documents.*.name', ['Bouwtekening begane grond.pdf'])
            ->assertJsonPath('documents.0.icon', 'document-text')
            ->assertJsonPath('documents.0.sub', 'Bouwtekening, Fam. Jansen, '.now()->translatedFormat('j M Y'));
        $this->withToken($token)->getJson(route('api.app.documents.index', ['categorie' => 'contract']))->assertOk()
            ->assertJsonPath('documents.*.name', ['Overeenkomst.pdf']);
        $this->withToken($token)->getJson(route('api.app.documents.index', ['q' => 'keuring']))->assertOk()
            ->assertJsonPath('documents.*.name', ['Keuringsrapport.pdf']);
        // Een lege groep blijft staan als hij gekozen is.
        $this->withToken($token)->getJson(route('api.app.documents.index', ['groep' => 'Administratie']))->assertOk()
            ->assertJsonPath('documents', [])
            ->assertJsonPath('groups.3.value', 'Administratie');
    }

    public function test_a_document_opens_with_a_signed_link_and_executors_keep_the_website_rules(): void
    {
        Storage::fake('local');
        Storage::put('documents/plan.pdf', '%PDF-1.4 test');
        $project = Project::factory()->create(['name' => 'Badkamer Arnhem']);
        $document = Document::factory()->create([
            'name' => 'Plan.pdf', 'path' => 'documents/plan.pdf', 'size' => 2048, 'category' => DocumentCategory::Tekening,
            'documentable_id' => $project->id, 'customer_id' => $project->customer_id,
        ]);
        $craftsman = User::factory()->create(['role' => UserRole::Uitvoerder]);

        // Uitvoerder: geen bibliotheek (alleen kantoor), wel een document openen (zoals documents.show).
        $token = $this->tokenFor($craftsman);
        $this->withToken($token)->getJson(route('api.app.documents.index'))->assertForbidden();
        $this->withToken($token)->getJson(route('api.app.documents.show', $document))->assertOk()
            ->assertJsonPath('kind', 'pdf')
            ->assertJsonPath('size', '2 kB')
            ->assertJsonPath('customer', null)
            ->assertJsonPath('belongs_to', null);

        $project->craftsmen()->attach($craftsman);
        $this->withToken($token)->getJson(route('api.app.documents.show', $document))->assertOk()
            ->assertJsonPath('belongs_to.path', route('projects.show', $project, false));

        $admin = $this->tokenFor(User::factory()->create(['role' => UserRole::Admin]));
        $shown = $this->withToken($admin)->getJson(route('api.app.documents.show', $document))->assertOk()
            ->assertJsonPath('customer.path', route('customers.show', $project->customer_id, false))
            ->assertJsonPath('group', 'Technisch');

        $this->app['auth']->forgetGuards();
        $this->assertStringStartsWith('/api/app/documenten/'.$document->id.'/bestand?', $shown->json('url'));
        $this->get($shown->json('url'))->assertOk()->assertHeader('Content-Disposition', 'inline; filename=Plan.pdf');
        $this->get($shown->json('download_url'))->assertOk()->assertHeader('Content-Disposition', 'attachment; filename=Plan.pdf');
        $this->get('/api/app/documenten/'.$document->id.'/bestand')->assertForbidden();
    }

    public function test_customers_cannot_use_the_app_screens(): void
    {
        $document = Document::factory()->create();
        $customer = User::factory()->create(['role' => UserRole::Klant]);

        $this->withToken($this->tokenFor($customer))->getJson(route('api.app.documents.show', $document))->assertForbidden();
    }
}

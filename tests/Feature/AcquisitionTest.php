<?php

namespace Tests\Feature;

use App\Jobs\ImportProfile;
use App\Models\Lead;
use App\Models\SearchProfile;
use App\Models\User;
use App\Services\AreaMatcher;
use App\Services\Classifier;
use App\Services\Imports\ListingImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AcquisitionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function item(array $extra = []): array
    {
        return [...['external_id' => '123', 'title' => 'Wohnung Hilden', 'property_type' => 'apartment', 'market' => 'sale', 'postal_code' => '40721', 'city' => 'Hilden', 'price' => 299000, 'area' => 78, 'provider_type' => 'private', 'email' => 'owner@example.de', 'name' => 'Eigentümer'], ...$extra];
    }

    private function lead(array $extra = []): Lead
    {
        return app(ListingImporter::class)->ingest('manual', $this->item($extra))['lead'];
    }

    private function profile(array $extra = []): SearchProfile
    {
        return SearchProfile::create([...['name' => 'Hilden', 'active' => true, 'postal_patterns' => ['40XXX'], 'sources' => ['immowelt'], 'property_types' => ['apartment', 'house'], 'markets' => ['sale', 'rent'], 'area_mode' => 'any'], ...$extra]);
    }

    private function updateData(array $extra = []): array
    {
        return [...['status' => 'in_conversation', 'assigned_to' => null, 'follow_up_at' => null, 'acquisition_active' => true, 'contact_permission' => '', 'name' => 'Eigentümer', 'email' => 'owner@example.de', 'phone' => null, 'provider_type' => 'private', 'contact_blocked' => false, 'block_reason' => null], ...$extra];
    }

    public function test_login_required_and_valid_credentials_work(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertOk();
        User::factory()->create(['email' => 'admin@example.de', 'password' => 'TestPassword123']);
        $this->post('/login', ['email' => 'admin@example.de', 'password' => 'TestPassword123'])->assertRedirect('/');
        $this->assertAuthenticated();
    }

    public function test_disabled_user_cannot_login_or_keep_session(): void
    {
        $user = User::factory()->create(['active' => false, 'password' => 'TestPassword123']);
        $this->post('/login', ['email' => $user->email, 'password' => 'TestPassword123'])->assertSessionHasErrors('email');
        $this->actingAs($user)->get('/')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_admin_can_create_and_assign_a_manual_lead(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/leads', $this->item())->assertRedirect();
        $this->assertDatabaseHas('leads', ['assigned_to' => $admin->id, 'acquisition_active' => true]);
    }

    public function test_agent_cannot_modify_other_agents_lead(): void
    {
        $lead = $this->lead();
        $agent = User::factory()->create();
        $this->actingAs($agent)->put('/leads/'.$lead->id, $this->updateData())->assertForbidden();
        $this->get('/users')->assertForbidden();
        $this->post('/profiles', [])->assertForbidden();
    }

    public function test_assigned_agent_can_add_note_but_cannot_reassign(): void
    {
        $lead = $this->lead();
        $agent = User::factory()->create();
        $lead->update(['assigned_to' => $agent->id]);
        $this->actingAs($agent)->post('/leads/'.$lead->id.'/activities', ['type' => 'note', 'note' => 'Gespräch dokumentiert', 'occurred_at' => '2026-10-01T10:00:00Z'])->assertRedirect();
        $other = User::factory()->create();
        $this->put('/leads/'.$lead->id, $this->updateData(['assigned_to' => $other->id]))->assertForbidden();
        $this->assertDatabaseHas('activities', ['note' => 'Gespräch dokumentiert', 'user_id' => $agent->id]);
    }

    public function test_contact_block_cancels_active_acquisition_and_followup(): void
    {
        $lead = $this->lead();
        $this->actingAs($this->admin())->put('/leads/'.$lead->id, $this->updateData(['contact_blocked' => true, 'block_reason' => 'Widerspruch', 'follow_up_at' => '2026-10-05T10:00:00Z']))->assertRedirect();
        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'status' => 'blocked', 'acquisition_active' => false, 'follow_up_at' => null]);
        $this->post('/leads/'.$lead->id.'/activities', ['type' => 'phone', 'note' => 'Anruf', 'occurred_at' => now()->toISOString()])->assertStatus(422);
    }

    public function test_sale_and_rental_views_are_separate(): void
    {
        $this->lead();
        $this->lead(['external_id' => 'rent', 'market' => 'rent']);
        $this->actingAs($this->admin())->get('/')->assertInertia(fn (Assert $p) => $p->component('Leads')->has('leads.data', 1)->where('leads.data.0.property.market', 'sale'));
        $this->get('/?market=rent')->assertInertia(fn (Assert $p) => $p->has('leads.data', 1)->where('leads.data.0.acquisition_active', false));
    }

    public function test_plz_pattern_filters_the_list(): void
    {
        $this->lead();
        $this->lead(['external_id' => 'outside', 'postal_code' => '50667']);
        $this->actingAs($this->admin())->get('/?q=40XXX')->assertInertia(fn (Assert $p) => $p->has('leads.data', 1));
    }

    public function test_repeated_import_updates_price_without_overwriting_acquisition(): void
    {
        $lead = $this->lead();
        $user = User::factory()->create();
        $lead->update(['status' => 'in_conversation', 'assigned_to' => $user->id]);
        $lead->contact->update(['name' => 'Manuell korrigiert', 'contact_blocked' => true]);
        app(ListingImporter::class)->ingest('manual', $this->item(['price' => 279000, 'name' => 'Quellenname']));
        $this->assertDatabaseCount('leads', 1);
        $this->assertDatabaseCount('properties', 1);
        $this->assertDatabaseCount('contacts', 1);
        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'status' => 'in_conversation', 'assigned_to' => $user->id]);
        $this->assertDatabaseHas('contacts', ['name' => 'Manuell korrigiert', 'contact_blocked' => true]);
        $this->assertCount(2, $lead->property->listings()->first()->price_history);
    }

    public function test_classification_does_not_treat_commission_free_as_private(): void
    {
        $result = app(Classifier::class)->classify(['description' => 'Provisionsfrei']);
        $this->assertSame('unclear', $result['provider_type']);
        $lead = $this->lead(['description' => 'Bitte keine Makleranfragen']);
        $this->assertTrue($lead->contact->contact_blocked);
        $this->assertFalse($lead->acquisition_active);
    }

    public function test_area_matcher_handles_plz_radius_and_missing_coordinates(): void
    {
        $matcher = app(AreaMatcher::class);
        $p = $this->profile(['latitude' => 51.1686, 'longitude' => 6.9308, 'radius_km' => 10, 'area_mode' => 'all']);
        $this->assertFalse($matcher->matches($p, $this->item()));
        $this->assertTrue($matcher->matches($p, $this->item(['latitude' => 51.17, 'longitude' => 6.94])));
        $this->assertFalse($matcher->matches($p, $this->item(['postal_code' => '50667', 'latitude' => 51.17, 'longitude' => 6.94])));
        $p->area_mode = 'any';
        $this->assertTrue($matcher->matches($p, $this->item()));
        $this->assertFalse($matcher->matches($p, $this->item(['postal_code' => '50667', 'latitude' => 50.94, 'longitude' => 6.96])));
    }

    public function test_price_and_area_filters_exclude_unknown_values(): void
    {
        $p = $this->profile(['min_price' => 100000, 'max_area' => 100]);
        $matcher = app(AreaMatcher::class);
        $this->assertFalse($matcher->matches($p, $this->item(['price' => null])));
        $this->assertFalse($matcher->matches($p, $this->item(['area' => 120])));
        $this->assertTrue($matcher->matches($p, $this->item()));
    }

    public function test_profile_validation_and_admin_save(): void
    {
        $data = $this->profile()->toArray();
        unset($data['id'],$data['created_at'],$data['updated_at']);
        $this->actingAs($this->admin())->post('/profiles', [...$data, 'postal_patterns' => ['40XXX', '40721']])->assertSessionHasNoErrors();
        $this->post('/profiles', [...$data, 'postal_patterns' => ['40%']])->assertSessionHasErrors('postal_patterns.0');
        $this->post('/profiles', [...$data, 'postal_patterns' => [], 'radius_km' => 10, 'latitude' => null, 'longitude' => null])->assertSessionHasErrors('center');
    }

    public function test_missing_portal_access_produces_waiting_run(): void
    {
        Http::preventStrayRequests();
        $p = $this->profile();
        app()->call([new ImportProfile($p->id, 'immowelt'), 'handle']);
        $this->assertDatabaseHas('import_runs', ['source' => 'immowelt', 'status' => 'waiting', 'created_count' => 0]);
    }

    public function test_approved_feed_is_filtered_and_ingested(): void
    {
        config(['acquisition.sources.immowelt.enabled' => true, 'acquisition.sources.immowelt.access_approved' => true, 'acquisition.sources.immowelt.feed_url' => 'https://feed.example.de/listings']);
        Http::fake(['feed.example.de/*' => Http::response(['listings' => [$this->item(), $this->item(['external_id' => 'outside', 'postal_code' => '50667'])]])]);
        $p = $this->profile();
        app()->call([new ImportProfile($p->id, 'immowelt'), 'handle']);
        $this->assertDatabaseHas('import_runs', ['status' => 'completed', 'created_count' => 1, 'skipped_count' => 1]);
        $this->assertDatabaseCount('leads', 1);
    }

    public function test_daily_command_queues_each_profile_source(): void
    {
        Queue::fake();
        $this->profile(['sources' => ['immowelt', 'immoscout24', 'kleinanzeigen']]);
        $this->artisan('acquisition:import')->assertSuccessful();
        Queue::assertPushed(ImportProfile::class, 3);
    }

    public function test_user_management_requires_strong_password_and_protects_self(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/users', ['name' => 'Agent', 'email' => 'agent@example.de', 'role' => 'agent', 'active' => true, 'password' => 'weak'])->assertSessionHasErrors('password');
        $this->post('/users', ['name' => 'Agent', 'email' => 'agent@example.de', 'role' => 'agent', 'active' => true, 'password' => 'TestPassword123'])->assertRedirect();
        $this->put('/users/'.$admin->id, ['name' => $admin->name, 'email' => $admin->email, 'role' => 'agent', 'active' => false])->assertSessionHasErrors('role');
    }

    public function test_property_edits_are_audited_and_permission_checked(): void
    {
        $lead = $this->lead();
        $this->actingAs($this->admin())->put('/leads/'.$lead->id.'/property', ['title' => 'Korrigierter Titel', 'price' => 300000])->assertRedirect('/leads/'.$lead->id);
        $this->assertDatabaseHas('properties', ['id' => $lead->property_id, 'title' => 'Korrigierter Titel']);
        $this->assertDatabaseHas('activities', ['note' => 'Objektdaten manuell aktualisiert.']);
    }

    public function test_geocoder_works_for_hilden_without_external_request(): void
    {
        Http::preventStrayRequests();
        $this->actingAs($this->admin())->postJson('/geocode', ['query' => 'Hilden'])->assertOk()->assertJsonPath('0.latitude', 51.1686);
    }

    public function test_feed_with_unprocessed_pagination_is_rejected(): void
    {
        config(['acquisition.sources.immowelt.enabled' => true, 'acquisition.sources.immowelt.access_approved' => true, 'acquisition.sources.immowelt.feed_url' => 'https://feed.example.de/listings']);
        Http::fake(['feed.example.de/*' => Http::response(['listings' => [$this->item()], 'next_page' => 'page2'])]);
        $p = $this->profile();
        app()->call([new ImportProfile($p->id,'immowelt'), 'handle']);
        $this->assertDatabaseHas('import_runs',['status' => 'failed']);
        $this->assertDatabaseCount('leads',0);
    }
}

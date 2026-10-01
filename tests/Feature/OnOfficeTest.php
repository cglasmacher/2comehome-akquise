<?php

namespace Tests\Feature;

use App\Jobs\TransferLead;
use App\Models\Activity;
use App\Models\Lead;
use App\Models\User;
use App\Services\Imports\ListingImporter;
use App\Services\OnOffice\ApiException;
use App\Services\OnOffice\Client;
use App\Services\OnOffice\Transfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OnOfficeTest extends TestCase
{
    use RefreshDatabase;

    private bool $rejectEstate = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['onoffice.enabled' => true, 'onoffice.token' => 'test-token', 'onoffice.secret' => 'test-secret']);
        Http::preventStrayRequests();
    }

    private function lead(): Lead
    {
        return app(ListingImporter::class)->ingest('manual', ['external_id' => '123', 'title' => 'Wohnung', 'property_type' => 'apartment', 'market' => 'sale', 'email' => 'owner@example.de', 'name' => 'Besitzer', 'price' => 299000])['lead'];
    }

    private function response(array $records = [], int $error = 0): array
    {
        return ['status' => ['errorcode' => 0], 'response' => ['results' => [['status' => ['errorcode' => $error], 'data' => ['records' => $records]]]]];
    }

    private function fake(?int $duplicate = null): void
    {
        Http::fake(function ($r) use ($duplicate) {
            $a = $r['request']['actions'][0];
            $type = $a['resourcetype'];
            $action = $a['actionid'];
            if ($this->rejectEstate && $type === 'estate' && str_ends_with($action, ':create')) {
                return Http::response($this->response([], 111));
            }
            if (str_ends_with($action, ':read')) {
                return Http::response($this->response($type === 'address' && $duplicate ? [['id' => $duplicate, 'elements' => ['Name' => 'Vorhanden', 'Status' => 0]]] : []));
            }

            return Http::response($this->response([['id' => ['address' => 101, 'estate' => 201, 'relation' => 301, 'agentslog' => 401][$type]]]));
        });
    }

    public function test_client_generates_valid_hmac_without_disclosing_secret(): void
    {
        $this->fake();
        app(Client::class)->call('read', 'address', ['data' => ['Name']]);
        Http::assertSent(function ($r) {
            $a = $r['request']['actions'][0];
            $expected = base64_encode(hash_hmac('sha256', $a['timestamp'].'test-token'.'address'.$a['actionid'], 'test-secret', true));

            return hash_equals($expected, $a['hmac']) && $a['hmac_version'] === 2;
        });
    }

    public function test_transfer_creates_contact_property_owner_and_history_once(): void
    {
        $lead = $this->lead();
        $this->fake();
        $transfer = app(Transfer::class);
        $transfer->run($lead, null);
        $this->assertSame(101, $lead->fresh()->contact->onoffice_id);
        $this->assertSame(201, $lead->fresh()->property->onoffice_id);
        $this->assertSame('completed', $lead->fresh()->transfer_state);
        $this->assertDatabaseHas('activities', ['lead_id' => $lead->id, 'onoffice_id' => 401]);
        $before = count(Http::recorded());
        $transfer->run($lead, null);
        $this->assertCount($before, Http::recorded());
        Http::assertSent(fn ($r) => $r['request']['actions'][0]['resourcetype'] === 'relation' && str_ends_with($r['request']['actions'][0]['parameters']['relationtype'], ':owner'));
    }

    public function test_duplicate_contact_reused_without_overwrite(): void
    {
        $lead = $this->lead();
        $this->fake(999);
        app(Transfer::class)->run($lead, 999);
        $this->assertSame(999, $lead->fresh()->contact->onoffice_id);
        Http::assertNotSent(fn ($r) => $r['request']['actions'][0]['resourcetype'] === 'address' && str_ends_with($r['request']['actions'][0]['actionid'], ':create'));
    }

    public function test_duplicate_requires_explicit_selection(): void
    {
        $lead = $this->lead();
        $this->fake(999);
        try {
            app(Transfer::class)->run($lead, null);
            $this->fail('Expected selection error');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Kontakt', $e->getMessage());
        }
        $this->assertSame('pending', $lead->fresh()->transfer_state);
    }

    public function test_ambiguous_response_blocks_automatic_retry(): void
    {
        $lead = $this->lead();
        Http::fake(function ($r) {
            $a = $r['request']['actions'][0];

            return Http::response(str_ends_with($a['actionid'], ':read') ? $this->response() : ['unexpected' => 'reply']);
        });
        try {
            app(Transfer::class)->run($lead, null);
            $this->fail();
        } catch (ApiException $e) {
            $this->assertTrue($e->uncertain);
        }
        $this->assertSame('review', $lead->fresh()->transfer_state);
        $before = count(Http::recorded());
        try {
            app(Transfer::class)->run($lead, null);
            $this->fail();
        } catch (\RuntimeException $e) {
        }
        $this->assertCount($before, Http::recorded());
    }

    public function test_partial_success_keeps_ids_and_retry_only_completes_missing_steps(): void
    {
        $lead = $this->lead();
        $this->rejectEstate = true;
        $this->fake();
        try {
            app(Transfer::class)->run($lead, null);
            $this->fail();
        } catch (ApiException $e) {
            $this->assertFalse($e->uncertain);
        }
        $this->assertSame(101, $lead->fresh()->contact->onoffice_id);
        $this->assertSame('failed', $lead->fresh()->transfer_state);
        $this->rejectEstate = false;
        app(Transfer::class)->run($lead, null);
        $this->assertSame('completed', $lead->fresh()->transfer_state);
        $creates = collect(Http::recorded())->filter(fn ($pair) => $pair[0]['request']['actions'][0]['resourcetype'] === 'address' && str_ends_with($pair[0]['request']['actions'][0]['actionid'], ':create'));
        $this->assertCount(1, $creates);
    }

    public function test_transfer_needs_fresh_preview_and_confirmation(): void
    {
        $lead = $this->lead();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post('/leads/'.$lead->id.'/onoffice', ['confirmed' => true, 'token' => 'invented'])->assertStatus(409);
        $this->fake();
        $this->get('/leads/'.$lead->id.'/onoffice')->assertInertia(fn (Assert $p) => $p->component('TransferPreview')->has('token'));
        $token = session('transfer.'.$lead->id.'.token');
        $this->post('/leads/'.$lead->id.'/onoffice', ['confirmed' => true, 'token' => $token])->assertRedirect('/leads/'.$lead->id);
        $this->assertSame('completed', $lead->fresh()->transfer_state);
    }

    public function test_new_activities_can_be_appended_after_initial_transfer(): void
    {
        $lead = $this->lead();
        $this->fake();
        app(Transfer::class)->run($lead, null);
        Activity::create(['lead_id' => $lead->id, 'type' => 'note', 'note' => 'Neue Notiz', 'occurred_at' => now()]);
        $before = count(Http::recorded());
        app(Transfer::class)->run($lead, null);
        $this->assertCount($before + 1, Http::recorded());
    }

    public function test_contact_change_invalidates_preview_even_with_same_timestamp(): void
    {
        $lead = $this->lead();
        $this->fake();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/leads/'.$lead->id.'/onoffice')->assertOk();
        $token = session('transfer.'.$lead->id.'.token');
        $lead->contact->update(['name' => 'Neuer Name']);
        $this->post('/leads/'.$lead->id.'/onoffice', ['confirmed' => true, 'token' => $token])->assertStatus(409);
    }

    public function test_transfer_is_queued_for_background_processing(): void
    {
        Queue::fake();
        $lead = $this->lead();
        $this->fake();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/leads/'.$lead->id.'/onoffice');
        $token = session('transfer.'.$lead->id.'.token');
        $this->post('/leads/'.$lead->id.'/onoffice', ['confirmed' => true, 'token' => $token])->assertRedirect();
        $this->assertSame('queued',$lead->fresh()->transfer_state);
        Queue::assertPushed(TransferLead::class,1);
    }
}

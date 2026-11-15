<?php

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\DisposalStatus;
use App\Enums\LoanStatus;
use App\Enums\ProcurementStatus;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Disposal;
use App\Models\LoanRequest;
use App\Models\MaintenanceReport;
use App\Models\Procurement;
use App\Models\User;
use Illuminate\Support\Carbon;

test('guests are redirected to login when visiting reports', function () {
    $this->get(route('reports.loans'))->assertRedirect(route('login'));
    $this->get(route('reports.maintenance'))->assertRedirect(route('login'));
});

test('users without the report permission cannot view reports', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('reports.loans'))->assertForbidden();
    $this->get(route('reports.maintenance'))->assertForbidden();
    $this->get(route('reports.assets'))->assertForbidden();
});

test('loan report filters by period and counts overdue days', function () {
    $user = sarprasUser(['report.view']);

    LoanRequest::factory()->create([
        'borrowed_at' => '2026-11-05 08:00',
        'due_at' => '2026-11-10 00:00',
        'status' => LoanStatus::Active->value,
    ]);
    LoanRequest::factory()->create([
        'borrowed_at' => '2026-11-06 08:00',
        'due_at' => '2026-11-20 17:00',
        'returned_at' => '2026-11-15 10:00',
        'status' => LoanStatus::Returned->value,
    ]);
    LoanRequest::factory()->create([
        'borrowed_at' => '2026-10-01 08:00',
        'due_at' => '2026-10-05 08:00',
        'status' => LoanStatus::Active->value,
    ]);

    $this->travelTo(Carbon::parse('2026-11-15 12:00'));

    $response = $this->actingAs($user)->get(route('reports.loans', [
        'from' => '2026-11-01',
        'to' => '2026-11-30',
    ]));

    $response->assertOk()->assertInertia(fn ($assert) => $assert
        ->component('sarpras/reports/loans')
        ->has('rows', 2)
        ->where('summary.total', 2)
        ->where('summary.late', 1)
        ->where('summary.active', 1)
        ->where('rows.0.overdue_days', 5)
        ->where('rows.1.overdue_days', 0)
    );
});

test('loan report can be limited to late loans only', function () {
    $user = sarprasUser(['report.view']);

    LoanRequest::factory()->create([
        'borrowed_at' => '2026-11-05 08:00',
        'due_at' => '2026-11-10 00:00',
        'status' => LoanStatus::Active->value,
    ]);
    LoanRequest::factory()->create([
        'borrowed_at' => '2026-11-06 08:00',
        'due_at' => '2026-11-20 00:00',
        'status' => LoanStatus::Active->value,
    ]);

    $this->travelTo(Carbon::parse('2026-11-15 12:00'));

    $response = $this->actingAs($user)->get(route('reports.loans', [
        'from' => '2026-11-01',
        'to' => '2026-11-30',
        'late' => 1,
    ]));

    $response->assertOk()->assertInertia(fn ($assert) => $assert
        ->has('rows', 1)
        ->where('rows.0.overdue_days', 5)
    );
});

test('loan report can be exported as csv', function () {
    $user = sarprasUser(['report.view']);

    LoanRequest::factory()->create([
        'borrowed_at' => '2026-11-05 08:00',
        'due_at' => '2026-11-10 00:00',
        'purpose' => 'Rapat koordinasi',
        'status' => LoanStatus::Active->value,
    ]);

    $response = $this->actingAs($user)->get(route('reports.loans', [
        'from' => '2026-11-01',
        'to' => '2026-11-30',
        'export' => 'csv',
    ]));

    $response->assertOk();

    expect($response->headers->get('content-type'))->toContain('text/csv')
        ->and($response->streamedContent())->toContain('Peminjam')
        ->toContain('Rapat koordinasi');
});

test('maintenance report totals repair costs and recaps per category', function () {
    $user = sarprasUser(['report.view']);
    $category = Category::factory()->create(['name' => 'Elektronik']);
    $asset = Asset::factory()->create(['category_id' => $category->id]);

    MaintenanceReport::factory()->completed()->create([
        'asset_id' => $asset->id,
        'cost' => 100000,
        'created_at' => '2026-11-05 09:00',
    ]);
    MaintenanceReport::factory()->completed()->create([
        'asset_id' => $asset->id,
        'cost' => 50000,
        'created_at' => '2026-11-06 09:00',
    ]);
    MaintenanceReport::factory()->create([
        'asset_id' => $asset->id,
        'cost' => null,
        'created_at' => '2026-11-07 09:00',
    ]);

    $response = $this->actingAs($user)->get(route('reports.maintenance', [
        'from' => '2026-11-01',
        'to' => '2026-11-30',
    ]));

    $response->assertOk()->assertInertia(fn ($assert) => $assert
        ->component('sarpras/reports/maintenance')
        ->has('rows', 3)
        ->where('summary.total', 3)
        ->where('summary.completed', 2)
        ->where('summary.open', 1)
        ->where('summary.total_cost', fn ($cost) => (float) $cost === 150000.0)
        ->has('category_recap', 1)
        ->where('category_recap.0.category', 'Elektronik')
        ->where('category_recap.0.total', 3)
        ->where('category_recap.0.cost', fn ($cost) => (float) $cost === 150000.0)
    );
});

test('governance report summarizes procurement and disposal within the period', function () {
    $user = sarprasUser(['report.view']);

    $received = Procurement::factory()->create([
        'status' => ProcurementStatus::Received->value,
        'created_at' => '2026-11-05 09:00',
    ]);
    $received->items()->create(['name' => 'Laptop', 'quantity' => 2, 'unit_price' => 5000000]);
    Procurement::factory()->create([
        'status' => ProcurementStatus::Approved->value,
        'created_at' => '2026-11-06 09:00',
    ]);
    Procurement::factory()->create(['created_at' => '2026-10-01 09:00']);

    Disposal::factory()->create([
        'status' => DisposalStatus::Completed->value,
        'created_at' => '2026-11-07 09:00',
    ]);
    Disposal::factory()->create(['created_at' => '2026-11-08 09:00']);

    $response = $this->actingAs($user)->get(route('reports.governance', [
        'from' => '2026-11-01',
        'to' => '2026-11-30',
    ]));

    $response->assertOk()->assertInertia(fn ($assert) => $assert
        ->component('sarpras/reports/governance')
        ->has('procurements', 2)
        ->has('disposals', 2)
        ->where('summary.procurement_total', 2)
        ->where('summary.procurement_received', 1)
        ->where('summary.procurement_cost', fn ($cost) => (float) $cost === 10000000.0)
        ->where('summary.disposal_total', 2)
        ->where('summary.disposal_completed', 1)
    );
});

test('governance report can be exported as csv per module', function () {
    $user = sarprasUser(['report.view']);

    $procurement = Procurement::factory()->create([
        'title' => 'Pengadaan laboratorium',
        'created_at' => '2026-11-05 09:00',
    ]);
    $procurement->items()->create(['name' => 'Laptop', 'quantity' => 1, 'unit_price' => 5000000]);
    Disposal::factory()->create([
        'reason' => 'Rusak berat',
        'created_at' => '2026-11-06 09:00',
    ]);

    $procurementCsv = $this->actingAs($user)->get(route('reports.governance', [
        'from' => '2026-11-01',
        'to' => '2026-11-30',
        'export' => 'procurement',
    ]));

    $procurementCsv->assertOk();
    expect($procurementCsv->headers->get('content-type'))->toContain('text/csv')
        ->and($procurementCsv->streamedContent())->toContain('Usulan')
        ->toContain('Pengadaan laboratorium');

    $disposalCsv = $this->actingAs($user)->get(route('reports.governance', [
        'from' => '2026-11-01',
        'to' => '2026-11-30',
        'export' => 'disposal',
    ]));

    $disposalCsv->assertOk();
    expect($disposalCsv->streamedContent())->toContain('Aset')
        ->toContain('Rusak berat');
});

test('asset report filters by category and counts incomplete records', function () {
    $user = sarprasUser(['report.view']);
    $category = Category::factory()->create(['name' => 'Elektronik']);
    $otherCategory = Category::factory()->create(['name' => 'Furniture']);

    $complete = Asset::factory()->create([
        'category_id' => $category->id,
        'name' => 'Laptop',
        'condition' => AssetCondition::Baik->value,
        'status' => AssetStatus::Aktif->value,
    ]);
    $complete->images()->create(['path' => 'assets/1/laptop.jpg']);

    Asset::factory()->create([
        'inventory_code' => null,
        'category_id' => $category->id,
        'name' => 'Proyektor',
        'condition' => AssetCondition::Rusak->value,
    ]);
    Asset::factory()->create([
        'category_id' => $otherCategory->id,
        'name' => 'Meja',
    ]);

    $this->actingAs($user)
        ->get(route('reports.assets', ['category_id' => $category->id]))
        ->assertOk()
        ->assertInertia(fn ($assert) => $assert
            ->component('sarpras/reports/assets')
            ->has('rows', 2)
            ->has('missing_code', 1)
            ->has('missing_photo', 1)
            ->where('summary.total', 2)
            ->where('summary.without_code', 1)
            ->where('summary.without_photo', 1)
            ->where('summary.by_condition.0.count', 1)
            ->where('summary.by_condition.2.count', 1)
            ->where('missing_code.0.name', 'Proyektor'));
});

test('asset report can be exported as csv', function () {
    $user = sarprasUser(['report.view']);

    Asset::factory()->create([
        'inventory_code' => 'INV-90001',
        'name' => 'Laptop Laboratorium',
    ]);

    $response = $this->actingAs($user)->get(route('reports.assets', [
        'export' => 'csv',
    ]));

    $response->assertOk();

    expect($response->headers->get('content-type'))->toContain('text/csv')
        ->and($response->streamedContent())->toContain('Kode Inventaris')
        ->toContain('Laptop Laboratorium');
});

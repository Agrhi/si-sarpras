<?php

use App\Enums\AssetStatus;
use App\Enums\MaintenanceStatus;
use App\Enums\MaintenanceUrgency;
use App\Models\Asset;
use App\Models\MaintenanceReport;
use App\Models\User;
use App\Notifications\Sarpras\MaintenanceReportAssigned;
use App\Notifications\Sarpras\MaintenanceReportSubmitted;
use Illuminate\Support\Facades\Notification;

test('guests are redirected to login when visiting maintenance reports', function () {
    $this->get(route('maintenance-reports.index'))->assertRedirect(route('login'));
});

test('users without permission cannot view maintenance reports', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('maintenance-reports.index'))->assertForbidden();
});

test('submitting a report marks the asset as under repair and notifies technicians', function () {
    Notification::fake();

    $reporter = sarprasUser(['maintenance-report.view', 'maintenance-report.create']);
    $technician = roleUser('petugas-teknis');
    $asset = Asset::factory()->create();

    $response = $this->actingAs($reporter)->post(route('maintenance-reports.store'), [
        'asset_id' => $asset->id,
        'urgency' => MaintenanceUrgency::High->value,
        'description' => 'Proyektor tidak dapat menyala.',
    ]);

    $report = MaintenanceReport::firstWhere('asset_id', $asset->id);

    $response->assertRedirect(route('maintenance-reports.show', $report));
    expect($report->status)->toBe(MaintenanceStatus::Pending);
    expect($report->reported_by)->toBe($reporter->id);
    expect($asset->fresh()->status)->toBe(AssetStatus::DalamPerbaikan);
    Notification::assertSentTo($technician, MaintenanceReportSubmitted::class);
});

test('submitting a report for a borrowed asset keeps it borrowed', function () {
    $reporter = sarprasUser(['maintenance-report.create']);
    $asset = Asset::factory()->create(['status' => AssetStatus::Dipinjam]);

    $this->actingAs($reporter)->post(route('maintenance-reports.store'), [
        'asset_id' => $asset->id,
        'urgency' => MaintenanceUrgency::Low->value,
        'description' => 'Kabel power longgar.',
    ]);

    expect($asset->fresh()->status)->toBe(AssetStatus::Dipinjam);
});

test('reports cannot be filed for deleted assets', function () {
    $reporter = sarprasUser(['maintenance-report.create']);
    $asset = Asset::factory()->create(['status' => AssetStatus::Dihapus]);

    $response = $this->actingAs($reporter)->post(route('maintenance-reports.store'), [
        'asset_id' => $asset->id,
        'urgency' => MaintenanceUrgency::Medium->value,
        'description' => 'Tidak boleh.',
    ]);

    $response->assertSessionHasErrors('asset_id');
    expect(MaintenanceReport::count())->toBe(0);
});

test('assigning a report notifies the technician', function () {
    Notification::fake();

    $manager = sarprasUser(['maintenance-report.assign']);
    $technician = roleUser('petugas-teknis');
    $report = MaintenanceReport::factory()->create();

    $this->actingAs($manager)
        ->patch(route('maintenance-reports.assign', $report), ['assigned_to' => $technician->id])
        ->assertRedirect();

    expect($report->fresh()->assigned_to)->toBe($technician->id);
    Notification::assertSentTo($technician, MaintenanceReportAssigned::class);
});

test('assigning a user without the technician role fails', function () {
    $manager = sarprasUser(['maintenance-report.assign']);
    $report = MaintenanceReport::factory()->create();

    $this->actingAs($manager)
        ->patch(route('maintenance-reports.assign', $report), [
            'assigned_to' => User::factory()->create()->id,
        ])
        ->assertStatus(422);

    expect($report->fresh()->assigned_to)->toBeNull();
});

test('a technician can start processing a report', function () {
    $technician = roleUser('petugas-teknis', ['maintenance-report.process']);
    $report = MaintenanceReport::factory()->create();

    $this->actingAs($technician)
        ->patch(route('maintenance-reports.start', $report))
        ->assertRedirect();

    $report = $report->fresh();
    expect($report->status)->toBe(MaintenanceStatus::InProgress);
    expect($report->started_at)->not->toBeNull();
    expect($report->assigned_to)->toBe($technician->id);
});

test('completing the last open report returns the asset to service', function () {
    $technician = roleUser('petugas-teknis', ['maintenance-report.process']);
    $asset = Asset::factory()->create(['status' => AssetStatus::DalamPerbaikan]);
    $report = MaintenanceReport::factory()->inProgress()->create(['asset_id' => $asset->id]);

    $this->actingAs($technician)->patch(route('maintenance-reports.complete', $report), [
        'action_taken' => 'Mengganti kabel power.',
        'cost' => 150000,
        'spare_parts' => 'Kabel power',
    ])->assertRedirect();

    $report = $report->fresh();
    expect($report->status)->toBe(MaintenanceStatus::Completed);
    expect($report->completed_at)->not->toBeNull();
    expect((float) $report->cost)->toBe(150000.0);
    expect($asset->fresh()->status)->toBe(AssetStatus::Aktif);
});

test('completing a report keeps the asset under repair while another report is open', function () {
    $technician = roleUser('petugas-teknis', ['maintenance-report.process']);
    $asset = Asset::factory()->create(['status' => AssetStatus::DalamPerbaikan]);
    $report = MaintenanceReport::factory()->inProgress()->create(['asset_id' => $asset->id]);
    MaintenanceReport::factory()->create(['asset_id' => $asset->id]);

    $this->actingAs($technician)->patch(route('maintenance-reports.complete', $report), [
        'action_taken' => 'Perbaikan sementara.',
    ]);

    expect($asset->fresh()->status)->toBe(AssetStatus::DalamPerbaikan);
});

test('the reporter can cancel their own pending report', function () {
    $reporter = sarprasUser(['maintenance-report.cancel']);
    $asset = Asset::factory()->create(['status' => AssetStatus::DalamPerbaikan]);
    $report = MaintenanceReport::factory()->create([
        'asset_id' => $asset->id,
        'reported_by' => $reporter->id,
    ]);

    $this->actingAs($reporter)
        ->patch(route('maintenance-reports.cancel', $report))
        ->assertRedirect();

    expect($report->fresh()->status)->toBe(MaintenanceStatus::Cancelled);
    expect($asset->fresh()->status)->toBe(AssetStatus::Aktif);
});

test('the reporter cannot cancel a report that is already being processed', function () {
    $reporter = sarprasUser(['maintenance-report.cancel']);
    $report = MaintenanceReport::factory()->inProgress()->create([
        'reported_by' => $reporter->id,
    ]);

    $this->actingAs($reporter)
        ->patch(route('maintenance-reports.cancel', $report))
        ->assertForbidden();

    expect($report->fresh()->status)->toBe(MaintenanceStatus::InProgress);
});

test('report index lists submitted reports', function () {
    $user = sarprasUser(['maintenance-report.view']);
    MaintenanceReport::factory()->count(2)->create();

    $response = $this->actingAs($user)->get(route('maintenance-reports.index'));

    $response->assertOk()->assertInertia(fn ($assert) => $assert
        ->component('sarpras/maintenance-reports/index')
        ->has('reports', 2)
        ->where('can_create', false)
    );
});

test('report show page exposes available actions to the technician', function () {
    $technician = roleUser('petugas-teknis', [
        'maintenance-report.view',
        'maintenance-report.process',
    ]);
    $report = MaintenanceReport::factory()->create();

    $response = $this->actingAs($technician)
        ->get(route('maintenance-reports.show', $report));

    $response->assertOk()->assertInertia(fn ($assert) => $assert
        ->component('sarpras/maintenance-reports/show')
        ->where('report.id', $report->id)
        ->where('can.process', true)
        ->where('can.assign', false)
    );
});

test('a report notifies administrators when no technician exists', function () {
    Notification::fake();

    $admin = roleUser('admin');
    $reporter = sarprasUser(['maintenance-report.create']);
    $asset = Asset::factory()->create();

    $this->actingAs($reporter)->post(route('maintenance-reports.store'), [
        'asset_id' => $asset->id,
        'urgency' => MaintenanceUrgency::High->value,
        'description' => 'Lampu ruang rapat mati',
    ]);

    Notification::assertSentTo($admin, MaintenanceReportSubmitted::class);
});

test('technicians can filter the index to their own assignments', function () {
    $technician = roleUser('petugas-teknis', ['maintenance-report.view']);
    $mine = MaintenanceReport::factory()->create(['assigned_to' => $technician->id]);
    MaintenanceReport::factory()->create(['assigned_to' => null]);

    $this->actingAs($technician)
        ->get(route('maintenance-reports.index', ['mine' => 1]))
        ->assertOk()
        ->assertInertia(fn ($assert) => $assert
            ->has('reports', 1)
            ->where('reports.0.id', $mine->id)
            ->where('mine', true)
        );
});

<?php

namespace Tests\Feature;

use App\Http\Resources\RiskIssueRecordResource;
use App\Models\RiskIssue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** API-K05-09 RiskIssueRecord serialization; no database access is needed. */
class RiskIssueRecordResourceTest extends TestCase
{
    private const RISK_ISSUE = '55555555-5555-4555-8555-000000000001';

    public function test_returns_exactly_the_contract_fields_and_enum_values(): void
    {
        $response = $this->record()->assertOk()->assertExactJson([
            'riskIssueId' => self::RISK_ISSUE,
            'type' => 'ISSUE',
            'title' => 'Server uji tidak dapat diakses',
            'description' => 'Pengujian tertunda karena server uji tidak tersedia.',
            'impactLevel' => 'HIGH',
            'status' => 'CLOSED',
            'picAccountId' => 'acc-tl-0042',
            'createdAt' => '2026-09-08T09:00:00Z',
            'closedAt' => '2026-09-10T07:00:00Z',
            'resolutionNote' => 'Pengujian dipindahkan ke server cadangan.',
        ])->assertJsonCount(10);

        foreach (['data', 'reportedBy', 'projectId', 'mitigation', 'sourceMomId', 'updatedAt'] as $field) {
            $response->assertJsonMissingPath($field);
        }
    }

    public function test_nullable_fields_remain_present_as_null(): void
    {
        $this->record([
            'type' => 'RISK',
            'impact_level' => null,
            'status' => 'OPEN',
            'closed_at' => null,
            'resolution_note' => null,
        ])->assertOk()->assertExactJson([
            'riskIssueId' => self::RISK_ISSUE,
            'type' => 'RISK',
            'title' => 'Server uji tidak dapat diakses',
            'description' => 'Pengujian tertunda karena server uji tidak tersedia.',
            'impactLevel' => null,
            'status' => 'OPEN',
            'picAccountId' => 'acc-tl-0042',
            'createdAt' => '2026-09-08T09:00:00Z',
            'closedAt' => null,
            'resolutionNote' => null,
        ]);
    }

    public function test_timestamps_are_converted_to_utc_with_a_z_suffix(): void
    {
        $response = $this->record([
            'created_at' => '2026-09-08T16:00:00+07:00',
            'closed_at' => '2026-09-10T14:00:00+07:00',
        ])->assertOk()
            ->assertJsonPath('createdAt', '2026-09-08T09:00:00Z')
            ->assertJsonPath('closedAt', '2026-09-10T07:00:00Z');

        foreach (['createdAt', 'closedAt'] as $field) {
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $response->json($field));
        }
    }

    private function record(array $attributes = []): TestResponse
    {
        // Raw attributes exercise the model's enum/date casts without saving a row.
        $item = (new RiskIssue)->setRawAttributes(array_replace([
            'id' => self::RISK_ISSUE,
            'project_id' => 'PRJ-2026-014',
            'type' => 'ISSUE',
            'title' => 'Server uji tidak dapat diakses',
            'description' => 'Pengujian tertunda karena server uji tidak tersedia.',
            'impact_level' => 'HIGH',
            'mitigation' => 'Gunakan server cadangan.',
            'status' => 'CLOSED',
            'pic_account_id' => 'acc-tl-0042',
            'source_mom_id' => '22222222-2222-4222-8222-000000000003',
            'reported_by' => 'acc-pm-0007',
            'created_at' => '2026-09-08 09:00:00',
            'updated_at' => '2026-09-10 07:00:00',
            'closed_at' => '2026-09-10 07:00:00',
            'resolution_note' => 'Pengujian dipindahkan ke server cadangan.',
        ], $attributes));

        return TestResponse::fromBaseResponse((new RiskIssueRecordResource($item))->response());
    }
}

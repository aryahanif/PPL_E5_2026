<?php

namespace Tests\Feature;

use Tests\TestCase;

/** API-K05-09 GET /api/v1/projects/{projectId}/control-data (slice-1 stub). */
class ControlDataTest extends TestCase
{
    private function fetch(string $projectId, string $query)
    {
        return $this->withToken('mock-acc-tl-0042')->getJson("/api/v1/projects/{$projectId}/control-data{$query}");
    }

    public function test_returns_example_data_for_requested_period(): void
    {
        $this->fetch('PRJ-2026-014', '?periodStart=2026-09-01&periodEnd=2026-09-30')->assertOk()
            ->assertJsonPath('projectId', 'PRJ-2026-014')
            ->assertJsonPath('periodStart', '2026-09-01')
            ->assertJsonPath('periodEnd', '2026-09-30')
            ->assertJsonPath('changeLog.0.baselineItemId', 'BSL-014-03')
            ->assertJsonPath('changeLog.0.affectedTaskIds', ['TSK-014-027'])
            ->assertJsonStructure(['generatedAt', 'riskIssues' => [['riskIssueId', 'type', 'status']]]);
    }

    public function test_reversed_or_missing_period_is_400(): void
    {
        $this->fetch('PRJ-2026-014', '?periodStart=2026-09-30&periodEnd=2026-09-01')->assertStatus(400)
            ->assertJsonPath('code', 'INVALID_PERIOD');

        $this->fetch('PRJ-2026-014', '?periodStart=2026-09-01')->assertStatus(400)
            ->assertJsonPath('code', 'INVALID_PERIOD');
    }

    public function test_unknown_project_is_404(): void
    {
        $this->fetch('PRJ-2026-999', '?periodStart=2026-09-01&periodEnd=2026-09-30')->assertStatus(404)
            ->assertExactJson(['code' => 'PROJECT_NOT_FOUND', 'message' => 'Proyek PRJ-2026-999 tidak ditemukan']);
    }
}

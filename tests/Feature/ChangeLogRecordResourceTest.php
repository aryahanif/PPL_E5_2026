<?php

namespace Tests\Feature;

use App\Http\Resources\ChangeLogRecordResource;
use App\Models\ChangeRequest;
use App\Models\ChangeRequestTask;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** API-K05-09 ChangeLogRecord serialization; no database rows are needed. */
class ChangeLogRecordResourceTest extends TestCase
{
    private const CHANGE_REQUEST = '66666666-6666-4666-8666-000000000001';

    private const SECOND = '66666666-6666-4666-8666-000000000002';

    private const THIRD = '66666666-6666-4666-8666-000000000003';

    public function test_returns_exactly_the_contract_fields_and_enum_values(): void
    {
        $response = $this->record()->assertOk()->assertExactJson([
            'changeRequestId' => self::CHANGE_REQUEST,
            'changeDescription' => 'Tambah fitur export Excel',
            'source' => 'USER',
            'changeType' => 'SCOPE',
            'isFormalCr' => true,
            'impact' => 'Tambah 2 hari, 1 tugas baru',
            'status' => 'APPROVED',
            'decisionNote' => 'Disetujui, masuk sprint berikut',
            'createdAt' => '2026-09-15T05:00:00Z',
            'decidedAt' => '2026-09-17T04:00:00Z',
            'baselineItemId' => 'BSL-014-03',
            'affectedTaskIds' => ['TSK-014-027', 'TSK-014-028'],
        ])->assertJsonCount(12);

        foreach (['data', 'createdBy', 'analyzedBy', 'decidedBy', 'created_by', 'analyzed_by', 'decided_by', 'projectId', 'momId', 'updatedAt'] as $field) {
            $response->assertJsonMissingPath($field);
        }
    }

    public function test_recorded_nullable_fields_remain_present_and_empty_tasks_are_an_array(): void
    {
        $this->record([
            'source' => 'INTERNAL',
            'change_type' => 'TIMELINE',
            'is_formal_cr' => 0,
            'impact' => null,
            'status' => 'RECORDED',
            'decision_note' => null,
            'decided_at' => null,
            'baseline_item_id' => null,
            'analyzed_by' => null,
            'decided_by' => null,
        ], [])->assertOk()->assertExactJson([
            'changeRequestId' => self::CHANGE_REQUEST,
            'changeDescription' => 'Tambah fitur export Excel',
            'source' => 'INTERNAL',
            'changeType' => 'TIMELINE',
            'isFormalCr' => false,
            'impact' => null,
            'status' => 'RECORDED',
            'decisionNote' => null,
            'createdAt' => '2026-09-15T05:00:00Z',
            'decidedAt' => null,
            'baselineItemId' => null,
            'affectedTaskIds' => [],
        ])->assertJsonCount(12);
    }

    public function test_rejected_entry_keeps_its_status_and_decision_note(): void
    {
        $this->record([
            'status' => 'REJECTED',
            'decision_note' => 'Ditolak karena di luar anggaran.',
        ])->assertOk()
            ->assertJsonPath('status', 'REJECTED')
            ->assertJsonPath('decisionNote', 'Ditolak karena di luar anggaran.')
            ->assertJsonPath('isFormalCr', true)
            ->assertJsonCount(12);
    }

    public function test_timestamps_are_converted_to_utc_with_a_z_suffix(): void
    {
        $response = $this->record([
            'created_at' => '2026-09-15T12:00:00+07:00',
            'decided_at' => '2026-09-17T11:00:00+07:00',
        ])->assertOk()
            ->assertJsonPath('createdAt', '2026-09-15T05:00:00Z')
            ->assertJsonPath('decidedAt', '2026-09-17T04:00:00Z');

        foreach (['createdAt', 'decidedAt'] as $field) {
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $response->json($field));
        }
    }

    public function test_affected_task_ids_are_a_json_array_even_when_relation_keys_are_not_sequential(): void
    {
        $this->record([], [5 => 'TSK-014-027', 9 => 'TSK-014-028'])->assertOk()
            ->assertJsonPath('affectedTaskIds', ['TSK-014-027', 'TSK-014-028']);
    }

    public function test_unloaded_tasks_return_an_empty_array_without_lazy_loading(): void
    {
        $item = $this->item()->unsetRelation('affectedTasks');
        $this->expectsDatabaseQueryCount(0);

        TestResponse::fromBaseResponse((new ChangeLogRecordResource($item))->response())
            ->assertOk()
            ->assertJsonPath('affectedTaskIds', [])
            ->assertJsonCount(12);
        $this->assertFalse($item->relationLoaded('affectedTasks'));
    }

    public function test_collection_uses_loaded_task_relations_without_extra_queries_or_a_data_wrapper(): void
    {
        $items = new Collection([
            $this->item(),
            $this->item([
                'id' => self::SECOND,
                'status' => 'RECORDED',
                'is_formal_cr' => 0,
                'impact' => null,
                'decision_note' => null,
                'decided_at' => null,
                'baseline_item_id' => null,
            ], []),
            $this->item(['id' => self::THIRD, 'status' => 'REJECTED'], ['TSK-014-029']),
        ]);
        $this->expectsDatabaseQueryCount(0);

        $response = TestResponse::fromBaseResponse(ChangeLogRecordResource::collection($items)->response())
            ->assertOk()
            ->assertJsonCount(3)
            ->assertJsonMissingPath('data')
            ->assertJsonPath('0.affectedTaskIds', ['TSK-014-027', 'TSK-014-028'])
            ->assertJsonPath('1.affectedTaskIds', [])
            ->assertJsonPath('2.affectedTaskIds', ['TSK-014-029']);
        $this->assertSame([self::CHANGE_REQUEST, self::SECOND, self::THIRD], $response->json('*.changeRequestId'));

        foreach ([0, 1, 2] as $index) {
            $response->assertJsonCount(12, (string) $index)
                ->assertJsonMissingPath("{$index}.createdBy")
                ->assertJsonMissingPath("{$index}.analyzedBy")
                ->assertJsonMissingPath("{$index}.decidedBy");
        }
    }

    public function test_empty_collection_is_an_empty_json_array_without_a_data_wrapper(): void
    {
        TestResponse::fromBaseResponse(ChangeLogRecordResource::collection(new Collection)->response())
            ->assertOk()->assertExactJson([]);
    }

    private function record(array $attributes = [], array $taskIds = ['TSK-014-027', 'TSK-014-028']): TestResponse
    {
        return TestResponse::fromBaseResponse((new ChangeLogRecordResource($this->item($attributes, $taskIds)))->response());
    }

    private function item(array $attributes = [], array $taskIds = ['TSK-014-027', 'TSK-014-028']): ChangeRequest
    {
        // Raw attributes exercise the model's enum, boolean, and date casts without saving rows.
        $item = (new ChangeRequest)->setRawAttributes(array_replace([
            'id' => self::CHANGE_REQUEST,
            'project_id' => 'PRJ-2026-014',
            'mom_id' => '22222222-2222-4222-8222-000000000003',
            'change_description' => 'Tambah fitur export Excel',
            'source' => 'USER',
            'change_type' => 'SCOPE',
            'is_formal_cr' => 1,
            'baseline_item_id' => 'BSL-014-03',
            'impact' => 'Tambah 2 hari, 1 tugas baru',
            'analyzed_by' => 'acc-tl-0042',
            'status' => 'APPROVED',
            'decision_note' => 'Disetujui, masuk sprint berikut',
            'decided_by' => 'acc-pm-0007',
            'decided_at' => '2026-09-17 04:00:00',
            'created_by' => 'acc-tw-0091',
            'created_at' => '2026-09-15 05:00:00',
            'updated_at' => '2026-09-17 04:00:00',
        ], $attributes));

        $tasks = new Collection;
        foreach ($taskIds as $key => $taskId) {
            $tasks->put($key, (new ChangeRequestTask)->setRawAttributes([
                'change_request_id' => $item->id,
                'task_id' => $taskId,
                'impact_note' => 'Perbarui format laporan.',
                'added_by' => 'acc-tl-0042',
                'created_at' => '2026-09-16 03:00:00',
            ]));
        }

        return $item->setRelation('affectedTasks', $tasks);
    }
}

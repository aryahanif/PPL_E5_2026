<?php

namespace Tests\Feature;

use App\Contracts\IdentityProvider;
use App\Http\Resources\ActionItemResource;
use App\Http\Resources\MomResource;
use App\Models\ActionItem;
use App\Models\Mom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Shared pieces used by API-K05-02, 03 and 04: MomPolicy, ActionItemPolicy,
 * MomResource, ActionItemResource and ActionItem::isOverdue().
 */
class SharedStubsTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private const MOM = '22222222-2222-4222-8222-000000000003';

    private const ITEM_TODO_TL = '33333333-3333-4333-8333-000000000001';

    private const ITEM_OVERDUE_TW = '33333333-3333-4333-8333-000000000002';

    private const ITEM_DONE_PM = '33333333-3333-4333-8333-000000000003';

    private function account(string $id)
    {
        return app(IdentityProvider::class)->find($id);
    }

    public function test_overdue_is_computed_from_due_date_and_status(): void
    {
        $this->assertFalse(ActionItem::find(self::ITEM_TODO_TL)->isOverdue());   // due in the future
        $this->assertTrue(ActionItem::find(self::ITEM_OVERDUE_TW)->isOverdue()); // past due, IN_PROGRESS
        $this->assertFalse(ActionItem::find(self::ITEM_DONE_PM)->isOverdue());   // past due but DONE
    }

    public function test_mom_resource_matches_openapi_mom_schema(): void
    {
        $data = (new MomResource(Mom::find(self::MOM)))->toArray(Request::create('/'));

        $this->assertSame([
            'momId', 'meetingId', 'projectId', 'meetingDate', 'discussion', 'decision', 'changeSummary',
            'status', 'writtenBy', 'reviewedBy', 'reviewNote', 'finalizedAt', 'actionItems', 'createdAt', 'updatedAt',
        ], array_keys($data));
        $this->assertSame('PRJ-2026-014', $data['projectId']);
        $this->assertSame('FINAL', $data['status']);
        $this->assertCount(3, $data['actionItems']);
    }

    public function test_action_item_resource_matches_openapi_action_item_schema(): void
    {
        $data = (new ActionItemResource(ActionItem::find(self::ITEM_OVERDUE_TW)))->toArray(Request::create('/'));

        $this->assertSame([
            'actionItemId', 'momId', 'projectId', 'description', 'picAccountId', 'dueDate',
            'status', 'overdue', 'completedAt', 'createdAt', 'updatedAt',
        ], array_keys($data));
        $this->assertSame('IN_PROGRESS', $data['status']);
        $this->assertTrue($data['overdue']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $data['dueDate']);
    }

    public function test_mom_policy_roles(): void
    {
        $mom = Mom::find(self::MOM);
        $tw = Gate::forUser($this->account('acc-tw-0091'));
        $tl = Gate::forUser($this->account('acc-tl-0042'));
        $pm = Gate::forUser($this->account('acc-pm-0007'));

        $this->assertTrue($tw->allows('create', Mom::class));
        $this->assertTrue($tw->allows('update', $mom));
        $this->assertTrue($tw->allows('submit', $mom));
        $this->assertFalse($tw->allows('review', $mom));

        $this->assertTrue($tl->allows('review', $mom));
        $this->assertFalse($tl->allows('create', Mom::class));

        $this->assertFalse($pm->allows('create', Mom::class));
        $this->assertFalse($pm->allows('review', $mom));
        $this->assertTrue($pm->allows('view', $mom));
    }

    public function test_only_pic_may_update_action_item_status(): void
    {
        $item = ActionItem::find(self::ITEM_OVERDUE_TW); // PIC: acc-tw-0091

        $this->assertTrue(Gate::forUser($this->account('acc-tw-0091'))->allows('updateStatus', $item));
        $this->assertFalse(Gate::forUser($this->account('acc-pm-0007'))->allows('updateStatus', $item));
        $this->assertFalse(Gate::forUser($this->account('acc-tl-0042'))->allows('updateStatus', $item));
    }
}

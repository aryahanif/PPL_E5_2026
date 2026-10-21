<?php

namespace App\Integrations\Mock;

use App\Contracts\BaselineDirectory;
use App\Data\BaselineItem;
use Carbon\CarbonInterface;

/** Reads fake E-3 baselines from database/mocks/e3_baselines.json. */
class MockBaselineDirectory implements BaselineDirectory
{
    /** @var array<string, BaselineItem> */
    private array $items = [];

    /** @var array<string, list<array{date: string, progress: int|float}>> */
    private array $targets;

    public function __construct(string $fixturePath)
    {
        $data = json_decode(file_get_contents($fixturePath), true, flags: JSON_THROW_ON_ERROR);

        foreach ($data['items'] as $row) {
            $this->items[$row['baselineItemId']] = new BaselineItem(
                $row['baselineItemId'],
                $row['projectId'],
                $row['name'],
                $row['type'],
            );
        }

        $this->targets = $data['targets'];
    }

    public function find(string $baselineItemId): ?BaselineItem
    {
        return $this->items[$baselineItemId] ?? null;
    }

    public function targetProgress(string $projectId, CarbonInterface $date): ?float
    {
        $day = $date->toDateString();
        $latest = null;
        $progress = null;

        foreach ($this->targets[$projectId] ?? [] as $target) {
            if ($target['date'] <= $day && ($latest === null || $target['date'] > $latest)) {
                $latest = $target['date'];
                $progress = (float) $target['progress'];
            }
        }

        return $progress;
    }
}

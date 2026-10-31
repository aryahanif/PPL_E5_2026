<?php

namespace App\Integrations\Http;

use App\Contracts\ProjectMemberSource;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * INTERIM member source: a JSON file {"<D-1 projectId>": ["<F-1 accountId>", ...]}.
 * Fails closed: a missing file or unknown project means "no members", so access is denied
 * rather than granted. A malformed file is a configuration error and throws.
 */
class FileProjectMemberSource implements ProjectMemberSource
{
    /** @var array<string, list<string>>|null */
    private ?array $map = null;

    public function __construct(private string $path) {}

    public function memberAccountIds(string $projectId): array
    {
        return $this->map()[$projectId] ?? [];
    }

    public function allAccountIds(): array
    {
        $ids = [];

        foreach ($this->map() as $accountIds) {
            array_push($ids, ...$accountIds);
        }

        return array_values(array_unique($ids));
    }

    /** @return array<string, list<string>> */
    private function map(): array
    {
        if ($this->map !== null) {
            return $this->map;
        }

        if (! is_file($this->path)) {
            Log::warning("Project member file not found: {$this->path}. Nobody is a member of any project.");

            return $this->map = [];
        }

        $data = json_decode((string) file_get_contents($this->path), true);

        if (! is_array($data)) {
            throw new RuntimeException("Project member file is not a JSON object: {$this->path}");
        }

        foreach ($data as $projectId => $accountIds) {
            if (! is_array($accountIds) || array_filter($accountIds, fn ($id) => ! is_string($id) || $id === '')) {
                throw new RuntimeException("Project member file: members of {$projectId} must be a list of account ids.");
            }
        }

        return $this->map = array_map('array_values', $data);
    }
}

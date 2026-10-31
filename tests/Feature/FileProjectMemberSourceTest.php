<?php

namespace Tests\Feature;

use App\Integrations\Http\FileProjectMemberSource;
use RuntimeException;
use Tests\TestCase;

class FileProjectMemberSourceTest extends TestCase
{
    private function file(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'members');
        file_put_contents($path, $contents);

        return $path;
    }

    public function test_reads_members_per_project(): void
    {
        $source = new FileProjectMemberSource($this->file('{"p1":["a","b"],"p2":["b","c"]}'));

        $this->assertSame(['a', 'b'], $source->memberAccountIds('p1'));
        $this->assertSame([], $source->memberAccountIds('p3'));
        $this->assertSame(['a', 'b', 'c'], $source->allAccountIds());
    }

    public function test_missing_file_means_nobody_is_a_member(): void
    {
        $source = new FileProjectMemberSource('/nonexistent/members.json');

        $this->assertSame([], $source->memberAccountIds('p1'));
        $this->assertSame([], $source->allAccountIds());
    }

    public function test_invalid_json_is_a_configuration_error(): void
    {
        $this->expectException(RuntimeException::class);

        (new FileProjectMemberSource($this->file('not json')))->memberAccountIds('p1');
    }

    public function test_members_must_be_a_list_of_strings(): void
    {
        $this->expectException(RuntimeException::class);

        (new FileProjectMemberSource($this->file('{"p1":"a"}')))->memberAccountIds('p1');
    }

    public function test_the_example_file_is_valid(): void
    {
        $source = new FileProjectMemberSource(database_path('mocks/project_members.example.json'));

        $this->assertNotEmpty($source->allAccountIds());
    }
}

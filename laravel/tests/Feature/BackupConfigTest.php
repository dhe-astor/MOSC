<?php

namespace Tests\Feature;

use Tests\TestCase;

class BackupConfigTest extends TestCase
{
    public function test_backup_and_restore_guide_exists(): void
    {
        $guidePath = base_path('../BACKUP_AND_RESTORE_GUIDE.md');
        $this->assertTrue(file_exists($guidePath) || file_exists(base_path('BACKUP_AND_RESTORE_GUIDE.md')));
    }

    public function test_database_backup_configurations(): void
    {
        // Assert that connection properties exist
        $this->assertNotNull(config('database.connections.sqlite') ?: config('database.connections.pgsql'));
    }
}

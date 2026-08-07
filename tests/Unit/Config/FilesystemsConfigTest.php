<?php

/**
 * Covers that filesystem disks raise on a failed write rather than
 * silently returning false — a failed invoice/image write must be a
 * visible error, not a write that quietly never happened.
 */

declare(strict_types=1);

namespace Tests\Unit\Config;

use Tests\TestCase;

class FilesystemsConfigTest extends TestCase
{
    public function test_every_filesystem_disk_throws_on_failure(): void
    {
        foreach (['local', 'public', 's3'] as $disk) {
            $this->assertTrue(
                config("filesystems.disks.{$disk}.throw"),
                "Disk [{$disk}] must have 'throw' => true so a failed write raises instead of silently returning false.",
            );
        }
    }
}

<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    /**
     * Skip a test that would invoke openspout's XLSX writer, which calls
     * PHP's ZipArchive at runtime to build the .xlsx container. Some
     * environments (this sandbox) have no `ext-zip` loaded for the active
     * PHP CLI -- see .planning/phases/08-expenses-reporting/deferred-items.md.
     * Entitlement/denial tests that never reach the writer must NOT use
     * this guard; only tests that actually generate a file should.
     */
    protected function skipUnlessZipAvailable(?string $message = null): void
    {
        if (! class_exists(\ZipArchive::class)) {
            $this->markTestSkipped($message ?? 'ZipArchive (ext-zip) is not available in this environment; xlsx writer cannot run.');
        }
    }
}

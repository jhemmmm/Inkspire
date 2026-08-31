<?php

test('AuditLog is never mutated or deleted anywhere in app/', function () {
    $appDirectory = dirname(__DIR__, 3).'/app';

    $offendingFiles = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($appDirectory, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $contents = file_get_contents($file->getPathname());

        if (str_contains($contents, 'AuditLog::update(') || str_contains($contents, 'AuditLog::destroy(')) {
            $offendingFiles[] = $file->getPathname();
        }
    }

    expect($offendingFiles)->toBeEmpty(
        'AuditLog::update()/AuditLog::destroy() must never be called anywhere in app/ (AUDIT-02, D-04). Offending files: '.implode(', ', $offendingFiles),
    );
});

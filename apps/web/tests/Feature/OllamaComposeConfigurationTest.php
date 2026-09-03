<?php

use Illuminate\Support\Facades\File;

test('ollama prepares required models without a separate init container', function () {
    $projectRoot = dirname(base_path(), 2);
    $compose = File::get($projectRoot.'/compose.yaml');
    $entrypoint = File::get($projectRoot.'/infrastructure/ollama/entrypoint.sh');

    expect($compose)
        ->not->toContain("\n  ollama-init:")
        ->toContain('./infrastructure/ollama/entrypoint.sh:/opt/knowledge/ollama-entrypoint.sh:ro')
        ->toContain('ollama show \"$${OLLAMA_MODEL}\"')
        ->toContain('ollama show \"$${OLLAMA_EMBED_MODEL}\"');

    expect($entrypoint)
        ->toContain('ollama serve &')
        ->toContain('pull_if_missing "${OLLAMA_MODEL:?OLLAMA_MODEL is required}"')
        ->toContain('pull_if_missing "${OLLAMA_EMBED_MODEL:?OLLAMA_EMBED_MODEL is required}"');

    expect(File::get($projectRoot.'/docker-start.sh'))
        ->toContain('docker compose up -d --remove-orphans')
        ->toContain('docker compose down --remove-orphans');
});

test('deployment configuration uses monorepo service paths', function () {
    $projectRoot = dirname(base_path(), 2);
    $compose = File::get($projectRoot.'/compose.yaml');

    expect($compose)
        ->toContain('context: ./apps/web')
        ->toContain('context: ./services/document-processor')
        ->toContain('context: ./automation/n8n')
        ->and(File::exists($projectRoot.'/apps/web/artisan'))->toBeTrue()
        ->and(File::exists($projectRoot.'/automation/n8n/workflows/knowledge-workflows.json'))->toBeTrue()
        ->and(File::exists($projectRoot.'/services/document-processor/app/main.py'))->toBeTrue();
});

<?php

use Illuminate\Support\Facades\File;

test('ollama prepares required models without a separate init container', function () {
    $compose = File::get(base_path('docker-compose.yml'));
    $entrypoint = File::get(base_path('docker/ollama/entrypoint.sh'));

    expect($compose)
        ->not->toContain("\n  ollama-init:")
        ->toContain('./docker/ollama/entrypoint.sh:/opt/knowledge/ollama-entrypoint.sh:ro')
        ->toContain('ollama show \"$${OLLAMA_MODEL}\"')
        ->toContain('ollama show \"$${OLLAMA_EMBED_MODEL}\"');

    expect($entrypoint)
        ->toContain('ollama serve &')
        ->toContain('pull_if_missing "${OLLAMA_MODEL:?OLLAMA_MODEL is required}"')
        ->toContain('pull_if_missing "${OLLAMA_EMBED_MODEL:?OLLAMA_EMBED_MODEL is required}"');

    expect(File::get(base_path('docker-start.sh')))
        ->toContain('docker compose up -d --remove-orphans')
        ->toContain('docker compose down --remove-orphans');
});

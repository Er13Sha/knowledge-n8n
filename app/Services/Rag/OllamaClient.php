<?php

namespace App\Services\Rag;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OllamaClient
{
    /**
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    public function embed(array $texts): array
    {
        $embeddings = [];
        $batchSize = max(1, (int) config('services.rag.embedding_batch_size', 16));

        foreach (array_chunk($texts, $batchSize) as $batch) {
            $response = Http::timeout((int) config('services.rag.request_timeout', 600))
                ->post(rtrim((string) config('services.ollama.url'), '/').'/api/embed', [
                    'model' => config('services.ollama.embedding_model'),
                    'input' => $batch,
                    'truncate' => true,
                ])
                ->throw();

            $batchEmbeddings = $response->json('embeddings');

            if (! is_array($batchEmbeddings) || count($batchEmbeddings) !== count($batch)) {
                throw new RuntimeException('Ollama вернул некорректный набор embeddings.');
            }

            foreach ($batchEmbeddings as $embedding) {
                if (! is_array($embedding)) {
                    throw new RuntimeException('Ollama вернул некорректный embedding.');
                }

                $embeddings[] = array_values(array_map('floatval', $embedding));
            }
        }

        return $embeddings;
    }
}

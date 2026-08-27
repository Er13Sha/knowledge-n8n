<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'ollama' => [
        'url' => env('OLLAMA_URL', 'http://localhost:11434'),
        'model' => env('OLLAMA_MODEL', 'llama3.2:3b'),
        'embedding_model' => env('OLLAMA_EMBED_MODEL', 'nomic-embed-text'),
        'timeout' => env('OLLAMA_TIMEOUT', 600),
    ],

    'document_processor' => [
        'url' => env('DOCUMENT_PROCESSOR_URL', 'http://localhost:8001'),
        'token' => env('DOCUMENT_PROCESSOR_TOKEN'),
        'timeout' => env('DOCUMENT_PROCESSOR_TIMEOUT', 600),
    ],

    'qdrant' => [
        'url' => env('QDRANT_URL', 'http://localhost:6333'),
        'api_key' => env('QDRANT_API_KEY'),
        'collection' => env('QDRANT_COLLECTION', 'knowledge_documents'),
    ],

    'rag' => [
        'internal_token' => env('RAG_INTERNAL_TOKEN'),
        'chunk_size' => env('RAG_CHUNK_SIZE', 1400),
        'chunk_overlap' => env('RAG_CHUNK_OVERLAP', 200),
        'embedding_batch_size' => env('RAG_EMBEDDING_BATCH_SIZE', 16),
        'ocr_languages' => env('RAG_OCR_LANGUAGES', 'rus+eng'),
        'ocr_dpi' => env('RAG_OCR_DPI', 200),
        'top_k' => env('RAG_TOP_K', 6),
        'lexical_result_limit' => env('RAG_LEXICAL_RESULT_LIMIT', 50),
        'lexical_candidate_limit' => env('RAG_LEXICAL_CANDIDATE_LIMIT', 1000),
        'score_threshold' => env('RAG_SCORE_THRESHOLD', 0.25),
        'context_max_chars' => env('RAG_CONTEXT_MAX_CHARS', 16000),
        'request_timeout' => env('RAG_REQUEST_TIMEOUT', 600),
    ],

    'n8n' => [
        'index_webhook_url' => env('N8N_INDEX_WEBHOOK_URL'),
        'search_webhook_url' => env('N8N_SEARCH_WEBHOOK_URL'),
        'delete_webhook_url' => env('N8N_DELETE_WEBHOOK_URL'),
        'timeout' => env('N8N_TIMEOUT', 600),
    ],

];

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;

class ApiDocumentationController extends Controller
{
    public function __invoke(): Response
    {
        return response(File::get(resource_path('openapi/openapi.json')), headers: [
            'Cache-Control' => 'private, no-store',
            'Content-Type' => 'application/vnd.oai.openapi+json',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

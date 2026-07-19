<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MobileDemoApkController extends Controller
{
    public function __invoke(): BinaryFileResponse|Response
    {
        $path = (string) config('casa.mobile.apk_path');

        abort_unless(config('casa.mobile.demo_apk_enabled') && is_file($path), 404);

        return response()->download(
            $path,
            'Casa-Paraiso-Mobile-v1.0.1.apk',
            [
                'Cache-Control' => 'private, no-store',
                'Content-Type' => 'application/vnd.android.package-archive',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}

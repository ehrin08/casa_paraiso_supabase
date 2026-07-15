<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MobileDemoApkDownloadTest extends TestCase
{
    private string $apkPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->apkPath = storage_path('framework/testing/casa-paraiso-mobile.apk');
        File::ensureDirectoryExists(dirname($this->apkPath));
        File::put($this->apkPath, 'signed-apk-fixture');

        config(['casa.mobile.apk_path' => $this->apkPath]);
    }

    protected function tearDown(): void
    {
        File::delete($this->apkPath);

        parent::tearDown();
    }

    public function test_apk_is_available_only_while_the_mobile_demo_is_enabled(): void
    {
        config(['casa.mobile.demo_enabled' => false]);

        $this->get('/api/v1/demo/Casa-Paraiso-Mobile.apk')->assertNotFound();

        config(['casa.mobile.demo_enabled' => true]);

        $this->get('/api/v1/demo/Casa-Paraiso-Mobile.apk')
            ->assertOk()
            ->assertDownload('Casa-Paraiso-Mobile-v1.0.0.apk')
            ->assertHeader('Content-Type', 'application/vnd.android.package-archive')
            ->assertHeaderContains('Cache-Control', 'no-store');
    }

    public function test_apk_returns_not_found_when_the_release_artifact_is_missing(): void
    {
        config(['casa.mobile.demo_enabled' => true]);
        File::delete($this->apkPath);

        $this->get('/api/v1/demo/Casa-Paraiso-Mobile.apk')->assertNotFound();
    }
}

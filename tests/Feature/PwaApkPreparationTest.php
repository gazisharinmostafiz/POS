<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PwaApkPreparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pwa_manifest_valid(): void
    {
        $manifestPath = public_path('manifest.json');

        $this->assertFileExists($manifestPath);

        $manifest = json_decode(file_get_contents($manifestPath), true);

        $this->assertSame('PosLAB POS Platform', $manifest['name']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/', $manifest['start_url']);
        $this->assertSame('#020617', $manifest['theme_color']);
        $this->assertNotEmpty($manifest['icons']);

        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')));
            $this->assertContains($icon['sizes'], ['192x192', '512x512']);
            $this->assertSame('image/png', $icon['type']);
        }
    }

    public function test_app_can_be_installed_in_browser(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('rel="manifest"', false)
            ->assertSee('apple-mobile-web-app-capable', false)
            ->assertSee('theme-color', false);

        $this->assertFileExists(public_path('sw.js'));
        $this->assertStringContainsString(
            "navigator.serviceWorker.register('/sw.js')",
            file_get_contents(resource_path('js/app.js'))
        );
    }

    public function test_capacitor_project_builds_skeleton(): void
    {
        $this->assertFileExists(base_path('capacitor.config.ts'));
        $this->assertFileExists(base_path('capacitor-www/index.html'));
        $this->assertFileExists(base_path('android/app/build.gradle'));
        $this->assertFileExists(base_path('android/app/src/main/assets/capacitor.config.json'));

        $config = file_get_contents(base_path('capacitor.config.ts'));

        $this->assertStringContainsString('com.poslab.placeholder', $config);
        $this->assertStringContainsString('CAPACITOR_SERVER_URL', $config);
        $this->assertStringContainsString("webDir: 'capacitor-www'", $config);
    }

    public function test_mobile_layout_usable(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('name="viewport"', false)
            ->assertSee('width=device-width', false)
            ->assertSee('min-h-screen')
            ->assertSee('px-6')
            ->assertSee('sm:text-6xl');

        $this->assertFileExists(public_path('offline.html'));
        $this->assertStringContainsString(
            'width=device-width',
            file_get_contents(public_path('offline.html'))
        );
    }
}

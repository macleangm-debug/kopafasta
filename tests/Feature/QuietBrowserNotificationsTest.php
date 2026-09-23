<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuietBrowserNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_responses_disable_browser_notification_and_push_permission(): void
    {
        $response = $this->get('/');

        $policy = (string) $response->headers->get('Permissions-Policy');
        $this->assertStringContainsString('notifications=()', $policy);
        $this->assertStringContainsString('push=()', $policy);
        $response->assertSee('translate="no"', false);
        $response->assertSee('name="google" content="notranslate"', false);
    }

    public function test_app_js_blocks_automatic_browser_permission_requests(): void
    {
        $appJs = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString('Notification.requestPermission = () => Promise.resolve(\'denied\')', $appJs);
        $this->assertStringContainsString('PushManager.prototype.subscribe', $appJs);
        $this->assertStringContainsString('quietBrowserPermissionPrompts', $appJs);
        $this->assertStringContainsString('quietBrowserPasswordSave', $appJs);
        $this->assertStringContainsString('preventSilentAccess', $appJs);
    }

    public function test_no_frontend_source_requests_browser_notification_permission(): void
    {
        $roots = [
            resource_path('js'),
            resource_path('views'),
        ];

        $hits = [];
        foreach ($roots as $root) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
            foreach ($iterator as $file) {
                if (! $file->isFile()) {
                    continue;
                }
                $path = $file->getPathname();
                if (! preg_match('/\.(js|blade\.php)$/', $path)) {
                    continue;
                }

                $contents = file_get_contents($path);
                if ($contents === false) {
                    continue;
                }

                if (preg_match('/Notification\.requestPermission\s*\(/', $contents)) {
                    if (str_ends_with($path, '/app.js') && str_contains($contents, "Notification.requestPermission = () => Promise.resolve('denied')")) {
                        continue;
                    }
                    $hits[] = $path;
                }

                if (preg_match('/PushManager\.prototype\.subscribe\s*\(|\.subscribe\s*\(\s*\{[^}]*userVisibleOnly/', $contents)) {
                    if (str_ends_with($path, '/app.js')) {
                        continue;
                    }
                    $hits[] = $path;
                }
            }
        }

        $this->assertSame([], $hits, 'Automatic browser notification / push permission requests must be zero.');
    }
}

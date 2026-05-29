<?php

namespace Tests\Unit;

use App\Core\Support\WebPhpAccessGuard;
use Tests\TestCase;

class WebPhpAccessGuardTest extends TestCase
{
    public function test_path_is_allowed_respects_open_basedir_segments(): void
    {
        $base = base_path();
        $openBasedir = $base.PATH_SEPARATOR.sys_get_temp_dir();

        $this->assertTrue(WebPhpAccessGuard::pathIsAllowed($base.'/vendor/autoload.php', $openBasedir));
        $this->assertFalse(WebPhpAccessGuard::pathIsAllowed('/etc/ssl/certs/ca-certificates.crt', $openBasedir));
    }

    public function test_patch_pool_content_injects_app_root_after_public_shtml(): void
    {
        $guard = new WebPhpAccessGuard('/var/www/laravel', '/var/www/laravel/public');
        $method = new \ReflectionMethod(WebPhpAccessGuard::class, 'patchPoolContent');

        $original = <<<'CONF'
[www]
php_admin_value[open_basedir] = /home/admin/.composer:/home/admin/web/example.com/public_html:/home/admin/web/example.com/private:/home/admin/web/example.com/public_shtml:/home/admin/tmp:/tmp
CONF;

        $patched = $method->invoke($guard, $original, '/var/www/laravel');

        $this->assertStringContainsString(
            'public_shtml:/var/www/laravel:/home/admin/tmp',
            $patched
        );
    }

    public function test_patch_pool_content_is_idempotent_when_app_root_already_present(): void
    {
        $guard = new WebPhpAccessGuard('/var/www/laravel', '/var/www/laravel/public');
        $method = new \ReflectionMethod(WebPhpAccessGuard::class, 'patchPoolContent');

        $original = 'php_admin_value[open_basedir] = /var/www/laravel:/tmp';
        $patched = $method->invoke($guard, $original, '/var/www/laravel');

        $this->assertSame($original, $patched);
    }

    public function test_missing_required_paths_detects_app_root_outside_open_basedir(): void
    {
        $guard = new WebPhpAccessGuard('/var/www/laravel', '/var/www/laravel/public');
        $method = new \ReflectionMethod(WebPhpAccessGuard::class, 'missingRequiredPaths');

        $openBasedir = '/home/admin/web/example.com/public_html:/home/admin/web/example.com/private:/home/admin/web/example.com/public_shtml:/tmp';

        $missing = $method->invoke($guard, $openBasedir);

        $this->assertContains('/var/www/laravel', $missing);
    }
}

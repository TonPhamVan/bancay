<?php

namespace Tests\Support;

use Exceedone\Exment\Database\Seeder\InstallSeeder;
use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\LoginUser;
use Exceedone\Exment\Model\RoleGroup;
use Exceedone\Exment\Model\RoleGroupUserOrganization;
use Exceedone\Exment\Model\System;
use Exceedone\Exment\Providers\PluginServiceProvider;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Str;
use Tests\TestCase;

abstract class ExmentAdminTestCase extends TestCase
{
    use RefreshDatabase;

    protected function refreshTestDatabase()
    {
        // Native Exment template creates physical system-value tables. Do it before the test transaction.
        if (! RefreshDatabaseState::$migrated) {
            $this->artisan('migrate:fresh', $this->migrateFreshUsing());
            $this->app[Kernel::class]->setArtisan(null);
            RefreshDatabaseState::$migrated = true;
        }
        System::clearCache();
        if (! CustomTable::where('table_name', 'user')->exists()) {
            $this->seed(InstallSeeder::class);
            System::initialized(1);
        }
        $this->artisan('store:configure-admin')->assertSuccessful();
        (new PluginServiceProvider($this->app))->map();
        $this->beginDatabaseTransaction();
    }

    protected function loginAsRole(?string $role = null, bool $admin = false): LoginUser
    {
        System::clearCache();
        $user = CustomTable::getEloquent('user')->getValueModel();
        $user->value = ['user_code' => 'test-'.Str::lower(Str::random(8)), 'user_name' => 'Synthetic Administrator', 'email' => Str::random(10).'@example.test'];
        $user->save();
        $login = new LoginUser;
        $login->base_user_id = $user->getUserId();
        $login->password = Str::random(40);
        $login->save();
        if ($admin) {
            System::system_admin_users([$user->getUserId()]);
        }
        if ($role) {
            RoleGroupUserOrganization::create(['role_group_id' => RoleGroup::where('role_group_name', 'bancay_'.$role)->value('id'),
                'role_group_user_org_type' => 'user', 'role_group_target_id' => $user->getUserId()]);
        }
        System::clearCache();
        $this->actingAs($login, 'admin');

        return $login;
    }

    protected function storeUrl(string $module, string $path = ''): string
    {
        return '/admin/plugins/bancay_'.$module.($path ? '/'.$path : '');
    }
}

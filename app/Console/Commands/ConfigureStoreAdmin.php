<?php

namespace App\Console\Commands;

use Exceedone\Exment\Enums\MenuType;
use Exceedone\Exment\Enums\Permission;
use Exceedone\Exment\Enums\PluginType;
use Exceedone\Exment\Enums\RoleType;
use Exceedone\Exment\Model\Menu;
use Exceedone\Exment\Model\Plugin;
use Exceedone\Exment\Model\RoleGroup;
use Exceedone\Exment\Model\RoleGroupPermission;
use Exceedone\Exment\Model\System;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

final class ConfigureStoreAdmin extends Command
{
    protected $signature = 'store:configure-admin';

    protected $description = 'Register versioned Exment store page plugins and least-privilege role presets (no user assignment)';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Local/testing only; production release requires separate review.');

            return self::FAILURE;
        }
        if (! System::initialized()) {
            $this->error('Initialize Exment first.');

            return self::FAILURE;
        }
        DB::transaction(function () {
            foreach (File::directories(base_path('exment-plugins')) as $directory) {
                $config = json_decode(File::get($directory.'/config.json'), true, flags: JSON_THROW_ON_ERROR);
                if (! File::copyDirectory($directory, storage_path('app/plugins/'.basename($directory)))) {
                    throw new \RuntimeException('Unable to copy versioned store plugin files.');
                }
                $plugin = Plugin::firstOrNew(['plugin_name' => $config['plugin_name']]);
                $plugin->uuid = $config['uuid'];
                $plugin->plugin_view_name = $config['plugin_view_name'];
                $plugin->description = $config['description'];
                $plugin->version = $config['version'];
                $plugin->author = 'bancay';
                $plugin->plugin_types = PluginType::PAGE;
                $plugin->active_flg = 1;
                $plugin->options = ['uri' => $config['uri'], 'all_user_enabled' => $config['plugin_name'] === 'BancayDashboard'];
                $plugin->save();
                $menu = Menu::where('menu_name', 'store_'.$config['uri'])->where('menu_type', MenuType::PLUGIN)->first() ?? new Menu;
                // Native Menu inherits laravel-admin's fillable list, which omits Exment's type/target fields.
                $menu->forceFill(['menu_name' => 'store_'.$config['uri'], 'menu_type' => MenuType::PLUGIN,
                    'parent_id' => 0, 'title' => $config['plugin_view_name'], 'icon' => $config['icon'],
                    'order' => $config['order'], 'menu_target' => $plugin->id, 'uri' => $plugin->getRouteUri()])->save();
                // Clean only earlier generated store links with discarded Exment fields.
                Menu::whereNull('menu_name')->where('menu_type', '')->where('uri', $plugin->getRouteUri())->delete();
            }
            foreach (['product_manager' => ['catalog'], 'content_manager' => ['content'], 'order_manager' => ['orders', 'customers']] as $name => $modules) {
                $role = RoleGroup::firstOrNew(['role_group_name' => 'bancay_'.$name]);
                if ($role->exists) {
                    continue;
                } // Preserve permissions edited by the administrator on rerun.
                $role->role_group_view_name = ucwords(str_replace('_', ' ', $name));
                $role->description = 'Store module preset; assign to native Exment users explicitly.';
                $role->save();
                foreach ($modules as $module) {
                    RoleGroupPermission::create(['role_group_id' => $role->id, 'role_group_permission_type' => RoleType::PLUGIN,
                        'role_group_target_id' => Plugin::where('plugin_name', 'Bancay'.ucfirst($module))->value('id'),
                        'permissions' => [Permission::PLUGIN_ACCESS]]);
                }
            }
            System::permission_available(1);
        });
        System::clearCache();
        $this->info('Store plugins, menu and role presets configured; no identities/passwords/role assignments changed.');

        return self::SUCCESS;
    }
}

<?php

namespace App\Services\Admin;

use Exceedone\Exment\Enums\Permission;
use Exceedone\Exment\Enums\RoleType;
use Exceedone\Exment\Model\LoginUser;
use Exceedone\Exment\Model\Plugin;
use Exceedone\Exment\Model\RoleGroupPermission;
use Exceedone\Exment\Model\RoleGroupUserOrganization;

final class StoreAccess
{
    public function allows(LoginUser $user, string $module): bool
    {
        if ($user->isAdministrator()) {
            return true;
        }
        if (! $user->exists || ! $user->base_user) {
            return false;
        }
        $plugin = Plugin::where('plugin_name', 'Bancay'.ucfirst($module))->where('active_flg', 1)->first();
        if (! $plugin) {
            return false;
        }
        // Native Exment role records, direct user membership. Query fresh so revocation is immediate.
        $roles = RoleGroupUserOrganization::where('role_group_user_org_type', 'user')
            ->where('role_group_target_id', $user->getUserId())->pluck('role_group_id');

        return RoleGroupPermission::whereIn('role_group_id', $roles)->where('role_group_permission_type', RoleType::PLUGIN)
            ->where('role_group_target_id', $plugin->id)->get()->contains(function ($permission) {
                $permissions = $permission->permissions ?? [];

                return in_array(Permission::PLUGIN_ACCESS, $permissions, true) || array_key_exists(Permission::PLUGIN_ACCESS, $permissions);
            });
    }
}

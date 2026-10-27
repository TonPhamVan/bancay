<?php

namespace App\Console\Commands;

use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\LoginUser;
use Exceedone\Exment\Model\System;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class InitializeLocalAdmin extends Command
{
    protected $signature = 'store:initialize-local-admin {--email=admin@example.test}';

    protected $description = 'Initialize Exment only on local/testing, with a prompted or private environment password';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Local/testing environments only.');

            return self::FAILURE;
        }
        if (System::initialized()) {
            $this->warn('Exment is already initialized; no account was changed.');

            return self::SUCCESS;
        }
        $password = env('BANCAY_BOOTSTRAP_PASSWORD') ?: $this->secret('New local admin password (at least 16 characters)');
        $email = $this->option('email');
        Validator::make(compact('email', 'password'), [
            'email' => ['required', 'string', 'not_regex:/[\r\n]/', 'email'], 'password' => ['required', 'string', 'min:16', 'max:72'],
        ])->validate();

        DB::transaction(function () use ($password, $email) {
            $user = CustomTable::getEloquent('user')->getValueModel();
            $user->value = ['user_code' => 'local-admin', 'user_name' => 'Local Administrator', 'email' => $email];
            $user->saveOrFail();
            $login = new LoginUser;
            $login->base_user_id = $user->getUserId();
            $login->password = $password; // Exment's native mutator uses bcrypt/Hash.
            $login->saveOrFail();
            System::system_admin_users([$user->getUserId()]);
            System::site_name('bancay');
            System::site_name_short('bancay');
            System::initialized(1);
        });
        $this->info('Local Exment initialized. User code: local-admin. Password was not logged.');

        return self::SUCCESS;
    }
}

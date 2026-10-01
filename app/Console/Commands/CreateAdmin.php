<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'admin:create {email} {--name=}';

    protected $description = 'Administrator mit verdeckter Passwortabfrage anlegen';

    public function handle(): int
    {
        $data = ['email' => $this->argument('email'), 'name' => $this->option('name') ?: $this->ask('Name'), 'password' => $this->secret('Passwort (mind. 12 Zeichen, Groß-/Kleinbuchstaben und Zahl)')];
        $v = Validator::make($data, ['email' => 'required|email|unique:users', 'name' => 'required|string|max:255', 'password' => ['required', Password::min(12)->mixedCase()->numbers()]]);
        if ($v->fails()) {
            foreach ($v->errors()->all() as $error) {
                $this->error($error);
            }

return self::FAILURE;
        }
        User::create([...$data, 'role' => 'admin', 'active' => true]);
        $this->info('Administrator angelegt.');

        return self::SUCCESS;
    }
}

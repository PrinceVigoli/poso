<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Creates a staff account from the terminal.
 *
 * The user management screen needs an administrator to be signed in, so on a
 * fresh database there is no way in. The demo seeder creates one, but it also
 * inserts sample violators, which does not belong in a real installation.
 */
class MakeUser extends Command
{
    protected $signature = 'poso:make-user
        {--name= : Full name as it should appear on records}
        {--username= : Login name}
        {--email= : Contact address}
        {--role= : admin or enforcer}
        {--password= : Set non-interactively; this lands in your shell history}';

    protected $description = 'Create a staff account (admin or enforcer)';

    public function handle(): int
    {
        $this->line('');
        $this->info('Create a POSO staff account');
        $this->line('');

        $data = [
            'name'     => $this->valueFor('name', 'Full name'),
            'username' => $this->valueFor('username', 'Username (used to sign in)'),
            'email'    => $this->valueFor('email', 'Email address'),
            'role'     => $this->roleValue(),
            'password' => $this->passwordValue(),
        ];

        // The same rules the user management screen enforces, so an account
        // made here cannot be weaker than one made in the browser.
        $validator = Validator::make($data, [
            'name'     => 'required|string|max:255',
            'username' => 'required|string|max:50|unique:users,username',
            'email'    => 'required|email|unique:users,email',
            'role'     => 'required|in:admin,enforcer',
            'password' => 'required|string|min:15|max:128',
        ], [
            'password.min'      => 'The password must be at least 15 characters.',
            'username.unique'   => 'That username is taken. Pick another one.',
            'email.unique'      => 'That email address is already on an account.',
        ]);

        if ($validator->fails()) {
            $this->line('');
            $this->error('The account was not created:');
            foreach ($validator->errors()->all() as $message) {
                $this->line('  - '.$message);
            }
            $this->line('');
            return self::FAILURE;
        }

        $this->line('');
        $this->line('  Name     : '.$data['name']);
        $this->line('  Username : '.$data['username']);
        $this->line('  Email    : '.$data['email']);
        $this->line('  Role     : '.$data['role']);
        $this->line('');

        if ($this->input->isInteractive() && ! $this->confirm('Create this account?', true)) {
            $this->line('Cancelled. Nothing was created.');
            return self::SUCCESS;
        }

        // The model casts password as hashed, so the plain value is never stored.
        $user = User::create($data);

        AuditLog::record('created', 'users', "Created user from the console: {$user->username} ({$user->role})");

        $this->line('');
        $this->info("Account created. Sign in as '{$user->username}'.");
        $this->line('');

        return self::SUCCESS;
    }

    /** Takes the option when given, otherwise asks for it. */
    private function valueFor(string $option, string $question): string
    {
        $given = $this->option($option);
        if (filled($given)) { return trim($given); }
        if (! $this->input->isInteractive()) { return ''; }

        return trim((string) $this->ask($question));
    }

    private function roleValue(): string
    {
        $given = $this->option('role');
        if (filled($given)) { return strtolower(trim($given)); }
        if (! $this->input->isInteractive()) { return ''; }

        return $this->choice('Role', ['enforcer', 'admin'], 'enforcer');
    }

    /**
     * Asked for twice and never echoed. Falls back to --password for scripted
     * setups, which the help text flags as visible in shell history.
     */
    private function passwordValue(): string
    {
        $given = $this->option('password');
        if (filled($given)) { return $given; }
        if (! $this->input->isInteractive()) { return ''; }

        while (true) {
            $this->line('');
            $this->line('  The password must be at least 15 characters.');
            $password = (string) $this->secret('Password');
            $again = (string) $this->secret('Confirm password');

            if ($password === $again) { return $password; }

            $this->error('  Those did not match. Try again.');
        }
    }
}

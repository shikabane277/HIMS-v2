<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class BreakGlassAdmin extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'hims:break-glass
        {email? : The administrator email address}
        {--password= : New password to set}
        {--unlock : Clear failed login attempts and unlock the account}';

    /**
     * The console command description.
     */
    protected $description = 'Emergency break-glass access for administrators when email delivery or 2FA fails';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email') ?? $this->ask('Enter administrator email');

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("User with email [{$email}] not found.");

            return self::FAILURE;
        }

        if ($user->role !== 'admin') {
            $this->error("User [{$email}] is not an administrator. Break-glass is restricted to administrators.");

            return self::FAILURE;
        }

        $code = TwoFactorService::generateCode();
        $updates = [
            'two_factor_code' => hash('sha256', $code),
            'two_factor_expires_at' => now()->addMinutes(15),
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'updated_at' => now(),
        ];

        if ($newPassword = $this->option('password')) {
            $updates['password'] = Hash::make($newPassword);
            $this->info('Password updated successfully.');
        }

        DB::table('users')->where('id', $user->id)->update($updates);

        Log::critical('Break-glass emergency admin command executed', [
            'admin_id' => $user->id,
            'admin_email' => $user->email,
            'ip' => 'CLI',
        ]);

        $this->warn('⚠️  BREAK-GLASS EMERGENCY ACCESS GRANTED');
        $this->table(['Field', 'Value'], [
            ['Admin Email', $email],
            ['Account Status', 'Unlocked (failed attempts reset)'],
            ['One-Time 2FA Code', $code],
            ['Code Expiration', now()->addMinutes(15)->toTimeString()],
        ]);

        $this->line('Use this 6-character code on the 2FA challenge screen to log in immediately.');

        return self::SUCCESS;
    }
}

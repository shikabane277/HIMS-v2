<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->text('phone')->nullable()->change();
        });

        Schema::table('employee_credentials', function (Blueprint $table) {
            $table->text('credential_number')->nullable()->change();
        });

        // Encrypt phone in employees table
        $employees = DB::table('employees')->whereNotNull('phone')->where('phone', '!=', '')->get(['employee_id', 'phone']);
        foreach ($employees as $emp) {
            try {
                // Check if already encrypted
                Crypt::decryptString($emp->phone);
            } catch (Throwable $e) {
                // If decryption fails, it's plaintext — encrypt it!
                DB::table('employees')->where('employee_id', $emp->employee_id)->update([
                    'phone' => Crypt::encryptString($emp->phone),
                ]);
            }
        }

        // Encrypt credential_number in employee_credentials table
        $credentials = DB::table('employee_credentials')->whereNotNull('credential_number')->where('credential_number', '!=', '')->get(['credential_id', 'credential_number']);
        foreach ($credentials as $cred) {
            try {
                Crypt::decryptString($cred->credential_number);
            } catch (Throwable $e) {
                DB::table('employee_credentials')->where('credential_id', $cred->credential_id)->update([
                    'credential_number' => Crypt::encryptString($cred->credential_number),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Rollback decrypts plaintext if needed
        $employees = DB::table('employees')->whereNotNull('phone')->where('phone', '!=', '')->get(['employee_id', 'phone']);
        foreach ($employees as $emp) {
            try {
                $plain = Crypt::decryptString($emp->phone);
                DB::table('employees')->where('employee_id', $emp->employee_id)->update(['phone' => $plain]);
            } catch (Throwable $e) {
            }
        }

        $credentials = DB::table('employee_credentials')->whereNotNull('credential_number')->where('credential_number', '!=', '')->get(['credential_id', 'credential_number']);
        foreach ($credentials as $cred) {
            try {
                $plain = Crypt::decryptString($cred->credential_number);
                DB::table('employee_credentials')->where('credential_id', $cred->credential_id)->update(['credential_number' => $plain]);
            } catch (Throwable $e) {
            }
        }
    }
};

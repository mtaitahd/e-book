<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->cleanExistingPhoneNumbers();

        Schema::table('users', function (Blueprint $table) {
            $table->unique('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_phone_unique');
        });
    }

    /**
     * Reduce every existing value to the canonical 255XXXXXXXXX form, keeping
     * the first account to claim a number and clearing anything unparseable or
     * duplicated. MySQL allows repeated NULLs under a unique index, so the
     * accounts without a Mobile Money number are left untouched.
     */
    private function cleanExistingPhoneNumbers(): void
    {
        $claimed = [];

        DB::table('users')
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->orderBy('id')
            ->each(function (object $user) use (&$claimed): void {
                $digits = preg_replace('/\D+/', '', (string) $user->phone) ?? '';

                if (str_starts_with($digits, '0')) {
                    $digits = '255'.substr($digits, 1);
                } elseif (! str_starts_with($digits, '255')) {
                    $digits = '255'.$digits;
                }

                $isCanonical = preg_match('/^255(6|7)\d{8}$/', $digits) === 1 && ! isset($claimed[$digits]);

                if ($isCanonical) {
                    $claimed[$digits] = true;
                }

                if ($isCanonical && $digits === $user->phone) {
                    return;
                }

                DB::table('users')->where('id', $user->id)->update([
                    'phone' => $isCanonical ? $digits : null,
                ]);
            });
    }
};

<?php

namespace App\Services\Security;

use App\Models\User;
use App\Models\UserRecoveryCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use ParagonIE\ConstantTime\Base32;

final class RecoveryCodeService
{
    public const PATTERN = '/\b[A-Z2-7]{4}(?:-[A-Z2-7]{4}){3}\b/';

    /** @return list<string> */
    public function regenerate(User $user): array
    {
        $count = max(8, min(10, (int) config('mfa.recovery_code_count', 10)));
        $generationId = (string) Str::uuid();
        $codes = [];

        for ($index = 0; $index < $count; $index++) {
            $raw = substr(Base32::encodeUpperUnpadded(random_bytes(10)), 0, 16);
            $codes[] = implode('-', str_split($raw, 4));
        }

        DB::transaction(function () use ($user, $codes, $generationId): void {
            UserRecoveryCode::query()->where('user_id', $user->getKey())->delete();

            foreach ($codes as $code) {
                UserRecoveryCode::query()->create([
                    'user_id' => $user->getKey(),
                    'generation_id' => $generationId,
                    'code_hash' => Hash::make($code),
                ]);
            }
        });

        return $codes;
    }

    public function consume(User $user, string $candidate): bool
    {
        $candidate = strtoupper(trim($candidate));

        if (preg_match('/^[A-Z2-7]{4}(?:-[A-Z2-7]{4}){3}$/', $candidate) !== 1) {
            return false;
        }

        return DB::transaction(function () use ($user, $candidate): bool {
            $records = UserRecoveryCode::query()
                ->where('user_id', $user->getKey())
                ->whereNull('consumed_at')
                ->lockForUpdate()
                ->get();

            foreach ($records as $record) {
                if (Hash::check($candidate, $record->code_hash)) {
                    $record->forceFill(['consumed_at' => now()])->save();

                    return true;
                }
            }

            return false;
        });
    }

    /** @return list<string> */
    public function candidatesFromFile(string $contents): array
    {
        preg_match_all(self::PATTERN, strtoupper($contents), $matches);

        return array_values(array_unique($matches[0] ?? []));
    }
}

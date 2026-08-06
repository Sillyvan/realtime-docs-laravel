<?php

use App\Models\User;
use Illuminate\Support\Facades\Crypt;

/*
 * Fortify only registers its two-factor migration when the feature is enabled,
 * and it is not. Load it by hand so the factory state can still be verified.
 */
beforeEach(function () {
    $this->artisan('migrate', [
        '--path' => 'vendor/laravel/fortify/database/migrations',
        '--realpath' => false,
    ])->assertSuccessful();
});

test('the two factor state stores a usable secret and recovery codes', function () {
    $user = User::factory()->withTwoFactor()->create();

    expect($user->two_factor_confirmed_at)->not->toBeNull()
        ->and(Crypt::decrypt($user->two_factor_secret))->toBe('SECRETSECRETSECR')
        ->and(json_decode(Crypt::decrypt($user->two_factor_recovery_codes), true))
        ->toHaveCount(8);
});

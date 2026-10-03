<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // A key missing from a model's #[Fillable] list never vanishes
        // quietly. A silently dropped `confirmed_at` once kept every counter
        // payment out of the reports. Everywhere but production it throws;
        // in production it is reported instead, so the gap reaches the error
        // log without a sale at the counter failing over it. A model with no
        // fillable list at all still throws everywhere, as it always has.
        Model::preventSilentlyDiscardingAttributes();

        Model::handleDiscardedAttributeViolationUsing(function (Model $model, array $keys, MassAssignmentException $exception): void {
            throw_if(! app()->isProduction() || $model->totallyGuarded(), $exception);

            report($exception);
        });

        Password::defaults(fn (): Password => Password::min(12)
            ->mixedCase()
            ->letters()
            ->numbers()
            ->symbols()
            ->uncompromised(),
        );
    }
}

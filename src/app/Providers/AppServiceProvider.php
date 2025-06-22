<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Relations\Relation;

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
        //Add Blueprints for database migrations
        //source: https://laracasts.com/discuss/channels/laravel/writing-custom-blueprints-for-migration-in-my-large-project?page=1&replyId=901390
        Blueprint::macro('sequence', function ($col = 'id', $seq = 'id_seq') {
            /**
             * @var Blueprint $this
             */
            return $this->unsignedBigInteger($col)->default(DB::raw("nextval('{$seq}')"));
        });

        Blueprint::macro('timestampAudits', function () {
            /**
             * @var Blueprint $this
             */

            $this->bigInteger('created_by')->nullable();
            $this->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP(0)'));
            $this->bigInteger('updated_by')->nullable();
            $this->timestamp('updated_at')->nullable();
            $this->bigInteger('deleted_by')->nullable();
            $this->timestamp('deleted_at')->nullable();
        });

        Relation::morphMap([
            'opportunity' => \App\Models\Opportunity::class,
            'organization' => \App\Models\Organization::class,
        ]);
    }
}

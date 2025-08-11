<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\AliasLoader;

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

        Blueprint::macro('timestampAudits', function ($includeDeleted = true) {
            /**
             * @var Blueprint $this
             */

            $this->bigInteger('created_by')->nullable();
            $this->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP(0)'));
            $this->bigInteger('updated_by')->nullable();
            $this->timestamp('updated_at')->nullable();
            if($includeDeleted){
                $this->bigInteger('deleted_by')->nullable();
                $this->timestamp('deleted_at')->nullable();
            }
        });

        Relation::morphMap([
            'opportunity' => \App\Models\Opportunity::class,
            'organization' => \App\Models\Organization::class,
            'tag' => \App\Models\Tag::class,
            'file' => \App\Models\File::class,
            'user' => \App\Models\User::class,
            'opportunity_organization' => \App\Models\OpportunityOrganization::class,
            'user_opportunity' => \App\Models\UserOpportunity::class,
            'fileable' => \App\Models\Fileable::class,
            'taggable' => \App\Models\Taggable::class,
        ]);

        //add aliases

        //source: https://laracasts.com/discuss/channels/laravel/here-to-declare-aliases-on-laravel-11?page=1&replyId=929675
        $loader = AliasLoader::getInstance();

        $loader->alias('Opportunity', \App\Models\Opportunity::class);
        $loader->alias('Organization', \App\Models\Organization::class);
        $loader->alias('File', \App\Models\File::class);
        $loader->alias('Tag', \App\Models\Tag::class);
        $loader->alias('User', \App\Models\User::class);
        $loader->alias('OpportunityOrganization', \App\Models\OpportunityOrganization::class);
        $loader->alias('UserOpportunity', \App\Models\UserOpportunity::class);
        $loader->alias('Fileable', \App\Models\Fileable::class);
        $loader->alias('Taggable', \App\Models\Taggable::class);
    }
}

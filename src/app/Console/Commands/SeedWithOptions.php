<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Organization;
use App\Models\Opportunity;

class SeedWithOptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:seed-with-options {--organizations=5} {--opportunities=10}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed the database with a custom number of organizations and opportunities.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $orgCount = (int) ($this->option('organizations') ?? 5);
        $oppCount = (int) ($this->option('opportunities') ?? 10);

        $this->info("Seeding {$orgCount} organizations...");
        $organizations = Organization::factory()->count($orgCount)->create();

        $this->info("Seeding {$oppCount} opportunities...");
        $opportunities = Opportunity::factory()->count($oppCount)->create();

        // Assign the first org 2 opportunities if possible
        $remainingOpps = $opportunities->all();
        if ($oppCount > 1 && $organizations->count() > 0) {
            $firstOrg = $organizations->first();
            $firstTwo = array_splice($remainingOpps, 0, 2);
            foreach ($firstTwo as $opp) {
                $opp->organizations()->attach($firstOrg->id);
            }
        }

        // Now assign the rest of the opportunities
        $orgs = $organizations->slice(1); // skip the first org
        $orgsArr = $orgs->all();
        shuffle($orgsArr);

        // 90% of orgs should get 1 opp, 10% get 2 opps (if enough opps remain)
        $singleCount = (int) round($orgs->count() * 0.9);
        $multiCount = $orgs->count() - $singleCount;
        $assigned = 0;

        // Assign 1 opp to 90% of orgs
        foreach (array_slice($orgsArr, 0, $singleCount) as $org) {
            if (count($remainingOpps) === 0) break;
            $opp = array_shift($remainingOpps);
            $opp->organizations()->attach($org->id);
            $assigned++;
        }

        // Assign 2 opps to 10% of orgs
        foreach (array_slice($orgsArr, $singleCount, $multiCount) as $org) {
            if (count($remainingOpps) === 0) {
                break;
            };
            $opp1 = array_shift($remainingOpps);
            $opp1->organizations()->attach($org->id);
            $assigned++;
            if (count($remainingOpps) === 0) {
                break;
            };
            $opp2 = array_shift($remainingOpps);
            $opp2->organizations()->attach($org->id);
            $assigned++;
        }


        // Assign any remaining opps to random orgs so all opps are assigned
        foreach ($remainingOpps as $opp) {
            $org = $organizations->random();
            $opp->organizations()->attach($org->id);
        }

        $this->info('Seeding complete!');
        return 0;
    }
}

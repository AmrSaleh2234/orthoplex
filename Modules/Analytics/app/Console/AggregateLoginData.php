<?php

namespace Modules\Analytics\app\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Analytics\app\Models\LoginDaily;
use Modules\Analytics\app\Models\LoginEvent;

class AggregateLoginData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'analytics:aggregate-logins';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Aggregates login events into a daily summary.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Aggregating login data...');

        // This command should be run for each tenant.
        // We can achieve this by calling it from the tenancy:run command.

        $aggregates = LoginEvent::query()
            ->select(DB::raw('DATE(login_at) as date'), DB::raw('count(*) as login_count'))
            ->groupBy('date')
            ->get();

        foreach ($aggregates as $aggregate) {
            LoginDaily::updateOrCreate(
                ['date' => $aggregate->date],
                ['login_count' => $aggregate->login_count]
            );
        }

        $this->info('Login data aggregated successfully.');

        return 0;
    }
}

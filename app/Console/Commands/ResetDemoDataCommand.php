<?php

namespace App\Console\Commands;

use App\Models\Merchant;
use Database\Seeders\DemoMerchantsSeeder;
use Illuminate\Console\Command;

class ResetDemoDataCommand extends Command
{
    protected $signature = 'demo:reset';

    protected $description = 'Delete and regenerate the three demo merchants (never touches real prospect data)';

    public function handle(): int
    {
        Merchant::where('is_demo', true)->get()->each(fn (Merchant $merchant) => $merchant->delete());

        $this->call('db:seed', [
            '--class' => DemoMerchantsSeeder::class,
            '--force' => true,
        ]);

        $this->info('Demo data reset: 3 merchants regenerated.');

        return self::SUCCESS;
    }
}

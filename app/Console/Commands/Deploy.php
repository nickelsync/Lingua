<?php

namespace App\Console\Commands;

use App\Models\CefrLevel;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class Deploy extends Command
{
    protected $signature = 'app:deploy {--fresh-content : Re-run the full content seeder (overwrites seeded lessons edited in the CMS)}';

    protected $description = 'Run migrations, seed initial content on first deploy, ensure the admin user, and cache the framework';

    public function handle(): int
    {
        $this->call('migrate', ['--force' => true]);

        // Content is seeded once; later deploys must not clobber CMS edits.
        if ($this->option('fresh-content') || CefrLevel::count() === 0) {
            $this->call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);
        } else {
            $this->call('db:seed', ['--class' => AdminUserSeeder::class, '--force' => true]);
        }

        Artisan::call('storage:link', ['--force' => true]);
        $this->call('optimize');

        $this->info('Deploy complete.');

        return self::SUCCESS;
    }
}

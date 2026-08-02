<?php

namespace App\Console\Commands;

use App\Services\ShuttleServiceMaterializer;
use Illuminate\Console\Command;

class MaterializeShuttleServices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shuttle-services:materialize';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Materialize today’s shuttle services and flag departed services for closeout';

    /**
     * Execute the console command.
     */
    public function handle(ShuttleServiceMaterializer $materializer): int
    {
        $result = $materializer->materializeCurrentDay();

        $this->components->info(
            "Shuttle services synchronized: {$result['created']} created, {$result['transitioned']} awaiting completion."
        );

        return self::SUCCESS;
    }
}

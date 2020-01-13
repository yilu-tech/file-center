<?php


namespace YiluTech\FileCenter\Commands;


use Illuminate\Console\Command;
use YiluTech\FileCenter\Server;
use YiluTech\Permission\Models\Role;

class ClearTempCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'file:clear-temp';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'clear temp directory.';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $buckets = config('filesystems.buckets');

        if (empty($buckets)) {
            $this->info('no bucket clear.');
            return;
        }
        foreach ($buckets as $bucket => $data) {
            $server = new Server($bucket);
            $server->clearTemp();
        }
        $this->info('cleared.');
    }
}

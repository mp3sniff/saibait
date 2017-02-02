<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class VpnOnlineUserDelete extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vpnonlinedelete';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $auto_delete = \App\OnlineUser::where('updated_at', '<=', \Carbon\Carbon::now()->subMinutes(5));
        $auto_delete->delete();
    }
}
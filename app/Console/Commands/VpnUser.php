<?php

namespace App\Console\Commands;

use App\OnlineUser;
use Illuminate\Console\Command;

class VpnUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vpnuser';

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
        $servers = \App\VpnServer::where('is_active', 1)->get();
        foreach ($servers as $server) {
            $logs = $this->parseLog('http://' . strtolower($server->server_domain) . '/logs/logs.log', 'tcp');
            foreach($logs as $log)
            {
                $update_online = \App\User::where('username', $log['CommonName'] ? $log['CommonName'] : 'UNDEF')->first();
                if(count($update_online) > 0) {
                    $update_online->onlineuser->byte_sent = intval($log['BytesSent']) ? intval($log['BytesSent']) : 0;
                    $update_online->onlineuser->byte_received = intval($log['BytesReceived']) ? intval($log['BytesReceived']) : 0;
                    $update_online->onlineuser->touch();
                    $update_online->onlineuser->save();
                }
            }
        }
    }

    public function parseLog($log, $proto) {
        //global $uid, $ctr;
        $status = array();
        $ctr = 0;
        $uid = 0;
        $handle = @fopen($log, "r");

        if($handle) {
            while (!@feof($handle)) {
                $buffer = @fgets($handle, 4096);

                unset($match);

                //if (ereg("^Updated,(.+)", $buffer, $match)) {
                    //$status['updated'] = $match[1];
                //}

                if (preg_match("/^(.+),(\d+\.\d+\.\d+\.\d+\:\d+),(\d+),(\d+),(.+)$/", $buffer, $match)) {
                    if ($match[1] <> 'Common Name' && $match[1] <> 'UNDEF' && $match[1] <> 'client') {
                        //      $cn = $match[1];

                        // for each remote ip:port because smarty doesnt
                        // like looping on strings in a section
                        $userlookup[$match[2]] = $uid;

                        $status[$ctr]['CommonName'] = $match[1];
                        $status[$ctr]['RealAddress'] = $match[2];
                        $status[$ctr]['BytesReceived'] = $match[3]; #sizeformat($match[3]);
                        $status[$ctr]['BytesSent'] = $match[4]; #sizeformat($match[4]);
                        $status[$ctr]['Since'] = $match[5];
                        $status[$ctr]['Proto'] = $proto;
                        $uid++; $ctr++;
                    }
                }

            }
            @fclose($handle);
        }
        return $status;
    }
}

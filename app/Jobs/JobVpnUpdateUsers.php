<?php

namespace App\Jobs;

use App\SiteSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Schema;

class JobVpnUpdateUsers implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;
    
    protected $server_id;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($server_id)
    {
        $this->server_id = $server_id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            if(Schema::hasTable('site_settings') && SiteSettings::where('id', 1)->exists()) {
                $db_settings = SiteSettings::find(1);

                $server = \App\VpnServer::findorfail($this->server_id);
                $logs = $this->parseLog($server->server_ip, 'tcp', $server->web_port);
                foreach($logs as $log)
                {
                    try {
                        $user = \App\User::where('username', $log['CommonName'])->firstorfail();
                        $real_address = explode(":", $log['RealAddress']);
                        //$login_session = $user->vpn->count();
                        if($user->vpn->count() >= 1) {
                            $vpn_user = $user->vpn()->where('vpn_server_id', $this->server_id);
                            $vpn_user->update(['byte_sent' => floatval($log['BytesSent']) ? floatval($log['BytesSent']) : 0, 'byte_received' => floatval($log['BytesReceived']) ? floatval($log['BytesReceived']) : 0]);
                            //$vpn_user->update(['byte_sent' => 0, 'byte_received' => 0]);
                        } else {
                            $job = (new JobVpnDisconnectUser($log['CommonName'], $server->server_ip, $server->server_port))->onConnection($db_settings->settings['queue_driver'])->onQueue('disconnect_user');
                            dispatch($job);
                        }
                    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $ex) {
                        $job = (new JobVpnDisconnectUser($log['CommonName'], $server->server_ip, $server->server_port))->onConnection($db_settings->settings['queue_driver'])->onQueue('disconnect_user');
                        dispatch($job);
                    }
                }

            }
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $ex) {
            //
        }
    }

    public function availableIp($host, $port, $timeout=3) {
        $fp = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if($fp) {
            return true;
        } else {
            return false;
        }
    }


    public function parseLog($ip, $proto, $port=80) {
        $status = array();
        $ctr = 0;
        $uid = 0;
        
        if($this->availableIp($ip, $port)) {
            $handle = @fopen('http://' . $ip . ':' . $port . '/logs/logs.log', "r");

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
        }
        
        return $status;
    }
    
}

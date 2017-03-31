<?php

namespace App\Http\Controllers\VpnServer;

use App\SiteSettings;
use App\VpnServer;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7;

class AddServerController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware(['auth:api']);
    }

    public function index()
    {
        $permission['is_admin'] = auth()->user()->isAdmin();
        $permission['update_account'] = auth()->user()->can('update-account');
        $permission['manage_user'] = auth()->user()->can('manage-user');

        if (!auth()->user()->isAdmin()) {
            return response()->json([
                'message' => 'No permission to access this page.',
                'profile' => auth()->user(),
                'permission' => $permission,
            ], 403);
        }

        return response()->json([
            'profile' => auth()->user(),
            'permission' => $permission,
        ], 200);
    }

    public function addServer(Request $request)
    {
        $permission['is_admin'] = auth()->user()->isAdmin();
        $permission['update_account'] = auth()->user()->can('update-account');
        $permission['manage_user'] = auth()->user()->can('manage-user');

        if (!auth()->user()->isAdmin()) {
            return response()->json([
                'message' => 'Action not allowed.',
                'profile' => auth()->user(),
                'permission' => $permission,
            ], 403);
        }

        $this->validate($request, [
            'server_name' => 'bail|required|unique:vpn_servers,server_name',
            'server_ip' => 'bail|required|ip|unique:vpn_servers,server_ip',
            'server_domain' => 'bail|required|unique:vpn_servers,server_domain',
            'web_port' => 'bail|required|integer',
            'server_key' => 'bail|required|unique:vpn_servers,server_key',
            'vpn_secret' => 'required',
            'server_port' => 'bail|required|integer',
            'server_access' => 'bail|required|in:0,1,2',
            'server_status' => 'bail|required|boolean',
            'package_bronze' => 'bail|required|boolean',
            'package_silver' => 'bail|required|boolean',
            'package_gold' => 'bail|required|boolean',
            'limit_bandwidth' => 'bail|required|boolean',
        ]);

        $site_settings = SiteSettings::find(1);

        $client = new Client(['base_uri' => 'https://api.cloudflare.com']);
        $response = $client->request('POST', "/client/v4/zones/{$site_settings->settings['cf_zone']}/dns_records",
            ['http_errors' => false, 'headers' => ['X-Auth-Email' => 'mp3sniff@gmail.com', 'X-Auth-Key' => 'ff245b46bd71002891e2890059b122e80b834', 'Content-Type' => 'application/json'], 'json' => ['type' => 'A', 'name' => $request->server_domain, 'content' => $request->server_ip]]);

        $cloudflare = json_decode($response->getBody());

        if(!$cloudflare->success) {
            return response()->json([
                'message' => 'Cloudflare: ' . $cloudflare->errors[0]->message,
            ], 403);
        }

        $allowed_userpackage['bronze'] = (int)$request->package_bronze;
        $allowed_userpackage['silver'] = (int)$request->package_silver;
        $allowed_userpackage['gold'] = (int)$request->package_gold;

        $server = new VpnServer;
        $server->cf_id = $cloudflare->result->id;
        $server->server_name = $request->server_name;
        $server->server_ip = $request->server_ip;
        $server->server_domain = $request->server_domain;
        $server->web_port = $request->web_port;
        $server->server_key = $request->server_key;
        $server->server_port = $request->server_port;
        $server->vpn_secret = $request->vpn_secret;
        $server->access = $request->server_access;
        $server->is_active = $request->server_status;
        $server->allowed_userpackage = $allowed_userpackage;
        $server->limit_bandwidth = (int)$request->limit_bandwidth;
        $server->save();

        return response()->json([
            'message' => 'New server added.',
        ], 200);
    }
}

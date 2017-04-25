<?php

namespace App\Http\Controllers;

use App\Lang;
use App\VpnServer;
use Illuminate\Http\Request;

class PublicServerStatusController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest');
    }
    
    public function index()
    {
        $db_settings = \App\SiteSettings::findorfail(1);

        $language = Lang::all()->pluck('name');

        if (!$db_settings->settings['enable_panel_login']) {
            return response()->json([
                'site_options' => ['site_name' => $db_settings->settings['site_name'], 'sub_name' => 'Error'],
                'message' => 'Maintenance Mode.',
                'language' => $language,
            ], 401);
        }

        if (!$db_settings->settings['public_server_status']) {
            return response()->json([
                'site_options' => ['site_name' => $db_settings->settings['site_name'], 'sub_name' => 'Error', 'public_credit_distributors' => $db_settings->settings['public_credit_distributors'], 'public_online_users' => $db_settings->settings['public_online_users']],
                'message' => 'Please Login to access this page.',
                'language' => $language,
            ], 403);
        }

        $servers = VpnServer::with(['server_access', 'user_packages'])->select('id', 'server_name', 'server_access_id', 'limit_bandwidth', 'is_active')->withCount('online_users')->orderBy('server_name', 'asc')->get();

        $site_options['site_name'] = $db_settings->settings['site_name'];
        $site_options['sub_name'] = 'Server Status';
        $site_options['enable_panel_login'] = $db_settings->settings['enable_panel_login'];
        $site_options['public_online_users'] = $db_settings->settings['public_online_users'];
        $site_options['public_credit_distributors'] = $db_settings->settings['public_credit_distributors'];
        $site_options['public_server_status'] = $db_settings->settings['public_server_status'];

        return response()->json([
            'site_options' => $site_options,
            'language' => $language,
            'model' => $servers,
        ], 200);
    }
}

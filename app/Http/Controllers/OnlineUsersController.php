<?php

namespace App\Http\Controllers;

use App\Jobs\JobVpnDisconnectUser;
use App\Lang;
use App\OnlineUser;
use App\SiteSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OnlineUsersController extends Controller
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
        $db_settings = \App\SiteSettings::findorfail(1);

        if (auth()->user()->cannot('update-account') || !auth()->user()->isAdmin() && !$db_settings->settings['enable_panel_login']) {
            return response()->json([
                'message' => 'Logged out.',
            ], 401);
        }

        $data = OnlineUser::select('user_id', 'vpn_server_id', 'byte_sent', 'byte_received', 'created_at')->with(['user' => function($q) {
            $q->select('id', 'username');
        }, 'vpnserver' => function($q) {
            $q->select('id', 'server_name');
        }])->orderBy('created_at', 'desc')->paginate(50);

        $permission['is_admin'] = auth()->user()->isAdmin();
        $permission['manage_user'] = auth()->user()->can('manage-user');
        $permission['manage_vpn_server'] = auth()->user()->can('manage-vpn-server');
        $permission['manage_voucher'] = auth()->user()->can('manage-voucher');
        $permission['manage_update_json'] = auth()->user()->can('manage-update-json');

        $site_options['site_name'] = $db_settings->settings['site_name'];
        $site_options['sub_name'] = 'Online Users';
        $site_options['enable_panel_login'] = $db_settings->settings['enable_panel_login'];

        $language = Lang::all()->pluck('name');

        return response()->json([
            'site_options' => $site_options,
            'profile' => ['username' => auth()->user()->username],
            'language' => $language,
            'permission' => $permission,
            'model' => $data,
        ], 200);
    }

    public function searchOnlineUser(Request $request)
    {
        $db_settings = \App\SiteSettings::findorfail(1);

        if (auth()->user()->cannot('update-account') || !auth()->user()->isAdmin() && !$db_settings->settings['enable_panel_login']) {
            return response()->json([
                'message' => 'Logged out.',
            ], 401);
        }

        $data = OnlineUser::with(['user', 'vpnserver'])->whereHas('user', function($query) use ($request) {
            if($request->has('search_input')) {
                $query->where('username', 'LIKE', '%'.trim($request->search_input).'%');
            }
        })->orderBy('created_at', 'desc')->paginate(50);
        
        return response()->json([
            'model' => $data,
        ], 200);
    }

    public function disconnectVpn(Request $request)
    {
        try {
            if (!auth()->user()->isAdmin() || auth()->user()->id == $request->user_id) {
                return response()->json(['message' => 'Action not allowed.'], 403);
            }

            $db_settings = SiteSettings::find(1);
            $server_id = $request->server_id;
            $vpn_user = OnlineUser::with(['vpnserver', 'user'])->where([['user_id', $request->user_id], ['vpn_server_id', $server_id]])->firstorfail();
            $job = (new JobVpnDisconnectUser($vpn_user->user->username, $vpn_user->vpnserver->server_ip, $vpn_user->vpnserver->server_port))->onConnection($db_settings->settings['queue_driver'])->onQueue('disconnect_user');
            dispatch($job);
            return response()->json(['message' => 'Request sent to the server.'], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $ex) {
            return response()->json(['message' => 'Session not found.'], 404);
        }
    }
}

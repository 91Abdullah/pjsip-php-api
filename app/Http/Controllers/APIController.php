<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class APIController extends Controller
{
    public function changePassword(Request $request)
    {
        $input = $request->validate([
            'username' => 'required',
            'current_password' => 'required',
            'new_password' => 'required',
            'confirm_password' => 'required|same:new_password',
        ]);

        $user = DB::table('ps_auths')->where('username', $input['username'])->first();

        if (!$user || $user->password != $input['current_password']) {
            throw ValidationException::withMessages([
                'current_password' => ['Incorrect current password'],
            ]);
        }

        DB::table('ps_auths')
            ->where('username', $input['username'])
            ->update(['password' => $input['new_password']]);

        return response()->json(['message' => 'Password updated successfully']);
    }

    public function downloadRecording($date, $recordingfile)
    {
        // Parse the date
        $dt = Carbon::parse($date);
        $year = $dt->format('Y');
        $month = $dt->format('m');
        $day = $dt->format('d');

        // Construct the file path
        $filePath = Storage::disk('monitor')->path("/{$year}/{$month}/{$day}/{$recordingfile}");

        // Check if the file exists
        if (!file_exists($filePath)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        // Return the file as a response
        return new BinaryFileResponse($filePath);
    }

    public function getCdrsByFilters(Request $request)
    {
        $page = $request->input('page', 1);
        $pageSize = $request->input('page_size', 10);
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $source = $request->input('source');
        $destination = $request->input('destination');
        $user = $request->input('user');

        $query = DB::table('cdr');

        if ($user == 'admin') {
            $totalRecords = $query->count();
            if ($startDate) {
                $query->where('start', '>=', $startDate);
            }
            if ($endDate) {
                $query->where('end', '<=', $endDate);
            }
            if ($source) {
                $query->where('src', $source);
            }
            if ($destination) {
                $query->where('dst', $destination);
            }
        } else {
            $pattern = '%' . $user . '%';
            $totalRecords = $query->where('channel', 'like', $pattern)
                ->orWhere('dstchannel', 'like', $pattern)
                ->count();
            $query->where(function ($query) use ($pattern) {
                $query->where('channel', 'like', $pattern)
                    ->orWhere('dstchannel', 'like', $pattern);
            });
            if ($startDate) {
                $query->where('start', '>=', $startDate);
            }
            if ($endDate) {
                $query->where('end', '<=', $endDate);
            }
            if ($source) {
                $query->where('src', $source);
            }
            if ($destination) {
                $query->where('dst', $destination);
            }
        }

        $totalPages = ceil($totalRecords / $pageSize);
        $offset = ($page - 1) * $pageSize;

        $cdrs = $query->orderBy('start', 'desc')
            ->offset($offset)
            ->limit($pageSize)
            ->get();

        return response()->json([
            'cdrs' => $cdrs,
            'total_pages' => $totalPages,
            'current_page' => $page
        ]);
    }

    public function getAccounts(Request $request)
    {
        $page = $request->input('page', 1);
        $pageSize = $request->input('page_size', 10);

        $totalRecords = DB::table('ps_endpoints')->count();
        $totalPages = ceil($totalRecords / $pageSize);
        $offset = ($page - 1) * $pageSize;

        $accounts = DB::table('ps_endpoints')
            ->offset($offset)
            ->limit($pageSize)
            ->get();

        return response()->json([
            'accounts' => $accounts,
            'total_pages' => $totalPages,
            'current_page' => $page
        ]);
    }

    public function getAccount(int $extension)
    {
        $account = DB::table('ps_endpoints')->where('id', $extension)->first();

        if (!$account) {
            return response()->json(['error' => 'Account not found'], 404);
        }

        return response()->json($account);
    }

    public function createAccount(Request $request, int $extension)
    {
        $account = $request->validate([
            'username' => 'required',
        ]);

        try {

            DB::table('ps_aors')->insert([
                'id' => $extension,
                'max_contacts' => 2
            ]);

            DB::table('ps_auths')->insert([
                'id' => $extension,
                'auth_type' => 'userpass',
                'password' => $extension,
                'username' => $extension
            ]);

            DB::table('ps_endpoints')->insert([
                'id' => $extension,
                'transport' => 'transport-udp',
                'aors' => $extension,
                'auth' => $extension,
                'context' => $account['username'],
                'disallow' => 'all',
                'allow' => 'ulaw,alaw,gsm',
                'direct_media' => 'no',
                'deny' => '0.0.0.0/0',
                'permit' => '0.0.0.0/0',
                'mailboxes' => $extension . '@default'
            ]);

            return response()->json(['message' => 'Account created successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error creating account: ' . $e->getMessage()], 500);
        }
    }

    public function deleteAccount($extension)
    {
        try {
            DB::table('ps_aors')->where('id', $extension)->delete();
            DB::table('ps_auths')->where('id', $extension)->delete();
            DB::table('ps_endpoints')->where('id', $extension)->delete();

            return response()->json(['message' => 'Account deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error deleting account: ' . $e->getMessage()], 500);
        }
    }
}

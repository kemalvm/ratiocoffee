<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function index()
    {
        return response()->json(Account::where('active', true)->orderBy('code')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code'           => 'required|string|unique:accounts,code',
            'name'           => 'required|string|max:200',
            'type'           => 'required|in:asset,liability,equity,revenue,cost_of_goods,expense',
            'normal_balance' => 'required|in:debit,credit',
            'is_header'      => 'boolean',
            'description'    => 'nullable|string',
        ]);

        return response()->json(Account::create($data), 201);
    }

    public function show(Account $account)
    {
        return response()->json($account->load('children'));
    }

    public function update(Request $request, Account $account)
    {
        $data = $request->validate([
            'name'        => 'sometimes|string|max:200',
            'active'      => 'boolean',
            'description' => 'nullable|string',
        ]);
        $account->update($data);
        return response()->json($account->fresh());
    }

    public function destroy(Account $account)
    {
        $account->delete();
        return response()->json(['message' => 'Akun dihapus.']);
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class JournalEntryController extends Controller
{
    public function index()    { return response()->json(['data' => []]); }
    public function store(Request $request) { return response()->json(['message' => 'Journal entry created.'], 201); }
    public function show($id)  { return response()->json([]); }
    public function update(Request $request, $id) { return response()->json([]); }
    public function destroy($id) { return response()->json(['message' => 'Deleted.']); }
}

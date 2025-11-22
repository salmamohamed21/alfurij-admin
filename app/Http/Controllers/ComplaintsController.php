<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use Illuminate\Http\Request;

class ComplaintsController extends Controller
{
    public function index()
    {
        $complaints = Complaint::with('user:id,name,email')->orderBy('created_at', 'desc')->get();

        return response()->json($complaints);
    }
}

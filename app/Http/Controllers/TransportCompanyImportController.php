<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\TransportCompaniesImport;

class TransportCompanyImportController extends Controller
{
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls'
        ]);

        Excel::import(new TransportCompaniesImport, $request->file('file'));

        return response()->json(['message' => 'Import thành công!']);
    }
}

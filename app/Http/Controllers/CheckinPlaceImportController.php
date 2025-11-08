<?php

namespace App\Http\Controllers;

use App\Imports\CheckInPlacesImport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class CheckInPlaceImportController extends Controller
{
    /**
     * Import file Excel vào database.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv'
        ]);

        Excel::import(new CheckInPlacesImport, $request->file('file'));

        return response()->json(['message' => 'Import địa điểm check-in thành công!']);
    }
}

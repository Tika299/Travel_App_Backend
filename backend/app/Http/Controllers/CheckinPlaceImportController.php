<?php

namespace App\Http\Controllers;

use App\Imports\CheckinPlacesImport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class CheckinPlaceImportController extends Controller
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

        Excel::import(new CheckinPlacesImport, $request->file('file'));

        return response()->json(['message' => 'Import địa điểm check-in thành công!']);
    }
}

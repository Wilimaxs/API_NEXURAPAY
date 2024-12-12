<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Markup_pascabayar;
use App\Models\Markup_prabayar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MarkupController extends Controller
{
    /**
     * Update the specified product in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updatePrabayar(Request $request)
    {
        $request->validate([
            'kode' => 'required|string',
            'markup_harga' => 'required|numeric'
        ]);

        try {
            // Mencari markup berdasarkan kode dari request
            $markup = Markup_prabayar::where('kode', $request->kode)->firstOrFail();
            $markup->update($request->all());

            return response()->json([
                'status' => 'success',
                'message' => 'Markup berhasil diupdate',
                'data' => $markup
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Markup dengan kode tersebut tidak ditemukan =' . $e->getMessage()
            ], 404); // Mengembalikan status 404 Not Found
        }
    }

    /**
     * Update the specified product in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updatePascabayar(Request $request)
    {
        $request->validate([
            'kode' => 'required|string',
            'markup_harga' => 'required|numeric'
        ]);

        try {
            // Mencari markup berdasarkan kode dari request
            $markup = Markup_pascabayar::where('kode', $request->kode)->firstOrFail();
            $markup->update($request->all());

            return response()->json([
                'status' => 'success',
                'message' => 'Markup berhasil diupdate',
                'data' => $markup
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Markup dengan kode tersebut tidak ditemukan =' . $e->getMessage()
            ], 404); // Mengembalikan status 404 Not Found
        }
    }
}

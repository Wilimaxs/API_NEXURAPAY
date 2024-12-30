<?php

namespace App\Http\Controllers\Api;

// import model Post
use App\Models\Post;

use Illuminate\Http\Request;

use App\Http\Controllers\Controller;
// import http request
use App\Http\Resources\PostResource;
// import frcades storage
use Illuminate\Support\Facades\Storage;
// import fecades validator
use Illuminate\Support\Facades\Validator;

use Illuminate\Support\Facades\DB;

use Carbon\Carbon;

class PostController extends Controller
{
    //
    public function index()
    {
        // get all posts
        /**
         * index
         *
         * @return void
         */
        $posts = Post::latest()->paginate(5);
        //  return collection of posts as a resource
        return new PostResource(true, 'List Data Posts', $posts);
    }
    // store data
    public function store(Request $request)
    {
        // define validator rules
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:png,jpg,gif,jpeg,svg|max:2048',
            'title' => 'required',
            'content' => 'required',
        ]);

        // check validator if fails
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // uploade image
        $image = $request->file('image');
        $image->storeAs('public/posts', $image->hashName());

        // create post or insert data to database
        $post = Post::create([
            'image' => $image->hashName(),
            'title' => $request->title,
            'content' => $request->content,
        ]);

        // return proses or return responses
        return new PostResource(true, 'Data Berhasil Ditambahkan', $post);
    }

    // show data
    public function show($id)
    {
        // fing by id
        $post = Post::find($id);

        // return single post as a resource
        return new PostResource(true, 'Detail Data Post!', $post);
    }

    // update data
    public function update(Request $request, $id)
    {

        //define validation rules
        $validator = Validator::make($request->all(), [
            'title' => 'required',
            'content' => 'required',
        ]);

        // check validation id fails
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // find by id
        $post = Post::find($id);

        // check image is not empty
        if ($request->hasFile('image')) {

            // upload image
            $image = $request->file('image');
            $image->storeAs('public/posts', $image->hashName());

            // delete old image
            Storage::delete('public/posts/' . basename($post->image));

            // update post with image
            $post->update([
                'image' => $image->hashName(),
                'title' => $request->title,
                'content' => $request->content,
            ]);
        } else {
            $post->update([
                'title' => $request->title,
                'content' => $request->content,
            ]);
        }
        // return respose
        return new PostResource(true, 'Data Berhasil Di Update', $post);
    }

    // deleted Data
    public function destroy($id)
    {
        $post = Post::find($id);

        // Delete image
        Storage::delete('public/posts/' . basename($post->image));

        // delete post
        $post->delete();

        // return response
        return new PostResource(true, 'Data Behasil Di Hapus', null);
    }




    public function getTransactionSummary(Request $request)
    {
        try {
            // Get phone number from token user
            // $phone = auth()->user()->hp;
            $user = $request->user();

            // Get current month and year
            $currentMonth = Carbon::now()->month;
            $currentYear = Carbon::now()->year;

            // Query 1: Get Pengeluaran and Penghasilan
            $transactionData = DB::select(
                "
                SELECT 
                    COALESCE(SUM(transactions.hjual), 0) AS Pengeluaran,
                    COALESCE(SUM(
                        CASE 
                            WHEN EXISTS (
                                SELECT 1 FROM markup_prabayars 
                                WHERE markup_prabayars.kode = transactions.product_code
                            )
                            THEN (
                                SELECT markup_prabayars.markup 
                                FROM markup_prabayars
                                WHERE markup_prabayars.kode = transactions.product_code
                            )
                            ELSE 0
                        END
                    ), 0) AS penghasilan_Prabayar,
                    COALESCE(SUM(
                        CASE 
                            WHEN EXISTS (
                                SELECT 1 FROM markup_pascabayars 
                                WHERE markup_pascabayars.kode = transactions.product_code
                            )
                            THEN (
                                SELECT markup_pascabayars.markup 
                                FROM markup_pascabayars
                                WHERE markup_pascabayars.kode = transactions.product_code
                            )
                            ELSE 0
                        END
                    ), 0) AS penghasilan_pascabayar
                FROM transactions
                INNER JOIN users ON transactions.no_hp = users.hp
                WHERE transactions.no_hp = ?
                AND transactions.status = 0
                AND MONTH(transactions.created_at) = ?
                AND YEAR(transactions.created_at) = ?",
                [$user->hp, $currentMonth, $currentYear]
            );

            // Query 2: Get Pembelian
            $topupData = DB::select(
                "
                SELECT COALESCE(SUM(topups.amount), 0) AS pembelian 
                FROM topups 
                INNER JOIN users ON topups.hp = users.hp 
                WHERE topups.hp = ?",
                [$user->hp]
            );

            // Query 3: Get Penghematan
            $penghematanData = DB::select(
                "
                SELECT 
                (SELECT COALESCE(sum(transactions.hjual),0) 
                FROM transactions, users 
                WHERE MONTH(transactions.created_at) = ?
                AND YEAR(transactions.created_at) = ?
                AND transactions.no_hp = users.hp 
                AND transactions.no_hp = ?) -
                (SELECT COALESCE(sum(transactions.hjual),0) 
                FROM transactions, users 
                WHERE MONTH(transactions.created_at) = ?
                AND YEAR(transactions.created_at) = ?
                AND transactions.no_hp = users.hp 
                AND transactions.no_hp = ?) AS Penghematan
                FROM transactions 
                LIMIT 1",
                [$currentMonth, $currentYear, $user->hp, $currentMonth - 1, $currentYear, $user->hp]
            );

            // Combine all data
            $response = [
                'status' => 'success',
                'data' => [
                    'pengeluaran' => $transactionData[0]->Pengeluaran ?? "0",
                    'penghasilan' => ($transactionData[0]->penghasilan_Prabayar ?? 0) +
                        ($transactionData[0]->penghasilan_pascabayar ?? 0),
                    'pembelian' => $topupData[0]->pembelian ?? "0",
                    'penghematan' => $penghematanData[0]->Penghematan ?? "0"
                ]
            ];

            return response()->json($response, 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan dalam memproses data',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}

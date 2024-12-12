<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;

use Symfony\Component\HttpFoundation\Response;
use App\Models\product_prabayar;
use App\Models\product_pascabayar;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{

    protected $apiKey;


    public function __construct()
    {
        $this->apiKey = env('TRIPAY_API_KEY');
    }

    /**
     * 
     * product prabayar
     * 
     */
    public function productPrabayar()
    {
        try {
            $payload = [
                // 'code' => 'AX5'
            ];

            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Authorization' =>  'Bearer' . env('TRIPAY_API_KEY'),
            ])->get('https://tripay.id/api/v2/pembelian/produk?' . http_build_query($payload));

            if ($response->successful()) {
                $products = $response->json()['data'];

                foreach ($products as $product) {
                    product_prabayar::updateOrCreate(
                        ['product_code' => $product['code']],
                        [
                            'name' => $product['product_name'],
                            'operator_id' => $product['pembelianoperator_id'],
                            'category_id' => $product['pembeliankategori_id'],
                            'price' => $product['price'],
                            'status' => $product['status'],
                            'description' => $product['desc'] ?? null,
                        ]
                    );
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Prepaid products synchronized successfully'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch prepaid products',
                'error' => $response->json() // Menampilkan response lengkap
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error synchronizing prepaid products: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 
     * Product pascabayar
     * 
     */
    public function productPascabayar()
    {
        try {
            $payload = [
                // 'category_id' => 13,
                // 'operator_id' => 2,
            ];
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Authorization' => 'Bearer' . env('TRIPAY_API_KEY'),
            ])->get('https://tripay.id/api/v2/pembayaran/produk?' . http_build_query($payload));

            if ($response->successful()) {
                $products = $response->json()['data'];

                foreach ($products as $product) {
                    product_pascabayar::updateOrCreate(
                        ['product_code' => $product['code']],
                        [
                            'name' => $product['product_name'],
                            'operator_id' => $product['pembayaranoperator_id'],
                            'category_id' => $product['pembayarankategori_id'],
                            'biaya_admin' => $product['biaya_admin'],
                            'status' => $product['status'],
                        ]
                    );
                }
                return response()->json([
                    'success' => true,
                    'message' => 'Prepaid products synchronized successfully'
                ]);
            }
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch prepaid products',
                'error' => $response->json()
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'false',
                'message' => 'Error synchronizing prepaid products: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 
     * retrieving product by id 
     * 
     */
    public function showData(Request $request)
    {
        try {
            $operatorId = $request->input('operator');
            $productCode = $request->input('product_code');
            $categoryId = $request->input('category_id');



            // Query produk prabayar dengan kondisi opsional
            // Query menggunakan Query Builder
            $produkPrabayar = product_prabayar::leftJoin('markups_prabayar', 'product_prabayars.product_code', '=', 'markups_prabayar.kode')
                ->select(
                    'product_prabayars.*',
                    DB::raw('COALESCE(product_prabayars.price + markups_prabayar.markup, product_prabayars.price) AS final_price')
                )
                ->when($productCode, function ($query, $productCode) {
                    return $query->where('product_prabayars.product_code', 'LIKE', "$productCode%");
                })
                ->when($operatorId, function ($query, $operatorId) {
                    return $query->where('product_prabayars.operator_id', $operatorId);
                })
                ->when($categoryId, function ($query, $categoryId) {
                    return $query->where('product_prabayars.category_id', $categoryId);
                })
                // Menambahkan pengurutan berdasarkan final_price
                ->orderBy(DB::raw('COALESCE(product_prabayars.price + markups_prabayar.markup, product_prabayars.price)'), 'asc')
                ->get();

            // Query produk pascabayar dengan kondisi opsional
            $produkPascabayar = product_pascabayar::where('operator_id', $operatorId)->get();

            // Cek jika tidak ada produk ditemukan
            if ($produkPrabayar->isEmpty() && $produkPascabayar->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => "Produk tidak ditemukan dengan operatorId = $operatorId",
                    'data' => [
                        'prabayar' => [],
                        'pascabayar' => [],
                    ]
                ], Response::HTTP_NOT_FOUND);
            }

            // Siapkan response message
            $message = $this->prepareResponseMessage($produkPrabayar, $produkPascabayar, $operatorId);

            // Return response
            return response()->json([
                'status' => true,
                'message' => $message,
                'data' => [
                    'prabayar' => $produkPrabayar->isEmpty() ? [] : $produkPrabayar,
                    'pascabayar' => $produkPascabayar->isEmpty() ? [] : $produkPascabayar,
                    'summary' => [
                        'prabayar_count' => $produkPrabayar->count(),
                        'pascabayar_count' => $produkPascabayar->count(),
                        'total_products' => $produkPrabayar->count() + $produkPascabayar->count()
                    ]
                ]
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            // Tangkap error dan kembalikan response
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve products: ' . $e->getMessage(),
                'data' => null
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * 
     * preparied message for function retrieving data
     * 
     */
    private function prepareResponseMessage($prabayarProducts, $pascabayarProducts, $operatorName)
    {
        if (!$prabayarProducts->isEmpty() && !$pascabayarProducts->isEmpty()) {
            return "Found both prabayar and pascabayar products for operator: $operatorName";
        }

        if (!$prabayarProducts->isEmpty()) {
            return "Found only prabayar products for operator: $operatorName";
        }

        if (!$pascabayarProducts->isEmpty()) {
            return "Found only pascabayar products for operator: $operatorName";
        }

        return "No products found for operator: $operatorName";
    }
}

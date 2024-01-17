<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BrandController extends Controller {
    /**
     * Display a listing of the resource.
     */
    public function index() {
        //

        return response([
            'brands' => Brand::all()
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request) {
        //

        $brand = Brand::create([
            'name' => $request->name
        ]);

        return response([
            'brand' => $brand
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(Brand $brand) {
        //

        $brand->logo_url = $brand->logo_url != null ? Storage::disk('gcs')->temporaryUrl($brand->logo_url, now()->addMinutes(10)) : '';

        return response([
            'brand' => $brand,

        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Brand $brand) {
        //

        $brand->update([
            'name' => $request->name
        ]);

        return response([
            'brand' => $brand
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Brand $brand) {
        //
    }

    public function uploadLogo($id, Request $request) {

        if ($request->file('file') != null) {

            $file = $request->file('file');
            $file_name = time() . '_' . $file->getClientOriginalName();

            $path = "brands/" . $id . "/logo/" . $file_name;

            $file->storeAs($path);

            $brand = Brand::find($id);
            $brand->update([
                'logo_url' => $path
            ]);

            return response()->json([
                'brand' => $brand
            ]);
        }
    }

    public function generatedSignedUrlForFile($id) {

        $brand = Brand::find($id);

        $url = Storage::disk('gcs')->temporaryUrl(
            $brand->logo_url,
            now()->addMinutes(5)
        );

        return response([
            'url' => $url,
        ], 200);
    }
}

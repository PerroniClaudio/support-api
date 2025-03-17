<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WikiObject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class WikiObjectController extends Controller {
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request) {

        $user = $request->user();

        if ($user["is_admin"] != 1) {
            return response([
                'message' => 'The user must be an admin.',
            ], 401);
        }

        $folder = base64_decode($request->folder);
        $wikiObjects = WikiObject::where('path', $folder)
            ->with('user')
            ->orderByRaw("type = 'folder' DESC")
            ->orderBy('created_at', 'asc')
            ->get();



        return response([
            'files' => $wikiObjects,
        ], 200);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create() {
        //
        return response([
            'message' => 'Please use /api/store to create a new object',
        ], 404);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request) {
        //

        $user = $request->user();

        if ($user["is_admin"] != 1) {
            return response([
                'message' => 'The user must be an admin.',
            ], 401);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:file,folder',
            'path' => 'required|string',
            'is_public' => 'required|boolean',
        ]);

        if ($request->type == 'folder') {


            $company_id = isset($request->company_id) ? $request->company_id : null;
            $wikiObject = WikiObject::create([
                'name' => $validated['name'],
                'uploaded_name' => $validated['name'],
                'mime_type' => 'folder',
                'type' => $validated['type'],
                'path' => $validated['path'],
                'is_public' => $validated['is_public'],
                'company_id' => $company_id,
                'uploaded_by' => $user->id,
            ]);

            return response([
                'wikiObject' => $wikiObject,
            ], 201);
        } else  if ($request->file('file') != null) {

            $file = $request->file('file');

            $uploaded_name = time() . '_' . $file->getClientOriginalName();
            $mime_type = $file->getClientMimeType();
            $file_size = $file->getSize();
            $bucket_path = 'wiki_objects' . $validated['path'] . '';
            $file->storeAs($bucket_path, $uploaded_name, 'gcs');

            $company_id = isset($request->company_id) ? $request->company_id : null;
            $wikiObject = WikiObject::create([
                'name' => $validated['name'],
                'uploaded_name' =>  $bucket_path . $uploaded_name,
                'type' => $validated['type'],
                'mime_type' => $mime_type,
                'path' => $validated['path'],
                'is_public' => $validated['is_public'],
                'company_id' => $company_id,
                'uploaded_by' => $user->id,
                'file_size' => $file_size,
            ]);

            return response([
                'wikiObject' => $wikiObject,
            ], 201);
        }
    }

    public function downloadFile(WikiObject $wikiObject) {
        /**
         * @disregard P1009 Undefined type
         */
        $url = Storage::disk('gcs')->temporaryUrl(
            $wikiObject->uploaded_name,
            now()->addMinutes(65)
        );


        return response([
            'url' => $url,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(WikiObject $wikiObject, Request $request) {
        //

        $user = $request->user();

        if ($user["is_admin"] != 1) {
            return response([
                'message' => 'The user must be an admin.',
            ], 401);
        }

        $wikiObject->delete();

        return response([
            'message' => 'WikiObject soft deleted successfully.',
        ], 200);
    }
}

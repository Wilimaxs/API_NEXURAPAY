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
}

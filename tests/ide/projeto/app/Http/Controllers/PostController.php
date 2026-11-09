<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function index()
    {
        return view('posts.index', ['posts' => Post::with('comentarios')->latest('id')->paginate(5)]);
    }

    public function store(Request $request)
    {
        $dados = $request->validate([
            'titulo' => ['required', 'string', 'max:120'],
            'corpo' => 'nullable|string',
        ]);
        $post = Post::create($dados);

        return redirect()->route('posts.show', $post)->with('ok', 'Post criado!');
    }

    public function show(Post $post)
    {
        return view('posts.show', compact('post'));
    }

    public function update(Request $request, Post $post)
    {
        $post->update($request->validate(['titulo' => 'required|min:3']));

        return back()->with('ok', 'Atualizado');
    }

    public function destroy(Post $post)
    {
        $post->delete();

        return redirect('/posts');
    }
}

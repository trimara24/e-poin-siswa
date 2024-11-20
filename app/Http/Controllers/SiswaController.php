<?php

namespace App\Http\Controllers;

use App\Models\Siswa;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SiswaController extends Controller
{
    public function index(): View 
    {
      //get data Db
      $siswas = DB::table('siswas')
      ->join('users', 'siswas.id_user', '=', 'users.id')
      ->select(
        'siswas.*',
        'users.name',
        'users.email'
      )
      ->paginate(10);

    if (request('cari')) {
      $siswas = $this->search(request('cari'));
    }

      return view('admin.siswa.index', compact('siswas'));
    }

    public function create(): View
    {
     return view('admin.siswa.create');
    }

    public function store(Request $request): RedirectResponse
    {
      //validate form
      $validated = $request->validate([
        'name'      => 'required|string|max:250',
        'email'     => 'required|email|max:250|unique:users',
        'password'  => 'required|min:8|confirmed',
        'image'     => 'required|image|mimes:jpeg,png,jpg|max:2048',
        'nis'       => 'required|numeric',
        'tingkatan' => 'required',
        'jurusan'   => 'required',
        'kelas'     => 'required',
        'hp'        => 'required|numeric',
      ]);

      //upload image
      $image = $request->file('image');
      $image->storeAs('public/siswas', $image->hashName());
      // jika config private true dan .env public
      //$image->storeAs('siswas',$image->hasName());

      $id_akun = $this->insertAccount($request->name, $request->email, $request->password);
      
      //create post
      Siswa::create([
        'id_user'       => $id_akun,
        'image'         => $image->hashName(),
        'nis'           => $request->nis,
        'tingkatan'     => $request->tingkatan,
        'jurusan'       => $request->jurusan,
        'kelas'         => $request->kelas,
        'hp'            => $request->hp,
        'status'        => 1
      ]);

      //redirect to index
      return redirect()->route('siswa.index')->with(['success' => 'Data Berhasil Disimpan!']);
    }

    public function insertAccount(string $name, string $email, string $password)
    {

      user::create([
        'name'          => $name,
        'email'         => $email,
        'password'      => Hash::make($password),
        'usertype'      => 'siswa'
      ]);

      $id = DB::table('users')->where('email', $email)->value('id');

      return $id;
    }

    public function show(string $id): View
    {

      //get Data db
      $siswa = DB::table('siswas')
          ->join('users', 'siswas.id_user', '=', 'users.id')
          ->select(
            'siswas.*',
            'users.name',
            'users.email'
          )
          ->where('siswas.id', $id)
          ->first();

          return view('admin.siswa.show', compact('siswa')); 
    }

    public function search(string $cari) 
    {

      $siswas = DB::table('siswas')
      ->join('users', 'siswas.id_user', '=', 'users.id')
      ->select(
        'siswas.*',
        'users.name',
        'users.email'
      )->where('users.name', 'like', '%' . $cari . '%')
      ->orWhere('siswas.nis', 'like', '%' . $cari . '%')
      ->orWhere('users.email', 'like', '%' . $cari . '%')
      ->paginate(10);

      return $siswas;
    }
    public function edit(string $id): View
    {
      //get data db
      $siswa = Db::table('siswas')
      ->join('users', 'siswas.id_user', '=', 'users.id')
      ->select(
        'siswas.*',
        'users.name',
        'users.email'
      )
      ->where('siswas.id', $id)
      ->first();

      return view('admin.siswa.edit', compact('siswa'));
    }

    public function update(Request $request, $id): RedirectResponse
    {
      //validate form
      $validated = $request->validate([
        'name'        => 'required|string|max:250',
        'image'       => 'image|mimes:jpeg,png,jpg|max:2048',
        'nis'         => 'required|numeric',
        'tingkatan'   => 'required',
        'jurusan'     => 'required',
        'kelas'       => 'required',
        'hp'          => 'required|numeric',
        'status'      => 'required',
      ]);

      $datas = Siswa::findOrFail($id);

      $this->editAccount($request->name, $id);

      if ($request->hasfile('image')) {

        $image = $request->file('image');
        $image->storeAs('public/siswas', $image->hashName());

        Storage::delete('public/siswas/' . $datas->image);

        $datas->update([
          'image'        => $image->hashName(),
          'nis'          => $request->nis,
          'tingkatan'    => $request->tingkatan,
          'jurusan'      => $request->jurusan,
          'kelas'        => $request->kelas,
          'hp'           => $request->hp,
          'status'       => $request->status
        ]);
    } else {

      $datas->update([
        'nis'        => $request->nis,
        'tingkatan'  => $request->tingkatan,
        'jurusan'    => $request->jurusan,
        'kelas'      => $request->kelas,
        'hp'         => $request->hp,
        'status'     => $request->status,
      ]);
    }
      return redirect()->route('siswa.index')->with(['success' => 'Data Berhasil Diubah!']);
    }

    public function editAccount(string $name, string $id)
    {

      //get id user
      $siswa = DB::table('siswas')->where('id',$id)->value('id_user');
      $user = User::findOrFail($siswa);

      //jika ada password
      $user->update([
        'name'  => $name
      ]);
    }

    public function destroy($id): RedirectResponse
    {
      //delete pelanggar
      $this->destroyUser($id);

      //get post by ID
      $post = Siswa::findOrFail($id);

      //delete image
      $post->delete('public/siswas/' . $post->Image);

      //delete post
      $post->delete();

      //redirect to index
      return redirect()->route('siswa.index')->with(['success' => 'Data Berhasil Dihapus!']);

    }

    public function destroyUser(string $id)
    {
      //get id User
      $siswa = DB::table('siswas')->where('id', $id)->value('id_user');
      $user = User::findOrFail($siswa);

      //delete post
      $user->delete();
    }

}

<?php

namespace App\Http\Controllers;

use App\Models\Artwork;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ArtworkController extends Controller
{
    private const MAX_BYTES = 5 * 1024 * 1024;
    private const MAX_PER_USER = 100;

    public function index(Request $request)
    {
        $artwork = $request->query('artwork') ? $request->user()->artworks()->findOrFail($request->query('artwork')) : null;

        return view('draw.index', ['artwork' => $artwork]);
    }

    public function gallery(Request $request)
    {
        return view('draw.gallery', ['artworks' => $request->user()->artworks()->latest()->paginate(12)]);
    }

    public function store(Request $request)
    {
        if ($request->user()->artworks()->count() >= self::MAX_PER_USER) {
            return response()->json(['message' => __('Galeri penuh. Hapus beberapa karya dulu.')], 422);
        }
        [$binary, $info, $title] = $this->readPayload($request);
        $path = 'artworks/'.$request->user()->id.'/'.Str::uuid().'.png';
        Storage::disk(Artwork::DISK)->put($path, $binary);

        $artwork = $request->user()->artworks()->create([
            'title' => $title, 'path' => $path, 'width' => $info[0], 'height' => $info[1], 'size_bytes' => strlen($binary),
        ]);

        return response()->json(['saved' => true, 'id' => $artwork->id, 'title' => $artwork->title]);
    }

    public function update(Request $request, $id)
    {
        $artwork = $request->user()->artworks()->findOrFail($id);
        [$binary, $info, $title] = $this->readPayload($request);
        Storage::disk(Artwork::DISK)->put($artwork->path, $binary);
        $artwork->update(['title' => $title ?: $artwork->title, 'width' => $info[0], 'height' => $info[1], 'size_bytes' => strlen($binary)]);

        return response()->json(['saved' => true, 'id' => $artwork->id, 'title' => $artwork->title]);
    }

    public function image(Request $request, $id)
    {
        $artwork = $request->user()->artworks()->findOrFail($id);
        abort_unless(Storage::disk(Artwork::DISK)->exists($artwork->path), 404);

        if ($request->boolean('download')) {
            return Storage::disk(Artwork::DISK)->download($artwork->path, Str::slug($artwork->title ?: 'wedi-artwork').'.png');
        }

        return Storage::disk(Artwork::DISK)->response($artwork->path, null, ['Cache-Control' => 'private, no-cache']);
    }

    public function destroy(Request $request, $id)
    {
        $artwork = $request->user()->artworks()->findOrFail($id);
        Storage::disk(Artwork::DISK)->delete($artwork->path);
        $artwork->delete();

        return redirect()->route('draw.gallery')->with('success', __('Karya dihapus.'));
    }

    /** @return array{0: string, 1: array, 2: ?string} */
    private function readPayload(Request $request): array
    {
        $data = $request->validate([
            'image' => ['required', 'string', 'starts_with:data:image/png;base64,'],
            'title' => ['nullable', 'string', 'max:120'],
        ]);

        $binary = base64_decode(substr($data['image'], strlen('data:image/png;base64,')), true);
        abort_if($binary === false || $binary === '' || strlen($binary) > self::MAX_BYTES, 422, __('Gambar tidak valid atau terlalu besar.'));

        $info = @getimagesizefromstring($binary);
        abort_if(! $info || $info[2] !== IMAGETYPE_PNG || $info[0] > 4000 || $info[1] > 4000, 422, __('Gambar tidak valid atau terlalu besar.'));

        return [$binary, $info, trim((string) ($data['title'] ?? '')) ?: null];
    }
}

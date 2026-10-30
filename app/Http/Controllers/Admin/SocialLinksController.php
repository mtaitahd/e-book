<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\SocialLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class SocialLinksController extends Controller
{
    public function index() { return view('admin.settings.social', ['links' => SocialLink::pluck('url', 'platform')]); }
    public function update(Request $request) {
        $rules = [];
        foreach (SocialLink::PLATFORMS as $key => $label) { $rules[$key] = ['nullable', 'url:http,https', 'max:2048']; }
        $data = $request->validate($rules);
        DB::transaction(function () use ($data) {
            foreach (SocialLink::PLATFORMS as $key => $label) { SocialLink::updateOrCreate(['platform' => $key], ['url' => $data[$key] ?? null]); }
        });
        return back()->with('success', 'Social media links updated successfully.');
    }
}

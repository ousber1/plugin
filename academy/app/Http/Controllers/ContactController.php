<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function create()
    {
        return view('pages.contact');
    }

    public function store(Request $request)
    {
        // Honeypot field: bots fill it, humans do not see it.
        if (filled($request->input('website'))) {
            return back()->with('success', __('تم إرسال رسالتك. سنعود إليك قريبًا.'));
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'subject' => ['nullable', 'string', 'max:190'],
            'body' => ['required', 'string', 'min:10', 'max:5000'],
        ]);
        ContactMessage::create($data);

        return back()->with('success', __('تم إرسال رسالتك. سنعود إليك قريبًا.'));
    }
}

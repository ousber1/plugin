<?php

namespace App\Http\Controllers\Admin;

use App\Models\ContactMessage;

class ContactMessageController extends AdminController
{
    public function index()
    {
        return view('admin.messages.index', ['messages' => ContactMessage::latest()->paginate(25)]);
    }

    public function show(ContactMessage $message)
    {
        if (! $message->read_at) {
            $message->forceFill(['read_at' => now()])->save();
        }

        return view('admin.messages.show', compact('message'));
    }

    public function destroy(ContactMessage $message)
    {
        $message->delete();

        return redirect()->route('admin.messages.index')->with('success', 'تم حذف الرسالة.');
    }
}

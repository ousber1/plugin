<?php

namespace App\Http\Controllers;

use App\Models\AutomationRule;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WhatsappContact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class WhatsappController extends Controller
{
    /**
     * Chat interface showing conversations list.
     */
    public function index(): View
    {
        $conversations = Conversation::with(['whatsappContact', 'messages' => function ($query) {
            $query->latest()->limit(1);
        }])
            ->orderByDesc('last_message_at')
            ->paginate(25);

        $contacts = WhatsappContact::where('is_subscribed', true)->get();
        return view('whatsapp.index', compact('conversations', 'contacts'));
    }

    /**
     * AJAX - list conversations with latest message.
     */
    public function getConversations(Request $request): JsonResponse
    {
        $query = Conversation::with(['whatsappContact', 'messages' => function ($q) {
            $q->latest()->limit(1);
        }])->orderByDesc('last_message_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('whatsappContact', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $conversations = $query->paginate($request->input('per_page', 25));

        return response()->json($conversations);
    }

    /**
     * AJAX - get messages for a conversation.
     */
    public function getMessages(int $conversationId): JsonResponse
    {
        $conversation = Conversation::with('whatsappContact')->findOrFail($conversationId);

        $messages = Message::where('conversation_id', $conversationId)
            ->orderBy('created_at', 'asc')
            ->paginate(50);

        return response()->json([
            'conversation' => $conversation,
            'messages' => $messages,
        ]);
    }

    /**
     * Send message via WhatsApp Cloud API and store in DB.
     */
    public function sendMessage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'conversation_id' => 'required|exists:conversations,id',
            'content' => 'required|string|max:4096',
            'type' => 'in:text,image,document,template',
        ]);

        $conversation = Conversation::with('whatsappContact')->findOrFail($validated['conversation_id']);
        $contact = $conversation->whatsappContact;
        $type = $validated['type'] ?? 'text';

        $token = config('services.whatsapp.token');
        $phoneNumberId = config('services.whatsapp.phone_number_id');

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $contact->phone,
            'type' => $type,
        ];

        if ($type === 'text') {
            $payload['text'] = ['body' => $validated['content']];
        }

        $response = Http::withToken($token)
            ->post("https://graph.facebook.com/v18.0/{$phoneNumberId}/messages", $payload);

        $status = $response->successful() ? 'sent' : 'failed';
        $whatsappMessageId = $response->successful()
            ? ($response->json('messages.0.id') ?? null)
            : null;

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'type' => $type,
            'content' => $validated['content'],
            'whatsapp_message_id' => $whatsappMessageId,
            'status' => $status,
        ]);

        $conversation->update(['last_message_at' => now()]);

        if (!$response->successful()) {
            Log::error('WhatsApp API error', [
                'response' => $response->json(),
                'status' => $response->status(),
            ]);
        }

        return response()->json([
            'success' => $response->successful(),
            'message' => $message,
        ], $response->successful() ? 200 : 502);
    }

    /**
     * Receive webhook from Meta (POST).
     */
    public function webhook(Request $request): JsonResponse
    {
        $payload = $request->all();

        $entries = $payload['entry'] ?? [];

        foreach ($entries as $entry) {
            $changes = $entry['changes'] ?? [];
            foreach ($changes as $change) {
                $value = $change['value'] ?? [];
                $messages = $value['messages'] ?? [];
                $contacts = $value['contacts'] ?? [];

                foreach ($messages as $index => $msg) {
                    $from = $msg['from'] ?? null;
                    $waId = $contacts[$index]['wa_id'] ?? $from;
                    $profileName = $contacts[$index]['profile']['name'] ?? null;
                    $msgType = $msg['type'] ?? 'text';
                    $content = '';
                    $mediaUrl = null;

                    if ($msgType === 'text') {
                        $content = $msg['text']['body'] ?? '';
                    } elseif (in_array($msgType, ['image', 'document'])) {
                        $content = $msg[$msgType]['caption'] ?? '';
                        $mediaUrl = $msg[$msgType]['id'] ?? null;
                    }

                    // Find or create WhatsApp contact
                    $contact = WhatsappContact::firstOrCreate(
                        ['phone' => $from],
                        ['name' => $profileName, 'whatsapp_id' => $waId]
                    );

                    if ($profileName && !$contact->name) {
                        $contact->update(['name' => $profileName]);
                    }

                    // Find or create conversation
                    $conversation = Conversation::firstOrCreate(
                        ['whatsapp_contact_id' => $contact->id, 'status' => 'open'],
                        ['last_message_at' => now()]
                    );

                    // Store message
                    $storedMessage = Message::create([
                        'conversation_id' => $conversation->id,
                        'direction' => 'inbound',
                        'type' => $msgType,
                        'content' => $content,
                        'media_url' => $mediaUrl,
                        'whatsapp_message_id' => $msg['id'] ?? null,
                        'status' => 'delivered',
                    ]);

                    $conversation->update(['last_message_at' => now()]);

                    // Trigger auto-replies
                    $this->processAutoReply($conversation, $content);
                }

                // Process status updates
                $statuses = $value['statuses'] ?? [];
                foreach ($statuses as $statusUpdate) {
                    $waMessageId = $statusUpdate['id'] ?? null;
                    $newStatus = $statusUpdate['status'] ?? null;
                    if ($waMessageId && $newStatus) {
                        Message::where('whatsapp_message_id', $waMessageId)
                            ->update(['status' => $newStatus]);
                    }
                }
            }
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * GET endpoint for Meta webhook verification.
     */
    public function webhookVerify(Request $request): mixed
    {
        $verifyToken = config('services.whatsapp.verify_token');

        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode === 'subscribe' && $token === $verifyToken) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    /**
     * List WhatsApp contacts.
     */
    public function contacts(Request $request): View|JsonResponse
    {
        $query = WhatsappContact::with('customer');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%");
        }

        $contacts = $query->orderBy('name')->paginate(25);

        if ($request->expectsJson()) {
            return response()->json($contacts);
        }

        return view('whatsapp.contacts', compact('contacts'));
    }

    /**
     * Send broadcast message to multiple contacts.
     */
    public function broadcast(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contact_ids' => 'required|array|min:1',
            'contact_ids.*' => 'exists:whatsapp_contacts,id',
            'content' => 'required|string|max:4096',
            'type' => 'in:text,template',
        ]);

        $token = config('services.whatsapp.token');
        $phoneNumberId = config('services.whatsapp.phone_number_id');
        $type = $validated['type'] ?? 'text';

        $contacts = WhatsappContact::whereIn('id', $validated['contact_ids'])
            ->where('is_subscribed', true)
            ->get();

        $results = ['sent' => 0, 'failed' => 0, 'errors' => []];

        foreach ($contacts as $contact) {
            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $contact->phone,
                'type' => $type,
            ];

            if ($type === 'text') {
                $payload['text'] = ['body' => $validated['content']];
            }

            $response = Http::withToken($token)
                ->post("https://graph.facebook.com/v18.0/{$phoneNumberId}/messages", $payload);

            $conversation = Conversation::firstOrCreate(
                ['whatsapp_contact_id' => $contact->id, 'status' => 'open'],
                ['last_message_at' => now()]
            );

            $whatsappMessageId = $response->successful()
                ? ($response->json('messages.0.id') ?? null)
                : null;

            Message::create([
                'conversation_id' => $conversation->id,
                'direction' => 'outbound',
                'type' => $type,
                'content' => $validated['content'],
                'whatsapp_message_id' => $whatsappMessageId,
                'status' => $response->successful() ? 'sent' : 'failed',
            ]);

            $conversation->update(['last_message_at' => now()]);

            if ($response->successful()) {
                $results['sent']++;
            } else {
                $results['failed']++;
                $results['errors'][] = [
                    'contact_id' => $contact->id,
                    'phone' => $contact->phone,
                    'error' => $response->json('error.message', 'Unknown error'),
                ];
            }
        }

        return response()->json($results);
    }

    /**
     * List automation rules.
     */
    public function automationRules(Request $request): View|JsonResponse
    {
        $rules = AutomationRule::orderBy('name')->get();

        if ($request->expectsJson()) {
            return response()->json($rules);
        }

        return view('whatsapp.automation', compact('rules'));
    }

    /**
     * Create or update automation rule.
     */
    public function storeAutomationRule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'nullable|exists:automation_rules,id',
            'name' => 'required|string|max:255',
            'trigger_keyword' => 'required|string|max:255',
            'response_text' => 'required|string|max:4096',
            'is_active' => 'boolean',
        ]);

        $rule = AutomationRule::updateOrCreate(
            ['id' => $validated['id'] ?? null],
            [
                'name' => $validated['name'],
                'trigger_keyword' => $validated['trigger_keyword'],
                'response_text' => $validated['response_text'],
                'is_active' => $validated['is_active'] ?? true,
            ]
        );

        return response()->json([
            'success' => true,
            'rule' => $rule,
        ]);
    }

    /**
     * Delete automation rule.
     */
    public function deleteAutomationRule(int $id): JsonResponse
    {
        $rule = AutomationRule::findOrFail($id);
        $rule->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Process auto-reply based on automation rules.
     */
    protected function processAutoReply(Conversation $conversation, string $incomingText): void
    {
        if (empty($incomingText)) {
            return;
        }

        $rules = AutomationRule::where('is_active', true)->get();
        $lowerText = strtolower($incomingText);

        foreach ($rules as $rule) {
            if (str_contains($lowerText, strtolower($rule->trigger_keyword))) {
                $token = config('services.whatsapp.token');
                $phoneNumberId = config('services.whatsapp.phone_number_id');
                $contact = $conversation->whatsappContact;

                $payload = [
                    'messaging_product' => 'whatsapp',
                    'to' => $contact->phone,
                    'type' => 'text',
                    'text' => ['body' => $rule->response_text],
                ];

                $response = Http::withToken($token)
                    ->post("https://graph.facebook.com/v18.0/{$phoneNumberId}/messages", $payload);

                $whatsappMessageId = $response->successful()
                    ? ($response->json('messages.0.id') ?? null)
                    : null;

                Message::create([
                    'conversation_id' => $conversation->id,
                    'direction' => 'outbound',
                    'type' => 'text',
                    'content' => $rule->response_text,
                    'whatsapp_message_id' => $whatsappMessageId,
                    'status' => $response->successful() ? 'sent' : 'failed',
                ]);

                $conversation->update(['last_message_at' => now()]);

                break; // Only fire the first matching rule
            }
        }
    }
}

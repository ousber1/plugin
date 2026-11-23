<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\Proof;
use App\Models\User;
use App\Notifications\SimpleNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProofService
{
    public function __construct(protected ProductionService $production) {}

    /** Upload a new BAT version. Any earlier version is superseded and approval is reset. */
    public function upload(OrderItem $item, UploadedFile $file, ?string $message = null): Proof
    {
        $proof = DB::transaction(function () use ($item, $file, $message) {
            $item = OrderItem::whereKey($item->id)->lockForUpdate()->first();
            $version = (int) $item->proofs()->max('version') + 1;
            $item->proofs()->whereIn('status', ['pending', 'approved', 'changes_requested'])->update(['status' => 'superseded']);
            $path = $file->storeAs('proofs/order-'.$item->order_id.'/item-'.$item->id, 'bat-v'.$version.'-'.Str::random(10).'.'.strtolower($file->getClientOriginalExtension()), 'private');
            $proof = $item->proofs()->create([
                'version' => $version, 'path' => $path, 'original_name' => mb_substr($file->getClientOriginalName(), 0, 250),
                'mime' => $file->getMimeType(), 'status' => 'pending', 'staff_message' => $message, 'created_by' => auth()->id(),
            ]);
            $item->update(['proof_status' => 'pending', 'requires_proof' => true]);
            $job = $item->job;
            if ($job && ! in_array($job->stage, ['cancelled', 'shipped', 'delivered', 'proof_pending'])) {
                $this->production->transition($job, 'proof_pending', 'Nouveau BAT v'.$version.' – approbation requise', force: true);
            }
            AuditLogger::log('proof.uploaded', $proof, null, ['version' => $version, 'order_item' => $item->id]);

            return $proof;
        });
        $this->notifyCustomer($proof);

        return $proof;
    }

    /**
     * @param  'approved'|'changes_requested'  $decision
     */
    public function respond(Proof $proof, string $decision, ?User $user, ?string $comment = null): Proof
    {
        if (! in_array($decision, ['approved', 'changes_requested'], true)) {
            throw ValidationException::withMessages(['decision' => 'Décision invalide.']);
        }

        return DB::transaction(function () use ($proof, $decision, $user, $comment) {
            $proof = Proof::whereKey($proof->id)->lockForUpdate()->first();
            $latest = (int) Proof::where('order_item_id', $proof->order_item_id)->max('version');
            if ($proof->version !== $latest || $proof->status !== 'pending') {
                throw ValidationException::withMessages(['proof' => __('Ce BAT n\'est plus la version en attente de validation.')]);
            }
            if ($decision === 'changes_requested' && ! trim((string) $comment)) {
                throw ValidationException::withMessages(['comment' => __('Merci de préciser les corrections souhaitées.')]);
            }
            $proof->update(['status' => $decision, 'customer_comment' => $comment, 'responded_by' => $user?->id,
                'responded_at' => now(), 'responded_ip' => request()?->ip()]);
            $item = $proof->orderItem;
            $item->update(['proof_status' => $decision]);
            AuditLogger::log('proof.'.$decision, $proof, ['status' => 'pending'], ['status' => $decision, 'version' => $proof->version], $comment);
            $job = $item->job;
            if ($job && $decision === 'approved' && $job->stage === 'proof_pending') {
                $this->production->transition($job, 'approved', 'BAT v'.$proof->version.' approuvé par le client');
            }
            if ($job && $decision === 'changes_requested' && $job->stage === 'proof_pending') {
                $this->production->transition($job, 'prepress_review', 'Corrections demandées sur BAT v'.$proof->version);
            }

            return $proof;
        });
    }

    protected function notifyCustomer(Proof $proof): void
    {
        $order = $proof->orderItem->order;
        $email = $order->contact_email ?: $order->customer?->email;
        if ($email) {
            try {
                Notification::route('mail', $email)->notify(new SimpleNotification('proof_ready', [
                    'order' => $order->number, 'version' => $proof->version,
                    'url' => $order->user_id ? route('account.orders.show', $order) : route('guest.order', $order->guest_token),
                ], $order->locale));
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}

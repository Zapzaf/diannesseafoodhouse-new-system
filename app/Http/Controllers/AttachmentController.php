<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\CheckVoucher;
use App\Models\PurchaseVoucher;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    /**
     * @var array<string, class-string>
     */
    private const MODELS = [
        'purchase-voucher' => PurchaseVoucher::class,
        'service' => Service::class,
        'check-voucher' => CheckVoucher::class,
    ];

    public function store(Request $request, string $type, int $id)
    {
        $modelClass = self::MODELS[$type] ?? abort(404);
        $record = $modelClass::findOrFail($id);
        $this->authorizeBranchRecord($request, $record->branch_id ?? null);

        $validated = $request->validate([
            'attachments' => ['required', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:5120', 'mimes:jpg,jpeg,png,webp,pdf,xls,xlsx,doc,docx,csv'],
        ], [
            'attachments.*.max' => 'Each supporting document must be 5MB or smaller.',
        ]);

        foreach ($request->file('attachments', []) as $file) {
            $path = $file->store("attachments/{$type}/{$id}", 'public');

            $record->attachments()->create([
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize() ?: 0,
                'created_by' => $request->user()->id,
            ]);
        }

        return back()->with('success', 'Attachment(s) uploaded.');
    }

    public function destroy(Request $request, string $type, int $id, Attachment $attachment)
    {
        $modelClass = self::MODELS[$type] ?? abort(404);
        $record = $modelClass::findOrFail($id);
        $this->authorizeBranchRecord($request, $record->branch_id ?? null);

        abort_if($attachment->attachable_type !== $modelClass || $attachment->attachable_id !== $record->id, 404);

        Storage::disk('public')->delete($attachment->path);
        $attachment->delete();

        return back()->with('success', 'Attachment removed.');
    }
}

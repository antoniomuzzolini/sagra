<?php

namespace App\Http\Controllers;

use App\Models\Supply;
use App\Models\SupplyAttachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Attachments on a supply (Forniture phase 2): invoices/notes on the private
 * disk. Upload/download/delete are gated by the same scope as the supply —
 * the organizer, or the area manager of the supply's area.
 */
class SupplyAttachmentController extends Controller
{
    public function store(Request $request, Supply $supply): RedirectResponse
    {
        $this->authorizeSupply($request, $supply);

        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,heic,webp,doc,docx'],
        ], [
            'file.required' => 'Scegli un file da allegare.',
            'file.max' => 'Il file non può superare i 10 MB.',
            'file.mimes' => 'Formati ammessi: PDF, immagini, Word.',
        ]);

        $file = $data['file'];
        $path = $file->store("supply-attachments/{$supply->tenant_id}/{$supply->id}", SupplyAttachment::DISK);

        $supply->attachments()->create([
            'tenant_id' => $supply->tenant_id,
            'path' => $path,
            'name' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);

        return back();
    }

    public function download(Request $request, SupplyAttachment $attachment): StreamedResponse
    {
        $this->authorizeSupply($request, $attachment->supply);

        abort_unless(Storage::disk(SupplyAttachment::DISK)->exists($attachment->path), 404);

        return Storage::disk(SupplyAttachment::DISK)->download($attachment->path, $attachment->name);
    }

    public function destroy(Request $request, SupplyAttachment $attachment): RedirectResponse
    {
        $this->authorizeSupply($request, $attachment->supply);

        $attachment->delete();

        return back();
    }

    /**
     * Same gate as the supply itself: the manager of its area, or the
     * organizer for an event-level supply. Cross-tenant is a 404.
     */
    private function authorizeSupply(Request $request, Supply $supply): void
    {
        $person = $request->user();

        abort_unless($supply->tenant_id === $person->tenant_id, 404);
        abort_unless(
            $supply->area_id === null ? $person->isOrganizer() : $person->managesArea($supply->area_id),
            404,
        );
    }
}

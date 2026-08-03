{{-- Expects: $attachmentType (route segment, e.g. 'purchase-voucher'), $attachmentId, $attachments (collection) --}}
<div class="card mb-4">
    <div class="card-header"><i class="me-1" data-lucide="paperclip"></i> Attachments</div>
    <div class="card-body">
        <div class="table-responsive mb-3">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>File</th>
                        <th>Size</th>
                        <th>Uploaded</th>
                        <th class="table-actions-head">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($attachments as $attachment)
                    <tr>
                        <td><a href="{{ $attachment->url() }}" target="_blank">{{ $attachment->original_name }}</a></td>
                        <td>{{ $attachment->humanSize() }}</td>
                        <td class="text-muted small">{{ $attachment->created_at->format('M d, Y') }}</td>
                        <td class="table-actions-cell">
                            <form action="{{ route('attachments.destroy', [$attachmentType, $attachmentId, $attachment]) }}" method="POST" onsubmit="return confirm('Remove this attachment?');">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i data-lucide="trash-2"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="text-center text-muted py-3">No attachments uploaded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <form action="{{ route('attachments.store', [$attachmentType, $attachmentId]) }}" method="POST" enctype="multipart/form-data" class="d-flex gap-2">
            @csrf
            <input type="file" name="attachments[]" class="form-control" multiple>
            <button type="submit" class="btn btn-outline-primary text-nowrap">Upload</button>
        </form>
    </div>
</div>

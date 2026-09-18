<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\GoodTransferSourceService;
use App\Services\InvoiceSourceService;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentSuggestionController extends Controller
{
    public function __construct(
        private readonly InvoiceSourceService $invoiceSource,
        private readonly GoodTransferSourceService $goodTransferSource
    ) {
    }

    public function suggest(Request $request): JsonResponse
    {
        $this->assertOperationalUser($request->user());

        $data = $request->validate([
            'document_type' => ['required', 'in:invoice,good_transfer'],
            'company' => ['required', 'in:INGCO,SMI'],
            'q' => ['required', 'string', 'max:100'],
        ]);

        $query = trim($data['q']);

        // Autocomplete only starts after YYMMDD/ is present at the end,
        // optionally followed by a numeric sequence prefix (e.g. 260915/0).
        if (!preg_match('/(\d{6})\/(\d*)$/', $query, $matches)) {
            return response()->json(['suggestions' => []]);
        }

        $dateToken = $matches[1];
        $sequencePrefix = $matches[2] ?? '';
        $date = DateTimeImmutable::createFromFormat('!ymd', $dateToken);

        if (!$date || $date->format('ymd') !== $dateToken) {
            return response()->json(['suggestions' => []]);
        }

        $start = $date->format('Y-m-d 00:00:00');
        $end = $date->modify('+1 day')->format('Y-m-d 00:00:00');

        $rows = $data['document_type'] === 'good_transfer'
            ? $this->goodTransferSource->suggestNumbers(
                $data['company'],
                $dateToken,
                $start,
                $end,
                $sequencePrefix
            )
            : $this->invoiceSource->suggestNumbers(
                $data['company'],
                $dateToken,
                $start,
                $end,
                $sequencePrefix
            );

        $suggestions = $rows
            ->map(fn ($row) => [
                'number' => trim((string) $row->DocumentNumber),
                'date' => $this->formatDocumentDate($row->DocumentDate ?? null),
            ])
            ->values();

        return response()->json([
            'suggestions' => $suggestions,
        ]);
    }

    private function formatDocumentDate(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if ($value === null || $value === '') {
            return null;
        }

        $timestamp = strtotime((string) $value);

        return $timestamp === false ? null : date('Y-m-d', $timestamp);
    }

    private function assertOperationalUser(?User $user): void
    {
        if (!$user || !$user->is_active || $user->role === 'admin') {
            abort(403, 'Admin tidak dapat menjalankan proses checking.');
        }
    }
}

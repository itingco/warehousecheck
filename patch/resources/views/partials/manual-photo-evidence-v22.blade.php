@php
    $evidenceType = isset($transfer) ? 'transfer' : 'invoice';
    $evidenceHeader = isset($transfer) ? $transfer : $check;
    $evidences = \Illuminate\Support\Facades\DB::connection('apphub')
        ->table('warehouse_check_manual_photos as p')
        ->leftJoin('users as u', 'u.id', '=', 'p.checker_user_id')
        ->where('p.document_type', $evidenceType)
        ->where('p.check_header_id', $evidenceHeader->id)
        ->select('p.id', 'p.item_code', 'p.qty_base', 'p.created_at', 'u.name as checker_name')
        ->orderByDesc('p.created_at')->limit(100)->get();
@endphp
@if($evidences->isNotEmpty())
<section class="card" style="padding:20px;margin:18px 0">
  <h3>Riwayat Input Manual + Foto Bukti</h3>
  <div class="table-scroll"><table><thead><tr><th>Waktu</th><th>Kode Barang</th><th>Checker</th><th>Qty Base</th><th>Bukti Foto</th></tr></thead><tbody>
  @foreach($evidences as $evidence)
  <tr><td>{{ $evidence->created_at }}</td><td>{{ $evidence->item_code }}</td><td>{{ $evidence->checker_name ?: '-' }}</td>
      <td>{{ number_format((float) $evidence->qty_base, 4, '.', ',') }}</td>
      <td><a target="_blank" rel="noopener noreferrer" href="{{ route('checks.manual-photo.view', ['type' => $evidenceType, 'header' => $evidenceHeader->id, 'evidence' => $evidence->id]) }}">Lihat Foto</a></td></tr>
  @endforeach
  </tbody></table></div>
</section>
@endif

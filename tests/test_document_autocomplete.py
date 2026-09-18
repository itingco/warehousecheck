from pathlib import Path
import re

ROOT = Path('/mnt/data/v11_work')

routes = (ROOT / 'routes/web.php').read_text(encoding='utf-8')
blade = (ROOT / 'resources/views/checks/create.blade.php').read_text(encoding='utf-8')
gt = (ROOT / 'app/Services/GoodTransferSourceService.php').read_text(encoding='utf-8')

controller_path = ROOT / 'app/Http/Controllers/DocumentSuggestionController.php'
invoice_path = ROOT / 'app/Services/InvoiceSourceService.php'

assert "DocumentSuggestionController" in routes, 'document suggestion controller route missing'
assert "/checks/document-suggestions" in routes, 'suggestion endpoint missing'
assert "checks.document-suggestions" in routes, 'suggestion route name missing'
assert controller_path.exists(), 'DocumentSuggestionController.php missing'
assert invoice_path.exists(), 'InvoiceSourceService.php must be included in V11 patch'

controller = controller_path.read_text(encoding='utf-8')
invoice = invoice_path.read_text(encoding='utf-8')

for text, label in [(invoice, 'invoice'), (gt, 'good transfer')]:
    assert 'function suggestNumbers' in text, f'{label} suggestNumbers missing'
    assert 'TOP 20' in text, f'{label} query must be capped at TOP 20'
    assert ' >= ?' in text and ' < ?' in text, f'{label} query must use indexed date range'
    assert 'LIKE ?' in text, f'{label} query must filter by document number pattern'

assert 'AR_InvoiceDetails' not in invoice.split('function suggestNumbers',1)[1], 'suggest query must not join invoice details'
assert 'IC_MutationDetails' not in gt.split('function suggestNumbers',1)[1], 'suggest query must not join transfer details'

assert "preg_match" in controller and "\\d{6}" in controller, 'controller must require YYMMDD/ token'
assert "role === 'admin'" in controller or "role !== 'admin'" in controller, 'admin must be blocked from operational suggestions'
assert "document_type" in controller and "good_transfer" in controller, 'controller must support both document types'
assert 'DateTimeInterface' in controller, 'SQL Server date values must support DateTimeInterface as well as strings'

assert 'AbortController' in blade, 'frontend must cancel stale requests'
assert '350' in blade, 'frontend must debounce requests around 350ms'
assert "checks.document-suggestions" in blade, 'frontend must call suggestion endpoint'
assert re.search(r'\\d\{6\}.*\\/', blade), 'frontend must wait for YYMMDD/ pattern'
assert 'documentSuggestions' in blade, 'suggestion list UI missing'

print('V11 document autocomplete regression checks passed')

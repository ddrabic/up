<?php
declare(strict_types=1);
require __DIR__ . '/login_check.php';
require __DIR__ . '/lib/web.php';
$csrf = upp_csrf_token();
?>
<!doctype html>
<html lang="hr">
<head>
    <meta charset="utf-8">
    <title>Import proizvoda</title>
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
</head>
<body>
<nav class="w3-bar w3-purple">
    <a class="w3-bar-item w3-button" href="index.php">Početna</a>
    <a class="w3-bar-item w3-button" href="pregled_json.php">Pregled JSON-a</a>
    <a class="w3-bar-item w3-button w3-right" href="logout.php">Odjava</a>
    <span class="w3-bar-item w3-right">WP domena: <?= upp_escape((string) upp_config('target_domain')) ?></span>
</nav>
<main class="w3-container">
    <h1>Import proizvoda</h1>
    <button id="start" class="w3-button w3-border w3-border-red w3-round">Pokreni import</button>
    <button id="stop" class="w3-button w3-red w3-round" disabled>Zaustavi import</button>
    <p id="progress" aria-live="polite"></p>
    <table class="w3-table-all"><thead><tr><th>Zapis</th><th>Operacija</th><th>SKU</th><th>Poruka</th></tr></thead><tbody id="results"></tbody></table>
</main>
<script>
const button = document.getElementById('start');
const stopButton = document.getElementById('stop');
const progress = document.getElementById('progress');
const results = document.getElementById('results');
const csrfToken = <?= json_encode($csrf, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
let activeImportId = null;
let totalRecords = null;
let lastConfirmedRecord = 0;
let currentImportContext = '';

const endpointError = (response, endpoint) => {
    if (response.status === 404) {
        return new Error(`Serverska datoteka ${endpoint} nije pronađena (HTTP 404). Prenesite novu verziju aplikacije.`);
    }
    if (response.redirected && response.url.includes('login.php')) {
        return new Error('Prijava je istekla. Ponovno se prijavite i pokrenite import.');
    }
    return new Error(`Poslužitelj je vratio neočekivani HTML odgovor umjesto podataka (HTTP ${response.status}).`);
};

const parseEvent = (line) => {
    try {
        return JSON.parse(line);
    } catch (error) {
        if (line.trimStart().startsWith('<')) {
            const context = currentImportContext || `nakon potvrđenog zapisa #${lastConfirmedRecord}`;
            throw new Error(`Poslužitelj je vratio HTML stranicu tijekom obrade: ${context}. Provjerite serverski error log.`);
        }
        throw new Error(`Poslužitelj je vratio neispravan zapis rezultata: ${error.message}`);
    }
};

const stopImport = async () => {
    if (!activeImportId || stopButton.disabled) return;
    stopButton.disabled = true;
    const body = new FormData();
    body.append('csrf_token', csrfToken);
    body.append('import_id', activeImportId);
    const response = await fetch('stop_import.php', {method: 'POST', body, keepalive: true});
    const contentType = response.headers.get('content-type') || '';
    if (!contentType.includes('application/json')) {
        throw endpointError(response, 'stop_import.php');
    }
    const result = await response.json();
    if (!response.ok || result.success !== true) {
        throw new Error(result.message || 'Zaustavljanje importa nije uspjelo.');
    }
};

stopButton.addEventListener('click', async () => {
    try {
        await stopImport();
        progress.textContent = 'Zahtjev za zaustavljanje je poslan; dovršava se započeti paket.';
    } catch (error) {
        progress.textContent = `Greška pri zaustavljanju: ${error.message}`;
        stopButton.disabled = false;
    }
});

window.addEventListener('pagehide', () => {
    if (!activeImportId) return;
    const body = new FormData();
    body.append('csrf_token', csrfToken);
    body.append('import_id', activeImportId);
    navigator.sendBeacon('stop_import.php', body);
});

button.addEventListener('click', async () => {
    button.disabled = true;
    results.replaceChildren();
    totalRecords = null;
    lastConfirmedRecord = 0;
    currentImportContext = '';
    const startedAt = Date.now();
    let statusText = 'Import je pokrenut; čekanje na prvi WooCommerce odgovor';
    let processed = 0;
    const renderProgress = () => {
        const seconds = Math.floor((Date.now() - startedAt) / 1000);
        progress.textContent = `${statusText} (${seconds} s)`;
    };
    renderProgress();
    const timer = window.setInterval(renderProgress, 1000);
    const body = new FormData();
    body.append('csrf_token', csrfToken);
    try {
        const response = await fetch('import_03.php', {method: 'POST', body});
        const contentType = response.headers.get('content-type') || '';
        if (!contentType.includes('application/x-ndjson')) {
            throw endpointError(response, 'import_03.php');
        }
        if (!response.body) throw new Error('Poslužitelj nije vratio tijelo odgovora.');
        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';
        let finished = false;
        let streamError = null;

        const handleEvent = (event) => {
            if (event.type === 'start') {
                activeImportId = event.importId;
                stopButton.disabled = false;
                statusText = 'Veza je uspostavljena; priprema se prvi paket SKU-ova i WooCommerce kategorije';
            } else if (event.type === 'progress') {
                totalRecords = event.total ?? totalRecords;
                if (event.stage === 'ready') {
                    statusText = `JSON je spreman za import. Ukupno zapisa: ${totalRecords}`;
                } else if (event.stage === 'resolving') {
                    currentImportContext = `razrješavanje zapisa #${event.from}–#${event.to}`;
                    statusText = `Priprema zapisa ${event.from}–${event.to} od ${totalRecords}`;
                } else if (event.stage === 'record') {
                    currentImportContext = `zapis #${event.record}, SKU ${event.sku}`;
                    statusText = `Priprema zapisa ${event.record}/${totalRecords}, SKU: ${event.sku}`;
                } else if (event.stage === 'updating_batch') {
                    currentImportContext = `batch zapisa #${event.from}–#${event.to}; SKU-ovi: ${(event.skus || []).join(', ')}`;
                    statusText = `WooCommerce ažurira zapise ${event.from}–${event.to} od ${totalRecords}`;
                } else if (event.stage === 'updating_one') {
                    currentImportContext = `zapis #${event.record}, SKU ${event.sku}`;
                    statusText = `WooCommerce ažurira zapis ${event.record}/${totalRecords}, SKU: ${event.sku}`;
                }
            } else if (event.type === 'result') {
                processed++;
                const recordNumber = Number(event.index) + 1;
                lastConfirmedRecord = recordNumber;
            const row = document.createElement('tr');
                for (const value of [recordNumber, event.operation, event.sku, event.message]) {
                const cell = document.createElement('td'); cell.textContent = value ?? ''; row.appendChild(cell);
            }
            results.appendChild(row);
                statusText = `Obrađeno proizvoda: ${processed}/${totalRecords ?? '?'}. Zadnji zapis: #${recordNumber}, SKU: ${event.sku}`;
                renderProgress();
            } else if (event.type === 'complete') {
                finished = true;
                activeImportId = null;
                stopButton.disabled = true;
                const summary = event.summary;
                statusText = `Završeno: ${summary.created} kreirano, ${summary.updated} ažurirano, ${summary.errors} grešaka.`;
            } else if (event.type === 'cancelled') {
                finished = true;
                activeImportId = null;
                stopButton.disabled = true;
                statusText = `Import je zaustavljen. Obrađeno proizvoda: ${event.processed}/${totalRecords ?? '?'}.`;
            } else if (event.type === 'error') {
                streamError = event.message || 'Import nije uspio.';
            }
        };

        while (true) {
            const {value, done} = await reader.read();
            buffer += decoder.decode(value || new Uint8Array(), {stream: !done});
            const lines = buffer.split('\n');
            buffer = lines.pop() || '';
            for (const line of lines) {
                if (line.trim()) handleEvent(parseEvent(line));
            }
            if (done) break;
        }
        if (buffer.trim()) handleEvent(parseEvent(buffer));
        if (streamError) throw new Error(streamError);
        if (!response.ok || !finished) throw new Error('Import je prekinut prije završnog rezultata.');
        progress.textContent = statusText;
    } catch (error) {
        if (activeImportId) {
            const stopBody = new FormData();
            stopBody.append('csrf_token', csrfToken);
            stopBody.append('import_id', activeImportId);
            navigator.sendBeacon('stop_import.php', stopBody);
        }
        progress.textContent = `Greška: ${error.message}`;
    } finally {
        window.clearInterval(timer);
        activeImportId = null;
        stopButton.disabled = true;
        button.disabled = false;
    }
});
</script>
</body>
</html>

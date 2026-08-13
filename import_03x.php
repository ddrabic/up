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
    <p id="progress" aria-live="polite"></p>
    <table class="w3-table-all"><thead><tr><th>Operacija</th><th>SKU</th><th>Poruka</th></tr></thead><tbody id="results"></tbody></table>
</main>
<script>
const button = document.getElementById('start');
const progress = document.getElementById('progress');
const results = document.getElementById('results');
button.addEventListener('click', async () => {
    button.disabled = true;
    results.replaceChildren();
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
    body.append('csrf_token', <?= json_encode($csrf, JSON_HEX_TAG | JSON_HEX_AMP) ?>);
    try {
        const response = await fetch('import_03.php', {method: 'POST', body});
        if (!response.body) throw new Error('Poslužitelj nije vratio tijelo odgovora.');
        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';
        let completed = false;
        let streamError = null;

        const handleEvent = (event) => {
            if (event.type === 'start') {
                statusText = 'Veza je uspostavljena; čeka se obrada prvog proizvoda';
            } else if (event.type === 'result') {
                processed++;
            const row = document.createElement('tr');
                for (const value of [event.operation, event.sku, event.message]) {
                const cell = document.createElement('td'); cell.textContent = value ?? ''; row.appendChild(cell);
            }
            results.appendChild(row);
                statusText = `Obrađeno proizvoda: ${processed}. Zadnji SKU: ${event.sku}`;
                renderProgress();
            } else if (event.type === 'complete') {
                completed = true;
                const summary = event.summary;
                statusText = `Završeno: ${summary.created} kreirano, ${summary.updated} ažurirano, ${summary.errors} grešaka.`;
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
                if (line.trim()) handleEvent(JSON.parse(line));
            }
            if (done) break;
        }
        if (buffer.trim()) handleEvent(JSON.parse(buffer));
        if (streamError) throw new Error(streamError);
        if (!response.ok || !completed) throw new Error('Import je prekinut prije završnog rezultata.');
        progress.textContent = statusText;
    } catch (error) {
        progress.textContent = `Greška: ${error.message}`;
    } finally {
        window.clearInterval(timer);
        button.disabled = false;
    }
});
</script>
</body>
</html>

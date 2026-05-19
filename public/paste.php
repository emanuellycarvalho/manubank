<?php declare(strict_types=1);
/**
 * Interface de cola de texto para importação Nubank.
 * GET  → formulário HTML
 * POST → processa e exibe resultado
 */

require_once __DIR__ . '/../vendor/autoload.php';

$result   = null;
$preview  = [];
$error    = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $text = trim($_POST['text'] ?? '');
    $year = (int) ($_POST['year'] ?? date('Y'));

    if (empty($text)) {
        $error = 'Cole o texto da fatura antes de importar.';
    } else {
        try {
            $pdo        = Database::getConnection();
            $ruleEngine = new RuleEngine($pdo);
            $parser     = new NubankParser($ruleEngine, $year);
            $rows       = $parser->parseText($text);

            if (empty($rows)) {
                $error = 'Nenhuma transação reconhecida. Verifique se o texto está no formato: "DD MMM •••• DDDD Descrição R$ valor"';
            } else {
                $controller = new ImportController($pdo, $ruleEngine);
                $result     = $controller->persistFromRows($rows);
                $preview    = $rows;
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar Transações — Colar Texto</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-6">
<div class="bg-white rounded-2xl shadow-lg w-full max-w-2xl p-8">

    <h1 class="text-2xl font-bold text-gray-800 mb-1">Importar Transações</h1>
    <p class="text-sm text-gray-500 mb-6">
        Cole as linhas copiadas da fatura Nubank. Formato esperado:<br>
        <code class="text-xs bg-gray-100 rounded px-1 py-0.5 text-gray-700">
            29 MAR •••• 1470 Descrição R$ 286,58
        </code>
    </p>

    <?php if ($error): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 mb-6 text-sm">
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <?php if ($result !== null): ?>
    <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-4 mb-6">
        <p class="font-semibold text-base mb-1">
            ✓ <?= $result['imported'] ?> transações importadas
            <?php if ($result['skipped'] > 0): ?>
            <span class="text-yellow-600 text-sm">(<?= $result['skipped'] ?> ignoradas)</span>
            <?php endif; ?>
        </p>
        <?php foreach ($result['month_year_groups'] as $my => $count): ?>
        <span class="inline-block bg-green-100 text-green-800 text-xs rounded px-2 py-0.5 mr-1">
            <?= htmlspecialchars($my) ?>: <?= $count ?> lançamentos
        </span>
        <?php endforeach; ?>
    </div>

    <div class="mb-6">
        <h2 class="text-sm font-semibold text-gray-600 mb-2 uppercase tracking-wide">Transações importadas</h2>
        <div class="overflow-x-auto">
        <table class="w-full text-sm border-collapse">
            <thead>
                <tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                    <th class="px-3 py-2 text-left">Data</th>
                    <th class="px-3 py-2 text-left">Descrição</th>
                    <th class="px-3 py-2 text-right">Valor</th>
                    <th class="px-3 py-2 text-center">Parcela</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            <?php foreach ($preview as $row): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-3 py-2 text-gray-600 whitespace-nowrap"><?= htmlspecialchars($row['date']) ?></td>
                <td class="px-3 py-2 text-gray-800"><?= htmlspecialchars($row['raw_description']) ?></td>
                <td class="px-3 py-2 text-right font-mono text-red-600">
                    R$ <?= number_format($row['amount'], 2, ',', '.') ?>
                </td>
                <td class="px-3 py-2 text-center text-gray-500 text-xs">
                    <?= $row['installment_current'] !== null
                        ? $row['installment_current'] . '/' . $row['installment_total']
                        : '—' ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php endif; ?>

    <form method="POST" action="/paste.php">
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1" for="year">
                Ano das transações
            </label>
            <input
                type="number"
                id="year"
                name="year"
                value="<?= htmlspecialchars((string)($_POST['year'] ?? date('Y'))) ?>"
                min="2020"
                max="2099"
                class="w-32 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-400"
            >
        </div>

        <div class="mb-5">
            <label class="block text-sm font-medium text-gray-700 mb-1" for="text">
                Texto da fatura <span class="text-gray-400 font-normal">(Cole aqui as linhas)</span>
            </label>
            <textarea
                id="text"
                name="text"
                rows="10"
                placeholder="29 MAR •••• 1470 Mercadolivre*Mercadol - Parcela 6/12 R$ 286,58&#10;02 ABR •••• 8812 Dl *Uber*Rides R$ 5,91&#10;02 ABR •••• 8812 Pg *99 Ride R$ 7,06"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-purple-400 resize-y"
            ><?= htmlspecialchars($_POST['text'] ?? '') ?></textarea>
        </div>

        <button
            type="submit"
            class="w-full bg-purple-600 hover:bg-purple-700 text-white font-semibold py-2.5 rounded-lg transition-colors"
        >
            Importar transações
        </button>
    </form>

</div>
</body>
</html>

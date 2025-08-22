<?php
require_once __DIR__ . '/../src/db.php';

$msg = '';

$serieB = [
    'América-MG', 'Avaí', 'Botafogo-SP', 'Brusque', 'Ceará', 'Chapecoense',
    'CRB', 'Goiás', 'Guarani', 'Ituano', 'Mirassol', 'Novorizontino',
    'Operário-PR', 'Ponte Preta', 'Santos', 'Sport', 'Vila Nova', 'Amazonas',
    'Paysandu', 'Coritiba', 'Londrina'
];

$limit = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

default $filter_nome = isset($_GET['nome']) ? trim($_GET['nome']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    $nome = trim($_POST['nome'] ?? '');
    $cidade = trim($_POST['cidade'] ?? '');
    if ($nome === '' || $cidade === '') {
        $msg = 'Nome e cidade são obrigatórios!';
    } else {
        $pdo = getPDO();
        $stmt = $pdo->prepare('INSERT INTO times (nome, cidade) VALUES (?, ?)');
        $stmt->execute([$nome, $cidade]);
        $msg = 'Time cadastrado com sucesso!';
    }
}

if (isset($_POST['edit_id'])) {
    $id = intval($_POST['edit_id']);
    $nome = trim($_POST['edit_nome'] ?? '');
    $cidade = trim($_POST['edit_cidade'] ?? '');
    if ($nome === '' || $cidade === '') {
        $msg = 'Nome e cidade são obrigatórios!';
    } else {
        $pdo = getPDO();
        $stmt = $pdo->prepare('UPDATE times SET nome=?, cidade=? WHERE id=?');
        $stmt->execute([$nome, $cidade, $id]);
        $msg = 'Time atualizado!';
    }
}

if (isset($_POST['delete_id'])) {
    $id = intval($_POST['delete_id']);
    $pdo = getPDO();
    $hasPlayers = $pdo->prepare('SELECT COUNT(*) FROM jogadores WHERE time_id=?');
    $hasPlayers->execute([$id]);
    $hasMatches = $pdo->prepare('SELECT COUNT(*) FROM partidas WHERE time_casa_id=? OR time_fora_id=?');
    $hasMatches->execute([$id, $id]);
    if ($hasPlayers->fetchColumn() > 0 || $hasMatches->fetchColumn() > 0) {
        $msg = 'Não é possível excluir: existem jogadores ou partidas vinculados.';
    } else {
        $stmt = $pdo->prepare('DELETE FROM times WHERE id=?');
        $stmt->execute([$id]);
        $msg = 'Time excluído!';
    }
}

$pdo = getPDO();
$where = '';
$params = [];
if ($filter_nome !== '') {
    $where = 'WHERE nome LIKE ?';
    $params[] = "%$filter_nome%";
}
$total = $pdo->prepare("SELECT COUNT(*) FROM times $where");
$total->execute($params);
$totalRows = $total->fetchColumn();

$stmt = $pdo->prepare("SELECT * FROM times $where ORDER BY nome LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$times = $stmt->fetchAll(PDO::FETCH_ASSOC);
$totalPages = ceil($totalRows / $limit);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Times</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ccc; padding: 8px; }
        th { background: #eee; }
        .msg { color: red; }
    </style>
</head>
<body>
    <h1>Times</h1>
    <?php if ($msg): ?><p class="msg"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
    <form method="get">
        <input type="text" name="nome" placeholder="Filtrar por nome" value="<?= htmlspecialchars($filter_nome) ?>">
        <button type="submit">Filtrar</button>
    </form>
    <h2>Cadastrar novo time</h2>
    <form method="post">
        <label>Selecione um time da Série B:</label>
        <select name="nome_select" onchange="document.getElementById('nome_manual').value=this.value;">
            <option value="">-- Selecione --</option>
            <?php foreach ($serieB as $time): ?>
                <option value="<?= htmlspecialchars($time) ?>"><?= htmlspecialchars($time) ?></option>
            <?php endforeach; ?>
        </select>
        <br>
        <label>Ou digite o nome do time:</label>
        <input type="text" id="nome_manual" name="nome" placeholder="Nome do time" required>
        <input type="text" name="cidade" placeholder="Cidade" required>
        <button type="submit" name="add">Cadastrar</button>
    </form>
    <h2>Lista de times</h2>
    <table>
        <tr><th>ID</th><th>Nome</th><th>Cidade</th><th>Ações</th></tr>
        <?php foreach ($times as $t): ?>
        <tr>
            <form method="post">
            <td><?= $t['id'] ?></td>
            <td><input type="text" name="edit_nome" value="<?= htmlspecialchars($t['nome']) ?>" required></td>
            <td><input type="text" name="edit_cidade" value="<?= htmlspecialchars($t['cidade']) ?>" required></td>
            <td>
                <button type="submit" name="edit_id" value="<?= $t['id'] ?>">Salvar</button>
                <button type="submit" name="delete_id" value="<?= $t['id'] ?>" onclick="return confirm('Excluir este time?')">Excluir</button>
            </td>
            </form>
        </tr>
        <?php endforeach; ?>
    </table>
    <div>
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?page=<?= $i ?>&nome=<?= urlencode($filter_nome) ?>"<?= $i === $page ? ' style="font-weight:bold"' : '' ?>><?= $i ?></a>
        <?php endfor; ?>
    </div>
</body>
</html>

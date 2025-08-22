<?php
require_once __DIR__ . '/../src/db.php';

$msg = '';
$limit = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Filtros
$filter_time = isset($_GET['time_id']) ? intval($_GET['time_id']) : 0;
$filter_data_ini = isset($_GET['data_ini']) ? $_GET['data_ini'] : '';
$filter_data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : '';

$pdo = getPDO();
$times = $pdo->query('SELECT id, nome FROM times ORDER BY nome')->fetchAll(PDO::FETCH_ASSOC);

// Cadastro
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    $casa = intval($_POST['time_casa_id'] ?? 0);
    $fora = intval($_POST['time_fora_id'] ?? 0);
    $data = $_POST['data_jogo'] ?? '';
    $gols_casa = max(0, intval($_POST['gols_casa'] ?? 0));
    $gols_fora = max(0, intval($_POST['gols_fora'] ?? 0));
    if ($casa < 1 || $fora < 1 || $casa === $fora || !$data) {
        $msg = 'Preencha todos os campos corretamente e selecione times diferentes!';
    } else {
        try {
            $stmt = $pdo->prepare('INSERT INTO partidas (time_casa_id, time_fora_id, data_jogo, gols_casa, gols_fora) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$casa, $fora, $data, $gols_casa, $gols_fora]);
            $msg = 'Partida cadastrada!';
        } catch (Exception $e) {
            $msg = 'Erro ao cadastrar partida.';
        }
    }
}

// Edição
if (isset($_POST['edit_id'])) {
    $id = intval($_POST['edit_id']);
    $casa = intval($_POST['edit_time_casa_id'] ?? 0);
    $fora = intval($_POST['edit_time_fora_id'] ?? 0);
    $data = $_POST['edit_data_jogo'] ?? '';
    $gols_casa = max(0, intval($_POST['edit_gols_casa'] ?? 0));
    $gols_fora = max(0, intval($_POST['edit_gols_fora'] ?? 0));
    if ($casa < 1 || $fora < 1 || $casa === $fora || !$data) {
        $msg = 'Preencha todos os campos corretamente e selecione times diferentes!';
    } else {
        try {
            $stmt = $pdo->prepare('UPDATE partidas SET time_casa_id=?, time_fora_id=?, data_jogo=?, gols_casa=?, gols_fora=? WHERE id=?');
            $stmt->execute([$casa, $fora, $data, $gols_casa, $gols_fora, $id]);
            $msg = 'Partida atualizada!';
        } catch (Exception $e) {
            $msg = 'Erro ao atualizar partida.';
        }
    }
}

// Exclusão
if (isset($_POST['delete_id'])) {
    $id = intval($_POST['delete_id']);
    try {
        $stmt = $pdo->prepare('DELETE FROM partidas WHERE id=?');
        $stmt->execute([$id]);
        $msg = 'Partida excluída!';
    } catch (Exception $e) {
        $msg = 'Erro ao excluir partida.';
    }
}

// Listagem com filtros e paginação
$where = [];
$params = [];
if ($filter_time > 0) {
    $where[] = '(p.time_casa_id = ? OR p.time_fora_id = ?)';
    $params[] = $filter_time;
    $params[] = $filter_time;
}
if ($filter_data_ini) {
    $where[] = 'p.data_jogo >= ?';
    $params[] = $filter_data_ini;
}
if ($filter_data_fim) {
    $where[] = 'p.data_jogo <= ?';
    $params[] = $filter_data_fim;
}
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = $pdo->prepare("SELECT COUNT(*) FROM partidas p $whereSQL");
$total->execute($params);
$totalRows = $total->fetchColumn();

$sql = "SELECT p.*, t1.nome as casa_nome, t2.nome as fora_nome FROM partidas p JOIN times t1 ON p.time_casa_id = t1.id JOIN times t2 ON p.time_fora_id = t2.id $whereSQL ORDER BY p.data_jogo DESC, p.id DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$partidas = $stmt->fetchAll(PDO::FETCH_ASSOC);
$totalPages = ceil($totalRows / $limit);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Partidas</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ccc; padding: 8px; }
        th { background: #eee; }
        .msg { color: red; }
    </style>
</head>
<body>
    <h1>Partidas</h1>
    <?php if ($msg): ?><p class="msg"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
    <form method="get">
        <select name="time_id">
            <option value="0">Todos os times</option>
            <?php foreach ($times as $t): ?>
                <option value="<?= $t['id'] ?>"<?= $filter_time == $t['id'] ? ' selected' : '' ?>><?= htmlspecialchars($t['nome']) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="date" name="data_ini" value="<?= htmlspecialchars($filter_data_ini) ?>">
        <input type="date" name="data_fim" value="<?= htmlspecialchars($filter_data_fim) ?>">
        <button type="submit">Filtrar</button>
    </form>
    <h2>Cadastrar nova partida</h2>
    <form method="post">
        <select name="time_casa_id" required>
            <option value="">Time mandante</option>
            <?php foreach ($times as $t): ?>
                <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['nome']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="time_fora_id" required>
            <option value="">Time visitante</option>
            <?php foreach ($times as $t): ?>
                <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['nome']) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="date" name="data_jogo" required>
        <input type="number" name="gols_casa" min="0" value="0" required style="width:60px;"> x
        <input type="number" name="gols_fora" min="0" value="0" required style="width:60px;">
        <button type="submit" name="add">Cadastrar</button>
    </form>
    <h2>Lista de partidas</h2>
    <table>
        <tr><th>ID</th><th>Mandante</th><th>Visitante</th><th>Data</th><th>Placar</th><th>Ações</th></tr>
        <?php foreach ($partidas as $p): ?>
        <tr>
            <form method="post">
            <td><?= $p['id'] ?></td>
            <td>
                <select name="edit_time_casa_id" required>
                    <?php foreach ($times as $t): ?>
                        <option value="<?= $t['id'] ?>"<?= $p['time_casa_id'] == $t['id'] ? ' selected' : '' ?>><?= htmlspecialchars($t['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td>
                <select name="edit_time_fora_id" required>
                    <?php foreach ($times as $t): ?>
                        <option value="<?= $t['id'] ?>"<?= $p['time_fora_id'] == $t['id'] ? ' selected' : '' ?>><?= htmlspecialchars($t['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td><input type="date" name="edit_data_jogo" value="<?= $p['data_jogo'] ?>" required></td>
            <td>
                <input type="number" name="edit_gols_casa" min="0" value="<?= $p['gols_casa'] ?>" required style="width:60px;"> x
                <input type="number" name="edit_gols_fora" min="0" value="<?= $p['gols_fora'] ?>" required style="width:60px;">
            </td>
            <td>
                <button type="submit" name="edit_id" value="<?= $p['id'] ?>">Salvar</button>
                <button type="submit" name="delete_id" value="<?= $p['id'] ?>" onclick="return confirm('Excluir esta partida?')">Excluir</button>
            </td>
            </form>
        </tr>
        <?php endforeach; ?>
    </table>
    <div>
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?page=<?= $i ?>&time_id=<?= $filter_time ?>&data_ini=<?= urlencode($filter_data_ini) ?>&data_fim=<?= urlencode($filter_data_fim) ?>"<?= $i === $page ? ' style="font-weight:bold"' : '' ?>><?= $i ?></a>
        <?php endfor; ?>
    </div>
</body>
</html>

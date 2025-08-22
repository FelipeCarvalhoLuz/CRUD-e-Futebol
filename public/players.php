<?php
require_once __DIR__ . '/../src/db.php';

// Lista de posições válidas
$posicoes = ['GOL', 'ZAG', 'LAT', 'MEI', 'ATA'];
$msg = '';

// Paginação
$limit = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Filtros
$filter_nome = isset($_GET['nome']) ? trim($_GET['nome']) : '';
$filter_posicao = isset($_GET['posicao']) ? trim($_GET['posicao']) : '';
$filter_time = isset($_GET['time_id']) ? intval($_GET['time_id']) : 0;

$pdo = getPDO();
// Carregar times para o filtro e cadastro
$times = $pdo->query('SELECT id, nome FROM times ORDER BY nome')->fetchAll(PDO::FETCH_ASSOC);

// Cadastro
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    $nome = trim($_POST['nome'] ?? '');
    $posicao = $_POST['posicao'] ?? '';
    $numero = intval($_POST['numero_camisa'] ?? 0);
    $time_id = intval($_POST['time_id'] ?? 0);
    if ($nome === '' || !in_array($posicao, $posicoes) || $numero < 1 || $numero > 99 || $time_id < 1) {
        $msg = 'Preencha todos os campos corretamente!';
    } else {
        try {
            $stmt = $pdo->prepare('INSERT INTO jogadores (nome, posicao, numero_camisa, time_id) VALUES (?, ?, ?, ?)');
            $stmt->execute([$nome, $posicao, $numero, $time_id]);
            $msg = 'Jogador cadastrado!';
        } catch (Exception $e) {
            $msg = 'Erro ao cadastrar jogador.';
        }
    }
}

// Edição
if (isset($_POST['edit_id'])) {
    $id = intval($_POST['edit_id']);
    $nome = trim($_POST['edit_nome'] ?? '');
    $posicao = $_POST['edit_posicao'] ?? '';
    $numero = intval($_POST['edit_numero_camisa'] ?? 0);
    $time_id = intval($_POST['edit_time_id'] ?? 0);
    if ($nome === '' || !in_array($posicao, $posicoes) || $numero < 1 || $numero > 99 || $time_id < 1) {
        $msg = 'Preencha todos os campos corretamente!';
    } else {
        try {
            $stmt = $pdo->prepare('UPDATE jogadores SET nome=?, posicao=?, numero_camisa=?, time_id=? WHERE id=?');
            $stmt->execute([$nome, $posicao, $numero, $time_id, $id]);
            $msg = 'Jogador atualizado!';
        } catch (Exception $e) {
            $msg = 'Erro ao atualizar jogador.';
        }
    }
}

// Exclusão
if (isset($_POST['delete_id'])) {
    $id = intval($_POST['delete_id']);
    try {
        $stmt = $pdo->prepare('DELETE FROM jogadores WHERE id=?');
        $stmt->execute([$id]);
        $msg = 'Jogador excluído!';
    } catch (Exception $e) {
        $msg = 'Erro ao excluir jogador.';
    }
}

// Listagem com filtros e paginação
$where = [];
$params = [];
if ($filter_nome !== '') {
    $where[] = 'j.nome LIKE ?';
    $params[] = "%$filter_nome%";
}
if ($filter_posicao !== '' && in_array($filter_posicao, $posicoes)) {
    $where[] = 'j.posicao = ?';
    $params[] = $filter_posicao;
}
if ($filter_time > 0) {
    $where[] = 'j.time_id = ?';
    $params[] = $filter_time;
}
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = $pdo->prepare("SELECT COUNT(*) FROM jogadores j $whereSQL");
$total->execute($params);
$totalRows = $total->fetchColumn();

$sql = "SELECT j.*, t.nome as time_nome FROM jogadores j JOIN times t ON j.time_id = t.id $whereSQL ORDER BY j.nome LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$jogadores = $stmt->fetchAll(PDO::FETCH_ASSOC);
$totalPages = ceil($totalRows / $limit);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Jogadores</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ccc; padding: 8px; }
        th { background: #eee; }
        .msg { color: red; }
    </style>
</head>
<body>
    <h1>Jogadores</h1>
    <?php if ($msg): ?><p class="msg"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
    <form method="get">
        <input type="text" name="nome" placeholder="Filtrar por nome" value="<?= htmlspecialchars($filter_nome) ?>">
        <select name="posicao">
            <option value="">Todas posições</option>
            <?php foreach ($posicoes as $p): ?>
                <option value="<?= $p ?>"<?= $filter_posicao === $p ? ' selected' : '' ?>><?= $p ?></option>
            <?php endforeach; ?>
        </select>
        <select name="time_id">
            <option value="0">Todos os times</option>
            <?php foreach ($times as $t): ?>
                <option value="<?= $t['id'] ?>"<?= $filter_time == $t['id'] ? ' selected' : '' ?>><?= htmlspecialchars($t['nome']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Filtrar</button>
    </form>
    <h2>Cadastrar novo jogador</h2>
    <form method="post" id="cadastroJogador">
        <select name="time_id" id="time_id" required onchange="carregarJogadores()">
            <option value="">Selecione o time</option>
            <?php foreach ($times as $t): ?>
                <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['nome']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="nome" id="jogador_nome" required>
            <option value="">Selecione o jogador</option>
        </select>
        <select name="posicao" required>
            <option value="">Posição</option>
            <?php foreach ($posicoes as $p): ?>
                <option value="<?= $p ?>"><?= $p ?></option>
            <?php endforeach; ?>
        </select>
        <input type="number" name="numero_camisa" min="1" max="99" placeholder="Nº Camisa" required>
        <button type="submit" name="add">Cadastrar</button>
    </form>
    <script>
    // Carregar jogadores do time selecionado via AJAX
    function carregarJogadores() {
        var timeId = document.getElementById('time_id').value;
        var jogadorSelect = document.getElementById('jogador_nome');
        jogadorSelect.innerHTML = '<option value="">Carregando...</option>';
        if (!timeId) {
            jogadorSelect.innerHTML = '<option value="">Selecione o jogador</option>';
            return;
        }
        var xhr = new XMLHttpRequest();
        xhr.open('GET', 'players.php?ajax=1&time_id=' + timeId, true);
        xhr.onload = function() {
            if (xhr.status === 200) {
                var jogadores = JSON.parse(xhr.responseText);
                var options = '<option value="">Selecione o jogador</option>';
                for (var i = 0; i < jogadores.length; i++) {
                    options += '<option value="' + jogadores[i].nome.replace(/"/g, '&quot;') + '">' + jogadores[i].nome + ' (Camisa ' + jogadores[i].numero_camisa + ')</option>';
                }
                jogadorSelect.innerHTML = options;
            } else {
                jogadorSelect.innerHTML = '<option value="">Erro ao carregar</option>';
            }
        };
        xhr.send();
    }
    </script>
$ajax = isset($_GET['ajax']) && $_GET['ajax'] == '1';
if ($ajax && isset($_GET['time_id'])) {
    $time_id = intval($_GET['time_id']);
    $stmt = $pdo->prepare('SELECT nome, numero_camisa FROM jogadores WHERE time_id = ?');
    $stmt->execute([$time_id]);
    $jogadores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: application/json');
    echo json_encode($jogadores);
    exit;
}
    <h2>Lista de jogadores</h2>
    <table>
        <tr><th>ID</th><th>Nome</th><th>Posição</th><th>Nº</th><th>Time</th><th>Ações</th></tr>
        <?php foreach ($jogadores as $j): ?>
        <tr>
            <form method="post">
            <td><?= $j['id'] ?></td>
            <td><input type="text" name="edit_nome" value="<?= htmlspecialchars($j['nome']) ?>" required></td>
            <td>
                <select name="edit_posicao" required>
                    <?php foreach ($posicoes as $p): ?>
                        <option value="<?= $p ?>"<?= $j['posicao'] === $p ? ' selected' : '' ?>><?= $p ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td><input type="number" name="edit_numero_camisa" min="1" max="99" value="<?= $j['numero_camisa'] ?>" required></td>
            <td>
                <select name="edit_time_id" required>
                    <?php foreach ($times as $t): ?>
                        <option value="<?= $t['id'] ?>"<?= $j['time_id'] == $t['id'] ? ' selected' : '' ?>><?= htmlspecialchars($t['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td>
                <button type="submit" name="edit_id" value="<?= $j['id'] ?>">Salvar</button>
                <button type="submit" name="delete_id" value="<?= $j['id'] ?>" onclick="return confirm('Excluir este jogador?')">Excluir</button>
            </td>
            </form>
        </tr>
        <?php endforeach; ?>
    </table>
    <div>
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?page=<?= $i ?>&nome=<?= urlencode($filter_nome) ?>&posicao=<?= urlencode($filter_posicao) ?>&time_id=<?= $filter_time ?>"<?= $i === $page ? ' style="font-weight:bold"' : '' ?>><?= $i ?></a>
        <?php endfor; ?>
    </div>
</body>
</html>

<?php
// Conexão com o banco de dados
$host = 'db';
$db   = 'sdts3';
$user = 'sdts3user';
$pass = 'sdts3pass';
$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die('Erro ao conectar ao banco de dados.');
}

// Listagens
$receitas = $pdo->query('SELECT * FROM receitas ORDER BY ano DESC, mes DESC, id DESC')->fetchAll();
$itens = $pdo->query('SELECT * FROM itens_despesas ORDER BY nome')->fetchAll();
$despesas = $pdo->query('SELECT d.*, i.nome as item_nome, i.valor_unitario FROM despesas_realizadas d JOIN itens_despesas i ON d.item_id = i.id ORDER BY ano DESC, mes DESC, d.id DESC')->fetchAll();

// Tabela de Receitas
echo '<h2>Receitas</h2>';
echo '<table>';
echo '<tr><th>Nome</th><th>Tipo</th><th>Valor</th><th>Mês</th><th>Ano</th><th>Ações</th></tr>';
foreach ($receitas as $r) {
    echo '<tr>';
    echo '<td>' . htmlspecialchars($r['nome']) . '</td>';
    echo '<td>' . htmlspecialchars($r['tipo']) . '</td>';
    echo '<td>R$ ' . number_format($r['valor'],2,',','.') . '</td>';
    echo '<td>' . ($r['mes'] ?? '-') . '</td>';
    echo '<td>' . $r['ano'] . '</td>';
    echo '<td class="actions">';
    echo '<form method="post" style="display:inline;">';
    echo '<input type="hidden" name="delete_receita" value="' . $r['id'] . '" />';
    echo '<button type="submit" onclick="return confirm(\'Remover receita?\')">🗑️</button>';
    echo '</form>';
    echo '</td>';
    echo '</tr>';
}
echo '</table>';

// Tabela de Itens de Despesa
echo '<h2>Itens de Despesa</h2>';
echo '<table>';
echo '<tr><th>Nome</th><th>Descrição</th><th>Valor Unitário</th><th>Categoria</th><th>Elemento Item</th><th>Ações</th></tr>';
foreach ($itens as $i) {
    echo '<tr>';
    echo '<td>' . htmlspecialchars($i['nome']) . '</td>';
    echo '<td>' . htmlspecialchars($i['descricao']) . '</td>';
    echo '<td>R$ ' . number_format($i['valor_unitario'],2,',','.') . '</td>';
    echo '<td>' . htmlspecialchars($i['categoria']) . '</td>';
    echo '<td>' . htmlspecialchars($i['elemento_item'] ?? '') . '</td>';
    echo '<td class="actions">';
    echo '<form method="post" style="display:inline;">';
    echo '<input type="hidden" name="delete_item" value="' . $i['id'] . '" />';
    echo '<button type="submit" onclick="return confirm(\'Remover item?\')">🗑️</button>';
    echo '</form>';
    echo '</td>';
    echo '</tr>';
}
echo '</table>';

// Tabela de Despesas Realizadas
echo '<h2>Despesas Realizadas</h2>';
echo '<table>';
echo '<tr><th>Item</th><th>Valor Unitário</th><th>Quantidade</th><th>Valor Total</th><th>Mês</th><th>Ano</th><th>Ações</th></tr>';
foreach ($despesas as $d) {
    echo '<tr>';
    echo '<td>' . htmlspecialchars($d['item_nome']) . '</td>';
    echo '<td>R$ ' . number_format($d['valor_unitario'],2,',','.') . '</td>';
    echo '<td>' . $d['quantidade'] . '</td>';
    echo '<td>R$ ' . number_format($d['valor_total'],2,',','.') . '</td>';
    echo '<td>' . $d['mes'] . '</td>';
    echo '<td>' . $d['ano'] . '</td>';
    echo '<td class="actions">';
    echo '<form method="post" style="display:inline;">';
    echo '<input type="hidden" name="delete_despesa" value="' . $d['id'] . '" />';
    echo '<button type="submit" onclick="return confirm(\'Remover despesa?\')">🗑️</button>';
    echo '</form>';
    echo '</td>';
    echo '</tr>';
}
echo '</table>'; 
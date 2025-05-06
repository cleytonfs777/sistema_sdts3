<?php
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
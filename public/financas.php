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

// Criação das tabelas se não existirem
$pdo->exec("CREATE TABLE IF NOT EXISTS receitas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50),
    tipo VARCHAR(20),
    valor DECIMAL(15,2),
    mes INT NULL,
    ano INT
)");
$pdo->exec("CREATE TABLE IF NOT EXISTS itens_despesas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100),
    descricao VARCHAR(255),
    valor_unitario DECIMAL(15,2),
    categoria VARCHAR(20),
    elemento_item VARCHAR(100) NULL
)");
$pdo->exec("CREATE TABLE IF NOT EXISTS despesas_realizadas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT,
    quantidade INT,
    valor_total DECIMAL(15,2),
    mes INT,
    ano INT,
    FOREIGN KEY (item_id) REFERENCES itens_despesas(id) ON DELETE CASCADE
)");

// Inserção de exemplos iniciais se não existirem
if ($pdo->query('SELECT COUNT(*) FROM itens_despesas')->fetchColumn() == 0) {
    $pdo->exec("INSERT INTO itens_despesas (nome, descricao, valor_unitario, categoria) VALUES
        ('Analista (hora/aula)', 'Hora técnica de analista', 120.00, 'serviço'),
        ('Desenvolvedor (hora/aula)', 'Hora técnica de desenvolvedor', 100.00, 'serviço'),
        ('Licença Adobe Creative Cloud', 'Licença anual Adobe', 2500.00, 'material')");
}

// Função para feedback visual
function feedback($msg, $type = 'success') {
    $_SESSION['feedback'] = "<div id='feedback' class='feedback $type'>$msg</div>";
}

// Funções auxiliares para o dashboard
function getReceitasMensais($pdo, $ano) {
    $receitas = $pdo->prepare('SELECT * FROM receitas WHERE ano = ?');
    $receitas->execute([$ano]);
    $mensais = array_fill(1, 12, 0);
    
    foreach ($receitas->fetchAll() as $r) {
        if ($r['tipo'] === 'mensal') {
            $mensais[$r['mes']] += $r['valor'];
        } else { // anual
            $valor_mensal = $r['valor'] / 12;
            for ($m = 1; $m <= 12; $m++) {
                $mensais[$m] += $valor_mensal;
            }
        }
    }
    return $mensais;
}

function getDespesasMensais($pdo, $ano) {
    $despesas = $pdo->prepare('SELECT mes, SUM(valor_total) as total FROM despesas_realizadas WHERE ano = ? GROUP BY mes');
    $despesas->execute([$ano]);
    $mensais = array_fill(1, 12, 0);
    
    foreach ($despesas->fetchAll() as $d) {
        $mensais[$d['mes']] = $d['total'];
    }
    return $mensais;
}

function calcularSaldos($receitas, $despesas) {
    $saldos = [];
    $acumulado = 0;
    
    for ($m = 1; $m <= 12; $m++) {
        $saldo = $receitas[$m] - $despesas[$m];
        $acumulado += $saldo;
        $saldos[$m] = [
            'mensal' => $saldo,
            'acumulado' => $acumulado
        ];
    }
    return $saldos;
}

// Obter dados do ano atual
$ano_atual = date('Y');
$receitas_mensais = getReceitasMensais($pdo, $ano_atual);
$despesas_mensais = getDespesasMensais($pdo, $ano_atual);
$saldos = calcularSaldos($receitas_mensais, $despesas_mensais);

// API para dados do dashboard
if (isset($_GET['api']) && $_GET['api'] === 'dashboard') {
    header('Content-Type: application/json');
    echo json_encode([
        'receitas' => $receitas_mensais,
        'despesas' => $despesas_mensais,
        'saldos' => $saldos
    ]);
    exit;
}

// API para exportar CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=dashboard_' . $ano_atual . '.csv');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Mês', 'Receitas', 'Despesas', 'Saldo Mensal', 'Saldo Acumulado']);
    
    for ($m = 1; $m <= 12; $m++) {
        fputcsv($output, [
            $m,
            $receitas_mensais[$m],
            $despesas_mensais[$m],
            $saldos[$m]['mensal'],
            $saldos[$m]['acumulado']
        ]);
    }
    fclose($output);
    exit;
}

// API para obter dados de um item específico
if (isset($_GET['api']) && $_GET['api'] === 'get_item') {
    header('Content-Type: application/json');
    $id = $_GET['id'];
    $tipo = $_GET['tipo'];
    
    switch ($tipo) {
        case 'receita':
            $stmt = $pdo->prepare('SELECT * FROM receitas WHERE id = ?');
            break;
        case 'item':
            $stmt = $pdo->prepare('SELECT * FROM itens_despesas WHERE id = ?');
            break;
        case 'despesa':
            $stmt = $pdo->prepare('SELECT d.*, i.nome as item_nome FROM despesas_realizadas d JOIN itens_despesas i ON d.item_id = i.id WHERE d.id = ?');
            break;
    }
    
    $stmt->execute([$id]);
    echo json_encode($stmt->fetch());
    exit;
}

// Adicionar validação de valores monetários
function formatarValor($valor) {
    return number_format($valor, 2, ',', '.');
}

function validarValor($valor) {
    return preg_match('/^\d+([,.]\d{1,2})?$/', $valor);
}

// API para validação de despesa
if (isset($_GET['api']) && $_GET['api'] === 'validar_despesa') {
    header('Content-Type: application/json');
    $mes = $_GET['mes'];
    $ano = $_GET['ano'];
    $valor = $_GET['valor'];
    
    $receitas = getReceitasMensais($pdo, $ano);
    $despesas = getDespesasMensais($pdo, $ano);
    
    $receita_mes = $receitas[$mes];
    $despesa_mes = $despesas[$mes];
    
    echo json_encode([
        'receita_mes' => $receita_mes,
        'despesa_mes' => $despesa_mes,
        'disponivel' => $receita_mes - $despesa_mes,
        'ultrapassa' => ($despesa_mes + $valor) > $receita_mes
    ]);
    exit;
}

// API para retornar tabelas HTML e feedback
if (isset($_GET['api']) && $_GET['api'] === 'tabelas') {
    ob_start();
    include __DIR__ . '/financas_tabelas.php';
    $tabelas_html = ob_get_clean();
    $feedback = isset($_SESSION['feedback']) ? $_SESSION['feedback'] : '';
    unset($_SESSION['feedback']);
    header('Content-Type: application/json');
    echo json_encode([
        'tabelas' => $tabelas_html,
        'feedback' => $feedback
    ]);
    exit;
}

// CRUD Receitas
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Adicionar receita
    if (isset($_POST['add_receita'])) {
        $stmt = $pdo->prepare('INSERT INTO receitas (nome, tipo, valor, mes, ano) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([
            $_POST['nome'], $_POST['tipo'], $_POST['valor'], $_POST['tipo']==='mensal'?$_POST['mes']:null, $_POST['ano']
        ]);
        feedback('Receita adicionada com sucesso!');
    }
    // Editar receita
    if (isset($_POST['edit_receita'])) {
        $stmt = $pdo->prepare('UPDATE receitas SET nome=?, tipo=?, valor=?, mes=?, ano=? WHERE id=?');
        $stmt->execute([
            $_POST['nome'], $_POST['tipo'], $_POST['valor'], $_POST['tipo']==='mensal'?$_POST['mes']:null, $_POST['ano'], $_POST['edit_receita']
        ]);
        feedback('Receita atualizada!');
    }
    // Deletar receita
    if (isset($_POST['delete_receita'])) {
        $stmt = $pdo->prepare('DELETE FROM receitas WHERE id=?');
        $stmt->execute([$_POST['delete_receita']]);
        feedback('Receita removida!');
    }
    // Adicionar item despesa
    if (isset($_POST['add_item'])) {
        $stmt = $pdo->prepare('INSERT INTO itens_despesas (nome, descricao, valor_unitario, categoria, elemento_item) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([
            $_POST['nome'], $_POST['descricao'], $_POST['valor_unitario'], $_POST['categoria'], $_POST['elemento_item']
        ]);
        feedback('Item de despesa adicionado!');
    }
    // Editar item despesa
    if (isset($_POST['edit_item'])) {
        $stmt = $pdo->prepare('UPDATE itens_despesas SET nome=?, descricao=?, valor_unitario=?, categoria=?, elemento_item=? WHERE id=?');
        $stmt->execute([
            $_POST['nome'], $_POST['descricao'], $_POST['valor_unitario'], $_POST['categoria'], $_POST['elemento_item'], $_POST['edit_item']
        ]);
        feedback('Item de despesa atualizado!');
    }
    // Deletar item despesa
    if (isset($_POST['delete_item'])) {
        $stmt = $pdo->prepare('DELETE FROM itens_despesas WHERE id=?');
        $stmt->execute([$_POST['delete_item']]);
        feedback('Item de despesa removido!');
    }
    // Adicionar despesa realizada
    if (isset($_POST['add_despesa'])) {
        $item = $pdo->prepare('SELECT valor_unitario FROM itens_despesas WHERE id=?');
        $item->execute([$_POST['item_id']]);
        $valor_unitario = $item->fetchColumn();
        $valor_total = $valor_unitario * $_POST['quantidade'];
        $stmt = $pdo->prepare('INSERT INTO despesas_realizadas (item_id, quantidade, valor_total, mes, ano) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([
            $_POST['item_id'], $_POST['quantidade'], $valor_total, $_POST['mes'], $_POST['ano']
        ]);
        feedback('Despesa realizada registrada!');
    }
    // Editar despesa realizada
    if (isset($_POST['edit_despesa'])) {
        $item = $pdo->prepare('SELECT valor_unitario FROM itens_despesas WHERE id=?');
        $item->execute([$_POST['item_id']]);
        $valor_unitario = $item->fetchColumn();
        $valor_total = $valor_unitario * $_POST['quantidade'];
        $stmt = $pdo->prepare('UPDATE despesas_realizadas SET item_id=?, quantidade=?, valor_total=?, mes=?, ano=? WHERE id=?');
        $stmt->execute([
            $_POST['item_id'], $_POST['quantidade'], $valor_total, $_POST['mes'], $_POST['ano'], $_POST['edit_despesa']
        ]);
        feedback('Despesa realizada atualizada!');
    }
    // Deletar despesa realizada
    if (isset($_POST['delete_despesa'])) {
        $stmt = $pdo->prepare('DELETE FROM despesas_realizadas WHERE id=?');
        $stmt->execute([$_POST['delete_despesa']]);
        feedback('Despesa realizada removida!');
    }
}

// Listagens
$receitas = $pdo->query('SELECT * FROM receitas ORDER BY ano DESC, mes DESC, id DESC')->fetchAll();
$itens = $pdo->query('SELECT * FROM itens_despesas ORDER BY nome')->fetchAll();
$despesas = $pdo->query('SELECT d.*, i.nome as item_nome, i.valor_unitario FROM despesas_realizadas d JOIN itens_despesas i ON d.item_id = i.id ORDER BY ano DESC, mes DESC, d.id DESC')->fetchAll();

ob_start();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Finanças</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: Arial, sans-serif; background: #f7f7fa; margin: 0; }
        .container { max-width: 1100px; margin: 2rem auto; background: #fff; border-radius: 10px; box-shadow: 0 2px 8px #0001; padding: 2rem; }
        h2 { color: #232946; margin-top: 2rem; }
        form { margin-bottom: 1.5rem; background: #f4f6f8; padding: 1rem 1.5rem; border-radius: 8px; }
        label { display: block; margin-bottom: 0.2rem; font-weight: bold; }
        input, select, textarea { width: 100%; padding: 0.5rem; margin-bottom: 0.7rem; border-radius: 5px; border: 1px solid #ccc; font-size: 1rem; }
        button, .btn { background: #232946; color: #fff; border: none; border-radius: 6px; padding: 0.5rem 1.2rem; font-size: 1rem; font-weight: bold; cursor: pointer; margin-right: 0.5rem; transition: background 0.2s; }
        button:hover, .btn:hover { background: #ffb347; color: #232946; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 2rem; }
        th, td { padding: 0.7rem; text-align: center; border-bottom: 1px solid #eee; }
        th { background: #232946; color: #fff; }
        tr:nth-child(even) { background: #f4f6f8; }
        .actions { white-space: nowrap; }
        .feedback { margin: 1rem 0; padding: 0.7rem 1.2rem; border-radius: 6px; font-weight: bold; }
        .feedback.success { background: #d4edda; color: #155724; }
        .feedback.error { background: #f8d7da; color: #721c24; }
        @media (max-width: 800px) {
            .container { padding: 0.5rem; }
            form { padding: 0.5rem; }
            th, td { font-size: 0.95rem; padding: 0.4rem; }
        }
        
        /* Novos estilos para o dashboard */
        .dashboard { margin: 2rem 0; }
        .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .dashboard-card { background: #fff; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px #0001; }
        .dashboard-card h3 { margin-top: 0; color: #232946; }
        .chart-container { position: relative; height: 300px; margin-bottom: 2rem; }
        
        .financial-table { width: 100%; border-collapse: collapse; margin: 2rem 0; }
        .financial-table th, .financial-table td { padding: 0.8rem; text-align: right; border: 1px solid #eee; }
        .financial-table th { background: #232946; color: #fff; text-align: center; }
        .financial-table td:first-child { text-align: left; }
        .positive { color: #28a745; }
        .negative { color: #dc3545; }
        
        .export-buttons { margin: 1rem 0; }
        .export-buttons button { margin-right: 1rem; }
        
        @media print {
            .no-print { display: none; }
            .container { max-width: none; box-shadow: none; }
        }
        
        /* Estilos para modais */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .modal.show {
            opacity: 1;
        }
        
        .modal-content {
            position: relative;
            background: #fff;
            width: 90%;
            max-width: 1000px;
            margin: 2rem auto;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transform: translateY(-20px);
            transition: transform 0.3s ease;
        }
        
        .modal.show .modal-content {
            transform: translateY(0);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }
        
        .modal-header h3 {
            margin: 0;
            color: #232946;
            font-size: 1.5rem;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
            padding: 0.5rem;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.2s;
        }
        
        .close-modal:hover {
            background-color: #f0f0f0;
        }
        
        .modal-body {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            max-height: 70vh;
            overflow-y: auto;
            padding-right: 1rem;
        }
        
        .modal-body::-webkit-scrollbar {
            width: 8px;
        }
        
        .modal-body::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        
        .modal-body::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }
        
        .modal-body::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        
        .modal-footer {
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid #eee;
            text-align: right;
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: bold;
            color: #232946;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #232946;
            box-shadow: 0 0 0 3px rgba(35, 41, 70, 0.1);
            outline: none;
        }
        
        .form-group textarea {
            height: 120px;
            resize: vertical;
        }
        
        .form-group .error-message {
            color: #dc3545;
            font-size: 0.875rem;
            margin-top: 0.25rem;
            display: none;
        }
        
        .form-group.has-error input,
        .form-group.has-error select,
        .form-group.has-error textarea {
            border-color: #dc3545;
        }
        
        .form-group.has-error .error-message {
            display: block;
        }
        
        .btn {
            padding: 0.8rem 1.5rem;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: #232946;
            color: #fff;
            border: none;
        }
        
        .btn-secondary {
            background: #f8f9fa;
            color: #232946;
            border: 1px solid #ddd;
        }
        
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .btn-primary:hover {
            background: #1a1f35;
        }
        
        .btn-secondary:hover {
            background: #e9ecef;
        }
        
        .valor-info {
            font-size: 0.875rem;
            color: #666;
            margin-top: 0.25rem;
        }
        
        .valor-info.alert {
            color: #dc3545;
        }
        
        @media (max-width: 768px) {
            .modal-body {
                grid-template-columns: 1fr;
                max-height: 60vh;
            }
            
            .modal-content {
                margin: 1rem;
                padding: 1rem;
            }
            
            .btn {
                padding: 0.6rem 1.2rem;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <div style="display:flex;justify-content:space-between;align-items:center;">
        <h1>Finanças</h1>
        <button class="btn btn-primary no-print" onclick="openAddMenu()">➕ Adicionar</button>
    </div>
    
    <!-- Menu de seleção de tipo de adição -->
    <div id="addMenuModal" class="modal">
        <div class="modal-content" style="max-width:400px;">
            <div class="modal-header">
                <h3>O que deseja adicionar?</h3>
                <button class="close-modal" onclick="closeModal('addMenuModal')">&times;</button>
            </div>
            <div class="modal-body" style="display:flex;flex-direction:column;gap:1rem;">
                <button class="btn btn-primary" onclick="closeModal('addMenuModal');openModal('modalReceita')">Receita</button>
                <button class="btn btn-primary" onclick="closeModal('addMenuModal');openModal('modalItem')">Item de Despesa</button>
                <button class="btn btn-primary" onclick="closeModal('addMenuModal');openModal('modalDespesa')">Despesa Realizada</button>
            </div>
        </div>
    </div>
    
    <!-- Dashboard -->
    <div class="dashboard">
        <h2>Dashboard Financeiro <?php echo $ano_atual; ?></h2>
        
        <div class="export-buttons">
            <button onclick="exportCSV()">Exportar CSV</button>
            <button onclick="window.print()">Imprimir Relatório</button>
        </div>
        
        <div class="dashboard-grid">
            <div class="dashboard-card">
                <h3>Receitas vs Despesas</h3>
                <div class="chart-container">
                    <canvas id="barChart"></canvas>
                </div>
            </div>
            <div class="dashboard-card">
                <h3>Saldo Acumulado</h3>
                <div class="chart-container">
                    <canvas id="lineChart"></canvas>
                </div>
            </div>
        </div>
        
        <table class="financial-table">
            <tr>
                <th>Mês</th>
                <th>Receitas</th>
                <th>Despesas</th>
                <th>Saldo Mensal</th>
                <th>Saldo Acumulado</th>
            </tr>
            <?php for ($m = 1; $m <= 12; $m++): ?>
            <tr>
                <td><?php echo $m; ?></td>
                <td>R$ <?php echo number_format($receitas_mensais[$m], 2, ',', '.'); ?></td>
                <td>R$ <?php echo number_format($despesas_mensais[$m], 2, ',', '.'); ?></td>
                <td class="<?php echo $saldos[$m]['mensal'] >= 0 ? 'positive' : 'negative'; ?>">
                    R$ <?php echo number_format($saldos[$m]['mensal'], 2, ',', '.'); ?>
                </td>
                <td class="<?php echo $saldos[$m]['acumulado'] >= 0 ? 'positive' : 'negative'; ?>">
                    R$ <?php echo number_format($saldos[$m]['acumulado'], 2, ',', '.'); ?>
                </td>
            </tr>
            <?php endfor; ?>
        </table>
    </div>

    <h2>Receitas</h2>
    <table>
        <tr><th>Nome</th><th>Tipo</th><th>Valor</th><th>Mês</th><th>Ano</th><th>Ações</th></tr>
        <?php foreach ($receitas as $r): ?>
        <tr>
            <td><?php echo htmlspecialchars($r['nome']); ?></td>
            <td><?php echo htmlspecialchars($r['tipo']); ?></td>
            <td>R$ <?php echo number_format($r['valor'],2,',','.'); ?></td>
            <td><?php echo $r['mes'] ?? '-'; ?></td>
            <td><?php echo $r['ano']; ?></td>
            <td class="actions">
                <form method="post" style="display:inline;">
                    <input type="hidden" name="delete_receita" value="<?php echo $r['id']; ?>" />
                    <button type="submit" onclick="return confirm('Remover receita?')">🗑️</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>

    <h2>Itens de Despesa</h2>
    <table>
        <tr><th>Nome</th><th>Descrição</th><th>Valor Unitário</th><th>Categoria</th><th>Elemento Item</th><th>Ações</th></tr>
        <?php foreach ($itens as $i): ?>
        <tr>
            <td><?php echo htmlspecialchars($i['nome']); ?></td>
            <td><?php echo htmlspecialchars($i['descricao']); ?></td>
            <td>R$ <?php echo number_format($i['valor_unitario'],2,',','.'); ?></td>
            <td><?php echo htmlspecialchars($i['categoria']); ?></td>
            <td><?php echo htmlspecialchars($i['elemento_item'] ?? ''); ?></td>
            <td class="actions">
                <form method="post" style="display:inline;">
                    <input type="hidden" name="delete_item" value="<?php echo $i['id']; ?>" />
                    <button type="submit" onclick="return confirm('Remover item?')">🗑️</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>

    <h2>Despesas Realizadas</h2>
    <table>
        <tr><th>Item</th><th>Valor Unitário</th><th>Quantidade</th><th>Valor Total</th><th>Mês</th><th>Ano</th><th>Ações</th></tr>
        <?php foreach ($despesas as $d): ?>
        <tr>
            <td><?php echo htmlspecialchars($d['item_nome']); ?></td>
            <td>R$ <?php echo number_format($d['valor_unitario'],2,',','.'); ?></td>
            <td><?php echo $d['quantidade']; ?></td>
            <td>R$ <?php echo number_format($d['valor_total'],2,',','.'); ?></td>
            <td><?php echo $d['mes']; ?></td>
            <td><?php echo $d['ano']; ?></td>
            <td class="actions">
                <form method="post" style="display:inline;">
                    <input type="hidden" name="delete_despesa" value="<?php echo $d['id']; ?>" />
                    <button type="submit" onclick="return confirm('Remover despesa?')">🗑️</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>

<!-- Modais atualizados -->
<div id="modalReceita" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Receita</h3>
            <button class="close-modal" onclick="closeModal('modalReceita')">&times;</button>
        </div>
        <form id="formReceita" method="post" onsubmit="return validarFormulario(this)">
            <input type="hidden" name="add_receita" value="1" />
            <div class="modal-body">
                <div class="form-group">
                    <label>Nome</label>
                    <select name="nome" required>
                        <option value="Ordinária">Ordinária</option>
                        <option value="Extraordinária">Extraordinária</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Tipo</label>
                    <select name="tipo" required onchange="toggleMesField(this)">
                        <option value="mensal">Mensal</option>
                        <option value="anual">Anual</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Valor</label>
                    <input type="text" name="valor" required oninput="formatarValorInput(this)" />
                    <div class="error-message">Valor inválido</div>
                </div>
                <div class="form-group">
                    <label>Mês</label>
                    <select name="mes">
                        <option value="">-</option>
                        <?php for($m=1;$m<=12;$m++): ?>
                            <option value="<?php echo $m; ?>"><?php echo $m; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Ano</label>
                    <input type="number" name="ano" value="<?php echo date('Y'); ?>" required min="2000" max="2100" />
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalReceita')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal de Item de Despesa -->
<div id="modalItem" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Item de Despesa</h3>
            <button class="close-modal" onclick="closeModal('modalItem')">&times;</button>
        </div>
        <form id="formItem" method="post">
            <div class="modal-body">
                <div class="form-group">
                    <label>Nome</label>
                    <input type="text" name="nome" required />
                </div>
                <div class="form-group">
                    <label>Descrição</label>
                    <textarea name="descricao" required></textarea>
                </div>
                <div class="form-group">
                    <label>Valor Unitário</label>
                    <input type="number" step="0.01" name="valor_unitario" required />
                </div>
                <div class="form-group">
                    <label>Categoria</label>
                    <select name="categoria" required>
                        <option value="material">Material</option>
                        <option value="serviço">Serviço</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Elemento Item</label>
                    <input type="text" name="elemento_item" />
                </div>
            </div>
            <div class="modal-footer">
                <input type="hidden" name="add_item" value="1" />
                <button type="button" class="btn" onclick="closeModal('modalItem')">Cancelar</button>
                <button type="submit" class="btn">Salvar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal de Despesa -->
<div id="modalDespesa" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Despesa Realizada</h3>
            <button class="close-modal" onclick="closeModal('modalDespesa')">&times;</button>
        </div>
        <form id="formDespesa" method="post">
            <div class="modal-body">
                <div class="form-group">
                    <label>Item</label>
                    <select name="item_id" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($itens as $i): ?>
                            <option value="<?php echo $i['id']; ?>" data-valor="<?php echo $i['valor_unitario']; ?>">
                                <?php echo htmlspecialchars($i['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Quantidade</label>
                    <input type="number" name="quantidade" min="1" required />
                </div>
                <div class="form-group">
                    <label>Mês</label>
                    <select name="mes" required>
                        <?php for($m=1;$m<=12;$m++): ?>
                            <option value="<?php echo $m; ?>"><?php echo $m; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Ano</label>
                    <input type="number" name="ano" value="<?php echo date('Y'); ?>" required />
                </div>
            </div>
            <div class="modal-footer">
                <input type="hidden" name="add_despesa" value="1" />
                <button type="button" class="btn" onclick="closeModal('modalDespesa')">Cancelar</button>
                <button type="submit" class="btn">Salvar</button>
            </div>
        </form>
    </div>
</div>

<div id="tabelas-financas">
<?php include __DIR__ . '/financas_tabelas.php'; ?>
</div>

<script>
// Configuração dos gráficos
const meses = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

// Gráfico de barras
const barCtx = document.getElementById('barChart').getContext('2d');
const barChart = new Chart(barCtx, {
    type: 'bar',
    data: {
        labels: meses,
        datasets: [{
            label: 'Receitas',
            data: <?php echo json_encode(array_values($receitas_mensais)); ?>,
            backgroundColor: 'rgba(40, 167, 69, 0.5)',
            borderColor: 'rgb(40, 167, 69)',
            borderWidth: 1
        }, {
            label: 'Despesas',
            data: <?php echo json_encode(array_values($despesas_mensais)); ?>,
            backgroundColor: 'rgba(220, 53, 69, 0.5)',
            borderColor: 'rgb(220, 53, 69)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// Gráfico de linha
const lineCtx = document.getElementById('lineChart').getContext('2d');
const lineChart = new Chart(lineCtx, {
    type: 'line',
    data: {
        labels: meses,
        datasets: [{
            label: 'Saldo Acumulado',
            data: <?php echo json_encode(array_map(function($s) { return $s['acumulado']; }, $saldos)); ?>,
            borderColor: 'rgb(35, 41, 70)',
            tension: 0.1,
            fill: false
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// Função para atualizar dashboard
async function updateDashboard() {
    try {
        const response = await fetch('?api=dashboard');
        const data = await response.json();
        
        // Atualizar gráficos
        barChart.data.datasets[0].data = Object.values(data.receitas);
        barChart.data.datasets[1].data = Object.values(data.despesas);
        barChart.update();
        
        lineChart.data.datasets[0].data = Object.values(data.saldos).map(s => s.acumulado);
        lineChart.update();
        
        // Atualizar tabela
        const table = document.querySelector('.financial-table');
        for (let m = 1; m <= 12; m++) {
            const row = table.rows[m];
            row.cells[1].textContent = `R$ ${data.receitas[m].toFixed(2).replace('.', ',')}`;
            row.cells[2].textContent = `R$ ${data.despesas[m].toFixed(2).replace('.', ',')}`;
            
            const saldoMensal = data.saldos[m].mensal;
            const saldoAcumulado = data.saldos[m].acumulado;
            
            row.cells[3].textContent = `R$ ${Math.abs(saldoMensal).toFixed(2).replace('.', ',')}`;
            row.cells[3].className = saldoMensal >= 0 ? 'positive' : 'negative';
            
            row.cells[4].textContent = `R$ ${Math.abs(saldoAcumulado).toFixed(2).replace('.', ',')}`;
            row.cells[4].className = saldoAcumulado >= 0 ? 'positive' : 'negative';
        }
    } catch (error) {
        console.error('Erro ao atualizar dashboard:', error);
    }
}

// Função para exportar CSV
function exportCSV() {
    window.location.href = '?export=csv';
}

// Atualizar dashboard após operações
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(form);
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData
            });
            if (response.ok) {
                // Atualizar dashboard
                updateDashboard();
                // Fechar modal se for modal
                const modal = form.closest('.modal');
                if (modal) closeModal(modal.id);
                // Resetar formulário
                form.reset();
                // Atualizar tabelas e feedback
                const tabelasResp = await fetch('?api=tabelas');
                const tabelasData = await tabelasResp.json();
                document.getElementById('tabelas-financas').innerHTML = tabelasData.tabelas;
                if (tabelasData.feedback) {
                    document.body.insertAdjacentHTML('afterbegin', tabelasData.feedback);
                    setTimeout(() => {
                        const fb = document.getElementById('feedback');
                        if (fb) fb.style.display = 'none';
                    }, 3000);
                }
            }
        } catch (error) {
            console.error('Erro ao processar formulário:', error);
        }
    });
});

// Validação de despesas
document.querySelector('form[name="add_despesa"]').addEventListener('submit', async (e) => {
    const mes = e.target.querySelector('[name="mes"]').value;
    const ano = e.target.querySelector('[name="ano"]').value;
    const valor = parseFloat(e.target.querySelector('[name="quantidade"]').value) * 
                 parseFloat(e.target.querySelector('[name="item_id"]').selectedOptions[0].dataset.valor);
    
    try {
        const response = await fetch(`?api=dashboard&mes=${mes}&ano=${ano}`);
        const data = await response.json();
        
        const receita_mes = data.receitas[mes];
        const despesa_mes = data.despesas[mes];
        
        if (despesa_mes + valor > receita_mes) {
            e.preventDefault();
            alert('Atenção: Esta despesa ultrapassará a receita planejada para o mês!');
        }
    } catch (error) {
        console.error('Erro ao validar despesa:', error);
    }
});

// Feedback visual
if (document.getElementById('feedback')) {
    setTimeout(() => {
        document.getElementById('feedback').style.display = 'none';
    }, 3000);
}

// Funções melhoradas para gerenciar modais
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.style.display = 'block';
    // Trigger reflow
    modal.offsetHeight;
    modal.classList.add('show');
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.classList.remove('show');
    setTimeout(() => {
        modal.style.display = 'none';
    }, 300);
}

function toggleMesField(select) {
    const mesField = select.form.querySelector('[name="mes"]');
    mesField.disabled = select.value === 'anual';
}

// Fechar modal ao clicar fora
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}

// Atualizar botões para abrir modais
document.querySelectorAll('.actions').forEach(actions => {
    const editBtn = document.createElement('button');
    editBtn.innerHTML = '✏️';
    editBtn.onclick = function() {
        const id = this.closest('tr').dataset.id;
        const tipo = this.closest('table').dataset.tipo;
        loadItemData(id, tipo);
    };
    actions.insertBefore(editBtn, actions.firstChild);
});

// Carregar dados para edição
async function loadItemData(id, tipo) {
    try {
        const response = await fetch(`?api=get_item&id=${id}&tipo=${tipo}`);
        const data = await response.json();
        
        const modalId = `modal${tipo.charAt(0).toUpperCase() + tipo.slice(1)}`;
        const form = document.getElementById(`form${tipo.charAt(0).toUpperCase() + tipo.slice(1)}`);
        
        // Preencher formulário
        for (const [key, value] of Object.entries(data)) {
            const input = form.querySelector(`[name="${key}"]`);
            if (input) {
                if (input.type === 'checkbox') {
                    input.checked = value;
                } else {
                    input.value = value;
                }
            }
        }
        
        // Ajustar campos específicos
        if (tipo === 'receita') {
            toggleMesField(form.querySelector('[name="tipo"]'));
        }
        
        // Alterar ação do formulário para edição
        form.querySelector('input[type="hidden"]').name = `edit_${tipo}`;
        form.querySelector('input[type="hidden"]').value = id;
        
        openModal(modalId);
    } catch (error) {
        console.error('Erro ao carregar dados:', error);
    }
}

// Formatação de valores monetários
function formatarValorInput(input) {
    let valor = input.value.replace(/\D/g, '');
    valor = (parseInt(valor) / 100).toFixed(2);
    valor = valor.replace('.', ',');
    valor = valor.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    input.value = valor;
}

// Validação de formulários
function validarFormulario(form) {
    let isValid = true;
    const valorInput = form.querySelector('[name="valor"]');
    
    if (valorInput) {
        const valor = valorInput.value.replace(/\./g, '').replace(',', '.');
        if (!validarValor(valor)) {
            valorInput.closest('.form-group').classList.add('has-error');
            isValid = false;
        } else {
            valorInput.closest('.form-group').classList.remove('has-error');
        }
    }
    
    return isValid;
}

// Validação de despesas em tempo real
async function validarDespesa(form) {
    const mes = form.querySelector('[name="mes"]').value;
    const ano = form.querySelector('[name="ano"]').value;
    const item = form.querySelector('[name="item_id"]').selectedOptions[0];
    const quantidade = form.querySelector('[name="quantidade"]').value;
    
    if (!mes || !ano || !item || !quantidade) return;
    
    const valor = parseFloat(item.dataset.valor) * parseFloat(quantidade);
    
    try {
        const response = await fetch(`?api=validar_despesa&mes=${mes}&ano=${ano}&valor=${valor}`);
        const data = await response.json();
        
        const infoDiv = form.querySelector('.valor-info') || document.createElement('div');
        infoDiv.className = 'valor-info';
        
        if (data.ultrapassa) {
            infoDiv.classList.add('alert');
            infoDiv.textContent = `Atenção: Esta despesa ultrapassará a receita disponível (R$ ${formatarValor(data.disponivel)})`;
        } else {
            infoDiv.classList.remove('alert');
            infoDiv.textContent = `Receita disponível: R$ ${formatarValor(data.disponivel)}`;
        }
        
        if (!form.querySelector('.valor-info')) {
            form.querySelector('.form-group').appendChild(infoDiv);
        }
    } catch (error) {
        console.error('Erro ao validar despesa:', error);
    }
}

// Atualizar validação de despesa quando os campos mudarem
document.querySelectorAll('#formDespesa input, #formDespesa select').forEach(input => {
    input.addEventListener('change', () => validarDespesa(input.form));
    input.addEventListener('input', () => validarDespesa(input.form));
});

function openAddMenu() {
    openModal('addMenuModal');
}
</script>
</body>
</html>
<?php
$content = ob_get_clean();
$page_title = 'Finanças - SDTS3 Manager';
include 'base.php'; 
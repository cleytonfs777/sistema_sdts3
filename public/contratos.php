<?php
session_start();
// Configuração do banco de dados
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
    $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Não foi possível conectar ao banco de dados.'];
    $pdo = null;
}

// CRUD
if ($pdo && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['novo_contrato'])) {
        $stmt = $pdo->prepare('INSERT INTO contratos (nome, cliente, valor, status, data_inicio, data_fim) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $_POST['nome'], $_POST['cliente'], $_POST['valor'], $_POST['status'], $_POST['data_inicio'], $_POST['data_fim']
        ]);
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Contrato criado com sucesso!'];
        header('Location: contratos.php');
        exit;
    }
    if (isset($_POST['edit_id'])) {
        $stmt = $pdo->prepare('UPDATE contratos SET nome=?, cliente=?, valor=?, status=?, data_inicio=?, data_fim=? WHERE id=?');
        $stmt->execute([
            $_POST['nome'], $_POST['cliente'], $_POST['valor'], $_POST['status'], $_POST['data_inicio'], $_POST['data_fim'], $_POST['edit_id']
        ]);
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Contrato atualizado com sucesso!'];
        header('Location: contratos.php');
        exit;
    }
}
if ($pdo && isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $pdo->prepare('DELETE FROM contratos WHERE id = ?');
    $stmt->execute([$id]);
    $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Contrato deletado com sucesso!'];
    header('Location: contratos.php');
    exit;
}

$contratos = $pdo ? $pdo->query('SELECT * FROM contratos ORDER BY id DESC')->fetchAll() : [];

function flash_message() {
    if (!empty($_SESSION['flash'])) {
        $type = $_SESSION['flash']['type'] === 'success' ? 'alert-success' : 'alert-danger';
        $msg = $_SESSION['flash']['msg'];
        echo "<div class='alert $type' role='alert' style='margin-bottom:1rem;'>$msg</div>";
        unset($_SESSION['flash']);
    }
}

ob_start();
?>
<div class="container-main">
    <?php flash_message(); ?>
    <div class="contratos-header">
        <h1>Contratos</h1>
        <button class="btn-novo" data-bs-toggle="modal" data-bs-target="#modalNovoContrato"><i class="fa fa-plus"></i> Novo Contrato</button>
    </div>
    <table class="table-contratos">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>Cliente</th>
                <th>Valor</th>
                <th>Status</th>
                <th>Início</th>
                <th>Fim</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($contratos as $contrato): ?>
            <tr>
                <td><?php echo $contrato['id']; ?></td>
                <td><?php echo htmlspecialchars($contrato['nome']); ?></td>
                <td><?php echo htmlspecialchars($contrato['cliente']); ?></td>
                <td>R$ <?php echo number_format($contrato['valor'],2,',','.'); ?></td>
                <td><?php echo htmlspecialchars($contrato['status']); ?></td>
                <td><?php echo date('d/m/Y', strtotime($contrato['data_inicio'])); ?></td>
                <td><?php echo date('d/m/Y', strtotime($contrato['data_fim'])); ?></td>
                <td>
                    <button class="btn-edit" title="Editar" data-bs-toggle="modal" data-bs-target="#modalEditarContrato<?php echo $contrato['id']; ?>"><i class="fa fa-pen"></i></button>
                    <a href="?delete=<?php echo $contrato['id']; ?>" class="btn-delete" title="Deletar" onclick="return confirm('Tem certeza que deseja deletar este contrato?');"><i class="fa fa-trash"></i></a>
                </td>
            </tr>
            <!-- Modal Editar Contrato -->
            <div class="modal fade" id="modalEditarContrato<?php echo $contrato['id']; ?>" tabindex="-1" aria-labelledby="modalEditarContratoLabel<?php echo $contrato['id']; ?>" aria-hidden="true">
              <div class="modal-dialog">
                <div class="modal-content">
                  <form method="post">
                    <div class="modal-header">
                      <h5 class="modal-title" id="modalEditarContratoLabel<?php echo $contrato['id']; ?>">Editar Contrato</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body form-contrato">
                      <input type="hidden" name="edit_id" value="<?php echo $contrato['id']; ?>" />
                      <input type="text" name="nome" value="<?php echo htmlspecialchars($contrato['nome']); ?>" placeholder="Nome do Contrato" required />
                      <input type="text" name="cliente" value="<?php echo htmlspecialchars($contrato['cliente']); ?>" placeholder="Cliente" required />
                      <input type="number" step="0.01" name="valor" value="<?php echo $contrato['valor']; ?>" placeholder="Valor" required />
                      <select name="status" required>
                        <option value="Ativo" <?php if($contrato['status']==='Ativo') echo 'selected'; ?>>Ativo</option>
                        <option value="Inativo" <?php if($contrato['status']==='Inativo') echo 'selected'; ?>>Inativo</option>
                        <option value="Pendente" <?php if($contrato['status']==='Pendente') echo 'selected'; ?>>Pendente</option>
                      </select>
                      <input type="date" name="data_inicio" value="<?php echo $contrato['data_inicio']; ?>" required />
                      <input type="date" name="data_fim" value="<?php echo $contrato['data_fim']; ?>" required />
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                      <button type="submit" class="btn btn-primary">Salvar</button>
                    </div>
                  </form>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($contratos)): ?>
            <tr><td colspan="8" style="text-align:center;color:#888;">Nenhum contrato cadastrado.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<!-- Modal Novo Contrato -->
<div class="modal fade" id="modalNovoContrato" tabindex="-1" aria-labelledby="modalNovoContratoLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <div class="modal-header">
          <h5 class="modal-title" id="modalNovoContratoLabel">Novo Contrato</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body form-contrato">
          <input type="hidden" name="novo_contrato" value="1" />
          <input type="text" name="nome" placeholder="Nome do Contrato" required />
          <input type="text" name="cliente" placeholder="Cliente" required />
          <input type="number" step="0.01" name="valor" placeholder="Valor" required />
          <select name="status" required>
            <option value="Ativo">Ativo</option>
            <option value="Inativo">Inativo</option>
            <option value="Pendente">Pendente</option>
          </select>
          <input type="date" name="data_inicio" required />
          <input type="date" name="data_fim" required />
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Salvar</button>
        </div>
      </form>
    </div>
  </div>
</div>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php
$content = ob_get_clean();
$page_title = 'Contratos - SDTS3 Manager';
include 'base.php'; 
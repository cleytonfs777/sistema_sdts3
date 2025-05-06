<?php
ob_start();
?>
<div class="placeholder" style="margin: 2rem auto; max-width: 700px; width: 100%; background: #fff; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.07); padding: 2rem 3rem;">
    <h1>Documentos</h1>
    <p>Área de documentos. Em breve, funcionalidades para upload, organização e consulta de documentos.</p>
</div>
<?php
$content = ob_get_clean();
$page_title = 'Documentos - SDTS3 Manager';
include 'base.php'; 
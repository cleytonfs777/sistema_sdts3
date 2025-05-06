<?php
ob_start();
?>
<div class="placeholder" style="margin: 2rem auto; max-width: 700px; width: 100%; background: #fff; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.07); padding: 2rem 3rem;">
    <h1>Finanças</h1>
    <p>Área financeira. Em breve, funcionalidades para controle de receitas, despesas e relatórios.</p>
</div>
<?php
$content = ob_get_clean();
$page_title = 'Finanças - SDTS3 Manager';
include 'base.php'; 
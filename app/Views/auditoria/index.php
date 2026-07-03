<?php
ob_start();
?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <h5 class="mb-0">Registros de auditoria</h5>
        <small class="text-muted">Últimos eventos administrativos registrados na RD Intranet</small>
    </div>

    <div class="card-body">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Usuário</th>
                    <th>Módulo</th>
                    <th>Ação</th>
                    <th>IP</th>
                    <th>Descrição</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($registros as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['criado_em']) ?></td>
                        <td><?= htmlspecialchars($r['usuario_nome'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($r['modulo']) ?></td>
                        <td><?= htmlspecialchars($r['acao']) ?></td>
                        <td><?= htmlspecialchars($r['ip_origem'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($r['descricao'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Auditoria';

require __DIR__ . '/../layouts/main.php';

<?php
ob_start();

use App\Components\Alert;
use App\Components\Badge;
use App\Services\EntraService;

$skusPorId = [];
foreach ($skus as $sku) {
    $skusPorId[$sku['skuId']] = EntraService::nomeAmigavelSku($sku['skuPartNumber'] ?? $sku['skuId']);
}
?>

<?= Alert::flash() ?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-1"><i class="bi bi-people me-1"></i> Microsoft Entra - Usuários</h4>
        <small class="text-muted"><a href="<?= url('/entra/dashboard') ?>"><i class="bi bi-arrow-left"></i> Dashboard</a></small>
    </div>
    <?php if ($configurado): ?>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNovoUsuarioEntra">
            <i class="bi bi-person-plus"></i> Novo usuário
        </button>
    <?php endif; ?>
</div>

<?php if (!$configurado): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-plug display-6 text-muted d-block mb-3"></i>
            <p class="text-muted mb-3">Módulo ainda não configurado.</p>
            <a href="<?= url('/entra/configuracao') ?>" class="btn btn-primary"><i class="bi bi-gear"></i> Configurar</a>
        </div>
    </div>
<?php else: ?>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <?php if (empty($usuarios)): ?>
                <p class="text-muted p-3 mb-0">Nenhum usuário encontrado no tenant.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>UPN</th>
                                <th>Status</th>
                                <th>Licenças</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $u): ?>
                                <?php
                                    $userId = $u['id'];
                                    $upn = $u['userPrincipalName'] ?? '';
                                    $nome = $u['displayName'] ?? $upn;
                                    $ativo = (bool)($u['accountEnabled'] ?? false);
                                    $licencasDoUsuario = array_column($u['assignedLicenses'] ?? [], 'skuId');
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($nome) ?></td>
                                    <td class="font-monospace small"><?= htmlspecialchars($upn) ?></td>
                                    <td><?= $ativo ? Badge::make('Ativo', 'success') : Badge::make('Desativado', 'secondary') ?></td>
                                    <td>
                                        <?php if (empty($licencasDoUsuario)): ?>
                                            <span class="text-muted small">—</span>
                                        <?php else: ?>
                                            <?php foreach ($licencasDoUsuario as $skuId): ?>
                                                <span class="badge text-bg-info me-1"><?= htmlspecialchars($skusPorId[$skuId] ?? $skuId) ?></span>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                Ações
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li>
                                                    <button type="button" class="dropdown-item botao-resetar-senha-entra"
                                                            data-user-id="<?= htmlspecialchars($userId) ?>" data-upn="<?= htmlspecialchars($upn) ?>">
                                                        <i class="bi bi-key"></i> Resetar senha
                                                    </button>
                                                </li>
                                                <li>
                                                    <button type="button" class="dropdown-item botao-licenca-entra"
                                                            data-user-id="<?= htmlspecialchars($userId) ?>" data-upn="<?= htmlspecialchars($upn) ?>"
                                                            data-licencas-atuais="<?= htmlspecialchars(implode(',', $licencasDoUsuario)) ?>">
                                                        <i class="bi bi-award"></i> Licenças
                                                    </button>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <form method="post" action="<?= url($ativo ? '/entra/usuarios/desativar' : '/entra/usuarios/ativar') ?>"
                                                          class="form-toggle-ativo-entra">
                                                        <input type="hidden" name="user_id" value="<?= htmlspecialchars($userId) ?>">
                                                        <input type="hidden" name="upn" value="<?= htmlspecialchars($upn) ?>">
                                                        <button type="submit" class="dropdown-item <?= $ativo ? 'text-danger' : '' ?>">
                                                            <i class="bi <?= $ativo ? 'bi-slash-circle' : 'bi-check-circle' ?>"></i>
                                                            <?= $ativo ? 'Desativar' : 'Ativar' ?>
                                                        </button>
                                                    </form>
                                                </li>
                                                <li>
                                                    <form method="post" action="<?= url('/entra/usuarios/excluir') ?>" class="form-excluir-usuario-entra">
                                                        <input type="hidden" name="user_id" value="<?= htmlspecialchars($userId) ?>">
                                                        <input type="hidden" name="upn" value="<?= htmlspecialchars($upn) ?>">
                                                        <button type="submit" class="dropdown-item text-danger">
                                                            <i class="bi bi-trash"></i> Excluir
                                                        </button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal: novo usuário -->
    <div class="modal fade" id="modalNovoUsuarioEntra" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="<?= url('/entra/usuarios/novo') ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">Novo usuário</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nome</label>
                            <input type="text" name="nome" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">UPN (login)</label>
                            <input type="email" name="upn" class="form-control font-monospace" required placeholder="usuario@empresa.onmicrosoft.com">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Senha inicial</label>
                            <input type="text" name="senha" class="form-control font-monospace" required>
                            <div class="form-text">O usuário é obrigado a trocar no primeiro login.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Criar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: resetar senha (compartilhado, preenchido via JS) -->
    <div class="modal fade" id="modalResetarSenhaEntra" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="<?= url('/entra/usuarios/resetar-senha') ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">Resetar senha</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="user_id" id="campoResetarUserId">
                        <input type="hidden" name="upn" id="campoResetarUpn">
                        <p class="text-muted small" id="textoResetarUsuario"></p>
                        <div class="mb-3">
                            <label class="form-label">Nova senha</label>
                            <input type="text" name="senha" class="form-control font-monospace" required>
                            <div class="form-text">O usuário é obrigado a trocar no primeiro login.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Resetar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: licenças (compartilhado, preenchido via JS) -->
    <div class="modal fade" id="modalLicencaEntra" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Licenças</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small" id="textoLicencaUsuario"></p>

                    <div id="listaLicencasAtuais" class="mb-3"></div>

                    <form method="post" action="<?= url('/entra/usuarios/licenca/atribuir') ?>" class="row g-2 align-items-end">
                        <input type="hidden" name="user_id" id="campoLicencaAtribuirUserId">
                        <input type="hidden" name="upn" id="campoLicencaAtribuirUpn">
                        <div class="col-8">
                            <label class="form-label small mb-1">Atribuir licença</label>
                            <select name="sku_id" class="form-select form-select-sm" required>
                                <?php foreach ($skus as $sku): ?>
                                    <option value="<?= htmlspecialchars($sku['skuId']) ?>"><?= htmlspecialchars(EntraService::nomeAmigavelSku($sku['skuPartNumber'] ?? $sku['skuId'])) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-4">
                            <button type="submit" class="btn btn-sm btn-primary w-100">Atribuir</button>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <form method="post" action="<?= url('/entra/usuarios/licenca/remover') ?>" id="formRemoverLicencaEntra" class="d-none">
        <input type="hidden" name="user_id" id="campoLicencaRemoverUserId">
        <input type="hidden" name="upn" id="campoLicencaRemoverUpn">
        <input type="hidden" name="sku_id" id="campoLicencaRemoverSkuId">
    </form>

<?php endif; ?>

<script>
window.addEventListener('load', function () {
    document.querySelectorAll('.botao-resetar-senha-entra').forEach(function (botao) {
        botao.addEventListener('click', function () {
            document.getElementById('campoResetarUserId').value = botao.dataset.userId;
            document.getElementById('campoResetarUpn').value = botao.dataset.upn;
            document.getElementById('textoResetarUsuario').textContent = 'Usuário: ' + botao.dataset.upn;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalResetarSenhaEntra')).show();
        });
    });

    const skusLabel = <?= json_encode($skusPorId) ?>;

    document.querySelectorAll('.botao-licenca-entra').forEach(function (botao) {
        botao.addEventListener('click', function () {
            const userId = botao.dataset.userId;
            const upn = botao.dataset.upn;
            const licencasAtuais = botao.dataset.licencasAtuais ? botao.dataset.licencasAtuais.split(',') : [];

            document.getElementById('campoLicencaAtribuirUserId').value = userId;
            document.getElementById('campoLicencaAtribuirUpn').value = upn;
            document.getElementById('textoLicencaUsuario').textContent = 'Usuário: ' + upn;

            const lista = document.getElementById('listaLicencasAtuais');
            lista.innerHTML = '';

            if (licencasAtuais.length === 0) {
                const vazio = document.createElement('span');
                vazio.className = 'text-muted small';
                vazio.textContent = 'Nenhuma licença atribuída.';
                lista.appendChild(vazio);
            } else {
                licencasAtuais.forEach(function (skuId) {
                    const badge = document.createElement('span');
                    badge.className = 'badge text-bg-info me-1 mb-1 d-inline-flex align-items-center gap-1';
                    badge.textContent = skusLabel[skuId] || skuId;

                    const botaoRemover = document.createElement('button');
                    botaoRemover.type = 'button';
                    botaoRemover.className = 'btn-close btn-close-white';
                    botaoRemover.style.fontSize = '9px';
                    botaoRemover.addEventListener('click', function () {
                        document.getElementById('campoLicencaRemoverUserId').value = userId;
                        document.getElementById('campoLicencaRemoverUpn').value = upn;
                        document.getElementById('campoLicencaRemoverSkuId').value = skuId;
                        document.getElementById('formRemoverLicencaEntra').submit();
                    });

                    badge.appendChild(botaoRemover);
                    lista.appendChild(badge);
                });
            }

            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalLicencaEntra')).show();
        });
    });

    document.querySelectorAll('.form-toggle-ativo-entra').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (form.action.includes('/desativar') && !confirm('Desativar este usuário no Entra? Ele perde acesso imediatamente.')) {
                e.preventDefault();
            }
        });
    });

    document.querySelectorAll('.form-excluir-usuario-entra').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!confirm('Excluir este usuário do Entra? Essa ação não pode ser desfeita por aqui (a conta some do tenant).')) {
                e.preventDefault();
            }
        });
    });
});
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Microsoft Entra - Usuários';

require __DIR__ . '/../layouts/main.php';

<?php
ob_start();

use App\Components\Alert;
use App\Components\Badge;
?>

<?= Alert::flash() ?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-1"><i class="bi bi-sliders me-1"></i> Microsoft Entra - Perfis de Configuração</h4>
        <small class="text-muted"><a href="<?= url('/entra/dashboard') ?>"><i class="bi bi-arrow-left"></i> Dashboard</a></small>
    </div>
    <?php if ($configurado): ?>
        <a href="<?= url('/entra/perfis-configuracao/novo') ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Novo perfil
        </a>
    <?php endif; ?>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <strong><i class="bi bi-question-circle"></i> O que é isso e como usar</strong>
        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#ajudaPerfis">
            Ver explicação
        </button>
    </div>
    <div class="collapse" id="ajudaPerfis">
        <div class="card-body">
            <p class="small text-muted">
                Um <strong>Perfil de Configuração</strong> é um conjunto de ajustes do Windows que o Intune aplica
                sozinho em toda máquina gerenciada, sem precisar mexer uma por uma. Você cria o perfil aqui, marca
                o que quer configurar, e depois <strong>aplica</strong> ele -- a partir daí, toda máquina inscrita
                no Intune recebe essas configurações automaticamente (e o usuário da máquina não consegue mudar,
                dependendo do que for marcado).
            </p>
            <strong class="small">O que cada campo do formulário faz</strong>
            <ul class="small text-muted">
                <li><strong>Papel de parede (área de trabalho / tela de bloqueio)</strong> -- define uma imagem
                    fixa, o usuário não consegue trocar. Precisa ser uma URL pública (http/https) de uma imagem
                    .jpg ou .png.</li>
                <li><strong>Bloquear conexão USB / Bloquear armazenamento removível</strong> -- impede o uso de
                    pen drives, HDs externos e outros dispositivos USB de armazenamento na máquina. Útil pra evitar
                    vazamento de dados (ex: informação de paciente, num laboratório).</li>
                <li><strong>Exigir senha pra entrar no Windows</strong> -- obriga ter senha configurada; sem isso
                    marcado, o Windows pode continuar sem senha nenhuma.</li>
                <li><strong>Tamanho mínimo da senha</strong> -- quantos caracteres a senha precisa ter no mínimo.</li>
                <li><strong>Bloquear tela após ficar parado (minutos)</strong> -- a tela trava sozinha depois de X
                    minutos sem uso, precisando da senha/PIN de novo pra continuar.</li>
                <li><strong>Bloquear a Windows Store</strong> -- impede o usuário de instalar apps pela Store.</li>
                <li><strong>Bloquear acesso às Configurações do Windows</strong> -- esconde o app de Configurações
                    inteiro (cuidado: isso também bloqueia o próprio usuário de ver informações básicas da
                    máquina).</li>
            </ul>
            <div class="alert alert-warning small mb-0">
                <i class="bi bi-exclamation-triangle"></i> <strong>"Aplicar a todos os dispositivos" afeta a frota
                inteira</strong> inscrita no Intune de uma vez, não dá pra escolher máquina por máquina nesta
                primeira versão. Pense bem antes de aplicar um perfil com campos restritivos (USB bloqueado, Store
                bloqueada) -- pode atrapalhar alguém que realmente precise daquilo no dia a dia.
                <br><br>
                <strong>Fora desta tela por enquanto:</strong> criptografia de disco (BitLocker) e perfil de Wi-Fi
                corporativo -- são configurações bem mais complexas (estrutura de campos bem diferente), ficam pra
                uma próxima etapa.
            </div>
        </div>
    </div>
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
            <?php if (empty($perfis)): ?>
                <p class="text-muted p-3 mb-0">Nenhum perfil de configuração criado ainda.</p>
            <?php else: ?>
                <?php /* Sem .table-responsive de proposito -- corta o dropdown de Acoes (mesmo motivo ja resolvido na tela de Dispositivos). */ ?>
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Descrição</th>
                            <th>Atribuição</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($perfis as $p): ?>
                            <?php
                                $id = $p['id'];
                                $nome = $p['displayName'] ?? '(sem nome)';
                                $assignments = $p['assignments'] ?? [];
                                $atribuidoTodos = false;
                                foreach ($assignments as $a) {
                                    if (($a['target']['@odata.type'] ?? '') === '#microsoft.graph.allDevicesAssignmentTarget') {
                                        $atribuidoTodos = true;
                                    }
                                }
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($nome) ?></td>
                                <td class="small text-muted"><?= htmlspecialchars($p['description'] ?? '—') ?></td>
                                <td>
                                    <?php if ($atribuidoTodos): ?>
                                        <?= Badge::make('Todos os dispositivos', 'success') ?>
                                    <?php elseif (!empty($assignments)): ?>
                                        <?= Badge::make('Atribuição personalizada', 'info') ?>
                                    <?php else: ?>
                                        <?= Badge::make('Ninguém', 'secondary') ?>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">Ações</button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li><a class="dropdown-item" href="<?= url('/entra/perfis-configuracao/editar?id=' . urlencode($id)) ?>"><i class="bi bi-pencil"></i> Editar</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <?php if ($atribuidoTodos): ?>
                                                <li>
                                                    <form method="post" action="<?= url('/entra/perfis-configuracao/desatribuir') ?>" class="form-acao-perfil" data-confirmacao="Remover a atribuição desse perfil? Ele deixa de valer pra qualquer dispositivo.">
                                                        <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>">
                                                        <input type="hidden" name="nome" value="<?= htmlspecialchars($nome) ?>">
                                                        <button type="submit" class="dropdown-item"><i class="bi bi-x-circle"></i> Remover atribuição</button>
                                                    </form>
                                                </li>
                                            <?php else: ?>
                                                <li>
                                                    <form method="post" action="<?= url('/entra/perfis-configuracao/atribuir') ?>" class="form-acao-perfil" data-confirmacao="Aplicar esse perfil em TODOS os dispositivos gerenciados pelo Intune? Isso afeta a frota inteira, não dá pra escolher só algumas máquinas.">
                                                        <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>">
                                                        <input type="hidden" name="nome" value="<?= htmlspecialchars($nome) ?>">
                                                        <button type="submit" class="dropdown-item"><i class="bi bi-broadcast"></i> Aplicar a todos os dispositivos</button>
                                                    </form>
                                                </li>
                                            <?php endif; ?>
                                            <li>
                                                <form method="post" action="<?= url('/entra/perfis-configuracao/excluir') ?>" class="form-acao-perfil" data-confirmacao="Excluir este perfil de configuração? Não pode ser desfeito por aqui.">
                                                    <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>">
                                                    <input type="hidden" name="nome" value="<?= htmlspecialchars($nome) ?>">
                                                    <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash"></i> Excluir</button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

<?php endif; ?>

<script>
(function () {
    document.querySelectorAll('.form-acao-perfil').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!confirm(form.dataset.confirmacao || 'Confirma essa ação?')) {
                e.preventDefault();
            }
        });
    });
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Microsoft Entra - Perfis de Configuração';

require __DIR__ . '/../layouts/main.php';

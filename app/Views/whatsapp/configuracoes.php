<?php
ob_start();

use App\Components\Alert;

/** Card "chave-mestra + lista de usuários selecionáveis" -- mesmo formato pras duas permissões (Encerrados/NPS). */
function wppCardAcessoUsuarios(string $idCampo, string $titulo, string $descricao, bool $restritoAtivo, array $usuariosAtivos, array $idsComAcesso, string $action): void
{
    ?>
    <div class="card border-0 shadow-sm mt-3" style="max-width:700px">
        <div class="card-body">
            <h6 class="mb-2"><?= htmlspecialchars($titulo) ?></h6>
            <p class="text-muted small"><?= $descricao ?></p>
            <form method="post" action="<?= url($action) ?>">
                <div class="form-check form-switch mb-2">
                    <input type="checkbox" name="restrito" class="form-check-input" id="<?= $idCampo ?>" role="switch" <?= $restritoAtivo ? 'checked' : '' ?>>
                    <label class="form-check-label" for="<?= $idCampo ?>">Restringir só a atendentes selecionados</label>
                </div>

                <?php if (empty($usuariosAtivos)): ?>
                    <p class="text-muted small">Nenhum usuário ativo cadastrado no sistema.</p>
                <?php else: ?>
                    <div class="row row-cols-1 row-cols-md-2 g-1 mb-3 ms-1" style="max-height:220px; overflow-y:auto">
                        <?php foreach ($usuariosAtivos as $usuario): ?>
                            <?php $marcado = in_array((int)$usuario['id'], $idsComAcesso, true); ?>
                            <div class="col">
                                <div class="form-check">
                                    <input type="checkbox" name="usuarios[]" value="<?= (int)$usuario['id'] ?>"
                                           class="form-check-input" id="<?= $idCampo ?>_<?= (int)$usuario['id'] ?>" <?= $marcado ? 'checked' : '' ?>>
                                    <label class="form-check-label small" for="<?= $idCampo ?>_<?= (int)$usuario['id'] ?>">
                                        <?= htmlspecialchars($usuario['nome']) ?> <span class="text-muted">(<?= htmlspecialchars($usuario['login']) ?>)</span>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-check-lg"></i> Salvar</button>
            </form>
        </div>
    </div>
    <?php
}
?>

<?= Alert::flash() ?>

<div class="mb-4">
    <h4 class="mb-1"><i class="bi bi-gear me-1"></i> WhatsApp - Configurações</h4>
    <small class="text-muted">Regras gerais do módulo, valem pra todos os setores.</small>
</div>

<div class="card border-0 shadow-sm" style="max-width:700px">
    <div class="card-body">
        <form method="post" action="<?= url('/whatsapp/configuracoes/salvar') ?>">
            <div class="form-check form-switch mb-2">
                <input type="checkbox" name="anexos_ativos" class="form-check-input" id="anexosAtivos" role="switch" <?= $anexosAtivos ? 'checked' : '' ?>>
                <label class="form-check-label" for="anexosAtivos"><strong>Atendente pode enviar e receber anexos</strong></label>
            </div>
            <p class="text-muted small mb-3">
                Chave-mestra -- desligada, nenhum anexo passa, não importa o que as chaves abaixo digam.
            </p>

            <div class="ms-4">
                <div class="form-check form-switch mb-2">
                    <input type="checkbox" name="imagens_ativas" class="form-check-input" id="imagensAtivas" role="switch" <?= $imagensAtivas ? 'checked' : '' ?>>
                    <label class="form-check-label" for="imagensAtivas"><i class="bi bi-image me-1"></i> Imagens</label>
                </div>
                <div class="form-check form-switch mb-2">
                    <input type="checkbox" name="documentos_ativos" class="form-check-input" id="documentosAtivos" role="switch" <?= $documentosAtivos ? 'checked' : '' ?>>
                    <label class="form-check-label" for="documentosAtivos"><i class="bi bi-file-earmark me-1"></i> Documentos</label>
                </div>
                <div class="form-check form-switch mb-3">
                    <input type="checkbox" name="audios_ativos" class="form-check-input" id="audiosAtivos" role="switch" <?= $audiosAtivos ? 'checked' : '' ?>>
                    <label class="form-check-label" for="audiosAtivos"><i class="bi bi-mic me-1"></i> Áudios</label>
                </div>
            </div>

            <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-check-lg"></i> Salvar</button>
        </form>
    </div>
</div>

<?php
wppCardAcessoUsuarios(
    'encerradosRestrito',
    'Quem pode ver Atendimentos > Encerrados',
    'Desligada, qualquer atendente vê os atendimentos que ele mesmo encerrou (comportamento de hoje). Ligada, só quem for marcado abaixo enxerga a aba Encerrados -- os outros nem veem ela no menu.',
    $encerradosRestritoAtivo,
    $usuariosAtivos,
    $idsComAcessoEncerrados,
    '/whatsapp/configuracoes/acesso-encerrados'
);

wppCardAcessoUsuarios(
    'npsRestrito',
    'Quem pode ver a pesquisa de satisfação dentro de Encerrados',
    'Desligada, quem já pode ver Encerrados também vê as perguntas/respostas do NPS na conversa. Ligada, só quem for marcado abaixo vê essa parte -- os outros veem a conversa normal, sem a pesquisa.',
    $npsRestritoAtivo,
    $usuariosAtivos,
    $idsComAcessoNps,
    '/whatsapp/configuracoes/acesso-nps'
);
?>

<?php
$conteudo = ob_get_clean();
$titulo = 'WhatsApp - Configurações';

require __DIR__ . '/../layouts/main.php';

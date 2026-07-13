<?php
ob_start();

use App\Components\Alert;
use App\Components\Badge;
?>

<?= Alert::flash() ?>

<div class="mb-4">
    <h4 class="mb-1"><i class="bi bi-display me-1"></i> Acesso Remoto</h4>
    <small class="text-muted">
        <a href="<?= url('/ativos') ?>"><i class="bi bi-arrow-left"></i> Dashboard</a> ·
        Powered by <a href="https://github.com/Ylianst/MeshCentral" target="_blank">MeshCentral</a> (open source, Apache 2.0) -- não é uma ferramenta construída do zero.
    </small>
</div>

<div class="card border-0 shadow-sm" style="max-width:720px">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <strong>Status</strong>
            <?php if (!$instalado): ?>
                <?= Badge::make('Não instalado', 'secondary') ?>
            <?php elseif ($rodando): ?>
                <?= Badge::make('Rodando', 'success') ?>
            <?php else: ?>
                <?= Badge::make('Instalado, mas parado', 'warning') ?>
            <?php endif; ?>
        </div>

        <?php if (!$instalado): ?>
            <p class="text-muted small">
                Instala o MeshCentral como serviço próprio (Node.js + systemd), numa porta dedicada
                (<?= (int)$porta ?>), separado do Apache/PHP. Não expõe nada na internet sozinho -- a porta só
                fica alcançável de onde o Firewall permitir.
            </p>
            <button type="button" class="btn btn-primary" id="botaoInstalar">
                <i class="bi bi-download"></i> Instalar
            </button>
        <?php else: ?>
            <p class="text-muted small">
                Console próprio do MeshCentral (criação da conta de administrador, instalador do
                MeshAgent pra baixar e rodar em cada máquina Windows, e configuração de tokens de
                automação usados pela integração com a ficha do ativo):
            </p>
            <a href="<?= htmlspecialchars($urlConsole) ?>" target="_blank" class="btn btn-outline-primary">
                <i class="bi bi-box-arrow-up-right"></i> Abrir console do MeshCentral
            </a>
            <p class="text-muted small mt-3 mb-0">
                Na primeira vez, a conta que você criar lá vira automaticamente administrador do
                MeshCentral. Depois de criar, desative o cadastro público de novas contas nas
                configurações dele (não é gerenciado por aqui ainda).
            </p>

            <?php if ($rodando): ?>
                <hr>
                <div class="d-flex justify-content-between align-items-center gap-3">
                    <div>
                        <strong>Porta <?= (int)$porta ?>/tcp no Firewall</strong>
                        <p class="text-muted small mb-0">
                            Sem isso, o console e a tela remota embutida na ficha do ativo não ficam
                            alcançáveis de fora deste servidor.
                        </p>
                    </div>
                    <?php if ($portaLiberada): ?>
                        <?= Badge::make('Liberada', 'success') ?>
                    <?php else: ?>
                        <button type="button" class="btn btn-sm btn-outline-warning text-nowrap" id="botaoLiberarPorta">
                            <i class="bi bi-unlock"></i> Liberar no Firewall
                        </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php if ($instalado && $rodando): ?>
<div class="card border-0 shadow-sm mt-3" style="max-width:720px">
    <div class="card-body">
        <strong>Integração (usada pra vincular ativos aos dispositivos e futuramente abrir a tela remota embutida)</strong>
        <p class="text-muted small mt-2 mb-3">
            Gere um <strong>Login Token</strong> no console do MeshCentral: entre com a conta admin,
            clique no seu usuário (canto superior direito) &gt; <em>Minha conta</em> &gt; <em>Tokens de login</em> &gt;
            <em>Novo</em>. Cole aqui o "Nome do usuário" e a "Senha" gerados (a senha só aparece uma vez lá).
        </p>
        <form method="post" action="<?= url('/ativos/acesso-remoto/credenciais') ?>" class="row g-2 align-items-end">
            <div class="col-sm-5">
                <label class="form-label small mb-1">Nome do usuário (token)</label>
                <input type="text" name="usuario" class="form-control form-control-sm" value="<?= htmlspecialchars($usuarioTokenAtual) ?>" placeholder="~t:xxxxxxxxxxxxxxxx" required>
            </div>
            <div class="col-sm-5">
                <label class="form-label small mb-1">Senha (token)</label>
                <input type="password" name="senha" class="form-control form-control-sm" placeholder="<?= $credenciaisConfiguradas ? '••••••••  (deixe preenchido pra manter)' : '' ?>" <?= $credenciaisConfiguradas ? '' : 'required' ?>>
            </div>
            <div class="col-sm-2">
                <button type="submit" class="btn btn-sm btn-primary w-100">Salvar</button>
            </div>
        </form>
        <?php if ($credenciaisConfiguradas): ?>
            <div class="mt-2"><?= Badge::make('Credenciais configuradas', 'success') ?></div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($instalado && $rodando && $credenciaisConfiguradas): ?>
<div class="card border-0 shadow-sm mt-3" style="max-width:960px">
    <div class="card-body">
        <strong>Dispositivos no MeshCentral</strong>
        <p class="text-muted small mt-2">
            Aparecem aqui depois que o MeshAgent for instalado numa máquina Windows (pelo instalador do
            próprio console do MeshCentral, link acima). Vincule cada um ao ativo correspondente pra
            habilitar o acesso remoto embutido na ficha do ativo.
        </p>
        <?php if (empty($dispositivos)): ?>
            <p class="text-muted small mb-0">Nenhum dispositivo reportado ainda pelo MeshCentral.</p>
        <?php else: ?>
            <?php
            $ativosPorMesh = [];
            foreach ($ativos as $a) {
                if (!empty($a['mesh_device_id'])) {
                    $ativosPorMesh[$a['mesh_device_id']] = $a;
                }
            }
            ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>Dispositivo</th>
                            <th>Grupo</th>
                            <th>Status</th>
                            <th>Vincular ao ativo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dispositivos as $d): ?>
                            <?php
                            $deviceId = $d['_id'] ?? '';
                            $vinculado = $ativosPorMesh[$deviceId] ?? null;
                            $conectado = !empty($d['conn']);
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($d['name'] ?? $deviceId) ?></td>
                                <td class="text-muted small"><?= htmlspecialchars($d['groupname'] ?? '-') ?></td>
                                <td><?= $conectado ? Badge::make('Conectado', 'success') : Badge::make('Offline', 'secondary') ?></td>
                                <td>
                                    <form method="post" action="<?= url('/ativos/acesso-remoto/vincular') ?>" class="d-flex gap-2">
                                        <input type="hidden" name="mesh_device_id" value="<?= htmlspecialchars($deviceId) ?>">
                                        <select name="ativo_id" class="form-select form-select-sm">
                                            <option value="">-- Nenhum --</option>
                                            <?php foreach ($ativos as $a): ?>
                                                <option value="<?= (int)$a['id'] ?>" <?= ($vinculado && (int)$vinculado['id'] === (int)$a['id']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($a['codigo_patrimonio'] . ' - ' . $a['nome']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-outline-primary">Salvar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<script>
(function () {
    const botao = document.getElementById('botaoInstalar');
    if (!botao) return;

    botao.addEventListener('click', async function () {
        botao.disabled = true;
        botao.innerHTML = '<i class="bi bi-hourglass-split"></i> Instalando (pode levar um minuto)...';

        try {
            const res = await fetch(<?= json_encode(url('/ativos/acesso-remoto/instalar')) ?>, { method: 'POST' });
            const resultado = await res.json();
            alert(resultado.message || (resultado.success ? 'Instalado.' : 'Falha ao instalar.'));
            if (resultado.success) location.reload();
        } catch (e) {
            alert('Erro ao comunicar com o servidor.');
        } finally {
            botao.disabled = false;
            botao.innerHTML = '<i class="bi bi-download"></i> Instalar';
        }
    });
})();

(function () {
    const botao = document.getElementById('botaoLiberarPorta');
    if (!botao) return;

    botao.addEventListener('click', async function () {
        const confirmado = confirm(
            'Criar e aplicar uma regra no Firewall liberando a porta <?= (int)$porta ?>/tcp (entrada) ' +
            'pra qualquer origem?\n\nÉ só isso -- não mexe em mais nada do Firewall.'
        );

        if (!confirmado) return;

        botao.disabled = true;
        botao.innerHTML = '<i class="bi bi-hourglass-split"></i> Aplicando...';

        try {
            const res = await fetch(<?= json_encode(url('/ativos/acesso-remoto/liberar-porta')) ?>, { method: 'POST' });
            const resultado = await res.json();
            alert(resultado.message || (resultado.success ? 'Porta liberada.' : 'Falha ao liberar a porta.'));
            if (resultado.success) location.reload();
        } catch (e) {
            alert('Erro ao comunicar com o servidor.');
        } finally {
            botao.disabled = false;
            botao.innerHTML = '<i class="bi bi-unlock"></i> Liberar no Firewall';
        }
    });
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Ativos - Acesso Remoto';

require __DIR__ . '/../layouts/main.php';

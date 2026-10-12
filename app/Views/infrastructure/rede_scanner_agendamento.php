<?php
/**
 * Card "Varredura automática" do IP Scanner -- incluído por rede_scanner.php.
 * Espera $agenda, $agendaProximas, $agendaDescricao e $faixaSugerida.
 * A prévia das próximas execuções é calculada aqui no navegador com as
 * MESMAS regras de IpScannerAgendamentoService::horariosDoDia() (dia do mês
 * inexistente vira o último dia), pra responder na hora a cada clique.
 */
$faixasAgenda = !empty($agenda['faixas']) ? $agenda['faixas'] : array_filter([$faixaSugerida ?? '']);
?>
<style>
.ipauto { border-radius:16px; color:#e6edf6; position:relative; overflow:hidden;
    background:linear-gradient(140deg,#0a1220 0%,#0d1f38 55%,#10294d 100%);
    box-shadow:0 10px 30px rgba(8,20,40,.25); }
.ipauto::before { content:''; position:absolute; inset:0; pointer-events:none;
    background:
        radial-gradient(circle at 92% 0%, rgba(62,168,255,.22), transparent 45%),
        linear-gradient(rgba(255,255,255,.035) 1px, transparent 1px) 0 0/100% 26px; }
.ipauto > * { position:relative; }
.ipauto-head { display:flex; align-items:center; gap:14px; padding:20px 22px 14px; flex-wrap:wrap; }
.ipauto-icone { width:46px; height:46px; border-radius:12px; display:flex; align-items:center; justify-content:center;
    font-size:1.35rem; color:#7cc4ff; background:rgba(62,168,255,.12); border:1px solid rgba(62,168,255,.35);
    box-shadow:0 0 18px rgba(62,168,255,.25) inset; }
.ipauto-titulo { font-weight:600; font-size:1.05rem; }
.ipauto-sub { color:#93a4ba; font-size:.82rem; }

/* interruptor grande */
.ipauto-switch { margin-left:auto; display:flex; align-items:center; gap:10px; cursor:pointer; user-select:none; }
.ipauto-switch input { display:none; }
.ipauto-switch .trilho { width:54px; height:28px; border-radius:14px; background:#25344a; border:1px solid #33455f; position:relative; transition:.2s; }
.ipauto-switch .trilho::after { content:''; position:absolute; top:3px; left:3px; width:20px; height:20px; border-radius:50%; background:#8b9bb0; transition:.2s; }
.ipauto-switch input:checked + .trilho { background:linear-gradient(90deg,#1a7cf0,#3ea8ff); border-color:#3ea8ff; box-shadow:0 0 14px rgba(62,168,255,.45); }
.ipauto-switch input:checked + .trilho::after { left:29px; background:#fff; }
.ipauto-switch .rotulo { font-size:.85rem; font-weight:600; min-width:62px; }

.ipauto-status { margin:0 22px; padding:9px 14px; border-radius:10px; font-size:.83rem; display:flex; align-items:center; gap:10px; flex-wrap:wrap;
    background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.07); }
.ipauto-pulso { width:9px; height:9px; border-radius:50%; background:#5b6b80; flex-shrink:0; }
.ipauto.ligado .ipauto-pulso { background:#3ddc84; box-shadow:0 0 0 0 rgba(61,220,132,.6); animation:ipauto-pulso 1.8s infinite; }
@keyframes ipauto-pulso { 70% { box-shadow:0 0 0 9px rgba(61,220,132,0); } 100% { box-shadow:0 0 0 0 rgba(61,220,132,0); } }
@media (prefers-reduced-motion: reduce) { .ipauto.ligado .ipauto-pulso { animation:none; } }

.ipauto-corpo { padding:18px 22px 22px; display:grid; grid-template-columns:minmax(0,1fr) 270px; gap:22px; transition:opacity .2s; }
.ipauto:not(.ligado) .ipauto-corpo { opacity:.55; }
@media (max-width: 991px) { .ipauto-corpo { grid-template-columns:1fr; } }

.ipauto-rotulo { font-size:.7rem; text-transform:uppercase; letter-spacing:.08em; color:#7f93ac; margin-bottom:8px; font-weight:600; }
.ipauto-bloco + .ipauto-bloco { margin-top:18px; }

.ipauto-input { background:rgba(255,255,255,.05); border:1px solid #2c3e57; color:#e6edf6; border-radius:9px; padding:7px 11px; font-family:var(--bs-font-monospace); font-size:.88rem; width:100%; }
.ipauto-input:focus { outline:0; border-color:#3ea8ff; box-shadow:0 0 0 3px rgba(62,168,255,.2); }
.ipauto-input::placeholder { color:#5f7390; }

/* modos */
.ipauto-modos { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; }
@media (max-width: 575px) { .ipauto-modos { grid-template-columns:repeat(2,minmax(0,1fr)); } }
.ipauto-modo { border:1px solid #2c3e57; background:rgba(255,255,255,.03); border-radius:12px; padding:12px; text-align:left; color:#c9d5e3; transition:.15s; cursor:pointer; }
.ipauto-modo i { font-size:1.2rem; color:#7f93ac; display:block; margin-bottom:6px; transition:.15s; }
.ipauto-modo b { display:block; font-size:.9rem; }
.ipauto-modo small { color:#7f93ac; font-size:.72rem; }
.ipauto-modo:hover { border-color:#3e5c82; background:rgba(62,168,255,.06); }
.ipauto-modo.ativo { border-color:#3ea8ff; background:rgba(62,168,255,.12); box-shadow:0 0 0 1px #3ea8ff inset, 0 0 18px rgba(62,168,255,.18); color:#fff; }
.ipauto-modo.ativo i { color:#7cc4ff; }

/* dias da semana */
.ipauto-semana { display:flex; gap:8px; flex-wrap:wrap; }
.ipauto-dia { width:46px; height:46px; border-radius:50%; border:1px solid #2c3e57; background:rgba(255,255,255,.03); color:#c9d5e3; font-weight:600; font-size:.8rem; cursor:pointer; transition:.15s; }
.ipauto-dia:hover { border-color:#3e5c82; }
.ipauto-dia.ativo { background:linear-gradient(135deg,#1a7cf0,#3ea8ff); border-color:#3ea8ff; color:#fff; box-shadow:0 0 12px rgba(62,168,255,.4); }

/* calendário do mês */
.ipauto-cal { display:grid; grid-template-columns:repeat(7,minmax(0,40px)); gap:6px; }
.ipauto-cal button { height:36px; border-radius:9px; border:1px solid #2c3e57; background:rgba(255,255,255,.03); color:#c9d5e3; font-size:.82rem; font-variant-numeric:tabular-nums; cursor:pointer; transition:.12s; }
.ipauto-cal button:hover { border-color:#3e5c82; }
.ipauto-cal button.ativo { background:linear-gradient(135deg,#1a7cf0,#3ea8ff); border-color:#3ea8ff; color:#fff; box-shadow:0 0 10px rgba(62,168,255,.35); }
.ipauto-atalhos { display:flex; gap:6px; flex-wrap:wrap; margin-top:10px; }
.ipauto-atalho { border:1px solid #2c3e57; background:transparent; color:#9fb2c8; font-size:.72rem; border-radius:20px; padding:3px 10px; cursor:pointer; }
.ipauto-atalho:hover { color:#fff; border-color:#3ea8ff; }

.ipauto-select { background:rgba(255,255,255,.05); border:1px solid #2c3e57; color:#e6edf6; border-radius:9px; padding:6px 10px; font-size:.95rem; font-weight:600; }
.ipauto-select option { color:#212529; }

/* horários */
.ipauto-horas { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
.ipauto-hora { display:inline-flex; align-items:center; gap:6px; padding:6px 8px 6px 12px; border-radius:20px; font-family:var(--bs-font-monospace); font-weight:600;
    background:rgba(62,168,255,.14); border:1px solid rgba(62,168,255,.45); color:#cfe7ff; }
.ipauto-hora button { border:0; background:transparent; color:#9fc9f0; padding:0 2px; line-height:1; }
.ipauto-hora button:hover { color:#fff; }
.ipauto-hora-nova { display:inline-flex; gap:6px; align-items:center; }
.ipauto-hora-nova input { width:110px; color-scheme:dark; }
.ipauto-btn-mini { border:1px dashed #3e5c82; background:transparent; color:#7cc4ff; border-radius:20px; padding:5px 12px; font-size:.8rem; }
.ipauto-btn-mini:hover { border-style:solid; background:rgba(62,168,255,.1); }

/* coluna direita */
.ipauto-lado { border-left:1px solid rgba(255,255,255,.07); padding-left:22px; }
@media (max-width: 991px) { .ipauto-lado { border-left:0; padding-left:0; border-top:1px solid rgba(255,255,255,.07); padding-top:18px; } }
.ipauto-linha { position:relative; padding-left:20px; }
.ipauto-linha::before { content:''; position:absolute; left:4px; top:6px; bottom:6px; width:1px; background:linear-gradient(#3ea8ff,rgba(62,168,255,0)); }
.ipauto-prox { position:relative; padding:5px 0; font-size:.84rem; }
.ipauto-prox::before { content:''; position:absolute; left:-20px; top:11px; width:9px; height:9px; border-radius:50%; background:#0d1f38; border:2px solid #3ea8ff; }
.ipauto-prox:first-child::before { background:#3ea8ff; box-shadow:0 0 10px #3ea8ff; }
.ipauto-prox .quando { color:#fff; font-weight:600; }
.ipauto-prox .rel { color:#7f93ac; font-size:.74rem; }
.ipauto-ultimo { margin-top:16px; font-size:.8rem; color:#9fb2c8; background:rgba(255,255,255,.04); border-radius:10px; padding:10px 12px; }

.ipauto-rodape { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; padding:14px 22px; border-top:1px solid rgba(255,255,255,.07); background:rgba(0,0,0,.12); }
.ipauto-salvar { background:linear-gradient(90deg,#1a7cf0,#3ea8ff); border:0; color:#fff; font-weight:600; border-radius:10px; padding:9px 22px; box-shadow:0 6px 18px rgba(26,124,240,.35); }
.ipauto-salvar:hover { filter:brightness(1.08); }
.ipauto-salvar:disabled { opacity:.6; }
.ipauto-msg { font-size:.82rem; }
.ipauto-msg.ok { color:#3ddc84; } .ipauto-msg.erro { color:#ff7b7b; }
</style>

<div class="ipauto mb-4 <?= !empty($agenda['ativo']) ? 'ligado' : '' ?>" id="ipauto">
    <div class="ipauto-head">
        <div class="ipauto-icone"><i class="bi bi-clock-history"></i></div>
        <div>
            <div class="ipauto-titulo">Varredura automática</div>
            <div class="ipauto-sub">O servidor varre a rede sozinho nos horários escolhidos -- histórico, "novos/sumiram" e as portas dos switches sempre em dia.</div>
        </div>
        <label class="ipauto-switch">
            <input type="checkbox" id="ipautoAtivo" <?= !empty($agenda['ativo']) ? 'checked' : '' ?>>
            <span class="trilho"></span>
            <span class="rotulo" id="ipautoAtivoRotulo"><?= !empty($agenda['ativo']) ? 'Ligada' : 'Desligada' ?></span>
        </label>
    </div>

    <div class="ipauto-status">
        <span class="ipauto-pulso"></span>
        <span id="ipautoResumo"></span>
    </div>

    <div class="ipauto-corpo">
        <div>
            <div class="ipauto-bloco">
                <div class="ipauto-rotulo">Faixas de IP</div>
                <input type="text" class="ipauto-input" id="ipautoFaixas" value="<?= htmlspecialchars(implode(', ', $faixasAgenda)) ?>" placeholder="ex: 192.168.0.0/24, 192.168.10.0/24">
            </div>

            <div class="ipauto-bloco">
                <div class="ipauto-rotulo">Frequência</div>
                <div class="ipauto-modos" id="ipautoModos">
                    <button type="button" class="ipauto-modo" data-modo="diario"><i class="bi bi-sun"></i><b>Diário</b><small>Todo dia</small></button>
                    <button type="button" class="ipauto-modo" data-modo="semanal"><i class="bi bi-calendar-week"></i><b>Semanal</b><small>Dias da semana</small></button>
                    <button type="button" class="ipauto-modo" data-modo="mensal"><i class="bi bi-calendar-event"></i><b>Mensal</b><small>Um dia do mês</small></button>
                    <button type="button" class="ipauto-modo" data-modo="personalizado"><i class="bi bi-calendar3"></i><b>Personalizado</b><small>Vários dias no calendário</small></button>
                </div>
            </div>

            <div class="ipauto-bloco" data-so="semanal">
                <div class="ipauto-rotulo">Dias da semana</div>
                <div class="ipauto-semana" id="ipautoSemana"></div>
            </div>

            <div class="ipauto-bloco" data-so="mensal">
                <div class="ipauto-rotulo">Dia do mês</div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span>Todo dia</span>
                    <select class="ipauto-select" id="ipautoDiaMensal"></select>
                    <span>de cada mês</span>
                </div>
                <div class="ipauto-sub mt-2">Se o mês não tiver esse dia (ex.: 31 em abril), roda no último dia do mês.</div>
            </div>

            <div class="ipauto-bloco" data-so="personalizado">
                <div class="ipauto-rotulo">Dias do mês <span class="text-lowercase" style="letter-spacing:0">(clique para marcar)</span></div>
                <div class="ipauto-cal" id="ipautoCal"></div>
                <div class="ipauto-atalhos">
                    <button type="button" class="ipauto-atalho" data-dias="1,15">Dia 1 e 15</button>
                    <button type="button" class="ipauto-atalho" data-dias="1,8,15,22">A cada ~semana</button>
                    <button type="button" class="ipauto-atalho" data-dias="31">Último dia</button>
                    <button type="button" class="ipauto-atalho" data-dias="">Limpar</button>
                </div>
            </div>

            <div class="ipauto-bloco">
                <div class="ipauto-rotulo">Horários</div>
                <div class="ipauto-horas" id="ipautoHoras"></div>
                <div class="ipauto-atalhos">
                    <button type="button" class="ipauto-atalho" data-hora="02:00"><i class="bi bi-moon-stars"></i> Madrugada 02:00</button>
                    <button type="button" class="ipauto-atalho" data-hora="08:30"><i class="bi bi-sunrise"></i> Início do expediente 08:30</button>
                    <button type="button" class="ipauto-atalho" data-hora="12:30"><i class="bi bi-cup-hot"></i> Almoço 12:30</button>
                    <button type="button" class="ipauto-atalho" data-hora="17:30"><i class="bi bi-sunset"></i> Fim do expediente 17:30</button>
                </div>
                <div class="ipauto-sub mt-2">Dica: um horário com as máquinas ligadas (ex.: 08:30) encontra mais dispositivos que a madrugada.</div>
            </div>
        </div>

        <div class="ipauto-lado">
            <div class="ipauto-rotulo">Próximas execuções</div>
            <div class="ipauto-linha" id="ipautoProximas"></div>
            <?php if (!empty($agenda['ultimo_resultado'])): ?>
                <?php $ultimo = $agenda['ultimo_resultado']; ?>
                <div class="ipauto-ultimo">
                    <div class="ipauto-rotulo mb-1">Última automática</div>
                    <i class="bi <?= !empty($ultimo['success']) ? 'bi-check-circle text-success' : 'bi-x-circle text-danger' ?>"></i>
                    <?= htmlspecialchars(date('d/m H:i', strtotime($ultimo['em']))) ?> --
                    <?php if (!empty($ultimo['success'])): ?>
                        <?= (int)$ultimo['total'] ?> dispositivo(s)<?= isset($ultimo['novos']) ? ', ' . (int)$ultimo['novos'] . ' novo(s), ' . (int)$ultimo['sumiram'] . ' sumiram' : '' ?>
                        <?php
                            $mudancaUltima = null;
                            foreach ($mudancas ?? [] as $m) {
                                if ((int)$m['id'] === (int)($ultimo['execucao_id'] ?? 0)) {
                                    $mudancaUltima = $m;
                                }
                            }
                        ?>
                        <?php if ($mudancaUltima): ?>
                            <div class="mt-2">
                                <?php foreach (array_slice($mudancaUltima['novos'], 0, 5) as $h): ?>
                                    <div class="font-monospace small" style="color:#3ddc84">+ <?= htmlspecialchars($h['ip']) ?> <span style="color:#9fb2c8"><?= htmlspecialchars($h['ativo']['nome'] ?? (($h['hostname'] ?? '') ?: ($h['vendor'] ?? ''))) ?></span></div>
                                <?php endforeach; ?>
                                <?php foreach (array_slice($mudancaUltima['sumiram'], 0, 5) as $h): ?>
                                    <div class="font-monospace small" style="color:#ff7b7b">− <?= htmlspecialchars($h['ip']) ?> <span style="color:#9fb2c8"><?= htmlspecialchars($h['ativo']['nome'] ?? (($h['hostname'] ?? '') ?: ($h['vendor'] ?? ''))) ?></span></div>
                                <?php endforeach; ?>
                                <a href="#mudancasRede" class="small" style="color:#7cc4ff">ver todas as mudanças</a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <?= htmlspecialchars($ultimo['mensagem'] ?? 'falhou') ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="ipauto-rodape">
        <span class="ipauto-msg" id="ipautoMsg"></span>
        <button type="button" class="ipauto-salvar" id="ipautoSalvar"><i class="bi bi-check2-circle me-1"></i> Salvar agenda</button>
    </div>
</div>

<script>
(function () {
    const URL_SALVAR = <?= json_encode(url('/infraestrutura/rede/scanner/agendamento')) ?>;
    const salvo = <?= json_encode([
        'ativo' => !empty($agenda['ativo']),
        'modo' => $agenda['modo'],
        'horarios' => $agenda['horarios'],
        'dias_semana' => $agenda['dias_semana'],
        'dias_mes' => $agenda['dias_mes'],
    ]) ?>;

    const estado = {
        ativo: salvo.ativo,
        modo: salvo.modo,
        horarios: [...salvo.horarios],
        diasSemana: new Set(salvo.dias_semana),
        diaMensal: salvo.dias_mes[0] || 1,
        diasMes: new Set(salvo.modo === 'personalizado' ? salvo.dias_mes : [1, 15]),
    };

    const SEMANA_CURTA = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
    const SEMANA_LETRA = ['D', 'S', 'T', 'Q', 'Q', 'S', 'S'];
    const el = id => document.getElementById(id);
    const painel = el('ipauto');

    // ---------- montagem dos controles ----------
    el('ipautoSemana').innerHTML = SEMANA_LETRA.map((l, i) => `<button type="button" class="ipauto-dia" data-dia="${i}" title="${SEMANA_CURTA[i]}">${l}</button>`).join('');
    el('ipautoCal').innerHTML = Array.from({ length: 31 }, (_, i) => `<button type="button" data-dia="${i + 1}">${i + 1}</button>`).join('');
    el('ipautoDiaMensal').innerHTML = Array.from({ length: 31 }, (_, i) => `<option value="${i + 1}">${i + 1}</option>`).join('');

    document.querySelectorAll('.ipauto-modo').forEach(b => b.addEventListener('click', () => { estado.modo = b.dataset.modo; render(); }));
    el('ipautoSemana').addEventListener('click', e => {
        const b = e.target.closest('[data-dia]'); if (!b) return;
        const d = +b.dataset.dia; estado.diasSemana.has(d) ? estado.diasSemana.delete(d) : estado.diasSemana.add(d); render();
    });
    el('ipautoCal').addEventListener('click', e => {
        const b = e.target.closest('[data-dia]'); if (!b) return;
        const d = +b.dataset.dia; estado.diasMes.has(d) ? estado.diasMes.delete(d) : estado.diasMes.add(d); render();
    });
    document.querySelectorAll('[data-so="personalizado"] .ipauto-atalho').forEach(b => b.addEventListener('click', () => {
        estado.diasMes = new Set(b.dataset.dias ? b.dataset.dias.split(',').map(Number) : []); render();
    }));
    el('ipautoDiaMensal').addEventListener('change', e => { estado.diaMensal = +e.target.value; render(); });
    document.querySelectorAll('[data-hora]').forEach(b => b.addEventListener('click', () => adicionarHora(b.dataset.hora)));
    el('ipautoAtivo').addEventListener('change', e => { estado.ativo = e.target.checked; render(); });

    function adicionarHora(h) {
        if (!/^([01]\d|2[0-3]):[0-5]\d$/.test(h)) return;
        if (!estado.horarios.includes(h) && estado.horarios.length < 8) estado.horarios.push(h);
        estado.horarios.sort();
        render();
    }

    // ---------- regras (iguais às do PHP) ----------
    function diasMesEfetivos() {
        return estado.modo === 'mensal' ? [estado.diaMensal] : [...estado.diasMes];
    }
    function horariosDoDia(dia) {
        const ultimo = new Date(dia.getFullYear(), dia.getMonth() + 1, 0).getDate();
        let vale = true;
        if (estado.modo === 'semanal') vale = estado.diasSemana.has(dia.getDay());
        if (estado.modo === 'mensal' || estado.modo === 'personalizado') vale = diasMesEfetivos().some(d => Math.min(d, ultimo) === dia.getDate());
        if (!vale) return [];
        return estado.horarios.map(h => new Date(dia.getFullYear(), dia.getMonth(), dia.getDate(), +h.slice(0, 2), +h.slice(3, 5)));
    }
    function proximas(qtd) {
        const agora = new Date(), lista = [];
        for (let i = 0; i < 400 && lista.length < qtd; i++) {
            const dia = new Date(agora.getFullYear(), agora.getMonth(), agora.getDate() + i);
            horariosDoDia(dia).forEach(q => { if (q > agora && lista.length < qtd) lista.push(q); });
        }
        return lista;
    }
    function juntar(itens) {
        return itens.length <= 1 ? (itens[0] ?? '') : itens.slice(0, -1).join(', ') + ' e ' + itens[itens.length - 1];
    }
    function descrever() {
        const horas = juntar(estado.horarios);
        if (estado.modo === 'semanal') return 'Toda ' + juntar([...estado.diasSemana].sort().map(d => SEMANA_CURTA[d])) + ' às ' + horas;
        if (estado.modo === 'mensal') return 'Todo dia ' + estado.diaMensal + ' do mês às ' + horas;
        if (estado.modo === 'personalizado') return 'Dias ' + juntar([...estado.diasMes].sort((a, b) => a - b)) + ' de cada mês às ' + horas;
        return 'Diário às ' + horas;
    }
    function relativo(d) {
        const min = Math.round((d - new Date()) / 60000);
        if (min < 60) return 'em ' + min + ' min';
        const h = Math.round(min / 60);
        if (h < 48) return 'em ' + h + 'h';
        return 'em ' + Math.round(h / 24) + ' dias';
    }
    function formatar(d) {
        const hoje = new Date(); const amanha = new Date(hoje.getFullYear(), hoje.getMonth(), hoje.getDate() + 1);
        const hora = d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        if (d.toDateString() === hoje.toDateString()) return 'Hoje, ' + hora;
        if (d.toDateString() === amanha.toDateString()) return 'Amanhã, ' + hora;
        return SEMANA_CURTA[d.getDay()] + ' ' + d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' }) + ', ' + hora;
    }

    // ---------- render ----------
    function render() {
        painel.classList.toggle('ligado', estado.ativo);
        el('ipautoAtivoRotulo').textContent = estado.ativo ? 'Ligada' : 'Desligada';
        document.querySelectorAll('.ipauto-modo').forEach(b => b.classList.toggle('ativo', b.dataset.modo === estado.modo));
        document.querySelectorAll('[data-so]').forEach(b => b.classList.toggle('d-none', b.dataset.so !== estado.modo));
        document.querySelectorAll('#ipautoSemana [data-dia]').forEach(b => b.classList.toggle('ativo', estado.diasSemana.has(+b.dataset.dia)));
        document.querySelectorAll('#ipautoCal [data-dia]').forEach(b => b.classList.toggle('ativo', estado.diasMes.has(+b.dataset.dia)));
        el('ipautoDiaMensal').value = String(estado.diaMensal);

        el('ipautoHoras').innerHTML = estado.horarios.map(h => `<span class="ipauto-hora">${h}<button type="button" data-remover="${h}" title="Remover"><i class="bi bi-x-lg"></i></button></span>`).join('')
            + `<span class="ipauto-hora-nova"><input type="time" class="ipauto-input" id="ipautoHoraNova" step="300"><button type="button" class="ipauto-btn-mini" id="ipautoHoraAdd"><i class="bi bi-plus-lg"></i> Horário</button></span>`;
        el('ipautoHoras').querySelectorAll('[data-remover]').forEach(b => b.addEventListener('click', () => {
            estado.horarios = estado.horarios.filter(h => h !== b.dataset.remover); render();
        }));
        el('ipautoHoraAdd').addEventListener('click', () => adicionarHora(el('ipautoHoraNova').value));
        el('ipautoHoraNova').addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); adicionarHora(e.target.value); } });

        const lista = estado.horarios.length ? proximas(5) : [];
        el('ipautoProximas').innerHTML = lista.length
            ? lista.map(d => `<div class="ipauto-prox"><div class="quando">${formatar(d)}</div><div class="rel">${relativo(d)}</div></div>`).join('')
            : '<div class="ipauto-sub">Escolha os dias e pelo menos um horário.</div>';

        el('ipautoResumo').innerHTML = estado.ativo
            ? `<strong>Ligada</strong> · ${descrever()}${lista.length ? ' · próxima: <strong>' + formatar(lista[0]) + '</strong>' : ''}`
            : 'Desligada -- as varreduras só acontecem quando alguém clica em "Iniciar varredura".';
    }

    // ---------- salvar ----------
    el('ipautoSalvar').addEventListener('click', async () => {
        const msg = el('ipautoMsg'), botao = el('ipautoSalvar');
        msg.className = 'ipauto-msg'; msg.textContent = 'Salvando...'; botao.disabled = true;
        try {
            const res = await fetch(URL_SALVAR, {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    ativo: estado.ativo,
                    modo: estado.modo,
                    faixas: el('ipautoFaixas').value.split(/[\s,;]+/).filter(Boolean),
                    horarios: estado.horarios,
                    dias_semana: [...estado.diasSemana],
                    dias_mes: diasMesEfetivos(),
                }),
            });
            const r = await res.json();
            msg.className = 'ipauto-msg ' + (r.success ? 'ok' : 'erro');
            msg.innerHTML = r.success ? '<i class="bi bi-check2"></i> ' + r.message + (r.descricao && estado.ativo ? ' (' + r.descricao + ')' : '') : r.message;
        } catch (e) {
            msg.className = 'ipauto-msg erro'; msg.textContent = 'Erro ao comunicar com o servidor.';
        } finally {
            botao.disabled = false;
        }
    });

    render();
})();
</script>

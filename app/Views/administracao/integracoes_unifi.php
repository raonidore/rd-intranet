<?php

use App\Components\Alert;
use App\Components\Badge;

ob_start();
?>

<div class="mb-4">
    <h4 class="mb-1"><i class="bi bi-wifi me-1"></i> UniFi Network Controller</h4>
    <small class="text-muted">
        <a href="<?= url('/administracao/integracoes') ?>"><i class="bi bi-arrow-left"></i> Integrações</a>
    </small>
</div>

<?= Alert::flash() ?>

<div class="card border-0 shadow-sm mb-3" style="max-width:900px">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <strong><i class="bi bi-hdd-network"></i> Conexão com o Controller</strong>
            <?= $configurado ? Badge::make('Configurado', 'success') : Badge::make('Não configurado', 'secondary') ?>
        </div>

        <p class="text-muted small mb-3">
            Uma chave só, pro Controller inteiro -- vale pra todo Access Point, Switch e Roteador/Gateway
            UniFi cadastrado em Ativos (não é por equipamento, como no DVR/NVR Intelbras).
        </p>

        <form method="post" action="<?= url('/administracao/integracoes/unifi/salvar') ?>" class="row g-3">
            <div class="col-12">
                <label class="form-label">URL do Controller</label>
                <input type="text" name="url" class="form-control font-monospace" required
                       value="<?= htmlspecialchars($urlAtual) ?>" placeholder="https://192.168.10.1">
            </div>
            <div class="col-12">
                <label class="form-label">API Key</label>
                <input type="password" name="api_key" class="form-control font-monospace"
                       placeholder="<?= $configurado ? '••••••••  (deixe em branco pra manter)' : 'chave gerada em Configurações > Integrações, no Controller' ?>">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Salvar</button>
                <?php if ($configurado): ?>
                    <button type="button" class="btn btn-outline-secondary" id="botaoTestarConexaoUnifi"><i class="bi bi-plug"></i> Testar conexão</button>
                    <button type="button" class="btn btn-outline-danger" id="botaoRemoverConfigUnifi"><i class="bi bi-trash"></i> Remover configuração</button>
                <?php endif; ?>
            </div>
        </form>
        <form method="post" action="<?= url('/administracao/integracoes/unifi/remover') ?>" id="formRemoverConfigUnifi" class="d-none"></form>

        <div class="alert alert-success mt-3 d-none" id="unifiTesteOk"></div>
        <div class="alert alert-danger mt-3 d-none" id="unifiTesteErro"></div>

        <?php if ($configurado && $siteId): ?>
            <p class="text-muted small mt-3 mb-0"><i class="bi bi-info-circle"></i> Site identificado: <code><?= htmlspecialchars($siteId) ?></code></p>
        <?php elseif ($configurado): ?>
            <p class="text-muted small mt-3 mb-0"><i class="bi bi-exclamation-triangle"></i> Site ainda não identificado -- clique em "Testar conexão".</p>
        <?php endif; ?>
    </div>
</div>

<div class="card border-0 shadow-sm unifi-doc-card" style="max-width:960px">
    <div class="card-body">
        <strong><i class="bi bi-rocket-takeoff"></i> Como configurar</strong>
        <div class="unifi-steps mt-3">
            <div class="unifi-step">
                <div class="unifi-step-num">1</div>
                <div class="unifi-step-body">
                    <div class="unifi-step-title">Atualize o servidor</div>
                    <div class="unifi-step-text">Só na primeira vez, via SSH -- a migration cria os tipos de ativo "Access Point", "Switch" e "Roteador/Gateway".</div>
                    <code class="unifi-code">cd /var/www/rd.intranet &amp;&amp; git pull --ff-only &amp;&amp; php rd migrate</code>
                </div>
            </div>
            <div class="unifi-step">
                <div class="unifi-step-num">2</div>
                <div class="unifi-step-body">
                    <div class="unifi-step-title">Gere a API Key no Controller</div>
                    <div class="unifi-step-text">No próprio UniFi Controller (Cloud Gateway/Dream Machine), abra <strong>Configurações do Sistema &gt; Integração/Integrations</strong>, crie uma chave nova (ex: "RD.Intranet") e copie o valor mostrado -- ele não aparece de novo depois.</div>
                </div>
            </div>
            <div class="unifi-step">
                <div class="unifi-step-num">3</div>
                <div class="unifi-step-body">
                    <div class="unifi-step-title">Configure aqui em cima</div>
                    <div class="unifi-step-text">Cole a URL do Controller e a API Key, salve, e clique em "Testar conexão" -- identifica e grava o site sozinho.</div>
                </div>
            </div>
            <div class="unifi-step">
                <div class="unifi-step-num">4</div>
                <div class="unifi-step-body">
                    <div class="unifi-step-title">Cadastre o equipamento como Ativo</div>
                    <div class="unifi-step-text">Ativos &gt; Novo Ativo &gt; informe o IP de <strong>gerenciamento na LAN</strong> (não o IP da WAN, no caso de gateway) &gt; clique em "Detectar" -- identifica sozinho se é Access Point, Switch ou Roteador/Gateway, e preenche marca/modelo/firmware.</div>
                </div>
            </div>
            <div class="unifi-step">
                <div class="unifi-step-num">5</div>
                <div class="unifi-step-body">
                    <div class="unifi-step-title">Colete os dados completos</div>
                    <div class="unifi-step-text">Na ficha do ativo já salvo, "Detectar e coletar automaticamente". Pontos de acesso ganham a aba "Wi-Fi"; roteadores/gateways ganham "Rede/WAN", "Clientes" e "Firewall".</div>
                </div>
            </div>
            <div class="unifi-step unifi-step-last">
                <div class="unifi-step-num">6</div>
                <div class="unifi-step-body">
                    <div class="unifi-step-title">(Opcional) Ative a coleta periódica</div>
                    <div class="unifi-step-text">Dashboard de Ativos &gt; "Integrações de rede" &gt; "Ativar coleta" no card UniFi, pra não precisar clicar manualmente em cada equipamento.</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mt-3 unifi-doc-card" style="max-width:960px">
    <div class="card-body">
        <strong><i class="bi bi-tools"></i> Ferramentas disponíveis -- visão geral</strong>
        <p class="text-muted small mt-2 mb-3">
            Diferente do DVR/NVR Intelbras, o UniFi <strong>não abre chamado automático</strong> -- tudo aqui é
            visibilidade (o que o Controller sabe sobre a rede) e ações manuais (bloquear cliente, ligar/desligar
            regra de firewall). Detalhe completo de cada uma logo abaixo.
        </p>
        <div class="table-responsive">
            <table class="table table-sm unifi-tabela-ferramentas align-middle mb-0">
                <thead>
                    <tr>
                        <th>Ferramenta</th>
                        <th>Onde</th>
                        <th>Tipo de Ativo</th>
                        <th>Como é carregada</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><i class="bi bi-wifi text-primary"></i> Rádios e clientes Wi-Fi</td>
                        <td class="text-muted small">Aba "Wi-Fi"</td>
                        <td class="text-muted small">Access Point</td>
                        <td><?= Badge::make('Coleta periódica', 'secondary') ?></td>
                    </tr>
                    <tr>
                        <td><i class="bi bi-slash-circle text-danger"></i> Bloquear / desbloquear / desconectar cliente</td>
                        <td class="text-muted small">Aba "Wi-Fi", por linha</td>
                        <td class="text-muted small">Access Point</td>
                        <td><?= Badge::make('Ação manual', 'danger') ?></td>
                    </tr>
                    <tr>
                        <td><i class="bi bi-diagram-3 text-primary"></i> Conexões WAN, failover/balanceamento, diagnóstico 24h</td>
                        <td class="text-muted small">Aba "Rede/WAN"</td>
                        <td class="text-muted small">Roteador/Gateway</td>
                        <td><?= Badge::make('Coleta periódica', 'secondary') ?> + <?= Badge::make('Ação manual', 'danger') ?></td>
                    </tr>
                    <tr>
                        <td><i class="bi bi-heart-pulse text-success"></i> Saúde da rede (WAN/Wi-Fi/LAN/VPN)</td>
                        <td class="text-muted small">Aba "Rede/WAN", topo</td>
                        <td class="text-muted small">Roteador/Gateway</td>
                        <td><?= Badge::make('Sob demanda', 'info') ?></td>
                    </tr>
                    <tr>
                        <td><i class="bi bi-router text-secondary"></i> Redes Wi-Fi configuradas, rotas estáticas, info do Controller</td>
                        <td class="text-muted small">Aba "Rede/WAN", rodapé</td>
                        <td class="text-muted small">Roteador/Gateway</td>
                        <td><?= Badge::make('Sob demanda', 'info') ?></td>
                    </tr>
                    <tr>
                        <td><i class="bi bi-hdd-network text-primary"></i> Clientes conectados na rede (site inteiro, ordenável)</td>
                        <td class="text-muted small">Aba "Clientes"</td>
                        <td class="text-muted small">Roteador/Gateway</td>
                        <td><?= Badge::make('Sob demanda', 'info') ?></td>
                    </tr>
                    <tr>
                        <td><i class="bi bi-broadcast-pin text-warning"></i> Redes Wi-Fi vizinhas (canal, sinal, filtro)</td>
                        <td class="text-muted small">Aba "Clientes", rodapé</td>
                        <td class="text-muted small">Roteador/Gateway</td>
                        <td><?= Badge::make('Sob demanda', 'info') ?></td>
                    </tr>
                    <tr>
                        <td><i class="bi bi-shield-lock text-danger"></i> Regras de firewall -- ver, ativar/desativar, criar, excluir</td>
                        <td class="text-muted small">Aba "Firewall"</td>
                        <td class="text-muted small">Roteador/Gateway</td>
                        <td><?= Badge::make('Sob demanda', 'info') ?> + <?= Badge::make('Ação manual', 'danger') ?></td>
                    </tr>
                    <tr>
                        <td><i class="bi bi-signpost-split text-secondary"></i> Redirecionamento de portas (NAT)</td>
                        <td class="text-muted small">Aba "Firewall", rodapé</td>
                        <td class="text-muted small">Roteador/Gateway</td>
                        <td><?= Badge::make('Sob demanda', 'info') ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mt-3 unifi-doc-card" style="max-width:960px">
    <div class="card-body">
        <strong><i class="bi bi-palette me-2"></i> Legenda dos ícones</strong>
        <p class="text-muted small mt-2 mb-3">Os botões abaixo usam exatamente as mesmas cores das telas reais -- bate o olho e já reconhece.</p>
        <div class="unifi-legenda">
            <div class="unifi-legenda-item">
                <button type="button" class="btn btn-sm btn-outline-primary" tabindex="-1"><i class="bi bi-eye"></i></button>
                <span>Ver detalhes do cliente Wi-Fi -- sinal, taxa, uptime, tráfego por app da sessão</span>
            </div>
            <div class="unifi-legenda-item">
                <button type="button" class="btn btn-sm btn-outline-secondary" tabindex="-1"><i class="bi bi-x-circle"></i></button>
                <span>Desconectar cliente agora -- não é bloqueio, ele pode reconectar sozinho em seguida</span>
            </div>
            <div class="unifi-legenda-item">
                <button type="button" class="btn btn-sm btn-outline-danger" tabindex="-1"><i class="bi bi-slash-circle"></i></button>
                <span>Bloquear cliente -- fica impedido de conectar até ser desbloqueado</span>
            </div>
            <div class="unifi-legenda-item">
                <button type="button" class="btn btn-sm btn-outline-success" tabindex="-1"><i class="bi bi-unlock"></i> Desbloquear</button>
                <span>Cliente já bloqueado -- clique pra liberar de novo</span>
            </div>
            <div class="unifi-legenda-item">
                <button type="button" class="btn btn-sm btn-dark" tabindex="-1"><i class="bi bi-signpost-split"></i> Failover</button>
                <span>Modo WAN <strong>atual</strong> do gateway (preto = ativo agora)</span>
            </div>
            <div class="unifi-legenda-item">
                <button type="button" class="btn btn-sm btn-outline-secondary" tabindex="-1"><i class="bi bi-distribute-horizontal"></i> Balanceamento de carga</button>
                <span>Modo WAN alternativo -- clique pra trocar (só aparece com 2+ conexões WAN configuradas)</span>
            </div>
            <div class="unifi-legenda-item">
                <button type="button" class="btn btn-sm btn-outline-primary" tabindex="-1"><i class="bi bi-speedometer2"></i> Avaliar internet agora</button>
                <span>Dispara um speedtest no gateway na hora, sem esperar a próxima coleta periódica</span>
            </div>
            <div class="unifi-legenda-item">
                <div class="form-check form-switch mb-0"><input type="checkbox" class="form-check-input" checked disabled tabindex="-1"></div>
                <span>Regra de firewall <strong class="text-success">ativa</strong> -- clique no interruptor pra desativar (muda o firewall de verdade)</span>
            </div>
            <div class="unifi-legenda-item">
                <button type="button" class="btn btn-sm btn-outline-primary" tabindex="-1"><i class="bi bi-plus-lg"></i> Nova regra</button>
                <span>Cria uma regra de firewall nova (zona a zona, com IP/porta opcionais)</span>
            </div>
            <div class="unifi-legenda-item">
                <button type="button" class="btn btn-sm btn-outline-danger" tabindex="-1"><i class="bi bi-trash"></i></button>
                <span>Excluir regra -- só aparece em regras <strong>personalizadas</strong> (criadas por alguém, não as automáticas do sistema)</span>
            </div>
            <div class="unifi-legenda-item">
                <button type="button" class="btn btn-sm btn-outline-secondary" tabindex="-1"><i class="bi bi-arrow-repeat"></i> Atualizar</button>
                <span>Consulta o Controller de novo agora, sem esperar a próxima vez que a aba for aberta</span>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mt-3 unifi-doc-card" style="max-width:960px">
    <div class="card-body">
        <strong><i class="bi bi-book"></i> Documentação técnica</strong>
        <p class="text-muted small mt-2 mb-3">Detalhe de cada aba, o que ela mostra, o que dá pra fazer, e como a integração conversa com o Controller por baixo dos panos.</p>

        <div class="accordion unifi-accordion" id="acordeaoDocUnifi">

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#docWifi">
                        <i class="bi bi-wifi text-primary me-2"></i> Wi-Fi -- rádios e clientes de um Access Point
                    </button>
                </h2>
                <div id="docWifi" class="accordion-collapse collapse" data-bs-parent="#acordeaoDocUnifi">
                    <div class="accordion-body">
                        <p class="text-muted small">
                            A cada coleta, o sistema traz canal/largura/padrão de cada rádio do AP, e a lista de
                            clientes conectados <strong>naquele AP específico</strong> -- nome, MAC, rede (SSID),
                            sinal, e quando conectou. O botão de olho (<i class="bi bi-eye"></i>) abre um modal com
                            o detalhe completo de um cliente: uptime da sessão, taxa de transmissão/recepção,
                            retentativas, e um gráfico de tráfego por aplicativo daquela sessão (DPI).
                        </p>
                        <ul class="small text-muted mb-0">
                            <li><strong>Desconectar</strong> força uma nova associação -- útil quando um dispositivo está "grudado" num AP distante com sinal ruim; ele reconecta sozinho, geralmente no AP mais próximo.</li>
                            <li><strong>Bloquear/Desbloquear</strong> é por site inteiro, não só naquele AP -- um cliente bloqueado fica impedido de conectar em <strong>qualquer</strong> Access Point do mesmo Controller.</li>
                            <li>Não existe bloqueio por tempo determinado nativo no Controller -- fica bloqueado até alguém desbloquear manualmente.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#docRedeWan">
                        <i class="bi bi-diagram-3 text-primary me-2"></i> Rede/WAN -- gateway e saúde da rede
                    </button>
                </h2>
                <div id="docRedeWan" class="accordion-collapse collapse" data-bs-parent="#acordeaoDocUnifi">
                    <div class="accordion-body">
                        <p class="text-muted small">
                            Só aparece pro tipo "Roteador/Gateway". Reúne tudo que diz respeito à internet e à rede
                            como um todo (não é por AP nem por cliente).
                        </p>
                        <ul class="small text-muted mb-0">
                            <li><strong>Saúde da rede</strong> (topo) -- status por subsistema (WAN/Wi-Fi/LAN/VPN), contagem de dispositivos conectados/desconectados/pendentes, CPU/memória do próprio gateway, provedor (ISP) identificado.</li>
                            <li><strong>Conexões WAN</strong> -- cada link de internet configurado, com IP público, download/upload contratado e prioridade. Com 2+ conexões, aparece o seletor <strong>Failover</strong> (uma WAN só ativa, a outra de reserva) ou <strong>Balanceamento de carga</strong> (as duas em uso ao mesmo tempo).</li>
                            <li><strong>Avaliar internet agora</strong> -- dispara um speedtest de verdade no próprio gateway, sem esperar o agendamento automático dele.</li>
                            <li><strong>Diagnóstico de 24h</strong> -- disponibilidade e latência média de cada WAN, medida pelo próprio gateway (ping/DNS contra alvos de referência).</li>
                            <li><strong>Redes Wi-Fi configuradas</strong> -- lista as redes cadastradas no Controller (nome, banda, segurança, se está ativa) -- não mostra senha.</li>
                            <li><strong>Rotas estáticas</strong> -- rotas de rede configuradas manualmente no gateway (ex: pra uma VPN site-a-site).</li>
                            <li><strong>Controller UniFi</strong> -- versão do software do Controller, hostname, uptime, e se tem atualização disponível.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#docClientes">
                        <i class="bi bi-hdd-network text-primary me-2"></i> Clientes -- rede inteira e redes vizinhas
                    </button>
                </h2>
                <div id="docClientes" class="accordion-collapse collapse" data-bs-parent="#acordeaoDocUnifi">
                    <div class="accordion-body">
                        <p class="text-muted small">
                            Também só pro "Roteador/Gateway" -- diferente da aba "Wi-Fi" (que é por AP), aqui é
                            <strong>todo</strong> dispositivo conectado ao site, com fio ou Wi-Fi, numa lista só,
                            ordenada por consumo de dados. Clique em qualquer cabeçalho de coluna pra reordenar.
                        </p>
                        <div class="unifi-callout unifi-callout-warning">
                            <i class="bi bi-exclamation-triangle"></i>
                            <div>
                                <strong>Redes Wi-Fi vizinhas</strong> mostra o que os próprios rádios dos APs
                                enxergam passivamente ao redor -- não é uma varredura ativa nem uma lista de ameaças.
                                Confirmado ao vivo: o Controller guarda <strong>histórico</strong> de avistamentos, não
                                um retrato de "agora" -- sem filtro, isso vira uma tabela de centenas de linhas
                                (chegou a 525 num teste real). O sistema já filtra pra última 1 hora e junta
                                avistamentos repetidos da mesma rede num só, e ainda traz um resumo de quais
                                <strong>canais</strong> estão mais concorridos (útil pra escolher um canal livre pro
                                seu próprio Wi-Fi -- em 2.4GHz, prefira sempre 1, 6 ou 11).
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#docFirewall">
                        <i class="bi bi-shield-lock text-danger me-2"></i> Firewall -- ver, ativar/desativar, criar e excluir regras
                    </button>
                </h2>
                <div id="docFirewall" class="accordion-collapse collapse" data-bs-parent="#acordeaoDocUnifi">
                    <div class="accordion-body">
                        <p class="text-muted small">
                            O firewall de gateways UniFi modernos é <strong>por zonas</strong> (Internal, External,
                            Gateway, Vpn, Hotspot, Dmz) -- cada regra diz o que fazer entre uma zona de origem e uma
                            de destino. Um Controller típico já vem com <strong>dezenas</strong> de regras
                            automáticas (a malha básica entre cada par de zonas) -- por isso, o interruptor <strong>"Mostrar
                            regras internas do sistema"</strong> vem desligado por padrão, mostrando só o que alguém
                            configurou de propósito (bloqueio de app, porta/serviço específico).
                        </p>
                        <div class="unifi-callout unifi-callout-danger">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <div>
                                <strong>Toda ação aqui muda o firewall de verdade, na hora.</strong> Ativar/desativar,
                                criar ou excluir uma regra tem efeito imediato no gateway -- uma regra errada pode
                                bloquear tráfego legítimo ou liberar algo que não devia. O sistema pede confirmação
                                antes de cada ação, mas a confirmação não avalia se a regra faz sentido -- confira os
                                valores (zona, IP, porta) com atenção antes de clicar.
                            </div>
                        </div>
                        <ul class="small text-muted mb-0">
                            <li><strong>Nova regra</strong> cobre o caso comum: nome, ação (permitir/bloquear), protocolo, zona de origem/destino, e IP/porta de destino opcionais. Não expõe agendamento fora de "sempre" nem estado de conexão avançado -- pra isso, o Controller direto.</li>
                            <li><strong>Excluir</strong> só aparece em regras personalizadas (não dá pra apagar a malha automática do sistema por aqui).</li>
                            <li><strong>Redirecionamento de portas</strong> (rodapé) é uma lista <strong>separada</strong> -- são regras de NAT (porta externa → IP/porta interna), um conceito diferente de regra de firewall, embora os dois apareçam na mesma aba por serem "regras de rede" no sentido amplo.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#docForaEscopo">
                        <i class="bi bi-slash-circle text-muted me-2"></i> O que NÃO temos (e por quê)
                    </button>
                </h2>
                <div id="docForaEscopo" class="accordion-collapse collapse" data-bs-parent="#acordeaoDocUnifi">
                    <div class="accordion-body">
                        <p class="text-muted small mb-2">Testado ao vivo contra um Controller real antes de desistir de cada um -- não é chute:</p>
                        <ul class="small text-muted mb-0">
                            <li><strong>Tráfego por aplicativo (DPI) fora do detalhe de um cliente</strong> -- a tela "Insights &gt; Atividade &gt; Aplicativo" do app oficial (ex: "QUIC 37%, WhatsApp 13%...") não foi possível reproduzir de forma agregada: o endpoint clássico de DPI responde vazio mesmo com a opção habilitada no Controller.</li>
                            <li><strong>Eventos de IPS/IDS</strong> (tentativa de invasão, bloqueio por geolocalização) -- 11 endpoints diferentes testados, todos retornaram "não encontrado". Ou o recurso de Threat Management não está habilitado/licenciado nesse Controller, ou o caminho de API não é público.</li>
                            <li><strong>Histórico de banda ao longo do tempo</strong> (gráfico por período) -- o mecanismo de relatório do Controller responde certinho, mas os contadores de banda da WAN vêm sempre zerados nesse equipamento, em qualquer intervalo testado (5min, hora, dia).</li>
                            <li><strong>Vouchers/Wi-Fi visitante e controle de porta PoE em switch</strong> -- endpoints existem e respondem, mas não foram implementados ainda por não terem sido pedidos -- avise se fizerem falta.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#docTecnicoUnifi">
                        <i class="bi bi-cpu me-2"></i> Como funciona por baixo dos panos
                    </button>
                </h2>
                <div id="docTecnicoUnifi" class="accordion-collapse collapse" data-bs-parent="#acordeaoDocUnifi">
                    <div class="accordion-body">
                        <p class="text-muted small mb-0">
                            Autenticação por <strong>API Key local</strong> (header <code>X-API-KEY</code>), gerada
                            no próprio Controller -- não usa a sessão de login com MFA, que exigiria um humano
                            digitando código a cada chamada. O Controller é local (mesma rede, certificado
                            autoassinado), por isso a verificação de certificado SSL fica desligada aqui, o mesmo
                            risco que o próprio app oficial da Ubiquiti assume nessa rede. A integração fala com
                            <strong>dois grupos de endpoint</strong> do Controller: os mais novos
                            (<code>/v2/api/site/...</code> -- firewall por zonas, clientes ativos) e os clássicos
                            (<code>/api/s/{site}/...</code> -- dispositivos, rádios, saúde da rede, redes vizinhas,
                            port forward) -- alguns dados só existem num, outros só no outro, dependendo de quando
                            aquele recurso foi adicionado ao UniFi OS. Cada endpoint novo foi confirmado ao vivo
                            contra um Controller real antes de entrar no sistema.
                        </p>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<style>
.unifi-doc-card .card-body { padding: 1.25rem 1.5rem; }

.unifi-steps { display: flex; flex-direction: column; gap: 0; }
.unifi-step { display: flex; gap: .9rem; position: relative; padding-bottom: 1.25rem; }
.unifi-step::before {
    content: ''; position: absolute; left: 13px; top: 30px; bottom: 0;
    width: 2px; background: linear-gradient(to bottom, #cfe2ff, #e9ecef);
}
.unifi-step-last::before { display: none; }
.unifi-step-num {
    flex: 0 0 auto; width: 28px; height: 28px; border-radius: 50%;
    background: #0d6efd; color: #fff; font-weight: 600; font-size: .8rem;
    display: flex; align-items: center; justify-content: center; z-index: 1;
}
.unifi-step-title { font-weight: 600; font-size: .9rem; }
.unifi-step-text { color: #6c757d; font-size: .82rem; margin-top: 2px; }
.unifi-code {
    display: block; margin-top: .5rem; padding: .5rem .75rem; border-radius: .375rem;
    background: #0d1117; color: #7ee787; font-size: .78rem; overflow-x: auto; white-space: pre;
}

.unifi-tabela-ferramentas th { font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: #6c757d; border-top: none; }
.unifi-tabela-ferramentas td { font-size: .85rem; }

.unifi-legenda { display: flex; flex-direction: column; gap: .65rem; }
.unifi-legenda-item { display: flex; align-items: center; gap: .75rem; }
.unifi-legenda-item > .btn, .unifi-legenda-item > .form-check { flex: 0 0 auto; pointer-events: none; }
.unifi-legenda-item > span { font-size: .85rem; color: #495057; }

.unifi-accordion .accordion-button {
    font-size: .88rem; font-weight: 600; background: #f8f9fa;
}
.unifi-accordion .accordion-button:not(.collapsed) {
    background: #eef4ff; color: #0d3b8c; box-shadow: none;
}
.unifi-accordion .accordion-button:focus { box-shadow: none; }
.unifi-accordion .accordion-item { border-color: #e9ecef; }

.unifi-callout {
    display: flex; gap: .6rem; padding: .75rem .9rem; border-radius: .5rem; font-size: .82rem; margin-top: .75rem;
}
.unifi-callout i { font-size: 1.1rem; flex: 0 0 auto; }
.unifi-callout-warning { background: #fff8e6; color: #664d03; border: 1px solid #ffe69c; }
.unifi-callout-warning i { color: #997404; }
.unifi-callout-danger { background: #fdeeee; color: #7a1f1f; border: 1px solid #f5c2c2; }
.unifi-callout-danger i { color: #b02a2a; }
</style>

<script>
(function () {
    const botaoTestar = document.getElementById('botaoTestarConexaoUnifi');
    const okBox = document.getElementById('unifiTesteOk');
    const erroBox = document.getElementById('unifiTesteErro');

    if (botaoTestar) {
        botaoTestar.addEventListener('click', async function () {
            okBox.classList.add('d-none');
            erroBox.classList.add('d-none');
            botaoTestar.disabled = true;

            try {
                const res = await fetch(<?= json_encode(url('/administracao/integracoes/unifi/testar')) ?>, { method: 'POST' });
                const resultado = await res.json();

                if (resultado.success) {
                    okBox.textContent = resultado.message;
                    okBox.classList.remove('d-none');
                    // O site identificado é gravado no servidor, mas o parágrafo "Site
                    // identificado: ..." abaixo é renderizado no carregamento da página --
                    // sem recarregar, ficava mostrando o aviso antigo mesmo com o teste
                    // já tendo dado certo.
                    setTimeout(() => location.reload(), 1200);
                } else {
                    erroBox.textContent = resultado.message;
                    erroBox.classList.remove('d-none');
                }
            } catch (e) {
                erroBox.textContent = 'Erro ao comunicar com o servidor.';
                erroBox.classList.remove('d-none');
            } finally {
                botaoTestar.disabled = false;
            }
        });
    }

    const botaoRemover = document.getElementById('botaoRemoverConfigUnifi');
    if (botaoRemover) {
        botaoRemover.addEventListener('click', function () {
            if (confirm('Remover a configuração do UniFi Controller? A coleta de dados dos equipamentos para de funcionar até ser configurada de novo.')) {
                document.getElementById('formRemoverConfigUnifi').submit();
            }
        });
    }
})();
</script>

<?php
$conteudo = ob_get_clean();
$titulo = 'Integrações - UniFi Network Controller';

require __DIR__ . '/../layouts/main.php';

<?php

ob_start();
?>

<div class="mb-4">
    <h4 class="mb-1"><i class="bi bi-book me-1"></i> Guia: Servidor DHCP + VLANs</h4>
    <small class="text-muted">
        <a href="<?= url('/infraestrutura/dhcp') ?>"><i class="bi bi-hdd-network-fill"></i> Servidor DHCP</a>
        &nbsp;·&nbsp;
        <a href="<?= url('/infraestrutura/vlan') ?>"><i class="bi bi-diagram-3-fill"></i> VLANs</a>
    </small>
</div>

<div class="card border-0 shadow-sm mb-3 guia-emergencia" style="max-width:960px">
    <div class="card-body">
        <strong><i class="bi bi-life-preserver"></i> "Meu roteador/gateway caiu, preciso disso funcionando AGORA"</strong>
        <p class="text-muted small mt-2 mb-3">
            Cenário real que originou este módulo: o equipamento que fazia roteamento entre VLANs e distribuía IP
            (ex: um gateway UniFi) parou de funcionar, e este servidor Linux precisa assumir as duas funções até o
            equipamento ser trocado. Roteiro direto, sem enrolação:
        </p>
        <ol class="small mb-0 ps-3">
            <li class="mb-2"><strong>Anote o que o equipamento antigo tinha</strong> -- pra cada VLAN: o ID (tag), o IP de gateway (geralmente o <code>.1</code> da faixa) e a faixa de IP que os dispositivos usavam. Sem isso, dá pra descobrir olhando a configuração de um PC que ainda está ligado (IP, máscara e gateway atuais) em cada setor/VLAN.</li>
            <li class="mb-2"><strong>Confirme que o switch já está com a porta em modo trunk</strong> pra este servidor, carregando as mesmas tags 802.1Q que o equipamento antigo usava -- ver aviso técnico mais abaixo. Sem isso, as VLANs criadas aqui não recebem tráfego nenhum.</li>
            <li class="mb-2">Crie cada VLAN em <a href="<?= url('/infraestrutura/vlan') ?>">Infraestrutura &gt; VLANs</a> (nome, interface física/trunk, ID, IP de gateway) e clique em <strong>Aplicar</strong>.</li>
            <li class="mb-2">Em <a href="<?= url('/infraestrutura/dhcp') ?>">Infraestrutura &gt; Servidor DHCP</a>: instale (se ainda não tiver), marque a interface de cada VLAN criada, cadastre uma <strong>rede (subnet)</strong> por VLAN com a mesma faixa que os dispositivos já usavam, e clique em <strong>Aplicar</strong>.</li>
            <li class="mb-2">Em <a href="<?= url('/infraestrutura/iptables') ?>">Infraestrutura &gt; Firewall &gt; Templates</a>, aplique um <strong>"Masquerade de Saída"</strong> apontando pra interface de internet (WAN) -- isso libera as VLANs pra acessar a internet e já habilita o roteamento entre elas (<code>ip_forward</code>) de brinde.</li>
            <li class="mb-0">Teste: peça pra alguém desconectar/reconectar a rede de um PC em cada VLAN (ou reiniciá-lo) e confirme que ele recebeu IP correto e navega.</li>
        </ol>
    </div>
</div>

<div class="card border-0 shadow-sm guia-doc-card" style="max-width:960px">
    <div class="card-body">
        <strong><i class="bi bi-rocket-takeoff"></i> Passo a passo detalhado</strong>
        <div class="guia-steps mt-3">
            <div class="guia-step">
                <div class="guia-step-num">1</div>
                <div class="guia-step-body">
                    <div class="guia-step-title">Configure a porta do switch como trunk (fora do RD Intranet)</div>
                    <div class="guia-step-text">A porta física do switch conectada a este servidor precisa carregar todas as VLANs marcadas (tagged) -- normalmente com uma "VLAN nativa/untagged" pra rede de gerência. Isso é feito na interface do próprio switch (Omada, UniFi, TP-Link standalone, etc.), não aqui.</div>
                </div>
            </div>
            <div class="guia-step">
                <div class="guia-step-num">2</div>
                <div class="guia-step-body">
                    <div class="guia-step-title">Crie as VLANs</div>
                    <div class="guia-step-text"><a href="<?= url('/infraestrutura/vlan') ?>">Infraestrutura &gt; VLANs &gt; Nova VLAN</a> -- escolha a interface física/trunk, o ID (tag) da VLAN (o botão de varinha sugere o próximo livre) e o IP que este servidor vai usar como gateway dela. O sistema mostra a rede resultante (ex: <code>192.168.10.0/24</code>) antes de salvar.</div>
                </div>
            </div>
            <div class="guia-step">
                <div class="guia-step-num">3</div>
                <div class="guia-step-body">
                    <div class="guia-step-title">Aplique as VLANs</div>
                    <div class="guia-step-text">Cria de verdade as sub-interfaces (<code>eth0.10</code>, por exemplo) via netplan. Se algo der errado (ou você perder o acesso ao aplicar numa interface que você mesmo usa pra conectar), a configuração anterior <strong>volta sozinha em 90 segundos</strong> caso ninguém confirme a tempo.</div>
                </div>
            </div>
            <div class="guia-step">
                <div class="guia-step-num">4</div>
                <div class="guia-step-body">
                    <div class="guia-step-title">Instale e configure o Servidor DHCP</div>
                    <div class="guia-step-text"><a href="<?= url('/infraestrutura/dhcp') ?>">Infraestrutura &gt; Servidor DHCP</a> -- instale o <code>isc-dhcp-server</code> (um clique), marque as interfaces que devem responder DHCP (a física e/ou cada VLAN criada), e defina domínio/DNS/tempo de concessão.</div>
                </div>
            </div>
            <div class="guia-step">
                <div class="guia-step-num">5</div>
                <div class="guia-step-body">
                    <div class="guia-step-title">Cadastre uma rede (subnet) por VLAN</div>
                    <div class="guia-step-text">Uma "rede" no DHCP precisa bater com a rede de uma VLAN já aplicada (mesmo endereço de rede/máscara) pra o <code>dhcpd</code> conseguir casar os dois e distribuir IP nela. Dá pra reaproveitar uma reserva de IP fixo por MAC pra impressoras, câmeras etc.</div>
                </div>
            </div>
            <div class="guia-step">
                <div class="guia-step-num">6</div>
                <div class="guia-step-body">
                    <div class="guia-step-title">Aplique o DHCP</div>
                    <div class="guia-step-text">Mesma lógica de segurança: valida a sintaxe antes, e se o serviço não subir ou causar problema, reverte -- na hora (falha detectada) ou em até 90s (se ninguém notar).</div>
                </div>
            </div>
            <div class="guia-step guia-step-last">
                <div class="guia-step-num">7</div>
                <div class="guia-step-body">
                    <div class="guia-step-title">Libere roteamento entre VLANs e internet</div>
                    <div class="guia-step-text"><a href="<?= url('/infraestrutura/iptables') ?>">Infraestrutura &gt; Firewall &gt; Templates &gt; "Masquerade de Saída"</a>, apontando pra interface de internet (WAN). Sem isso, os dispositivos das VLANs recebem IP e conseguem se enxergar, mas não navegam -- e nem se enxergam entre VLANs diferentes.</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mt-3 guia-doc-card" style="max-width:960px">
    <div class="card-body">
        <strong><i class="bi bi-tools"></i> Como os três módulos se encaixam</strong>
        <p class="text-muted small mt-2 mb-3">Cada um cuida de uma parte -- nenhum sozinho substitui um gateway completo, mas os três juntos sim.</p>
        <div class="table-responsive">
            <table class="table table-sm guia-tabela-ferramentas align-middle mb-0">
                <thead>
                    <tr>
                        <th>Módulo</th>
                        <th>O que faz</th>
                        <th>Não faz</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><i class="bi bi-diagram-3-fill text-primary"></i> VLANs</td>
                        <td class="text-muted small">Cria a "perna" de cada VLAN neste servidor (sub-interface + IP de gateway)</td>
                        <td class="text-muted small">Não distribui IP nem roteia sozinho</td>
                    </tr>
                    <tr>
                        <td><i class="bi bi-hdd-network-fill text-primary"></i> Servidor DHCP</td>
                        <td class="text-muted small">Distribui IP automaticamente em cada interface marcada (física e/ou VLANs)</td>
                        <td class="text-muted small">Não cria a interface nem faz roteamento/NAT</td>
                    </tr>
                    <tr>
                        <td><i class="bi bi-shield-lock text-danger"></i> Firewall</td>
                        <td class="text-muted small">Liga o roteamento entre redes (<code>ip_forward</code>) e o NAT/Masquerade pra internet</td>
                        <td class="text-muted small">Não cria interface nem distribui IP</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mt-3 guia-doc-card" style="max-width:960px">
    <div class="card-body">
        <strong><i class="bi bi-exclamation-triangle-fill text-danger me-1"></i> Avisos importantes</strong>
        <div class="guia-callout guia-callout-danger mt-3">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div><strong>Só pode haver UM servidor DHCP respondendo por rede/VLAN.</strong> Se o equipamento antigo ainda está ligado (mesmo com defeito parcial) e ainda distribui IP, desligue-o antes de ativar o DHCP daqui -- dois servidores DHCP na mesma rede causam conflito de IP imprevisível.</div>
        </div>
        <div class="guia-callout guia-callout-warning mt-2">
            <i class="bi bi-exclamation-triangle"></i>
            <div><strong>Cuidado ao aplicar uma VLAN na interface física que você está usando pra acessar este servidor agora.</strong> Uma tag errada pode cortar seu próprio acesso -- é exatamente pra isso que existe a reversão automática em 90 segundos.</div>
        </div>
        <div class="guia-callout guia-callout-warning mt-2">
            <i class="bi bi-exclamation-triangle"></i>
            <div><strong>A tag da VLAN (ID) precisa ser idêntica dos dois lados</strong> -- na porta trunk do switch e aqui no servidor. IDs diferentes fazem o tráfego simplesmente não chegar, sem erro nenhum pra investigar.</div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mt-3 guia-doc-card" style="max-width:960px">
    <div class="card-body">
        <strong><i class="bi bi-cpu"></i> Como funciona por baixo dos panos</strong>

        <div class="accordion guia-accordion mt-3" id="acordeaoDocGuia">

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#docVlanTecnico">
                        <i class="bi bi-diagram-3-fill text-primary me-2"></i> VLANs -- netplan e 802.1Q
                    </button>
                </h2>
                <div id="docVlanTecnico" class="accordion-collapse collapse" data-bs-parent="#acordeaoDocGuia">
                    <div class="accordion-body">
                        <p class="text-muted small mb-0">
                            Cada VLAN cadastrada vira uma sub-interface 802.1Q de verdade (ex: <code>eth0.10</code>),
                            declarada num arquivo de override do netplan (<code>/etc/netplan/95-rd-intranet-vlans.yaml</code>,
                            regenerado por completo a cada "Aplicar" com todas as VLANs ativas). O Linux (via
                            systemd-networkd, o mesmo motor que já gerencia as demais interfaces) cria a
                            sub-interface, marca o tráfego que sai dela com a tag configurada, e já sabe rotear
                            entre as redes diretamente conectadas -- sem precisar de rota estática nenhuma, desde
                            que o encaminhamento de pacotes (<code>ip_forward</code>) esteja habilitado.
                        </p>
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#docDhcpTecnico">
                        <i class="bi bi-hdd-network-fill text-primary me-2"></i> DHCP -- múltiplas interfaces
                    </button>
                </h2>
                <div id="docDhcpTecnico" class="accordion-collapse collapse" data-bs-parent="#acordeaoDocGuia">
                    <div class="accordion-body">
                        <p class="text-muted small mb-0">
                            O <code>isc-dhcp-server</code> aceita nativamente uma lista de interfaces pra escutar
                            (variável <code>INTERFACESv4</code>, em <code>/etc/default/isc-dhcp-server</code>) --
                            é por isso que a tela deixa marcar mais de uma. Pra cada interface com IP configurado
                            (a física e/ou cada VLAN), o <code>dhcpd</code> precisa achar no <code>dhcpd.conf</code>
                            uma declaração de <code>subnet</code> com a mesma rede -- daí a exigência de cadastrar
                            uma "rede" por VLAN. Uma interface sem rede correspondente é simplesmente ignorada
                            (não trava as demais).
                        </p>
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#docFirewallTecnico">
                        <i class="bi bi-shield-lock text-danger me-2"></i> Firewall -- ip_forward e Masquerade
                    </button>
                </h2>
                <div id="docFirewallTecnico" class="accordion-collapse collapse" data-bs-parent="#acordeaoDocGuia">
                    <div class="accordion-body">
                        <p class="text-muted small mb-0">
                            Por padrão, o kernel Linux <strong>não</strong> encaminha pacotes entre interfaces
                            (<code>net.ipv4.ip_forward = 0</code>) -- sem isso, cada VLAN fica isolada, enxergando só
                            a si mesma. O template "Masquerade de Saída" do Firewall habilita esse encaminhamento
                            de brinde (ação extra automática) e ainda cria a regra de NAT (tabela <code>nat</code>,
                            cadeia <code>POSTROUTING</code>, ação <code>MASQUERADE</code>) que traduz o IP interno
                            de cada VLAN pro IP público da interface de internet -- sem isso, os dispositivos até
                            se enxergam entre VLANs, mas não navegam.
                        </p>
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#docSegurancaTecnico">
                        <i class="bi bi-shield-check text-success me-2"></i> Por que tudo tem "90 segundos de reversão"
                    </button>
                </h2>
                <div id="docSegurancaTecnico" class="accordion-collapse collapse" data-bs-parent="#acordeaoDocGuia">
                    <div class="accordion-body">
                        <p class="text-muted small mb-0">
                            VLANs e DHCP têm o mesmo risco de fundo do editor de Interfaces de rede: uma mudança
                            pode derrubar o próprio acesso ao servidor (ou a rede inteira), e não tem como o sistema
                            saber isso sozinho <em>no instante em que aplica</em>. Por isso, toda mudança grava um
                            backup, aplica, e agenda uma reversão automática (<code>systemd-run</code>) que só é
                            cancelada se alguém clicar em "Confirmar" a tempo -- se o acesso cair, é só esperar a
                            contagem: a configuração anterior volta sozinha, sem precisar de ninguém no teclado.
                        </p>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<style>
.guia-emergencia { border-left: 4px solid #dc3545 !important; }
.guia-doc-card .card-body { padding: 1.25rem 1.5rem; }

.guia-steps { display: flex; flex-direction: column; gap: 0; }
.guia-step { display: flex; gap: .9rem; position: relative; padding-bottom: 1.25rem; }
.guia-step::before {
    content: ''; position: absolute; left: 13px; top: 30px; bottom: 0;
    width: 2px; background: linear-gradient(to bottom, #cfe2ff, #e9ecef);
}
.guia-step-last::before { display: none; }
.guia-step-num {
    flex: 0 0 auto; width: 28px; height: 28px; border-radius: 50%;
    background: #0d6efd; color: #fff; font-weight: 600; font-size: .8rem;
    display: flex; align-items: center; justify-content: center; z-index: 1;
}
.guia-step-title { font-weight: 600; font-size: .9rem; }
.guia-step-text { color: #6c757d; font-size: .82rem; margin-top: 2px; }

.guia-tabela-ferramentas th { font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: #6c757d; border-top: none; }
.guia-tabela-ferramentas td { font-size: .85rem; }

.guia-accordion .accordion-button {
    font-size: .88rem; font-weight: 600; background: #f8f9fa;
}
.guia-accordion .accordion-button:not(.collapsed) {
    background: #eef4ff; color: #0d3b8c; box-shadow: none;
}
.guia-accordion .accordion-button:focus { box-shadow: none; }
.guia-accordion .accordion-item { border-color: #e9ecef; }

.guia-callout {
    display: flex; gap: .6rem; padding: .75rem .9rem; border-radius: .5rem; font-size: .82rem;
}
.guia-callout i { font-size: 1.1rem; flex: 0 0 auto; }
.guia-callout-warning { background: #fff8e6; color: #664d03; border: 1px solid #ffe69c; }
.guia-callout-warning i { color: #997404; }
.guia-callout-danger { background: #fdeeee; color: #7a1f1f; border: 1px solid #f5c2c2; }
.guia-callout-danger i { color: #b02a2a; }
</style>

<?php
$conteudo = ob_get_clean();
$titulo = 'Infraestrutura - Guia DHCP + VLANs';

require __DIR__ . '/../layouts/main.php';

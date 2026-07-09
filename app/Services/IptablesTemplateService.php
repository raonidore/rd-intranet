<?php

namespace App\Services;

/**
 * Catalogo de regras pre-prontas. Cada template descreve os campos que a
 * tela deve pedir ao admin (normalmente so a interface + um dado especifico,
 * como pedido) e sabe construir a(s) linha(s) de regra correspondentes.
 */
class IptablesTemplateService
{
    public function catalogo(): array
    {
        return [
            'ssh_restringir' => [
                'nome' => 'Restringir SSH a uma rede confiável',
                'descricao' => 'Só permite acesso SSH a partir de um IP/rede específica; qualquer outra origem é rejeitada.',
                'icone' => 'bi-shield-lock',
                'campos' => [
                    ['nome' => 'interface', 'label' => 'Interface', 'tipo' => 'interface', 'obrigatorio' => true],
                    ['nome' => 'rede_confiavel', 'label' => 'IP ou rede confiável (CIDR)', 'tipo' => 'cidr', 'placeholder' => '192.168.1.0/24', 'obrigatorio' => true],
                    ['nome' => 'porta', 'label' => 'Porta do SSH', 'tipo' => 'porta', 'padrao' => '22', 'obrigatorio' => true],
                ],
            ],
            'ssh_bloquear_interface' => [
                'nome' => 'Bloquear SSH em uma interface',
                'descricao' => 'Bloqueia acesso SSH só naquela interface (ex: WAN), mantendo liberado nas demais.',
                'icone' => 'bi-shield-x',
                'campos' => [
                    ['nome' => 'interface', 'label' => 'Interface a bloquear', 'tipo' => 'interface', 'obrigatorio' => true],
                    ['nome' => 'porta', 'label' => 'Porta do SSH', 'tipo' => 'porta', 'padrao' => '22', 'obrigatorio' => true],
                ],
            ],
            'ssh_mudar_porta' => [
                'nome' => 'Mudar a porta do SSH',
                'descricao' => 'Libera a nova porta no firewall e, opcionalmente, já troca a porta do próprio serviço sshd.',
                'icone' => 'bi-key',
                'campos' => [
                    ['nome' => 'porta_nova', 'label' => 'Nova porta do SSH', 'tipo' => 'porta', 'placeholder' => '2222', 'obrigatorio' => true],
                    ['nome' => 'tambem_sshd', 'label' => 'Também mudar no sshd e recarregar o serviço', 'tipo' => 'checkbox'],
                ],
            ],
            'bloquear_ping' => [
                'nome' => 'Bloquear ping (ICMP)',
                'descricao' => 'Descarta pedidos de ping (echo-request) recebidos naquela interface.',
                'icone' => 'bi-slash-circle',
                'campos' => [
                    ['nome' => 'interface', 'label' => 'Interface', 'tipo' => 'interface', 'obrigatorio' => true],
                ],
            ],
            'ssh_rate_limit' => [
                'nome' => 'Proteção contra força bruta no SSH',
                'descricao' => 'Limita novas tentativas de conexão SSH por IP numa janela de tempo (padrão: 4 em 60s).',
                'icone' => 'bi-speedometer',
                'campos' => [
                    ['nome' => 'interface', 'label' => 'Interface', 'tipo' => 'interface', 'obrigatorio' => true],
                    ['nome' => 'porta', 'label' => 'Porta do SSH', 'tipo' => 'porta', 'padrao' => '22', 'obrigatorio' => true],
                    ['nome' => 'tentativas', 'label' => 'Tentativas permitidas', 'tipo' => 'numero', 'padrao' => '4', 'obrigatorio' => true],
                    ['nome' => 'janela', 'label' => 'Janela (segundos)', 'tipo' => 'numero', 'padrao' => '60', 'obrigatorio' => true],
                ],
            ],
            'nat_masquerade' => [
                'nome' => 'Compartilhar internet (NAT de saída / Masquerade)',
                'descricao' => 'Permite que outros hosts da rede saiam para a internet através deste servidor.',
                'icone' => 'bi-share',
                'campos' => [
                    ['nome' => 'interface_wan', 'label' => 'Interface de saída (internet)', 'tipo' => 'interface', 'obrigatorio' => true],
                ],
            ],
            'nat_port_forward' => [
                'nome' => 'Redirecionamento de porta (Port Forward)',
                'descricao' => 'Encaminha uma porta externa deste servidor para um IP/porta internos (DNAT).',
                'icone' => 'bi-signpost-split',
                'campos' => [
                    ['nome' => 'interface', 'label' => 'Interface de entrada', 'tipo' => 'interface', 'obrigatorio' => true],
                    ['nome' => 'protocolo', 'label' => 'Protocolo', 'tipo' => 'protocolo', 'padrao' => 'tcp', 'obrigatorio' => true],
                    ['nome' => 'porta_externa', 'label' => 'Porta externa', 'tipo' => 'porta', 'obrigatorio' => true],
                    ['nome' => 'ip_interno', 'label' => 'IP interno de destino', 'tipo' => 'ip', 'placeholder' => '192.168.1.50', 'obrigatorio' => true],
                    ['nome' => 'porta_interna', 'label' => 'Porta interna de destino', 'tipo' => 'porta', 'obrigatorio' => true],
                ],
            ],
            'liberar_porta' => [
                'nome' => 'Liberar uma porta/serviço',
                'descricao' => 'Libera acesso a uma porta específica (ex: um servidor web na 80/443).',
                'icone' => 'bi-unlock',
                'campos' => [
                    ['nome' => 'interface', 'label' => 'Interface (opcional)', 'tipo' => 'interface', 'opcional_todas' => true],
                    ['nome' => 'protocolo', 'label' => 'Protocolo', 'tipo' => 'protocolo', 'padrao' => 'tcp', 'obrigatorio' => true],
                    ['nome' => 'porta', 'label' => 'Porta', 'tipo' => 'porta', 'obrigatorio' => true],
                ],
            ],
            'bloquear_ip' => [
                'nome' => 'Bloquear um IP ou rede',
                'descricao' => 'Descarta todo tráfego vindo de um IP ou rede específica.',
                'icone' => 'bi-ban',
                'campos' => [
                    ['nome' => 'ip_bloqueado', 'label' => 'IP ou rede a bloquear (CIDR)', 'tipo' => 'cidr', 'placeholder' => '203.0.113.5/32', 'obrigatorio' => true],
                    ['nome' => 'interface', 'label' => 'Interface (opcional)', 'tipo' => 'interface', 'opcional_todas' => true],
                ],
            ],
        ];
    }

    /**
     * @return array{regras: array[], acoes_extras: array[]}
     */
    public function construirRegras(string $chave, array $p): array
    {
        $base = ['ordem' => 100, 'origem_template' => $chave, 'ativo' => true];

        switch ($chave) {
            case 'ssh_restringir':
                return ['regras' => [
                    [...$base, 'nome' => "SSH liberado para {$p['rede_confiavel']}", 'tabela' => 'filter', 'cadeia' => 'INPUT',
                        'acao' => 'ACCEPT', 'protocolo' => 'tcp', 'porta_destino' => $p['porta'],
                        'ip_origem' => $p['rede_confiavel'], 'interface_entrada' => $p['interface'], 'ordem' => 10],
                    [...$base, 'nome' => "SSH bloqueado para o restante ({$p['interface']})", 'tabela' => 'filter', 'cadeia' => 'INPUT',
                        'acao' => 'REJECT', 'protocolo' => 'tcp', 'porta_destino' => $p['porta'],
                        'interface_entrada' => $p['interface'], 'ordem' => 20, 'registrar_log' => true],
                ], 'acoes_extras' => []];

            case 'ssh_bloquear_interface':
                return ['regras' => [
                    [...$base, 'nome' => "SSH bloqueado em {$p['interface']}", 'tabela' => 'filter', 'cadeia' => 'INPUT',
                        'acao' => 'REJECT', 'protocolo' => 'tcp', 'porta_destino' => $p['porta'],
                        'interface_entrada' => $p['interface'], 'registrar_log' => true],
                ], 'acoes_extras' => []];

            case 'ssh_mudar_porta':
                $acoes = [];
                if (!empty($p['tambem_sshd'])) {
                    $acoes[] = ['tipo' => 'sshd_porta', 'porta' => $p['porta_nova']];
                }
                return ['regras' => [
                    [...$base, 'nome' => "SSH liberado na porta {$p['porta_nova']}", 'tabela' => 'filter', 'cadeia' => 'INPUT',
                        'acao' => 'ACCEPT', 'protocolo' => 'tcp', 'porta_destino' => $p['porta_nova']],
                ], 'acoes_extras' => $acoes];

            case 'bloquear_ping':
                return ['regras' => [
                    [...$base, 'nome' => "Ping bloqueado em {$p['interface']}", 'tabela' => 'filter', 'cadeia' => 'INPUT',
                        'acao' => 'DROP', 'protocolo' => 'icmp', 'interface_entrada' => $p['interface'],
                        'extra' => '-m icmp --icmp-type echo-request', 'registrar_log' => true],
                ], 'acoes_extras' => []];

            case 'ssh_rate_limit':
                $nome = "SSH_{$p['porta']}_" . preg_replace('/\D/', '', $p['interface']);
                return ['regras' => [
                    [...$base, 'nome' => "Rastreio de tentativas SSH ({$p['interface']})", 'tabela' => 'filter', 'cadeia' => 'INPUT',
                        'acao' => 'NONE', 'protocolo' => 'tcp', 'porta_destino' => $p['porta'], 'interface_entrada' => $p['interface'],
                        'extra' => "-m conntrack --ctstate NEW -m recent --name {$nome} --set", 'ordem' => 10],
                    [...$base, 'nome' => "Bloqueio por excesso de tentativas SSH ({$p['interface']})", 'tabela' => 'filter', 'cadeia' => 'INPUT',
                        'acao' => 'DROP', 'protocolo' => 'tcp', 'porta_destino' => $p['porta'], 'interface_entrada' => $p['interface'],
                        'extra' => "-m conntrack --ctstate NEW -m recent --name {$nome} --update --seconds {$p['janela']} --hitcount {$p['tentativas']}",
                        'ordem' => 20, 'registrar_log' => true],
                ], 'acoes_extras' => []];

            case 'nat_masquerade':
                return ['regras' => [
                    [...$base, 'nome' => "Masquerade de saída em {$p['interface_wan']}", 'tabela' => 'nat', 'cadeia' => 'POSTROUTING',
                        'acao' => 'MASQUERADE', 'protocolo' => 'all', 'interface_saida' => $p['interface_wan']],
                ], 'acoes_extras' => [['tipo' => 'ip_forward']]];

            case 'nat_port_forward':
                return ['regras' => [
                    [...$base, 'nome' => "Encaminha {$p['porta_externa']} → {$p['ip_interno']}:{$p['porta_interna']}", 'tabela' => 'nat', 'cadeia' => 'PREROUTING',
                        'acao' => 'DNAT', 'protocolo' => $p['protocolo'], 'porta_destino' => $p['porta_externa'],
                        'interface_entrada' => $p['interface'], 'nat_destino' => "{$p['ip_interno']}:{$p['porta_interna']}", 'ordem' => 10],
                    [...$base, 'nome' => "Libera encaminhamento para {$p['ip_interno']}:{$p['porta_interna']}", 'tabela' => 'filter', 'cadeia' => 'FORWARD',
                        'acao' => 'ACCEPT', 'protocolo' => $p['protocolo'], 'ip_destino' => $p['ip_interno'],
                        'porta_destino' => $p['porta_interna'], 'ordem' => 20],
                ], 'acoes_extras' => [['tipo' => 'ip_forward']]];

            case 'liberar_porta':
                return ['regras' => [
                    [...$base, 'nome' => "Porta {$p['porta']}/{$p['protocolo']} liberada", 'tabela' => 'filter', 'cadeia' => 'INPUT',
                        'acao' => 'ACCEPT', 'protocolo' => $p['protocolo'], 'porta_destino' => $p['porta'],
                        'interface_entrada' => $p['interface'] ?: null],
                ], 'acoes_extras' => []];

            case 'bloquear_ip':
                return ['regras' => [
                    [...$base, 'nome' => "Bloqueado: {$p['ip_bloqueado']}", 'tabela' => 'filter', 'cadeia' => 'INPUT',
                        'acao' => 'DROP', 'protocolo' => 'all', 'ip_origem' => $p['ip_bloqueado'],
                        'interface_entrada' => $p['interface'] ?: null, 'registrar_log' => true],
                ], 'acoes_extras' => []];
        }

        return ['regras' => [], 'acoes_extras' => []];
    }
}

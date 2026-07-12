<?php

namespace App\Services;

/**
 * Catalogo de ferramentas/pacotes externos dos quais a RD Intranet depende.
 * Serve pra tela de checklist mostrar status + descricao; a lista de
 * pacotes que o botao "Instalar" realmente aceita fica travada, duplicada,
 * dentro do proprio script root (dependencias_instalar_web.sh) -- o PHP
 * nunca dita pro root o que instalar, so sugere.
 */
class DependenciaCatalogo
{
    public static function itens(): array
    {
        return [
            [
                'chave' => 'iptables',
                'nome' => 'iptables',
                'pacote' => 'iptables',
                'descricao' => 'Motor do firewall gerenciado pela tela.',
                'usado_em' => 'Infraestrutura > Firewall',
                'obrigatorio' => true,
            ],
            [
                'chave' => 'conntrack',
                'nome' => 'conntrack',
                'pacote' => 'conntrack',
                'descricao' => 'Limpa conexões rastreadas (ex: ping) ao aplicar novas regras de bloqueio — sem isso, um ping "em andamento" pode continuar passando mesmo depois de bloqueado.',
                'usado_em' => 'Infraestrutura > Firewall',
                'obrigatorio' => false,
            ],
            [
                'chave' => 'openssl',
                'nome' => 'openssl',
                'pacote' => 'openssl',
                'descricao' => 'Gera e valida certificados (autoassinado e importado).',
                'usado_em' => 'Infraestrutura > Certificado Digital',
                'obrigatorio' => true,
            ],
            [
                'chave' => 'certbot',
                'nome' => 'certbot',
                'pacote' => 'certbot',
                'descricao' => 'Emite e renova certificados HTTPS gratuitos via Let\'s Encrypt.',
                'usado_em' => 'Infraestrutura > Certificado Digital',
                'obrigatorio' => false,
            ],
            [
                'chave' => 'cron',
                'nome' => 'cron',
                'pacote' => 'cron',
                'descricao' => 'Agendador do sistema que executa os jobs configurados na tela.',
                'usado_em' => 'Infraestrutura > Cron',
                'obrigatorio' => true,
            ],
            [
                'chave' => 'lm-sensors',
                'nome' => 'sensors (lm-sensors)',
                'pacote' => 'lm-sensors',
                'descricao' => 'Leitura de temperatura do hardware (CPU/placa-mãe).',
                'usado_em' => 'Infraestrutura > Hardware',
                'obrigatorio' => false,
            ],
            [
                'chave' => 'mariadb-client',
                'nome' => 'mysql (cliente MariaDB)',
                'pacote' => 'mariadb-client',
                'descricao' => 'Cliente de linha de comando usado pelo Console SQL.',
                'usado_em' => 'Banco de Dados > Console',
                'obrigatorio' => true,
            ],
            [
                'chave' => 'samba',
                'nome' => 'samba (smbd)',
                'pacote' => 'samba',
                'descricao' => 'Servidor de compartilhamento de arquivos gerenciado pelo módulo Samba.',
                'usado_em' => 'Samba',
                'obrigatorio' => true,
            ],
            [
                'chave' => 'iproute2',
                'nome' => 'ip (iproute2)',
                'pacote' => 'iproute2',
                'descricao' => 'Leitura de interfaces, rotas e endereços de rede.',
                'usado_em' => 'Infraestrutura > Network',
                'obrigatorio' => true,
            ],
            [
                'chave' => 'python3',
                'nome' => 'python3',
                'pacote' => 'python3',
                'descricao' => 'Usado pelas rotinas de arquivos do Samba (copiar/mover/renomear/criar pasta/listar) para proteção contra path traversal.',
                'usado_em' => 'Samba > Arquivos',
                'obrigatorio' => true,
            ],
            [
                'chave' => 'smbclient',
                'nome' => 'smbcacls (smbclient)',
                'pacote' => 'smbclient',
                'descricao' => 'Aplica permissões (ACL) do Windows por usuário em compartilhamentos Samba. Não vem junto do pacote "samba".',
                'usado_em' => 'Samba > Compartilhamentos (ACL avançada)',
                'obrigatorio' => true,
            ],
            [
                'chave' => 'apache2',
                'nome' => 'apache2ctl / a2enmod',
                'pacote' => 'apache2',
                'descricao' => 'Gerencia módulos, sites e configuração do Apache — inclusive a ativação do HTTPS.',
                'usado_em' => 'Apache / Infraestrutura > Certificado Digital',
                'obrigatorio' => true,
            ],
            [
                'chave' => 'openssh-server',
                'nome' => 'sshd',
                'pacote' => 'openssh-server',
                'descricao' => 'Necessário para o Firewall poder validar e trocar a porta do SSH com segurança.',
                'usado_em' => 'Infraestrutura > Firewall (mudar porta do SSH)',
                'obrigatorio' => true,
            ],
            [
                'chave' => 'netplan',
                'nome' => 'netplan',
                'pacote' => 'netplan.io',
                'descricao' => 'Aplica e reverte a configuração de IP das interfaces de rede.',
                'usado_em' => 'Infraestrutura > Network (editar interface)',
                'obrigatorio' => true,
            ],
            [
                'chave' => 'samba-common-bin',
                'nome' => 'smbpasswd / testparm',
                'pacote' => 'samba-common-bin',
                'descricao' => 'Gerencia senha de usuários Samba e valida a sintaxe do smb.conf antes de aplicar mudanças.',
                'usado_em' => 'Samba > Usuários / Config. Global',
                'obrigatorio' => true,
            ],
            [
                'chave' => 'acl',
                'nome' => 'getfacl (acl)',
                'pacote' => 'acl',
                'descricao' => 'Lê permissões POSIX de compartilhamentos na tela de diagnóstico do Samba.',
                'usado_em' => 'Samba > Diagnóstico',
                'obrigatorio' => false,
            ],
            [
                'chave' => 'traceroute',
                'nome' => 'traceroute',
                'pacote' => 'traceroute',
                'descricao' => 'Rastreamento de rota usado na ferramenta de diagnóstico de rede.',
                'usado_em' => 'Infraestrutura > Network > Traceroute',
                'obrigatorio' => false,
            ],
            [
                'chave' => 'iputils-ping',
                'nome' => 'ping',
                'pacote' => 'iputils-ping',
                'descricao' => 'Usado pela ferramenta de Ping da tela de rede.',
                'usado_em' => 'Infraestrutura > Network > Ping',
                'obrigatorio' => true,
            ],
            [
                'chave' => 'qrencode',
                'nome' => 'qrencode',
                'pacote' => 'qrencode',
                'descricao' => 'Gera os QR codes de configuração de VPN e das etiquetas de ativos.',
                'usado_em' => 'VPN / Ativos > Etiquetas',
                'obrigatorio' => false,
            ],
            [
                'chave' => 'snmp',
                'nome' => 'snmpget / snmpwalk',
                'pacote' => 'snmp',
                'descricao' => 'Coleta automática de dados de switches, impressoras e servidores via SNMP.',
                'usado_em' => 'Ativos (coleta SNMP)',
                'obrigatorio' => false,
            ],
        ];
    }

    public static function item(string $chave): ?array
    {
        foreach (self::itens() as $item) {
            if ($item['chave'] === $chave) {
                return $item;
            }
        }
        return null;
    }
}

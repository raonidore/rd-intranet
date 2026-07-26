<?php

namespace App\Services;

use App\Repositories\RdpCredencialRepository;

/**
 * RDP pelo navegador (Ativos > ficha da máquina) -- conecta num RDP que
 * JÁ existe na máquina Windows (nada instalado no alvo), via guacd
 * (traduz o protocolo) + ponte guacamole-lite (WebSocket, só em
 * 127.0.0.1) + proxy reverso do Apache (mesma porta HTTPS que o site já
 * usa, ver scripts/system/rdp_proxy_ativar_web.sh). Decisão revertida em
 * relação à primeira versão (porta própria + TLS na própria ponte, igual
 * ao MeshCentral): confirmado ao vivo que isso exige liberar mais uma
 * porta no roteador/NAT de CADA servidor pra acesso remoto funcionar --
 * inviável administrando vários servidores atrás de NAT com mapeamento
 * mínimo de portas. Proxy pela mesma porta do site elimina essa
 * exigência (e de quebra tira a ponte da necessidade de ler certificado
 * nenhum -- quem termina TLS agora é o Apache).
 *
 * Credencial por ativo (não config única do servidor, diferente do
 * módulo Túneis) -- mesmo padrão de segredo por linha do db_conexoes
 * (senha_cifrada via CryptoService, nunca devolvida em claro por leitura
 * comum).
 */
class RdpService
{
    private const SCRIPT_GUACD = '/opt/rdtecnologia/scripts/guacd_instalar_web.sh';
    private const SCRIPT_BRIDGE = '/opt/rdtecnologia/scripts/guacamole_bridge_instalar_web.sh';
    private const SCRIPT_PROXY = '/opt/rdtecnologia/scripts/rdp_proxy_ativar_web.sh';
    private const VHOST_SSL = '/etc/apache2/sites-available/rd.intranet-ssl.conf';
    private const MARCA_PROXY = '# RD Intranet - proxy da ponte RDP pelo navegador';
    private const CHAVE_SEGREDO = '/etc/rd-intranet/db_secret.key';

    private RdpCredencialRepository $repository;
    private LinuxService $linux;

    public function __construct()
    {
        $this->repository = new RdpCredencialRepository();
        $this->linux = new LinuxService();
    }

    /** Credencial sem a senha -- pra preencher o formulário/mostrar o host já salvo. */
    public function credencial(int $ativoId): ?array
    {
        $item = $this->repository->buscarPorAtivo($ativoId);
        if ($item) {
            unset($item['senha_cifrada']);
        }

        return $item;
    }

    public function credencialSalva(int $ativoId): bool
    {
        return $this->repository->buscarPorAtivo($ativoId) !== null;
    }

    /**
     * Fluxo inteiro dessa feature é modal + fetch (sem recarregar a
     * página) -- por isso devolve ['success','message'] direto em vez de
     * usar NotificationService (que é pro flash da PRÓXIMA carga de
     * página via redirect, não se aplica aqui).
     */
    public function salvarCredencial(int $ativoId, string $host, int $porta, string $usuario, string $senha): array
    {
        $host = trim($host);
        $usuario = trim($usuario);

        if ($host === '' || $usuario === '') {
            return ['success' => false, 'message' => 'Informe o host e o usuário do RDP.'];
        }

        if ($porta < 1 || $porta > 65535) {
            return ['success' => false, 'message' => 'Porta inválida.'];
        }

        $existente = $this->repository->buscarPorAtivo($ativoId);

        if ($senha === '') {
            if (!$existente) {
                return ['success' => false, 'message' => 'Informe a senha do RDP.'];
            }
            $senhaCifrada = $existente['senha_cifrada'];
        } else {
            $senhaCifrada = CryptoService::encriptar($senha);
        }

        $this->repository->salvar($ativoId, $host, $porta, $usuario, $senhaCifrada);

        AuditService::registrar('Ativos', 'RDP', "Credencial de RDP salva pro ativo #{$ativoId}.");

        return ['success' => true, 'message' => 'Credencial salva.'];
    }

    public function removerCredencial(int $ativoId): array
    {
        $this->repository->remover($ativoId);

        AuditService::registrar('Ativos', 'RDP', "Credencial de RDP removida do ativo #{$ativoId}.");

        return ['success' => true, 'message' => 'Credencial removida.'];
    }

    /**
     * Estado ao vivo (nunca cacheado) do gateway compartilhado por todos
     * os ativos -- guacd, ponte e proxy do Apache. O vhost SSL
     * (/etc/apache2/sites-available/rd.intranet-ssl.conf) é 644 root:root
     * -- world-readable, confirmado, diferente do certificado em si --
     * então dá pra checar o proxy direto por aqui, sem precisar do script
     * root.
     */
    public function statusGateway(): array
    {
        return [
            'guacd_instalado' => $this->linux->executar('command -v guacd')['success'],
            'guacd_ativo' => trim($this->linux->executar('systemctl is-active guacd')['output']) === 'active',
            'bridge_ativo' => trim($this->linux->executar('systemctl is-active rd-guac-bridge')['output']) === 'active',
            'proxy_configurado' => is_readable(self::VHOST_SSL) && str_contains(file_get_contents(self::VHOST_SSL), self::MARCA_PROXY),
        ];
    }

    public function gatewayPronto(): bool
    {
        $status = $this->statusGateway();

        return $status['guacd_ativo'] && $status['bridge_ativo'] && $status['proxy_configurado'];
    }

    public function instalarGateway(): array
    {
        $guacd = $this->resultadoScript(
            $this->linux->executarScript(self::SCRIPT_GUACD),
            'Falha ao instalar o guacd.'
        );
        if (!$guacd['success']) {
            return $guacd;
        }

        $bridge = $this->resultadoScript(
            $this->linux->executarScript(self::SCRIPT_BRIDGE),
            'Falha ao instalar a ponte RDP.'
        );
        if (!$bridge['success']) {
            return $bridge;
        }

        $proxy = $this->resultadoScript(
            $this->linux->executarScript(self::SCRIPT_PROXY),
            'Falha ao configurar o proxy do RDP no Apache.'
        );
        if (!$proxy['success']) {
            return $proxy;
        }

        AuditService::registrar('Ativos', 'RDP', 'Suporte a RDP pelo navegador instalado neste servidor.');

        return ['success' => true, 'message' => 'Suporte a RDP pelo navegador pronto.'];
    }

    /**
     * Token único de conexão pro guacamole-lite -- JSON com host/usuário/
     * senha cifrado AES-256-CBC com a MESMA chave de 32 bytes já usada
     * pelo CryptoService (reaproveitada em vez de provisionar mais um
     * segredo; o bridge lê o mesmo arquivo). Formato do envelope conferido
     * direto na fonte do guacamole-lite: base64(JSON{iv,value}), iv/value
     * cada um também em base64.
     */
    /**
     * $largura/$altura -- tamanho (em pixels CSS) da área do modal no
     * navegador, pra a sessão RDP já nascer nessa resolução em vez de
     * sempre 1024x768 (proporção 4:3 -- sobrava bastante espaço vazio nas
     * laterais num modal widescreen, confirmado ao vivo). Clampados pra
     * uma faixa razoável -- nunca confiar em número vindo do front sem
     * limite.
     */
    public function gerarToken(int $ativoId, int $largura = 1024, int $altura = 768): ?string
    {
        $item = $this->repository->buscarPorAtivo($ativoId);
        if ($item === null) {
            return null;
        }

        try {
            $senha = CryptoService::decriptar($item['senha_cifrada']);
        } catch (\RuntimeException $e) {
            return null;
        }

        $largura = max(640, min(3840, $largura));
        $altura = max(480, min(2160, $altura));

        $payload = json_encode([
            'connection' => [
                'type' => 'rdp',
                'settings' => [
                    'hostname' => $item['host'],
                    'port' => (string)$item['porta'],
                    'username' => $item['usuario'],
                    'password' => $senha,
                    'security' => 'any',
                    'ignore-cert' => 'true',
                    'enable-drive' => 'false',
                    'create-drive-path' => 'false',
                    'width' => (string)$largura,
                    'height' => (string)$altura,
                    'dpi' => '96',
                    // Windows 10/11 usa por padrão o pipeline gráfico RDPGFX
                    // (H.264) -- o FreeRDP por trás do guacd às vezes conecta
                    // normal (cursor aparece, sessão fica de pé) mas nunca
                    // desenha a área de trabalho nesse modo. Desabilitar volta
                    // pro bitmap clássico, que sempre funciona.
                    'disable-gfx' => 'true',
                ],
            ],
        ]);

        $chave = $this->chaveCompartilhada();
        if ($chave === null) {
            return null;
        }

        $iv = random_bytes(16);
        $cifrado = openssl_encrypt($payload, 'aes-256-cbc', $chave, OPENSSL_RAW_DATA, $iv);
        if ($cifrado === false) {
            return null;
        }

        $envelope = json_encode([
            'iv' => base64_encode($iv),
            'value' => base64_encode($cifrado),
        ]);

        return base64_encode($envelope);
    }

    private function chaveCompartilhada(): ?string
    {
        if (!is_readable(self::CHAVE_SEGREDO)) {
            return null;
        }

        $chave = base64_decode(trim(file_get_contents(self::CHAVE_SEGREDO)));

        return $chave !== false ? $chave : null;
    }

    private function resultadoScript(array $execResultado, string $mensagemPadrao): array
    {
        $dados = json_decode($execResultado['output'], true);

        if (is_array($dados) && isset($dados['success'])) {
            return [
                'success' => (bool)$dados['success'],
                'message' => (string)($dados['message'] ?? $mensagemPadrao),
                'detalhes' => $execResultado['output'],
            ];
        }

        return ['success' => false, 'message' => $mensagemPadrao, 'detalhes' => $execResultado['output']];
    }
}

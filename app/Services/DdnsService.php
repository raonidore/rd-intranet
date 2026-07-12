<?php

namespace App\Services;

use App\Repositories\DdnsRepository;

class DdnsService
{
    private const PROVEDORES = ['noip', 'dyndns', 'cloudflare', 'duckdns', 'freedns'];

    private DdnsRepository $repo;
    private PublicIpService $ip;

    public function __construct()
    {
        $this->repo = new DdnsRepository();
        $this->ip = new PublicIpService();
    }

    public function listar(): array
    {
        return $this->repo->listar();
    }

    public function buscar(int $id): ?array
    {
        return $this->repo->buscar($id);
    }

    public function historico(int $contaId, int $limite = 20): array
    {
        return $this->repo->listarHistorico($contaId, $limite);
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function salvar(array $dados): array
    {
        $provedor = $dados['provedor'] ?? '';
        $apelido = trim($dados['apelido'] ?? '');
        $hostname = trim($dados['hostname'] ?? '');
        $id = isset($dados['id']) ? (int)$dados['id'] : null;

        if (!in_array($provedor, self::PROVEDORES, true)) {
            return ['success' => false, 'message' => 'Provedor inválido.'];
        }
        if ($apelido === '') {
            return ['success' => false, 'message' => 'Informe um apelido para identificar esta conta.'];
        }
        if ($hostname === '') {
            return ['success' => false, 'message' => 'Informe o hostname a ser mantido atualizado.'];
        }

        $credenciais = $this->montarCredenciais($provedor, $dados);
        if (is_string($credenciais)) {
            // string = mensagem de erro de validacao dos campos do provedor
            return ['success' => false, 'message' => $credenciais];
        }

        $credenciaisCriptografadas = $credenciais !== null
            ? CryptoService::encriptar(json_encode($credenciais))
            : null;

        if ($id === null) {
            if ($credenciaisCriptografadas === null) {
                return ['success' => false, 'message' => 'Preencha as credenciais do provedor.'];
            }
            $this->repo->criar($provedor, $apelido, $hostname, $credenciaisCriptografadas);
            return ['success' => true, 'message' => 'Conta de DNS dinâmico criada.'];
        }

        $this->repo->atualizar($id, $provedor, $apelido, $hostname, $credenciaisCriptografadas);

        return ['success' => true, 'message' => 'Conta de DNS dinâmico atualizada.'];
    }

    public function excluir(int $id): void
    {
        $this->repo->excluir($id);
    }

    public function ativar(int $id): void
    {
        $this->repo->ativar($id);
    }

    public function desativar(int $id): void
    {
        $this->repo->desativar($id);
    }

    /**
     * @return array{success: bool, message: string, ip: ?string}
     */
    public function atualizarTodas(): array
    {
        $ip = $this->ip->obter();
        if ($ip === null) {
            return ['success' => false, 'message' => 'Não foi possível obter o IP público do servidor.', 'ip' => null];
        }

        $contas = $this->repo->listarAtivas();
        $falhas = 0;

        foreach ($contas as $conta) {
            $resultado = $this->atualizarConta($conta, $ip);
            if (!$resultado['success']) {
                $falhas++;
            }
        }

        $total = count($contas);
        $mensagem = $total === 0
            ? 'Nenhuma conta ativa para atualizar.'
            : "{$total} conta(s) verificada(s), {$falhas} falha(s). IP público atual: {$ip}.";

        return ['success' => $falhas === 0, 'message' => $mensagem, 'ip' => $ip];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function atualizarContaId(int $id): array
    {
        $conta = $this->repo->buscar($id);
        if (!$conta) {
            return ['success' => false, 'message' => 'Conta não encontrada.'];
        }

        $ip = $this->ip->obter();
        if ($ip === null) {
            return ['success' => false, 'message' => 'Não foi possível obter o IP público do servidor.'];
        }

        // atualizacao manual forca o envio mesmo que o IP nao tenha mudado
        // (admin clicou "atualizar agora" de proposito).
        return $this->atualizarConta($conta, $ip, true);
    }

    /**
     * @return array{success: bool, message: string}
     */
    private function atualizarConta(array $conta, string $ip, bool $forcar = false): array
    {
        if (!$forcar && $conta['ultimo_ip'] === $ip) {
            $this->repo->registrarVerificacao((int)$conta['id'], $ip);
            return ['success' => true, 'message' => 'IP sem mudança.'];
        }

        try {
            $credenciais = json_decode(CryptoService::decriptar($conta['credenciais']), true);
        } catch (\Throwable $e) {
            $mensagem = 'Não foi possível decifrar as credenciais salvas.';
            $this->repo->registrarAtualizacao((int)$conta['id'], $ip, false, $mensagem);
            $this->repo->registrarHistorico((int)$conta['id'], $ip, false, $mensagem);
            return ['success' => false, 'message' => $mensagem];
        }

        $resultado = match ($conta['provedor']) {
            'noip' => $this->atualizarNoIp($credenciais, $conta['hostname'], $ip),
            'dyndns' => $this->atualizarDynDns($credenciais, $conta['hostname'], $ip),
            'cloudflare' => $this->atualizarCloudflare($credenciais, $conta['hostname'], $ip),
            'duckdns' => $this->atualizarDuckDns($credenciais, $conta['hostname'], $ip),
            'freedns' => $this->atualizarFreeDns($credenciais, $ip),
            default => ['success' => false, 'message' => 'Provedor desconhecido.'],
        };

        $this->repo->registrarAtualizacao((int)$conta['id'], $ip, $resultado['success'], $resultado['message']);
        $this->repo->registrarHistorico((int)$conta['id'], $ip, $resultado['success'], $resultado['message']);

        return $resultado;
    }

    // -- Provedores -----------------------------------------------------

    private function atualizarNoIp(array $c, string $hostname, string $ip): array
    {
        $url = 'https://dynupdate.no-ip.com/nic/update?' . http_build_query(['hostname' => $hostname, 'myip' => $ip]);
        $resposta = $this->chamarUrl($url, [$c['usuario'] ?? '', $c['senha'] ?? '']);

        return $this->interpretarRespostaDynStyle($resposta);
    }

    private function atualizarDynDns(array $c, string $hostname, string $ip): array
    {
        $servidor = trim($c['servidor'] ?? '') ?: 'members.dyndns.org';
        $url = 'https://' . $servidor . '/nic/update?' . http_build_query(['hostname' => $hostname, 'myip' => $ip]);
        $resposta = $this->chamarUrl($url, [$c['usuario'] ?? '', $c['senha'] ?? '']);

        return $this->interpretarRespostaDynStyle($resposta);
    }

    private function interpretarRespostaDynStyle(array $resposta): array
    {
        if ($resposta['erro_conexao']) {
            return ['success' => false, 'message' => 'Falha de conexão com o provedor.'];
        }

        $corpo = trim($resposta['corpo']);

        if (str_starts_with($corpo, 'good') || str_starts_with($corpo, 'nochg')) {
            return ['success' => true, 'message' => $corpo];
        }

        return ['success' => false, 'message' => $corpo !== '' ? $corpo : 'Resposta inesperada do provedor.'];
    }

    private function atualizarDuckDns(array $c, string $hostname, string $ip): array
    {
        $url = 'https://www.duckdns.org/update?' . http_build_query([
            'domains' => $hostname,
            'token' => $c['token'] ?? '',
            'ip' => $ip,
        ]);
        $resposta = $this->chamarUrl($url, null);

        if ($resposta['erro_conexao']) {
            return ['success' => false, 'message' => 'Falha de conexão com o provedor.'];
        }

        $corpo = trim($resposta['corpo']);

        return $corpo === 'OK'
            ? ['success' => true, 'message' => 'OK']
            : ['success' => false, 'message' => 'DuckDNS recusou a atualização (token ou domínio incorretos).'];
    }

    private function atualizarFreeDns(array $c, string $ip): array
    {
        $updateUrl = trim($c['update_url'] ?? '');
        if ($updateUrl === '') {
            return ['success' => false, 'message' => 'URL de atualização do FreeDNS não configurada.'];
        }

        $resposta = $this->chamarUrl($updateUrl, null);

        if ($resposta['erro_conexao']) {
            return ['success' => false, 'message' => 'Falha de conexão com o provedor.'];
        }

        $corpo = trim($resposta['corpo']);
        // afraid.org nao tem um padrao de resposta unico -- essas duas
        // frases cobrem os dois casos de sucesso (mudou / ja estava certo).
        $sucesso = stripos($corpo, 'updated') !== false || stripos($corpo, 'has not changed') !== false;

        return [
            'success' => $sucesso,
            'message' => $corpo !== '' ? $corpo : 'Resposta inesperada do provedor.',
        ];
    }

    private function atualizarCloudflare(array $c, string $hostname, string $ip): array
    {
        $token = $c['api_token'] ?? '';
        $zoneId = $c['zone_id'] ?? '';

        $busca = $this->chamarUrl(
            "https://api.cloudflare.com/client/v4/zones/{$zoneId}/dns_records?type=A&name={$hostname}",
            null,
            ["Authorization: Bearer {$token}"]
        );

        if ($busca['erro_conexao']) {
            return ['success' => false, 'message' => 'Falha de conexão com a Cloudflare.'];
        }

        $dadosBusca = json_decode($busca['corpo'], true);
        if (!is_array($dadosBusca) || empty($dadosBusca['success'])) {
            return ['success' => false, 'message' => 'Falha ao consultar a zone na Cloudflare (verifique o token/zone_id).'];
        }

        $registro = $dadosBusca['result'][0] ?? null;
        if (!$registro) {
            return [
                'success' => false,
                'message' => 'Registro A não encontrado na zone — crie-o manualmente uma vez no painel da Cloudflare antes de ativar aqui.',
            ];
        }

        $atualizacao = $this->chamarUrl(
            "https://api.cloudflare.com/client/v4/zones/{$zoneId}/dns_records/{$registro['id']}",
            null,
            ["Authorization: Bearer {$token}", 'Content-Type: application/json'],
            'PATCH',
            json_encode(['content' => $ip])
        );

        if ($atualizacao['erro_conexao']) {
            return ['success' => false, 'message' => 'Falha de conexão com a Cloudflare.'];
        }

        $dadosAtualizacao = json_decode($atualizacao['corpo'], true);
        if (is_array($dadosAtualizacao) && !empty($dadosAtualizacao['success'])) {
            return ['success' => true, 'message' => "Registro atualizado para {$ip}."];
        }

        return ['success' => false, 'message' => 'Cloudflare recusou a atualização do registro.'];
    }

    // -- Infra ------------------------------------------------------------

    /**
     * Unico ponto com curl_init do modulo. $auth = [usuario, senha] pra
     * Basic Auth (via CURLOPT_USERPWD, nunca embutido na URL).
     */
    private function chamarUrl(string $url, ?array $auth, array $headers = [], string $metodo = 'GET', ?string $corpo = null): array
    {
        $ch = curl_init($url);

        $opcoes = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => $headers,
        ];

        if ($auth !== null) {
            $opcoes[CURLOPT_USERPWD] = $auth[0] . ':' . $auth[1];
        }
        if ($metodo !== 'GET') {
            $opcoes[CURLOPT_CUSTOMREQUEST] = $metodo;
        }
        if ($corpo !== null) {
            $opcoes[CURLOPT_POSTFIELDS] = $corpo;
        }

        curl_setopt_array($ch, $opcoes);

        $resposta = curl_exec($ch);
        $codigoHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $erro = curl_errno($ch) !== 0;
        curl_close($ch);

        return [
            'http_code' => $codigoHttp,
            'corpo' => $resposta !== false ? $resposta : '',
            'erro_conexao' => $erro || $resposta === false,
        ];
    }

    /**
     * @return array|string|null array = credenciais validas, string = mensagem de erro, null = manter as credenciais atuais (edicao sem trocar)
     */
    private function montarCredenciais(string $provedor, array $dados)
    {
        return match ($provedor) {
            'noip' => $this->credenciaisUsuarioSenha($dados),
            'dyndns' => $this->credenciaisUsuarioSenha($dados, true),
            'cloudflare' => $this->credenciaisCloudflare($dados),
            'duckdns' => $this->credenciaisDuckDns($dados),
            'freedns' => $this->credenciaisFreeDns($dados),
            default => 'Provedor inválido.',
        };
    }

    private function credenciaisUsuarioSenha(array $dados, bool $comServidor = false)
    {
        $usuario = trim($dados['usuario'] ?? '');
        $senha = $dados['senha'] ?? '';

        if ($usuario === '' && $senha === '') {
            return null;
        }
        if ($usuario === '' || $senha === '') {
            return 'Informe usuário e senha.';
        }

        $credenciais = ['usuario' => $usuario, 'senha' => $senha];
        if ($comServidor) {
            $credenciais['servidor'] = trim($dados['servidor'] ?? '') ?: 'members.dyndns.org';
        }

        return $credenciais;
    }

    private function credenciaisCloudflare(array $dados)
    {
        $token = trim($dados['api_token'] ?? '');
        $zoneId = trim($dados['zone_id'] ?? '');

        if ($token === '' && $zoneId === '') {
            return null;
        }
        if ($token === '' || $zoneId === '') {
            return 'Informe o API Token e o Zone ID.';
        }

        return ['api_token' => $token, 'zone_id' => $zoneId];
    }

    private function credenciaisDuckDns(array $dados)
    {
        $token = trim($dados['token'] ?? '');

        if ($token === '') {
            return null;
        }

        return ['token' => $token];
    }

    private function credenciaisFreeDns(array $dados)
    {
        $updateUrl = trim($dados['update_url'] ?? '');

        if ($updateUrl === '') {
            return null;
        }

        return ['update_url' => $updateUrl];
    }
}

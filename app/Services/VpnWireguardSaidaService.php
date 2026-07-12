<?php

namespace App\Services;

use App\Repositories\VpnWireguardRepository;
use App\Repositories\VpnWireguardSaidaRepository;

/**
 * "Conexões de saída": este servidor como PEER/CLIENTE de um WireGuard
 * existente de terceiros. Mesma logica do VpnOpenvpnSaidaService --
 * não gera chave própria aqui, a config completa (com chave privada
 * embutida) já vem pronta de quem administra o servidor remoto.
 */
class VpnWireguardSaidaService
{
    private LinuxService $linux;
    private VpnWireguardSaidaRepository $repo;

    public function __construct()
    {
        $this->linux = new LinuxService();
        $this->repo = new VpnWireguardSaidaRepository();
    }

    public function listar(): array
    {
        $conexoes = $this->repo->listar();

        foreach ($conexoes as &$c) {
            $c['ativo'] = $this->statusConexao($c['nome']);
        }
        unset($c);

        return $conexoes;
    }

    public function buscar(int $id): ?array
    {
        return $this->repo->buscar($id);
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function criar(string $nome, string $conteudoConf): array
    {
        $nome = trim($nome);
        if (!preg_match('/^[a-zA-Z0-9_-]{1,15}$/', $nome)) {
            return ['success' => false, 'message' => 'Nome inválido (até 15 caracteres, letras/números/"-"/"_").'];
        }
        if ($this->repo->buscarPorNome($nome)) {
            return ['success' => false, 'message' => 'Já existe uma conexão com este nome.'];
        }

        $nomeInterfaceServidor = (new VpnWireguardRepository())->config()['interface_nome'] ?? 'wg0';
        if ($nome === $nomeInterfaceServidor) {
            return ['success' => false, 'message' => "\"{$nome}\" já é usado pela interface do modo servidor (VPN > WireGuard > Servidor) — escolha outro nome."];
        }

        if (trim($conteudoConf) === '') {
            return ['success' => false, 'message' => 'Cole o conteúdo do arquivo .conf fornecido pelo servidor remoto.'];
        }
        if (!str_contains($conteudoConf, '[Interface]') || !str_contains($conteudoConf, 'PrivateKey')) {
            return ['success' => false, 'message' => 'Arquivo .conf não parece válido (falta "[Interface]"/"PrivateKey").'];
        }

        $criptografado = CryptoService::encriptar($conteudoConf);
        $id = $this->repo->criar($nome, $criptografado);

        $deploy = $this->aplicarNoDisco($nome, $conteudoConf);
        if (!$deploy['success']) {
            $this->repo->excluir($id);

            return $deploy;
        }

        return ['success' => true, 'message' => 'Conexão criada. Use "Conectar" para ativar o túnel.'];
    }

    private function aplicarNoDisco(string $nome, string $conteudoConf): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'rd_wg_saida_');
        file_put_contents($tmp, $conteudoConf);

        $resultado = $this->linux->executarScript(
            '/opt/rdtecnologia/scripts/vpn_wireguard_saida_web.sh',
            ['aplicar', $nome, $tmp]
        );

        @unlink($tmp);

        $dados = json_decode(trim($resultado['output']), true);

        return is_array($dados) ? $dados : ['success' => false, 'message' => $resultado['output']];
    }

    public function conectar(int $id): array
    {
        $conexao = $this->repo->buscar($id);
        if (!$conexao) {
            return ['success' => false, 'message' => 'Conexão não encontrada.'];
        }

        return $this->chamar('conectar', $conexao['nome']);
    }

    public function desconectar(int $id): array
    {
        $conexao = $this->repo->buscar($id);
        if (!$conexao) {
            return ['success' => false, 'message' => 'Conexão não encontrada.'];
        }

        return $this->chamar('desconectar', $conexao['nome']);
    }

    public function alternarAtivoNoBoot(int $id, bool $ativo): array
    {
        $conexao = $this->repo->buscar($id);
        if (!$conexao) {
            return ['success' => false, 'message' => 'Conexão não encontrada.'];
        }

        $resultado = $this->chamar($ativo ? 'ativar_boot' : 'desativar_boot', $conexao['nome']);
        if (!empty($resultado['success'])) {
            $this->repo->marcarAtivoNoBoot($id, $ativo);
        }

        return $resultado;
    }

    public function remover(int $id): array
    {
        $conexao = $this->repo->buscar($id);
        if (!$conexao) {
            return ['success' => false, 'message' => 'Conexão não encontrada.'];
        }

        $resultado = $this->chamar('remover', $conexao['nome']);
        if (!empty($resultado['success'])) {
            $this->repo->excluir($id);
        }

        return $resultado;
    }

    /**
     * Gera um par de chaves novo pro admin registrar como peer no
     * servidor remoto -- so devolve, nao persiste nada (mesmo cuidado
     * do modo servidor: chave privada nunca fica guardada).
     */
    public function gerarParDeChaves(): array
    {
        $resultado = $this->linux->executar(
            'PRIV=$(wg genkey); PUB=$(printf \'%s\' "$PRIV" | wg pubkey); printf \'%s|%s\' "$PRIV" "$PUB"'
        );

        [$privada, $publica] = array_pad(explode('|', trim($resultado['output']), 2), 2, '');

        if ($privada === '' || $publica === '') {
            return ['success' => false, 'message' => 'Falha ao gerar chaves (WireGuard instalado?).'];
        }

        return ['success' => true, 'chave_privada' => $privada, 'chave_publica' => $publica];
    }

    private function statusConexao(string $nome): bool
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/vpn_wireguard_saida_web.sh', ['status', $nome]);

        foreach (explode("\n", trim($resultado['output'])) as $linha) {
            if (str_starts_with(trim($linha), 'ATIVO|')) {
                return trim($linha) === 'ATIVO|1';
            }
        }

        return false;
    }

    private function chamar(string $acao, string $nome): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/vpn_wireguard_saida_web.sh', [$acao, $nome]);
        $dados = json_decode(trim($resultado['output']), true);

        return is_array($dados) ? $dados : ['success' => false, 'message' => $resultado['output']];
    }
}

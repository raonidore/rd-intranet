<?php

namespace App\Services;

use App\Repositories\VpnOpenvpnSaidaRepository;

/**
 * "Conexões de saída": este servidor como CLIENTE de um OpenVPN de
 * terceiros (provedor comercial, matriz, outro servidor RD Intranet).
 * Diferente do modo servidor, não tem PKI própria aqui -- o .ovpn já
 * vem pronto (com credenciais embutidas) de quem administra o servidor
 * remoto, então só guarda/aplica/gerencia o serviço.
 */
class VpnOpenvpnSaidaService
{
    private LinuxService $linux;
    private VpnOpenvpnSaidaRepository $repo;

    public function __construct()
    {
        $this->linux = new LinuxService();
        $this->repo = new VpnOpenvpnSaidaRepository();
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
    public function criar(string $nome, string $conteudoOvpn): array
    {
        $nome = trim($nome);
        if (!preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $nome)) {
            return ['success' => false, 'message' => 'Nome inválido (use letras, números, "-" ou "_").'];
        }
        if ($this->repo->buscarPorNome($nome)) {
            return ['success' => false, 'message' => 'Já existe uma conexão com este nome.'];
        }
        if (trim($conteudoOvpn) === '') {
            return ['success' => false, 'message' => 'Cole o conteúdo do arquivo .ovpn fornecido pelo servidor remoto.'];
        }
        if (!str_contains($conteudoOvpn, 'remote ')) {
            return ['success' => false, 'message' => 'Arquivo .ovpn não parece válido (falta a diretiva "remote").'];
        }

        $criptografado = CryptoService::encriptar($conteudoOvpn);
        $id = $this->repo->criar($nome, $criptografado);

        $deploy = $this->aplicarNoDisco($nome, $conteudoOvpn);
        if (!$deploy['success']) {
            $this->repo->excluir($id);

            return $deploy;
        }

        return ['success' => true, 'message' => 'Conexão criada. Use "Conectar" para ativar o túnel.'];
    }

    private function aplicarNoDisco(string $nome, string $conteudoOvpn): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'rd_ovpn_saida_');
        file_put_contents($tmp, $conteudoOvpn);

        $resultado = $this->linux->executarScript(
            '/opt/rdtecnologia/scripts/vpn_openvpn_saida_web.sh',
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

    private function statusConexao(string $nome): bool
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/vpn_openvpn_saida_web.sh', ['status', $nome]);

        foreach (explode("\n", trim($resultado['output'])) as $linha) {
            if (str_starts_with(trim($linha), 'ATIVO|')) {
                return trim($linha) === 'ATIVO|1';
            }
        }

        return false;
    }

    private function chamar(string $acao, string $nome): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/vpn_openvpn_saida_web.sh', [$acao, $nome]);
        $dados = json_decode(trim($resultado['output']), true);

        return is_array($dados) ? $dados : ['success' => false, 'message' => $resultado['output']];
    }
}

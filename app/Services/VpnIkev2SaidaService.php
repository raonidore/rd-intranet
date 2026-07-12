<?php

namespace App\Services;

use App\Repositories\VpnIkev2SaidaRepository;

/**
 * "Conexões de saída" do IKEv2: este servidor como INICIADOR de um
 * túnel IPsec pra um gateway remoto. Ao contrário do OpenVPN/WireGuard
 * (interface/unidade systemd própria por conexão), o strongSwan roda
 * tudo no mesmo daemon/arquivo -- por isso toda mudança aqui delega a
 * regeneração completa pra VpnIkev2Service::regenerarEAplicar(), que
 * já combina servidor + todas as saídas num ipsec.conf/secrets só.
 */
class VpnIkev2SaidaService
{
    private LinuxService $linux;
    private VpnIkev2SaidaRepository $repo;
    private VpnIkev2Service $ikev2;

    public function __construct()
    {
        $this->linux = new LinuxService();
        $this->repo = new VpnIkev2SaidaRepository();
        $this->ikev2 = new VpnIkev2Service();
    }

    public function listar(): array
    {
        $conexoes = $this->repo->listar();

        foreach ($conexoes as &$c) {
            $c['ativo'] = $this->statusConexao('saida-' . $c['nome']);
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
    public function criar(array $dados): array
    {
        $nome = trim($dados['nome'] ?? '');
        if (!preg_match('/^[a-zA-Z0-9_-]{1,50}$/', $nome)) {
            return ['success' => false, 'message' => 'Nome inválido (letras, números, "-" ou "_").'];
        }
        if ($this->repo->buscarPorNome($nome)) {
            return ['success' => false, 'message' => 'Já existe uma conexão com este nome.'];
        }

        $servidorRemoto = trim($dados['servidor_remoto'] ?? '');
        if ($servidorRemoto === '') {
            return ['success' => false, 'message' => 'Informe o endereço do servidor remoto.'];
        }

        $subnetRemota = trim($dados['subnet_remota'] ?? '') ?: '0.0.0.0/0';
        $tipoAuth = ($dados['tipo_auth'] ?? 'psk') === 'eap' ? 'eap' : 'psk';

        $usuarioEap = null;
        $segredo = '';
        $caRemota = null;

        if ($tipoAuth === 'eap') {
            $usuarioEap = trim($dados['usuario_eap'] ?? '');
            $segredo = $dados['senha'] ?? '';
            if ($usuarioEap === '' || trim($segredo) === '') {
                return ['success' => false, 'message' => 'Informe usuário e senha EAP.'];
            }
            $caRemota = trim($dados['ca_remota'] ?? '') ?: null;
            if ($caRemota === null) {
                return ['success' => false, 'message' => 'Cole o certificado da CA do servidor remoto (necessário pra validar a identidade dele em autenticação EAP).'];
            }
        } else {
            $segredo = $dados['psk'] ?? '';
            if (trim($segredo) === '') {
                return ['success' => false, 'message' => 'Informe a chave pré-compartilhada (PSK).'];
            }
        }

        $id = $this->repo->criar([
            'nome' => $nome,
            'servidor_remoto' => $servidorRemoto,
            'tipo_auth' => $tipoAuth,
            'segredo' => CryptoService::encriptar($segredo),
            'usuario_eap' => $usuarioEap,
            'subnet_remota' => $subnetRemota,
            'ca_remota' => $caRemota,
        ]);

        $resultado = $this->ikev2->regenerarEAplicar();
        if (!$resultado['success']) {
            $this->repo->excluir($id);

            return $resultado;
        }

        return ['success' => true, 'message' => 'Conexão criada. Use "Conectar" para ativar o túnel.'];
    }

    public function conectar(int $id): array
    {
        $conexao = $this->repo->buscar($id);
        if (!$conexao) {
            return ['success' => false, 'message' => 'Conexão não encontrada.'];
        }

        return $this->chamarConexao('up', 'saida-' . $conexao['nome']);
    }

    public function desconectar(int $id): array
    {
        $conexao = $this->repo->buscar($id);
        if (!$conexao) {
            return ['success' => false, 'message' => 'Conexão não encontrada.'];
        }

        return $this->chamarConexao('down', 'saida-' . $conexao['nome']);
    }

    public function alternarAtivoNoBoot(int $id, bool $ativo): array
    {
        $conexao = $this->repo->buscar($id);
        if (!$conexao) {
            return ['success' => false, 'message' => 'Conexão não encontrada.'];
        }

        $this->repo->marcarAtivoNoBoot($id, $ativo);

        return $this->ikev2->regenerarEAplicar();
    }

    public function remover(int $id): array
    {
        $conexao = $this->repo->buscar($id);
        if (!$conexao) {
            return ['success' => false, 'message' => 'Conexão não encontrada.'];
        }

        $this->chamarConexao('down', 'saida-' . $conexao['nome']);
        $this->repo->excluir($id);

        return $this->ikev2->regenerarEAplicar();
    }

    private function statusConexao(string $nomeConn): bool
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/vpn_ikev2_conexao_web.sh', ['status', $nomeConn]);

        foreach (explode("\n", trim($resultado['output'])) as $linha) {
            if (str_starts_with(trim($linha), 'ATIVO|')) {
                return trim($linha) === 'ATIVO|1';
            }
        }

        return false;
    }

    private function chamarConexao(string $acao, string $nomeConn): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/vpn_ikev2_conexao_web.sh', [$acao, $nomeConn]);
        $dados = json_decode(trim($resultado['output']), true);

        return is_array($dados) ? $dados : ['success' => false, 'message' => $resultado['output']];
    }
}

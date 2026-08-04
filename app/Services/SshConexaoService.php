<?php

namespace App\Services;

use App\Repositories\SshConexaoRepository;

/**
 * Guarda credenciais de servidores (locais ou atrás de NAT, alcançáveis
 * por uma VPN de Saída já conectada -- ver módulo VPN > Saída, que dá a
 * este servidor rota de rede pra dentro da rede remota; nada aqui cuida
 * de conectividade, só de abrir terminal uma vez que o host já é
 * alcançável) e abre terminal pelo navegador via GuacdGatewayService
 * (mesma infraestrutura já usada pelo RDP em Ativos, só trocando
 * "type" de rdp pra ssh).
 *
 * Padrão de cifragem idêntico ao DbConexaoService (senha_cifrada via
 * CryptoService, nunca devolvida em claro por leitura comum) -- estendido
 * pra também aceitar chave privada (PEM colado, com passphrase opcional),
 * já que servidor Linux tipicamente usa chave, não senha.
 */
class SshConexaoService
{
    private SshConexaoRepository $repository;
    private GuacdGatewayService $gateway;

    public function __construct()
    {
        $this->repository = new SshConexaoRepository();
        $this->gateway = new GuacdGatewayService();
    }

    public function listar(): array
    {
        return $this->repository->listar();
    }

    /** Sem os campos cifrados -- pra preencher o formulário/mostrar os dados já salvos. */
    public function buscar(int $id): ?array
    {
        $item = $this->repository->buscarPorId($id);
        if ($item) {
            unset($item['senha_cifrada'], $item['chave_privada_cifrada'], $item['chave_privada_senha_cifrada']);
        }

        return $item;
    }

    public function criar(array $dados): bool
    {
        [$nome, $host, $porta, $usuario] = $this->validarDadosBasicos($dados);
        if ($nome === null) {
            return false;
        }

        $credencial = $this->validarCredencial($dados, null);
        if ($credencial === null) {
            return false;
        }

        $this->repository->criar(array_merge($credencial, [
            'nome' => $nome,
            'host' => $host,
            'porta' => $porta,
            'usuario' => $usuario,
            'observacoes' => trim($dados['observacoes'] ?? ''),
        ]));

        return true;
    }

    public function atualizar(int $id, array $dados): bool
    {
        [$nome, $host, $porta, $usuario] = $this->validarDadosBasicos($dados);
        if ($nome === null) {
            return false;
        }

        if (!$this->repository->buscarPorId($id)) {
            NotificationService::error('Conexão não encontrada.');
            return false;
        }

        $this->repository->atualizar($id, [
            'nome' => $nome,
            'host' => $host,
            'porta' => $porta,
            'usuario' => $usuario,
            'observacoes' => trim($dados['observacoes'] ?? ''),
        ]);

        return true;
    }

    public function redefinirCredencial(int $id, array $dados): bool
    {
        $existente = $this->repository->buscarPorId($id);
        if (!$existente) {
            NotificationService::error('Conexão não encontrada.');
            return false;
        }

        $credencial = $this->validarCredencial($dados, $existente);
        if ($credencial === null) {
            return false;
        }

        $this->repository->atualizarCredencial(
            $id,
            $credencial['tipo_autenticacao'],
            $credencial['senha_cifrada'],
            $credencial['chave_privada_cifrada'],
            $credencial['chave_privada_senha_cifrada']
        );

        return true;
    }

    public function ativar(int $id): void
    {
        $this->repository->definirAtivo($id, true);
    }

    public function desativar(int $id): void
    {
        $this->repository->definirAtivo($id, false);
    }

    public function excluir(int $id): bool
    {
        return $this->repository->excluir($id);
    }

    /**
     * Só confere se a porta está alcançável (TCP) -- não valida login,
     * já que este projeto não tem nenhuma biblioteca de cliente SSH (o
     * guacd é quem autentica de verdade, na hora de abrir o terminal).
     * Ainda assim é um teste útil: distingue "host/porta errados ou fora
     * do ar" (não alcança nem a porta) de "credencial errada" (só se
     * descobre ao abrir o terminal).
     */
    public function testar(int $id): array
    {
        $conexao = $this->repository->buscarPorId($id);
        if (!$conexao) {
            return ['success' => false, 'message' => 'Conexão não encontrada.'];
        }

        $erro = null;
        $conector = @fsockopen($conexao['host'], (int)$conexao['porta'], $codigoErro, $mensagemErro, 5);

        if ($conector === false) {
            return ['success' => false, 'message' => "Não foi possível alcançar {$conexao['host']}:{$conexao['porta']} -- {$mensagemErro}"];
        }

        fclose($conector);

        return ['success' => true, 'message' => "Porta {$conexao['porta']} alcançável em {$conexao['host']}. O login em si só é validado ao abrir o terminal."];
    }

    /**
     * Token único de conexão pro guacamole-lite -- mesmo mecanismo do RDP
     * (Ativos), delegado a GuacdGatewayService::gerarToken(), só trocando
     * o "type" e os "settings" pros equivalentes de SSH.
     *
     * $largura/$altura -- tamanho (em pixels CSS) da área do modal no
     * navegador, mesmo raciocínio do RDP (clampados, nunca confiar em
     * número vindo do front sem limite).
     */
    /**
     * $senhaDigitada -- só usada quando tipo_autenticacao === 'perguntar'
     * (conexão cadastrada de propósito sem credencial salva; o usuário
     * digita a senha a cada conexão, nunca fica gravada neste servidor).
     */
    public function gerarToken(int $id, int $largura = 1024, int $altura = 768, ?string $senhaDigitada = null): ?string
    {
        $item = $this->repository->buscarPorId($id);
        if ($item === null) {
            return null;
        }

        $settings = [
            'hostname' => $item['host'],
            'port' => (string)$item['porta'],
            'username' => $item['usuario'],
            'width' => (string)max(640, min(3840, $largura)),
            'height' => (string)max(480, min(2160, $altura)),
            'dpi' => '96',
            'font-size' => '12',
            'color-scheme' => 'gray-black',
            // permite subir/baixar arquivo pelo próprio terminal (menu
            // lateral do Guacamole) sem precisar de scp/sftp separado
            'enable-sftp' => 'true',
        ];

        try {
            if ($item['tipo_autenticacao'] === 'perguntar') {
                if ($senhaDigitada === null || $senhaDigitada === '') {
                    return null;
                }
                $settings['password'] = $senhaDigitada;
            } elseif ($item['tipo_autenticacao'] === 'chave_privada') {
                if (empty($item['chave_privada_cifrada'])) {
                    return null;
                }
                $settings['private-key'] = CryptoService::decriptar($item['chave_privada_cifrada']);
                if (!empty($item['chave_privada_senha_cifrada'])) {
                    $settings['passphrase'] = CryptoService::decriptar($item['chave_privada_senha_cifrada']);
                }
            } else {
                if (empty($item['senha_cifrada'])) {
                    return null;
                }
                $settings['password'] = CryptoService::decriptar($item['senha_cifrada']);
            }
        } catch (\RuntimeException $e) {
            return null;
        }

        return $this->gateway->gerarToken(['type' => 'ssh', 'settings' => $settings]);
    }

    private function validarDadosBasicos(array $dados): array
    {
        $nome = trim($dados['nome'] ?? '');
        $host = trim($dados['host'] ?? '');
        $porta = (int)($dados['porta'] ?? 22);
        $usuario = trim($dados['usuario'] ?? '');

        if ($nome === '' || $host === '' || $usuario === '') {
            NotificationService::error('Preencha nome, host e usuário.');
            return [null, null, null, null];
        }

        if ($porta < 1 || $porta > 65535) {
            NotificationService::error('Porta inválida.');
            return [null, null, null, null];
        }

        return [$nome, $host, $porta, $usuario];
    }

    /**
     * @return array{tipo_autenticacao: string, senha_cifrada: ?string, chave_privada_cifrada: ?string, chave_privada_senha_cifrada: ?string}|null
     */
    private function validarCredencial(array $dados, ?array $existente): ?array
    {
        $tipoRaw = $dados['tipo_autenticacao'] ?? '';
        $tipo = in_array($tipoRaw, ['chave_privada', 'perguntar'], true) ? $tipoRaw : 'senha';
        $senha = trim($dados['senha'] ?? '');
        $chave = trim($dados['chave_privada'] ?? '');
        $chaveSenha = trim($dados['chave_privada_senha'] ?? '');

        // Nada pra cifrar/guardar -- o usuário digita a senha a cada
        // conexão (ver SshConexaoService::gerarToken()); é a única opção
        // deste trio que aceita qualquer coisa nos campos de senha/chave
        // sem validar, já que eles nem existem no formulário pra esse tipo.
        if ($tipo === 'perguntar') {
            return [
                'tipo_autenticacao' => 'perguntar',
                'senha_cifrada' => null,
                'chave_privada_cifrada' => null,
                'chave_privada_senha_cifrada' => null,
            ];
        }

        if ($tipo === 'senha') {
            if ($senha === '') {
                if ($existente && $existente['tipo_autenticacao'] === 'senha' && $existente['senha_cifrada']) {
                    return [
                        'tipo_autenticacao' => 'senha',
                        'senha_cifrada' => $existente['senha_cifrada'],
                        'chave_privada_cifrada' => null,
                        'chave_privada_senha_cifrada' => null,
                    ];
                }
                NotificationService::error('Informe a senha SSH.');
                return null;
            }

            return [
                'tipo_autenticacao' => 'senha',
                'senha_cifrada' => CryptoService::encriptar($senha),
                'chave_privada_cifrada' => null,
                'chave_privada_senha_cifrada' => null,
            ];
        }

        // tipo_autenticacao === 'chave_privada'
        if ($chave === '') {
            if ($existente && $existente['tipo_autenticacao'] === 'chave_privada' && $existente['chave_privada_cifrada']) {
                return [
                    'tipo_autenticacao' => 'chave_privada',
                    'senha_cifrada' => null,
                    'chave_privada_cifrada' => $existente['chave_privada_cifrada'],
                    'chave_privada_senha_cifrada' => $chaveSenha !== '' ? CryptoService::encriptar($chaveSenha) : ($existente['chave_privada_senha_cifrada'] ?? null),
                ];
            }
            NotificationService::error('Cole a chave privada SSH.');
            return null;
        }

        return [
            'tipo_autenticacao' => 'chave_privada',
            'senha_cifrada' => null,
            'chave_privada_cifrada' => CryptoService::encriptar($chave),
            'chave_privada_senha_cifrada' => $chaveSenha !== '' ? CryptoService::encriptar($chaveSenha) : null,
        ];
    }
}

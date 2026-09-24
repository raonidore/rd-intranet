<?php

namespace App\Services;

use App\Repositories\CofreSenhaRepository;

/**
 * Cofre de senhas "genéricas" (site, wifi, painel de cliente etc) que um
 * humano precisa consultar depois -- diferente de SshConexaoService/
 * DbConexaoService, cujas credenciais são write-only e nunca reexibidas.
 * Mesmo padrão de cifragem (CryptoService), mas aqui existe um método
 * (revelar()) que decripta de propósito pra mostrar na tela -- por isso é
 * o único módulo do sistema a auditar leitura de segredo, não só escrita.
 *
 * Cada item é pessoal (cofre_id NULL, só o dono acessa) ou de um cofre de
 * equipe (cofre_id preenchido, visibilidade resolvida por
 * CofrePermissaoService -- ver/editar/excluir por usuário ou grupo).
 */
class CofreSenhaService
{
    private CofreSenhaRepository $repository;
    private CofrePermissaoService $permissaoService;

    public function __construct()
    {
        $this->repository = new CofreSenhaRepository();
        $this->permissaoService = new CofrePermissaoService();
    }

    /** @return array{pessoal: array, cofres: array, itensPorCofre: array} */
    public function listar(int $usuarioId): array
    {
        $cofreIds = $this->permissaoService->cofresVisiveis($usuarioId);
        $itensDeCofres = $this->repository->listarDeCofres($cofreIds);

        $itensPorCofre = [];
        foreach ($itensDeCofres as $item) {
            $itensPorCofre[$item['cofre_id']][] = $item;
        }

        return [
            'pessoal' => $this->repository->listarPessoais($usuarioId),
            'cofres' => (new CofreService())->buscarVarios($cofreIds),
            'itensPorCofre' => $itensPorCofre,
        ];
    }

    /**
     * Sem senha_cifrada -- pra preencher formulário/exibir dados salvos.
     * Retorna null também quando o item existe mas o usuário não pode
     * vê-lo -- controller trata igual a "não encontrado".
     */
    public function buscar(int $id, int $usuarioId): ?array
    {
        $item = $this->repository->buscarPorId($id);
        if (!$item || !$this->podeVer($item, $usuarioId)) {
            return null;
        }

        unset($item['senha_cifrada']);

        return $item;
    }

    public function criar(array $dados, int $usuarioId): bool
    {
        $validado = $this->validar($dados, null, $usuarioId);
        if ($validado === null) {
            return false;
        }

        $this->repository->criar(array_merge($validado, ['usuario_id_dono' => $usuarioId]));

        return true;
    }

    public function atualizar(int $id, array $dados, int $usuarioId): bool
    {
        $existente = $this->repository->buscarPorId($id);
        if (!$existente || !$this->podeEditar($existente, $usuarioId)) {
            NotificationService::error('Segredo não encontrado.');
            return false;
        }

        $validado = $this->validar($dados, $existente, $usuarioId);
        if ($validado === null) {
            return false;
        }

        $this->repository->atualizar($id, $validado);

        $novaSenha = trim($dados['senha'] ?? '');
        if ($novaSenha !== '') {
            $this->repository->atualizarSenha($id, CryptoService::encriptar($novaSenha));
        }

        return true;
    }

    public function excluir(int $id, int $usuarioId): bool
    {
        $existente = $this->repository->buscarPorId($id);
        if (!$existente || !$this->podeExcluir($existente, $usuarioId)) {
            return false;
        }

        return $this->repository->excluir($id);
    }

    /**
     * Único ponto do módulo que decripta a senha -- sempre que for
     * chamado, quem chamou (a Controller) deve gravar auditoria de
     * leitura logo em seguida. Retorna null se não encontrado/sem
     * permissão de ver, pra controller responder sem decriptar nada.
     */
    public function revelar(int $id, int $usuarioId): ?array
    {
        $item = $this->repository->buscarPorId($id);
        if (!$item || !$this->podeVer($item, $usuarioId)) {
            return null;
        }

        return [
            'id' => $item['id'],
            'nome' => $item['nome'],
            'senha' => CryptoService::decriptar($item['senha_cifrada']),
        ];
    }

    /** cofre_id NULL -- pessoal, só o dono; senão delega pro cofre de equipe. */
    private function podeVer(array $item, int $usuarioId): bool
    {
        if ($item['cofre_id'] === null) {
            return (int)$item['usuario_id_dono'] === $usuarioId;
        }

        return $this->permissaoService->podeVisualizar((int)$item['cofre_id'], $usuarioId);
    }

    private function podeEditar(array $item, int $usuarioId): bool
    {
        if ($item['cofre_id'] === null) {
            return (int)$item['usuario_id_dono'] === $usuarioId;
        }

        return $this->permissaoService->podeEditar((int)$item['cofre_id'], $usuarioId);
    }

    /**
     * Excluir é uma flag independente de editar num cofre de equipe --
     * alguém com pode_editar=1, pode_excluir=0 não pode apagar o item,
     * mesmo podendo editá-lo.
     */
    private function podeExcluir(array $item, int $usuarioId): bool
    {
        if ($item['cofre_id'] === null) {
            return (int)$item['usuario_id_dono'] === $usuarioId;
        }

        return $this->permissaoService->podeExcluir((int)$item['cofre_id'], $usuarioId);
    }

    private function validar(array $dados, ?array $existente, int $usuarioId): ?array
    {
        $nome = trim($dados['nome'] ?? '');
        $categoria = trim($dados['categoria'] ?? '') ?: 'Geral';
        $usuarioLogin = trim($dados['usuario_login'] ?? '');
        $urlHost = trim($dados['url_host'] ?? '');
        $observacoes = trim($dados['observacoes'] ?? '');
        $senha = trim($dados['senha'] ?? '');

        if ($nome === '') {
            NotificationService::error('Informe o nome do segredo.');
            return null;
        }

        if ($existente === null && $senha === '') {
            NotificationService::error('Informe a senha.');
            return null;
        }

        $resultado = [
            'nome' => $nome,
            'categoria' => $categoria,
            'usuario_login' => $usuarioLogin,
            'url_host' => $urlHost,
            'observacoes' => $observacoes,
        ];

        if ($existente === null) {
            // cofre_id só é decidido na criação -- mover de cofre depois fica
            // fora de escopo (reduz superfície de abuso: editar num cofre A
            // pra "roubar" visibilidade/edição em B).
            $cofreId = !empty($dados['cofre_id']) ? (int)$dados['cofre_id'] : null;
            if ($cofreId !== null && !$this->permissaoService->podeEditar($cofreId, $usuarioId)) {
                NotificationService::error('Você não tem permissão de editar itens nesse cofre.');
                return null;
            }

            $resultado['cofre_id'] = $cofreId;
            $resultado['senha_cifrada'] = CryptoService::encriptar($senha);
        }

        return $resultado;
    }
}

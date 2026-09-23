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
 */
class CofreSenhaService
{
    private CofreSenhaRepository $repository;

    public function __construct()
    {
        $this->repository = new CofreSenhaRepository();
    }

    public function listar(int $usuarioId): array
    {
        return $this->repository->listarVisiveis($usuarioId);
    }

    /**
     * Sem senha_cifrada -- pra preencher formulário/exibir dados salvos.
     * Retorna null também quando o item existe mas é privado de outro
     * dono -- controller trata igual a "não encontrado".
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
        $validado = $this->validar($dados, null);
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

        $validado = $this->validar($dados, $existente);
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
        if (!$existente || !$this->podeEditar($existente, $usuarioId)) {
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

    /** privado=0 (compartilhado) -- todo mundo com acesso ao módulo vê; privado=1 -- só o dono. */
    private function podeVer(array $item, int $usuarioId): bool
    {
        return (int)$item['privado'] === 0 || (int)$item['usuario_id_dono'] === $usuarioId;
    }

    /**
     * Editar/excluir é mais restrito que ver: mesmo um segredo
     * compartilhado (privado=0) só pode ser editado/excluído pelo dono --
     * senão qualquer um com acesso ao módulo poderia apagar segredo alheio.
     */
    private function podeEditar(array $item, int $usuarioId): bool
    {
        return (int)$item['usuario_id_dono'] === $usuarioId;
    }

    private function validar(array $dados, ?array $existente): ?array
    {
        $nome = trim($dados['nome'] ?? '');
        $categoria = trim($dados['categoria'] ?? '') ?: 'Geral';
        $usuarioLogin = trim($dados['usuario_login'] ?? '');
        $urlHost = trim($dados['url_host'] ?? '');
        $observacoes = trim($dados['observacoes'] ?? '');
        $privado = !empty($dados['privado']);
        $senha = trim($dados['senha'] ?? '');

        if ($nome === '') {
            NotificationService::error('Informe o nome do segredo.');
            return null;
        }

        if ($existente === null && $senha === '') {
            NotificationService::error('Informe a senha.');
            return null;
        }

        return [
            'nome' => $nome,
            'categoria' => $categoria,
            'usuario_login' => $usuarioLogin,
            'url_host' => $urlHost,
            'observacoes' => $observacoes,
            'privado' => $privado,
            'senha_cifrada' => $existente === null ? CryptoService::encriptar($senha) : null,
        ];
    }
}

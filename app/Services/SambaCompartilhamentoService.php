<?php

namespace App\Services;

use App\Repositories\BackupDestinoRepository;
use App\Repositories\SambaCompartilhamentoExcecaoRepository;
use App\Repositories\SambaCompartilhamentoRepository;
use App\Repositories\SambaUsuarioRepository;

class SambaCompartilhamentoService
{
    private SambaCompartilhamentoRepository $repository;
    private SambaUsuarioRepository $usuarioRepository;
    private LinuxService $linux;

    public function __construct()
    {
        $this->repository = new SambaCompartilhamentoRepository();
        $this->usuarioRepository = new SambaUsuarioRepository();
        $this->linux = new LinuxService();
    }

    public function listar(): array
    {
        return $this->repository->listar();
    }

    public function dashboard(): array
    {
        return [
            'total' => $this->repository->contarTotal(),
            'ativos' => $this->repository->contarAtivos(),
            'lixeira' => $this->repository->contarComLixeira(),
            'bloqueio_extensoes' => $this->repository->contarComBloqueioExtensoes(),
            'backup_nuvem' => $this->repository->contarComBackupAtivo(),
        ];
    }

    public function alternarBackup(int $id): void
    {
        $compartilhamento = $this->buscar($id);

        if (!$compartilhamento) {
            NotificationService::error('Compartilhamento não encontrado.');
            return;
        }

        $this->repository->alternarBackup($id);

        $ativo = !(int)$compartilhamento['backup_nuvem_ativo'];

        AuditService::registrar(
            'Samba',
            'Backup em nuvem',
            ($ativo ? 'Ativado' : 'Desativado') . ' backup em nuvem para ' . $compartilhamento['nome'] . '.'
        );

        // Ativar a flag sem nenhum destino de nuvem configurado nao tem
        // efeito nenhum ate que um exista (BackupService::executarAgora()
        // so olha compartilhamentos ativos NA HORA de rodar) -- avisa pra
        // nao passar a impressao de que o backup ja esta acontecendo.
        if ($ativo && empty((new BackupDestinoRepository())->listar())) {
            NotificationService::success(
                'Backup em nuvem ativado para ' . $compartilhamento['nome'] . '. '
                . 'Atenção: nenhum destino de backup foi configurado ainda -- '
                . 'vá em Backup > Configuração para cadastrar um, senão nada será enviado.'
            );
            return;
        }

        NotificationService::success('Backup em nuvem ' . ($ativo ? 'ativado' : 'desativado') . ' para ' . $compartilhamento['nome'] . '.');
    }

    public function buscar(int $id): ?array
    {
        return $this->repository->buscarPorId($id);
    }

    public function usuariosDisponiveis(): array
    {
        return $this->usuarioRepository->listar();
    }

    public function usuariosAutorizados(int $id): array
    {
        return $this->repository->usuariosAutorizados($id);
    }

    public function criar(array $dados): bool
    {
        if ($this->repository->buscarPorNome($dados['nome'])) {
            NotificationService::error('Já existe um compartilhamento com este nome.');
            return false;
        }

        $resultadoLinux = $this->linux->executarScript(
            '/opt/rdtecnologia/scripts/cria_compartilhamento_samba_web.sh',
            [
                $dados['nome'],
                $dados['caminho'],
                $dados['grupo']
            ]
        );

        if (!$resultadoLinux['success']) {
            NotificationService::error('Erro ao criar a estrutura Linux.', $resultadoLinux['output']);
            return false;
        }

        $this->repository->criar($dados);

        (new DeployCenterService())->marcarPendente(
            'samba',
            'Compartilhamento',
            $dados['nome'],
            'Novo compartilhamento criado: '.$dados['nome'].' em '.$dados['caminho'].'.'
        );

        AuditService::registrar(
            'Samba',
            'Criar Compartilhamento',
            'Compartilhamento '.$dados['nome'].' criado e aguardando deploy.'
        );

        NotificationService::success(
            'Compartilhamento criado com sucesso. A configuração ainda precisa ser aplicada no Deploy Center.',
            $resultadoLinux['output']
        );

        return true;
    }

    public function editar(int $id, array $dados): bool
    {
        $compartilhamento = $this->buscar($id);

        if (!$compartilhamento) {
            NotificationService::error('Compartilhamento não encontrado.');
            return false;
        }

        if ($dados['grupo'] !== $compartilhamento['grupo']) {
            $resultadoLinux = $this->linux->executarScript(
                '/opt/rdtecnologia/scripts/altera_grupo_compartilhamento_web.sh',
                [$compartilhamento['caminho'], $dados['grupo']]
            );

            if (!$resultadoLinux['success']) {
                NotificationService::error('Erro ao alterar o grupo no sistema.', $resultadoLinux['output']);
                return false;
            }
        }

        $this->repository->atualizar($id, $dados);

        (new DeployCenterService())->marcarPendente(
            'samba',
            'Compartilhamento',
            $dados['nome'],
            'Compartilhamento atualizado: '.$dados['nome'].'.'
        );

        AuditService::registrar(
            'Samba',
            'Editar Compartilhamento',
            'Compartilhamento '.$compartilhamento['nome'].' atualizado.'
        );

        NotificationService::success('Compartilhamento atualizado. Alterações pendentes para deploy.');

        return true;
    }

    public function atualizarSeguranca(int $id, array $dados): bool
    {
        $compartilhamento = $this->buscar($id);

        if (!$compartilhamento) {
            NotificationService::error('Compartilhamento não encontrado.');
            return false;
        }

        $this->repository->atualizarSeguranca($id, $dados);

        (new DeployCenterService())->marcarPendente(
            'samba',
            'Segurança',
            $compartilhamento['nome'],
            'Políticas de segurança atualizadas para '.$compartilhamento['nome'].'.'
        );

        AuditService::registrar(
            'Samba',
            'Segurança Compartilhamento',
            'Segurança do compartilhamento '.$compartilhamento['nome'].' atualizada.'
        );

        NotificationService::success('Segurança atualizada. Alterações pendentes para deploy.');

        return true;
    }

    /*
     |---------------------------------------------------------
     | Exceções por pasta (extensões liberadas numa subpasta)
     |---------------------------------------------------------
     */

    private const BASE_COMPARTILHAMENTOS = '/srv/samba/Compartilhamentos';

    public function listarExcecoes(int $id): array
    {
        return (new SambaCompartilhamentoExcecaoRepository())->listarPorCompartilhamento($id);
    }

    /** @param string[] $extensoes */
    public function criarExcecao(int $id, string $pasta, string $nome, array $extensoes): bool
    {
        $compartilhamento = $this->buscar($id);

        if (!$compartilhamento) {
            NotificationService::error('Compartilhamento não encontrado.');
            return false;
        }

        // Caminho relativo à raiz do compartilhamento. "%" vira macro no
        // smb.conf (%U, %m...) e quebra de linha abriria outra diretiva.
        $pasta = trim(str_replace('\\', '/', $pasta), "/ \t");
        if ($pasta === '' || preg_match('#(^|/)\.\.?(/|$)|[%\x00-\x1f]#', $pasta) || strlen($pasta) > 200) {
            NotificationService::error('Pasta inválida. Informe o caminho dentro do compartilhamento, ex.: arbackup ou TI/instaladores.');
            return false;
        }

        if ($nome === '') {
            $nome = $compartilhamento['nome'] . '-' . basename($pasta);
        }
        $nome = preg_replace('/[^A-Za-z0-9_-]/', '', $nome);

        if ($nome === '' || strlen($nome) > 80 || in_array(strtolower($nome), ['global', 'homes', 'printers', 'print$', 'ipc$'], true)) {
            NotificationService::error('Nome de compartilhamento inválido.');
            return false;
        }

        $excecoes = new SambaCompartilhamentoExcecaoRepository();
        if ($this->repository->buscarPorNome($nome) || $excecoes->buscarPorNome($nome)) {
            NotificationService::error("Já existe um compartilhamento chamado {$nome}.");
            return false;
        }

        $perigosas = ExtensaoPerigosaService::listarAtivas();
        $extensoes = array_values(array_intersect($perigosas, array_map('strtolower', $extensoes)));
        if (empty($extensoes)) {
            NotificationService::error('Marque ao menos uma extensão para liberar.');
            return false;
        }

        // Só dá pra conferir se a pasta existe quando o compartilhamento está
        // na raiz padrão (é a única que o script de listagem enxerga).
        $base = self::BASE_COMPARTILHAMENTOS . '/';
        if (str_starts_with($compartilhamento['caminho'], $base)) {
            $relativo = substr(rtrim($compartilhamento['caminho'], '/'), strlen($base)) . '/' . $pasta;
            $lista = $this->linux->executarScript('/opt/rdtecnologia/scripts/lista_arquivos_samba_web.sh', [$relativo]);

            if (!$lista['success']) {
                NotificationService::error("A pasta {$pasta} não existe em {$compartilhamento['nome']}.");
                return false;
            }
        }

        $excecoes->criar($id, $pasta, $nome, implode(',', $extensoes));

        (new DeployCenterService())->marcarPendente(
            'samba',
            'Segurança',
            $compartilhamento['nome'],
            "Exceção de extensões na pasta {$pasta} ({$nome}): " . implode(', ', $extensoes) . '.'
        );

        AuditService::registrar(
            'Samba',
            'Exceção de extensões',
            "Pasta {$pasta} de {$compartilhamento['nome']} liberada para " . implode(', ', $extensoes) . " pelo compartilhamento {$nome}."
        );

        NotificationService::success("Exceção criada. Depois de aplicar no Deploy Center, acesse a pasta por \\\\servidor\\{$nome}.");

        return true;
    }

    /** @param string[] $extensoes */
    public function editarExcecao(int $excecaoId, array $extensoes): void
    {
        $excecoes = new SambaCompartilhamentoExcecaoRepository();
        $excecao = $excecoes->buscarPorId($excecaoId);

        if (!$excecao) {
            NotificationService::error('Exceção não encontrada.');
            return;
        }

        $extensoes = array_values(array_intersect(ExtensaoPerigosaService::listarAtivas(), array_map('strtolower', $extensoes)));
        if (empty($extensoes)) {
            NotificationService::error('Marque ao menos uma extensão. Para tirar a exceção inteira, use o botão de remover.');
            return;
        }

        $excecoes->atualizarExtensoes($excecaoId, implode(',', $extensoes));

        $compartilhamento = $this->buscar((int)$excecao['compartilhamento_id']);

        (new DeployCenterService())->marcarPendente(
            'samba',
            'Segurança',
            $compartilhamento['nome'] ?? $excecao['nome_compartilhamento'],
            "Exceção {$excecao['nome_compartilhamento']} ({$excecao['pasta']}) agora libera: " . implode(', ', $extensoes) . '.'
        );

        AuditService::registrar(
            'Samba',
            'Exceção de extensões',
            "Exceção {$excecao['nome_compartilhamento']} (pasta {$excecao['pasta']}): de {$excecao['extensoes']} para " . implode(',', $extensoes) . '.'
        );

        NotificationService::success('Exceção atualizada. Alterações pendentes para deploy.');
    }

    public function excluirExcecao(int $excecaoId): void
    {
        $excecoes = new SambaCompartilhamentoExcecaoRepository();
        $excecao = $excecoes->buscarPorId($excecaoId);

        if (!$excecao) {
            NotificationService::error('Exceção não encontrada.');
            return;
        }

        $compartilhamento = $this->buscar((int)$excecao['compartilhamento_id']);
        $excecoes->excluir($excecaoId);

        (new DeployCenterService())->marcarPendente(
            'samba',
            'Segurança',
            $compartilhamento['nome'] ?? $excecao['nome_compartilhamento'],
            "Exceção de extensões removida: {$excecao['nome_compartilhamento']} ({$excecao['pasta']})."
        );

        AuditService::registrar(
            'Samba',
            'Exceção de extensões',
            "Exceção {$excecao['nome_compartilhamento']} (pasta {$excecao['pasta']}) removida."
        );

        NotificationService::success('Exceção removida. Alterações pendentes para deploy.');
    }

    private const STATUS_DIR = '/var/www/rd.intranet/storage/samba_acl_status';

    /**
     * Dispara a aplicação da ACL em SEGUNDO PLANO -- pra um compartilhamento
     * com árvore grande, --propagate-inheritance (ver
     * aplicar_acl_compartilhamento_web.sh) é uma operação item a item via
     * SMB que pode levar minutos, tempo demais pra segurar a requisição
     * HTTP (foi isso, não um erro de ACL em si, que causou
     * NT_STATUS_CONNECTION_DISCONNECTED em produção quando essa chamada
     * ainda era síncrona). O estado autorizado (banco) é salvo na hora;
     * o progresso da aplicação no sistema de arquivos é acompanhado à
     * parte via statusAplicacaoAcl() (polling, ver
     * usuariosStatus()/samba_acl_status/*.json).
     */
    public function salvarUsuarios(int $id, array $post): array
    {
        $compartilhamento = $this->buscar($id);

        if (!$compartilhamento) {
            return ['success' => false, 'message' => 'Compartilhamento não encontrado.'];
        }

        $usuarios = [];
        $tokensAcl = [];

        foreach (($post['usuarios'] ?? []) as $usuarioId => $permissoes) {
            $leitura = isset($permissoes['leitura']) ? 1 : 0;
            $escrita = isset($permissoes['escrita']) ? 1 : 0;

            $usuarios[] = [
                'usuario_id' => (int)$usuarioId,
                'leitura' => $leitura,
                'escrita' => $escrita,
            ];

            if ($leitura || $escrita) {
                $usuarioSamba = $this->usuarioRepository->buscarPorId((int)$usuarioId);

                if ($usuarioSamba) {
                    $tokensAcl[] = "{$usuarioSamba['login']}:{$leitura}:{$escrita}";
                }
            }
        }

        $this->repository->salvarUsuariosAutorizados($id, $usuarios);

        $this->linux->executarScriptEmSegundoPlano(
            '/opt/rdtecnologia/scripts/aplicar_acl_compartilhamento_web.sh',
            array_merge(
                [$compartilhamento['nome'], $compartilhamento['grupo'], $compartilhamento['caminho']],
                $tokensAcl
            )
        );

        (new DeployCenterService())->marcarPendente(
            'samba',
            'Usuários',
            $compartilhamento['nome'],
            'Usuários autorizados atualizados para '.$compartilhamento['nome'].'.'
        );

        AuditService::registrar(
            'Samba',
            'Usuários Compartilhamento',
            'Usuários do compartilhamento '.$compartilhamento['nome'].' atualizados.'
        );

        return [
            'success' => true,
            'message' => 'Usuários autorizados salvos. Aplicando permissões no sistema de arquivos em segundo plano...',
            'compartilhamento' => $compartilhamento['nome'],
        ];
    }

    /**
     * Lê o status (escrito pelo próprio script em segundo plano) da última
     * aplicação de ACL de um compartilhamento. Sem arquivo ainda = nunca
     * rodou (ou o job só está começando e ainda não escreveu a primeira vez).
     */
    public function statusAplicacaoAcl(string $nomeCompartilhamento): array
    {
        $arquivo = self::STATUS_DIR . '/' . basename($nomeCompartilhamento) . '.json';

        if (!is_file($arquivo)) {
            return ['status' => 'desconhecido'];
        }

        $conteudo = json_decode((string)file_get_contents($arquivo), true);

        if (!is_array($conteudo)) {
            return ['status' => 'desconhecido'];
        }

        if (($conteudo['status'] ?? '') === 'erro') {
            $log = self::STATUS_DIR . '/' . basename($nomeCompartilhamento) . '.log';
            $saida = is_file($log) ? substr((string)file_get_contents($log), -8000) : '';

            $conteudo['saida'] = $saida;
            $conteudo['mensagem'] = str_contains($saida, 'NT_STATUS_BAD_NETWORK_NAME')
                ? 'Este compartilhamento ainda não foi aplicado na configuração do Samba -- '
                    . 'vá em Deploy (menu lateral) e clique em "Aplicar Configuração" antes '
                    . 'de definir permissões de usuário nele.'
                : 'Erro ao aplicar permissões no sistema.';
        }

        return $conteudo;
    }

    public function excluir(int $id): bool
    {
        $compartilhamento = $this->buscar($id);

        if (!$compartilhamento) {
            NotificationService::error('Compartilhamento não encontrado.');
            return false;
        }

        $this->repository->excluir($id);

        (new DeployCenterService())->marcarPendente(
            'samba',
            'Compartilhamento',
            $compartilhamento['nome'],
            'Compartilhamento removido: '.$compartilhamento['nome'].'.'
        );

        AuditService::registrar(
            'Samba',
            'Excluir Compartilhamento',
            'Compartilhamento '.$compartilhamento['nome'].' removido do cadastro.'
        );

        NotificationService::success('Compartilhamento removido do cadastro. Alterações pendentes para deploy.');

        return true;
    }
}

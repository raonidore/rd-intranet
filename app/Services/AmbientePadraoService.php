<?php

namespace App\Services;

use App\Core\Database;

/**
 * Ambiente padrão (Ativos > Ambiente padrão): o que toda máquina/usuário
 * do cliente deve ter -- programa que inicia com o Windows, atalhos de
 * sites e de sistemas na Área de Trabalho, pastas mapeadas. Configurado
 * uma vez aqui e aplicado pelo agente: o SERVIÇO (SYSTEM) cuida do que
 * vale para todos os usuários da máquina; o ÍCONE de cada sessão cuida
 * das pastas mapeadas e do que é só de um grupo do Entra. PC novo ou
 * pessoa nova não precisa de configuração manual (Enzilab, 26/10/2026).
 */
class AmbientePadraoService
{
    public const TIPOS = [
        'inicializar' => ['rotulo' => 'Iniciar com o Windows', 'icone' => 'bi-play-circle'],
        'atalho_site' => ['rotulo' => 'Atalho de site', 'icone' => 'bi-globe2'],
        'atalho_programa' => ['rotulo' => 'Atalho de sistema', 'icone' => 'bi-window-stack'],
        'unidade_mapeada' => ['rotulo' => 'Pasta mapeada', 'icone' => 'bi-hdd-network'],
        'impressora' => ['rotulo' => 'Impressora de rede', 'icone' => 'bi-printer'],
    ];

    /** Tipos que só existem no perfil de cada usuário (aplicados pelo ícone da sessão, nunca pelo serviço). */
    private const TIPOS_DO_USUARIO = ['unidade_mapeada', 'impressora'];

    private const CACHE_GRUPO_SEGUNDOS = 600;

    // \x5C = barra invertida (mais legível que escapar barras em string PHP + regex).
    private const RE_NOME_PROIBIDO = '#[\x5C/:*?"<>|]#';
    private const RE_CAMINHO_PROGRAMA = '#^([a-zA-Z]:\x5C|\x5C\x5C)[^"<>|]+$#';
    private const RE_PASTA_REDE = '#^\x5C\x5C[^\x5C/\s]+\x5C[^\x5C/].*$#';
    private const RE_IMPRESSORA = '#^\x5C\x5C[^\x5C/\s]+\x5C[^\x5C/]+$#';

    /** @return array<int, array> itens com 'dados' decodificado (sem senha) */
    public function listar(): array
    {
        $itens = Database::connection()->query('SELECT * FROM ambiente_itens ORDER BY FIELD(tipo, "inicializar", "atalho_site", "atalho_programa", "unidade_mapeada", "impressora"), nome')->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(function ($i) {
            $dados = json_decode($i['dados'], true) ?: [];
            $i['tem_senha'] = !empty($dados['senha_cifrada']);
            unset($dados['senha_cifrada']);
            $i['dados'] = $dados;
            return $i;
        }, $itens);
    }

    /** @return array{success: bool, message: string} */
    public function salvar(array $post, ?int $id, string $por): array
    {
        $tipo = (string)($post['tipo'] ?? '');
        if (!isset(self::TIPOS[$tipo])) {
            return ['success' => false, 'message' => 'Escolha o tipo do item.'];
        }

        $nome = trim((string)($post['nome'] ?? ''));
        if ($nome === '' || mb_strlen($nome) > 80 || preg_match(self::RE_NOME_PROIBIDO, $nome)) {
            return ['success' => false, 'message' => 'Nome obrigatório, até 80 caracteres, sem \\ / : * ? " < > |.'];
        }

        $anterior = $id ? $this->buscar($id) : null;
        if ($id && !$anterior) {
            return ['success' => false, 'message' => 'Item não encontrado.'];
        }
        $dadosAnteriores = $anterior ? (json_decode($anterior['dados'], true) ?: []) : [];

        $dados = [];
        switch ($tipo) {
            case 'atalho_site':
                $url = trim((string)($post['url'] ?? ''));
                if (!preg_match('#^https?://\S+$#i', $url)) {
                    return ['success' => false, 'message' => 'Informe o endereço completo do site, começando com http:// ou https://.'];
                }
                $dados['url'] = $url;
                break;

            case 'inicializar':
            case 'atalho_programa':
                $caminho = trim((string)($post['caminho'] ?? ''), " \t\"");
                if (!preg_match(self::RE_CAMINHO_PROGRAMA, $caminho)) {
                    return ['success' => false, 'message' => 'Informe o caminho completo do programa, como C:\\Program Files (x86)\\ShiftApp\\ShiftApp.exe.'];
                }
                $dados['caminho'] = $caminho;
                $dados['argumentos'] = trim((string)($post['argumentos'] ?? ''));
                if ($tipo === 'atalho_programa') {
                    $dados['pasta'] = trim((string)($post['pasta'] ?? ''), " \t\"");
                }
                break;

            case 'impressora':
                $fila = trim((string)($post['unc'] ?? ''));
                if (!preg_match(self::RE_IMPRESSORA, $fila)) {
                    return ['success' => false, 'message' => 'Informe a impressora compartilhada, como \\\\servidor\\impressora.'];
                }
                $dados['unc'] = $fila;
                break;

            case 'unidade_mapeada':
                $letra = strtoupper(trim((string)($post['letra'] ?? ''), " :"));
                if (!preg_match('/^[D-Z]$/', $letra)) {
                    return ['success' => false, 'message' => 'Escolha uma letra de unidade entre D e Z.'];
                }
                $unc = rtrim(trim((string)($post['unc'] ?? '')), '\\');
                if (!preg_match(self::RE_PASTA_REDE, $unc)) {
                    return ['success' => false, 'message' => 'Informe o caminho de rede, como \\\\servidor\\pasta.'];
                }
                $dados['letra'] = $letra;
                $dados['unc'] = $unc;
                $dados['usuario'] = trim((string)($post['usuario'] ?? ''));
                $senha = (string)($post['senha'] ?? '');
                if ($senha !== '') {
                    $dados['senha_cifrada'] = CryptoService::encriptar($senha);
                } elseif ($dados['usuario'] !== '' && !empty($dadosAnteriores['senha_cifrada']) && ($dadosAnteriores['usuario'] ?? '') === $dados['usuario']) {
                    $dados['senha_cifrada'] = $dadosAnteriores['senha_cifrada'];
                }
                if ($dados['usuario'] !== '' && empty($dados['senha_cifrada'])) {
                    return ['success' => false, 'message' => 'Informe a senha do usuário da pasta.'];
                }
                break;
        }

        $escopo = (string)($post['escopo'] ?? 'todos');
        $ref = null;
        $refNome = null;
        if ($escopo === 'setor') {
            $ref = (string)(int)($post['setor_id'] ?? 0);
            $refNome = Database::connection()->query('SELECT nome FROM ativos_catalogos WHERE tipo = "setor" AND id = ' . (int)$ref)->fetchColumn() ?: null;
            if ($refNome === null) {
                return ['success' => false, 'message' => 'Escolha o setor.'];
            }
        } elseif ($escopo === 'grupo') {
            $ref = strtolower(trim((string)($post['grupo_id'] ?? '')));
            $refNome = trim((string)($post['grupo_nome'] ?? '')) ?: null;
            if (EntraService::sidDoObjeto($ref) === null) {
                return ['success' => false, 'message' => 'Escolha o grupo do Entra.'];
            }
        } else {
            $escopo = 'todos';
        }

        $pdo = Database::connection();
        $json = json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($id) {
            $pdo->prepare('UPDATE ambiente_itens SET tipo = ?, nome = ?, dados = ?, escopo = ?, escopo_ref = ?, escopo_nome = ? WHERE id = ?')
                ->execute([$tipo, $nome, $json, $escopo, $ref, $refNome, $id]);
        } else {
            $pdo->prepare('INSERT INTO ambiente_itens (tipo, nome, dados, escopo, escopo_ref, escopo_nome, criado_por) VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([$tipo, $nome, $json, $escopo, $ref, $refNome, $por]);
        }

        AuditService::registrar('Ambiente padrão', $id ? 'Item alterado' : 'Item criado',
            self::TIPOS[$tipo]['rotulo'] . " \"{$nome}\" (" . $this->descreverEscopo($escopo, $refNome) . ').');

        return ['success' => true, 'message' => $id ? 'Item atualizado. As máquinas aplicam em até 30 minutos.' : 'Item criado. As máquinas aplicam em até 30 minutos.'];
    }

    public function alternar(int $id): void
    {
        $item = $this->buscar($id);
        if (!$item) {
            return;
        }
        Database::connection()->prepare('UPDATE ambiente_itens SET ativo = 1 - ativo WHERE id = ?')->execute([$id]);
        AuditService::registrar('Ambiente padrão', (int)$item['ativo'] ? 'Item pausado' : 'Item reativado', "\"{$item['nome']}\".");
    }

    public function excluir(int $id): void
    {
        $item = $this->buscar($id);
        if (!$item) {
            return;
        }
        Database::connection()->prepare('DELETE FROM ambiente_itens WHERE id = ?')->execute([$id]);
        AuditService::registrar('Ambiente padrão', 'Item excluído', "\"{$item['nome']}\" -- as máquinas removem o que o agente tinha criado.");
    }

    public function descreverEscopo(string $escopo, ?string $nome): string
    {
        return match ($escopo) {
            'setor' => 'setor ' . ($nome ?? '?'),
            'grupo' => 'grupo do Entra ' . ($nome ?? '?'),
            default => 'todas as máquinas e usuários',
        };
    }

    private function buscar(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM ambiente_itens WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /*
     |---------------------------------------------------------
     | Agente
     |---------------------------------------------------------
     */

    /**
     * Itens que o agente deve aplicar agora. 'maquina' = serviço (SYSTEM):
     * vale para todos os usuários (Run do HKLM, Área de Trabalho pública).
     * 'usuario' = ícone na sessão: pastas mapeadas e o que é de um grupo do Entra.
     * A senha da pasta vai cifrada com a chave do próprio agente.
     */
    public function paraAgente(array $ativo, string $contexto, string $sid, string $chaveAgente): array
    {
        $saida = [];
        foreach (Database::connection()->query('SELECT * FROM ambiente_itens WHERE ativo = 1')->fetchAll(\PDO::FETCH_ASSOC) as $item) {
            if ($item['escopo'] === 'setor' && (int)$item['escopo_ref'] !== (int)($ativo['setor_id'] ?? 0)) {
                continue;
            }

            $doUsuario = in_array($item['tipo'], self::TIPOS_DO_USUARIO, true) || $item['escopo'] === 'grupo';
            if (($contexto === 'maquina') === $doUsuario) {
                continue;
            }
            if ($item['escopo'] === 'grupo' && !in_array(strtoupper($sid), $this->sidsDoGrupo((string)$item['escopo_ref']), true)) {
                continue;
            }

            $dados = json_decode($item['dados'], true) ?: [];
            if (!empty($dados['senha_cifrada'])) {
                try {
                    $dados['senha'] = self::cifrarParaAgente(CryptoService::decriptar($dados['senha_cifrada']), $chaveAgente);
                } catch (\Throwable $e) {
                    $dados['senha'] = '';
                }
            }
            unset($dados['senha_cifrada']);

            $saida[] = ['id' => (int)$item['id'], 'tipo' => $item['tipo'], 'nome' => $item['nome']] + $dados;
        }

        return $saida;
    }

    /** AES-256-GCM com chave derivada da chave de API do agente: base64(nonce 12 | cifrado | tag 16). */
    public static function cifrarParaAgente(string $texto, string $chaveAgente): string
    {
        $chave = hash('sha256', 'rd-ambiente|' . $chaveAgente, true);
        $nonce = random_bytes(12);
        $tag = '';
        $cifrado = openssl_encrypt($texto, 'aes-256-gcm', $chave, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);

        return base64_encode($nonce . $cifrado . $tag);
    }

    /** SIDs (S-1-12-1-...) dos membros do grupo, com transitividade -- cache de 10 min por grupo. */
    private function sidsDoGrupo(string $grupoId): array
    {
        $pasta = __DIR__ . '/../../storage/cache';
        $arquivo = $pasta . '/ambiente_grupo_' . preg_replace('/[^a-f0-9-]/', '', $grupoId) . '.json';
        if (is_file($arquivo) && time() - filemtime($arquivo) < self::CACHE_GRUPO_SEGUNDOS) {
            return json_decode((string)file_get_contents($arquivo), true) ?: [];
        }

        $entra = new EntraService();
        $ids = $entra->configurado() ? $entra->usuariosAutorizados([['tipo' => 'grupo', 'id' => $grupoId]]) : null;
        if ($ids === null) {
            // Graph fora do ar: mantém o último resultado conhecido em vez de tirar os itens de todo mundo.
            return is_file($arquivo) ? (json_decode((string)file_get_contents($arquivo), true) ?: []) : [];
        }

        $sids = array_values(array_filter(array_map(fn($id) => EntraService::sidDoObjeto((string)$id), $ids)));
        $sids = array_map('strtoupper', $sids);
        if (!is_dir($pasta)) {
            @mkdir($pasta, 0775, true);
        }
        @file_put_contents($arquivo, json_encode($sids));

        return $sids;
    }

    public function registrarResultado(int $ativoId, string $contexto, string $usuario, array $itens): void
    {
        $falhas = count(array_filter($itens, fn($i) => empty($i['ok'])));
        Database::connection()->prepare('
            INSERT INTO ambiente_aplicacoes (ativo_id, contexto, usuario, resultado, falhas, aplicado_em)
            VALUES (?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE resultado = VALUES(resultado), falhas = VALUES(falhas), aplicado_em = VALUES(aplicado_em)
        ')->execute([$ativoId, $contexto, mb_substr($usuario, 0, 190), json_encode($itens, JSON_UNESCAPED_UNICODE), $falhas]);
    }

    /** Última aplicação em cada máquina/usuário (mais recentes primeiro). */
    public function aplicacoes(): array
    {
        $linhas = Database::connection()->query('
            SELECT ap.*, a.codigo_patrimonio, a.nome AS ativo_nome
            FROM ambiente_aplicacoes ap
            JOIN ativos a ON a.id = ap.ativo_id
            ORDER BY ap.falhas > 0 DESC, ap.aplicado_em DESC
            LIMIT 300
        ')->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($linhas as &$l) {
            $l['itens'] = json_decode((string)$l['resultado'], true) ?: [];
        }

        return $linhas;
    }

    public function setores(): array
    {
        return Database::connection()->query('SELECT id, nome FROM ativos_catalogos WHERE tipo = "setor" ORDER BY nome')->fetchAll(\PDO::FETCH_ASSOC);
    }
}

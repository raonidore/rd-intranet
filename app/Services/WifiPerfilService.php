<?php

namespace App\Services;

use App\Repositories\WifiRepository;

/** Perfis do portal (colaboradores, fornecedores, visitantes e os que a TI criar): todas as regras editáveis. */
class WifiPerfilService
{
    /** Campos que viram coluna em wifi_pessoas (os demais vão para dados_extra). */
    public const CAMPOS_CONHECIDOS = [
        'telefone' => 'Celular',
        'documento' => 'CPF / documento',
        'empresa' => 'Empresa',
        'cnpj' => 'CNPJ',
        'setor' => 'Setor',
        'anfitriao' => 'Quem você vai visitar / responsável',
    ];

    public const DIAS = [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb', 7 => 'Dom'];

    private WifiRepository $repo;

    public function __construct()
    {
        $this->repo = new WifiRepository();
    }

    /** Converte o POST do formulário de perfil em colunas, validando. */
    public function salvar(?int $id, array $p, int $usuarioId): array
    {
        $nome = trim((string)($p['nome'] ?? ''));
        if ($nome === '' || mb_strlen($nome) > 60) {
            return ['success' => false, 'message' => 'Informe o nome do perfil (até 60 caracteres).'];
        }
        $atual = $id ? $this->repo->perfil($id) : null;
        if ($id && !$atual) {
            return ['success' => false, 'message' => 'Perfil não encontrado.'];
        }
        $slug = $atual['slug'] ?? $this->slugUnico($nome);

        $campos = [];
        foreach (self::CAMPOS_CONHECIDOS as $campo => $rotulo) {
            if (!empty($p['campo'][$campo]['usar'])) {
                $campos[] = ['campo' => $campo, 'rotulo' => trim((string)($p['campo'][$campo]['rotulo'] ?? '')) ?: $rotulo, 'obrigatorio' => !empty($p['campo'][$campo]['obrigatorio'])];
            }
        }
        // Campos livres: um por linha; "*" no fim = obrigatório.
        $n = 0;
        foreach (preg_split('/\R/', (string)($p['campos_extras'] ?? '')) as $linha) {
            $linha = trim($linha);
            if ($linha === '') {
                continue;
            }
            $obrig = str_ends_with($linha, '*');
            $campos[] = ['campo' => 'extra_' . (++$n), 'rotulo' => mb_substr(rtrim($linha, '* '), 0, 60), 'obrigatorio' => $obrig];
        }

        $dias = array_values(array_filter(array_map('intval', (array)($p['horario_dias'] ?? [])), fn ($d) => $d >= 1 && $d <= 7));
        $horarios = null;
        if (!empty($p['horario_ativo']) && $dias) {
            $inicio = preg_match('/^\d{2}:\d{2}$/', (string)($p['horario_inicio'] ?? '')) ? $p['horario_inicio'] : '';
            $fim = preg_match('/^\d{2}:\d{2}$/', (string)($p['horario_fim'] ?? '')) ? $p['horario_fim'] : '';
            $horarios = json_encode(['dias' => $dias, 'inicio' => $inicio, 'fim' => $fim]);
        }

        $num = fn ($k, $min = 0) => ($p[$k] ?? '') === '' ? null : max($min, (int)$p[$k]);
        $mbps = fn ($k) => ($p[$k] ?? '') === '' || (float)$p[$k] <= 0 ? null : (int)round((float)str_replace(',', '.', (string)$p[$k]) * 1000);

        $d = [
            'slug' => $slug,
            'nome' => $nome,
            'descricao' => mb_substr(trim((string)($p['descricao'] ?? '')), 0, 160) ?: null,
            'cor' => preg_match('/^#[0-9a-fA-F]{6}$/', (string)($p['cor'] ?? '')) ? $p['cor'] : '#0d7b83',
            'icone' => preg_match('/^bi-[a-z0-9-]+$/', (string)($p['icone'] ?? '')) ? $p['icone'] : 'bi-person',
            'ativo' => !empty($p['ativo']) ? 1 : 0,
            'tipo_acesso' => ($p['tipo_acesso'] ?? '') === 'cadastro' ? 'cadastro' : 'senha',
            'autocadastro' => !empty($p['autocadastro']) ? 1 : 0,
            'exige_liberacao' => !empty($p['exige_liberacao']) ? 1 : 0,
            'campos' => json_encode($campos, JSON_UNESCAPED_UNICODE),
            'dominios_email' => implode(', ', WifiFiltroService::normalizarDominios((string)($p['dominios_email'] ?? ''))) ?: null,
            'validade_dias' => $num('validade_dias', 1),
            'sessao_minutos' => max(5, (int)($p['sessao_horas'] ?? 0) * 60 + (int)($p['sessao_min'] ?? 0)),
            'banda_down_kbps' => $mbps('banda_down_mbps'),
            'banda_up_kbps' => $mbps('banda_up_mbps'),
            'cota_mb' => $num('cota_mb', 1),
            'max_aparelhos' => max(1, (int)($p['max_aparelhos'] ?? 1)),
            'lembrar_dias' => max(0, (int)($p['lembrar_dias'] ?? 0)),
            'horarios' => $horarios,
            'filtro' => json_encode(self::filtroDoPost($p), JSON_UNESCAPED_UNICODE),
            'ordem' => (int)($p['ordem'] ?? ($atual['ordem'] ?? 99)),
        ];
        $novoId = $this->repo->salvarPerfil($id, $d);

        $texto = trim(str_replace("\r", '', (string)($p['termo'] ?? '')));
        $termo = $this->repo->termoAtual($novoId);
        $versaoNova = false;
        if ($texto !== '' && (!$termo || trim($termo['texto']) !== $texto)) {
            $this->repo->novoTermo($novoId, $texto, $usuarioId);
            $versaoNova = true;
        }
        AuditService::registrar('Wi-Fi', $id ? 'Editar perfil' : 'Criar perfil', "Perfil \"{$nome}\"" . ($versaoNova ? ' (nova versão dos termos -- todos aceitam de novo no próximo acesso)' : '') . '.');
        try {
            (new WifiFiltroService())->sincronizar();
        } catch (\Throwable $e) {
            // filtro desligado ou fora do ar: as regras valem na próxima sincronização
        }

        return ['success' => true, 'id' => $novoId, 'message' => 'Perfil salvo.' . ($versaoNova && $termo ? ' Os termos mudaram: cada pessoa aceita a nova versão no próximo acesso.' : '')];
    }

    /** Filtro a partir de campos de formulário com prefixo (perfil, pessoa ou global). */
    public static function filtroDoPost(array $p, string $prefixo = 'filtro_'): array
    {
        return [
            'categorias' => array_values(array_intersect((array)($p[$prefixo . 'categorias'] ?? []), array_keys(WifiFiltroService::CATEGORIAS))),
            'servicos' => array_values(array_filter((array)($p[$prefixo . 'servicos'] ?? []), fn ($s) => preg_match('/^[a-z0-9_]+$/', (string)$s))),
            'bloquear' => WifiFiltroService::normalizarDominios((string)($p[$prefixo . 'bloquear'] ?? '')),
            'liberar' => WifiFiltroService::normalizarDominios((string)($p[$prefixo . 'liberar'] ?? '')),
        ];
    }

    public function excluir(int $id): array
    {
        $perfil = $this->repo->perfil($id);
        if (!$perfil) {
            return ['success' => false, 'message' => 'Perfil não encontrado.'];
        }
        if ($this->repo->contarPessoasDoPerfil($id) > 0) {
            return ['success' => false, 'message' => 'Este perfil tem cadastros. Desative-o em vez de excluir (os registros precisam ser mantidos pela LGPD/Marco Civil).'];
        }
        $this->repo->excluirPerfil($id);
        AuditService::registrar('Wi-Fi', 'Excluir perfil', "Perfil \"{$perfil['nome']}\" excluído.");

        return ['success' => true, 'message' => 'Perfil excluído.'];
    }

    private function slugUnico(string $nome): string
    {
        $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $nome) ?: $nome)), '-') ?: 'perfil';
        $slug = $base;
        $i = 2;
        while ($this->repo->perfilPorSlug($slug)) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}

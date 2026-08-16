<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Base de Conhecimento: artigos locais (privados, só desta instalação, ou
 * públicos, propostos pra moderação central em intranet.rd.inf.br -- ver
 * IntegracoesController). Categoria/subcategoria são taxonomia LOCAL --
 * ao propor um artigo público, só o NOME da categoria vai como texto
 * livre (cada instalação pode ter sua própria taxonomia, não dá pra
 * referenciar um ID que só existe aqui).
 */
class KbService
{
    /** Slug (id de linguagem do Prism.js) => rótulo exibido no botão "Inserir comando". */
    public const LINGUAGENS_COMANDO = [
        'sql' => 'SQL',
        'php' => 'PHP',
        'markup' => 'HTML',
        'css' => 'CSS',
        'javascript' => 'JavaScript',
        'bash' => 'Shell / Bash',
        'powershell' => 'PowerShell',
        'python' => 'Python',
        'perl' => 'Perl (CGI)',
        'aspnet' => 'ASP',
        'csharp' => 'C#',
        'java' => 'Java',
        'json' => 'JSON',
        'plaintext' => 'Texto simples',
    ];

    private const PASTA_UPLOADS = __DIR__ . '/../../storage/uploads/base_conhecimento';
    private const EXTENSOES_IMAGEM_VALIDAS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    private const TAMANHO_MAXIMO_IMAGEM = 5 * 1024 * 1024; // 5MB

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    // -- Configuração da base central ----------------------------------

    public function centralConfigurada(): bool
    {
        return $this->urlCentral() !== '' && $this->apiKeyCentral() !== '';
    }

    public function urlCentral(): string
    {
        return trim((string)(ConfigService::get('kb_central_url', '') ?: ''));
    }

    public function apiKeyCentral(): string
    {
        return trim((string)(ConfigService::get('kb_central_api_key', '') ?: ''));
    }

    public function salvarConfigCentral(string $url, string $apiKey): array
    {
        $url = trim($url);
        $apiKey = trim($apiKey);

        if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
            return ['success' => false, 'message' => 'URL inválida.'];
        }

        ConfigService::set('kb_central_url', rtrim($url, '/'));
        ConfigService::set('kb_central_api_key', $apiKey);

        return ['success' => true, 'message' => 'Configuração da base central salva.'];
    }

    // -- Categorias / subcategorias --------------------------------------

    public function listarCategorias(): array
    {
        return $this->pdo->query('SELECT * FROM base_conhecimento_categorias ORDER BY nome')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarSubcategorias(): array
    {
        return $this->pdo->query(
            'SELECT s.*, c.nome AS categoria_nome FROM base_conhecimento_subcategorias s
             JOIN base_conhecimento_categorias c ON c.id = s.categoria_id
             ORDER BY c.nome, s.nome'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function criarCategoria(string $nome): array
    {
        $nome = trim($nome);
        if ($nome === '') {
            return ['success' => false, 'message' => 'Informe o nome da categoria.'];
        }

        try {
            $stmt = $this->pdo->prepare('INSERT INTO base_conhecimento_categorias (nome) VALUES (?)');
            $stmt->execute([$nome]);
        } catch (\PDOException $e) {
            return ['success' => false, 'message' => 'Já existe uma categoria com esse nome.'];
        }

        return ['success' => true, 'message' => "Categoria \"{$nome}\" criada."];
    }

    public function excluirCategoria(int $id): void
    {
        $this->pdo->prepare('DELETE FROM base_conhecimento_subcategorias WHERE categoria_id = ?')->execute([$id]);
        $this->pdo->prepare('DELETE FROM base_conhecimento_categorias WHERE id = ?')->execute([$id]);
    }

    public function criarSubcategoria(int $categoriaId, string $nome): array
    {
        $nome = trim($nome);
        if ($categoriaId <= 0 || $nome === '') {
            return ['success' => false, 'message' => 'Escolha a categoria e informe o nome da subcategoria.'];
        }

        try {
            $stmt = $this->pdo->prepare('INSERT INTO base_conhecimento_subcategorias (categoria_id, nome) VALUES (?, ?)');
            $stmt->execute([$categoriaId, $nome]);
        } catch (\PDOException $e) {
            return ['success' => false, 'message' => 'Já existe uma subcategoria com esse nome nessa categoria.'];
        }

        return ['success' => true, 'message' => "Subcategoria \"{$nome}\" criada."];
    }

    public function excluirSubcategoria(int $id): void
    {
        $this->pdo->prepare('DELETE FROM base_conhecimento_subcategorias WHERE id = ?')->execute([$id]);
    }

    // -- Artigos ----------------------------------------------------------

    public function listarMeus(string $busca = ''): array
    {
        $sql = 'SELECT a.*, c.nome AS categoria_nome, s.nome AS subcategoria_nome, i.id AS imagem_capa_id
                FROM base_conhecimento a
                LEFT JOIN base_conhecimento_categorias c ON c.id = a.categoria_id
                LEFT JOIN base_conhecimento_subcategorias s ON s.id = a.subcategoria_id
                LEFT JOIN base_conhecimento_imagens i ON i.id = (
                    SELECT MIN(id) FROM base_conhecimento_imagens WHERE artigo_id = a.id
                )';
        $params = [];

        if ($busca !== '') {
            $sql .= ' WHERE a.titulo LIKE ? OR a.problema LIKE ? OR a.solucao LIKE ? OR c.nome LIKE ?';
            $like = "%{$busca}%";
            $params = [$like, $like, $like, $like];
        }

        $sql .= ' ORDER BY a.criado_em DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarPublicos(string $busca = ''): array
    {
        $sql = 'SELECT * FROM base_conhecimento_publica';
        $params = [];

        if ($busca !== '') {
            $sql .= ' WHERE titulo LIKE ? OR problema LIKE ? OR solucao LIKE ? OR categoria LIKE ?';
            $like = "%{$busca}%";
            $params = [$like, $like, $like, $like];
        }

        $sql .= ' ORDER BY sincronizado_em DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function criar(array $dados, array $imagens = []): array
    {
        $titulo = trim((string)($dados['titulo'] ?? ''));
        $solucao = $this->sanitizarHtml((string)($dados['solucao'] ?? ''));
        $problema = trim((string)($dados['problema'] ?? ''));
        $categoriaId = (int)($dados['categoria_id'] ?? 0) ?: null;
        $subcategoriaId = (int)($dados['subcategoria_id'] ?? 0) ?: null;
        $publico = ($dados['visibilidade'] ?? 'privado') === 'publico';

        if ($titulo === '' || $solucao === '') {
            return ['success' => false, 'message' => 'Preencha pelo menos o título e a solução.'];
        }

        $centralId = null;
        $statusCentral = 'nao_enviado';

        if ($publico) {
            if (!$this->centralConfigurada()) {
                return ['success' => false, 'message' => 'Pra propor um artigo público, a base central precisa estar configurada (fale com um administrador).'];
            }

            $resultado = $this->chamarCentral('propor', 'POST', [
                'titulo' => $titulo,
                'categoria' => $this->nomeCategoria($categoriaId),
                'problema' => $problema,
                'solucao' => $solucao,
                'origem_cliente' => ConfigService::get('empresa_nome', '') ?: ($_SERVER['HTTP_HOST'] ?? gethostname()),
            ]);

            if (!$resultado['success']) {
                return ['success' => false, 'message' => 'Falha ao enviar pra moderação central: ' . $resultado['message']];
            }

            $centralId = $resultado['id'];
            $statusCentral = 'proposto';
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO base_conhecimento (titulo, categoria_id, subcategoria_id, problema, solucao, visibilidade, central_id, status_central, usuario_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $titulo, $categoriaId, $subcategoriaId, $problema, $solucao,
            $publico ? 'publico' : 'privado',
            $centralId, $statusCentral,
            $_SESSION['usuario']['id'] ?? null,
        ]);

        $artigoId = (int)$this->pdo->lastInsertId();

        if (!empty($imagens)) {
            $this->salvarImagens($artigoId, $imagens);
        }

        return [
            'success' => true,
            'message' => $publico
                ? 'Artigo salvo e enviado pra moderação central. Assim que for aprovado, aparece na Base Pública de todo mundo.'
                : 'Artigo salvo (privado, só nesta instalação).',
        ];
    }

    public function excluir(int $id): void
    {
        foreach ($this->listarImagens($id) as $imagem) {
            $this->excluirImagem((int)$imagem['id']);
        }

        $this->pdo->prepare('DELETE FROM base_conhecimento WHERE id = ?')->execute([$id]);
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.*, c.nome AS categoria_nome, s.nome AS subcategoria_nome
             FROM base_conhecimento a
             LEFT JOIN base_conhecimento_categorias c ON c.id = a.categoria_id
             LEFT JOIN base_conhecimento_subcategorias s ON s.id = a.subcategoria_id
             WHERE a.id = ?'
        );
        $stmt->execute([$id]);
        $artigo = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($artigo === false) {
            return null;
        }

        $artigo['imagens'] = $this->listarImagens($id);

        return $artigo;
    }

    /**
     * Só altera a cópia LOCAL -- se o artigo já foi proposto/aprovado na
     * base central, a cópia de lá não é tocada (não existe hoje um
     * endpoint de "editar" na API central; reenviar como proposta nova
     * criaria duplicata em vez de atualizar). O aviso disso volta na
     * mensagem de retorno pra não passar despercebido.
     */
    public function atualizar(int $id, array $dados, array $imagens = []): array
    {
        $atual = $this->buscarPorId($id);
        if ($atual === null) {
            return ['success' => false, 'message' => 'Artigo não encontrado.'];
        }

        $titulo = trim((string)($dados['titulo'] ?? ''));
        $solucao = $this->sanitizarHtml((string)($dados['solucao'] ?? ''));
        $problema = trim((string)($dados['problema'] ?? ''));
        $categoriaId = (int)($dados['categoria_id'] ?? 0) ?: null;
        $subcategoriaId = (int)($dados['subcategoria_id'] ?? 0) ?: null;

        if ($titulo === '' || $solucao === '') {
            return ['success' => false, 'message' => 'Preencha pelo menos o título e a solução.'];
        }

        $stmt = $this->pdo->prepare(
            'UPDATE base_conhecimento SET titulo = ?, categoria_id = ?, subcategoria_id = ?, problema = ?, solucao = ? WHERE id = ?'
        );
        $stmt->execute([$titulo, $categoriaId, $subcategoriaId, $problema, $solucao, $id]);

        if (!empty($imagens)) {
            $this->salvarImagens($id, $imagens);
        }

        $mensagem = 'Artigo atualizado.';
        if ($atual['visibilidade'] === 'publico') {
            $mensagem .= ' Atenção: esse artigo já foi proposto/aprovado na base central -- a cópia pública NÃO é atualizada automaticamente, só a sua cópia local.';
        }

        return ['success' => true, 'message' => $mensagem];
    }

    /**
     * Sanitiza o HTML que vem do editor rico da "Solução" (negrito,
     * itálico, sublinhado, tamanho de fonte, blocos de comando) -- allowlist
     * estrita de tags/atributos via DOMDocument, tudo mais é removido
     * (tag desconhecida mantém o texto de dentro, script/style são
     * removidos com o conteúdo junto). Único ponto de entrada de HTML
     * gerado por usuário no sistema -- qualquer alteração aqui precisa
     * manter a lista fechada, nunca abrir por "conveniência".
     */
    private const TAGS_PERMITIDAS = ['b', 'strong', 'i', 'em', 'u', 'span', 'pre', 'code', 'br', 'div', 'p'];

    private function sanitizarHtml(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="utf-8"?><div id="rd-raiz-sanitizacao">' . $html . '</div>',
            LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED
        );
        libxml_clear_errors();

        $raiz = $dom->getElementById('rd-raiz-sanitizacao');
        if ($raiz === null) {
            return '';
        }

        $this->limparNoRecursivo($raiz);

        $saida = '';
        foreach (iterator_to_array($raiz->childNodes) as $filho) {
            $saida .= $dom->saveHTML($filho);
        }

        return trim($saida);
    }

    private function limparNoRecursivo(\DOMNode $no): void
    {
        foreach (iterator_to_array($no->childNodes) as $filho) {
            if ($filho instanceof \DOMComment) {
                $no->removeChild($filho);
                continue;
            }

            if ($filho instanceof \DOMText) {
                continue;
            }

            if (!($filho instanceof \DOMElement)) {
                $no->removeChild($filho);
                continue;
            }

            $tag = strtolower($filho->tagName);

            if (in_array($tag, ['script', 'style'], true)) {
                $no->removeChild($filho);
                continue;
            }

            if (!in_array($tag, self::TAGS_PERMITIDAS, true)) {
                // tag nao permitida (ex: <a>, <img>, <iframe>) -- descarta
                // so a tag, preserva o texto de dentro
                while ($filho->firstChild) {
                    $no->insertBefore($filho->firstChild, $filho);
                }
                $no->removeChild($filho);
                continue;
            }

            // guarda o unico atributo valido de cada tag ANTES de zerar
            // todos -- nunca copia atributo cru do input do usuario.
            $estiloFonte = null;
            $linguagem = null;
            if ($tag === 'span' && $filho->hasAttribute('style')) {
                if (preg_match('/^font-size:\s*(\d{1,3})px\s*;?$/', trim($filho->getAttribute('style')), $m)) {
                    $tamanho = max(10, min(48, (int)$m[1]));
                    $estiloFonte = "font-size:{$tamanho}px";
                }
            }
            if ($tag === 'code' && $filho->hasAttribute('class')) {
                if (preg_match('/language-([a-z0-9]+)/', $filho->getAttribute('class'), $m)) {
                    $linguagem = 'language-' . $m[1];
                }
            }

            foreach (iterator_to_array($filho->attributes ?? []) as $attr) {
                $filho->removeAttribute($attr->name);
            }

            if ($estiloFonte !== null) {
                $filho->setAttribute('style', $estiloFonte);
            }
            if ($linguagem !== null) {
                $filho->setAttribute('class', $linguagem);
            }

            $this->limparNoRecursivo($filho);
        }
    }

    private function nomeCategoria(?int $categoriaId): ?string
    {
        if ($categoriaId === null) {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT nome FROM base_conhecimento_categorias WHERE id = ?');
        $stmt->execute([$categoriaId]);
        $nome = $stmt->fetchColumn();

        return $nome !== false ? $nome : null;
    }

    // -- Imagens ------------------------------------------------------------

    public function listarImagens(int $artigoId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM base_conhecimento_imagens WHERE artigo_id = ? ORDER BY id');
        $stmt->execute([$artigoId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function caminhoImagem(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM base_conhecimento_imagens WHERE id = ?');
        $stmt->execute([$id]);
        $imagem = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$imagem) {
            return null;
        }

        $caminho = self::PASTA_UPLOADS . '/' . $imagem['artigo_id'] . '/' . $imagem['arquivo'];

        return file_exists($caminho) ? ['caminho' => $caminho, 'nome' => $imagem['arquivo']] : null;
    }

    /** @param array<int, array{name:string,tmp_name:string,error:int,size:int}> $arquivos */
    public function salvarImagens(int $artigoId, array $arquivos): void
    {
        $pasta = self::PASTA_UPLOADS . '/' . $artigoId;
        if (!is_dir($pasta)) {
            mkdir($pasta, 0775, true);
        }

        foreach ($arquivos as $arquivo) {
            if (($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }
            if ($arquivo['size'] > self::TAMANHO_MAXIMO_IMAGEM) {
                continue;
            }

            $ext = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, self::EXTENSOES_IMAGEM_VALIDAS, true)) {
                continue;
            }

            $info = extension_loaded('gd') ? @getimagesize($arquivo['tmp_name']) : false;
            if ($info === false) {
                continue; // nao e uma imagem de verdade
            }

            $nomeArquivo = bin2hex(random_bytes(8)) . '.' . $ext;
            $destino = $pasta . '/' . $nomeArquivo;

            // Imagem grande demais (ex: print de tela em resolucao alta) --
            // redimensiona pra nao acumular storage sem necessidade; imagem
            // menor que isso so copia como veio, sem reprocessar.
            if ($info[0] > 1600 && extension_loaded('gd')) {
                $this->redimensionarERegravar($arquivo['tmp_name'], $destino, $info, 1600);
            } else {
                move_uploaded_file($arquivo['tmp_name'], $destino);
            }

            $stmt = $this->pdo->prepare('INSERT INTO base_conhecimento_imagens (artigo_id, arquivo) VALUES (?, ?)');
            $stmt->execute([$artigoId, $nomeArquivo]);
        }
    }

    private function redimensionarERegravar(string $origem, string $destino, array $info, int $larguraMaxima): void
    {
        [$larguraOriginal, $alturaOriginal, $tipo] = $info;
        $novaAltura = (int)round($alturaOriginal * ($larguraMaxima / $larguraOriginal));

        $imagemOrigem = match ($tipo) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($origem),
            IMAGETYPE_PNG => imagecreatefrompng($origem),
            IMAGETYPE_GIF => imagecreatefromgif($origem),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($origem) : false,
            default => false,
        };

        if ($imagemOrigem === false) {
            move_uploaded_file($origem, $destino);
            return;
        }

        $imagemNova = imagecreatetruecolor($larguraMaxima, $novaAltura);
        if ($tipo === IMAGETYPE_PNG || $tipo === IMAGETYPE_GIF) {
            imagealphablending($imagemNova, false);
            imagesavealpha($imagemNova, true);
        }
        imagecopyresampled($imagemNova, $imagemOrigem, 0, 0, 0, 0, $larguraMaxima, $novaAltura, $larguraOriginal, $alturaOriginal);

        match ($tipo) {
            IMAGETYPE_JPEG => imagejpeg($imagemNova, $destino, 85),
            IMAGETYPE_PNG => imagepng($imagemNova, $destino),
            IMAGETYPE_GIF => imagegif($imagemNova, $destino),
            IMAGETYPE_WEBP => function_exists('imagewebp') ? imagewebp($imagemNova, $destino) : imagejpeg($imagemNova, $destino, 85),
            default => null,
        };

        imagedestroy($imagemOrigem);
        imagedestroy($imagemNova);
    }

    public function excluirImagem(int $id): void
    {
        $caminho = $this->caminhoImagem($id);
        if ($caminho !== null) {
            @unlink($caminho['caminho']);
        }

        $this->pdo->prepare('DELETE FROM base_conhecimento_imagens WHERE id = ?')->execute([$id]);
    }

    // -- Sincronização com a base central -----------------------------------

    /**
     * Chamado pelo cron nativo "Sincronizar Base de Conhecimento"
     * (AtualizacaoService::garantirCronBaseConhecimento()) e por um botão
     * manual na tela. Atualiza o cache local dos artigos públicos (de
     * qualquer cliente) e, pros meus artigos propostos ainda pendentes,
     * confere se a moderação central já decidiu algo.
     */
    public function sincronizar(): array
    {
        if (!$this->centralConfigurada()) {
            return ['success' => true, 'message' => 'Base central não configurada, nada a sincronizar.'];
        }

        $resultado = $this->chamarCentral('publicas', 'GET');
        if (!$resultado['success']) {
            return ['success' => false, 'message' => 'Falha ao buscar artigos públicos: ' . $resultado['message']];
        }

        $stmtUpsert = $this->pdo->prepare(
            'INSERT INTO base_conhecimento_publica (central_id, titulo, categoria, problema, solucao, sincronizado_em)
             VALUES (?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE titulo = VALUES(titulo), categoria = VALUES(categoria), problema = VALUES(problema), solucao = VALUES(solucao), sincronizado_em = NOW()'
        );
        foreach ($resultado['artigos'] as $artigo) {
            // sanitiza de novo aqui, mesmo o texto ja chegando sanitizado
            // desta instalacao -- defesa em profundidade, ja que o cache
            // publico tambem recebe conteudo proposto por QUALQUER outra
            // instalacao que fale com a mesma base central.
            $stmtUpsert->execute([$artigo['id'], $artigo['titulo'], $artigo['categoria'], $artigo['problema'], $this->sanitizarHtml((string)$artigo['solucao'])]);
        }

        $pendentes = $this->pdo->query(
            "SELECT id, central_id FROM base_conhecimento WHERE status_central = 'proposto' AND central_id IS NOT NULL"
        )->fetchAll(PDO::FETCH_ASSOC);

        $stmtStatus = $this->pdo->prepare('UPDATE base_conhecimento SET status_central = ? WHERE id = ?');
        foreach ($pendentes as $item) {
            $status = $this->chamarCentral('status', 'GET', [], (int)$item['central_id']);
            if ($status['success'] && in_array($status['status'] ?? '', ['aprovado', 'rejeitado'], true)) {
                $stmtStatus->execute([$status['status'], $item['id']]);
            }
        }

        return [
            'success' => true,
            'message' => count($resultado['artigos']) . ' artigo(s) público(s) sincronizado(s), ' . count($pendentes) . ' pendente(s) conferido(s).',
        ];
    }

    /** Único ponto com curl_init do módulo, mesmo padrão do DdnsService. */
    private function chamarCentral(string $acao, string $metodo, array $corpo = [], ?int $id = null): array
    {
        $url = $this->urlCentral() . '/api.php?acao=' . urlencode($acao) . ($id !== null ? '&id=' . $id : '');

        $ch = curl_init($url);
        $opcoes = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER => ['X-Api-Key: ' . $this->apiKeyCentral(), 'Content-Type: application/json'],
        ];

        if ($metodo === 'POST') {
            $opcoes[CURLOPT_POST] = true;
            $opcoes[CURLOPT_POSTFIELDS] = json_encode($corpo);
        }

        curl_setopt_array($ch, $opcoes);
        $resposta = curl_exec($ch);
        $erroConexao = curl_errno($ch) !== 0;
        curl_close($ch);

        if ($erroConexao || $resposta === false) {
            return ['success' => false, 'message' => 'Não foi possível conectar na base central.'];
        }

        $dados = json_decode($resposta, true);
        if (!is_array($dados)) {
            return ['success' => false, 'message' => 'Resposta inesperada da base central.'];
        }

        return $dados;
    }
}

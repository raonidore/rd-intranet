<?php

namespace App\Services;

/**
 * Configuração e geração das etiquetas de patrimônio -- tamanho físico,
 * DPI da impressora e quais campos aparecem. Gera dois formatos a partir
 * da MESMA configuração:
 *  - ZPL (Zebra Programming Language): enviado direto pra impressora
 *    térmica (TLP2844/GC420t/ZD420t) via o agente Windows, sem passar
 *    por driver -- funciona igual nos 3 modelos, todos falam ZPL.
 *  - HTML/CSS (unidades em mm, o navegador entende nativamente): usado
 *    só pra pré-visualização na tela de configuração, sem depender de
 *    nenhum serviço externo.
 */
class EtiquetaService
{
    /**
     * Ordem fixa de empilhamento -- QR fica à esquerda (se ativo), os
     * demais campos marcados empilham à direita nesta ordem.
     */
    public const CAMPOS_DISPONIVEIS = [
        'qrcode' => 'QR Code (aponta pra ficha do ativo)',
        'codigo' => 'Código de patrimônio',
        'tipo' => 'Tipo do ativo',
        'nome' => 'Nome / Apelido',
        'setor' => 'Setor',
        'localizacao' => 'Localização',
        'ip' => 'Endereço IP',
        'empresa' => 'Rodapé (nome da empresa)',
    ];

    private const CAMPOS_PADRAO = ['qrcode', 'codigo', 'tipo', 'nome', 'empresa'];

    /**
     * Altura de fonte padrão (mm, ajustável na tela de configuração) e
     * nº máximo de linhas de cada campo de texto, na ordem de empilhamento.
     */
    private const CAMPOS_TEXTO = [
        'codigo' => ['fonte_mm' => 4.2, 'linhas' => 1],
        'tipo' => ['fonte_mm' => 2.8, 'linhas' => 1],
        'nome' => ['fonte_mm' => 3.2, 'linhas' => 2],
        'setor' => ['fonte_mm' => 2.6, 'linhas' => 1],
        'localizacao' => ['fonte_mm' => 2.6, 'linhas' => 1],
        'ip' => ['fonte_mm' => 2.4, 'linhas' => 1],
        'empresa' => ['fonte_mm' => 2.2, 'linhas' => 1],
    ];

    private const FONTE_MIN_MM = 1.5;
    private const FONTE_MAX_MM = 12.0;

    private const OFFSET_MIN_MM = 0.0;
    private const OFFSET_MAX_MM = 15.0;

    private const MARGEM_MM = 1.8;
    private const ESPACO_LINHA_MM = 0.8;
    private const ESPACO_QR_TEXTO_MM = 2.0;
    private const ESPACO_EXTRA_EMPRESA_MM = 1.5;

    public function configuracao(): array
    {
        $camposSalvos = json_decode(ConfigService::get('etiqueta_campos', '') ?: '', true);

        return [
            'largura_mm' => (float)(ConfigService::get('etiqueta_largura_mm', '55') ?? 55),
            'altura_mm' => (float)(ConfigService::get('etiqueta_altura_mm', '25') ?? 25),
            'dpi' => (int)(ConfigService::get('etiqueta_dpi', '203') ?? 203),
            'campos' => is_array($camposSalvos) ? $camposSalvos : self::CAMPOS_PADRAO,
            'fontes' => $this->mesclarFontes(json_decode(ConfigService::get('etiqueta_fontes', '') ?: '', true)),
            'offset_x_mm' => $this->clampOffset(ConfigService::get('etiqueta_offset_x_mm', '0')),
            'offset_y_mm' => $this->clampOffset(ConfigService::get('etiqueta_offset_y_mm', '0')),
        ];
    }

    private function clampOffset(mixed $valor): float
    {
        $valor = (float)$valor;

        return max(self::OFFSET_MIN_MM, min(self::OFFSET_MAX_MM, $valor));
    }

    /** Tamanho padrão (mm) de cada campo de texto -- ponto de partida da tela de configuração e fallback de quem nunca customizou. */
    public function fontesPadrao(): array
    {
        return array_map(fn(array $estilo) => $estilo['fonte_mm'], self::CAMPOS_TEXTO);
    }

    private function mesclarFontes(mixed $salvas): array
    {
        $salvas = is_array($salvas) ? $salvas : [];
        $fontes = [];

        foreach (self::CAMPOS_TEXTO as $campo => $estilo) {
            $valor = isset($salvas[$campo]) ? (float)$salvas[$campo] : $estilo['fonte_mm'];
            $fontes[$campo] = ($valor >= self::FONTE_MIN_MM && $valor <= self::FONTE_MAX_MM) ? $valor : $estilo['fonte_mm'];
        }

        return $fontes;
    }

    /** Monta a configuração (dimensões/dpi/campos/fontes/ajuste de impressão) a partir do POST do formulário -- usado tanto pra salvar quanto pra pré-visualização ao vivo. */
    public function configuracaoDoPost(array $post): array
    {
        return [
            'largura_mm' => (float)str_replace(',', '.', $post['largura_mm'] ?? '55'),
            'altura_mm' => (float)str_replace(',', '.', $post['altura_mm'] ?? '25'),
            'dpi' => (int)($post['dpi'] ?? 203),
            'campos' => array_values(array_intersect((array)($post['campos'] ?? []), array_keys(self::CAMPOS_DISPONIVEIS))),
            'fontes' => $this->mesclarFontes($post['fontes'] ?? []),
            'offset_x_mm' => $this->clampOffset(str_replace(',', '.', $post['offset_x_mm'] ?? '0')),
            'offset_y_mm' => $this->clampOffset(str_replace(',', '.', $post['offset_y_mm'] ?? '0')),
        ];
    }

    public function salvarConfiguracao(array $post): bool
    {
        $config = $this->configuracaoDoPost($post);

        if ($config['largura_mm'] < 20 || $config['largura_mm'] > 200) {
            NotificationService::error('Largura inválida (use um valor entre 20 e 200mm).');
            return false;
        }

        if ($config['altura_mm'] < 10 || $config['altura_mm'] > 200) {
            NotificationService::error('Altura inválida (use um valor entre 10 e 200mm).');
            return false;
        }

        if (!in_array($config['dpi'], [152, 203, 300, 600], true)) {
            NotificationService::error('DPI inválido -- use 152, 203, 300 ou 600 (o manual da impressora informa qual é).');
            return false;
        }

        ConfigService::set('etiqueta_largura_mm', (string)$config['largura_mm']);
        ConfigService::set('etiqueta_altura_mm', (string)$config['altura_mm']);
        ConfigService::set('etiqueta_dpi', (string)$config['dpi']);
        ConfigService::set('etiqueta_campos', json_encode($config['campos']));
        ConfigService::set('etiqueta_fontes', json_encode($config['fontes']));
        ConfigService::set('etiqueta_offset_x_mm', (string)$config['offset_x_mm']);
        ConfigService::set('etiqueta_offset_y_mm', (string)$config['offset_y_mm']);

        AuditService::registrar('Ativos', 'Configuração de Etiqueta', "Tamanho {$config['largura_mm']}x{$config['altura_mm']}mm, {$config['dpi']}dpi, campos: " . implode(', ', $config['campos']) . '.');
        NotificationService::success('Configuração de etiqueta salva.');

        return true;
    }

    /**
     * Resolve o texto de cada campo marcado a partir dos dados do ativo --
     * usado tanto pelo gerador de ZPL quanto pela pré-visualização HTML,
     * pra nunca ficarem fora de sincronia.
     *
     * @return array{qrcode: ?string, linhas: array<int, array{campo: string, texto: string, fonte_mm: float, linhas: int}>}
     */
    private function montarConteudo(array $config, array $ativo): array
    {
        $campos = array_flip($config['campos']);
        $qrcode = null;

        if (isset($campos['qrcode'])) {
            $qrcode = $this->urlAbsoluta('/ativos/ver?id=' . (int)$ativo['id']);
        }

        $valores = [
            'codigo' => $ativo['codigo_patrimonio'] ?? '',
            'tipo' => AtivoService::TIPOS[$ativo['tipo']]['label'] ?? (string)($ativo['tipo'] ?? ''),
            'nome' => $ativo['apelido'] ?? '' ?: ($ativo['nome'] ?? ''),
            'setor' => $ativo['setor_nome'] ?? '',
            'localizacao' => $ativo['localizacao_nome'] ?? '',
            'ip' => $ativo['ip'] ?? '',
            'empresa' => trim((ConfigService::get('empresa_nome', 'RD Tecnologia') ?? 'RD Tecnologia') . ' - TI'),
        ];

        $linhas = [];
        foreach (self::CAMPOS_TEXTO as $campo => $estilo) {
            if (!isset($campos[$campo])) continue;
            $texto = trim((string)($valores[$campo] ?? ''));
            if ($texto === '') continue;

            $linhas[] = [
                'campo' => $campo,
                'texto' => $texto,
                'fonte_mm' => $config['fontes'][$campo] ?? $estilo['fonte_mm'],
                'linhas' => $estilo['linhas'],
            ];
        }

        return ['qrcode' => $qrcode, 'linhas' => $linhas];
    }

    /**
     * ZPL pronto pra mandar direto pra impressora (via o agente Windows).
     * ^CI28 liga UTF-8 -- sem isso, acento (ex: "Área", "Localização")
     * sai errado. ^FB quebra linha automaticamente dentro da largura
     * disponível, em vez de estourar a etiqueta com texto mais longo.
     * ^LH desloca a origem de impressão -- ajuste de calibração pra
     * compensar a posição física real da etiqueta na impressora (sensor
     * de gap, folga do rolo), quando o conteúdo sai colado na borda.
     */
    public function gerarZpl(array $config, array $ativo): string
    {
        $dpi = $config['dpi'];
        $mmParaDots = fn(float $mm): int => (int)round($mm * $dpi / 25.4);

        $larguraDots = $mmParaDots($config['largura_mm']);
        $alturaDots = $mmParaDots($config['altura_mm']);
        $margemDots = $mmParaDots(self::MARGEM_MM);
        $offsetXDots = max(0, $mmParaDots($config['offset_x_mm'] ?? 0));
        $offsetYDots = max(0, $mmParaDots($config['offset_y_mm'] ?? 0));

        $conteudo = $this->montarConteudo($config, $ativo);

        $qrLadoDots = 0;
        $textoXDots = $margemDots;

        $zpl = "^XA\n^CI28\n^PW{$larguraDots}\n^LL{$alturaDots}\n^LH{$offsetXDots},{$offsetYDots}\n";

        if ($conteudo['qrcode'] !== null) {
            // Magnificacao em ZPL e "dots por modulo" do QR, nao um tamanho
            // final direto -- mirando ~0.4mm por modulo (legivel por
            // celular numa etiqueta pequena) em vez de calcular a partir
            // do espaco disponivel, que nao tem relacao direta com o
            // numero de modulos que o conteudo (uma URL) vai gerar.
            $magnificacao = max(1, min(10, (int)round(0.4 * $dpi / 25.4)));
            $qrLadoDots = min($alturaDots - 2 * $margemDots, $mmParaDots(20));
            $zpl .= "^FO{$margemDots},{$margemDots}^BQN,2,{$magnificacao}^FDQA," . $this->escaparZpl($conteudo['qrcode']) . "^FS\n";
            $textoXDots = $margemDots + $qrLadoDots + $mmParaDots(self::ESPACO_QR_TEXTO_MM);
        }

        $textoLarguraDots = max($mmParaDots(10), $larguraDots - $textoXDots - $margemDots);
        $yDots = $margemDots;

        foreach ($conteudo['linhas'] as $linha) {
            if ($linha['campo'] === 'empresa') {
                $yDots += $mmParaDots(self::ESPACO_EXTRA_EMPRESA_MM);
            }

            $fonteDots = $mmParaDots($linha['fonte_mm']);
            // Largura da fonte um pouco menor que a altura (fonte
            // condensada) -- testado ao vivo (Labelary): com largura igual
            // a altura, um código de patrimônio comum (ex: RD-PC-000001)
            // estourava a coluna disponível e cortava o último caractere.
            $larguraFonteDots = max(1, (int)round($fonteDots * 0.72));
            $blocoAlturaDots = $mmParaDots($linha['fonte_mm'] + self::ESPACO_LINHA_MM) * $linha['linhas'];

            $zpl .= "^FO{$textoXDots},{$yDots}^A0N,{$fonteDots},{$larguraFonteDots}^FB{$textoLarguraDots},{$linha['linhas']},0,L,0^FD"
                . $this->escaparZpl($linha['texto']) . "^FS\n";

            $yDots += $blocoAlturaDots;
        }

        $zpl .= "^XZ\n";

        return $zpl;
    }

    /**
     * Mesmo conteúdo/posicionamento do ZPL, mas como HTML/CSS em mm --
     * usado tanto na pré-visualização da tela de configuração (sem QR
     * real, só um ícone -- não depende de nenhum serviço externo) quanto
     * na página de impressão/etiqueta HTML (com $qrCodeBase64 de
     * verdade, pra sair escaneável mesmo impressa numa impressora comum).
     */
    public function gerarPreviewHtml(array $config, array $ativo, ?string $qrCodeBase64 = null): string
    {
        $conteudo = $this->montarConteudo($config, $ativo);
        $margem = self::MARGEM_MM;
        $offsetX = $config['offset_x_mm'] ?? 0;
        $offsetY = $config['offset_y_mm'] ?? 0;

        // overflow:hidden de proposito -- se o conteudo nao couber, o
        // preview corta igual a impressora vai cortar (nao desenha fora
        // da etiqueta), pra ficar visualmente óbvio que passou do limite.
        // padding-top/left somam o ajuste de calibracao (^LH no ZPL), pra
        // o preview mostrar onde o conteudo realmente vai comecar.
        $html = '<div class="rd-etiqueta-preview" style="width:' . $config['largura_mm'] . 'mm;height:' . $config['altura_mm'] . 'mm;'
            . 'padding:' . ($margem + $offsetY) . 'mm ' . $margem . 'mm ' . $margem . 'mm ' . ($margem + $offsetX) . 'mm;overflow:hidden;">';

        if ($conteudo['qrcode'] !== null) {
            $ladoQr = min($config['altura_mm'] - 2 * $margem, 20);
            if ($qrCodeBase64 !== null) {
                $html .= '<img class="rd-etiqueta-qr-img" src="data:image/png;base64,' . $qrCodeBase64 . '" style="width:' . $ladoQr . 'mm;height:' . $ladoQr . 'mm;" alt="QR code">';
            } else {
                $html .= '<div class="rd-etiqueta-qr" style="width:' . $ladoQr . 'mm;height:' . $ladoQr . 'mm;"><i class="bi bi-qr-code"></i></div>';
            }
        }

        $html .= '<div class="rd-etiqueta-texto">';
        foreach ($conteudo['linhas'] as $linha) {
            $classeEspaco = $linha['campo'] === 'empresa' ? ' rd-etiqueta-espaco' : '';
            $html .= '<div class="rd-etiqueta-linha' . $classeEspaco . '" style="font-size:' . $linha['fonte_mm'] . 'mm;">'
                . htmlspecialchars($linha['texto']) . '</div>';
        }
        $html .= '</div></div>';

        return $html;
    }

    private function escaparZpl(string $texto): string
    {
        // ^ e ~ sao prefixo de comando em ZPL -- se sobrar um desses no
        // meio do texto (nome de ativo, etc.), quebra o resto da etiqueta.
        return str_replace(['^', '~', "\n", "\r"], ['', '', ' ', ''], $texto);
    }

    private function urlAbsoluta(string $path): string
    {
        $esquema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return $esquema . '://' . $host . url($path);
    }
}

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
        'empresa' => 'Rodapé (nome da empresa)',
    ];

    private const CAMPOS_PADRAO = ['qrcode', 'codigo', 'tipo', 'nome', 'empresa'];

    /** Altura de fonte (mm) e nº máximo de linhas de cada campo de texto, na ordem de empilhamento. */
    private const CAMPOS_TEXTO = [
        'codigo' => ['fonte_mm' => 4.2, 'linhas' => 1],
        'tipo' => ['fonte_mm' => 2.8, 'linhas' => 1],
        'nome' => ['fonte_mm' => 3.2, 'linhas' => 2],
        'setor' => ['fonte_mm' => 2.6, 'linhas' => 1],
        'localizacao' => ['fonte_mm' => 2.6, 'linhas' => 1],
        'empresa' => ['fonte_mm' => 2.2, 'linhas' => 1],
    ];

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
        ];
    }

    public function salvarConfiguracao(array $post): bool
    {
        $largura = (float)str_replace(',', '.', $post['largura_mm'] ?? '');
        $altura = (float)str_replace(',', '.', $post['altura_mm'] ?? '');
        $dpi = (int)($post['dpi'] ?? 0);
        $campos = array_values(array_intersect((array)($post['campos'] ?? []), array_keys(self::CAMPOS_DISPONIVEIS)));

        if ($largura < 20 || $largura > 200) {
            NotificationService::error('Largura inválida (use um valor entre 20 e 200mm).');
            return false;
        }

        if ($altura < 10 || $altura > 200) {
            NotificationService::error('Altura inválida (use um valor entre 10 e 200mm).');
            return false;
        }

        if (!in_array($dpi, [152, 203, 300, 600], true)) {
            NotificationService::error('DPI inválido -- use 152, 203, 300 ou 600 (o manual da impressora informa qual é).');
            return false;
        }

        ConfigService::set('etiqueta_largura_mm', (string)$largura);
        ConfigService::set('etiqueta_altura_mm', (string)$altura);
        ConfigService::set('etiqueta_dpi', (string)$dpi);
        ConfigService::set('etiqueta_campos', json_encode($campos));

        AuditService::registrar('Ativos', 'Configuração de Etiqueta', "Tamanho {$largura}x{$altura}mm, {$dpi}dpi, campos: " . implode(', ', $campos) . '.');
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
                'fonte_mm' => $estilo['fonte_mm'],
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
     */
    public function gerarZpl(array $config, array $ativo): string
    {
        $dpi = $config['dpi'];
        $mmParaDots = fn(float $mm): int => (int)round($mm * $dpi / 25.4);

        $larguraDots = $mmParaDots($config['largura_mm']);
        $alturaDots = $mmParaDots($config['altura_mm']);
        $margemDots = $mmParaDots(self::MARGEM_MM);

        $conteudo = $this->montarConteudo($config, $ativo);

        $qrLadoDots = 0;
        $textoXDots = $margemDots;

        $zpl = "^XA\n^CI28\n^PW{$larguraDots}\n^LL{$alturaDots}\n";

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
     * usado só pra pré-visualização na tela de configuração, sem
     * depender de nenhum serviço externo pra "desenhar" a etiqueta.
     */
    public function gerarPreviewHtml(array $config, array $ativo): string
    {
        $conteudo = $this->montarConteudo($config, $ativo);
        $margem = self::MARGEM_MM;

        // overflow:hidden de proposito -- se o conteudo nao couber, o
        // preview corta igual a impressora vai cortar (nao desenha fora
        // da etiqueta), pra ficar visualmente óbvio que passou do limite.
        $html = '<div class="rd-etiqueta-preview" style="width:' . $config['largura_mm'] . 'mm;height:' . $config['altura_mm'] . 'mm;padding:' . $margem . 'mm;overflow:hidden;">';

        if ($conteudo['qrcode'] !== null) {
            $ladoQr = min($config['altura_mm'] - 2 * $margem, 20);
            $html .= '<div class="rd-etiqueta-qr" style="width:' . $ladoQr . 'mm;height:' . $ladoQr . 'mm;"><i class="bi bi-qr-code"></i></div>';
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

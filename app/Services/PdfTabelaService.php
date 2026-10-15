<?php

namespace App\Services;

/**
 * PDF de tabela (relatório) escrito à mão, sem biblioteca externa: A4
 * paisagem, faixa de título na cor da marca, cabeçalho da tabela repetido
 * em cada página, linhas zebradas, texto quebrado em até 3 linhas por célula
 * e rodapé "página X de Y". Usa as fontes padrão do PDF (Helvetica), que
 * todo leitor tem -- acentos do português via WinAnsiEncoding.
 */
class PdfTabelaService
{
    private const LARGURA = 842.0;   // A4 paisagem, em pontos
    private const ALTURA = 595.0;
    private const MARGEM = 28.0;
    private const FONTE = 7.6;
    private const LINHAS_POR_CELULA = 3;

    // Larguras (1/1000 em) da Helvetica e da Helvetica-Bold para ASCII 32..126.
    private const LARG_NORMAL = [278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,1015,667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,556,556,333,500,278,556,500,722,500,500,500,334,260,334,584];
    private const LARG_NEGRITO = [278,333,474,556,556,889,722,238,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,333,333,584,584,584,611,975,722,722,722,722,667,611,778,722,278,556,722,611,833,722,778,667,778,722,667,611,722,667,944,667,667,611,333,278,333,584,556,333,556,611,556,611,556,333,611,611,278,278,556,278,889,611,611,611,611,389,556,333,611,556,778,556,556,500,389,280,389,584];

    /** @var array<int, string> comandos de desenho por página */
    private array $paginas = [];
    private string $atual = '';

    /**
     * @param string[] $cabecalho
     * @param array<int, array<int, scalar|null>> $linhas
     * @return string conteúdo binário do PDF
     */
    public function gerar(string $titulo, string $subtitulo, string $empresa, array $cabecalho, array $linhas): string
    {
        $cabecalho = array_values(array_map('strval', $cabecalho));
        $linhas = array_map(fn ($l) => array_map(fn ($v) => $this->limpar((string)($v ?? '')), array_values($l)), $linhas);
        $larguras = $this->largurasColunas($cabecalho, $linhas);

        $this->paginas = [];
        $y = $this->novaPagina($titulo, $subtitulo, $empresa, $cabecalho, $larguras);
        $limiteInferior = self::MARGEM + 20;

        if (!$linhas) {
            $this->texto(self::MARGEM + 4, $y - 14, 'Nenhum registro com esses filtros.', 9, false, '0.4 0.45 0.5');
        }

        foreach ($linhas as $n => $linha) {
            // Cada célula quebrada em até 3 linhas; a mais alta define a altura da linha.
            $quebradas = [];
            $maxLinhas = 1;
            foreach ($larguras as $i => $largura) {
                $quebradas[$i] = $this->quebrar($linha[$i] ?? '', $largura - 6, self::FONTE, false);
                $maxLinhas = max($maxLinhas, count($quebradas[$i]));
            }
            $altura = 5 + $maxLinhas * (self::FONTE + 2) + 2;

            if ($y - $altura < $limiteInferior) {
                $y = $this->novaPagina($titulo, $subtitulo, $empresa, $cabecalho, $larguras);
            }

            if ($n % 2 === 1) {
                $this->retangulo(self::MARGEM, $y - $altura, self::LARGURA - 2 * self::MARGEM, $altura, '0.953 0.961 0.976');
            }
            $x = self::MARGEM;
            foreach ($larguras as $i => $largura) {
                foreach ($quebradas[$i] as $k => $trecho) {
                    $this->texto($x + 3, $y - 4 - self::FONTE - $k * (self::FONTE + 2), $trecho, self::FONTE, false, '0.07 0.09 0.15');
                }
                $x += $largura;
            }
            $this->linha(self::MARGEM, $y - $altura, self::LARGURA - self::MARGEM, $y - $altura, '0.89 0.906 0.933');
            $y -= $altura;
        }

        $this->paginas[] = $this->atual;
        $total = count($this->paginas);
        foreach ($this->paginas as $p => &$conteudo) {
            $this->atual = '';
            $rodape = $this->limpar('RD.Intranet · ' . count($linhas) . ' registro(s) · gerado em ' . date('d/m/Y H:i'));
            $this->texto(self::MARGEM, 16, $rodape, 7, false, '0.45 0.5 0.56');
            $pagina = $this->limpar('Página ' . ($p + 1) . ' de ' . $total);
            $this->texto(self::LARGURA - self::MARGEM - $this->largura($pagina, 7, false), 16, $pagina, 7, false, '0.45 0.5 0.56');
            $conteudo .= $this->atual;
        }
        unset($conteudo);

        return $this->montarArquivo($titulo);
    }

    private function novaPagina(string $titulo, string $subtitulo, string $empresa, array $cabecalho, array $larguras): float
    {
        if ($this->atual !== '') {
            $this->paginas[] = $this->atual;
        }
        $this->atual = '';

        // Faixa do título.
        $this->retangulo(0, self::ALTURA - 50, self::LARGURA, 50, '0.043 0.106 0.2');
        $this->texto(self::MARGEM, self::ALTURA - 30, $this->limpar($titulo), 15, true, '1 1 1');
        $empresa = $this->limpar($empresa);
        $this->texto(self::LARGURA - self::MARGEM - $this->largura($empresa, 9, true), self::ALTURA - 28, $empresa, 9, true, '0.72 0.78 0.86');
        $this->texto(self::MARGEM, self::ALTURA - 43, $this->limpar($subtitulo), 7.5, false, '0.72 0.78 0.86');

        // Cabeçalho da tabela.
        $y = self::ALTURA - 62;
        $altura = 17;
        $this->retangulo(self::MARGEM, $y - $altura, self::LARGURA - 2 * self::MARGEM, $altura, '0.075 0.161 0.294');
        $x = self::MARGEM;
        foreach ($larguras as $i => $largura) {
            $rotulo = $this->quebrar($this->limpar($cabecalho[$i]), $largura - 6, self::FONTE, true)[0] ?? '';
            $this->texto($x + 3, $y - 11.5, $rotulo, self::FONTE, true, '1 1 1');
            $x += $largura;
        }

        return $y - $altura;
    }

    /** Larguras proporcionais ao conteúdo (percentil 90, pra uma linha enorme não engolir a tabela), somando a largura útil. */
    private function largurasColunas(array $cabecalho, array $linhas): array
    {
        $util = self::LARGURA - 2 * self::MARGEM;
        $naturais = [];
        foreach ($cabecalho as $i => $titulo) {
            $amostra = array_map(fn ($l) => $this->largura($l[$i] ?? '', self::FONTE, false), array_slice($linhas, 0, 400));
            sort($amostra);
            $p90 = $amostra ? $amostra[(int)floor((count($amostra) - 1) * 0.9)] : 0;
            $naturais[$i] = max(34, min(220, max($p90, $this->largura($titulo, self::FONTE, true)) + 8));
        }
        $soma = array_sum($naturais) ?: 1;

        return array_map(fn ($l) => $l * $util / $soma, $naturais);
    }

    /** Quebra por palavra para caber na largura; o que passar de 3 linhas termina em "…". */
    private function quebrar(string $texto, float $largura, float $tamanho, bool $negrito): array
    {
        $linhas = [];
        foreach (preg_split('/\r?\n/', $texto) as $paragrafo) {
            $atual = '';
            foreach (preg_split('/ +/', trim($paragrafo)) as $palavra) {
                $tentativa = $atual === '' ? $palavra : $atual . ' ' . $palavra;
                if ($this->largura($tentativa, $tamanho, $negrito) <= $largura) {
                    $atual = $tentativa;
                    continue;
                }
                if ($atual !== '') {
                    $linhas[] = $atual;
                }
                // Palavra sozinha maior que a coluna (IP, caminho...): corta por caractere.
                while ($this->largura($palavra, $tamanho, $negrito) > $largura && strlen($palavra) > 1) {
                    $corte = strlen($palavra);
                    while ($corte > 1 && $this->largura(substr($palavra, 0, $corte), $tamanho, $negrito) > $largura) {
                        $corte--;
                    }
                    $linhas[] = substr($palavra, 0, $corte);
                    $palavra = substr($palavra, $corte);
                }
                $atual = $palavra;
            }
            $linhas[] = $atual;
        }

        if (count($linhas) > self::LINHAS_POR_CELULA) {
            $linhas = array_slice($linhas, 0, self::LINHAS_POR_CELULA);
            $ultima = $linhas[self::LINHAS_POR_CELULA - 1];
            while ($ultima !== '' && $this->largura($ultima . "\x85", $tamanho, $negrito) > $largura) {
                $ultima = substr($ultima, 0, -1);
            }
            $linhas[self::LINHAS_POR_CELULA - 1] = $ultima . "\x85";
        }

        return $linhas ?: [''];
    }

    /** Texto já em Windows-1252 (ver limpar()). */
    private function largura(string $texto, float $tamanho, bool $negrito): float
    {
        $tabela = $negrito ? self::LARG_NEGRITO : self::LARG_NORMAL;
        $soma = 0;
        $n = strlen($texto);
        for ($i = 0; $i < $n; $i++) {
            $c = ord($texto[$i]);
            if ($c >= 32 && $c <= 126) {
                $soma += $tabela[$c - 32];
            } elseif ($c === 0x85) {
                $soma += 1000;
            } else {
                $soma += 556; // acentuadas: mesma largura média de uma letra
            }
        }

        return $soma * $tamanho / 1000;
    }

    /** UTF-8 -> Windows-1252 (a codificação das fontes padrão do PDF); o que não existir vira "?". */
    private function limpar(string $texto): string
    {
        // Windows-1252 já tem · … — – › (mb_convert_encoding converte); só tira tab e caracteres de controle.
        $texto = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', str_replace("\t", ' ', $texto)) ?? '';

        return mb_convert_encoding($texto, 'Windows-1252', 'UTF-8');
    }

    private function texto(float $x, float $y, string $texto, float $tamanho, bool $negrito, string $cor): void
    {
        $escapado = strtr($texto, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)', "\r" => '', "\n" => ' ']);
        $this->atual .= sprintf("BT %s rg /%s %.2F Tf %.2F %.2F Td (%s) Tj ET\n", $cor, $negrito ? 'F2' : 'F1', $tamanho, $x, $y, $escapado);
    }

    private function retangulo(float $x, float $y, float $l, float $a, string $cor): void
    {
        $this->atual .= sprintf("%s rg %.2F %.2F %.2F %.2F re f\n", $cor, $x, $y, $l, $a);
    }

    private function linha(float $x1, float $y1, float $x2, float $y2, string $cor): void
    {
        $this->atual .= sprintf("%s RG 0.5 w %.2F %.2F m %.2F %.2F l S\n", $cor, $x1, $y1, $x2, $y2);
    }

    private function montarArquivo(string $titulo): string
    {
        $objetos = [];
        $objetos[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        // 3 e 4 = fontes; páginas e conteúdos começam no 5.
        $objetos[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objetos[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        $filhos = [];
        $proximo = 5;
        foreach ($this->paginas as $conteudo) {
            $idPagina = $proximo++;
            $idConteudo = $proximo++;
            $comprimido = function_exists('gzcompress') ? gzcompress($conteudo, 6) : $conteudo;
            $filtro = function_exists('gzcompress') ? ' /Filter /FlateDecode' : '';
            $objetos[$idConteudo] = '<< /Length ' . strlen($comprimido) . $filtro . " >>\nstream\n" . $comprimido . "\nendstream";
            $objetos[$idPagina] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::LARGURA . ' ' . self::ALTURA . '] '
                . '/Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . $idConteudo . ' 0 R >>';
            $filhos[] = $idPagina . ' 0 R';
        }
        $objetos[2] = '<< /Type /Pages /Kids [' . implode(' ', $filhos) . '] /Count ' . count($filhos) . ' >>';
        $idInfo = $proximo;
        $tituloPdf = strtr($this->limpar($titulo), ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']);
        $objetos[$idInfo] = '<< /Title (' . $tituloPdf . ') /Producer (RD.Intranet) /CreationDate (D:' . date('YmdHis') . ') >>';
        ksort($objetos);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $posicoes = [];
        foreach ($objetos as $id => $corpo) {
            $posicoes[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $corpo . "\nendobj\n";
        }
        $inicioXref = strlen($pdf);
        $pdf .= "xref\n0 " . ($idInfo + 1) . "\n0000000000 65535 f \n";
        for ($id = 1; $id <= $idInfo; $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $posicoes[$id]);
        }

        return $pdf . "trailer\n<< /Size " . ($idInfo + 1) . ' /Root 1 0 R /Info ' . $idInfo . " 0 R >>\nstartxref\n" . $inicioXref . "\n%%EOF";
    }
}

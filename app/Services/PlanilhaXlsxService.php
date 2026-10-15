<?php

namespace App\Services;

use ZipArchive;

/**
 * Gera uma planilha .xlsx de verdade (OOXML) com ZipArchive, sem biblioteca
 * externa: uma aba, cabeçalho destacado e congelado, filtro automático e
 * largura de coluna pelo conteúdo. Números ficam como número (somam no Excel);
 * o resto vai como texto (código de patrimônio "0012" não vira 12).
 */
class PlanilhaXlsxService
{
    /**
     * @param string[] $cabecalho
     * @param array<int, array<int, scalar|null>> $linhas
     * @param int[] $colunasNumericas índices das colunas que devem ir como número
     * @return string conteúdo binário do .xlsx
     */
    public function gerar(string $nomeAba, array $cabecalho, array $linhas, array $colunasNumericas = [], string $titulo = ''): string
    {
        $arquivoTemporario = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive();
        $zip->open($arquivoTemporario, ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '</Types>');

        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '</Relationships>');

        $zip->addFromString('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:title>' . $this->xml($titulo ?: $nomeAba) . '</dc:title><dc:creator>RD.Intranet</dc:creator>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . gmdate('Y-m-d\TH:i:s\Z') . '</dcterms:created>'
            . '</cp:coreProperties>');

        // Nome de aba: até 31 caracteres, sem : \ / ? * [ ]
        $nomeAba = mb_substr(preg_replace('#[:\\\\/?*\[\]]#', ' ', $nomeAba) ?: 'Planilha', 0, 31);
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . $this->xml($nomeAba) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '<definedNames><definedName name="_xlnm._FilterDatabase" localSheetId="0" hidden="1">\'' . $this->xml(str_replace("'", "''", $nomeAba)) . '\'!$A$1:$' . $this->letra(count($cabecalho) - 1) . '$' . (count($linhas) + 1) . '</definedName></definedNames>'
            . '</workbook>');

        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>');

        // Estilos: 0 = normal, 1 = cabeçalho (negrito branco sobre marinho), 2 = texto com borda fina.
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="10"/><name val="Calibri"/></font><font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF0B1B33"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border><left/><right/><top/><bottom style="thin"><color rgb="FFE3E7EE"/></bottom><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"><alignment vertical="top"/></xf></cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>');

        $zip->addFromString('xl/worksheets/sheet1.xml', $this->aba($cabecalho, $linhas, $colunasNumericas));
        $zip->close();

        $conteudo = (string)file_get_contents($arquivoTemporario);
        @unlink($arquivoTemporario);

        return $conteudo;
    }

    private function aba(array $cabecalho, array $linhas, array $colunasNumericas): string
    {
        // Largura pelo maior conteúdo da coluna (amostra das 500 primeiras linhas), entre 8 e 60.
        $larguras = array_map(fn ($c) => mb_strlen((string)$c) + 2, $cabecalho);
        foreach (array_slice($linhas, 0, 500) as $linha) {
            foreach (array_values($linha) as $i => $valor) {
                $maior = max(array_map('mb_strlen', explode("\n", (string)$valor)));
                $larguras[$i] = max($larguras[$i] ?? 8, $maior + 2);
            }
        }

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="15"/><cols>';
        foreach ($larguras as $i => $largura) {
            $xml .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . min(60, max(8, $largura)) . '" customWidth="1"/>';
        }
        $xml .= '</cols><sheetData>';

        $xml .= '<row r="1" ht="20" customHeight="1">';
        foreach (array_values($cabecalho) as $i => $titulo) {
            $xml .= '<c r="' . $this->letra($i) . '1" t="inlineStr" s="1"><is><t>' . $this->xml((string)$titulo) . '</t></is></c>';
        }
        $xml .= '</row>';

        foreach (array_values($linhas) as $n => $linha) {
            $r = $n + 2;
            $xml .= '<row r="' . $r . '">';
            foreach (array_values($linha) as $i => $valor) {
                $ref = $this->letra($i) . $r;
                if ($valor === null || $valor === '') {
                    continue;
                }
                if (in_array($i, $colunasNumericas, true) && is_numeric($valor)) {
                    $xml .= '<c r="' . $ref . '" s="2"><v>' . (0 + $valor) . '</v></c>';
                } else {
                    $xml .= '<c r="' . $ref . '" t="inlineStr" s="2"><is><t xml:space="preserve">' . $this->xml((string)$valor) . '</t></is></c>';
                }
            }
            $xml .= '</row>';
        }

        $ultima = $this->letra(count($cabecalho) - 1) . (count($linhas) + 1);

        return $xml . '</sheetData><autoFilter ref="A1:' . $ultima . '"/>'
            . '<pageMargins left="0.4" right="0.4" top="0.5" bottom="0.5" header="0.3" footer="0.3"/>'
            . '<pageSetup orientation="landscape" paperSize="9" fitToWidth="1" fitToHeight="0"/></worksheet>';
    }

    /** 0 -> A, 25 -> Z, 26 -> AA */
    private function letra(int $indice): string
    {
        $letra = '';
        for ($i = $indice + 1; $i > 0; $i = intdiv($i - 1, 26)) {
            $letra = chr(65 + ($i - 1) % 26) . $letra;
        }

        return $letra;
    }

    private function xml(string $texto): string
    {
        // Remove caracteres de controle que o XML não aceita (vêm às vezes do inventário do Windows).
        $texto = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $texto) ?? '';

        return htmlspecialchars($texto, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
    }
}

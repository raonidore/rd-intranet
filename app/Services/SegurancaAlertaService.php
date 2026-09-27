<?php

namespace App\Services;

/**
 * Monta o alerta de segurança enviado por e-mail/WhatsApp: identifica o
 * cliente e o servidor de origem (cada cliente tem o próprio RD Intranet e
 * a TI recebe alertas de vários), a máquina e o que foi feito, com links
 * pra ficha e pra Central de Segurança.
 *
 * Três modelos visuais à escolha na Central (aba Configuração), todos com
 * logo e nome da empresa. HTML em tabelas com estilo inline -- é o que
 * Gmail/Outlook/celular respeitam. O logo vai EMBUTIDO no e-mail (cid:),
 * porque link pro servidor interno não abre de fora e imagem em data: é
 * bloqueada pelo Gmail; na prévia do portal usa data: (navegador aceita).
 */
class SegurancaAlertaService
{
    public const MODELOS = [
        'sistema' => ['Sistema', 'Cabeçalho escuro igual ao menu do RD Intranet, logo da empresa à direita.'],
        'corporativo' => ['Corporativo', 'Fundo branco com o logo da empresa em destaque; RD Intranet só no rodapé.'],
        'compacto' => ['Compacto', 'Uma coluna enxuta, pensada pra ler no celular.'],
    ];

    private const COR_MENU = '#111827';
    private const COR_PRIMARIA = '#2563eb';
    private const COR_FUNDO = '#f4f6f9';
    private const CID_LOGO_EMPRESA = 'logo_empresa';
    private const CID_LOGO_SISTEMA = 'logo_sistema';

    private const ROTULOS_DETALHE = [
        'motivo' => 'Motivo',
        'caminho' => 'Arquivo',
        'mudanca' => 'O que aconteceu',
        'pasta' => 'Pasta',
        'eventos' => 'Arquivos afetados',
        'janela_segundos' => 'Janela (segundos)',
        'extensoes_novas' => 'Extensões novas',
        'nota_resgate' => 'Nota de resgate',
        'contagem_anterior' => 'Shadow copies antes',
        'contagem_atual' => 'Shadow copies depois',
        'apagadas_pelo_windows' => 'Apagadas pelo próprio Windows',
        'sem_explicacao' => 'Sem explicação',
        'exemplos' => 'Exemplos',
    ];

    public static function modeloAtual(): string
    {
        $modelo = (string)(ConfigService::get('seguranca_modulo_padrao_alerta_modelo', 'sistema') ?? 'sistema');

        return isset(self::MODELOS[$modelo]) ? $modelo : 'sistema';
    }

    /**
     * @param bool $previa true = logos em data: pra mostrar no portal; false = cid: pro e-mail
     * @return array{assunto: string, html: string, texto: string, imagens: array<string, array{0: string, 1: string}>}
     */
    public static function montar(array $ativo, string $tipo, string $severidade, string $resumo, string $decisao, array $detalhes, ?int $eventoId = null, ?string $modelo = null, bool $previa = false): array
    {
        $d = self::dados($ativo, $tipo, $severidade, $resumo, $decisao, $detalhes, $eventoId);
        [$logoEmpresa, $logoSistema, $imagens] = self::logos($previa);

        $modelo = isset(self::MODELOS[$modelo ?? '']) ? $modelo : self::modeloAtual();
        $html = match ($modelo) {
            'corporativo' => self::htmlCorporativo($d, $logoEmpresa, $logoSistema),
            'compacto' => self::htmlCompacto($d, $logoEmpresa),
            default => self::htmlSistema($d, $logoEmpresa),
        };

        // Só embute o que o modelo usa.
        $imagens = array_filter($imagens, fn ($cid) => str_contains($html, 'cid:' . $cid), ARRAY_FILTER_USE_KEY);

        $texto = "🚨 {$d['rotuloSeveridade']} -- {$d['rotuloTipo']}\n"
            . "Cliente: {$d['cliente']} ({$d['servidor']})\n"
            . "Máquina: {$d['codigo']}" . ($d['nomeMaquina'] !== '' ? " ({$d['nomeMaquina']})" : '') . ($d['usuario'] !== '' ? " -- {$d['usuario']}" : '') . "\n"
            . "{$d['resumo']}\n"
            . "Resposta: {$d['decisao']}"
            . ($d['linkMaquina'] !== '' ? "\n{$d['linkMaquina']}" : '');

        return ['assunto' => $d['assunto'], 'html' => $html, 'texto' => $texto, 'imagens' => $imagens];
    }

    /** Evento de exemplo pra prévia e pro e-mail de teste. */
    public static function exemplo(?string $modelo = null, bool $previa = true): array
    {
        return self::montar(
            ['id' => 0, 'codigo_patrimonio' => 'PC-0001', 'nome' => 'RECEPCAO-01', 'ip' => '192.168.0.50', 'detalhes' => ['usuario_logado' => 'EMPRESA\\recepcao']],
            'MASS_FILE_CHANGE',
            'CRITICAL',
            '64 arquivos alterados em 30s em C:\\Users\\recepcao\\Documents -- 12 de 12 arquivos da amostra com conteúdo inválido (EXEMPLO)',
            'somente alerta -- nada foi isolado. Se confirmar o ataque, isole pela ficha do ativo.',
            ['motivo' => 'arquivos da amostra com conteúdo inválido', 'pasta' => 'C:\\Users\\recepcao\\Documents', 'eventos' => 64, 'extensoes_novas' => ['.locked']],
            null,
            $modelo,
            $previa
        );
    }

    /**
     * Endereço do portal nos links: o configurado na Central (acessível de
     * fora da rede do cliente) ou, sem configuração, o endereço por onde o
     * agente chegou ao servidor.
     */
    public static function urlPortal(): string
    {
        $configurado = rtrim(trim((string)(ConfigService::get('seguranca_modulo_padrao_alerta_url_portal', '') ?? '')), '/');
        if ($configurado !== '') {
            return $configurado;
        }

        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host === '') {
            return '';
        }
        $esquema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

        return $esquema . '://' . $host . rtrim(url(''), '/');
    }

    // ------------------------------------------------------------------

    private static function dados(array $ativo, string $tipo, string $severidade, string $resumo, string $decisao, array $detalhes, ?int $eventoId): array
    {
        $servidor = gethostname() ?: 'servidor';
        // Sem nome em Administração > Empresa, identifica pelo servidor.
        $cliente = trim((string)(ConfigService::get('empresa_nome', '') ?? '')) ?: $servidor;
        $portal = self::urlPortal();
        $ativoId = (int)($ativo['id'] ?? 0);
        $codigo = (string)($ativo['codigo_patrimonio'] ?? "#{$ativoId}");
        $nomeMaquina = (string)($ativo['nome'] ?? '');
        $detalhesAtivo = is_array($ativo['detalhes'] ?? null) ? $ativo['detalhes'] : (json_decode((string)($ativo['detalhes'] ?? ''), true) ?: []);
        $rotuloTipo = SegurancaEventoService::rotuloTipo($tipo);

        [$rotuloSeveridade, $cor, $fundo] = match ($severidade) {
            'CRITICAL' => ['CRÍTICO', '#b91c1c', '#fef2f2'],
            'WARNING' => ['AVISO', '#b45309', '#fffbeb'],
            default => ['INFORMATIVO', '#374151', '#f3f4f6'],
        };

        $tecnicos = [];
        foreach (self::ROTULOS_DETALHE as $chave => $rotulo) {
            $valor = $detalhes[$chave] ?? null;
            if ($valor === null || $valor === '' || $valor === []) {
                continue;
            }
            $tecnicos[$rotulo] = is_array($valor) ? array_slice(array_map('strval', $valor), 0, 5) : (string)$valor;
        }

        return [
            'cliente' => $cliente,
            'servidor' => $servidor,
            'portal' => $portal,
            'codigo' => $codigo,
            'nomeMaquina' => $nomeMaquina,
            'ip' => (string)($ativo['ip'] ?? ''),
            'usuario' => (string)($detalhesAtivo['usuario_logado'] ?? ''),
            'quando' => date('d/m/Y H:i:s') . ($eventoId ? " · evento #{$eventoId}" : ''),
            'rotuloTipo' => $rotuloTipo,
            'rotuloSeveridade' => $rotuloSeveridade,
            'cor' => $cor,
            'fundo' => $fundo,
            'resumo' => $resumo,
            'decisao' => $decisao,
            'tecnicos' => $tecnicos,
            'linkMaquina' => $portal !== '' && $ativoId > 0 ? $portal . '/ativos/ver?id=' . $ativoId . '#abaSeguranca' : '',
            'linkCentral' => $portal !== '' ? $portal . '/ativos/seguranca' : '',
            'assunto' => sprintf('[RD Intranet · %s] %s: %s em %s', $cliente, $rotuloSeveridade, $rotuloTipo, $codigo . ($nomeMaquina !== '' ? " ({$nomeMaquina})" : '')),
        ];
    }

    /** @return array{0: ?string, 1: ?string, 2: array<string, array{0: string, 1: string}>} src dos logos + imagens pra embutir */
    private static function logos(bool $previa): array
    {
        $imagens = [];
        $src = static function (string $caminho, string $mime, string $cid) use ($previa, &$imagens): ?string {
            if (!is_file($caminho) || filesize($caminho) > 512 * 1024) {
                return null;
            }
            if ($previa) {
                return 'data:' . $mime . ';base64,' . base64_encode((string)file_get_contents($caminho));
            }
            $imagens[$cid] = [$caminho, $mime];
            return 'cid:' . $cid;
        };

        $ativos = new AtivoService();
        $empresa = $src(AtivoService::caminhoLogoEmpresa(), $ativos->logoEmpresaMime(), self::CID_LOGO_EMPRESA);
        $sistema = $ativos->logoSistemaConfigurada()
            ? $src(AtivoService::caminhoLogoSistema(), $ativos->logoSistemaMime(), self::CID_LOGO_SISTEMA)
            : $src(AtivoService::caminhoLogoSistemaPadrao(), 'image/png', self::CID_LOGO_SISTEMA);

        return [$empresa, $sistema, $imagens];
    }

    private static function e($v): string
    {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }

    private static function linhas(array $d, bool $comTecnicos): string
    {
        $linha = static fn (string $rotulo, string $valorHtml) =>
            '<tr><td style="padding:5px 12px 5px 0;color:#6b7280;font-size:13px;width:140px;vertical-align:top">' . $rotulo . '</td>'
            . '<td style="padding:5px 0;color:#111827;font-size:13px;vertical-align:top">' . $valorHtml . '</td></tr>';
        $e = [self::class, 'e'];

        $html = $linha('Cliente', '<strong>' . $e($d['cliente']) . '</strong>')
            . $linha('Servidor', $e($d['servidor']) . ($d['portal'] !== '' ? ' &middot; <a href="' . $e($d['portal']) . '" style="color:' . self::COR_PRIMARIA . '">' . $e($d['portal']) . '</a>' : ''))
            . $linha('Máquina', '<strong>' . $e($d['codigo']) . '</strong>' . ($d['nomeMaquina'] !== '' ? ' &middot; ' . $e($d['nomeMaquina']) : '') . ($d['ip'] !== '' ? ' &middot; ' . $e($d['ip']) : ''))
            . ($d['usuario'] !== '' ? $linha('Usuário logado', $e($d['usuario'])) : '')
            . $linha('Quando', $e($d['quando']));

        if ($comTecnicos) {
            foreach ($d['tecnicos'] as $rotulo => $valor) {
                $texto = is_array($valor) ? implode('<br>', array_map($e, $valor)) : $e($valor);
                $html .= $linha($e($rotulo), '<span style="font-family:Consolas,monospace;font-size:12px">' . $texto . '</span>');
            }
        }

        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $html . '</table>';
    }

    private static function botoes(array $d, string $corPrimaria = self::COR_PRIMARIA): string
    {
        $botao = static fn (string $texto, string $href, bool $primario) => $href === '' ? '' :
            '<a href="' . self::e($href) . '" style="display:inline-block;margin:0 8px 8px 0;padding:10px 18px;border-radius:6px;font-size:14px;font-weight:600;text-decoration:none;'
            . ($primario ? 'background:' . $corPrimaria . ';color:#ffffff' : 'background:#ffffff;color:#111827;border:1px solid #d1d5db') . '">' . $texto . '</a>';

        return $botao('Abrir a máquina', $d['linkMaquina'], true) . $botao('Central de Segurança', $d['linkCentral'], false);
    }

    private static function envolver(string $conteudo, string $fundo = self::COR_FUNDO): string
    {
        return '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"></head>'
            . '<body style="margin:0;padding:0;background:' . $fundo . ';font-family:Segoe UI,Arial,sans-serif">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:' . $fundo . ';padding:24px 12px"><tr><td align="center">'
            . $conteudo
            . '</td></tr></table></body></html>';
    }

    private static function rodape(array $d): string
    {
        return 'Enviado automaticamente pelo RD Intranet de ' . self::e($d['cliente']) . ' (' . self::e($d['servidor']) . '). '
            . 'Para marcar como falso positivo ou ajustar destinatários: Ativos &rsaquo; Central de Segurança.';
    }

    /** 1. Sistema: cabeçalho escuro do RD Intranet, logo da empresa num selo claro à direita. */
    private static function htmlSistema(array $d, ?string $logoEmpresa): string
    {
        $e = [self::class, 'e'];
        $marcaEmpresa = $logoEmpresa
            ? '<span style="display:inline-block;background:#ffffff;border-radius:6px;padding:4px 8px"><img src="' . $e($logoEmpresa) . '" alt="' . $e($d['cliente']) . '" height="32" style="display:block;height:32px;max-width:140px"></span>'
            : '';

        return self::envolver(
            '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;background:#ffffff;border-radius:10px;overflow:hidden;border:1px solid #e5e7eb">'
            . '<tr><td style="background:' . self::COR_MENU . ';padding:16px 24px">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>'
            . '<td style="color:#ffffff;font-size:18px;font-weight:700">RD<span style="font-weight:400">.Intranet</span>'
            . '<div style="color:#9ca3af;font-size:12px;font-weight:400;margin-top:2px">Central de Segurança &middot; ' . $e($d['cliente']) . '</div></td>'
            . '<td align="right">' . $marcaEmpresa . '</td>'
            . '</tr></table></td></tr>'
            . '<tr><td style="background:' . $d['fundo'] . ';border-left:4px solid ' . $d['cor'] . ';padding:16px 24px">'
            . '<div style="color:' . $d['cor'] . ';font-size:12px;font-weight:700;letter-spacing:.5px">' . $d['rotuloSeveridade'] . ' &middot; ALERTA DE SEGURANÇA</div>'
            . '<div style="color:#111827;font-size:20px;font-weight:700;margin-top:4px">' . $e($d['rotuloTipo']) . '</div>'
            . '<div style="color:#374151;font-size:14px;margin-top:6px">' . $e($d['resumo']) . '</div></td></tr>'
            . '<tr><td style="padding:16px 24px 0 24px"><div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:12px 14px;font-size:13px;color:#111827"><strong>Resposta:</strong> ' . $e($d['decisao']) . '</div></td></tr>'
            . '<tr><td style="padding:16px 24px 0 24px">' . self::linhas($d, true) . '</td></tr>'
            . '<tr><td style="padding:20px 24px 12px 24px">' . self::botoes($d) . '</td></tr>'
            . '<tr><td style="padding:14px 24px 20px 24px;border-top:1px solid #e5e7eb;color:#9ca3af;font-size:11px">' . self::rodape($d) . '</td></tr>'
            . '</table>'
        );
    }

    /** 2. Corporativo: branco, logo da empresa em destaque, borda da cor da severidade no topo. */
    private static function htmlCorporativo(array $d, ?string $logoEmpresa, ?string $logoSistema): string
    {
        $e = [self::class, 'e'];
        $marca = $logoEmpresa
            ? '<img src="' . $e($logoEmpresa) . '" alt="' . $e($d['cliente']) . '" height="44" style="display:block;height:44px;max-width:200px">'
            : '<div style="color:#111827;font-size:20px;font-weight:700">' . $e($d['cliente']) . '</div>';
        $rodapeSistema = $logoSistema
            ? '<img src="' . $e($logoSistema) . '" alt="RD Intranet" height="18" style="height:18px;vertical-align:middle;margin-right:6px">'
            : '';

        return self::envolver(
            '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;background:#ffffff;border-radius:10px;overflow:hidden;border:1px solid #e5e7eb;border-top:5px solid ' . $d['cor'] . '">'
            . '<tr><td style="padding:22px 28px 10px 28px">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>'
            . '<td>' . $marca . ($logoEmpresa ? '<div style="color:#6b7280;font-size:12px;margin-top:6px">' . $e($d['cliente']) . '</div>' : '') . '</td>'
            . '<td align="right" style="vertical-align:top"><span style="display:inline-block;background:' . $d['fundo'] . ';color:' . $d['cor'] . ';border:1px solid ' . $d['cor'] . ';border-radius:999px;padding:4px 12px;font-size:12px;font-weight:700">' . $d['rotuloSeveridade'] . '</span></td>'
            . '</tr></table></td></tr>'
            . '<tr><td style="padding:10px 28px 0 28px">'
            . '<div style="color:#111827;font-size:22px;font-weight:700">' . $e($d['rotuloTipo']) . '</div>'
            . '<div style="color:#4b5563;font-size:14px;margin-top:6px;line-height:1.5">' . $e($d['resumo']) . '</div></td></tr>'
            . '<tr><td style="padding:16px 28px 0 28px"><div style="border-left:3px solid ' . self::COR_PRIMARIA . ';padding:4px 0 4px 12px;font-size:13px;color:#111827"><strong>Resposta aplicada:</strong> ' . $e($d['decisao']) . '</div></td></tr>'
            . '<tr><td style="padding:18px 28px 0 28px">' . self::linhas($d, true) . '</td></tr>'
            . '<tr><td style="padding:22px 28px 14px 28px">' . self::botoes($d) . '</td></tr>'
            . '<tr><td style="background:#f9fafb;padding:14px 28px;border-top:1px solid #e5e7eb;color:#9ca3af;font-size:11px">' . $rodapeSistema . self::rodape($d) . '</td></tr>'
            . '</table>',
            '#eef1f5'
        );
    }

    /** 3. Compacto: uma coluna estreita, só o essencial, pra celular. */
    private static function htmlCompacto(array $d, ?string $logoEmpresa): string
    {
        $e = [self::class, 'e'];
        $marca = $logoEmpresa
            ? '<img src="' . $e($logoEmpresa) . '" alt="" height="22" style="height:22px;vertical-align:middle;margin-right:8px">'
            : '';
        $fato = static fn (string $rotulo, string $valor) => $valor === '' ? '' :
            '<div style="font-size:13px;color:#111827;margin:3px 0"><span style="color:#6b7280">' . $rotulo . ':</span> ' . $valor . '</div>';

        return self::envolver(
            '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:460px;background:#ffffff;border-radius:10px;border:1px solid #e5e7eb">'
            . '<tr><td style="padding:14px 18px;border-bottom:1px solid #f1f5f9;font-size:13px;color:#374151;font-weight:600">' . $marca . $e($d['cliente']) . '</td></tr>'
            . '<tr><td style="padding:16px 18px 4px 18px">'
            . '<span style="display:inline-block;background:' . $d['cor'] . ';color:#ffffff;border-radius:4px;padding:2px 8px;font-size:11px;font-weight:700">' . $d['rotuloSeveridade'] . '</span>'
            . '<div style="color:#111827;font-size:18px;font-weight:700;margin-top:8px">' . $e($d['rotuloTipo']) . '</div>'
            . '<div style="color:#374151;font-size:14px;margin-top:6px;line-height:1.45">' . $e($d['resumo']) . '</div></td></tr>'
            . '<tr><td style="padding:10px 18px">'
            . $fato('Máquina', '<strong>' . $e($d['codigo']) . '</strong>' . ($d['nomeMaquina'] !== '' ? ' ' . $e($d['nomeMaquina']) : ''))
            . $fato('Usuário', $e($d['usuario']))
            . $fato('Servidor', $e($d['servidor']))
            . $fato('Quando', $e($d['quando']))
            . $fato('Resposta', $e($d['decisao']))
            . '</td></tr>'
            . '<tr><td style="padding:6px 18px 16px 18px">' . ($d['linkMaquina'] !== ''
                ? '<a href="' . $e($d['linkMaquina']) . '" style="display:block;text-align:center;background:' . $d['cor'] . ';color:#ffffff;border-radius:6px;padding:11px;font-size:14px;font-weight:600;text-decoration:none">Abrir a máquina</a>'
                : '') . '</td></tr>'
            . '<tr><td style="padding:0 18px 14px 18px;color:#9ca3af;font-size:10px">' . self::rodape($d) . '</td></tr>'
            . '</table>'
        );
    }
}

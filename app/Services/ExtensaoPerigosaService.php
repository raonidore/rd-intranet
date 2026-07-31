<?php

namespace App\Services;

/**
 * Catálogo de extensões consideradas perigosas para o `veto files` do Samba
 * (Deploy Center, "Gerenciador de Arquivos — Extensões perigosas"). Cada
 * compartilhamento com o toggle "Bloquear extensões perigosas"
 * (`samba_compartilhamentos.bloqueio_extensoes`) ligado passa a vetar as
 * extensões marcadas ATIVAS aqui -- antes disso o conjunto era uma lista
 * fixa no código (`SambaTemplate::share()`), sem forma de o admin desativar
 * uma extensão específica ou incluir outra.
 *
 * Guardado como JSON em `configuracoes` (chave `samba_extensoes_perigosas`),
 * formato `[{"ext":"exe","ativo":true}, ...]` -- precisa do estado
 * ativo/inativo por item, o que a lista simples "CSV = permitido" usada em
 * "Extensões permitidas" não comporta.
 */
class ExtensaoPerigosaService
{
    private const CHAVE = 'samba_extensoes_perigosas';

    /** Lista que já era hardcoded em SambaTemplate::share() -- vira o valor padrão pra não mudar nada em quem já usa o bloqueio. */
    private const PADRAO = [
        'exe', 'com', 'bat', 'cmd', 'dll', 'msi', 'scr', 'pif', 'cpl',
        'ps1', 'psm1', 'vbs', 'vbe', 'js', 'jse', 'wsf', 'wsh', 'jar',
        'reg', 'hta', 'lnk', 'apk', 'deb', 'rpm', 'appimage', 'sh', 'bin',
    ];

    /** @return array<int, array{ext: string, ativo: bool}> */
    public static function listar(): array
    {
        $bruto = ConfigService::get(self::CHAVE, '');

        if ($bruto === '' || $bruto === null) {
            return array_map(fn(string $ext) => ['ext' => $ext, 'ativo' => true], self::PADRAO);
        }

        $itens = json_decode($bruto, true);
        if (!is_array($itens)) {
            return array_map(fn(string $ext) => ['ext' => $ext, 'ativo' => true], self::PADRAO);
        }

        return array_values(array_filter(array_map(static function ($item) {
            if (!is_array($item) || empty($item['ext'])) {
                return null;
            }
            return ['ext' => (string)$item['ext'], 'ativo' => !empty($item['ativo'])];
        }, $itens)));
    }

    /** Só as extensões ativas -- usado por SambaTemplate::share() pra montar o "veto files". */
    public static function listarAtivas(): array
    {
        return array_values(array_map(
            fn(array $item) => $item['ext'],
            array_filter(self::listar(), fn(array $item) => $item['ativo'])
        ));
    }

    /**
     * @param array<int, array{ext: string, ativo: bool}> $itens
     * @return array{success: bool, message: string}
     */
    public static function salvar(array $itens): array
    {
        $vistos = [];
        $limpos = [];

        foreach ($itens as $item) {
            $ext = preg_replace('/[^a-z0-9]/', '', strtolower(trim((string)($item['ext'] ?? ''))));
            if ($ext === '' || strlen($ext) > 20 || isset($vistos[$ext])) {
                continue;
            }
            $vistos[$ext] = true;
            $limpos[] = ['ext' => $ext, 'ativo' => !empty($item['ativo'])];
        }

        if (count($limpos) > 200) {
            return ['success' => false, 'message' => 'Muitas extensões na lista (máximo 200).'];
        }

        ConfigService::set(self::CHAVE, json_encode($limpos));

        return ['success' => true, 'message' => 'Extensões perigosas salvas com sucesso.'];
    }
}

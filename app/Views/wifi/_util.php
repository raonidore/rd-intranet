<?php
// Helpers das telas do Wi-Fi (incluído por cada view).

if (!function_exists('wifi_bytes')) {
    function wifi_bytes($b): string
    {
        $b = (float)$b;
        if ($b >= 1073741824) {
            return number_format($b / 1073741824, 1, ',', '.') . ' GB';
        }
        if ($b >= 1048576) {
            return number_format($b / 1048576, 1, ',', '.') . ' MB';
        }

        return number_format($b / 1024, 0, ',', '.') . ' KB';
    }

    function wifi_status(string $status): string
    {
        [$classe, $rotulo] = match ($status) {
            'pendente' => ['text-bg-warning', 'Aguardando liberação'],
            'ativo' => ['text-bg-success', 'Ativo'],
            'recusado' => ['text-bg-secondary', 'Recusado'],
            'bloqueado' => ['text-bg-danger', 'Bloqueado'],
            'expirado' => ['text-bg-light border', 'Vencido'],
            'anonimizado' => ['text-bg-light border', 'Anonimizado'],
            default => ['text-bg-light border', $status],
        };

        return '<span class="badge ' . $classe . '">' . $rotulo . '</span>';
    }

    function wifi_perfil(?string $nome, ?string $cor): string
    {
        $cor = preg_match('/^#[0-9a-fA-F]{6}$/', (string)$cor) ? $cor : '#6c757d';

        return '<span class="badge" style="background:' . $cor . '">' . htmlspecialchars((string)$nome) . '</span>';
    }

    function wifi_banda(?int $kbps): string
    {
        if (!$kbps) {
            return 'sem limite';
        }

        return $kbps >= 1000 ? rtrim(rtrim(number_format($kbps / 1000, 1, ',', ''), '0'), ',') . ' Mbps' : $kbps . ' kbps';
    }

    function wifi_duracao(?int $min): string
    {
        $min = (int)$min;
        if ($min >= 1440 && $min % 1440 === 0) {
            return ($min / 1440) . ' dia(s)';
        }

        return $min >= 60 ? intdiv($min, 60) . 'h' . ($min % 60 ? sprintf('%02d', $min % 60) : '') : $min . ' min';
    }
}

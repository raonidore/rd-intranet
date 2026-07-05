<?php

namespace App\Services;

class SambaMonitorService
{
    public function snapshot(): array
    {
        $output = shell_exec('sudo /opt/rdtecnologia/scripts/monitor_samba_web.sh 2>/dev/null') ?? '';

        $jsonRaw  = $this->extrairSecao($output, 'SMBSTATUS_JSON');
        $discoRaw = $this->extrairSecao($output, 'DISCO');
        $procsRaw = $this->extrairSecao($output, 'SMBD_PROCS');

        $smbdata = json_decode($jsonRaw, true) ?? [];

        return [
            'generated_at'  => date('d/m/Y H:i:s'),
            'sessions'      => $this->parseSessions($smbdata),
            'shares_ativos' => $this->parseShares($smbdata),
            'open_files'    => $this->parseOpenFiles($smbdata),
            'locks'         => $this->parseLocks($smbdata),
            'performance'   => $this->parsePerformance($discoRaw, $procsRaw),
        ];
    }

    private function parseSessions(array $data): array
    {
        $sessions = [];

        foreach ($data['sessions'] ?? [] as $session) {
            $sessions[] = [
                'pid'        => $session['server_id']['pid'] ?? '-',
                'username'   => $session['username'] ?? '-',
                'machine'    => $session['remote_machine'] ?? '-',
                'protocol'   => $session['session_dialect'] ?? '-',
                'encryption' => $session['encryption']['cipher'] ?? '-',
                'signing'    => $session['signing']['cipher'] ?? '-',
            ];
        }

        return $sessions;
    }

    private function parseShares(array $data): array
    {
        $shares   = [];
        $sessions = $data['sessions'] ?? [];

        foreach ($data['tcons'] ?? [] as $tcon) {
            if (($tcon['service'] ?? '') === 'IPC$') {
                continue;
            }

            $sid  = $tcon['session_id'] ?? null;
            $user = '-';
            if ($sid && isset($sessions[$sid])) {
                $user = $sessions[$sid]['username'] ?? '-';
            }

            $shares[] = [
                'service'      => $tcon['service'] ?? '-',
                'machine'      => $tcon['machine'] ?? '-',
                'username'     => $user,
                'connected_at' => $this->formatDate($tcon['connected_at'] ?? ''),
                'encryption'   => $tcon['encryption']['cipher'] ?? '-',
            ];
        }

        return $shares;
    }

    private function parseOpenFiles(array $data): array
    {
        $files    = [];
        $pidToSes = $this->buildPidSessionMap($data);

        foreach ($data['open_files'] ?? [] as $filepath => $filedata) {
            foreach ($filedata['opens'] ?? [] as $open) {
                $pid      = $open['server_id']['pid'] ?? '-';
                $ses      = $pidToSes[$pid] ?? [];
                $filename = $filedata['filename'] ?? basename($filepath);
                if ($filename === '.') {
                    $filename = '(raiz do compartilhamento)';
                }

                $files[] = [
                    'filename'   => $filename,
                    'share_path' => $filedata['service_path'] ?? '-',
                    'pid'        => $pid,
                    'username'   => $ses['username'] ?? '-',
                    'machine'    => $ses['machine'] ?? '-',
                    'access'     => $open['access_mask']['text'] ?? '',
                    'sharemode'  => $open['sharemode']['text'] ?? '-',
                    'oplock'     => !empty($open['oplock']) ? 'Sim' : 'Não',
                    'opened_at'  => $this->formatDate($open['opened_at'] ?? ''),
                ];
            }
        }

        return $files;
    }

    private function parseLocks(array $data): array
    {
        $locks    = [];
        $pidToSes = $this->buildPidSessionMap($data);

        foreach ($data['open_files'] ?? [] as $filepath => $filedata) {
            foreach ($filedata['opens'] ?? [] as $open) {
                $pid       = $open['server_id']['pid'] ?? '-';
                $ses       = $pidToSes[$pid] ?? [];
                $sharemode = $open['sharemode'] ?? [];
                $hasLock   = !($sharemode['READ'] && $sharemode['WRITE'] && $sharemode['DELETE']);

                $locks[] = [
                    'filename'  => $filedata['filename'] ?? basename($filepath),
                    'path'      => $filedata['service_path'] ?? '-',
                    'pid'       => $pid,
                    'username'  => $ses['username'] ?? '-',
                    'machine'   => $ses['machine'] ?? '-',
                    'denymode'  => $sharemode['text'] ?? '-',
                    'rw'        => $open['access_mask']['text'] ?? '',
                    'oplock'    => !empty($open['oplock']) ? 'Sim' : 'Não',
                    'opened_at' => $this->formatDate($open['opened_at'] ?? ''),
                    'locked'    => $hasLock,
                ];
            }
        }

        return $locks;
    }

    private function buildPidSessionMap(array $data): array
    {
        $map = [];
        foreach ($data['sessions'] ?? [] as $session) {
            $pid = $session['server_id']['pid'] ?? null;
            if ($pid) {
                $map[$pid] = [
                    'username' => $session['username'] ?? '-',
                    'machine'  => $session['remote_machine'] ?? '-',
                ];
            }
        }
        return $map;
    }

    private function formatDate(string $date): string
    {
        if (empty($date) || $date === '-') {
            return '-';
        }
        try {
            $dt = new \DateTime($date);
            return $dt->format('d/m/Y H:i:s');
        } catch (\Exception $e) {
            return $date;
        }
    }

    private function parsePerformance(string $discoRaw, string $procsRaw): array
    {
        // Disco
        $diskLines = array_values(array_filter(array_map('trim', explode("\n", $discoRaw))));
        $diskData  = isset($diskLines[1]) ? preg_split('/\s+/', $diskLines[1]) : [];

        $diskTotal   = $diskData[1] ?? '-';
        $diskUsed    = $diskData[2] ?? '-';
        $diskAvail   = $diskData[3] ?? '-';
        $diskPercent = isset($diskData[4]) ? (int)str_replace('%', '', $diskData[4]) : 0;

        // Processos smbd
        $cpu     = 0.0;
        $mem     = 0.0;
        $numProc = 0;

        foreach (array_filter(explode("\n", $procsRaw)) as $linha) {
            $parts = preg_split('/\s+/', trim($linha));
            if (count($parts) >= 4) {
                $cpu += (float)($parts[2] ?? 0);
                $mem += (float)($parts[3] ?? 0);
                $numProc++;
            }
        }

        return [
            'disk_total'   => $diskTotal,
            'disk_used'    => $diskUsed,
            'disk_avail'   => $diskAvail,
            'disk_percent' => $diskPercent,
            'cpu_percent'  => round($cpu, 1),
            'mem_percent'  => round($mem, 1),
            'num_procs'    => $numProc,
        ];
    }

    private function extrairSecao(string $output, string $secao): string
    {
        $pattern = '/### ' . preg_quote($secao, '/') . '\n(.*?)(?=\n### |\z)/s';

        if (preg_match($pattern, $output, $m)) {
            return trim($m[1]);
        }

        return '';
    }
}

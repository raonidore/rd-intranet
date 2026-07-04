<?php

namespace App\Core\Samba\Health;

class SambaHealthEngine
{
    public function analyze(array $inventory): array
    {
        $score = 100;
        $alerts = [];
        $recommendations = [];
        $healthy = [];

        $services = $inventory['services'] ?? [];
        $shares = $inventory['shares'] ?? [];
        $pendencias = $inventory['deploy_pending'] ?? [];

        if (($services['smbd'] ?? '') !== 'active') {
            $score -= 30;
            $alerts[] = [
                'level' => 'danger',
                'title' => 'Serviço SMBD parado',
                'description' => 'O serviço principal do Samba não está ativo.',
                'action' => 'Verificar serviços'
            ];
        } else {
            $healthy[] = 'SMBD ativo';
        }

        if (($services['nmbd'] ?? '') !== 'active') {
            $score -= 10;
            $alerts[] = [
                'level' => 'warning',
                'title' => 'Serviço NMBD não está ativo',
                'description' => 'O serviço de NetBIOS/NMBD não está ativo. Pode não ser crítico em redes modernas, mas deve ser revisado.',
                'action' => 'Verificar serviços'
            ];
        } else {
            $healthy[] = 'NMBD ativo';
        }

        $orfaos = $shares['orfaos_linux'] ?? [];
        $ausentes = $shares['ausentes_linux'] ?? [];

        if (count($orfaos) > 0) {
            $score -= min(20, count($orfaos) * 5);

            $alerts[] = [
                'level' => 'warning',
                'title' => 'Pastas órfãs encontradas',
                'description' => 'Existem pastas no Linux que não estão cadastradas na RD Intranet.',
                'action' => 'Abrir diagnóstico'
            ];

            $recommendations[] = [
                'title' => 'Revisar pastas órfãs',
                'description' => 'Importe as pastas válidas ou mova testes antigos para a lixeira administrativa.',
                'url' => '/samba/diagnostico'
            ];
        } else {
            $healthy[] = 'Sem pastas órfãs';
        }

        if (count($ausentes) > 0) {
            $score -= min(25, count($ausentes) * 10);

            $alerts[] = [
                'level' => 'danger',
                'title' => 'Compartilhamentos sem pasta física',
                'description' => 'Existem registros no banco que não possuem pasta correspondente no Linux.',
                'action' => 'Corrigir no diagnóstico'
            ];

            $recommendations[] = [
                'title' => 'Criar pastas ausentes',
                'description' => 'Use o diagnóstico para recriar a estrutura física dos compartilhamentos ausentes.',
                'url' => '/samba/diagnostico'
            ];
        } else {
            $healthy[] = 'Todos os compartilhamentos possuem pasta física';
        }

        if (count($pendencias) > 0) {
            $score -= 15;

            $alerts[] = [
                'level' => 'warning',
                'title' => 'Alterações pendentes para deploy',
                'description' => 'Existem alterações cadastradas que ainda não foram aplicadas ao Samba.',
                'action' => 'Abrir Central de Configurações'
            ];

            $recommendations[] = [
                'title' => 'Aplicar configurações pendentes',
                'description' => 'Revise e aplique as alterações pendentes na Central de Configurações.',
                'url' => '/deploy'
            ];
        } else {
            $healthy[] = 'Sem alterações pendentes';
        }

        $score = max(0, min(100, $score));

        return [
            'score' => $score,
            'status' => $this->statusLabel($score),
            'level' => $this->statusLevel($score),
            'alerts' => $alerts,
            'recommendations' => $recommendations,
            'healthy' => $healthy,
        ];
    }

    private function statusLabel(int $score): string
    {
        if ($score >= 90) {
            return 'Excelente';
        }

        if ($score >= 70) {
            return 'Atenção';
        }

        return 'Crítico';
    }

    private function statusLevel(int $score): string
    {
        if ($score >= 90) {
            return 'success';
        }

        if ($score >= 70) {
            return 'warning';
        }

        return 'danger';
    }
}

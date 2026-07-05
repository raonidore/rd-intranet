<?php

namespace App\Services;

class ApacheModuloService
{
    private const PROTEGIDOS = ['mpm_prefork', 'php8.3', 'rewrite'];

    private LinuxService $linux;

    public function __construct()
    {
        $this->linux = new LinuxService();
    }

    public function listar(): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/apache_modulos_listar_web.sh');

        $modulos = [];

        foreach (explode("\n", trim($resultado['output'])) as $linha) {
            $linha = trim($linha);

            if ($linha === '') {
                continue;
            }

            [$nome, $estado] = array_pad(explode('|', $linha, 2), 2, '');

            $modulos[] = [
                'nome' => $nome,
                'habilitado' => $estado === 'habilitado',
                'protegido' => in_array($nome, self::PROTEGIDOS, true),
            ];
        }

        return $modulos;
    }

    public function habilitar(string $nome): bool
    {
        return $this->toggle($nome, 'enable');
    }

    public function desabilitar(string $nome): bool
    {
        if (in_array($nome, self::PROTEGIDOS, true)) {
            NotificationService::error("O módulo '{$nome}' é essencial e não pode ser desabilitado por aqui.");
            return false;
        }

        return $this->toggle($nome, 'disable');
    }

    private function toggle(string $nome, string $acao): bool
    {
        $resultado = $this->linux->executarScript(
            '/opt/rdtecnologia/scripts/apache_modulo_toggle_web.sh',
            [$nome, $acao]
        );

        if (!$resultado['success']) {
            NotificationService::error("Erro ao alterar o módulo '{$nome}'.", $resultado['output']);
            return false;
        }

        AuditService::registrar('Apache', 'Módulo', "Módulo {$nome} " . ($acao === 'enable' ? 'habilitado' : 'desabilitado') . '.');
        NotificationService::success("Módulo '{$nome}' " . ($acao === 'enable' ? 'habilitado' : 'desabilitado') . ' com sucesso.', $resultado['output']);

        return true;
    }
}

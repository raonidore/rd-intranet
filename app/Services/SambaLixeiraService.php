<?php

namespace App\Services;

class SambaLixeiraService
{
    private LinuxService $linux;

    public function __construct()
    {
        $this->linux = new LinuxService();
    }

    public function listar(): array
    {
        $resultado = $this->linux->executarScript(
            '/opt/rdtecnologia/scripts/lista_lixeira_samba_web.sh'
        );

        $itens = [];

        foreach (explode("\n", trim($resultado['output'] ?? '')) as $linha) {
            if (!str_contains($linha, '|')) {
                continue;
            }

            [$nome, $caminho, $data, $blocos] = array_pad(explode('|', $linha), 4, '');

            $itens[] = [
                'nome' => $nome,
                'caminho' => $caminho,
                'data' => $data,
                'tamanho_kb' => (int)$blocos,
            ];
        }

        return $itens;
    }

    public function restaurar(string $nome): void
    {
        $resultado = $this->linux->executarScript(
            '/opt/rdtecnologia/scripts/restaura_lixeira_samba_web.sh',
            [$nome]
        );

        if ($resultado['success']) {
            AuditService::registrar('Samba', 'Restaurar lixeira', 'Item '.$nome.' restaurado.');
            NotificationService::success('Item restaurado com sucesso.', $resultado['output']);
            return;
        }

        NotificationService::error('Erro ao restaurar item.', $resultado['output']);
    }

    public function excluirDefinitivo(string $nome): void
    {
        $resultado = $this->linux->executarScript(
            '/opt/rdtecnologia/scripts/exclui_lixeira_samba_web.sh',
            [$nome]
        );

        if ($resultado['success']) {
            AuditService::registrar('Samba', 'Excluir definitivo', 'Item '.$nome.' removido definitivamente.');
            NotificationService::success('Item removido definitivamente.', $resultado['output']);
            return;
        }

        NotificationService::error('Erro ao excluir item.', $resultado['output']);
    }
}

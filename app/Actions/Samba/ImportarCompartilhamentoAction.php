<?php

namespace App\Actions\Samba;

use App\Actions\Contracts\ActionInterface;
use App\Services\SambaRepairService;

class ImportarCompartilhamentoAction implements ActionInterface
{
    public function name(): string
    {
        return 'Importar compartilhamento';
    }

    public function preview(array $payload): array
    {
        return [
            'Importar pasta órfã para o banco da RD Intranet',
            'Nome: '.$payload['nome'],
            'Grupo: '.$payload['grupo'],
            'Caminho: '.$payload['caminho'],
            'Marcar configuração Samba como pendente para deploy',
            'Registrar auditoria'
        ];
    }

    public function execute(array $payload): void
    {
        (new SambaRepairService())->importarPasta(
            $payload['nome'],
            $payload['grupo'],
            $payload['caminho']
        );
    }
}

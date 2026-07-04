<?php

namespace App\Actions\Samba;

use App\Actions\Contracts\ActionInterface;
use App\Services\SambaRepairService;

class MoverPastaParaLixeiraAction implements ActionInterface
{
    public function name(): string
    {
        return 'Mover pasta para lixeira administrativa';
    }

    public function preview(array $payload): array
    {
        return [
            'Mover a pasta física para /srv/samba/.deleted',
            'Pasta: '.$payload['nome'],
            'Nenhum arquivo será apagado definitivamente',
            'Registrar auditoria'
        ];
    }

    public function execute(array $payload): void
    {
        (new SambaRepairService())->moverPastaOrfaParaLixeira(
            $payload['nome']
        );
    }
}

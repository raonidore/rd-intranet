<?php

namespace App\Services;

class SambaDiscoveryService
{
    private SambaDiagnosticoService $diagnostico;

    public function __construct()
    {
        $this->diagnostico = new SambaDiagnosticoService();
    }

    public function listarPastas(): array
    {
        $dados = $this->diagnostico->executar();

        return $dados['pastas'] ?? [];
    }

    public function compararBancoLinux(): array
    {
        $dados = $this->diagnostico->executar();

        return $dados['comparacao'] ?? [];
    }

    public function servicos(): array
    {
        $dados = $this->diagnostico->executar();

        return $dados['servicos'] ?? [];
    }

    public function testparm(): array
    {
        $dados = $this->diagnostico->executar();

        return $dados['testparm'] ?? [];
    }

    public function sessoes(): string
    {
        $dados = $this->diagnostico->executar();

        return $dados['smbstatus'] ?? '';
    }

    public function logs(): string
    {
        $dados = $this->diagnostico->executar();

        return $dados['logs'] ?? '';
    }
}

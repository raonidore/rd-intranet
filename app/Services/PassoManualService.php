<?php

namespace App\Services;

use App\Repositories\PassoManualRepository;

class PassoManualService
{
    private PassoManualRepository $repo;

    public function __construct()
    {
        $this->repo = new PassoManualRepository();
    }

    /**
     * @return array<int, array{chave: string, titulo: string, descricao: string, comando: string, status: string, confirmado_em: ?string, confirmado_por_nome: ?string}>
     */
    public function listar(): array
    {
        $confirmacoes = $this->repo->confirmacoes();
        $itens = [];

        foreach (PassoManualCatalogo::itens() as $item) {
            $confirmacao = $confirmacoes[$item['chave']] ?? null;

            $autoDetectado = null;
            if (isset($item['verificar']) && is_callable($item['verificar'])) {
                try {
                    $autoDetectado = ($item['verificar'])();
                } catch (\Throwable $e) {
                    $autoDetectado = null;
                }
            }

            if ($autoDetectado === true) {
                $status = 'auto';
            } elseif ($confirmacao) {
                $status = 'manual';
            } else {
                $status = 'pendente';
            }

            $itens[] = [
                'chave' => $item['chave'],
                'titulo' => $item['titulo'],
                'descricao' => $item['descricao'],
                'comando' => $item['comando'],
                'status' => $status,
                'confirmado_em' => $confirmacao['confirmado_em'] ?? null,
                'confirmado_por_nome' => $confirmacao['confirmado_por_nome'] ?? null,
            ];
        }

        return $itens;
    }

    public function pendentes(): int
    {
        $pendentes = 0;
        foreach ($this->listar() as $item) {
            if ($item['status'] === 'pendente') {
                $pendentes++;
            }
        }

        return $pendentes;
    }

    public function confirmar(string $chave, ?int $usuarioId): void
    {
        $this->repo->confirmar($chave, $usuarioId);
    }

    public function desconfirmar(string $chave): void
    {
        $this->repo->desconfirmar($chave);
    }
}

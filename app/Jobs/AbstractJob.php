<?php

namespace App\Jobs;

use App\Jobs\Contracts\JobInterface;

abstract class AbstractJob implements JobInterface
{
    protected array $steps = [];

    protected function addStep(string $titulo, callable $callback): void
    {
        $this->steps[] = [
            'titulo' => $titulo,
            'callback' => $callback
        ];
    }

    public function execute(): array
    {
        $resultado = [];

        foreach ($this->steps as $step) {

            $inicio = microtime(true);

            try {

                $retorno = call_user_func($step['callback']);

                $resultado[] = [
                    'step' => $step['titulo'],
                    'success' => true,
                    'tempo' => round(microtime(true) - $inicio, 3),
                    'resultado' => $retorno
                ];

            } catch (\Throwable $e) {

                $resultado[] = [
                    'step' => $step['titulo'],
                    'success' => false,
                    'tempo' => round(microtime(true) - $inicio, 3),
                    'erro' => $e->getMessage()
                ];

                break;
            }
        }

        return $resultado;
    }
}
